<?php

namespace App\Services;

use App\Models\MonRekonLock;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * "Lock Data OCF": mengambil snapshot data dashboard Monitoring Rekonsiliasi
 * untuk 1 OCF dan mengunci-nya di periode tanggal lock (hari ini).
 *
 * Yang di-snapshot (semuanya lewat MonitoringRekonsiliasiService, jadi angkanya
 * IDENTIK dengan yang tampil di dashboard):
 *  - Qty tiap stage: Total Contract, Cutting, Sewing, QC, Packing, CT-PAT
 *    (stage Warehouse setelah Packing), Sabkon, Warehouse Sabkon, Shipment,
 *    plus loss & balance garment stock.
 *  - Fabric Achievement -> Rekap per Fabric (order, received, out WIP req/doc,
 *    stock, harga total + persentasenya).
 *  - Buyer, Style, OCF.
 *
 * Jenis lock: 'partial', 'shortship', atau 'complete' (dipilih user lewat SweetAlert).
 *
 * Mode lock (2 mode):
 *  - 'ocf' : kunci = OCF terpilih (perilaku lama, filter dashboard dipakai apa adanya).
 *  - 'cpo' : kunci = CPO (uraian) terpilih. Snapshot mencakup SELURUH OCF di dalam
 *            CPO itu, jadi filter dashboard dipersempit hanya ke `uraian`
 *            (ocf / sub_ref / negara dibuang). Kolom `cpo_no` = kode CPO (kunci lock),
 *            kolom `ocf_no` = nomor OCF milik CPO itu (dipisah koma), `lock_mode` = 'cpo'.
 */
class RekonLockService
{
    /** Filter dashboard yang ikut disimpan sebagai jejak (filters_json). */
    private const FILTER_KEYS = ['brand', 'style', 'ocf', 'sub_ref', 'uraian', 'negara'];

