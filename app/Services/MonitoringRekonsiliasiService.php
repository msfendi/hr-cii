<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Satu service untuk dashboard Rekonsiliasi (gabungan), menarik data dari:
 *  - mon_orders          : Contract Qty (qty_ord) & info brand/style untuk header CPO
 *  - mon_rekonsiliasis   : material achievement, top excess, detail per material
 *  - mon_prod_lines      : tahapan produksi per department (Production Result),
 *                          + tahap Warehouse (dari kolom `destination`),
 *                          + scrap qty (barang_code = '01SCRP00001') untuk Fabric Usage
 *  - mon_purchase_orders : pivot Material Purchase
 *  - mon_boms            : pivot Work Order (item BOM yang belum di-PO-kan)
 *  - mon_work_orders     : sumber `request` (NEED) untuk fabric Qty, di-scope via mon_boms
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
    /** Cache hasil resolve daftar uraian (CPO) dari filter Buyer/Style/CPO. */
    private ?array $resolvedUraian = null;

    public function __construct(protected array $filters = []) {}

    public static function make(array $filters): self
    {
        return new self($filters);
    }

    /**
     * Resolve filter Buyer/Style/CPO menjadi daftar kode uraian (CPO) yang
     * dipakai untuk scope semua query di bawah.
     *  - Kalau CPO dipilih eksplisit, itu final -- Buyer/Style diabaikan di
     *    server (mereka cuma dipakai buat mempersempit dropdown di frontend).
     *  - Kalau CPO belum dipilih tapi Buyer dan/atau Style ada, cari semua
     *    uraian yang match kombinasi tsb dari mon_orders.
     */
    private function filterUraianList(): array
    {
        if ($this->resolvedUraian !== null) {
            return $this->resolvedUraian;
        }

        $uraian = trim((string) ($this->filters['uraian'] ?? ''));
        $brand  = trim((string) ($this->filters['brand'] ?? ''));
        $style  = trim((string) ($this->filters['style'] ?? ''));

        if ($uraian !== '') {
            return $this->resolvedUraian = [$uraian];
        }

        if ($brand === '' && $style === '') {
            return $this->resolvedUraian = [];
        }

        $query = DB::table('mon_orders')->whereNotNull('uraian');
        if ($brand !== '') {
            $query->where('brand', $brand);
        }
        if ($style !== '') {
            $query->where('style', $style);
        }

        return $this->resolvedUraian = $query->distinct()->pluck('uraian')->all();
    }

    private function hasCpo(): bool
    {
        return count($this->filterUraianList()) > 0;
    }

    private function rekonQuery()
    {
        $query = DB::table('mon_rekonsiliasis');
        if ($this->hasCpo()) {
            $query->whereIn('uraian', $this->filterUraianList());
        }
        return $query;
    }

    private function orderQuery()
    {
        $query = DB::table('mon_orders');
        if ($this->hasCpo()) {
            $query->whereIn('uraian', $this->filterUraianList());
        }
        return $query;
    }

    private function shipmentQuery()
    {
        $query = DB::table('mon_shipments');
        if ($this->hasCpo()) {
            $query->whereIn('uraian', $this->filterUraianList());
        }
        return $query;
    }

    /**
     * "FABRIC QTY (KGM)": NEED / ORDER / RECEIVED / OUT WIP untuk material
     * kain (satuan_code = 'KGM'), di-scope ke CPO terpilih.
     *  - ORDER    : SUM(mon_rekonsiliasis.jumlah_order) WHERE satuan_code = 'KGM'.
     *  - RECEIVED : SUM(mon_rekonsiliasis.jumlah_doc) WHERE satuan_code = 'KGM'.
     *  - OUT WIP  : SUM(mon_rekonsiliasis.out_req) WHERE satuan_code = 'KGM'.
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

        // NEED dari mon_work_orders.request (kolom `request` = NEED qty hasil
        // sinkronisasi get_ppic_bom.txt, lihat SyncWorkOrderFromSmartit),
        // di-scope via mon_boms supaya hanya menghitung material milik CPO
        // terpilih. Kolom wo.product_code / wo.barang_code / wo.satuan_code /
        // wo.request tetap sama persis di schema mon_work_orders yang baru.
        $need = 0.0;
        if ($this->hasCpo()) {
            $need = (float) DB::table('mon_work_orders as wo')
                ->join('mon_boms as b', function ($join) {
                    $join->on('wo.product_code', '=', 'b.barang_jadi')
                        ->on('wo.barang_code', '=', 'b.barang_code');
                })
                ->whereIn('b.uraian', $this->filterUraianList())
                ->where('wo.satuan_code', 'KGM')
                ->sum('wo.request') ?? 0;
        }

        return [
            'need'     => $need,
            'order'    => $order,
            'received' => $received,
            'out_wip'  => $outWip,
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

    public function summary(): array
    {
        $contract = (float) ($this->orderQuery()->sum('qty_ord') ?? 0);
        $shipment = (float) ($this->shipmentQuery()->sum('jumlah_barang') ?? 0);
        $balance  = $contract - $shipment;

        return [
            'contract_qty'    => $contract,
            'shipment_qty'    => $shipment,
            'balance_qty'     => $balance,
            'achievement_pct' => $contract > 0 ? round($shipment / $contract * 100, 1) : 0,
            'shortage_pct'    => $contract > 0 ? round($balance / $contract * 100, 1) : 0,
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

    public function materialAchievement(): Collection
    {
        $rows = $this->rekonQuery()
            ->select('barang_name')
            ->selectRaw('SUM(jumlah_order) as jumlah_order')
            ->selectRaw('SUM(jumlah_doc) as jumlah_doc')
            ->selectRaw('SUM(out_prod) as out_prod')
            ->selectRaw('SUM(saldo_gudang) as saldo_gudang')
            ->groupBy('barang_name')
            ->orderBy('barang_name')
            ->get();

        return $rows->map(function ($r) {
            $order = (float) $r->jumlah_order;
            $pct = fn($v) => $order > 0 ? round(max(0, (float) $v) / $order * 100) : 0;

            return (object) [
                'barang_name'  => $r->barang_name,
                'order_pct'    => $order > 0 ? 100 : 0,
                'received_pct' => $pct($r->jumlah_doc),
                'out_prod_pct' => $pct($r->out_prod),
                'stock_pct'    => $pct($r->saldo_gudang),
            ];
        });
    }

    public function productionPipeline(): array
    {
        $contract = (float) ($this->orderQuery()->sum('qty_ord') ?? 0);

        $cuttingDeptQty = $this->prodLineSumByDepartment('Cutting');
        // $cutting        = $contract - $cuttingDeptQty;
        $cutting        = $cuttingDeptQty;

        $sewing    = $this->prodLineSumByDestination('Sewing');
        $packing   = $this->prodLineSumByDestination('Packing');
        $warehouse = $this->prodLineSumByDestination('Warehouse');

        $shippedActual = (float) ($this->shipmentQuery()->sum('jumlah_barang') ?? 0);
        // $shipment      = $warehouse - $shippedActual;
        $shipment      = $shippedActual;

        $departments = collect([
            (object) ['department_id' => 'Cutting', 'jumlah' => $cutting],
            (object) ['department_id' => 'Sewing', 'jumlah' => $sewing],
            (object) ['department_id' => 'Packing', 'jumlah' => $packing],
            (object) ['department_id' => 'Warehouse', 'jumlah' => $warehouse],
        ]);

        $totalLoss = $contract - $shipment;

        return [
            'contract'    => $contract,
            'departments' => $departments,
            'shipment'    => $shipment,
            'total_loss'  => $totalLoss,
            'loss_pct'    => $contract > 0 ? round($totalLoss / $contract * 100, 2) : 0,
        ];
    }

    public function productionResultByMaterial(): Collection
    {
        if (!$this->hasCpo()) {
            return collect();
        }

        $query = DB::table('mon_prod_lines')
            ->whereIn('department_id', ['Cutting', 'Sewing', 'Packing'])
            ->select('department_id', 'barang_code', 'barang_name')
            ->selectRaw('SUM(jumlah) as jumlah')
            ->groupBy('department_id', 'barang_code', 'barang_name')
            ->orderBy('barang_name');

        $this->scopeByCodeProd($query, $this->filterUraianList());

        return $query->get();
    }

    private function prodLineSumByDepartment(string $departmentId): float
    {
        if (!$this->hasCpo()) {
            return 0;
        }

        $query = DB::table('mon_prod_lines')
            ->where('department_id', $departmentId)
            ->selectRaw('SUM(jumlah) as jumlah');

        $this->scopeByCodeProd($query, $this->filterUraianList());

        return (float) ($query->value('jumlah') ?? 0);
    }

    private function prodLineSumByDestination(string $keyword): float
    {
        if (!$this->hasCpo()) {
            return 0;
        }

        $query = DB::table('mon_prod_lines')
            ->where('destination', 'like', "%{$keyword}%")
            ->selectRaw('SUM(jumlah) as jumlah');

        $this->scopeByCodeProd($query, $this->filterUraianList());

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

    public function materialPurchasePivot(int $limit = 15): Collection
    {
        $query = DB::table('mon_purchase_orders as po')
            ->select('po.barang_code', 'po.barang_name', 'po.valas', 'po.satuan_order')
            ->selectRaw('SUM(po.jumlah_order) as jumlah_order')
            ->selectRaw('SUM(po.jumlah_doc) as jumlah_diterima')
            ->selectRaw('SUM(po.jumlah_order) - SUM(po.jumlah_doc) as sisa')
            ->selectRaw('SUM(po.harga_total) as harga_total')
            ->groupBy('po.barang_code', 'po.barang_name', 'po.valas', 'po.satuan_order')
            ->orderByDesc(DB::raw('SUM(po.jumlah_order)'))
            ->limit($limit);

        if ($this->hasCpo()) {
            $query->whereIn('po.uraian', $this->filterUraianList());
        }

        return $query->get();
    }

    private function baseWorkOrderQuery()
    {
        $poAgg = DB::table('mon_purchase_orders')
            ->select('uraian', 'barang_code')
            ->selectRaw('SUM(jumlah_order) as ordered_qty')
            ->groupBy('uraian', 'barang_code');

        $query = DB::table('mon_boms as b')
            ->leftJoinSub($poAgg, 'po_agg', function ($join) {
                $join->on('po_agg.uraian', '=', 'b.uraian')
                    ->on('po_agg.barang_code', '=', 'b.barang_code');
            })
            ->whereRaw('COALESCE(po_agg.ordered_qty, 0) = 0')
            ->select('b.uraian', 'b.barang_code', 'b.barang_name', 'b.departemen', 'b.komponen', 'b.barang_jadi')
            ->selectRaw('SUM(b.cons) as total_cons')
            ->groupBy('b.uraian', 'b.barang_code', 'b.barang_name', 'b.departemen', 'b.komponen', 'b.barang_jadi');

        if ($this->hasCpo()) {
            $query->whereIn('b.uraian', $this->filterUraianList());
        }

        return $query;
    }

    public function workOrderPivot(int $limit = 50): Collection
    {
        return $this->baseWorkOrderQuery()->orderBy('b.barang_code')->limit($limit)->get();
    }

    public function workOrderCount(): int
    {
        return $this->baseWorkOrderQuery()->count();
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

    public function pipelineLossSteps(): Collection
    {
        $pipeline = $this->productionPipeline();

        $stages = collect();
        $stages->push((object) ['label' => 'Contract', 'qty' => $pipeline['contract']]);
        foreach ($pipeline['departments'] as $dept) {
            $stages->push((object) [
                'label' => $dept->department_id ?? '-',
                'qty'   => (float) $dept->jumlah,
            ]);
        }
        $stages->push((object) ['label' => 'Shipment', 'qty' => $pipeline['shipment']]);

        $steps = collect();
        for ($i = 0; $i < $stages->count() - 1; $i++) {
            $from = $stages[$i];
            $to = $stages[$i + 1];
            $loss = $from->qty - $to->qty;

            $steps->push((object) [
                'process'  => "{$from->label} → {$to->label}",
                'input'    => $from->qty,
                'output'   => $to->qty,
                'loss_pcs' => $loss,
                'loss_pct' => $from->qty > 0 ? round($loss / $from->qty * 100, 2) : null,
            ]);
        }

        return $steps;
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
}
