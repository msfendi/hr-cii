<?php

namespace App\Services;

use App\Models\MsBarang;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Satu service untuk dashboard Rekonsiliasi (gabungan), menarik data dari:
 *  - mon_orders          : Contract Qty (qty_ord) & info brand/style untuk header CPO
 *  - mon_rekonsiliasis   : material achievement, top excess, detail per material
 *  - mon_prod_lines      : tahapan produksi per department (Production Result),
 *                          + tahap Warehouse (dari kolom `destination`),
 *                          + scrap qty (barang_code = '01SCRP00001') untuk Fabric Usage
 *  - mon_shipments       : Shipment qty & detail dokumen pengeluaran BC
 *  - mon_work_orders     : sumber `request` (NEED) untuk fabric Qty & basis ORDER%
 *                          pada Material Achievement
 *  - mon_ms_barangs          : master `barang_category` (sync dari smartit ms_barang),
 *                          dipakai untuk mengelompokkan card Material Achievement
 *                          (Fabric/Aksesoris/Packing) dan men-scope tahap produksi
 *                          (Cutting = Bahan Setengah Jadi, tahap lain = Barang Jadi)
 *
 * Widget di-scope oleh filter Buyer / Style / CPO (uraian) -- boleh dipakai
 * satu saja, kombinasi, atau ketiganya:
 *  - uraian dipilih spesifik -> scope persis ke 1 CPO itu (perilaku lama).
 *  - uraian kosong tapi Buyer dan/atau Style dipilih -> di-resolve dulu ke
 *    SEMUA kode uraian (CPO) yang cocok lewat mon_orders, lalu semua widget
 *    di-scope pakai whereIn() ke daftar uraian tersebut (bisa lebih dari 1
 *    CPO sekaligus, datanya digabung/agregat).
 */
class MonitoringRekonsiliasiService
{
    /** TTL cache untuk dropdown filter OCF (jarang berubah, aman di-cache). */
    private const FILTER_OPTIONS_TTL = 300; // 5 menit

    /** Cache hasil resolve daftar uraian (CPO) dari filter Buyer/Style/CPO. */
    private ?array $resolvedUraian = null;

    public function __construct(protected array $filters = []) {}

    public static function make(array $filters): self
    {
        return new self($filters);
    }

    /**
     * Resolve filter Buyer/Style/CPO/Negara menjadi daftar kode uraian (CPO)
     * yang dipakai untuk scope semua query di bawah.
     *  - Kalau CPO dipilih eksplisit, itu final -- Buyer/Style diabaikan di
     *    server (mereka cuma dipakai buat mempersempit dropdown di frontend).
     *  - Kalau CPO belum dipilih tapi Buyer dan/atau Style ada, cari semua
     *    uraian yang match kombinasi tsb dari mon_orders.
     *  - Negara bisa dipakai SENDIRIAN (tanpa Buyer/Style/CPO) atau
     *    dikombinasikan: kalau dikombinasikan, hasil akhirnya adalah
     *    IRISAN (intersect) antara CPO hasil Buyer/Style/CPO dan CPO yang
     *    match negara terpilih (lihat cpoListForNegara()).
     *
     *    Sumber nama negara SELALU dari master mon_ms_negaras, dinormalisasi
     *    UPPER(LTRIM(RTRIM(negara_name)) (lihat negaraNameByCode()/negaraMasterList()),
     *    lalu dicocokkan pakai LIKE ke kolom teks bebas di tabel transaksi
     *    berikut, berbeda per tahap:
     *      - Contract (mon_orders)               : kolom `destination`
     *      - Cutting..Warehouse (mon_prod_lines)  : kolom `code_prod`
     *      - Shipment (mon_shipments)             : kolom `spesifikasi`
     *    Ketiganya di-scope LANGSUNG per baris (lihat applyNegaraScopeToOrder(),
     *    scopeByCodeProd()/applyNegaraScopeToProdLines(), dan
     *    applyNegaraScopeToShipment()) supaya tiap widget presisi per baris,
     *    bukan cuma "per CPO yang kebetulan match".
     */
    private function filterUraianList(): array
    {
        if ($this->resolvedUraian !== null) {
            return $this->resolvedUraian;
        }

        return $this->resolvedUraian = $this->cpoListExcluding([]) ?? [];
    }

    /**
     * Versi generik dari resolve CPO: pakai SEMUA filter Buyer/Style/CPO/
     * Negara/OCF yang aktif, KECUALI kunci-kunci yang disebut di
     * $excludeKeys (mis. ['negara'] atau ['ocf']). Ini basis tunggal untuk
     * cascade filter dua arah -- dropdown Buyer, Style, CPO(uraian), OCF,
     * dan Negara semua dihitung dari fungsi ini dengan meng-exclude dirinya
     * sendiri, jadi TIDAK ada urutan tetap (bukan cuma Buyer->Style->
     * Uraian->OCF searah); siapapun yang dipilih duluan akan menyaring
     * yang lain, dan sebaliknya juga berlaku.
     *
     * Return null  = tidak ada filter aktif (di luar $excludeKeys) sama
     *                sekali -> tidak dibatasi CPO manapun (tampilkan semua).
     * Return array = daftar CPO (uraian) hasil irisan filter yang aktif;
     *                bisa kosong kalau kombinasinya tidak match apapun.
     */
    private function cpoListExcluding(array $excludeKeys): ?array
    {
        $uraian = in_array('uraian', $excludeKeys, true) ? '' : trim((string) ($this->filters['uraian'] ?? ''));
        $brand  = in_array('brand', $excludeKeys, true) ? '' : trim((string) ($this->filters['brand'] ?? ''));
        $style  = in_array('style', $excludeKeys, true) ? '' : trim((string) ($this->filters['style'] ?? ''));
        $negara = in_array('negara', $excludeKeys, true) ? '' : trim((string) ($this->filters['negara'] ?? ''));
        $ocf    = in_array('ocf', $excludeKeys, true) ? '' : trim((string) ($this->filters['ocf'] ?? ''));

        $base = null; // null = belum ditentukan oleh Buyer/Style/CPO

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

        if ($negara !== '') {
            $negaraCpo = $this->cpoListForNegara($negara);
            $base = $base === null ? $negaraCpo : array_values(array_intersect($base, $negaraCpo));
        }

        // OCF (kode hasil ekstraksi dari mon_boms.code_prod, lihat extractOcfCode())
        // -- sama seperti Negara, bisa dipakai sendirian atau dikombinasikan
        // dengan Buyer/Style/CPO/Negara lewat IRISAN (intersect) daftar CPO.
        if ($ocf !== '') {
            $ocfCpo = $this->cpoListForOcf($ocf);
            $base = $base === null ? $ocfCpo : array_values(array_intersect($base, $ocfCpo));
        }

        return $base;
    }