    /**
     * @param  array       $filters   Filter dashboard (brand, style, ocf, sub_ref, uraian, negara).
     * @param  string      $lockType  partial | shortship | complete
     * @param  string|null $lockedBy  Nama/ID user yang melakukan lock.
     * @param  string      $mode      ocf | cpo
     */
    public function lock(array $filters, string $lockType, ?string $lockedBy = null, string $mode = MonRekonLock::MODE_OCF): MonRekonLock
    {
        $mode = MonRekonLock::normalizeMode($mode);
        $isCpo = $mode === MonRekonLock::MODE_CPO;

        $lockType = strtolower(trim($lockType));
        if (!in_array($lockType, MonRekonLock::types(), true)) {
            throw new InvalidArgumentException('Lock type must be "partial", "shortship", or "complete".');
        }

        $filters = $this->cleanFilters($filters);

        if ($isCpo) {
            // Lock per CPO: seluruh OCF dalam CPO -> hanya filter uraian yang dipakai.
            $cpo = $filters['uraian'] ?? '';
            if ($cpo === '') {
                throw new InvalidArgumentException('Please select a CPO before locking.');
            }
            $filters     = ['uraian' => $cpo];
            $key         = $cpo;
            $ocfNumbers  = $this->ocfNumbersForCpo($cpo);
        } else {
            $key = $filters['ocf'] ?? '';
            if ($key === '') {
                throw new InvalidArgumentException('Please select an OCF before locking.');
            }
        }
        $ocf   = $key; // kunci lock: nomor OCF (mode ocf) / kode CPO (mode cpo)
        $ocfNumbers ??= [];
        // Kolom ocf_no: mode ocf = nomor OCF; mode cpo = daftar nomor OCF milik CPO (maks 450 char).
        $ocfColumn = $isCpo ? $this->joinOcfNumbers($ocfNumbers) : $key;
        $cpoNo     = $isCpo ? $key : null;
        $label = MonRekonLock::modeLabel($mode);

        $lockDate = Carbon::today();

        $exists = MonRekonLock::query()
            ->mode($mode)
            ->where($isCpo ? 'cpo_no' : 'ocf_no', $key)
            ->whereDate('lock_date', $lockDate->toDateString())
            ->where('lock_type', $lockType)
            ->exists();
        if ($exists) {
            throw new RuntimeException(
                "{$label} {$key} is already locked ({$lockType}) on " . $lockDate->format('d-m-Y') . '.'
            );
        }

        // ===== Ambil data dari service dashboard (sumber kebenaran tunggal) =====
        $svc      = MonitoringRekonsiliasiService::make($filters);
        $pipeline = $svc->productionPipeline();
        $fabrics  = $svc->materialAchievement()
            ->where('material_group', 'fabric')
            ->values();

        // Mode CPO: NEED dari materialAchievement() tidak ter-scope ke CPO (code_prod LIKE '%%'
        // => semua work order), jadi need_qty / order_pct / received_pct dihitung ulang
        // hanya dari work order milik OCF di dalam CPO ini. (Persentase tidak berbasis NEED
        // lagi -- lihat aturan di MonitoringRekonsiliasiService::materialAchievement().)
        if ($isCpo && !empty($ocfNumbers)) {
            $needByCode = $this->needByBarangForOcfs($ocfNumbers);
            $fabrics = $fabrics->map(function ($f) use ($needByCode) {
                $need = (float) ($needByCode[$f->barang_code] ?? 0);
                $f->need_qty = $need;
                return $f;
            });
        }

        $dept = collect($pipeline['departments'] ?? [])
            ->mapWithKeys(fn($d) => [(string) $d->department_id => (float) $d->jumlah]);

        $contract = (float) ($pipeline['contract'] ?? 0);
        $shipment = (float) ($pipeline['shipment'] ?? 0);

        // Short ship = kontrak yang belum terkirim (tidak pernah negatif).
        $shortShip = max(0.0, $contract - $shipment);

        // Sisa fabric (KGM) = total stock gudang semua fabric.
        $sisaFabricKg = (float) $fabrics->sum('stock_qty');

        // Estimasi nilai sisa fabric (Rp) = harga_total x (stock / received) per fabric.
        $sisaFabricValue = (float) $fabrics->sum(function ($f) {
            $received = (float) $f->received_qty;
            return $received > 0 ? (float) $f->harga_total * ((float) $f->stock_qty / $received) : 0;
        });

        [$buyer, $style] = $this->resolveBuyerStyle($filters, $key, $isCpo);

        try {
            return DB::transaction(function () use (
                $filters,
                $mode,
                $isCpo,
                $ocfNumbers,
                $ocfColumn,
                $cpoNo,
                $lockType,
                $lockedBy,
                $lockDate,
                $ocf,
                $buyer,
                $style,
                $pipeline,
                $dept,
                $contract,
                $shipment,
                $shortShip,
                $sisaFabricKg,
                $sisaFabricValue,
                $fabrics
            ) {
                $lock = MonRekonLock::create([
                    'lock_mode'    => $mode,
                    'lock_date'    => $lockDate->toDateString(),
                    'lock_type'    => $lockType,
                    'ocf_no'       => $ocfColumn,
                    'cpo_no'       => $cpoNo,
                    'buyer'        => $buyer,
                    'style'        => $style,
                    'sub_ref'      => $filters['sub_ref'] ?? null,
                    'uraian'       => $filters['uraian'] ?? null, // mode cpo: kode CPO
                    'negara'       => $filters['negara'] ?? null,
                    'filters_json' => json_encode($isCpo ? $filters + ['ocf_list' => $ocfNumbers] : $filters, JSON_UNESCAPED_UNICODE),

                    'qty_contract'         => $contract,
                    'qty_cutting'          => $dept->get('Cutting', 0),
                    'qty_sewing'           => $dept->get('Sewing', 0),
                    'qty_qc'               => $dept->get('QC', 0),
                    'qty_packing'          => $dept->get('Packing', 0),
                    'qty_ct_pat'           => $dept->get('Warehouse', 0),
                    'qty_dest_sewing'      => (float) ($pipeline['dest_sewing'] ?? 0),
                    'qty_dest_packing'     => (float) ($pipeline['dest_packing'] ?? 0),
                    'qty_sabkon'           => (float) ($pipeline['sabkon_pabrik_luar'] ?? 0),
                    'qty_sabkon_warehouse' => (float) ($pipeline['sabkon_warehouse'] ?? 0),
                    'qty_shipment'         => $shipment,

                    'loss_cutting'          => (float) ($pipeline['loss_cutting'] ?? 0),
                    'loss_sewing'           => (float) ($pipeline['loss_sewing'] ?? 0),
                    'loss_packing'          => (float) ($pipeline['loss_packing'] ?? 0),
                    'total_loss'            => (float) ($pipeline['total_loss'] ?? 0),
                    'total_loss_pct'        => (float) ($pipeline['loss_pct'] ?? 0),
                    'shipment_loss'         => (float) ($pipeline['shipment_loss'] ?? 0),
                    'balance_garment_stock' => (float) ($pipeline['balance_garment_stock'] ?? 0),

                    'short_ship_pcs'    => $shortShip,
                    'sisa_fabric_kg'    => $sisaFabricKg,
                    'sisa_fabric_value' => $sisaFabricValue,
                    'cm_per_pcs'        => $isCpo ? $this->cmPerPcsForCpo($ocfNumbers) : $this->cmPerPcs($filters),

                    'locked_by' => $lockedBy,
                    'locked_at' => now(),
                ]);

                $rows = $fabrics->map(fn($f) => [
                    'lock_id'      => $lock->id,
                    'barang_code'  => $f->barang_code,
                    'barang_name'  => $f->barang_name,
                    'need_qty'     => (float) $f->need_qty,
                    'order_qty'    => (float) $f->order_qty,
                    'received_qty' => (float) $f->received_qty,
                    'out_req_qty'  => (float) $f->out_req_qty,
                    'out_doc_qty'  => (float) $f->out_doc_qty,
                    'stock_qty'    => (float) $f->stock_qty,
                    'harga_total'  => (float) $f->harga_total,
                    'order_pct'    => (float) $f->order_pct,
                    'received_pct' => (float) $f->received_pct,
                    'out_req_pct'  => (float) $f->out_req_pct,
                    'out_doc_pct'  => (float) $f->out_doc_pct,
                    'stock_pct'    => (float) $f->stock_pct,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ])->all();

                // SQL Server: batas 2100 parameter -> insert per potongan kecil.
                foreach (array_chunk($rows, 50) as $chunk) {
                    DB::table('mon_rekon_lock_fabrics')->insert($chunk);
                }

                return $lock->load('fabrics');
            });
        } catch (QueryException $e) {
            // Unique index (ocf_no|cpo_no, lock_date, lock_type) -- race dua klik bersamaan.
            if (str_contains($e->getMessage(), 'uq_mon_rekon_locks_')) {
                throw new RuntimeException("{$label} {$key} is already locked ({$lockType}) on this date.");
            }
            throw $e;
        }
    }

