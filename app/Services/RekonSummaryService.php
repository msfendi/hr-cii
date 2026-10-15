<?php

namespace App\Services;

use App\Models\MonRekonLock;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menghitung "Summary Rekonsiliasi" (executive dashboard) dari tabel lock
 * (mon_rekon_locks), difilter berdasarkan PERIODE `lock_date`.
 *
 * Aturan hitung:
 *  - Satu OCF bisa di-lock berkali-kali (mis. partial lalu shortship). Snapshot
 *    berisi total-to-date, jadi menjumlah semua snapshot akan double count.
 *    Karena itu yang dipakai hanya lock TERBARU per OCF di dalam periode.
 *  - Total Loss     : max(0, -total_loss) -- loss sewing + packing (pcs). Di pipeline
 *                     total_loss = (sewing - dest_sewing) + (packing - dest_packing),
 *                     jadi NEGATIF = pcs hilang (loss, merah di dashboard) dan
 *                     POSITIF = gain; yang dijumlah di summary adalah besar loss-nya.
 *  - Short Ship     : short_ship_pcs, HANYA dari lock berjenis `shortship`
 *                     (lock `partial` berarti sisa kontrak masih akan dikirim).
 *  - Sisa Fabric    : sisa_fabric_kg (kg).
 *  - Grade A/B/C    : hasil import Excel (kosong dianggap 0).
 *  - Pembanding "vs periode sebelumnya" = periode dengan jumlah hari yang sama
 *    tepat sebelum periode terpilih.
 *  - Top 3 Production Loss  : % = total loss / Total Contract (sama seperti stage Balance
 *                     di Work In Process Monitoring Rekonsiliasi), urut dari % terbesar.
 *  - Top 3 Fabric Stock     : % = stock / received (sama seperti Stock % di Fabric
 *                     Achievement), urut dari % terbesar.
 *  - Urutan Top 5 OCF memakai estimasi dampak internal = CM per pcs x
 *    (loss + short ship + grade B + grade C) + estimasi nilai sisa fabric.
 *  - Status & REKOMENDASI & NOTES diisi manual (import Excel, kolom note_*).
 *
 * Mode summary (2 mode):
 *  - 'ocf' : hanya lock ber-`lock_mode` = 'ocf' (kunci = nomor OCF).
 *  - 'cpo' : hanya lock ber-`lock_mode` = 'cpo' (kunci = kode CPO).
 *  Kedua mode tidak dicampur supaya OCF yang sudah ikut di-lock lewat CPO
 *  tidak terhitung dobel. Key output tetap `ocf` (isinya OCF atau CPO sesuai mode).
 */
