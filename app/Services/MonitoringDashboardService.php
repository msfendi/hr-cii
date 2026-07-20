<?php

namespace App\Services;

use App\Models\MonPurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Membangun 3 pivot dashboard, mengikuti logika yang sudah ada di workbook Excel:
 *
 *  - ORDER            : SUM(qty_ord) group by uraian, buyer, style          (pivotTable3 "ORDER")
 *  - MATERIAL PURCHASE: SUM(jumlah_order) & SUM(jumlah_doc) group by item,  (pivotTable1 "MATERIAL PURCHASE")
 *                        filter jenis_po IN ('PO','Material Supply')
 *                        + harga_satuan, harga_total, valas (lihat catatan di materialPurchasePivot())
 *  - WORK ORDER        : item BOM yang belum ada PO-nya, replikasi formula
 *                        `STATUS ORDER` = IF(SUMIFS(jumlah_order, CPO-ITEM CODE)=0, 'NOT ORDER','ORDER')
 *                        dimana CPO-ITEM CODE = uraian & " - " & barang_code
 *
 * "MASTER DATA" di file Excel sebenarnya cuma Power Query yang meng-append/merge
 * ORDER + BOM + PO berdasarkan kolom `uraian`. Di sini kita tidak fisikkan tabel
 * gabungan itu (lebih berat & gampang basi) -- tiap pivot langsung JOIN on
 * demand ke tabel yang dibutuhkan saja, hasilnya sama tapi lebih ringan & selalu fresh.
 */
class MonitoringDashboardService
{
    public function __construct(protected array $filters = []) {}

    /**
     * $filters bisa berisi: uraian, buyer, style
     */
    public static function make(array $filters): self
    {
        return new self($filters);
    }

    /** Dropdown filter options, diambil dari tabel ORDER */
    public function filterOptions(): array
    {
        return [
            'uraian' => DB::table('mon_orders')->select('uraian')->distinct()->orderBy('uraian')->pluck('uraian'),
            'buyer'  => DB::table('mon_orders')->whereNotNull('buyer')->select('buyer')->distinct()->orderBy('buyer')->pluck('buyer'),
            'style'  => DB::table('mon_orders')->whereNotNull('style')->select('style')->distinct()->orderBy('style')->pluck('style'),
        ];
    }

    private function applyOrderFilters($query, string $prefix = ''): void
    {
        $col = fn(string $c) => $prefix ? "{$prefix}.{$c}" : $c;

        foreach (['uraian', 'buyer', 'style'] as $field) {
            $this->applyFilterValue($query, $col($field), $field);
        }
    }