    /**
     * Sama seperti filterUraianList(), tapi TANPA memperhitungkan filter
     * Negara -- dipakai khusus untuk cascade dropdown Negara itu sendiri
     * (lihat matchNegaraFromOrders()/filteredNegaraOptions()), supaya
     * pilihan Negara mengikuti Buyer/Style/CPO/OCF yang aktif tanpa
     * "membatasi diri sendiri" lewat filter Negara yang sedang dipilih.
     * Return null berarti tidak ada filter Buyer/Style/CPO/OCF aktif sama
     * sekali (tampilkan semua negara), beda dengan array kosong yang
     * berarti filter aktif tapi match 0 CPO (tidak ada negara valid).
     */
    private function filterUraianListExcludingNegara(): ?array
    {
        return $this->cpoListExcluding(['negara']);
    }

    private function hasCpo(): bool
    {
        return count($this->filterUraianList()) > 0;
    }

    /**
     * Apakah user memasukkan filter APAPUN (uraian/brand/style/negara)?
     * Beda dengan hasCpo(): hasCpo() bisa false meski filter negara aktif,
     * kalau kebetulan tidak ada CPO yang match (mis. negara dipilih tapi
     * belum ada shipment sama sekali dari negara itu) -- dalam kondisi itu
     * hasilnya harus tetap KOSONG, bukan balik ke "tanpa filter" (semua data).
     */
    private function hasAnyFilterInput(): bool
    {
        foreach (['uraian', 'brand', 'style', 'negara', 'ocf'] as $key) {
            if (trim((string) ($this->filters[$key] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    private function rekonQuery()
    {
        $query = DB::table('mon_rekonsiliasis');
        if ($this->hasAnyFilterInput()) {
            // whereIn dengan array kosong otomatis menghasilkan 0 baris (Laravel),
            // jadi filter yang match 0 CPO tetap kosong, bukan unscoped.
            $query->whereIn('uraian', $this->filterUraianList());
        }
        return $query;
    }

    private function orderQuery()
    {
        $query = DB::table('mon_orders');
        if ($this->hasAnyFilterInput()) {
            // PENTING: whereIn di sini HANYA dari Buyer/Style/CPO/OCF
            // (filterUraianListExcludingNegara(), null = tidak ada filter
            // itu = tidak dibatasi). Negara TIDAK ikut dipakai untuk
            // mempersempit daftar uraian di sini -- itu diserahkan
            // sepenuhnya ke applyNegaraScopeToOrder() di bawah supaya tidak
            // ada risiko "salah negara" dari tabel lain ikut membatasi CPO.
            $core = $this->filterUraianListExcludingNegara();
            if ($core !== null) {
                $query->whereIn('uraian', $core);
            }
        }
        // Step Contract di-scope LANGSUNG ke baris mon_orders yang
        // `destination`-nya cocok dengan negara terpilih.
        $this->applyNegaraScopeToOrder($query);
        return $query;
    }

    private function shipmentQuery()
    {
        $query = DB::table('mon_shipments');
        if ($this->hasAnyFilterInput()) {
            // Sama seperti orderQuery(): whereIn uraian di sini HANYA dari
            // Buyer/Style/CPO/OCF, BUKAN dari Negara. Kalau Negara ikut
            // dipakai buat mempersempit uraian (lewat cpoListForNegara(),
            // yang basisnya mon_orders.destination), shipment dari CPO yang
            // `destination` order-nya kebetulan tidak menyebut negara itu
            // (meski `spesifikasi` shipment-nya sendiri cocok) akan
            // ke-exclude duluan oleh whereIn ini -- sebelum sempat dicek
            // applyNegaraScopeToShipment() di bawah. Makanya negara
            // diserahkan sepenuhnya ke filter `spesifikasi` langsung.
            $core = $this->filterUraianListExcludingNegara();
            if ($core !== null) {
                $query->whereIn('uraian', $core);
            }
        }
        // Step Shipment di-scope LANGSUNG ke baris mon_shipments yang
        // `spesifikasi`-nya cocok dengan negara terpilih.
        $this->applyNegaraScopeToShipment($query);
        return $query;
    }

    /**
     * Master negara dari mon_ms_negaras, nama-nya dinormalisasi
     * UPPER(LTRIM(RTRIM(negara_name)) supaya pencocokan LIKE ke kolom teks bebas
     * (destination/code_prod/spesifikasi) di tabel transaksi konsisten
     * (tidak sensitif spasi/kapitalisasi).
     */
    private function negaraMasterList(): Collection
    {
        return DB::table('mon_ms_negaras')
            ->whereNotNull('negara_name')
            ->selectRaw('negara_code, UPPER(LTRIM(RTRIM(negara_name))) as negara_name')
            ->distinct()
            ->orderBy('negara_name')
            ->get();
    }

    /**
     * Nama negara (UPPER+TRIM) untuk satu negara_code, diambil dari master
     * mon_ms_negaras. Null kalau kode-nya tidak ditemukan di master.
     */
    private function negaraNameByCode(string $negaraCode): ?string
    {
        $row = DB::table('mon_ms_negaras')
            ->where('negara_code', $negaraCode)
            ->whereNotNull('negara_name')
            ->selectRaw('UPPER(LTRIM(RTRIM(negara_name))) as negara_name')
            ->first();

        return $row->negara_name ?? null;
    }

    /**
     * Nama negara (UPPER+TRIM) dari filter `negara` yang sedang aktif.
     * Return null kalau filter negara TIDAK diisi (berarti "tidak ada
     * scope negara"). Return string kosong '' kalau filter diisi tapi
     * kode-nya tidak ditemukan di master mon_ms_negaras (berarti caller
     * harus memaksa hasil kosong, bukan balik ke "tanpa filter").
     */
    private function negaraNameFilter(): ?string
    {
        $negara = trim((string) ($this->filters['negara'] ?? ''));
        if ($negara === '') {
            return null;
        }

        return $this->negaraNameByCode($negara) ?? '';
    }

    /**
     * Dropdown filter Negara: negara master (mon_ms_negaras) yang nama-nya
     * (LIKE) cocok dengan minimal 1 baris mon_orders.destination, di-scope
     * OPTIONAL ke daftar CPO ($cpoScope) hasil filter Buyer/Style/CPO/OCF
     * yang sedang aktif -- supaya dropdown negara mengikuti cascade
     * tersebut dan tidak menampilkan semua negara sekaligus.
     * $cpoScope = null artinya tidak ada filter Buyer/Style/CPO/OCF aktif
     * (tampilkan semua negara yang match order manapun).
     */
    private function matchNegaraFromOrders(?array $cpoScope): Collection
    {
        $negaraList = $this->negaraMasterList();
        if ($negaraList->isEmpty()) {
            return collect();
        }

        $query = DB::table('mon_orders')->whereNotNull('destination');
        if ($cpoScope !== null) {
            if (empty($cpoScope)) {
                // Buyer/Style/CPO/OCF match 0 CPO -> tidak ada negara valid.
                return collect();
            }
            $query->whereIn('uraian', $cpoScope);
        }

        $destinations = $query->distinct()->pluck('destination')
            ->map(fn($d) => strtoupper(trim((string) $d)))
            ->filter(fn($d) => $d !== '')
            ->values();

        if ($destinations->isEmpty()) {
            return collect();
        }

        return $negaraList->filter(function ($negara) use ($destinations) {
            return $destinations->contains(fn($dest) => str_contains($dest, $negara->negara_name));
        })->values();
    }

    /**
     * Dropdown filter Negara (versi tanpa cascade Buyer/Style/CPO/OCF --
     * dipakai saat belum ada filter apapun yang aktif). Lihat juga
     * filteredNegaraOptions() untuk versi yang mengikuti cascade tsb.
     */
    public function negaraOptions(): Collection
    {
        return $this->matchNegaraFromOrders(null);
    }

    /**
     * Semua kode uraian (CPO) yang punya minimal 1 baris mon_orders dengan
     * `destination` cocok (LIKE) dengan nama negara $negaraCode (diambil
     * dari master mon_ms_negaras, UPPER+TRIM).
     */
    public function cpoListForNegara(string $negaraCode): array // diubah dari private ke public
    {
        $negaraName = $this->negaraNameByCode($negaraCode);
        if ($negaraName === null || $negaraName === '') {
            return [];
        }

        return DB::table('mon_orders')
            ->whereNotNull('uraian')
            ->whereNotNull('destination')
            ->whereRaw('UPPER(LTRIM(RTRIM(destination))) LIKE ?', ['%' . $negaraName . '%'])
            ->distinct()
            ->pluck('uraian')
            ->all();
    }

    /**
     * Scope query mon_orders langsung ke baris yang `destination`-nya
     * cocok dengan filter `negara` (kalau ada). Dipakai oleh orderQuery()
     * supaya step "Contract" presisi per baris, bukan cuma per CPO.
     */
    private function applyNegaraScopeToOrder($query): void
    {
        $negaraName = $this->negaraNameFilter();
        if ($negaraName === null) {
            return;
        }
        if ($negaraName === '') {
            // Negara dipilih tapi kodenya tidak ada di master -- paksa kosong.
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereNotNull('destination')
            ->whereRaw('UPPER(LTRIM(RTRIM(destination))) LIKE ?', ['%' . $negaraName . '%']);
    }

    /**
     * Scope query mon_prod_lines langsung ke baris yang `code_prod`-nya
     * cocok dengan filter `negara` (kalau ada). Dipakai oleh
     * scopeByCodeProd() supaya semua tahap Cutting..Warehouse presisi
     * per baris terhadap negara terpilih.
     */
    private function applyNegaraScopeToProdLines($query): void
    {
        $negaraName = $this->negaraNameFilter();
        if ($negaraName === null) {
            return;
        }
        if ($negaraName === '') {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereNotNull('code_prod')
            ->whereRaw('UPPER(code_prod) LIKE ?', ['%' . $negaraName . '%']);
    }

    /**
     * Scope query mon_shipments langsung ke baris yang `spesifikasi`-nya
     * cocok dengan filter `negara` (kalau ada). Dipakai oleh shipmentQuery()
     * supaya KPI Shipment Qty & PIVOT SHIPMENT presisi per baris shipment,
     * bukan cuma "per CPO yang kebetulan match".
     */
    private function applyNegaraScopeToShipment($query): void
    {
        $negaraName = $this->negaraNameFilter();
        if ($negaraName === null) {
            return;
        }
        if ($negaraName === '') {
            // Negara dipilih tapi kodenya tidak ada di master -- paksa kosong.
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereNotNull('spesifikasi')
            ->whereRaw('UPPER(spesifikasi) LIKE ?', ['%' . $negaraName . '%']);
    }

    // ===== FILTER OCF (diambil dari mon_boms.code_prod) =====

    /**
     * mon_boms.code_prod berisi teks bebas, contoh:
     *   "CPO 25166 OCF 256P0011 / LADIES CREW /13-1904 TCX CHALK PINK"
     * Kode OCF yang dipakai untuk filter adalah "256P0011" -- teks setelah
     * kata "OCF" sampai sebelum tanda "/" pertama. Kalau posisi kata "OCF"
     * ternyata beda-beda / tidak ketemu, fallback ke pola kode yang formatnya
     * mirip (3 digit + 1 huruf + 4 digit, mis. 256P0011) di mana pun posisinya
     * dalam string. Persis sama dengan MonitoringDashboardService::extractOcfCode()
     * supaya kode OCF yang dihasilkan konsisten di kedua dashboard.
     */
    private static function extractOcfCode(?string $codeProd): ?string
    {
        if ($codeProd === null) {
            return null;
        }

        if (preg_match('/OCF\s*([^\/]+)/i', $codeProd, $m)) {
            $candidate = trim($m[1]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (preg_match('/\b\d{3}[A-Za-z]\d{4}\b/', $codeProd, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * Semua kode uraian (CPO) yang punya minimal 1 baris mon_boms dengan kode
     * OCF hasil ekstraksi (extractOcfCode()) sama dengan $ocfCode. Di-cache
     * per kode OCF karena dipakai berulang untuk resolve filter OCF ke CPO.
     */
    private function cpoListForOcf(string $ocfCode): array
    {
        $cacheKey = 'mon_rekon:cpo_for_ocf:' . md5($ocfCode);

        return Cache::remember($cacheKey, self::FILTER_OPTIONS_TTL, function () use ($ocfCode) {
            return DB::table('mon_boms')
                ->whereNotNull('code_prod')
                ->whereNotNull('uraian')
                ->select('uraian', 'code_prod')
                ->distinct()
                ->get()
                ->filter(fn($r) => self::extractOcfCode($r->code_prod) === $ocfCode)
                ->pluck('uraian')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Daftar kode OCF (hasil extractOcfCode() dari mon_boms.code_prod) yang
     * relevan dengan filter Buyer/Style/CPO(uraian)/Negara yang SEDANG
     * aktif -- dijembatani lewat uraian (mon_boms.uraian), pakai basis yang
     * sama (cpoListExcluding()) dengan dropdown lain supaya cascade-nya
     * BOLAK-BALIK: bukan cuma Buyer/Style yang mempersempit OCF, tapi
     * Uraian/Negara yang dipilih duluan juga ikut menyaring pilihan OCF.
     * OCF itu sendiri tetap di-exclude supaya tidak membatasi diri sendiri.
     */
    private function ocfCodesForCurrentBrandStyle(): Collection
    {
        $cpoScope = $this->cpoListExcluding(['ocf']);

        $cacheKey = 'mon_rekon:ocf_codes:' . md5(json_encode($cpoScope));

        return Cache::remember($cacheKey, self::FILTER_OPTIONS_TTL, function () use ($cpoScope) {
            $query = DB::table('mon_boms')->whereNotNull('code_prod')->whereNotNull('uraian')->select('code_prod')->distinct();

            if ($cpoScope !== null) {
                if (empty($cpoScope)) {
                    // Kombinasi Buyer/Style/Uraian/Negara tidak match CPO apapun.
                    return collect();
                }
                $query->whereIn('uraian', $cpoScope);
            }

            return $query->pluck('code_prod')
                ->map(fn($cp) => self::extractOcfCode($cp))
                ->filter(fn($v) => $v !== null && $v !== '')
                ->unique()
                ->sort()
                ->values();
        });
    }

    /**
     * Versi publik dari ocfCodesForCurrentBrandStyle(), dipakai controller
     * untuk mengisi dropdown OCF awal (index()) maupun cascade-nya lewat
     * endpoint data() setiap kali Buyer/Style/Uraian/Negara berubah.
     */
    public function ocfOptions(): Collection
    {
        return $this->ocfCodesForCurrentBrandStyle();
    }

    /**
     * "FABRIC QTY (KGM)": NEED / ORDER / RECEIVED / OUT WIP / STOCK untuk
     * material kain (satuan_code = 'KGM'), di-scope ke CPO terpilih.
     *  - ORDER    : SUM(mon_rekonsiliasis.jumlah_order) WHERE satuan_code = 'KGM'.
     *  - RECEIVED : SUM(mon_rekonsiliasis.jumlah_doc) WHERE satuan_code = 'KGM'.
     *  - OUT WIP  : SUM(mon_rekonsiliasis.out_req) WHERE satuan_code = 'KGM'.
     *  - STOCK    : SUM(mon_rekonsiliasis.saldo_gudang) WHERE satuan_code = 'KGM'.
     *  - NEED     : SUM(mon_work_orders.request) WHERE satuan_code = 'KGM',
     *               di-scope melalui join ke mon_boms (filter uraian).
     *
     * Relasi: mon_work_orders.product_code = mon_boms.barang_jadi (produk jadi)
     *         mon_work_orders.barang_code = mon_boms.barang_code (komponen)
     */
    public function fabricQty(): array
    {
        $order    = (float) ($this->rekonQuery()->where('satuan_code', 'KGM')->sum('jumlah_order') ?? 0);
        $received = (float) ($this->rekonQuery()->where('satuan_code', 'KGM')->sum('jumlah_doc') ?? 0);
        $outWip   = (float) ($this->rekonQuery()->where('satuan_code', 'KGM')->sum('out_req') ?? 0);
        $stock    = (float) ($this->rekonQuery()->where('satuan_code', 'KGM')->sum('saldo_gudang') ?? 0);

        // NEED dari mon_work_orders.request (kolom `request` = NEED qty hasil
        // sinkronisasi get_ppic_bom.txt, lihat SyncWorkOrderFromSmartit),
        // di-scope via mon_boms supaya hanya menghitung material milik CPO
        // terpilih. Kolom wo.product_code / wo.barang_code / wo.satuan_code /
        // wo.request tetap sama persis di schema mon_work_orders yang baru.
        $need = 0.0;
        if ($this->hasCpo()) {
            $need = (float) DB::table('mon_work_orders as wo')
                ->whereIn('wo.uraian', $this->filterUraianList())
                ->where('wo.satuan_code', 'KGM')
                ->sum('wo.request') ?? 0;
        }

        return [
            'need'     => $need,
            'order'    => $order,
            'received' => $received,
            'out_wip'  => $outWip,
            'stock'    => $stock,
        ];
    }

    /**
     * "FABRIC USAGE (KGM)": qty kain yang benar-benar terpakai untuk garment
     * vs yang menjadi scrap, di-scope ke CPO terpilih.
     *  - Total keluar (fabric) : SUM(mon_rekonsiliasis.out_req) WHERE satuan_code = 'KGM'
     *  - Scrap qty             : SUM(mon_prod_lines.jumlah) WHERE barang_code = '01SCRP00001',
     *                            di-scope lewat code_prod sama seperti widget Production Result.
     *  - Use for GMT           : Total keluar - Scrap qty
     *  - Consumption           : Out WIP (mon_rekonsiliasis.out_req, satuan_code KGM) /
     *                            Output Cutting (mon_prod_lines.jumlah, destination = Sewing,
     *                            kategori Bahan Setengah Jadi -- lihat productionPipeline()
     *                            dest_sewing). "Output Cutting" = hasil Cutting yang sudah
     *                            keluar menuju Sewing, bukan lagi dibagi Contract Qty.
     */
    public function fabricUsage(): array
    {
        $totalOutReq = (float) ($this->rekonQuery()->where('satuan_code', 'KGM')->sum('out_req') ?? 0);

        $scrapQty = 0.0;
        $outputCutting = 0.0;
        if ($this->hasAnyFilterInput()) {
            $query = DB::table('mon_prod_lines')
                ->where('barang_code', '01SCRP00001')
                ->selectRaw('SUM(jumlah) as jumlah');
            $this->scopeByCodeProd($query, $this->filterUraianListExcludingNegara());
            $scrapQty = (float) ($query->value('jumlah') ?? 0);

            // Output Cutting = qty yang destination-nya Sewing (keluar dari Cutting menuju Sewing).
            $outputCutting = $this->prodLineSumByDestination('Sewing', MsBarang::CATEGORY_WIP);
        }

        $useForGmt = $totalOutReq - $scrapQty;

        return [
            'use_for_gmt' => $useForGmt,
            'scrap_qty'   => $scrapQty,
            'usage_pct'   => $totalOutReq > 0 ? round($useForGmt / $totalOutReq * 100) : 0,
            'scrap_pct'   => $totalOutReq > 0 ? round($scrapQty / $totalOutReq * 100) : 0,
            'consumption' => $outputCutting > 0 ? round($totalOutReq / $outputCutting, 2) : 0,
        ];
    }

    /**
     * Dropdown CPO (uraian): di-cascade dari Buyer/Style/OCF/Negara yang
     * SEDANG aktif (cpoListExcluding(['uraian'])), tapi TIDAK membatasi
     * dirinya sendiri lewat filter Uraian yang sedang dipilih. Sumber data
     * tetap mon_rekonsiliasis (hanya uraian yang benar-benar punya baris
     * rekonsiliasi yang ditampilkan), lalu diirisankan ke cpoScope hasil
     * filter lain.
     */
    public function cpoOptions(): Collection
    {
        $cpoScope = $this->cpoListExcluding(['uraian']);

        $query = DB::table('mon_rekonsiliasis')->whereNotNull('uraian');
        if ($cpoScope !== null) {
            if (empty($cpoScope)) {
                return collect();
            }
            $query->whereIn('uraian', $cpoScope);
        }

        return $query->distinct()->orderBy('uraian')->pluck('uraian');
    }

    /**
     * Dropdown Buyer: di-cascade dari Style/CPO(uraian)/OCF/Negara yang
     * SEDANG aktif, tanpa membatasi dirinya sendiri lewat filter Buyer yang
     * sedang dipilih -- jadi kalau user pilih Style/CPO/OCF/Negara duluan,
     * daftar Buyer ikut menyempit mengikuti pilihan tsb.
     */
    public function buyerOptions(): Collection
    {
        return $this->cascadedOrderColumn('brand', ['brand']);
    }

    /**
     * Dropdown Style: di-cascade dari Buyer/CPO(uraian)/OCF/Negara yang
     * SEDANG aktif, tanpa membatasi dirinya sendiri lewat filter Style yang
     * sedang dipilih -- kalau user pilih Buyer/CPO/OCF/Negara duluan,
     * daftar Style ikut menyempit mengikuti pilihan tsb.
     */
    public function styleOptions(): Collection
    {
        return $this->cascadedOrderColumn('style', ['style']);
    }

    /**
     * Helper generik untuk dropdown Buyer/Style: ambil kolom $column dari
     * mon_orders, di-scope ke cpoListExcluding($excludeKeys) supaya
     * cascade-nya konsisten dan dua arah dengan dropdown lain (CPO/OCF/
     * Negara). $excludeKeys wajib memuat nama filter yang sedang dihitung
     * opsinya sendiri (mis. 'brand' untuk buyerOptions()), supaya tidak
     * membatasi diri sendiri.
     */
    private function cascadedOrderColumn(string $column, array $excludeKeys): Collection
    {
        $cpoScope = $this->cpoListExcluding($excludeKeys);

        $query = DB::table('mon_orders')->whereNotNull($column);
        if ($cpoScope !== null) {
            if (empty($cpoScope)) {
                return collect();
            }
            $query->whereIn('uraian', $cpoScope);
        }

        return $query->distinct()->orderBy($column)->pluck($column);
    }

    public function orderFilterOptions(): Collection
    {
        return DB::table('mon_orders')
            ->whereNotNull('uraian')
            ->select('brand', 'style', 'uraian')
            ->distinct()
            ->orderBy('brand')
            ->orderBy('style')
            ->orderBy('uraian')
            ->get();
    }

    /**
     * Semua opsi dropdown filter (Buyer, Style, CPO/Uraian, OCF, Negara)
     * dalam satu panggilan, MASING-MASING sudah di-cascade dari filter
     * lain yang sedang aktif (bolak-balik, tidak searah). Cocok dipanggil
     * dari controller setiap kali salah satu filter berubah, supaya
     * kelima dropdown saling menyaring satu sama lain:
     *  - pilih OCF tertentu  -> Uraian/Style/Buyer/Negara ikut menyaring.
     *  - pilih Style tertentu -> Buyer/CPO(Uraian)/Negara ikut menyaring
     *    (dan OCF juga menyaring, lihat ocfOptions()).
     *  - dst untuk Buyer, CPO(Uraian), dan Negara.
     */
    public function cascadedFilterOptions(): array
    {
        return [
            'buyer'  => $this->buyerOptions(),
            'style'  => $this->styleOptions(),
            'uraian' => $this->cpoOptions(),
            'ocf'    => $this->ocfOptions(),
            'negara' => $this->filteredNegaraOptions($this->filters),
        ];
    }

    /**
     * Kalau resolve filter cuma menghasilkan 1 CPO (baik karena user memilih
     * CPO spesifik, atau kombinasi Buyer+Style kebetulan cuma match 1 CPO),
     * tampilkan detail brand/style-nya seperti biasa. Kalau match lebih dari
     * 1 CPO (search by Buyer dan/atau Style saja), tampilkan jumlah CPO yang
     * tergabung plus filter Buyer/Style yang dipakai user.
     */
    public function header(): array
    {
        $uraianList = $this->filterUraianList();
        $count = count($uraianList);

        if ($count === 1) {
            $uraian = $uraianList[0];
            $orderInfo = DB::table('mon_orders')->where('uraian', $uraian)->select('brand', 'style')->first();

            return [
                'cpo'       => $uraian,
                'brand'     => $orderInfo->brand ?? ($this->filters['brand'] ?? null),
                'style'     => $orderInfo->style ?? ($this->filters['style'] ?? null),
                'cpoCount'  => 1,
            ];
        }

        return [
            'cpo'      => $count > 1 ? "{$count} CPO" : null,
            'brand'    => $this->filters['brand'] ?? null,
            'style'    => $this->filters['style'] ?? null,
            'cpoCount' => $count,
        ];
    }

    /**
     * KPI "Achievement" & "Ship Shortage" dihitung dari Shipment dibanding
     * hasil Cutting (bukan lagi Contract), sesuai definisi baru:
     *  - Achievement    : mon_shipments.jumlah_barang / mon_prod_lines.jumlah
     *                     (department_id = Cutting) * 100.
     *  - Ship Shortage  : 100% - Achievement%.
     */
    public function summary(): array
    {
        $contract = (float) ($this->orderQuery()->sum('qty_ord') ?? 0);
        $shipment = (float) ($this->shipmentSumByCategory(MsBarang::CATEGORY_JADI) ?? 0);
        $balance  = $contract - $shipment;

        $deptCutting = $this->prodLineSumByDepartment('Cutting', MsBarang::CATEGORY_WIP);
        $achievementPct = $deptCutting > 0 ? round($shipment / $deptCutting * 100, 1) : 0;

        return [
            'contract_qty'    => $contract,
            'shipment_qty'    => $shipment,
            'balance_qty'     => $balance,
            'achievement_pct' => $achievementPct,
            'shortage_pct'    => round(100 - $achievementPct, 1),
        ];
    }

    public function shipmentDates(int $limit = 6): Collection
    {
        return $this->shipmentQuery()
            ->whereNotNull('tgl_bukti')
            ->distinct()
            ->orderBy('tgl_bukti')
            ->limit($limit)
            ->pluck('tgl_bukti');
    }

    /**
     * "MATERIAL ACHIEVEMENT": persentase tiap tahap dihitung berantai
     * (bukan lagi sama-sama dibagi ORDER):
     *  - ORDER%    : mon_rekonsiliasis.jumlah_order / mon_work_orders.request (NEED).
     *  - RECEIVED% : mon_rekonsiliasis.jumlah_doc / mon_rekonsiliasis.jumlah_order.
     *  - OUT PROD% : mon_rekonsiliasis.out_req / mon_rekonsiliasis.jumlah_doc.
     *  - STOCK%    : mon_rekonsiliasis.saldo_gudang / mon_rekonsiliasis.jumlah_doc.
     *
     * Setiap baris juga dibawakan `barang_category` (dari mon_ms_barangs, via
     * barang_code) supaya frontend bisa memecah chart menjadi 3 card:
     * Fabric (Bahan Baku Lokal/Import), Aksesoris (Bahan Penolong),
     * Packing (Packaging).
     */
    public function materialAchievement(): Collection
    {
        $rows = $this->rekonQuery()
            ->select('barang_code', 'barang_name')
            ->selectRaw('SUM(jumlah_order) as jumlah_order')
            ->selectRaw('SUM(jumlah_doc) as jumlah_doc')
            ->selectRaw('SUM(out_req) as out_req')
            ->selectRaw('SUM(saldo_gudang) as saldo_gudang')
            ->groupBy('barang_code', 'barang_name')
            ->orderBy('barang_name')
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        // NEED per barang_code dari mon_work_orders.request, di-scope ke CPO
        // yang sama dengan widget lain (lihat fabricQty()).
        $needByCode = collect();
        if ($this->hasCpo()) {
            $needByCode = DB::table('mon_work_orders')
                ->whereIn('uraian', $this->filterUraianList())
                ->select('barang_code')
                ->selectRaw('SUM(request) as request')
                ->groupBy('barang_code')
                ->get()
                ->keyBy('barang_code');
        }

        // Kategori barang (Fabric/Aksesoris/Packing) dari mon_ms_barangs.
        $codes = $rows->pluck('barang_code')->filter()->unique()->values();
        $categoryByCode = $codes->isEmpty()
            ? collect()
            : DB::table('mon_ms_barangs')->whereIn('barang_code', $codes)->pluck('barang_category', 'barang_code');

        return $rows->map(function ($r) use ($needByCode, $categoryByCode) {
            $order = (float) $r->jumlah_order;
            $doc   = (float) $r->jumlah_doc;
            $need  = (float) ($needByCode[$r->barang_code]->request ?? 0);

            $pct = fn($num, $denom) => $denom > 0 ? round(max(0, (float) $num) / $denom * 100) : 0;

            return (object) [
                'barang_code'     => $r->barang_code,
                'barang_name'     => $r->barang_name,
                'barang_category' => $categoryByCode[$r->barang_code] ?? null,
                'material_group'  => $this->materialAchievementGroup($categoryByCode[$r->barang_code] ?? null),
                'order_pct'       => $pct($order, $order),
                'received_pct'    => $pct($doc, $order),
                'out_prod_pct'    => $pct($r->out_req, $doc),
                'stock_pct'       => $pct($r->saldo_gudang, $doc),
            ];
        });
    }

    /**
     * Kelompokkan barang_category smartit ke salah satu dari 3 card chart
     * Material Achievement. Kategori di luar ketiga grup ini (mis. Scrap,
     * Inventaris) ditandai 'lainnya' dan tidak ditampilkan di card manapun.
     */
    private function materialAchievementGroup(?string $barangCategory): string
    {
        return match (true) {
            in_array($barangCategory, MsBarang::GROUP_FABRIC, true)    => 'fabric',
            in_array($barangCategory, MsBarang::GROUP_AKSESORIS, true) => 'aksesoris',
            in_array($barangCategory, MsBarang::GROUP_PACKING, true)   => 'packing',
            default => 'lainnya',
        };
    }

    /**
     * Tahap produksi (Cutting → Sewing → Packing → Warehouse → Shipment),
     * masing-masing di-scope ke kategori barang lewat mon_ms_barangs:
     *  - Cutting                         : barang_category = Bahan Setengah Jadi
     *  - Sewing / Packing / Warehouse /
     *    Shipment (produk jadi)          : barang_category = Barang Jadi
     *
     * Sumber qty per tahap:
     *  - Cutting   : mon_prod_lines.jumlah, department_id = Cutting
     *  - Sewing    : mon_prod_lines.jumlah, department_id = Sewing
     *  - Packing   : mon_prod_lines.jumlah, department_id = Packing
     *  - Warehouse : mon_prod_lines.jumlah, destination   = Warehouse
     *  - Shipment  : mon_shipments.jumlah_barang
     *
     * dest_sewing & dest_packing (mon_prod_lines.jumlah per kolom
     * `destination`) juga dihitung di sini karena dipakai sebagai basis
     * loss per tahap, lihat pipelineLossSteps().
     *
     * PERUBAHAN: total_loss sekarang hanya mencakup loss dari Packing ke Warehouse,
     * bukan seluruh rantai (Contract → Shipment). Hal ini karena permintaan
     * "Total Process Loss yang dihitung hanya dari stage sewing sampai warehouse saja".
     * Untuk Shipment, loss-nya dihitung terpisah di step Warehouse→Shipment.
     */
    public function productionPipeline(): array
    {
        $contract = (float) ($this->orderQuery()->sum('qty_ord') ?? 0);

        $deptCutting = $this->prodLineSumByDepartment('Cutting', MsBarang::CATEGORY_WIP);
        $deptSewing  = $this->prodLineSumByDepartment('Sewing', MsBarang::CATEGORY_JADI);
        $deptPacking = $this->prodLineSumByDepartment('Packing', MsBarang::CATEGORY_JADI);

        $destSewing    = $this->prodLineSumByDestination('Sewing', MsBarang::CATEGORY_WIP);
        $destPacking   = $this->prodLineSumByDestination('Packing', MsBarang::CATEGORY_JADI);
        $destWarehouse = $this->prodLineSumByDestination('Warehouse', MsBarang::CATEGORY_JADI);

        $shipment = $this->shipmentSumByCategory(MsBarang::CATEGORY_JADI);

        $departments = collect([
            (object) ['department_id' => 'Cutting', 'jumlah' => $deptCutting],
            (object) ['department_id' => 'Sewing', 'jumlah' => $deptSewing],
            (object) ['department_id' => 'Packing', 'jumlah' => $deptPacking],
            (object) ['department_id' => 'Warehouse', 'jumlah' => $destWarehouse],
        ]);

        // Total loss hanya dari Packing ke Warehouse (loss di tahap ini)
        $totalLoss = ($deptSewing - $destSewing) + ($deptPacking - $destPacking) + ($destWarehouse - $deptPacking);
        $lossPct   = $deptPacking > 0 ? round($totalLoss / $deptCutting * 100, 2) : 0;

        return [
            'contract'    => $contract,
            'departments' => $departments,
            'shipment'    => $shipment,
            'total_loss'  => $totalLoss,
            'loss_pct'    => $lossPct,

            // Nilai mentah per tahap, dipakai pipelineLossSteps() untuk
            // menghitung loss per tahap sesuai definisi masing-masing.
            'dept_cutting'   => $deptCutting,
            'dept_sewing'    => $deptSewing,
            'dept_packing'   => $deptPacking,
            'dest_sewing'    => $destSewing,
            'dest_packing'   => $destPacking,
            'dest_warehouse' => $destWarehouse,
        ];
    }

    public function productionResultByMaterial(): Collection
    {
        if (!$this->hasAnyFilterInput()) {
            return collect();
        }

        $query = DB::table('mon_prod_lines')
            ->join('mon_ms_barangs', 'mon_ms_barangs.barang_code', '=', 'mon_prod_lines.barang_code')
            ->whereIn('mon_prod_lines.department_id', ['Cutting', 'Sewing', 'Packing'])
            ->where(function ($q) {
                // Cutting = barang setengah jadi (WIP); Sewing/Packing = barang jadi.
                $q->where(function ($q2) {
                    $q2->where('mon_prod_lines.department_id', 'Cutting')
                        ->where('mon_ms_barangs.barang_category', MsBarang::CATEGORY_WIP);
                })->orWhere(function ($q2) {
                    $q2->whereIn('mon_prod_lines.department_id', ['Sewing', 'Packing'])
                        ->where('mon_ms_barangs.barang_category', MsBarang::CATEGORY_JADI);
                });
            })
            ->where('mon_prod_lines.barang_name', 'not like', '%Potongan%')
            ->select('mon_prod_lines.department_id', 'mon_prod_lines.barang_code', 'mon_prod_lines.barang_name')
            ->selectRaw('SUM(mon_prod_lines.jumlah) as jumlah')
            ->groupBy('mon_prod_lines.department_id', 'mon_prod_lines.barang_code', 'mon_prod_lines.barang_name')
            ->orderBy('mon_prod_lines.barang_name');

        $this->scopeByCodeProd($query, $this->filterUraianListExcludingNegara());

        // Baris dengan barang_name berawalan "Pot" (mis. "Pot Lengan") TIDAK
        // lagi diabaikan (where 'not like Pot%' sudah dihapus) -- datanya tetap
        // ditampilkan, hanya kata "Pot" di depannya yang dibuang supaya label
        // chart lebih rapi. Barang dengan kata "Potongan" tetap disaring
        // terpisah lewat where('not like', '%Potongan%') di atas.
        return $query->get()->map(function ($row) {
            $row->barang_name = $this->stripPotPrefix($row->barang_name);
            return $row;
        });
    }

    private function stripPotPrefix(?string $barangName): ?string
    {
        if ($barangName === null) {
            return $barangName;
        }

        $trimmed  = trim($barangName);
        $stripped = preg_replace('/^Pot\.?\s+/i', '', $trimmed);

        return $stripped !== '' ? $stripped : $trimmed;
    }

    private function prodLineSumByDepartment(string $departmentId, ?string $barangCategory = null): float
    {
        if (!$this->hasAnyFilterInput()) {
            return 0;
        }

        $query = DB::table('mon_prod_lines')
            ->where('mon_prod_lines.department_id', $departmentId)
            ->selectRaw('SUM(mon_prod_lines.jumlah) as jumlah');

        if ($barangCategory !== null) {
            $query->join('mon_ms_barangs', 'mon_ms_barangs.barang_code', '=', 'mon_prod_lines.barang_code')
                ->where('mon_ms_barangs.barang_category', $barangCategory);
        }

        $this->scopeByCodeProd($query, $this->filterUraianListExcludingNegara());

        return (float) ($query->value('jumlah') ?? 0);
    }

    private function prodLineSumByDestination(string $keyword, ?string $barangCategory = null): float
    {
        if (!$this->hasAnyFilterInput()) {
            return 0;
        }

        $query = DB::table('mon_prod_lines')
            ->where('mon_prod_lines.destination', 'like', "%{$keyword}%")
            ->selectRaw('SUM(mon_prod_lines.jumlah) as jumlah');

        if ($barangCategory !== null) {
            $query->join('mon_ms_barangs', 'mon_ms_barangs.barang_code', '=', 'mon_prod_lines.barang_code')
                ->where('mon_ms_barangs.barang_category', $barangCategory);
        }

        $this->scopeByCodeProd($query, $this->filterUraianListExcludingNegara());

        return (float) ($query->value('jumlah') ?? 0);
    }

    /**
     * SUM(mon_shipments.jumlah_barang), opsional di-scope ke barang_category.
     * mon_shipments sudah punya kolom `barang_category` sendiri (lihat
     * shipmentByCategory()), jadi difilter langsung tanpa perlu join mon_ms_barangs.
     */
    private function shipmentSumByCategory(?string $barangCategory = null): float
    {
        $query = $this->shipmentQuery()->selectRaw('SUM(jumlah_barang) as jumlah');

        if ($barangCategory !== null) {
            $query->where('barang_category', $barangCategory);
        }

        return (float) ($query->value('jumlah') ?? 0);
    }

    /**
     * `code_prod` di mon_prod_lines tidak punya kolom uraian langsung --
     * di-match via LIKE ke setiap kode CPO di $cpoCodes (bisa lebih dari 1
     * kalau filternya Buyer/Style yang match banyak CPO sekaligus).
     * $cpoCodes null berarti tidak ada filter Buyer/Style/CPO/OCF aktif
     * sama sekali (tidak dibatasi CPO); array kosong berarti filter itu
     * aktif tapi match 0 CPO (paksa hasil kosong). Filter Negara SELALU
     * diterapkan terpisah lewat applyNegaraScopeToProdLines(), independen
     * dari $cpoCodes.
     */
    private function scopeByCodeProd($query, ?array $cpoCodes)
    {
        if ($cpoCodes !== null) {
            if (empty($cpoCodes)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($cpoCodes) {
                    foreach ($cpoCodes as $cpoCode) {
                        $q->orWhere('code_prod', 'like', "CPO {$cpoCode} %")
                            ->orWhere('code_prod', 'like', "{$cpoCode} %");
                    }
                });
            }
        }

        // Tahap Cutting..Warehouse (semua sumber dari mon_prod_lines) di-scope
        // LANGSUNG juga ke `code_prod` yang cocok dengan negara terpilih.
        $this->applyNegaraScopeToProdLines($query);

        return $query;
    }

    public function topMaterialExcess(int $limit = 3): Collection
    {
        return $this->rekonQuery()
            ->select('barang_name')
            ->selectRaw('SUM(saldo_gudang) as saldo_gudang')
            ->groupBy('barang_name')
            ->havingRaw('SUM(saldo_gudang) > 0')
            ->orderByDesc('saldo_gudang')
            ->limit($limit)
            ->get();
    }

    public function detail(): Collection
    {
        $shipmentAgg = $this->shipmentQuery()
            ->select('barang_code')
            ->selectRaw('SUM(jumlah_barang) as out_doc')
            ->groupBy('barang_code');

        $query = $this->rekonQuery()
            ->leftJoinSub($shipmentAgg, 'ship', function ($join) {
                $join->on('ship.barang_code', '=', 'mon_rekonsiliasis.barang_code');
            });

        // Pengaman tambahan: pastikan tabel Detail Rekonsiliasi per Material
        // SELALU ikut filter uraian/Buyer/Style/OCF/Negara yang aktif (jangan
        // sampai balik menampilkan seluruh mon_rekonsiliasis kalau salah satu
        // filter di atas sedang dipakai). Kolom di-qualify eksplisit ke
        // mon_rekonsiliasis supaya tidak ambigu setelah leftJoinSub di atas.
        if ($this->hasAnyFilterInput()) {
            $query->whereIn('mon_rekonsiliasis.uraian', $this->filterUraianList());
        }

        return $query
            ->select(
                'mon_rekonsiliasis.no_po',
                'mon_rekonsiliasis.jenis_po',
                'mon_rekonsiliasis.tgl_po',
                'mon_rekonsiliasis.tgl_pengiriman',
                'mon_rekonsiliasis.supplier_name',
                'mon_rekonsiliasis.barang_code',
                'mon_rekonsiliasis.barang_name',
                'mon_rekonsiliasis.satuan_order',
                'mon_rekonsiliasis.jumlah_order',
                'mon_rekonsiliasis.jumlah_doc',
                'mon_rekonsiliasis.out_req',
                'mon_rekonsiliasis.out_prod',
                'mon_rekonsiliasis.sisa',
                'mon_rekonsiliasis.saldo_wip',
                'mon_rekonsiliasis.saldo_gudang',
                'mon_rekonsiliasis.harga_total'
            )
            ->selectRaw('COALESCE(ship.out_doc, 0) as out_doc')
            ->orderBy('mon_rekonsiliasis.barang_name')
            ->get();
    }

    public function shipmentDetail(int $limit = 100): Collection
    {
        return $this->shipmentQuery()
            ->select(
                'doc_id',
                'uraian',
                'jenis_doc',
                'jenis_ps',
                'no_ps',
                'no_bukti',
                'tgl_bukti',
                'no_invoice',
                'supplier_name',
                'barang_code',
                'barang_name',
                'satuan_doc',
                'jumlah_doc',
                'jumlah_barang',
                'valas',
                'nilai_fob',
                'berat'
            )
            ->orderByDesc('tgl_bukti')
            ->limit($limit)
            ->get();
    }

    /**
     * Loss per tahap TIDAK lagi sekadar selisih output tahap sebelumnya vs
     * sekarang -- tiap tahap punya basis perhitungannya sendiri:
     *  - Cutting   : dept Cutting − Contract
     *  - Sewing    : dest Sewing − dept Sewing
     *  - Packing   : dest Packing − dept Packing
     *  - Warehouse : dept Packing − dest Warehouse
     *  - Shipment  : dest Warehouse − Shipment (basis akhir ke pengiriman aktual)
     */
    public function pipelineLossSteps(): Collection
    {
        $p = $this->productionPipeline();

        $steps = collect();

        $steps->push($this->lossStep(
            'Contract → Cutting',
            $p['contract'],
            $p['dept_cutting'],
            $p['dept_cutting'] - $p['contract']
        ));

        $steps->push($this->lossStep(
            'Cutting → Sewing',
            $p['dept_cutting'],
            $p['dept_sewing'],
            $p['dept_sewing'] - $p['dest_sewing']
        ));

        $steps->push($this->lossStep(
            'Sewing → Packing',
            $p['dept_sewing'],
            $p['dept_packing'],
            $p['dept_packing'] - $p['dest_packing']
        ));

        $steps->push($this->lossStep(
            'Packing → Warehouse',
            $p['dept_packing'],
            $p['dest_warehouse'],
            $p['dest_warehouse'] - $p['dept_packing']
        ));

        $steps->push($this->lossStep(
            'Cutting → Shipment',
            $p['dept_cutting'],
            $p['shipment'],
            $p['shipment'] - $p['dept_cutting']
        ));

        return $steps;
    }

    private function lossStep(string $process, float $input, float $output, float $loss): object
    {
        return (object) [
            'process'  => $process,
            'input'    => $input,
            'output'   => $output,
            'loss_pcs' => $loss,
            'loss_pct' => $input > 0 ? round($loss / $input * 100, 2) : null,
        ];
    }

    public function shipmentByCategory(): Collection
    {
        return $this->shipmentQuery()
            ->select('barang_category')
            ->selectRaw('SUM(jumlah_barang) as jumlah_barang')
            ->selectRaw('SUM(nilai_fob) as nilai_fob')
            ->groupBy('barang_category')
            ->orderByDesc(DB::raw('SUM(jumlah_barang)'))
            ->get();
    }

    public function shipmentByDate(): Collection
    {
        return $this->shipmentQuery()
            ->whereNotNull('tgl_bukti')
            ->select('tgl_bukti')
            ->selectRaw('SUM(jumlah_barang) as jumlah_barang')
            ->groupBy('tgl_bukti')
            ->orderBy('tgl_bukti')
            ->get();
    }

    /**
     * Ringkasan jumlah dokumen & qty shipment per tanggal `tgl_bukti`, untuk
     * satu bulan tertentu -- dipakai widget kalender "Shipment Date"
     * (replikasi kalender Production Delivery di dashboard Gabungan).
     * Filter uraian/brand/style/negara yang aktif tetap diikutkan.
     */
    public function shipmentCalendar(int $year, int $month): Collection
    {
        return $this->shipmentQuery()
            ->whereNotNull('tgl_bukti')
            ->whereYear('tgl_bukti', $year)
            ->whereMonth('tgl_bukti', $month)
            ->selectRaw('CAST(tgl_bukti AS DATE) as tanggal')
            ->selectRaw('COUNT(*) as jumlah_doc')
            ->selectRaw('SUM(jumlah_barang) as total_qty')
            ->groupBy(DB::raw('CAST(tgl_bukti AS DATE)'))
            ->orderBy('tanggal')
            ->get();
    }

    /**
     * Detail baris shipment untuk satu tanggal `tgl_bukti` spesifik
     * (dipanggil saat user klik tanggal di kalender Shipment Date).
     * Filter aktif (termasuk negara) tetap diikutkan.
     */
    public function shipmentCalendarDetail(string $date): Collection
    {
        return $this->shipmentQuery()
            ->whereDate('tgl_bukti', $date)
            ->select(
                'uraian',
                'no_bukti',
                'jenis_doc',
                'supplier_name',
                'barang_name',
                'satuan_doc',
                'jumlah_doc',
                'jumlah_barang'
            )
            ->orderBy('uraian')
            ->get();
    }

    // ===== TAMBAHAN BARU: filter negara options berdasarkan filter =====

    /**
     * Mengembalikan daftar negara (dari master mon_ms_negaras) yang valid
     * untuk filter Buyer/Style/CPO/OCF yang diberikan -- dropdown Negara
     * SELALU mengikuti cascade filter tersebut (bukan filter Negara itu
     * sendiri), jadi tidak semua negara langsung ditampilkan. Negara
     * dianggap valid kalau minimal ada 1 baris mon_orders.destination yang
     * cocok (LIKE) dengan nama negara tsb, dari order yang match Buyer/
     * Style/CPO/OCF yang sedang aktif.
     */
    public function filteredNegaraOptions(array $filters): Collection
    {
        // Simpan filter sementara
        $originalFilters = $this->filters;
        $this->filters = $filters;

        // Cascade dari Buyer/Style/CPO/OCF saja (TANPA Negara), supaya
        // dropdown Negara tidak membatasi diri sendiri lewat filter Negara
        // yang sedang dipilih user.
        $cpoScope = $this->filterUraianListExcludingNegara();

        // Kembalikan filter asli
        $this->filters = $originalFilters;

        return $this->matchNegaraFromOrders($cpoScope);
    }
}
