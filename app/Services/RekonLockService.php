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
 */
class RekonLockService
{
    /** Filter dashboard yang ikut disimpan sebagai jejak (filters_json). */
    private const FILTER_KEYS = ['brand', 'style', 'ocf', 'sub_ref', 'uraian', 'negara'];

    /**
     * @param  array       $filters   Filter dashboard (brand, style, ocf, sub_ref, uraian, negara).
     * @param  string      $lockType  partial | shortship | complete
     * @param  string|null $lockedBy  Nama/ID user yang melakukan lock.
     */
    public function lock(array $filters, string $lockType, ?string $lockedBy = null): MonRekonLock
    {
        $lockType = strtolower(trim($lockType));
        if (!in_array($lockType, MonRekonLock::types(), true)) {
            throw new InvalidArgumentException('Lock type must be "partial", "shortship", or "complete".');
        }

        $filters = $this->cleanFilters($filters);
        $ocf = $filters['ocf'] ?? '';
        if ($ocf === '') {
            throw new InvalidArgumentException('Please select an OCF before locking.');
        }

        $lockDate = Carbon::today();

        $exists = MonRekonLock::query()
            ->where('ocf_no', $ocf)
            ->whereDate('lock_date', $lockDate->toDateString())
            ->where('lock_type', $lockType)
            ->exists();
        if ($exists) {
            throw new RuntimeException(
                "OCF {$ocf} is already locked ({$lockType}) on " . $lockDate->format('d-m-Y') . '.'
            );
        }

        // ===== Ambil data dari service dashboard (sumber kebenaran tunggal) =====
        $svc      = MonitoringRekonsiliasiService::make($filters);
        $pipeline = $svc->productionPipeline();
        $fabrics  = $svc->materialAchievement()
            ->where('material_group', 'fabric')
            ->values();

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

        [$buyer, $style] = $this->resolveBuyerStyle($filters, $ocf);

        try {
            return DB::transaction(function () use (
                $filters,
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
                    'lock_date'    => $lockDate->toDateString(),
                    'lock_type'    => $lockType,
                    'ocf_no'       => $ocf,
                    'buyer'        => $buyer,
                    'style'        => $style,
                    'sub_ref'      => $filters['sub_ref'] ?? null,
                    'uraian'       => $filters['uraian'] ?? null,
                    'negara'       => $filters['negara'] ?? null,
                    'filters_json' => json_encode($filters, JSON_UNESCAPED_UNICODE),

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
                    'cm_per_pcs'        => $this->cmPerPcs($filters),

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
            // Unique index (ocf_no, lock_date, lock_type) -- race dua klik bersamaan.
            if (str_contains($e->getMessage(), 'uq_mon_rekon_locks_ocf_date_type')) {
                throw new RuntimeException("OCF {$ocf} is already locked ({$lockType}) on this date.");
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

    /** Buyer/Style: pakai filter kalau ada, kalau tidak ambil dari mon_orders berdasarkan OCF. */
    private function resolveBuyerStyle(array $filters, string $ocf): array
    {
        $buyer = $filters['brand'] ?? null;
        $style = $filters['style'] ?? null;

        if ($buyer === null || $style === null) {
            $orders = DB::table('mon_orders')
                ->where('ocf_no', $ocf)
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
}