    /**
     * Terapkan filter ke $query untuk kolom $column berdasarkan $this->filters[$key].
     * Mendukung baik value tunggal (string, dari select single) maupun array
     * (dari select2 multiple) -- otomatis pakai where() atau whereIn().
     */
    private function applyFilterValue($query, string $column, string $key): void
    {
        $value = $this->filters[$key] ?? null;

        if (is_array($value)) {
            $value = array_values(array_filter($value, fn($v) => $v !== null && $v !== ''));
            if (empty($value)) {
                return;
            }
            $query->whereIn($column, $value);
            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        $query->where($column, $value);
    }

    /**
     * Pivot ORDER: qty order per uraian / buyer / style
     */
    public function orderPivot(): \Illuminate\Support\Collection
    {
        $query = DB::table('mon_orders')
            ->select('uraian', 'buyer', 'style', 'destination')
            ->selectRaw('SUM(qty_ord) as qty_order')
            ->groupBy('uraian', 'buyer', 'style', 'destination')
            ->orderBy('uraian');

        $this->applyOrderFilters($query);

        return $query->get();
    }

    /**
     * Pivot MATERIAL PURCHASE: item (barang), jumlah order, jumlah diterima, sisa,
     * harga_satuan, harga_total, valas.
     * jenis_po dibatasi ke PO / Material Supply.
     * Filter buyer & style dijembatani lewat join ke mon_orders on uraian
     * (satu-satunya kolom yang ada di ketiga sheet).
     *
     * Setiap baris item (barang_code/barang_name) adalah baris SUMMARY (global),
     * dilengkapi `details`: breakdown per spesifikasi+valas di bawahnya,
     * supaya bisa di-expand/collapse di frontend (total detail = total summary).
     *
     * Catatan harga:
     *  - `harga_total` dijumlahkan langsung dari kolom mon_purchase_orders.harga_total.
     *  - `harga_satuan` TIDAK dijumlahkan (menjumlahkan harga satuan antar baris PO
     *    tidak bermakna), melainkan dihitung sebagai rata-rata tertimbang:
     *    total harga_total / total jumlah_order.
     *  - `valas` dikelompokkan per spesifikasi supaya nominal harga tidak
     *    tercampur antar mata uang berbeda. Kalau satu item ternyata pernah
     *    di-PO dengan valas berbeda-beda, baris SUMMARY akan menampilkan semua
     *    valas yang dipakai (dipisah koma) -- rincian per valas tetap bisa
     *    dilihat lewat expand/detail.
     */
    public function materialPurchasePivot(int $limit = 20): \Illuminate\Support\Collection
    {
        $query = DB::table('mon_purchase_orders as po')
            ->leftJoin('mon_orders as o', 'o.uraian', '=', 'po.uraian')
            ->whereIn('po.jenis_po', MonPurchaseOrder::MATERIAL_JENIS_PO)
            ->select('po.barang_code', 'po.barang_name', 'po.spesifikasi', 'po.valas')
            ->selectRaw('SUM(po.jumlah_order) as jumlah_order')
            ->selectRaw('SUM(po.jumlah_doc) as jumlah_diterima')
            ->selectRaw('SUM(po.jumlah_order) - SUM(po.jumlah_doc) as sisa')
            ->selectRaw('SUM(po.harga_total) as harga_total')
            ->groupBy('po.barang_code', 'po.barang_name', 'po.spesifikasi', 'po.valas')
            ->orderBy('po.barang_code')
            ->orderBy('po.spesifikasi');

        $this->applyFilterValue($query, 'po.uraian', 'uraian');
        $this->applyFilterValue($query, 'o.buyer', 'buyer');
        $this->applyFilterValue($query, 'o.style', 'style');

        $rows = $query->get();

        // Group flat rows (per barang_code + spesifikasi + valas) menjadi parent (global)
        // + details (per spesifikasi/valas), lalu urutkan parent berdasarkan jumlah_order terbesar.
        $grouped = $rows->groupBy(fn($r) => $r->barang_code . '||' . $r->barang_name);

        $result = $grouped->map(function ($details, $key) {
            [$code, $name] = explode('||', $key, 2);

            $totalOrder      = (float) $details->sum('jumlah_order');
            $totalHargaTotal = (float) $details->sum('harga_total');

            // Valas yang muncul untuk item ini (umumnya cuma 1, tapi ditampung sebagai
            // daftar kalau ternyata item yang sama pernah di-PO pakai valas berbeda).
            $valasList = $details->pluck('valas')->filter()->unique()->values();

            return (object) [
                'barang_code'     => $code,
                'barang_name'     => $name,
                'jumlah_order'    => $totalOrder,
                'jumlah_diterima' => (float) $details->sum('jumlah_diterima'),
                'sisa'            => (float) $details->sum('sisa'),
                'harga_total'     => $totalHargaTotal,
                'harga_satuan'    => $totalOrder > 0 ? $totalHargaTotal / $totalOrder : 0,
                'valas'           => $valasList->count() > 1 ? $valasList->implode(', ') : $valasList->first(),
                'details'         => $details->map(function ($d) {
                    $qty        = (float) $d->jumlah_order;
                    $hargaTotal = (float) $d->harga_total;

                    return [
                        'spesifikasi'     => $d->spesifikasi,
                        'jumlah_order'    => $qty,
                        'jumlah_diterima' => (float) $d->jumlah_diterima,
                        'sisa'            => (float) $d->sisa,
                        'harga_satuan'    => $qty > 0 ? $hargaTotal / $qty : 0,
                        'harga_total'     => $hargaTotal,
                        'valas'           => $d->valas,
                    ];
                })->values(),
            ];
        })->sortByDesc('jumlah_order')->values();

        return $limit ? $result->take($limit)->values() : $result;
    }

    /**
     * Pivot WORK ORDER: item BOM yang BELUM ada di PO (belum diorder).
     * Replikasi logika STATUS ORDER = 'NOT ORDER' dari MASTER DATA.
     */
    public function workOrderPivot(int $limit = 100): \Illuminate\Support\Collection
    {
        // Match #1: PO dengan barang_code yang PERSIS sama dengan BOM (kasus normal).
        $poAgg = DB::table('mon_purchase_orders')
            ->select('uraian', 'barang_code')
            ->selectRaw('SUM(jumlah_order) as ordered_qty')
            ->groupBy('uraian', 'barang_code');

        // Match #2: PO dengan barang_code BEDA tapi barang_name SAMA (dinormalisasi
        // lower+trim) dengan BOM. Ini menutup celah dimana item yang sebenarnya
        // sudah di-PO tapi kodenya beda (typo/kode lama/kode supplier) masih
        // muncul sebagai "NOT ORDER" di pivot WORK ORDER. Item semacam ini sudah
        // otomatis ikut tampil di pivot MATERIAL PURCHASE (dari tabel PO), jadi
        // di sini cukup di-exclude dari WORK ORDER.
        $poNameAgg = DB::table('mon_purchase_orders')
            ->select('uraian')
            ->selectRaw('LOWER(LTRIM(RTRIM(barang_name))) as barang_name_norm')
            ->selectRaw('SUM(jumlah_order) as ordered_qty')
            ->groupBy('uraian', DB::raw('LOWER(LTRIM(RTRIM(barang_name)))'));

        $query = DB::table('mon_boms as b')
            ->leftJoin('mon_orders as o', 'o.uraian', '=', 'b.uraian')
            ->leftJoinSub($poAgg, 'po_agg', function ($join) {
                $join->on('po_agg.uraian', '=', 'b.uraian')
                    ->on('po_agg.barang_code', '=', 'b.barang_code');
            })
            ->leftJoinSub($poNameAgg, 'po_name_agg', function ($join) {
                $join->on('po_name_agg.uraian', '=', 'b.uraian')
                    ->on('po_name_agg.barang_name_norm', '=', DB::raw('LOWER(LTRIM(RTRIM(b.barang_name)))'));
            })
            ->whereRaw('COALESCE(po_agg.ordered_qty, 0) = 0')
            ->whereRaw('COALESCE(po_name_agg.ordered_qty, 0) = 0')
            ->select(
                'b.uraian',
                'b.barang_code',
                'b.barang_name',
                'b.departemen',
                'b.komponen',
                'b.barang_jadi'
            )
            ->selectRaw('SUM(b.cons) as total_cons')
            ->groupBy('b.uraian', 'b.barang_code', 'b.barang_name', 'b.departemen', 'b.komponen', 'b.barang_jadi')
            ->orderBy('b.uraian');

        $this->applyFilterValue($query, 'b.uraian', 'uraian');
        $this->applyFilterValue($query, 'o.buyer', 'buyer');
        $this->applyFilterValue($query, 'o.style', 'style');

        return $query->limit($limit)->get();
    }

    /**
     * Ringkasan jumlah order per tanggal `production_delivery`, untuk satu bulan
     * tertentu -- dipakai buat kasih tanda titik/jumlah di komponen kalender.
     * Filter uraian/buyer/style yang aktif tetap diikutkan.
     */
    public function productionDeliveryCalendar(int $year, int $month): \Illuminate\Support\Collection
    {
        $query = DB::table('mon_orders')
            ->whereNotNull('production_delivery')
            ->whereYear('production_delivery', $year)
            ->whereMonth('production_delivery', $month)
            ->selectRaw('CAST(production_delivery AS DATE) as tanggal')
            ->selectRaw('COUNT(*) as jumlah_order')
            ->selectRaw('SUM(qty_ord) as total_qty')
            ->groupBy(DB::raw('CAST(production_delivery AS DATE)'))
            ->orderBy('tanggal');

        $this->applyOrderFilters($query);

        return $query->get();
    }

    /**
     * Detail baris ORDER untuk satu tanggal `production_delivery` spesifik
     * (dipanggil saat user klik tanggal di kalender). Filter aktif tetap diikutkan.
     */
    public function productionDeliveryDetail(string $date): \Illuminate\Support\Collection
    {
        $query = DB::table('mon_orders')
            ->whereDate('production_delivery', $date)
            ->select(
                'uraian',
                'buyer',
                'style',
                'item',
                'destination',
                'qty_ord',
                'production_delivery',
                'buyer_delivery'
            )
            ->orderBy('uraian');

        $this->applyOrderFilters($query);

        return $query->get();
    }

    /** Ringkasan angka untuk kartu KPI di atas dashboard */
    public function summary(): array
    {
        $orderQuery = DB::table('mon_orders');
        $this->applyOrderFilters($orderQuery);

        return [
            'total_qty_order'   => (clone $orderQuery)->sum('qty_ord'),
            'total_style'       => (clone $orderQuery)->distinct('style')->count('style'),
            'total_item_belum_order' => $this->workOrderPivot(100000)->count(),
        ];
    }
}