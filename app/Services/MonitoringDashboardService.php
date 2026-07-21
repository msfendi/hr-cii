<?php

namespace App\Services;

use App\Models\MonPurchaseOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class MonitoringDashboardService
{
    /** TTL cache untuk dropdown filter (jarang berubah, aman di-cache) */
    private const FILTER_OPTIONS_TTL = 300; // 5 menit

    public function __construct(protected array $filters = []) {}

    /**
     * $filters bisa berisi: uraian, buyer, style
     */
    public static function make(array $filters): self
    {
        return new self($filters);
    }

    /**
     * Dropdown filter options, diambil dari tabel ORDER.
     * Di-cache karena dropdown ini biasanya sama untuk semua user & jarang berubah.
     * Hapus cache manual saat ada import/update order besar: Cache::forget('mon_dashboard:filter_options')
     */
    public function filterOptions(): array
    {
        return Cache::remember('mon_dashboard:filter_options', self::FILTER_OPTIONS_TTL, function () {
            return [
                'uraian' => DB::table('mon_orders')->whereNotNull('uraian')->distinct()->orderBy('uraian')->pluck('uraian'),
                'buyer'  => DB::table('mon_orders')->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
                'style'  => DB::table('mon_orders')->whereNotNull('style')->distinct()->orderBy('style')->pluck('style'),
            ];
        });
    }

    /**
     * Kombinasi uraian/buyer/style yang benar-benar ada di mon_orders, dipakai HANYA
     * untuk cascading dropdown filter (Buyer -> Style -> Uraian) di blade.
     *
     * SENGAJA dibuat query ringan: SELECT DISTINCT 3 kolom saja, tanpa SUM/GROUP BY
     * per destination & tanpa MIN(buyer_delivery) seperti di orderPivot(). Sebelumnya
     * blade memakai orderPivot() (query berat, ikut dipanggil di load pertama) hanya
     * untuk mengambil kombinasi ini -- sekarang dipisah supaya load halaman pertama
     * tidak perlu menjalankan pivot lengkap.
     */
    public function orderCombos(): Collection
    {
        return DB::table('mon_orders')
            ->select('uraian', 'buyer', 'style')
            ->whereNotNull('uraian')
            ->distinct()
            ->orderBy('uraian')
            ->get();
    }

    private function applyOrderFilters($query, string $prefix = ''): void
    {
        $col = fn(string $c) => $prefix ? "{$prefix}.{$c}" : $c;

        foreach (['uraian', 'buyer', 'style'] as $field) {
            $this->applyFilterValue($query, $col($field), $field);
        }
    }

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

    /** Apakah filter $key sedang aktif (ada nilainya)? */
    private function hasFilterValue(string $key): bool
    {
        $value = $this->filters[$key] ?? null;

        if (is_array($value)) {
            return count(array_filter($value, fn($v) => $v !== null && $v !== '')) > 0;
        }

        return $value !== null && $value !== '';
    }

    private function applyUraianBridgeFilter($query, string $uraianColumn): void
    {
        if (!$this->hasFilterValue('buyer') && !$this->hasFilterValue('style')) {
            return;
        }

        $uraianSubquery = DB::table('mon_orders')->select('uraian')->distinct();
        $this->applyFilterValue($uraianSubquery, 'buyer', 'buyer');
        $this->applyFilterValue($uraianSubquery, 'style', 'style');

        $query->whereIn($uraianColumn, $uraianSubquery);
    }

    /**
     * Pivot ORDER: qty order per uraian / buyer / style
     */
    public function orderPivot(): Collection
    {
        $query = DB::table('mon_orders')
            ->select('uraian', 'buyer', 'style', 'destination')
            ->selectRaw('SUM(qty_ord) as qty_order')
            ->selectRaw('MIN(buyer_delivery) as estimasi_shipment')
            ->groupBy('uraian', 'buyer', 'style', 'destination')
            ->orderBy('uraian');

        $this->applyOrderFilters($query);

        return $query->get();
    }

    /**
     * Pivot PEMBELIAN MATERIAL, dibatasi ke top-$limit barang_code (berdasarkan
     * total jumlah_order) TANPA fetch seluruh baris po dulu.
     *
     * Strategi 2 langkah:
     *  1) Query ringan: SUM + GROUP BY barang_code + ORDER BY + LIMIT di level SQL,
     *     supaya penentuan "top N" tidak menunggu semua baris ditarik ke PHP.
     *  2) Baru fetch detail (per spesifikasi/valas/no_po) HANYA untuk barang_code
     *     yang lolos langkah 1.
     *
     * Ini jauh lebih murah dibanding versi lama yang selalu fetch SEMUA baris po
     * yang cocok filter (bisa ribuan baris) lalu baru grouping+sorting di PHP.
     */
    public function materialPurchasePivot(int $limit = 20): Collection
    {
        $topCodesQuery = DB::table('mon_purchase_orders as po')
            ->whereIn('po.jenis_po', MonPurchaseOrder::MATERIAL_JENIS_PO)
            ->select('po.barang_code')
            ->selectRaw('SUM(po.jumlah_order) as total_jumlah_order')
            ->groupBy('po.barang_code');

        $this->applyFilterValue($topCodesQuery, 'po.uraian', 'uraian');
        $this->applyUraianBridgeFilter($topCodesQuery, 'po.uraian');

        $topCodes = $limit
            ? $topCodesQuery->orderByDesc('total_jumlah_order')->limit($limit)->pluck('barang_code')
            : $topCodesQuery->pluck('barang_code');

        if ($topCodes->isEmpty()) {
            return collect();
        }

        $query = DB::table('mon_purchase_orders as po')
            ->whereIn('po.jenis_po', MonPurchaseOrder::MATERIAL_JENIS_PO)
            ->whereIn('po.barang_code', $topCodes)
            ->select(
                'po.barang_code',
                'po.barang_name',
                'po.spesifikasi',
                'po.valas',
                'po.satuan_order',
                'po.no_po',
                'po.tgl_pengiriman' // tambahan
            )
            ->selectRaw('SUM(po.jumlah_order) as jumlah_order')
            ->selectRaw('SUM(po.jumlah_doc) as jumlah_diterima')
            ->selectRaw('SUM(po.jumlah_order) - SUM(po.jumlah_doc) as sisa')
            ->selectRaw('SUM(po.harga_total) as harga_total')
            ->groupBy(
                'po.barang_code',
                'po.barang_name',
                'po.spesifikasi',
                'po.valas',
                'po.satuan_order',
                'po.no_po',
                'po.tgl_pengiriman' // tambahan
            )
            ->orderBy('po.barang_code')
            ->orderBy('po.spesifikasi');

        $this->applyFilterValue($query, 'po.uraian', 'uraian');
        $this->applyUraianBridgeFilter($query, 'po.uraian');

        $rows = $query->get();

        $grouped = $rows->groupBy(fn($r) => $r->barang_code . '||' . $r->barang_name);

        $result = $grouped->map(function ($details, $key) {
            [$code, $name] = explode('||', $key, 2);

            $totalOrder      = (float) $details->sum('jumlah_order');
            $totalHargaTotal = (float) $details->sum('harga_total');

            $valasList  = $details->pluck('valas')->filter()->unique()->values();
            $satuanList = $details->pluck('satuan_order')->filter()->unique()->values();
            $poList     = $details->pluck('no_po')->filter()->unique()->values();

            return (object) [
                'barang_code'     => $code,
                'barang_name'     => $name,
                'jumlah_order'    => $totalOrder,
                'jumlah_diterima' => (float) $details->sum('jumlah_diterima'),
                'sisa'            => (float) $details->sum('sisa'),
                'harga_total'     => $totalHargaTotal,
                'harga_satuan'    => $totalOrder > 0 ? $totalHargaTotal / $totalOrder : 0,
                'valas'           => $valasList->count() > 1 ? $valasList->implode(', ') : $valasList->first(),
                'satuan_order'    => $satuanList->count() > 1 ? $satuanList->implode(', ') : $satuanList->first(),
                'no_po'           => $poList->count() > 1 ? $poList->implode(', ') : $poList->first(),
                'no_po_count'     => $poList->count(),
                'details'         => $details->map(function ($d) {
                    $qty        = (float) $d->jumlah_order;
                    $hargaTotal = (float) $d->harga_total;

                    return [
                        'spesifikasi'     => $d->spesifikasi,
                        'no_po'           => $d->no_po,
                        'tgl_pengiriman'  => $d->tgl_pengiriman, // tambahan
                        'jumlah_order'    => $qty,
                        'jumlah_diterima' => (float) $d->jumlah_diterima,
                        'sisa'            => (float) $d->sisa,
                        'harga_satuan'    => $qty > 0 ? $hargaTotal / $qty : 0,
                        'harga_total'     => $hargaTotal,
                        'valas'           => $d->valas,
                        'satuan_order'    => $d->satuan_order,
                    ];
                })->values(),
            ];
        });

        // Urutan "top N" sudah ditentukan di langkah 1 (SQL), tinggal dipertahankan
        // di sini -- tidak perlu sortByDesc ulang di PHP atas seluruh dataset.
        $order = $topCodes->flip();

        return $result
            ->sortBy(fn($r) => $order[$r->barang_code] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Query dasar untuk pivot WORK ORDER (item BOM yang belum ada di PO).
     * Dipisah dari workOrderPivot() supaya bisa dipakai ulang untuk COUNT
     * tanpa perlu fetch+limit baris (lihat workOrderCount()).
     */
    private function baseWorkOrderQuery()
    {
        $poAgg = DB::table('mon_purchase_orders')
            ->select('uraian', 'barang_code')
            ->selectRaw('SUM(jumlah_order) as ordered_qty')
            ->groupBy('uraian', 'barang_code');

        $poNameAgg = DB::table('mon_purchase_orders')
            ->select('uraian')
            ->selectRaw('LOWER(LTRIM(RTRIM(barang_name))) as barang_name_norm')
            ->selectRaw('SUM(jumlah_order) as ordered_qty')
            ->groupBy('uraian', DB::raw('LOWER(LTRIM(RTRIM(barang_name)))'));

        // Referensi satuan: diambil dari histori PO manapun untuk barang_code yang sama
        // (item di pivot ini belum pernah di-PO untuk uraian-nya sendiri, jadi satuan
        // diambil sebagai info referensi dari histori PO barang yang sama di uraian lain).
        $satuanAgg = DB::table('mon_purchase_orders')
            ->select('barang_code')
            ->selectRaw('MAX(satuan_order) as satuan_order')
            ->groupBy('barang_code');

        $query = DB::table('mon_boms as b')
            ->leftJoinSub($poAgg, 'po_agg', function ($join) {
                $join->on('po_agg.uraian', '=', 'b.uraian')
                    ->on('po_agg.barang_code', '=', 'b.barang_code');
            })
            ->leftJoinSub($poNameAgg, 'po_name_agg', function ($join) {
                $join->on('po_name_agg.uraian', '=', 'b.uraian')
                    ->on('po_name_agg.barang_name_norm', '=', DB::raw('LOWER(LTRIM(RTRIM(b.barang_name)))'));
            })
            ->leftJoinSub($satuanAgg, 'satuan_agg', function ($join) {
                $join->on('satuan_agg.barang_code', '=', 'b.barang_code');
            })
            ->whereRaw('COALESCE(po_agg.ordered_qty, 0) = 0')
            ->whereRaw('COALESCE(po_name_agg.ordered_qty, 0) = 0')
            ->select(
                'b.uraian',
                'b.barang_code',
                'b.barang_name',
                'b.departemen',
                'b.komponen',
                'b.barang_jadi',
                'satuan_agg.satuan_order'
            )
            ->selectRaw('SUM(b.cons) as total_cons')
            ->groupBy(
                'b.uraian',
                'b.barang_code',
                'b.barang_name',
                'b.departemen',
                'b.komponen',
                'b.barang_jadi',
                'satuan_agg.satuan_order'
            );

        $this->applyFilterValue($query, 'b.uraian', 'uraian');
        // Filter buyer/style dijembatani lewat uraian (BUKAN join langsung ke mon_orders):
        // satu uraian bisa punya banyak baris mon_orders, join langsung akan meng-gandakan
        // baris BOM sebelum SUM(b.cons) dan bikin total_cons kegedean berkali lipat.
        $this->applyUraianBridgeFilter($query, 'b.uraian');

        return $query;
    }

    /**
     * Pivot WORK ORDER: item BOM yang BELUM ada di PO (belum diorder).
     * Replikasi logika STATUS ORDER = 'NOT ORDER' dari MASTER DATA.
     */
    public function workOrderPivot(int $limit = 100): Collection
    {
        return $this->baseWorkOrderQuery()
            ->orderBy('b.uraian')
            ->limit($limit)
            ->get();
    }

    /**
     * Jumlah item yang belum diorder, TANPA fetch seluruh baris ke PHP.
     * Laravel otomatis membungkus query yang punya GROUP BY ke dalam subquery
     * saat count() dipanggil, jadi ini hanya menghasilkan 1 angka dari DB
     * -- bukan lagi ->get()->count() atas puluhan ribu baris seperti sebelumnya.
     */
    public function workOrderCount(): int
    {
        return $this->baseWorkOrderQuery()->count();
    }

    /**
     * Ringkasan jumlah order per tanggal `buyer_delivery`, untuk satu bulan
     * tertentu -- dipakai buat kasih tanda titik/jumlah di komponen kalender.
     * Filter uraian/buyer/style yang aktif tetap diikutkan.
     */
    public function productionDeliveryCalendar(int $year, int $month): Collection
    {
        $query = DB::table('mon_orders')
            ->whereNotNull('buyer_delivery')
            ->whereYear('buyer_delivery', $year)
            ->whereMonth('buyer_delivery', $month)
            ->selectRaw('CAST(buyer_delivery AS DATE) as tanggal')
            ->selectRaw('COUNT(*) as jumlah_order')
            ->selectRaw('SUM(qty_ord) as total_qty')
            ->groupBy(DB::raw('CAST(buyer_delivery AS DATE)'))
            ->orderBy('tanggal');

        $this->applyOrderFilters($query);

        return $query->get();
    }

    /**
     * Detail baris ORDER untuk satu tanggal `buyer_delivery` spesifik
     * (dipanggil saat user klik tanggal di kalender). Filter aktif tetap diikutkan.
     */
    public function productionDeliveryDetail(string $date): Collection
    {
        $query = DB::table('mon_orders')
            ->whereDate('buyer_delivery', $date)
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

    /**
     * Ringkasan angka untuk kartu KPI di atas dashboard.
     * Sebelumnya: 2 query terpisah (clone) untuk qty & style, ditambah
     * workOrderPivot(100000)->count() yang fetch sampai 100rb baris cuma buat dihitung.
     * Sekarang: 1 query untuk qty+style, dan workOrderCount() yang COUNT murni di DB.
     */
    public function summary(): array
    {
        $orderQuery = DB::table('mon_orders');
        $this->applyOrderFilters($orderQuery);

        $orderSummary = $orderQuery
            ->selectRaw('SUM(qty_ord) as total_qty_order')
            ->selectRaw('COUNT(DISTINCT style) as total_style')
            ->first();

        return [
            'total_qty_order'         => $orderSummary->total_qty_order ?? 0,
            'total_style'             => $orderSummary->total_style ?? 0,
            'total_item_belum_order'  => $this->workOrderCount(),
        ];
    }
}