    private function cleanFilters(array $filters): array
    {
        $out = [];
        foreach (self::FILTER_KEYS as $key) {
            $v = trim((string) ($filters[$key] ?? ''));
            if ($v !== '') {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    /**
     * Buyer/Style: pakai filter kalau ada, kalau tidak ambil dari mon_orders
     * berdasarkan OCF (mode ocf) atau uraian/CPO (mode cpo).
     */
    private function resolveBuyerStyle(array $filters, string $key, bool $isCpo = false): array
    {
        $buyer = $filters['brand'] ?? null;
        $style = $filters['style'] ?? null;

        if ($buyer === null || $style === null) {
            $orders = DB::table('mon_orders')
                ->where($isCpo ? 'uraian' : 'ocf_no', $key)
                ->select('brand', 'style')
                ->distinct()
                ->get();

            $buyer ??= $orders->pluck('brand')->filter()->unique()->take(3)->implode(', ') ?: null;
            $style ??= $orders->pluck('style')->filter()->unique()->take(3)->implode(', ') ?: null;
        }

        return [$buyer, $style];
    }

    /**
     * CM cost per pcs = SUM(nilai_barang) / SUM(jumlah_doc) dokumen BC 3.0 milik OCF
     * (rumus yang sama dengan kolom "CM Cost/Pce" di Detail Data -> Shipment By Date).
     * Dipakai Summary Rekonsiliasi untuk mengestimasi nilai dampak (Rp).
     */
    private function cmPerPcs(array $filters): float
    {
        $query = DB::table('mon_shipments')
            ->whereRaw("UPPER(LTRIM(RTRIM(jenis_doc))) = 'BC 3.0'")
            ->whereNotNull('no_ps')
            ->whereRaw('UPPER(no_ps) LIKE ?', ['%' . strtoupper($filters['ocf']) . '%']);

        if (!empty($filters['sub_ref'])) {
            $query->whereRaw('UPPER(no_ps) LIKE ?', ['%' . strtoupper($filters['sub_ref']) . '%']);
        }

        $row = $query->selectRaw('SUM(nilai_barang) as nilai, SUM(jumlah_doc) as doc')->first();
        $doc = (float) ($row->doc ?? 0);

        return $doc > 0 ? round((float) $row->nilai / $doc, 4) : 0.0;
    }

    /** NEED per barang_code = SUM(jumlah_prod * cons) work order milik OCF-OCF tsb. */
    private function needByBarangForOcfs(array $ocfNumbers): array
    {
        $need = [];

        // SQL Server: batas 2100 parameter -> potong per 50 OCF.
        foreach (array_chunk($ocfNumbers, 50) as $chunk) {
            $rows = DB::table('mon_work_orders')
                ->where(function ($q) use ($chunk) {
                    foreach ($chunk as $ocf) {
                        $q->orWhereRaw('UPPER(code_prod) LIKE ?', ['%' . strtoupper($ocf) . '%']);
                    }
                })
                ->select('barang_code')
                ->selectRaw('SUM(jumlah_prod * cons) as request')
                ->groupBy('barang_code')
                ->get();

            foreach ($rows as $r) {
                $need[$r->barang_code] = ($need[$r->barang_code] ?? 0) + (float) $r->request;
            }
        }

        return $need;
    }

    /** Daftar nomor OCF (distinct, urut) milik 1 CPO, dari mon_orders.uraian = CPO. */
    private function ocfNumbersForCpo(string $cpo): array
    {
        return DB::table('mon_orders')
            ->where('uraian', $cpo)
            ->whereNotNull('ocf_no')
            ->distinct()
            ->pluck('ocf_no')
            ->map(fn($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** Gabungkan nomor OCF dipisah koma, dipotong agar muat kolom ocf_no (450 char). */
    private function joinOcfNumbers(array $ocfNumbers): string
    {
        $joined = implode(', ', $ocfNumbers);

        return mb_strlen($joined) > 450 ? mb_substr($joined, 0, 447) . '...' : $joined;
    }

    /**
     * CM cost per pcs untuk 1 CPO = SUM(nilai_barang) / SUM(jumlah_doc) dokumen BC 3.0
     * milik SEMUA OCF di dalam CPO tsb (OCF diambil dari mon_orders.uraian = CPO).
     */
    private function cmPerPcsForCpo(array $ocfNumbers): float
    {
        $ocfList = collect($ocfNumbers)
            ->map(fn($v) => strtoupper(trim((string) $v)))
            ->filter()
            ->unique()
            ->values();

        if ($ocfList->isEmpty()) {
            return 0.0;
        }

        $nilai = 0.0;
        $doc   = 0.0;

        // SQL Server: batas 2100 parameter -> potong per 50 OCF.
        foreach ($ocfList->chunk(50) as $chunk) {
            $row = DB::table('mon_shipments')
                ->whereRaw("UPPER(LTRIM(RTRIM(jenis_doc))) = 'BC 3.0'")
                ->whereNotNull('no_ps')
                ->where(function ($q) use ($chunk) {
                    foreach ($chunk as $ocf) {
                        $q->orWhereRaw('UPPER(no_ps) LIKE ?', ['%' . $ocf . '%']);
                    }
                })
                ->selectRaw('SUM(nilai_barang) as nilai, SUM(jumlah_doc) as doc')
                ->first();

            $nilai += (float) ($row->nilai ?? 0);
            $doc   += (float) ($row->doc ?? 0);
        }

        return $doc > 0 ? round($nilai / $doc, 4) : 0.0;
    }
}
