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
 *  - Total Loss     : max(0, total_loss) -- loss sewing + packing (pcs).
 *  - Short Ship     : short_ship_pcs, HANYA dari lock berjenis `shortship`
 *                     (lock `partial` berarti sisa kontrak masih akan dikirim).
 *  - Sisa Fabric    : sisa_fabric_kg (kg).
 *  - Grade A/B/C    : hasil import Excel (kosong dianggap 0).
 *  - Pembanding "vs periode sebelumnya" = periode dengan jumlah hari yang sama
 *    tepat sebelum periode terpilih.
 *  - Urutan Top 5 OCF memakai estimasi dampak internal = CM per pcs x
 *    (loss + short ship + grade B + grade C) + estimasi nilai sisa fabric.
 *  - Status & REKOMENDASI & NOTES diisi manual (import Excel, kolom note_*).
 */
class RekonSummaryService
{
    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {}

    public static function make(string|Carbon|null $from, string|Carbon|null $to): self
    {
        $to   = $to ? Carbon::parse($to)->startOfDay() : Carbon::today();
        $from = $from ? Carbon::parse($from)->startOfDay() : $to->copy()->startOfMonth();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return new self($from, $to);
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
            'period' => [
                'from'      => $this->from->toDateString(),
                'to'        => $this->to->toDateString(),
                'label'     => $this->from->translatedFormat('d M Y') . ' - ' . $this->to->translatedFormat('d M Y'),
                'prev_from' => $prevFrom->toDateString(),
                'prev_to'   => $prevTo->toDateString(),
            ],
            'kpi'         => $this->kpi($current, $totals, $prevTotals),
            'top3'        => [
                'short_ship'  => $this->topList($current, 'short_ship', 3, $totals['short_ship']),
                'loss'        => $this->topList($current, 'loss', 3, $totals['loss']),
                'sisa_fabric' => $this->topList($current, 'sisa_fabric', 3, $totals['sisa_fabric']),
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
            ->whereDate('lock_date', '>=', $from->toDateString())
            ->whereDate('lock_date', '<=', $to->toDateString())
            ->orderBy('lock_date')
            ->orderBy('id')
            ->get();

        return $locks->groupBy('ocf_no')->map(function (Collection $rows) {
            /** @var MonRekonLock $l */
            $l = $rows->last(); // lock terbaru

            $loss      = max(0.0, (float) $l->total_loss);
            $shortShip = $l->lock_type === MonRekonLock::TYPE_SHORTSHIP ? (float) $l->short_ship_pcs : 0.0;
            $gradeA    = (float) ($l->grade_a ?? 0);
            $gradeB    = (float) ($l->grade_b ?? 0);
            $gradeC    = (float) ($l->grade_c ?? 0);
            $sisaKg    = (float) $l->sisa_fabric_kg;

            $impact = (float) $l->cm_per_pcs * ($loss + $shortShip + $gradeB + $gradeC)
                + (float) $l->sisa_fabric_value;

            return [
                'lock_id'      => $l->id,
                'ocf'          => $l->ocf_no,
                'buyer'        => $l->buyer,
                'style'        => $l->style,
                'lock_type'    => $l->lock_type,
                'lock_date'    => $l->lock_date->toDateString(),
                'loss'         => $loss,
                'short_ship'   => $shortShip,
                'grade_a'      => $gradeA,
                'grade_b'      => $gradeB,
                'grade_c'      => $gradeC,
                'sisa_fabric'  => $sisaKg,
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
    private function topList(Collection $rows, string $key, int $limit, float $total): array
    {
        return $rows->filter(fn($r) => $r[$key] > 0)
            ->sortByDesc($key)
            ->take($limit)
            ->values()
            ->map(fn($r) => [
                'ocf'       => $r['ocf'],
                'buyer'     => $r['buyer'],
                'value'     => $r[$key],
                'share_pct' => $total > 0 ? round($r[$key] / $total * 100, 1) : 0,
            ])->all();
    }

    /** Top 5 OCF dengan masalah terbesar; Status mengikuti REKOMENDASI & NOTES (manual). */
    private function topImpact(Collection $rows): array
    {
        return $rows->sortByDesc('impact_value')->take(5)->values()->map(fn($r, $i) => [
            'no'          => $i + 1,
            'lock_id'     => $r['lock_id'],
            'ocf'         => $r['ocf'],
            'buyer'       => $r['buyer'],
            'loss'        => $r['loss'],
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