class RekonSummaryService
{
    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
        private readonly string $mode = MonRekonLock::MODE_OCF,
    ) {}

    public static function make(string|Carbon|null $from, string|Carbon|null $to, ?string $mode = null): self
    {
        $to   = $to ? Carbon::parse($to)->startOfDay() : Carbon::today();
        $from = $from ? Carbon::parse($from)->startOfDay() : $to->copy()->startOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self($from, $to, MonRekonLock::normalizeMode($mode));
    }

    public function build(): array
    {
        $current = $this->metricsPerOcf($this->from, $this->to);

        $days     = (int) $this->from->diffInDays($this->to) + 1;
        $prevTo   = $this->from->copy()->subDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1);
        $previous = $this->metricsPerOcf($prevFrom, $prevTo);

        $totals     = $this->totals($current);
        $prevTotals = $this->totals($previous);

        return [
            'mode'       => $this->mode,
            'mode_label' => MonRekonLock::modeLabel($this->mode),
            'period' => [
                'from'      => $this->from->toDateString(),
                'to'        => $this->to->toDateString(),
                'label'     => $this->from->translatedFormat('d M Y') . ' - ' . $this->to->translatedFormat('d M Y'),
                'prev_from' => $prevFrom->toDateString(),
                'prev_to'   => $prevTo->toDateString(),
            ],
            'kpi'         => $this->kpi($current, $totals, $prevTotals),
            'top3'        => [
                'short_ship'  => $this->topList($current, 'short_ship', 3, $totals['short_ship'], 'ship_pct'),
                'loss'        => $this->topList($current, 'loss', 3, $totals['loss'], 'loss_pct'),
                'sisa_fabric' => $this->topList($current, 'sisa_fabric', 3, $totals['sisa_fabric'], 'sisa_fabric_pct'),
            ],
            'top_impact'      => $this->topImpact($current),
            'notes'           => $this->notes($current),
            'grade_filled'    => $current->filter(fn($r) => $r['grade_filled'])->count(),
        ];
    }

    // ------------------------------------------------------------------
    // Data per OCF (lock terbaru per OCF dalam periode)
    // ------------------------------------------------------------------
    private function metricsPerOcf(Carbon $from, Carbon $to): Collection
    {
        $locks = MonRekonLock::query()
            ->mode($this->mode)
            ->with('fabrics') // data Rekap per Fabric (mon_rekon_lock_fabrics)
            ->whereDate('lock_date', '>=', $from->toDateString())
            ->whereDate('lock_date', '<=', $to->toDateString())
            ->orderBy('lock_date')
            ->orderBy('id')
            ->get();

        // Kunci grup: nomor OCF (mode ocf) atau kode CPO (mode cpo).
        return $locks->groupBy(fn(MonRekonLock $r) => $r->lock_key)->map(function (Collection $rows) {
            /** @var MonRekonLock $l */
            $l = $rows->last(); // lock terbaru

            $loss      = max(0.0, -(float) $l->total_loss); // negatif di pipeline = loss
            // Panel Reject = loss stage Sewing di Work In Process (Chutex) (Cutting -> Sewing:
            // output Sewing - tujuan Sewing). Negatif = loss, jadi diambil besar lossnya saja.
            $panelReject = max(0.0, -(float) $l->loss_sewing);
            $shortShip = $l->lock_type === MonRekonLock::TYPE_SHORTSHIP ? (float) $l->short_ship_pcs : 0.0;
            $gradeA    = (float) ($l->grade_a ?? 0);
            $gradeB    = (float) ($l->grade_b ?? 0);
            $gradeC    = (float) ($l->grade_c ?? 0);

            // Sisa fabric dibaca dari tabel mon_rekon_lock_fabrics (stock_qty); nilai Rp =
            // harga_total x stock/received per fabric. Kalau tidak ada baris fabric,
            // pakai angka yang tersimpan di baris lock.
            $receivedKg = 0.0;
            if ($l->fabrics->isNotEmpty()) {
                $receivedKg = (float) $l->fabrics->sum('received_qty');
                $sisaKg    = (float) $l->fabrics->sum('stock_qty');
                $sisaValue = (float) $l->fabrics->sum(fn($f) => (float) $f->received_qty > 0
                    ? (float) $f->harga_total * ((float) $f->stock_qty / (float) $f->received_qty)
                    : 0);
            } else {
                $sisaKg    = (float) $l->sisa_fabric_kg;
                $sisaValue = (float) $l->sisa_fabric_value;
            }

            // % Production Loss = sama seperti stage Balance di Work In Process (Chutex)
            // pada Monitoring Rekonsiliasi: total loss (sewing + packing) / Total Contract x 100.
            // Disimpan bertanda NEGATIF (loss = minus) agar tampil sama dengan stage Balance.
            $contract = (float) ($l->qty_contract ?? $l->total_contract ?? 0);
            $lossPct  = $contract > 0 ? round(-$loss / $contract * 100, 1) : null;

            // % Fabric Stock = sama seperti kolom Stock % di Fabric Achievement:
            // stock / received (semua fabric OCF/CPO digabung).
            $sisaPct = $receivedKg > 0 ? round($sisaKg / $receivedKg * 100, 1) : null;

            $impact = (float) $l->cm_per_pcs * ($loss + $shortShip + $gradeB + $gradeC)
                + $sisaValue;

            return [
                'lock_id'      => $l->id,
                'ocf'          => $l->lock_key,
                'ocf_numbers'  => $l->lock_mode === MonRekonLock::MODE_CPO ? $l->ocf_no : null,
                'buyer'        => $l->buyer,
                'style'        => $l->style,
                'lock_type'    => $l->lock_type,
                'lock_date'    => $l->lock_date->toDateString(),
                'loss'         => $loss,
                'loss_pct'     => $lossPct,
                'panel_reject' => $panelReject,
                'short_ship'   => $shortShip,
                // % Short Shipment terhadap output Cutting =
                // (qty_cutting - qty_shipment) / qty_cutting x 100
                // (dipakai kartu Top 3 Short Shipment).
                'ship_pct'     => (float) $l->qty_cutting > 0
                    ? round(((float) $l->qty_cutting - (float) $l->qty_shipment) / (float) $l->qty_cutting * 100, 1)
                    : null,
                'grade_a'      => $gradeA,
                'grade_b'      => $gradeB,
                'grade_c'      => $gradeC,
                'sisa_fabric'  => $sisaKg,
                'sisa_fabric_pct' => $sisaPct,
                'impact_value' => round($impact, 2),
                'grade_filled' => $l->grade_a !== null || $l->grade_b !== null || $l->grade_c !== null,
                'note_prioritas' => $l->note_prioritas !== null ? (int) $l->note_prioritas : null,
                'note_status'    => $l->note_status ?: null,
                'note_masalah'   => $l->note_masalah,
                'note_tindakan'  => $l->note_tindakan,
                'note_pic'       => $l->note_pic,
                'note_target'    => $l->note_target,
                'note_estimasi'  => $l->note_estimasi !== null ? (float) $l->note_estimasi : null,
                'has_note'       => $l->note_status || $l->note_masalah || $l->note_tindakan
                    || $l->note_pic || $l->note_target || $l->note_estimasi !== null,
            ];
        })->values();
    }

    private function totals(Collection $rows): array
    {
        return [
            'ocf'         => $rows->count(),
            'partial'     => $rows->where('lock_type', MonRekonLock::TYPE_PARTIAL)->count(),
            'shortship'   => $rows->where('lock_type', MonRekonLock::TYPE_SHORTSHIP)->count(),
            'complete'    => $rows->where('lock_type', MonRekonLock::TYPE_COMPLETE)->count(),
            'loss'        => (float) $rows->sum('loss'),
            'short_ship'  => (float) $rows->sum('short_ship'),
            'grade_a'     => (float) $rows->sum('grade_a'),
            'grade_b'     => (float) $rows->sum('grade_b'),
            'grade_c'     => (float) $rows->sum('grade_c'),
            'sisa_fabric' => (float) $rows->sum('sisa_fabric'),
            'impact'      => (float) $rows->sum('impact_value'),
        ];
    }

    // ------------------------------------------------------------------
    // Bagian dashboard
    // ------------------------------------------------------------------
    private function kpi(Collection $rows, array $t, array $p): array
    {
        $gradeTotal = $t['grade_a'] + $t['grade_b'] + $t['grade_c'];
        $share = fn($v) => $gradeTotal > 0 ? round($v / $gradeTotal * 100, 1) : null;

        $metric = fn(string $key, bool $higherIsBetter = false) => [
            'value'            => $t[$key],
            'prev'             => $p[$key],
            'delta_pct'        => $this->deltaPct($t[$key], $p[$key]),
            'higher_is_better' => $higherIsBetter,
        ];

        return [
            'total_ocf' => [
                'value'     => $t['ocf'],
                'partial'   => $t['partial'],
                'shortship' => $t['shortship'],
                'complete'  => $t['complete'],
            ],
            'loss'        => $metric('loss'),
            'grade_a'     => $metric('grade_a', true) + ['share_pct' => $share($t['grade_a'])],
            'grade_b'     => $metric('grade_b')       + ['share_pct' => $share($t['grade_b'])],
            'grade_c'     => $metric('grade_c')       + ['share_pct' => $share($t['grade_c'])],
            'short_ship'  => $metric('short_ship'),
            'sisa_fabric' => $metric('sisa_fabric'),
        ];
    }

    private function deltaPct(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    /** Top N OCF untuk 1 metrik + kontribusi terhadap total metrik itu. */
    private function topList(Collection $rows, string $key, int $limit, float $total, ?string $pctKey = null): array
    {
        // Kalau $pctKey diisi, urutan Top N mengikuti persentase terbesar (loss/stock: nilai absolut),
        // bukan qty. Data tanpa persentase (basis 0) tidak ikut.
        $rank = $pctKey
            ? $rows->filter(fn($r) => $r[$key] > 0 && $r[$pctKey] !== null)
            ->sortByDesc(fn($r) => $pctKey === 'ship_pct' ? $r[$pctKey] : abs($r[$pctKey]))
            : $rows->filter(fn($r) => $r[$key] > 0)->sortByDesc($key);

        return $rank
            ->take($limit)
            ->values()
            ->map(fn($r) => [
                'ocf'       => $r['ocf'],
                'buyer'     => $r['buyer'],
                'value'     => $r[$key],
                'share_pct' => $total > 0 ? round($r[$key] / $total * 100, 1) : 0,
                'ship_pct'  => $r['ship_pct'] ?? null,
                'pct'       => $pctKey ? $r[$pctKey] : null,
            ])->all();
    }

    /** Top 5 OCF dengan masalah terbesar; Status mengikuti REKOMENDASI & NOTES (manual). */
    private function topImpact(Collection $rows): array
    {
        return $rows->sortByDesc('impact_value')->take(5)->values()->map(fn($r, $i) => [
            'no'          => $i + 1,
            'lock_id'     => $r['lock_id'],
            'ocf'         => $r['ocf'],
            'ocf_numbers' => $r['ocf_numbers'],
            'buyer'       => $r['buyer'],
            'panel_reject' => $r['panel_reject'],
            'grade_a'     => $r['grade_a'],
            'grade_b'     => $r['grade_b'],
            'grade_c'     => $r['grade_c'],
            'short_ship'  => $r['short_ship'],
            'sisa_fabric' => $r['sisa_fabric'],
            'status'      => $r['lock_type'], // status saat lock: partial / shortship / complete
        ])->all();
    }

    /** REKOMENDASI & NOTES (isi manual): urut prioritas, lalu nomor OCF. */
    private function notes(Collection $rows): array
    {
        return $rows->filter(fn($r) => $r['has_note'])
            ->sort(fn($a, $b) => [$a['note_prioritas'] ?? PHP_INT_MAX, $a['ocf']] <=> [$b['note_prioritas'] ?? PHP_INT_MAX, $b['ocf']])
            ->values()
            ->map(fn($r, $i) => [
                'no'        => $r['note_prioritas'] ?? ($i + 1),
                'ocf'       => $r['ocf'],
                'buyer'     => $r['buyer'],
                'status'    => $r['note_status'],
                'masalah'   => $r['note_masalah'],
                'tindakan'  => $r['note_tindakan'],
                'pic'       => $r['note_pic'],
                'target'    => $r['note_target'],
                'estimasi'  => $r['note_estimasi'],
            ])->all();
    }
}