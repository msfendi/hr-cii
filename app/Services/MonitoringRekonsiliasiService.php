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
     *    punya shipment dari negara terpilih (lihat cpoListForNegara()).
     *    mon_prod_lines (Cutting/Sewing/Packing/Warehouse) tidak punya info
     *    supplier/negara sendiri, jadi filter negara di tahap-tahap itu
     *    di-approx lewat CPO yang match -- shipmentQuery() sendiri masih
     *    di-scope LANGSUNG ke baris supplier yang cocok (lihat di bawah)
     *    supaya Shipment Qty tetap presisi per baris.
     */
    private function filterUraianList(): array
    {
        if ($this->resolvedUraian !== null) {
            return $this->resolvedUraian;
        }

        $uraian = trim((string) ($this->filters['uraian'] ?? ''));
        $brand  = trim((string) ($this->filters['brand'] ?? ''));
        $style  = trim((string) ($this->filters['style'] ?? ''));
        $negara = trim((string) ($this->filters['negara'] ?? ''));
        $ocf    = trim((string) ($this->filters['ocf'] ?? ''));

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

        return $this->resolvedUraian = $base ?? [];
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
            $query->whereIn('uraian', $this->filterUraianList());
        }
        return $query;
    }

    private function shipmentQuery()
    {
        $query = DB::table('mon_shipments');
        if ($this->hasAnyFilterInput()) {
            $query->whereIn('uraian', $this->filterUraianList());
        }
        // Selain di-scope lewat CPO (di atas, yang sudah mengandung filter
        // negara via intersect), baris shipment juga di-scope LANGSUNG ke
        // supplier yang cocok dengan negara terpilih, supaya presisi per
        // baris (bukan cuma per CPO).
        $this->applyNegaraScopeToShipment($query);
        return $query;
    }

    /**
     * Dropdown filter Negara: semua negara yang punya minimal 1 supplier
     * terdaftar di mon_ms_suppliers, di-join ke mon_ms_negaras untuk nama.
     */
    public function negaraOptions(): Collection
    {
        return DB::table('mon_ms_suppliers as s')
            ->join('mon_ms_negaras as n', 'n.negara_code', '=', 's.negara_id')
            ->whereNotNull('s.negara_id')
            ->select('n.negara_code', 'n.negara_name')
            ->distinct()
            ->orderBy('n.negara_name')
            ->get();
    }

    /**
     * Nama-nama supplier (mon_ms_suppliers.supplier_name, TANPA kode di
     * dalam kurung) yang negara_id-nya cocok dengan $negaraCode.
     */
    private function supplierNamesForNegara(string $negaraCode): array
    {
        return DB::table('mon_ms_suppliers')
            ->where('negara_id', $negaraCode)
            ->whereNotNull('supplier_name')
            ->pluck('supplier_name')
            ->all();
    }

    /**
     * Semua kode uraian (CPO) yang punya minimal 1 baris mon_shipments
     * dengan supplier dari negara $negaraCode.
     *
     * mon_shipments.supplier_name formatnya "NAMA SUPPLIER (KODE)"
     * (contoh: "MAXHILL MANAGEMENT SERVICES PTE LTD (MAXHIL)"), sedangkan
     * mon_ms_suppliers.supplier_name tidak punya kode di belakangnya --
     * jadi dicocokkan pakai LIKE prefix ("NAMA SUPPLIER%").
     */
    public function cpoListForNegara(string $negaraCode): array // diubah dari private ke public
    {
        $supplierNames = $this->supplierNamesForNegara($negaraCode);
        if (empty($supplierNames)) {
            return [];
        }

        return DB::table('mon_shipments')
            ->whereNotNull('uraian')
            ->where(function ($q) use ($supplierNames) {
                foreach ($supplierNames as $name) {
                    $q->orWhere('supplier_name', 'like', $name . '%');
                }
            })
            ->distinct()
            ->pluck('uraian')
            ->all();
    }

    /**
     * Scope query mon_shipments langsung ke baris supplier yang negaranya
     * cocok dengan filter `negara` (kalau ada). Dipakai oleh shipmentQuery()
     * supaya KPI Shipment Qty & PIVOT SHIPMENT presisi per baris shipment,
     * bukan cuma "per CPO yang kebetulan punya 1 shipment dari negara itu".
     */
    private function applyNegaraScopeToShipment($query): void
    {
        $negara = trim((string) ($this->filters['negara'] ?? ''));
        if ($negara === '') {
            return;
        }

        $supplierNames = $this->supplierNamesForNegara($negara);
        if (empty($supplierNames)) {
            // Negara dipilih tapi tidak ada supplier terdaftar untuk negara
            // itu -- pastikan hasilnya kosong, bukan malah menampilkan semua.
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($supplierNames) {
            foreach ($supplierNames as $name) {
                $q->orWhere('supplier_name', 'like', $name . '%');
            }
        });
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
     * relevan dengan filter Buyer/Style yang SEDANG aktif -- dijembatani lewat
     * uraian (mon_orders), persis pola cascade yang sama dengan
     * MonitoringDashboardService::ocfCodesForCurrentBrandStyle(). Dropdown OCF
     * sengaja TIDAK ikut disaring oleh CPO/Negara/OCF itu sendiri, supaya
     * pilihan lain tetap terlihat -- hanya Buyer/Style yang mempersempitnya.
     */
    private function ocfCodesForCurrentBrandStyle(): Collection
    {
        $brand = trim((string) ($this->filters['brand'] ?? ''));
        $style = trim((string) ($this->filters['style'] ?? ''));

        $cacheKey = 'mon_rekon:ocf_codes:' . md5(json_encode(['brand' => $brand, 'style' => $style]));

        return Cache::remember($cacheKey, self::FILTER_OPTIONS_TTL, function () use ($brand, $style) {
            $query = DB::table('mon_boms')->whereNotNull('code_prod')->select('code_prod')->distinct();

            if ($brand !== '' || $style !== '') {
                $uraianSubquery = DB::table('mon_orders')->select('uraian')->distinct();
                if ($brand !== '') {
                    $uraianSubquery->where('brand', $brand);
                }
                if ($style !== '') {
                    $uraianSubquery->where('style', $style);
                }
                $query->whereIn('uraian', $uraianSubquery);
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
     * endpoint data() setiap kali Buyer/Style berubah.
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
     *  - Consumption           : Use for GMT / Contract Qty (mon_orders.qty_ord) --
     *                            asumsi kg kain terpakai per pcs garment yang di-order.
     *                            Ganti penyebutnya (mis. shipment_qty) kalau maksudnya beda.
     */
    public function fabricUsage(): array
    {
        $totalOutReq = (float) ($this->rekonQuery()->where('satuan_code', 'KGM')->sum('out_req') ?? 0);

        $scrapQty = 0.0;
        if ($this->hasCpo()) {
            $query = DB::table('mon_prod_lines')
                ->where('barang_code', '01SCRP00001')
                ->selectRaw('SUM(jumlah) as jumlah');
            $this->scopeByCodeProd($query, $this->filterUraianList());
            $scrapQty = (float) ($query->value('jumlah') ?? 0);
        }

        $useForGmt = $totalOutReq - $scrapQty;
        $contract  = (float) ($this->orderQuery()->sum('qty_ord') ?? 0);

        return [
            'use_for_gmt' => $useForGmt,
            'scrap_qty'   => $scrapQty,
            'usage_pct'   => $totalOutReq > 0 ? round($useForGmt / $totalOutReq * 100) : 0,
            'scrap_pct'   => $totalOutReq > 0 ? round($scrapQty / $totalOutReq * 100) : 0,
            'consumption' => $contract > 0 ? round($useForGmt / $contract, 2) : 0,
        ];
    }

    public function cpoOptions(): Collection
    {
        return DB::table('mon_rekonsiliasis')
            ->whereNotNull('uraian')
            ->distinct()
            ->orderBy('uraian')
            ->pluck('uraian');
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
                'order_pct'       => $pct($order, $need),
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

        $totalLoss = $contract - $shipment;

        return [
            'contract'    => $contract,
            'departments' => $departments,
            'shipment'    => $shipment,
            'total_loss'  => $totalLoss,
            'loss_pct'    => $contract > 0 ? round($totalLoss / $contract * 100, 2) : 0,

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
        if (!$this->hasCpo()) {
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

        $this->scopeByCodeProd($query, $this->filterUraianList());

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
        if (!$this->hasCpo()) {
            return 0;
        }

        $query = DB::table('mon_prod_lines')
            ->where('mon_prod_lines.department_id', $departmentId)
            ->selectRaw('SUM(mon_prod_lines.jumlah) as jumlah');

        if ($barangCategory !== null) {
            $query->join('mon_ms_barangs', 'mon_ms_barangs.barang_code', '=', 'mon_prod_lines.barang_code')
                ->where('mon_ms_barangs.barang_category', $barangCategory);
        }

        $this->scopeByCodeProd($query, $this->filterUraianList());

        return (float) ($query->value('jumlah') ?? 0);
    }

    private function prodLineSumByDestination(string $keyword, ?string $barangCategory = null): float
    {
        if (!$this->hasCpo()) {
            return 0;
        }

        $query = DB::table('mon_prod_lines')
            ->where('mon_prod_lines.destination', 'like', "%{$keyword}%")
            ->selectRaw('SUM(mon_prod_lines.jumlah) as jumlah');

        if ($barangCategory !== null) {
            $query->join('mon_ms_barangs', 'mon_ms_barangs.barang_code', '=', 'mon_prod_lines.barang_code')
                ->where('mon_ms_barangs.barang_category', $barangCategory);
        }

        $this->scopeByCodeProd($query, $this->filterUraianList());

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
     */
    private function scopeByCodeProd($query, array $cpoCodes)
    {
        return $query->where(function ($q) use ($cpoCodes) {
            foreach ($cpoCodes as $cpoCode) {
                $q->orWhere('code_prod', 'like', "CPO {$cpoCode} %")
                    ->orWhere('code_prod', 'like', "{$cpoCode} %");
            }
        });
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

        return $this->rekonQuery()
            ->leftJoinSub($shipmentAgg, 'ship', function ($join) {
                $join->on('ship.barang_code', '=', 'mon_rekonsiliasis.barang_code');
            })
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
            $p['dest_sewing'] - $p['dept_sewing']
        ));

        $steps->push($this->lossStep(
            'Sewing → Packing',
            $p['dept_sewing'],
            $p['dept_packing'],
            $p['dest_packing'] - $p['dept_packing']
        ));

        $steps->push($this->lossStep(
            'Packing → Warehouse',
            $p['dept_packing'],
            $p['dest_warehouse'],
            $p['dept_packing'] - $p['dest_warehouse']
        ));

        $steps->push($this->lossStep(
            'Warehouse → Shipment',
            $p['dest_warehouse'],
            $p['shipment'],
            $p['dest_warehouse'] - $p['shipment']
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
     * Mengembalikan daftar negara yang valid untuk filter yang diberikan.
     * Hanya negara yang memiliki minimal satu CPO yang cocok dengan filter
     * (uraian/brand/style/negara) yang akan muncul.
     */
    public function filteredNegaraOptions(array $filters): Collection
    {
        // Simpan filter sementara
        $originalFilters = $this->filters;
        $this->filters = $filters;

        // Dapatkan daftar CPO yang cocok dengan filter
        $cpoList = $this->filterUraianList();

        // Kembalikan filter asli
        $this->filters = $originalFilters;

        if (empty($cpoList)) {
            // Tidak ada CPO yang cocok → tidak ada negara yang valid
            return collect();
        }

        // Ambil semua negara
        $allCountries = $this->negaraOptions();

        // Filter: hanya negara yang memiliki irisan CPO dengan $cpoList
        return $allCountries->filter(function ($country) use ($cpoList) {
            $cpoForCountry = $this->cpoListForNegara($country->negara_code);
            return count(array_intersect($cpoList, $cpoForCountry)) > 0;
        })->values();
    }
}