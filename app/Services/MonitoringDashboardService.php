<?php

namespace App\Services;

use App\Models\MonPurchaseOrder;
use Illuminate\Support\Facades\DB;

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
    public function orderPivot(): \Illuminate\Support\Collection
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

    public function materialPurchasePivot(int $limit = 20): \Illuminate\Support\Collection
    {
        $query = DB::table('mon_purchase_orders as po')
            ->whereIn('po.jenis_po', MonPurchaseOrder::MATERIAL_JENIS_PO)
            ->select('po.barang_code', 'po.barang_name', 'po.spesifikasi', 'po.valas', 'po.satuan_order', 'po.no_po')
            ->selectRaw('SUM(po.jumlah_order) as jumlah_order')
            ->selectRaw('SUM(po.jumlah_doc) as jumlah_diterima')
            ->selectRaw('SUM(po.jumlah_order) - SUM(po.jumlah_doc) as sisa')
            ->selectRaw('SUM(po.harga_total) as harga_total')
            ->groupBy('po.barang_code', 'po.barang_name', 'po.spesifikasi', 'po.valas', 'po.satuan_order', 'po.no_po')
            ->orderBy('po.barang_code')
            ->orderBy('po.spesifikasi');

        $this->applyFilterValue($query, 'po.uraian', 'uraian');
        $this->applyUraianBridgeFilter($query, 'po.uraian');

        $rows = $query->get();

        // Group flat rows (per barang_code + spesifikasi + valas + no_po) menjadi parent (global)
        // + details (per spesifikasi/valas/no_po), lalu urutkan parent berdasarkan jumlah_order terbesar.
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
        })->sortByDesc('jumlah_order')->values();

        return $limit ? $result->take($limit)->values() : $result;
    }

    /**
     * Pivot WORK ORDER: item BOM yang BELUM ada di PO (belum diorder).
     * Replikasi logika STATUS ORDER = 'NOT ORDER' dari MASTER DATA.
     */
    public function workOrderPivot(int $limit = 100): \Illuminate\Support\Collection
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
            )
            ->orderBy('b.uraian');

        $this->applyFilterValue($query, 'b.uraian', 'uraian');
        // Filter buyer/style dijembatani lewat uraian (BUKAN join langsung ke mon_orders):
        // satu uraian bisa punya banyak baris mon_orders, join langsung akan meng-gandakan
        // baris BOM sebelum SUM(b.cons) dan bikin total_cons kegedean berkali lipat.
        $this->applyUraianBridgeFilter($query, 'b.uraian');

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
