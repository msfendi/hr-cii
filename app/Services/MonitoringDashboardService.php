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
     * $filters bisa berisi: uraian, brand, style, ocf
     * (ocf = nilai PERSIS dari mon_orders.ocf_no, sama seperti sumber yang
     * dipakai MonitoringRekonsiliasiService -- bukan lagi hasil ekstraksi
     * regex dari mon_boms.code_prod)
     */
    public static function make(array $filters): self
    {
        return new self($filters);
    }

    /**
     * Versi generik dari resolve daftar uraian: pakai SEMUA filter
     * uraian/brand/style/ocf yang aktif, KECUALI kunci-kunci yang disebut di
     * $excludeKeys. Ini basis tunggal untuk cascade filter dua arah --
     * dropdown Brand, Style, Uraian, dan OCF semua dihitung dari fungsi ini
     * dengan meng-exclude dirinya sendiri, jadi TIDAK ada urutan tetap
     * (bukan cuma Brand->Style->Uraian searah); siapapun yang dipilih
     * duluan akan menyaring yang lain, dan sebaliknya juga berlaku.
     * Meniru pola MonitoringRekonsiliasiService::cpoListExcluding().
     *
     * Return null  = tidak ada filter aktif (di luar $excludeKeys) sama
     *                sekali -> tidak dibatasi uraian manapun (tampilkan semua).
     * Return array = daftar uraian hasil irisan filter yang aktif; bisa
     *                kosong kalau kombinasinya tidak match apapun.
     */
    private function uraianListExcluding(array $excludeKeys): ?array
    {
        $uraian = in_array('uraian', $excludeKeys, true) ? '' : trim((string) ($this->filters['uraian'] ?? ''));
        $brand  = in_array('brand', $excludeKeys, true) ? '' : trim((string) ($this->filters['brand'] ?? ''));
        $style  = in_array('style', $excludeKeys, true) ? '' : trim((string) ($this->filters['style'] ?? ''));
        $ocf    = in_array('ocf', $excludeKeys, true) ? '' : trim((string) ($this->filters['ocf'] ?? ''));

        $base = null; // null = belum ditentukan oleh Brand/Style/Uraian

        if ($uraian !== '') {
            $base = [$uraian];
        } elseif ($brand !== '' || $style !== '') {
            $query = DB::table('mon_orders')->whereNotNull('uraian');
            if ($brand !== '') {
                $query->where('brand', $brand);
            }
            if ($style !== '') {
                $query->where('style', $style);
            }
            $base = $query->distinct()->pluck('uraian')->all();
        }

        // OCF (nilai persis mon_orders.ocf_no) -- bisa dipakai sendirian atau
        // dikombinasikan dengan Brand/Style/Uraian lewat IRISAN (intersect)
        // daftar uraian.
        if ($ocf !== '') {
            $ocfUraian = $this->uraianListForOcf($ocf);
            $base = $base === null ? $ocfUraian : array_values(array_intersect($base, $ocfUraian));
        }

        return $base;
    }

    /**
     * Jembatan OCF -> daftar uraian yang cocok, dipakai uraianListExcluding()
     * supaya filter OCF juga bisa menyaring Brand/Style/Uraian (bukan cuma
     * disaring oleh mereka).
     */
    private function uraianListForOcf(string $ocfNo): array
    {
        $cacheKey = 'mon_dashboard:uraian_for_ocf:' . md5($ocfNo);

        return Cache::remember($cacheKey, self::FILTER_OPTIONS_TTL, function () use ($ocfNo) {
            return DB::table('mon_orders')
                ->whereNotNull('ocf_no')
                ->whereNotNull('uraian')
                ->where('ocf_no', $ocfNo)
                ->distinct()
                ->pluck('uraian')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Daftar nilai OCF (mon_orders.ocf_no apa adanya) yang relevan dengan
     * filter Brand/Style/Uraian yang SEDANG aktif -- dijembatani lewat
     * mon_orders.uraian, pakai basis yang sama (uraianListExcluding())
     * dengan dropdown lain supaya cascade-nya BOLAK-BALIK: bukan cuma
     * Brand/Style yang mempersempit OCF, tapi Uraian yang dipilih duluan
     * juga ikut menyaring pilihan OCF. OCF itu sendiri tetap di-exclude
     * supaya tidak membatasi diri sendiri.
     *
     * Di-cache per kombinasi filter aktif (bukan satu cache global) karena
     * hasilnya berbeda-beda tergantung filter.
     */
    private function ocfCodesForCurrentBrandStyle(): Collection
    {
        $uraianScope = $this->uraianListExcluding(['ocf']);

        $cacheKey = 'mon_dashboard:ocf_codes:' . md5(json_encode($uraianScope));

        return Cache::remember($cacheKey, self::FILTER_OPTIONS_TTL, function () use ($uraianScope) {
            $query = DB::table('mon_orders')->whereNotNull('ocf_no')->whereNotNull('uraian')->select('ocf_no')->distinct();

            if ($uraianScope !== null) {
                if (empty($uraianScope)) {
                    // Kombinasi Brand/Style/Uraian tidak match uraian apapun.
                    return collect();
                }
                $query->whereIn('uraian', $uraianScope);
            }

            return $query->pluck('ocf_no')
                ->unique()
                ->sort()
                ->values();
        });
    }

    /**
     * Versi publik dari ocfCodesForCurrentBrandStyle(), dipakai controller untuk
     * mengisi ulang dropdown OCF secara cascading (AJAX) setiap kali filter
     * lain berubah -- perilakunya sama seperti cascade Brand -> Style ->
     * Uraian, tapi untuk dropdown OCF, dan sekarang BOLAK-BALIK (lihat
     * uraianListExcluding()).
     */
    public function ocfOptions(): Collection
    {
        return $this->ocfCodesForCurrentBrandStyle();
    }

    /**
     * Helper generik untuk dropdown Brand/Style: ambil kolom $column dari
     * mon_orders, di-scope ke uraianListExcluding($excludeKeys) supaya
     * cascade-nya konsisten dan dua arah dengan dropdown lain (Uraian/OCF).
     * $excludeKeys wajib memuat nama filter yang sedang dihitung opsinya
     * sendiri (mis. 'brand' untuk brandOptions()), supaya tidak membatasi
     * diri sendiri. Meniru MonitoringRekonsiliasiService::cascadedOrderColumn().
     */
    private function cascadedOrderColumn(string $column, array $excludeKeys): Collection
    {
        $uraianScope = $this->uraianListExcluding($excludeKeys);

        $query = DB::table('mon_orders')->whereNotNull($column);
        if ($uraianScope !== null) {
            if (empty($uraianScope)) {
                return collect();
            }
            $query->whereIn('uraian', $uraianScope);
        }

        return $query->distinct()->orderBy($column)->pluck($column);
    }

    /**
     * Dropdown Brand: di-cascade dari Style/Uraian/OCF yang SEDANG aktif,
     * tanpa membatasi dirinya sendiri lewat filter Brand yang sedang dipilih.
     */
    public function brandOptions(): Collection
    {
        return $this->cascadedOrderColumn('brand', ['brand']);
    }

    /**
     * Dropdown Style: di-cascade dari Brand/Uraian/OCF yang SEDANG aktif,
     * tanpa membatasi dirinya sendiri lewat filter Style yang sedang dipilih.
     */
    public function styleOptions(): Collection
    {
        return $this->cascadedOrderColumn('style', ['style']);
    }

    /**
     * Dropdown Uraian (CPO): di-cascade dari Brand/Style/OCF yang SEDANG
     * aktif (uraianListExcluding(['uraian'])), tanpa membatasi dirinya
     * sendiri lewat filter Uraian yang sedang dipilih.
     */
    public function uraianOptions(): Collection
    {
        $uraianScope = $this->uraianListExcluding(['uraian']);

        $query = DB::table('mon_orders')->whereNotNull('uraian');
        if ($uraianScope !== null) {
            if (empty($uraianScope)) {
                return collect();
            }
            $query->whereIn('uraian', $uraianScope);
        }

        return $query->distinct()->orderBy('uraian')->pluck('uraian');
    }

    /**
     * Semua opsi dropdown filter (Brand, Style, Uraian, OCF) dalam satu
     * panggilan, MASING-MASING sudah di-cascade dari filter lain yang
     * sedang aktif (bolak-balik, tidak searah). Cocok dipanggil dari
     * controller setiap kali salah satu filter berubah, supaya keempat
     * dropdown saling menyaring satu sama lain -- meniru
     * MonitoringRekonsiliasiService::cascadedFilterOptions().
     */
    public function cascadedFilterOptions(): array
    {
        return [
            'brand'  => $this->brandOptions(),
            'style'  => $this->styleOptions(),
            'uraian' => $this->uraianOptions(),
            'ocf'    => $this->ocfOptions(),
        ];
    }

    /**
     * Dropdown filter options, semuanya diambil dari tabel mon_orders
     * (termasuk OCF, dari mon_orders.ocf_no -- sama seperti sumber yang
     * dipakai MonitoringRekonsiliasiService).
     * uraian/brand/style di sini SENGAJA TIDAK di-scope ke filter aktif
     * (daftar penuh, unfiltered) -- dipertahankan untuk kompatibilitas kalau
     * masih ada pemanggil lama yang butuh daftar lengkap. Untuk dropdown
     * yang di-cascade dua arah (Brand/Style/Uraian/OCF saling menyaring),
     * pakai cascadedFilterOptions() -- itu yang sekarang dipakai controller.
     * Hapus cache manual saat ada import/update order besar: Cache::forget('mon_dashboard:filter_options')
     */
    public function filterOptions(): array
    {
        return Cache::remember('mon_dashboard:filter_options:uraian_brand_style', self::FILTER_OPTIONS_TTL, function () {
            return [
                'uraian' => DB::table('mon_orders')->whereNotNull('uraian')->distinct()->orderBy('uraian')->pluck('uraian'),
                'brand'  => DB::table('mon_orders')->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
                'style'  => DB::table('mon_orders')->whereNotNull('style')->distinct()->orderBy('style')->pluck('style'),
            ];
        }) + [
            // OCF: nilai mon_orders.ocf_no apa adanya, sudah disaring sesuai
            // brand/style yang aktif saat request ini dibuat.
            'ocf' => $this->ocfCodesForCurrentBrandStyle(),
        ];
    }


    public function orderCombos(): Collection
    {
        return DB::table('mon_orders')
            ->select('uraian', 'brand', 'style')
            ->whereNotNull('uraian')
            ->distinct()
            ->orderBy('uraian')
            ->get();
    }

    /**
     * Kombinasi uraian/brand/style yang siap dikirim ke blade untuk dropdown
     * select2 (cascading). Sengaja di-map+unique DI SINI, BUKAN inline di dalam
     * @json() pada blade -- soalnya @json() Blade meng-explode expression
     * berdasarkan koma untuk memisahkan argumen encoding-options/depth. Kalau
     * expression yang dikirim ke @json() mengandung array literal (banyak koma),
     * pemisahan itu salah dan flag escaping (JSON_HEX_APOS dkk) yang seharusnya
     * otomatis dipasang Laravel malah gagal terpasang -- akibatnya JSON yang
     * dikirim ke atribut HTML bisa pecah kalau ada uraian/brand/style yang
     * mengandung tanda kutip ('/"), dan dropdown filter jadi kosong.
     * Solusi: kirim variabel polos ke blade, blade cukup @json($orderComboOptions).
     */
    public function orderComboOptions(): Collection
    {
        return $this->orderCombos()
            ->map(fn($r) => ['uraian' => $r->uraian, 'brand' => $r->brand, 'style' => $r->style])
            ->unique()
            ->values();
    }

    private function applyOrderFilters($query, string $prefix = ''): void
    {
        $col = fn(string $c) => $prefix ? "{$prefix}.{$c}" : $c;

        foreach (['uraian', 'brand', 'style'] as $field) {
            $this->applyFilterValue($query, $col($field), $field);
        }

        // OCF sekarang kolom langsung di mon_orders (ocf_no), sama seperti
        // sumber yang dipakai MonitoringRekonsiliasiService -- matching exact,
        // bukan lagi dijembatani lewat mon_boms.code_prod.
        $this->applyFilterValue($query, $col('ocf_no'), 'ocf');
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
        if ($this->hasFilterValue('brand') || $this->hasFilterValue('style')) {
            $uraianSubquery = DB::table('mon_orders')->select('uraian')->distinct();
            $this->applyFilterValue($uraianSubquery, 'brand', 'brand');
            $this->applyFilterValue($uraianSubquery, 'style', 'style');

            $query->whereIn($uraianColumn, $uraianSubquery);
        }

        // OCF (mon_orders.ocf_no, nilai persis -- sama sumbernya dengan
        // MonitoringRekonsiliasiService) dijembatani terpisah ke uraian yang
        // cocok.
        if ($this->hasFilterValue('ocf')) {
            $ocfSubquery = DB::table('mon_orders')->whereNotNull('ocf_no')->select('uraian')->distinct();
            $this->applyFilterValue($ocfSubquery, 'ocf_no', 'ocf');

            $query->whereIn($uraianColumn, $ocfSubquery);
        }
    }

    /**
     * Pivot ORDER: qty order per uraian / brand / style
     */
    public function orderPivot(): Collection
    {
        $query = DB::table('mon_orders')
            ->select('uraian', 'brand', 'style', 'destination')
            ->selectRaw('SUM(qty_ord) as qty_order')
            ->selectRaw('MIN(buyer_delivery) as estimasi_shipment')
            ->groupBy('uraian', 'brand', 'style', 'destination')
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
     * Perbaikan: menambahkan po.uraian ke dalam SELECT dan GROUP BY query detail,
     * serta menyertakan uraian dalam kunci grouping di PHP. Hal ini mencegah
     * tercampurnya data dari uraian yang berbeda (misal no PO sama tapi uraian beda)
     * dan memastikan hanya uraian sesuai filter yang tampil.
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
                'po.uraian',                      // <-- ditambahkan
                'po.barang_code',
                'po.barang_name',
                'po.valas',
                'po.spesifikasi',
                'po.satuan_order',
                'po.no_po',
                'po.tgl_pengiriman'
            )
            ->selectRaw('SUM(po.jumlah_order) as jumlah_order')
            ->selectRaw('SUM(po.jumlah_doc) as jumlah_diterima')
            ->selectRaw('SUM(po.jumlah_order) - SUM(po.jumlah_doc) as sisa')
            ->selectRaw('SUM(po.harga_total) as harga_total')
            ->groupBy(
                'po.uraian',                      // <-- ditambahkan
                'po.barang_code',
                'po.barang_name',
                'po.valas',
                'po.spesifikasi',
                'po.satuan_order',
                'po.no_po',
                'po.tgl_pengiriman'
            )
            ->orderBy('po.barang_code')
            ->orderBy('po.valas')
            ->orderBy('po.spesifikasi');

        $this->applyFilterValue($query, 'po.uraian', 'uraian');
        $this->applyUraianBridgeFilter($query, 'po.uraian');

        $rows = $query->get();

        // Grouping berdasarkan barang_code, barang_name, valas, DAN uraian
        $grouped = $rows->groupBy(
            fn($r) =>
            $r->barang_code . '||' . $r->barang_name . '||' . $r->valas . '||' . $r->uraian
        );

        $result = $grouped->map(function ($details, $key) {
            [$code, $name, $valas, $uraian] = explode('||', $key, 4);

            $totalOrder      = (float) $details->sum('jumlah_order');
            $totalHargaTotal = (float) $details->sum('harga_total');

            $satuanList = $details->pluck('satuan_order')->filter()->unique()->values();
            $poList     = $details->pluck('no_po')->filter()->unique()->values();
            return (object) [
                'uraian'          => $uraian, // <-- ditambahkan agar informasi uraian tetap tersedia
                'barang_code'     => $code,
                'barang_name'     => $name,
                'valas'           => $valas,
                'jumlah_order'    => $totalOrder,
                'jumlah_diterima' => (float) $details->sum('jumlah_diterima'),
                'sisa'            => (float) $details->sum('sisa'),
                'harga_total'     => $totalHargaTotal,
                'harga_satuan'    => $totalOrder > 0 ? $totalHargaTotal / $totalOrder : 0,
                'satuan_order'    => $satuanList->count() > 1 ? $satuanList->implode(', ') : $satuanList->first(),
                'no_po'           => $poList->count() > 1 ? $poList->implode(', ') : $poList->first(),
                'no_po_count'     => $poList->count(),
                'details'         => $details->map(function ($d) {
                    $qty        = (float) $d->jumlah_order;
                    $hargaTotal = (float) $d->harga_total;

                    return [
                        'spesifikasi'     => $d->spesifikasi,
                        'no_po'           => $d->no_po,
                        'tgl_pengiriman'  => $d->tgl_pengiriman,
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

        // Urutan "top N" sudah ditentukan di langkah 1 (SQL), dipertahankan
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
        // Filter brand/style dijembatani lewat uraian (BUKAN join langsung ke mon_orders):
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
     * Filter uraian/brand/style yang aktif tetap diikutkan.
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
                'brand',
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
