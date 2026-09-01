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
 *  - mon_work_orders     : sumber NEED (SUM(jumlah_prod * cons) per row) untuk fabric Qty & basis ORDER%
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
 *
 * SIMPLIFIKASI FILTER OCF/SUB_REF (menggantikan pendekatan regex lama):
 *  Tabel mon_rekonsiliasis, mon_shipments, dan mon_prod_lines TIDAK punya
 *  kolom ocf_no/sub_ref asli seperti mon_orders -- sebelumnya OCF/Sub Ref
 *  di-resolve dulu ke daftar CPO (uraian) lewat mon_orders, lalu daftar itu
 *  dipakai untuk whereIn ke tabel lain. Pendekatan itu SALAH karena satu
 *  CPO/uraian bisa menaungi banyak OCF/sub_ref sekaligus -- hasilnya baris
 *  yang tidak relevan ikut ke-include (over-count) atau, sebaliknya, bikin
 *  kombinasi tertentu ke-drop duluan sebelum sempat dicek (under-count).
 *  Ini yang menyebabkan fabricUsage() tidak konsisten: total_out_req
 *  (dari mon_rekonsiliasis) dan scrap_qty (dari mon_prod_lines) dihitung
 *  dengan basis matching yang berbeda untuk filter yang "sama".
 *
 *  Sekarang SEMUA tabel pakai aturan yang sama & sederhana (tanpa regex):
 *   - Filter CPO (uraian)     -> whereIn('uraian', ...) / LIKE ke kolom
 *                                yang menyimpan CPO (code_prod).
 *   - Filter OCF               -> LIKE '%{ocf}%' ke kolom teks bebas milik
 *                                tabel itu sendiri (spesifikasi / no_ps /
 *                                code_prod).
 *   - Filter Sub Ref            -> LIKE '%{sub_ref}%' ke kolom yang sama.
 *   - Kalau CPO & OCF dua-duanya aktif -> keduanya di-AND-kan (uraian
 *     match DAN kolom teks bebas match OCF-nya).
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
     * CATATAN: fungsi ini tetap dipakai untuk dropdown cascade DAN untuk
     * NEED (mon_work_orders, yang cuma granular sampai level CPO), karena
     * di situ OCF/Sub Ref di-bridge lewat mon_orders.ocf_no/sub_ref yang
     * ASLI (exact match, bukan LIKE tebak-tebakan) -- beda kasus dengan
     * rekonQuery()/shipmentQuery()/scopeByCodeProd() yang sekarang
     * menghindari bridge ini dan men-scope OCF/Sub Ref LANGSUNG ke kolom
     * teks bebas milik tabelnya masing-masing (lihat catatan di atas class).
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
        $subRef = in_array('sub_ref', $excludeKeys, true) ? '' : trim((string) ($this->filters['sub_ref'] ?? ''));

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

        // OCF (nilai persis mon_orders.ocf_no) -- sama seperti Negara,
        // bisa dipakai sendirian atau dikombinasikan
        // dengan Buyer/Style/CPO/Negara lewat IRISAN (intersect) daftar CPO.
        if ($ocf !== '') {
            $ocfCpo = $this->cpoListForOcf($ocf);
            $base = $base === null ? $ocfCpo : array_values(array_intersect($base, $ocfCpo));
        }

        // SUB_REF (nilai persis mon_orders.sub_ref) -- sama seperti OCF,
        // bisa dipakai sendirian atau dikombinasikan dengan Buyer/Style/
        // CPO/Negara/OCF lewat IRISAN (intersect) daftar CPO.
        if ($subRef !== '') {
            $subRefCpo = $this->cpoListForSubRef($subRef);
            $base = $base === null ? $subRefCpo : array_values(array_intersect($base, $subRefCpo));
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

    /**
     * Versi khusus untuk scopeByCodeProd() (mon_prod_lines/mon_prod_qc):
     * kecualikan Negara, OCF, DAN Sub Ref dari bridge mon_orders.uraian --
     * BUKAN cuma Negara seperti filterUraianListExcludingNegara().
     *
     * scopeByCodeProd() sudah men-scope OCF/Sub Ref/Negara LANGSUNG ke
     * kolom `code_prod` (LIKE sederhana, tanpa bridge -- lihat komentar di
     * scopeByCodeProd()), sama seperti rekonQuery() yang juga meng-exclude
     * ['ocf', 'sub_ref'] dari cpoListExcluding()-nya. Kalau OCF/Sub Ref
     * TETAP diikutkan di sini juga, hasilnya di-AND dua kali dengan basis
     * matching yang beda (bridge via mon_orders.uraian vs LIKE langsung ke
     * code_prod) -- begitu code_prod TIDAK memuat teks uraian yang sama
     * persis (mis. mon_prod_qc.code_prod cuma berisi kode OCF apa adanya,
     * bukan uraian), whereIn-style OR-LIKE dari bridge otomatis gagal
     * match apapun (atau malah balikin array kosong kalau OCF itu tidak
     * ketemu persis di mon_orders.ocf_no) dan MEMAKSA 1=0 walau filter
     * OCF langsungnya sebenarnya cocok. Makanya OCF/Sub Ref harus
     * di-exclude dari bridge ini juga.
     */
    private function filterUraianListForCodeProdScope(): ?array
    {
        return $this->cpoListExcluding(['negara', 'ocf', 'sub_ref']);
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
        foreach (['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref'] as $key) {
            if (trim((string) ($this->filters[$key] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * True kalau filter yang aktif HANYA OCF saja (uraian/brand/style/
     * negara/sub_ref semuanya kosong) -- dipakai shipmentPlanVsActual()
     * untuk menentukan kapan chart PLAN VS ACTUAL SHIPMENT REPORT harus
     * dipecah per sub_ref (bukan per tanggal), karena satu OCF biasanya
     * menaungi banyak CPO dengan sub_ref berbeda-beda.
     */
    private function isOcfOnlyFilter(): bool
    {
        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($ocf === '') {
            return false;
        }

        foreach (['uraian', 'brand', 'style', 'negara', 'sub_ref'] as $key) {
            if (trim((string) ($this->filters[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Query dasar mon_rekonsiliasis, di-scope simpel:
     *  - Buyer/Style/CPO/Negara -> whereIn('uraian', ...) seperti biasa.
     *  - OCF                    -> LIKE '%{ocf}%' langsung ke `spesifikasi`.
     *  - Sub Ref                -> LIKE '%{sub_ref}%' langsung ke `spesifikasi`.
     *  - CPO & OCF dua-duanya aktif -> di-AND-kan (uraian match DAN
     *    spesifikasi match OCF-nya), bukan lagi lewat bridge/regex.
     */
    private function rekonQuery()
    {
        $query = DB::table('mon_purchase_orders');

        if (!$this->hasAnyFilterInput()) {
            return $query;
        }

        // Scope level CPO dari Buyer/Style/CPO/Negara SAJA. OCF & Sub Ref
        // sengaja di-exclude dari bridge ini -- mon_rekonsiliasis tidak
        // punya kolom ocf_no/sub_ref asli, jadi keduanya dicocokkan
        // LANGSUNG ke `spesifikasi` di bawah (LIKE sederhana), supaya basis
        // matching-nya konsisten dengan mon_shipments & mon_prod_lines.
        $core = $this->cpoListExcluding(['ocf', 'sub_ref']);
        if ($core !== null) {
            // whereIn dengan array kosong otomatis menghasilkan 0 baris
            // (Laravel), jadi filter yang match 0 CPO tetap kosong.
            $query->whereIn('uraian', $core);
        }

        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($ocf !== '') {
            $query->whereNotNull('spesifikasi')
                ->whereRaw('UPPER(spesifikasi) LIKE ?', ['%' . strtoupper($ocf) . '%']);
        }

        $subRef = trim((string) ($this->filters['sub_ref'] ?? ''));
        if ($subRef !== '') {
            $query->whereNotNull('spesifikasi')
                ->whereRaw('UPPER(spesifikasi) LIKE ?', ['%' . strtoupper($subRef) . '%']);
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
        // mon_orders punya kolom ocf_no/sub_ref ASLI, jadi tetap exact
        // match langsung di sini (bukan LIKE) -- ini yang paling akurat
        // dan dipakai juga sebagai basis mon_work_orders (NEED).
        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($ocf !== '') {
            $query->where('ocf_no', $ocf);
        }
        $subRef = trim((string) ($this->filters['sub_ref'] ?? ''));
        if ($subRef !== '') {
            $query->where('sub_ref', $subRef);
        }
        // Step Contract di-scope LANGSUNG ke baris mon_orders yang
        // `destination`-nya cocok dengan negara terpilih.
        $this->applyNegaraScopeToOrder($query);
        return $query;
    }

    /**
     * Query dasar mon_shipments, di-scope simpel:
     *  - Buyer/Style/CPO/Negara -> whereIn('uraian', ...).
     *  - OCF                    -> LIKE '%{ocf}%' langsung ke `no_ps`.
     *  - Sub Ref                -> LIKE '%{sub_ref}%' langsung ke `no_ps`.
     */
    private function shipmentQuery()
    {
        $query = DB::table('mon_shipments');
        if ($this->hasAnyFilterInput()) {
            // Sama seperti rekonQuery(): OCF/Sub Ref di-exclude dari bridge
            // uraian ini -- mon_shipments tidak punya kolom ocf_no/sub_ref
            // asli, jadi keduanya di-scope langsung ke `no_ps` di bawah.
            $core = $this->cpoListExcluding(['negara', 'ocf', 'sub_ref']);
            if ($core !== null) {
                $query->whereIn('uraian', $core);
            }
        }
        // Step Shipment di-scope LANGSUNG ke baris mon_shipments yang
        // `spesifikasi`-nya cocok dengan negara terpilih.
        $this->applyNegaraScopeToShipment($query);
        $this->applyOcfSubRefScopeToShipment($query);
        return $query;
    }

    /**
     * Scope query mon_shipments langsung ke baris yang `no_ps`-nya
     * mengandung OCF/Sub Ref terpilih (LIKE sederhana, tidak ada
     * parsing/regex lagi). Dipakai shipmentQuery() supaya semua widget
     * berbasis shipment (Shipment Qty, Shipment By Date, Pivot Shipment,
     * Shipment Detail, Shipment Calendar, dst) konsisten dengan
     * rekonQuery()/scopeByCodeProd().
     */
    private function applyOcfSubRefScopeToShipment($query): void
    {
        $ocf    = trim((string) ($this->filters['ocf'] ?? ''));
        $subRef = trim((string) ($this->filters['sub_ref'] ?? ''));

        if ($ocf === '' && $subRef === '') {
            return;
        }

        $query->whereNotNull('no_ps');

        if ($ocf !== '') {
            $query->whereRaw('UPPER(no_ps) LIKE ?', ['%' . strtoupper($ocf) . '%']);
        }

        if ($subRef !== '') {
            $query->whereRaw('UPPER(no_ps) LIKE ?', ['%' . strtoupper($subRef) . '%']);
        }
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

    // ===== FILTER OCF (diambil langsung dari mon_orders.ocf_no) =====

    /**
     * Semua kode uraian (CPO) yang punya minimal 1 baris mon_orders dengan
     * `ocf_no` PERSIS sama dengan $ocfNo (dropdown OCF diisi dari nilai
     * ocf_no apa adanya, jadi matching-nya exact, bukan ekstraksi regex).
     * Di-cache per nilai OCF karena dipakai berulang untuk resolve filter
     * OCF ke CPO (khusus untuk dropdown cascade & NEED/mon_work_orders --
     * BUKAN lagi untuk men-scope mon_rekonsiliasis/mon_shipments/
     * mon_prod_lines, lihat catatan di atas class).
     */
    private function cpoListForOcf(string $ocfNo): array
    {
        $cacheKey = 'mon_rekon:cpo_for_ocf:' . md5($ocfNo);

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
     * filter Buyer/Style/CPO(uraian)/Negara yang SEDANG
     * aktif -- semuanya dijembatani lewat mon_orders.uraian, pakai basis yang
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
            $query = DB::table('mon_orders')->whereNotNull('ocf_no')->whereNotNull('uraian')->select('ocf_no')->distinct();

            if ($cpoScope !== null) {
                if (empty($cpoScope)) {
                    // Kombinasi Buyer/Style/Uraian/Negara tidak match CPO apapun.
                    return collect();
                }
                $query->whereIn('uraian', $cpoScope);
            }

            return $query->pluck('ocf_no')
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

    // ===== FILTER SUB_REF (diambil langsung dari mon_orders.sub_ref) =====

    /**
     * Semua kode uraian (CPO) yang punya minimal 1 baris mon_orders dengan
     * `sub_ref` PERSIS sama dengan $subRef (dropdown Sub Ref diisi dari
     * nilai sub_ref apa adanya, exact match, sama seperti OCF). Di-cache
     * per nilai sub_ref karena dipakai berulang untuk resolve filter
     * sub_ref ke CPO (khusus dropdown cascade & NEED, bukan untuk men-scope
     * mon_rekonsiliasis/mon_shipments/mon_prod_lines).
     */
    private function cpoListForSubRef(string $subRef): array
    {
        $cacheKey = 'mon_rekon:cpo_for_sub_ref:' . md5($subRef);

        return Cache::remember($cacheKey, self::FILTER_OPTIONS_TTL, function () use ($subRef) {
            return DB::table('mon_orders')
                ->whereNotNull('sub_ref')
                ->whereNotNull('uraian')
                ->where('sub_ref', $subRef)
                ->distinct()
                ->pluck('uraian')
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * Daftar nilai Sub Ref (mon_orders.sub_ref apa adanya) yang relevan
     * dengan filter Buyer/Style/CPO(uraian)/OCF/Negara yang SEDANG aktif --
     * basisnya sama (cpoListExcluding()) dengan dropdown lain supaya
     * cascade-nya BOLAK-BALIK. Sub Ref itu sendiri tetap di-exclude supaya
     * tidak membatasi diri sendiri.
     */
    private function subRefCodesForCurrentFilters(): Collection
    {
        $cpoScope = $this->cpoListExcluding(['sub_ref']);

        $cacheKey = 'mon_rekon:sub_ref_codes:' . md5(json_encode($cpoScope));

        return Cache::remember($cacheKey, self::FILTER_OPTIONS_TTL, function () use ($cpoScope) {
            $query = DB::table('mon_orders')->whereNotNull('sub_ref')->whereNotNull('uraian')->select('sub_ref')->distinct();

            if ($cpoScope !== null) {
                if (empty($cpoScope)) {
                    // Kombinasi Buyer/Style/Uraian/OCF/Negara tidak match CPO apapun.
                    return collect();
                }
                $query->whereIn('uraian', $cpoScope);
            }

            return $query->pluck('sub_ref')
                ->unique()
                ->sort()
                ->values();
        });
    }

    /**
     * Versi publik dari subRefCodesForCurrentFilters(), dipakai controller
     * untuk mengisi dropdown Sub Ref awal (index()) maupun cascade-nya
     * lewat endpoint data() setiap kali Buyer/Style/Uraian/OCF/Negara berubah.
     */
    public function subRefOptions(): Collection
    {
        return $this->subRefCodesForCurrentFilters();
    }

    /**
     * "FABRIC QTY (KGM)": NEED / ORDER / RECEIVED / OUT WIP / STOCK untuk
     * material kain (satuan_code = 'KGM'), di-scope ke CPO terpilih.
     *  - ORDER    : SUM(mon_rekonsiliasis.jumlah_order) WHERE satuan_code = 'KGM'.
     *  - RECEIVED : SUM(mon_rekonsiliasis.jumlah_doc) WHERE satuan_code = 'KGM'.
     *  - OUT WIP  : SUM(mon_rekonsiliasis.out_req) WHERE satuan_code = 'KGM'.
     *  - STOCK    : SUM(mon_rekonsiliasis.saldo_gudang) WHERE satuan_code = 'KGM'.
     *  - NEED     : SUM(mon_work_orders.jumlah_prod * mon_work_orders.cons)
     *               WHERE satuan_code = 'KGM', di-scope melalui join ke
     *               mon_boms (filter uraian).
     *
     * Relasi: mon_work_orders.product_code = mon_boms.barang_jadi (produk jadi)
     *         mon_work_orders.barang_code = mon_boms.barang_code (komponen)
     */
    public function fabricQty(): array
    {
        $order    = (float) ($this->rekonQuery()->where('satuan_order', 'KGM')->sum('jumlah_order') ?? 0);
        $received = (float) ($this->rekonQuery()->where('satuan_order', 'KGM')->sum('total_in') ?? 0);
        $outWip   = (float) ($this->rekonQuery()->where('satuan_order', 'KGM')->sum('total_req') ?? 0) + ($this->rekonQuery()->where('satuan_order', 'KGM')->sum('total_doc') ?? 0);
        $stock    = (float) ($this->rekonQuery()->where('satuan_order', 'KGM')->sum('total_gudang') ?? 0);

        // NEED dari mon_work_orders: SUM(jumlah_prod * cons) per row (bukan
        // lagi SUM(request)), di-scope via mon_boms supaya hanya menghitung
        // material milik CPO terpilih. mon_work_orders cuma granular sampai
        // level CPO, jadi OCF/Sub Ref di sini tetap lewat bridge exact-match
        // mon_orders (filterUraianList()), bukan LIKE ke kolom teks bebas.
        $need = 0.0;
        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($this->hasCpo()) {
            $need = (float) DB::table('mon_work_orders as wo')
                ->whereRaw('UPPER(code_prod) LIKE ?', ['%' . strtoupper($ocf) . '%'])
                // ->whereIn('wo.uraian', $this->filterUraianList())
                ->where('wo.satuan_code', 'KGM')
                ->selectRaw('SUM(wo.jumlah_prod * wo.cons) as total')
                ->value('total') ?? 0;
        }

        // Perhitungan baru (persentase, konsisten dengan materialAchievement()):
        //  - order    : ORDER / NEED
        //  - received : RECEIVED / NEED
        //  - out_wip  : OUT WIP / RECEIVED
        //  - stock    : STOCK / RECEIVED
        $pct = fn($num, $denom) => $denom > 0 ? round(max(0, (float) $num) / $denom * 100, 1) : 0;

        return [
            'need'     => $need,
            'order'    => $pct($order, $need),
            'received' => $pct($received, $need),
            'out_wip'  => $pct($outWip, $need),
            'stock'    => $pct($stock, $need),
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
     *
     * total_out_req (mon_rekonsiliasis) dan scrap_qty (mon_prod_lines)
     * sekarang di-scope pakai basis matching yang SAMA untuk OCF/Sub Ref
     * (LIKE sederhana ke kolom teks bebas masing-masing tabel, lihat
     * rekonQuery()/scopeByCodeProd()), jadi keduanya konsisten satu sama
     * lain untuk filter yang sama.
     */
    public function fabricUsage(): array
    {
        $totalOutReq = (float) ($this->rekonQuery()->where('satuan_order', 'KGM')->sum('out_req') ?? 0);

        $scrapQty = 0.0;
        $outputCutting = 0.0;
        if ($this->hasAnyFilterInput()) {
            $query = DB::table('mon_prod_lines')
                ->where('barang_code', '01SCRP00001')
                ->selectRaw('SUM(jumlah) as jumlah');
            $this->scopeByCodeProd($query, $this->filterUraianListForCodeProdScope());
            $scrapQty = (float) ($query->value('jumlah') ?? 0);

            // Output Cutting = qty yang destination-nya Sewing (keluar dari Cutting menuju Sewing).
            $outputCutting = $this->prodLineSumByDestination('Sewing', MsBarang::CATEGORY_WIP);
        }

        $useForGmt = $totalOutReq - $scrapQty;

        return [
            'total_out_req' => $totalOutReq,
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
     * mon_orders (sama seperti dropdown Buyer/Style/OCF lain), lalu
     * diirisankan ke cpoScope hasil filter lain.
     */
    public function cpoOptions(): Collection
    {
        $cpoScope = $this->cpoListExcluding(['uraian']);

        $query = DB::table('mon_orders')->whereNotNull('uraian');
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
            'buyer'   => $this->buyerOptions(),
            'style'   => $this->styleOptions(),
            'uraian'  => $this->cpoOptions(),
            'ocf'     => $this->ocfOptions(),
            'sub_ref' => $this->subRefOptions(),
            'negara'  => $this->filteredNegaraOptions($this->filters),
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
     * "MATERIAL ACHIEVEMENT": persentase tiap tahap dihitung berantai,
     * dengan NEED sebagai baseline (selalu 100% kalau NEED > 0):
     *  - NEED%     : NEED / NEED (patokan, selalu 100%), dengan NEED = SUM(mon_work_orders.jumlah_prod * mon_work_orders.cons).
     *  - ORDER%    : mon_rekonsiliasis.jumlah_order / NEED.
     *  - RECEIVED% : (mon_rekonsiliasis.jumlah_doc - mon_rekonsiliasis.out_doc) / NEED.
     *  - OUT PROD% : mon_rekonsiliasis.out_req / (mon_rekonsiliasis.jumlah_doc - mon_rekonsiliasis.out_doc).
     *  - STOCK%    : mon_rekonsiliasis.saldo_gudang / (mon_rekonsiliasis.jumlah_doc - mon_rekonsiliasis.out_doc).
     *
     * `out_doc` (mon_rekonsiliasis.out_doc) dikurangkan dari jumlah_doc untuk
     * mendapatkan "jumlah diterima bersih" yang jadi pembagi OUT PROD% &
     * STOCK%, sekaligus pembilang RECEIVED%.
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
            ->selectRaw('SUM(out_doc) as out_doc')
            ->selectRaw('SUM(out_req) as out_req')
            ->selectRaw('SUM(total_gudang) as saldo_gudang')
            ->selectRaw('SUM(harga_total) as harga_total')
            ->where('jumlah_doc', '!=', 0)
            ->groupBy('barang_code', 'barang_name')
            ->orderBy('barang_name')
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        // NEED per barang_code dari mon_work_orders: SUM(jumlah_prod * cons)
        // per row (bukan lagi SUM(request)), di-scope ke CPO yang sama
        // dengan widget lain (lihat fabricQty()).
        $needByCode = collect();
        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($this->hasCpo()) {
            $needByCode = DB::table('mon_work_orders')
                // ->whereIn('uraian', $this->filterUraianList())
                ->whereRaw('UPPER(code_prod) LIKE ?', ['%' . strtoupper($ocf) . '%'])
                ->select('barang_code')
                ->selectRaw('SUM(jumlah_prod * cons) as request')
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
            $order  = (float) $r->jumlah_order;
            $doc    = (float) $r->jumlah_doc;
            $outDoc = (float) $r->out_doc;
            // Jumlah diterima bersih = jumlah_doc - out_doc, dipakai sebagai
            // pembagi OUT PROD% & STOCK%, dan pembilang RECEIVED%.
            $netReceived = $doc - $outDoc;
            $need   = (float) ($needByCode[$r->barang_code]->request ?? 0);

            $pct = fn($num, $denom) => $denom > 0 ? round(max(0, (float) $num) / $denom * 100) : 0;

            return (object) [
                'barang_code'     => $r->barang_code,
                'barang_name'     => $r->barang_name,
                'barang_category' => $categoryByCode[$r->barang_code] ?? null,
                'material_group'  => $this->materialAchievementGroup($categoryByCode[$r->barang_code] ?? null),
                // Total harga (mon_rekonsiliasis.harga_total, di-SUM per
                // barang_code) -- dipakai frontend untuk ditampilkan di
                // tooltip chart Material/Fabric Achievement saat di-hover.
                'harga_total'     => (float) ($r->harga_total ?? 0),
                // NEED% jadi baseline/patokan (selalu 100% kalau NEED > 0),
                // ORDER% & RECEIVED% sekarang dihitung terhadap NEED (bukan
                // lagi terhadap ORDER itu sendiri) -- lihat instruksi
                // perubahan formula fabricQty().
                'need_pct'        => $pct($need, $need),
                'order_pct'       => $pct($order, $need),
                // RECEIVED% = (jumlah_doc - out_doc) / need.
                'received_pct'    => $pct($doc, $need),
                // OUT PROD% = out_req / (jumlah_doc - out_doc).
                'out_prod_pct'    => $pct($r->out_req, $need),
                // STOCK% = saldo_gudang / (jumlah_doc - out_doc).
                'stock_pct'       => $pct($r->saldo_gudang, $need),
                // Qty mentah (bukan persentase) untuk masing-masing bar --
                // dipakai frontend supaya tooltip chart Material Achievement
                // bisa menampilkan qty asli di samping persentase saat di-hover.
                'need_qty'        => $need,
                'order_qty'       => $order,
                'received_qty'    => $doc,
                'out_prod_qty'    => (float) $r->out_req,
                'stock_qty'       => (float) $r->saldo_gudang,
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
     *  - QC        : mon_prod_qc.jumlah,    department_id = QC
     *                (stage inspeksi yang di-inject di antara Sewing dan
     *                Packing, lihat prodQcSumByDepartment())
     *  - Packing   : mon_prod_lines.jumlah, department_id = Packing
     *  - Warehouse : mon_prod_lines.jumlah, destination   = Warehouse
     *  - Shipment  : mon_shipments.jumlah_barang
     *
     * dest_sewing & dest_packing (mon_prod_lines.jumlah per kolom
     * `destination`) juga dihitung di sini karena dipakai sebagai basis
     * loss per tahap, lihat pipelineLossSteps().
     *
     * Setiap department di atas juga dilengkapi `remarks` (koleksi object
     * {id, remark} dari mon_stage_remarks, dicocokkan lewat department_id
     * -- dan OCF kalau filter OCF aktif) supaya frontend bisa menampilkannya
     * di bawah persentase loss pada masing-masing stage box, LENGKAP dengan
     * `id` supaya tombol hapus per baris remark bisa memanggil route
     * monitoring.rekonsiliasi.stage-remark.destroy.
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
        $deptQc      = $this->prodQcSumByDepartment('QC');
        $deptPacking = $this->prodLineSumByDepartment('Packing', MsBarang::CATEGORY_JADI);

        $destSewing    = $this->prodLineSumByDestination('Sewing', MsBarang::CATEGORY_WIP);
        $destPacking   = $this->prodLineSumByDestination('Packing', MsBarang::CATEGORY_JADI);
        $destWarehouse = $this->prodLineSumByDestination('Warehouse', MsBarang::CATEGORY_JADI);

        $shipment = $this->shipmentSumByCategory(MsBarang::CATEGORY_JADI);

        $departments = collect([
            (object) ['department_id' => 'Cutting', 'jumlah' => $deptCutting, 'remarks' => $this->stageRemarksByDepartment('Cutting')],
            (object) ['department_id' => 'Sewing', 'jumlah' => $deptSewing, 'remarks' => $this->stageRemarksByDepartment('Sewing')],
            (object) ['department_id' => 'QC', 'jumlah' => $deptQc, 'remarks' => $this->stageRemarksByDepartment('QC')],
            (object) ['department_id' => 'Packing', 'jumlah' => $deptPacking, 'remarks' => $this->stageRemarksByDepartment('Packing')],
            (object) ['department_id' => 'Warehouse', 'jumlah' => $destWarehouse, 'remarks' => $this->stageRemarksByDepartment('Warehouse')],
        ]);

        // Cabang SABKON (PABRIK LUAR): terpisah dari flow produksi internal
        // di atas, sumbernya tabel mon_subkons (bukan mon_prod_lines), dan
        // di-scope HANYA lewat filter OCF (dicocokkan ke mon_subkons.no_order)
        // -- lihat subkonSumByField().
        //  - Sabkon (Pabrik Luar) : SUM(qty_result_order)
        //  - Warehouse (Sabkon)   : SUM(qty_result_aktual)
        $sabkonPabrikLuar = $this->subkonSumByField('qty_result_order');
        $sabkonWarehouse  = $this->subkonSumByField('qty_result_aktual');

        $sabkonDepartments = collect([
            (object) ['department_id' => 'Sabkon', 'jumlah' => $sabkonPabrikLuar, 'remarks' => $this->stageRemarksByDepartment('Sabkon')],
            (object) ['department_id' => 'Warehouse (Sabkon)', 'jumlah' => $sabkonWarehouse, 'remarks' => $this->stageRemarksByDepartment('Warehouse (Sabkon)')],
        ]);

        // ===== Rumus per tahap (revisi) =====
        //  - Loss cutting    = output cutting - (total contract - sabkon)
        //  - Cutting %       = output cutting / (total contract - sabkon)
        //  - Cutting loss %  = loss cutting / output cutting
        $cuttingBase    = $contract - $sabkonPabrikLuar;
        $lossCutting    = $deptCutting - $cuttingBase;
        $cuttingPct     = $cuttingBase > 0 ? round($deptCutting / $cuttingBase * 100, 2) : 0;
        $cuttingLossPct = $deptCutting > 0 ? round($lossCutting / $deptCutting * 100, 2) : 0;

        //  - Shipment loss   = shipment - total contract
        //  - Shipment loss % = shipment / total contract
        $shipmentLoss    = $shipment - $contract;
        $shipmentLossPct = $contract > 0 ? round($shipment / $contract * 100, 2) : 0;

        //  - Total process loss   = loss sewing + loss qc + loss packing
        //  - Total process loss % = total process loss / contract total
        $lossSewing  = $deptSewing - $destSewing;
        $lossQc      = $deptQc - $deptSewing;
        $lossPacking = $deptPacking - $destPacking;
        $totalLoss   = $lossSewing + $lossQc + $lossPacking;
        $lossPct     = $contract > 0 ? round($totalLoss / $contract * 100, 2) : 0;

        // Balance Garment Stock = (Warehouse [Work In Process] + Warehouse
        // [Sabkon]) - Shipment (Total).
        $balanceGarmentStock = ($destWarehouse + $sabkonWarehouse) - $shipment;

        // Remark untuk kotak Balance Garment Stock (department_id khusus
        // di mon_stage_remarks, tidak terhubung ke stage produksi manapun).
        $balanceGarmentStockRemarks = $this->stageRemarksByDepartment('Balance Garment Stock');

        return [
            'contract'    => $contract,
            'departments' => $departments,
            'shipment'    => $shipment,
            'total_loss'  => $totalLoss,
            'loss_pct'    => $lossPct,

            // Cabang Sabkon (Pabrik Luar) -> Warehouse (Sabkon), ditampilkan
            // sebagai grup terpisah di sebelah flow produksi internal.
            // Sabkon selalu dianggap 100% tercapai (loss selalu 0).
            'sabkon' => $sabkonDepartments,

            // Nilai mentah per tahap, dipakai pipelineLossSteps() untuk
            // menghitung loss per tahap sesuai definisi masing-masing.
            'dept_cutting'   => $deptCutting,
            'dept_sewing'    => $deptSewing,
            'dept_qc'        => $deptQc,
            'dept_packing'   => $deptPacking,
            'dest_sewing'    => $destSewing,
            'dest_packing'   => $destPacking,
            'dest_warehouse' => $destWarehouse,

            'sabkon_pabrik_luar' => $sabkonPabrikLuar,
            'sabkon_warehouse'   => $sabkonWarehouse,

            // Cutting: basis kontrak dikurangi sabkon.
            'cutting_base'     => $cuttingBase,
            'loss_cutting'     => $lossCutting,
            'cutting_pct'      => $cuttingPct,
            'cutting_loss_pct' => $cuttingLossPct,

            // Komponen Total Process Loss.
            'loss_sewing'  => $lossSewing,
            'loss_qc'      => $lossQc,
            'loss_packing' => $lossPacking,

            // Shipment vs Total Contract.
            'shipment_loss'     => $shipmentLoss,
            'shipment_loss_pct' => $shipmentLossPct,

            // Balance Garment Stock = (Warehouse + Warehouse Sabkon) - Shipment.
            'balance_garment_stock'          => $balanceGarmentStock,
            'balance_garment_stock_remarks'  => $balanceGarmentStockRemarks,
        ];
    }

    /**
     * SUM(mon_subkons.{$field}) di-scope HANYA lewat filter OCF, dicocokkan
     * ke mon_subkons.no_order (bukan code_prod -- tabel ini datang dari
     * smartit lewat query get_subkon, kolom identifikasinya no_order/no_po
     * atau no_so, bukan CPO/uraian). Kalau filter OCF kosong, sengaja balik
     * 0 (tidak ada basis pencarian) -- sama seperti stage lain yang butuh
     * hasAnyFilterInput().
     */
    private function subkonSumByField(string $field): float
    {
        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($ocf === '') {
            return 0;
        }

        return (float) (DB::table('mon_subkons')
            ->whereRaw('UPPER(no_order) LIKE ?', ['%' . strtoupper($ocf) . '%'])
            ->sum($field) ?? 0);
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

        $this->scopeByCodeProd($query, $this->filterUraianListForCodeProdScope());

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

        $this->scopeByCodeProd($query, $this->filterUraianListForCodeProdScope());

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

        $this->scopeByCodeProd($query, $this->filterUraianListForCodeProdScope());

        return (float) ($query->value('jumlah') ?? 0);
    }

    /**
     * SUM(mon_prod_qc.jumlah) untuk 1 department_id (mis. 'QC'), di-scope
     * ke filter CPO/OCF/Sub Ref/Negara yang sama seperti tahap-tahap
     * mon_prod_lines lainnya (lewat scopeByCodeProd(), karena mon_prod_qc
     * juga punya kolom `code_prod`). Dipakai untuk inject stage QC di
     * antara Sewing dan Packing pada productionPipeline().
     */
    private function prodQcSumByDepartment(string $departmentId): float
    {
        if (!$this->hasAnyFilterInput()) {
            return 0;
        }

        $query = DB::table('mon_prod_qc')
            ->where('mon_prod_qc.department_id', $departmentId)
            ->selectRaw('SUM(mon_prod_qc.jumlah) as jumlah');

        $this->scopeByCodeProd($query, $this->filterUraianListForCodeProdScope());

        return (float) ($query->value('jumlah') ?? 0);
    }

    /**
     * Ambil daftar remark (mon_stage_remarks.id + .remark) untuk 1
     * department_id, dicocokkan juga dengan filter OCF yang aktif (kolom
     * mon_stage_remarks.ocf_no) -- dipakai untuk menampilkan remark di
     * bawah persentase loss pada tiap stage box di PRODUCTION FLOW /
     * STAGE PIPELINE.
     *
     * Balikannya berupa Collection of object {id, remark} (BUKAN cuma
     * teks remark) supaya front-end (rekonsiliasi_blade.php /
     * rekonsiliasi_ocf_blade.php) bisa menampilkan tombol hapus per baris
     * remark -- ikon hapus butuh `id` untuk memanggil
     * DELETE /rekonsiliasi/stage-remark/{id}
     * (route monitoring.rekonsiliasi.stage-remark.destroy).
     */
    private function stageRemarksByDepartment(string $departmentId): Collection
    {
        $query = DB::table('mon_stage_remarks')
            ->where('department_id', $departmentId);

        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($ocf !== '') {
            $query->whereNotNull('ocf_no')
                ->whereRaw('UPPER(ocf_no) LIKE ?', ['%' . strtoupper($ocf) . '%']);
        }

        return $query->orderByDesc('id')
            ->select('id', 'remark')
            ->get()
            ->filter(fn($row) => trim((string) $row->remark) !== '')
            ->map(fn($row) => ['id' => $row->id, 'remark' => $row->remark])
            ->values();
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
     * Scope mon_prod_lines ke filter CPO/OCF/Sub Ref/Negara -- SIMPLE LIKE,
     * tanpa parsing/regex/boundary-anchor lagi:
     *  - CPO(s)   : whereIn-style OR LIKE '%{cpo}%' ke `code_prod`.
     *  - OCF      : LIKE '%{ocf}%' ke `code_prod`.
     *  - Sub Ref  : LIKE '%{sub_ref}%' ke `code_prod`.
     *  - Negara   : didelegasikan ke applyNegaraScopeToProdLines() (tidak berubah).
     */
    private function scopeByCodeProd($query, ?array $cpoCodes)
    {
        if ($cpoCodes !== null) {
            if (empty($cpoCodes)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($cpoCodes) {
                    foreach ($cpoCodes as $cpoCode) {
                        $q->orWhereRaw('UPPER(code_prod) LIKE ?', ['%' . strtoupper($cpoCode) . '%']);
                    }
                });
            }
        }

        $ocf = trim((string) ($this->filters['ocf'] ?? ''));
        if ($ocf !== '') {
            $query->whereNotNull('code_prod')
                ->whereRaw('UPPER(code_prod) LIKE ?', ['%' . strtoupper($ocf) . '%']);
        }

        $subRef = trim((string) ($this->filters['sub_ref'] ?? ''));
        if ($subRef !== '') {
            $query->whereNotNull('code_prod')
                ->whereRaw('UPPER(code_prod) LIKE ?', ['%' . strtoupper($subRef) . '%']);
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
            ->selectRaw('SUM(total_gudang) as saldo_gudang')
            ->groupBy('barang_name')
            ->havingRaw('SUM(total_gudang) > 0')
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
                $join->on('ship.barang_code', '=', 'mon_purchase_orders.barang_code');
            });

        return $query
            ->select(
                'mon_purchase_orders.no_po',
                'mon_purchase_orders.jenis_po',
                'mon_purchase_orders.tgl_po',
                'mon_purchase_orders.tgl_pengiriman',
                'mon_purchase_orders.supplier_name',
                'mon_purchase_orders.barang_code',
                'mon_purchase_orders.barang_name',
                'mon_purchase_orders.satuan_order',
                'mon_purchase_orders.jumlah_order',
                'mon_purchase_orders.jumlah_doc',
                'mon_purchase_orders.out_req',
                'mon_purchase_orders.out_prod',
                'mon_purchase_orders.sisa',
                // 'mon_purchase_orders.saldo_wip',
                // 'mon_purchase_orders.saldo_gudang',
                'mon_purchase_orders.harga_total'
            )
            ->selectRaw('COALESCE(ship.out_doc, 0) as out_doc')
            ->orderBy('mon_purchase_orders.barang_name')
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
     * Loss per tahap -- tiap tahap punya basis perhitungannya sendiri:
     *  - Cutting   : output Cutting − (Total Contract − Sabkon); loss %
     *                dihitung terhadap output Cutting (bukan basisnya),
     *                sesuai definisi: Cutting loss % = loss cutting / output cutting.
     *  - Sewing    : dest Sewing − dept Sewing
     *  - QC        : dept QC − dept Sewing (stage inspeksi hasil Sewing,
     *                di-inject di antara Sewing dan Packing, sumber data
     *                mon_prod_qc -- lihat prodQcSumByDepartment())
     *  - Packing   : dest Packing − dept Packing (basis input sekarang
     *                dept QC, menggantikan dept Sewing karena QC sudah
     *                disisipkan di antara keduanya)
     *  - Warehouse : dept Packing − dest Warehouse
     *  - Shipment  : Shipment − Total Contract (basis kontrak, bukan lagi
     *                output Cutting); loss % = shipment / total contract.
     *  - Sabkon    : selalu dianggap 100% tercapai, loss selalu 0.
     */
    public function pipelineLossSteps(): Collection
    {
        $p = $this->productionPipeline();

        $steps = collect();

        // Cutting: basis = Total Contract - Sabkon. Loss % dihitung
        // terhadap OUTPUT Cutting (bukan basisnya) -- lihat docblock di atas.
        $steps->push((object) [
            'process'  => 'Contract → Cutting',
            'input'    => $p['cutting_base'],
            'output'   => $p['dept_cutting'],
            'loss_pcs' => $p['loss_cutting'],
            'loss_pct' => $p['cutting_loss_pct'],
        ]);

        $steps->push($this->lossStep(
            'Cutting → Sewing',
            $p['dept_cutting'],
            $p['dept_sewing'],
            $p['dept_sewing'] - $p['dest_sewing']
        ));

        $steps->push($this->lossStep(
            'Sewing → QC',
            $p['dept_sewing'],
            $p['dept_qc'],
            $p['dept_qc'] - $p['dept_sewing']
        ));

        $steps->push($this->lossStep(
            'QC → Packing',
            $p['dept_qc'],
            $p['dept_packing'],
            $p['dept_packing'] - $p['dest_packing']
        ));

        $steps->push($this->lossStep(
            'Packing → Warehouse',
            $p['dept_packing'],
            $p['dest_warehouse'],
            $p['dest_warehouse'] - $p['dept_packing']
        ));

        // Shipment: basis = Total Contract (bukan lagi output Cutting).
        // Loss % = shipment / total contract (bukan loss / contract).
        $steps->push((object) [
            'process'  => 'Cutting → Shipment',
            'input'    => $p['contract'],
            'output'   => $p['shipment'],
            'loss_pcs' => $p['shipment_loss'],
            'loss_pct' => $p['shipment_loss_pct'],
        ]);

        // Cabang SABKON (PABRIK LUAR) -- terpisah dari chain produksi
        // internal di atas. Sabkon selalu dianggap 100% tercapai, jadi
        // loss selalu 0 untuk kedua tahapnya (Sabkon & Warehouse (Sabkon)).
        $steps->push((object) [
            'process'  => 'Sabkon (Pabrik Luar)',
            'input'    => 0,
            'output'   => $p['sabkon_pabrik_luar'],
            'loss_pcs' => 0,
            'loss_pct' => 0,
        ]);

        $steps->push((object) [
            'process'  => 'Sabkon → Warehouse (Sabkon)',
            'input'    => $p['sabkon_pabrik_luar'],
            'output'   => $p['sabkon_warehouse'],
            'loss_pcs' => 0,
            'loss_pct' => 0,
        ]);

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
     * "PLAN VS ACTUAL SHIPMENT REPORT" (dulu "SHIPMENT BY DATE"):
     *  - PLAN   : mon_orders.qty_ord (Contract Qty).
     *  - ACTUAL : mon_shipments -- jumlah aktual yang sudah dikirim
     *             (kolom `jumlah_barang`, sumber yang sama dengan
     *             shipmentByDate()/widget shipment lain).
     *
     * Ada 2 mode tampilan:
     *  - 'date'    (default): PLAN & ACTUAL dikelompokkan per tanggal
     *    `tgl_bukti` -- ACTUAL persis shipmentByDate() (qty aktual per
     *    tanggal, sekarang sudah scoped LIKE per OCF/Sub Ref lewat
     *    shipmentQuery()), PLAN memakai total Contract Qty untuk scope
     *    filter yang sedang aktif, ditampilkan rata di setiap tanggal
     *    supaya kedua bar bisa dibandingkan berdampingan sebagai patokan.
     *  - 'sub_ref' (khusus filter OCF SENDIRIAN tanpa Buyer/Style/CPO/
     *    Negara/Sub Ref): PLAN & ACTUAL dikelompokkan per TANGGAL shipment
     *    DAN mon_orders.sub_ref sekaligus (karena 1 OCF biasanya menaungi
     *    banyak CPO dengan banyak sub_ref berbeda-beda). Mode ini masih
     *    memakai parsing `no_ps` (extractSubRefsFromNoPs()) karena butuh
     *    memisahkan qty PER sub_ref, bukan sekadar filter ya/tidak seperti
     *    widget lain.
     */
    public function shipmentPlanVsActual(): array
    {
        if ($this->isOcfOnlyFilter()) {
            return $this->shipmentPlanVsActualBySubRef();
        }

        return $this->shipmentPlanVsActualByDate();
    }

    private function shipmentPlanVsActualByDate(): array
    {
        $actualRows = $this->shipmentByDate();
        $totalPlan  = (float) ($this->orderQuery()->sum('qty_ord') ?? 0);

        $labels = $actualRows->pluck('tgl_bukti')->values()->all();
        $actual = $actualRows->pluck('jumlah_barang')->map(fn($v) => (float) $v)->values()->all();
        // PLAN ditampilkan rata (flat) di setiap tanggal sebagai patokan
        // total Contract Qty untuk scope filter yang sedang aktif.
        $plan = array_fill(0, count($labels), $totalPlan);

        return [
            'mode'   => 'date',
            'labels' => $labels,
            'plan'   => $plan,
            'actual' => $actual,
        ];
    }

    private function shipmentPlanVsActualBySubRef(): array
    {
        $ocf = trim((string) ($this->filters['ocf'] ?? ''));

        // PLAN per sub_ref: SUM(qty_ord) dari mon_orders untuk OCF terpilih.
        // Nilai ini FLAT per sub_ref (Contract Qty tidak punya tanggal
        // shipment sendiri), dipakai berulang di setiap tanggal tempat
        // sub_ref tsb muncul sebagai patokan pembanding ACTUAL. Daftar
        // sub_ref di sini SEKALIGUS jadi "kamus" master yang valid untuk
        // OCF ini -- dipakai memvalidasi hasil parsing no_ps di bawah.
        $planRows = DB::table('mon_orders')
            ->where('ocf_no', $ocf)
            ->whereNotNull('sub_ref')
            ->select('sub_ref')
            ->selectRaw('SUM(qty_ord) as qty_ord')
            ->groupBy('sub_ref')
            ->get()
            ->keyBy('sub_ref');

        $validSubRefs = $planRows->keys()->all();

        // ACTUAL: sumber sebenarnya adalah mon_shipments.no_ps, BUKAN
        // `uraian` -- karena satu `uraian` bisa menaungi banyak sub_ref
        // sekaligus (mon_orders punya banyak baris sub_ref per uraian yang
        // sama), jadi `uraian` tidak bisa dipakai untuk memisahkan qty per
        // sub_ref. `no_ps` isinya teks bebas/format tidak konsisten, mis.
        // "26063 OCF 266C0038 A - A1" atau "26067 OCF 266C0040/A/A1/A2/E5"
        // -- diparsing lewat extractSubRefsFromNoPs() di bawah, tiap token
        // hasil parsing divalidasi ke $validSubRefs supaya noise seperti
        // "(ADD)"/"2MWa"/singkatan lain otomatis kebuang.
        $actualRows = $this->shipmentQuery()
            ->whereNotNull('tgl_bukti')
            ->whereNotNull('no_ps')
            ->select('tgl_bukti', 'no_ps')
            ->selectRaw('SUM(jumlah_barang) as jumlah_barang')
            ->groupBy('tgl_bukti', 'no_ps')
            ->get();

        // Gabungkan ke bucket (tanggal|sub_ref) -- satu bucket = satu bar
        // group di chart (Plan & Actual berdampingan), dengan sub_ref
        // sebagai anotasi teks di atas bar, bukan sebagai label sumbu-X.
        $buckets = [];
        foreach ($actualRows as $row) {
            $subRefsFound = $this->extractSubRefsFromNoPs((string) $row->no_ps, $ocf, $validSubRefs);
            if (empty($subRefsFound)) {
                continue;
            }

            // Satu no_ps bisa menyebut beberapa sub_ref sekaligus (mis.
            // "OCF 266C0040/A/A1/A2/E5" = 4 sub_ref) -- karena tidak ada
            // info porsi masing-masing, qty dibagi RATA ke tiap sub_ref
            // yang disebut (kesepakatan bisnis: bagi rata).
            $qtyPerSubRef = (float) $row->jumlah_barang / count($subRefsFound);

            foreach ($subRefsFound as $subRef) {
                $key = $row->tgl_bukti . '|' . $subRef;
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'tgl_bukti' => $row->tgl_bukti,
                        'sub_ref'   => $subRef,
                        'actual'    => 0.0,
                    ];
                }
                $buckets[$key]['actual'] += $qtyPerSubRef;
            }
        }

        // Urutkan berdasarkan tanggal dulu, lalu sub_ref, supaya chart
        // terbaca rapi berurutan per tanggal shipment.
        $bucketList = collect($buckets)->values()->sortBy([
            ['tgl_bukti', 'asc'],
            ['sub_ref', 'asc'],
        ])->values();

        $labels  = $bucketList->pluck('tgl_bukti')->all();
        $subRefs = $bucketList->pluck('sub_ref')->all();
        $actual  = $bucketList->pluck('actual')->map(fn($v) => (float) $v)->all();
        $plan    = $bucketList
            ->map(fn($b) => (float) ($planRows[$b['sub_ref']]->qty_ord ?? 0))
            ->values()
            ->all();

        return [
            'mode'    => 'sub_ref',
            // Label sumbu-X = tanggal shipment (tgl_bukti), SAMA seperti
            // mode 'date' -- bedanya di sini satu tanggal bisa muncul lebih
            // dari sekali kalau ada beberapa sub_ref yang shipment di
            // tanggal yang sama (masing-masing jadi bar group tersendiri).
            'labels'  => $labels,
            // Sub_ref sejajar/paralel dengan labels-plan-actual, dipakai
            // frontend untuk anotasi teks DI ATAS tiap bar (sub_ref di
            // baris atas, qty di baris bawahnya -- lihat renderShipmentByDate()
            // di rekonsiliasi_blade.php).
            'subRefs' => $subRefs,
            'plan'    => $plan,
            'actual'  => $actual,
        ];
    }

    /**
     * Parse mon_shipments.no_ps (teks bebas, format TIDAK konsisten -- lihat
     * contoh di komentar shipmentPlanVsActualBySubRef()) untuk menemukan
     * sub_ref-sub_ref yang relevan dengan $ocf yang sedang aktif.
     *
     * CATATAN: helper ini SENGAJA masih dipertahankan (tidak ikut
     * disederhanakan) karena shipmentPlanVsActualBySubRef() butuh MEMECAH
     * qty per sub_ref individual untuk chart, bukan sekadar filter
     * ya/tidak seperti widget lain -- LIKE sederhana tidak cukup untuk
     * kebutuhan ini.
     */
    private function extractSubRefsFromNoPs(string $noPs, string $ocf, array $validSubRefs): array
    {
        if ($ocf === '' || $noPs === '') {
            return [];
        }

        // Cari kode OCF persis (word-boundary, case-insensitive) di no_ps.
        $pattern = '/(?<![A-Za-z0-9])' . preg_quote($ocf, '/') . '(?![A-Za-z0-9])/i';
        if (!preg_match($pattern, $noPs, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $matchEnd = $m[0][1] + strlen($m[0][0]);
        $after = substr($noPs, $matchEnd);

        // Split kasar per delimiter umum -- JANGAN split spasi di tahap ini,
        // supaya sub_ref majemuk yang mengandung spasi (kalau memang valid)
        // tidak keburu terpecah salah.
        $rawTokens = preg_split('/[\/\-,]+/', $after) ?: [];

        // Kamus lookup case-insensitive: SUB_REF_UPPER => nilai_asli.
        $validSet = [];
        foreach ($validSubRefs as $sr) {
            $validSet[strtoupper(trim((string) $sr))] = $sr;
        }

        $found = [];
        foreach ($rawTokens as $token) {
            $clean = strtoupper(trim($token));
            if ($clean === '') {
                continue;
            }

            if (isset($validSet[$clean])) {
                $found[$validSet[$clean]] = true;
                continue;
            }

            // Token utuh tidak match -- coba pecah per kata (menangani
            // noise semacam "C5 (ADD)" / "F3 2MWa").
            foreach (preg_split('/\s+/', $clean) ?: [] as $word) {
                $word = trim($word, "() \t\n\r");
                if ($word !== '' && isset($validSet[$word])) {
                    $found[$validSet[$word]] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Ringkasan jumlah dokumen & qty shipment per tanggal `tgl_bukti`, untuk
     * satu bulan tertentu -- dipakai widget kalender "Shipment Date"
     * (replikasi kalender Production Delivery di dashboard Gabungan).
     * Filter uraian/brand/style/negara/ocf/sub_ref yang aktif tetap diikutkan.
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
     * Filter aktif (termasuk negara/ocf/sub_ref) tetap diikutkan.
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
