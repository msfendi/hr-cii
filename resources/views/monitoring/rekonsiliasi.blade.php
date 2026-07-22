<!DOCTYPE html>
<html lang="id">
@include('layout.header')
@include('sweetalert::alert')
<body id="page-top" class="rekon-page">

<div id="wrapper">

    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

            @include('layout.navbar')

            <div class="container-fluid" id="rekon-app"
                 data-filters='@json($filters)'
                 data-filter-options='@json($filterOptions)'
                 data-endpoint="{{ route('monitoring.rekonsiliasi.data') }}"
                 data-sync-rekon-url="{{ route('monitoring.rekonsiliasi.sync') }}"
                 data-sync-prodline-url="{{ route('monitoring.rekonsiliasi.sync-prod-line') }}"
                 data-sync-shipment-url="{{ route('monitoring.rekonsiliasi.sync-shipment') }}"
                 data-sync-workorder-url="{{ route('monitoring.rekonsiliasi.sync-work-order') }}">

                {{-- ================= HEADER BAR (mirip gambar) ================= --}}
                <div class="rekon-hero shadow mb-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="rekon-hero-title">
                            <i class="fas fa-balance-scale mr-2"></i> DASHBOARD RECONCILIATION
                        </div>
                        <div class="d-flex align-items-center flex-wrap" style="gap:16px">
                            <div class="rekon-search input-group input-group-sm">
                                <select id="f-buyer" class="form-control select2-filter" style="min-width:150px" data-placeholder="Cari Buyer...">
                                    <option value=""></option>
                                </select>
                                <select id="f-style" class="form-control select2-filter" style="min-width:150px" data-placeholder="Cari Style...">
                                    <option value=""></option>
                                </select>
                                <select id="f-cpo" class="form-control select2-filter" style="min-width:220px" data-placeholder="Cari CPO...">
                                    <option value=""></option>
                                    @foreach($cpoOptions as $v)
                                        <option value="{{ $v }}" @selected(($filters['uraian'] ?? null) === $v)>{{ $v }}</option>
                                    @endforeach
                                </select>
                                <!-- <div class="input-group-append">
                                    <button id="btn-filter-cpo" type="button" class="btn btn-light" title="Tampilkan data untuk Buyer / Style / CPO terpilih (boleh salah satu saja)">
                                        <i class="fas fa-filter fa-sm"></i> Filter
                                    </button>
                                </div> -->
                            </div>
                            <div class="text-white small">
                                <div class="text-uppercase" style="opacity:.75">Last Updated</div>
                                <div class="font-weight-bold" id="rekon-last-updated">--</div>
                            </div>
                            <div class="d-flex" style="gap:6px">
                                @canRoute('monitoring.rekonsiliasi.sync')
                                    <button id="btn-sync-rekon" type="button" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-sync-alt fa-sm"></i> Sync Rekonsiliasi
                                    </button>
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-prod-line')
                                    <button id="btn-sync-prodline" type="button" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-sync-alt fa-sm"></i> Sync Production Line
                                    </button>
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-shipment')
                                    <button id="btn-sync-shipment" type="button" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-sync-alt fa-sm"></i> Sync Shipment
                                    </button>
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-work-order')
                                    <button id="btn-sync-workorder" type="button" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-sync-alt fa-sm"></i> Sync Work Order
                                    </button>
                                @endcanRoute
                            </div>
                        </div>
                    </div>
                    <div class="rekon-hero-sub mt-2">
                        CPO : <span id="hdr-cpo">-</span>
                        &nbsp;|&nbsp; BRAND <span id="hdr-brand">-</span>
                        &nbsp;|&nbsp; STYLE <span id="hdr-style">-</span>
                        <span id="hdr-cpo-count-wrap" style="display:none">
                            &nbsp;|&nbsp; <span class="badge badge-light" id="hdr-cpo-count"></span>
                        </span>
                    </div>
                </div>

                {{-- ================= EMPTY STATE: tampil sebelum ada filter dipilih ================= --}}
                <div id="rekon-empty-notice" class="card shadow mb-4" style="display:none">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-filter fa-2x mb-3 d-block"></i>
                        <div class="font-weight-bold mb-1">Belum ada filter yang dipilih</div>
                        <div class="small">
                            Pilih salah satu atau kombinasi dari <strong>Buyer</strong>, <strong>Style</strong>,
                            atau <strong>CPO</strong> pada kolom di atas untuk menampilkan data dashboard.
                            Kalau cuma Buyer dan/atau Style yang dipilih (tanpa CPO spesifik), data dari
                            semua CPO yang cocok akan digabung. Data tidak dimuat otomatis saat halaman
                            dibuka karena cukup berat kalau ditarik untuk semua CPO sekaligus.
                        </div>
                    </div>
                </div>

                {{-- ================= WIDGET DATA: disembunyikan sampai CPO dipilih ================= --}}
                <div id="rekon-widgets" style="display:none">

                {{-- ================= KPI CARDS ================= --}}
                <div class="row">
                    <div class="col-md mb-4">
                        <div class="card shadow h-100 py-2 rekon-kpi">
                            <div class="card-body text-center">
                                <i class="fas fa-file-contract fa-lg text-primary mb-2"></i>
                                <div class="text-xs font-weight-bold text-gray-600 text-uppercase mb-1">Contract Qty (Pcs)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-contract">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md mb-4">
                        <div class="card shadow h-100 py-2 rekon-kpi">
                            <div class="card-body text-center">
                                <i class="fas fa-truck fa-lg text-info mb-2"></i>
                                <div class="text-xs font-weight-bold text-gray-600 text-uppercase mb-1">Shipment Qty (Pcs)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-shipment">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md mb-4">
                        <div class="card shadow h-100 py-2 rekon-kpi">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-lg text-success mb-2"></i>
                                <div class="text-xs font-weight-bold text-gray-600 text-uppercase mb-1">Achievement</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-achievement">0%</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md mb-4">
                        <div class="card shadow h-100 py-2 rekon-kpi">
                            <div class="card-body text-center">
                                <i class="fas fa-balance-scale-right fa-lg text-warning mb-2"></i>
                                <div class="text-xs font-weight-bold text-gray-600 text-uppercase mb-1">Balance Qty (Pcs)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-balance">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md mb-4">
                        <div class="card shadow h-100 py-2 rekon-kpi">
                            <div class="card-body text-center">
                                <i class="fas fa-database fa-lg text-danger mb-2"></i>
                                <div class="text-xs font-weight-bold text-gray-600 text-uppercase mb-1">Ship Shortage</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-shortage">0%</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md mb-4">
                        <div class="card shadow h-100 py-2 rekon-kpi">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-alt fa-lg text-secondary mb-2"></i>
                                <div class="text-xs font-weight-bold text-gray-600 text-uppercase mb-1">Shipment Date</div>
                                <div class="small font-weight-bold text-gray-800" id="kpi-shipdates">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= FABRIC QTY / FABRIC USAGE / FABRIC USAGE % : 1 baris, 3 card terpisah ================= --}}
                <div class="row">
                    {{-- FABRIC QTY KGM (mon_rekonsiliasis + mon_work_orders) --}}
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100 rekon-fabric-card">
                            <div class="card-header py-2">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-shopping-basket mr-1"></i> FABRIC QTY (KGM)</h6>
                            </div>
                            <div class="card-body">
                                <div class="row rekon-fabric-boxes">
                                    <div class="col-6 mb-2">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Need</div>
                                            <div class="rekon-fabric-value" id="fabric-need">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Order</div>
                                            <div class="rekon-fabric-value" id="fabric-order">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Received</div>
                                            <div class="rekon-fabric-value" id="fabric-received">0</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Out WIP</div>
                                            <div class="rekon-fabric-value" id="fabric-out-wip">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- FABRIC USAGE (mon_rekonsiliasis.out_req - mon_prod_lines scrap) --}}
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100 rekon-fabric-card">
                            <div class="card-header py-2">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-tshirt mr-1"></i> FABRIC USAGE</h6>
                            </div>
                            <div class="card-body">
                                <div class="rekon-usage-box">
                                    <span class="rekon-usage-box-title">Qty Usage (KGM)</span>
                                    <div class="rekon-usage-row">
                                        <div class="rekon-usage-row-label">Use For GMT</div>
                                        <div class="rekon-usage-row-value" id="usage-for-gmt">0</div>
                                    </div>
                                    <div class="rekon-usage-row">
                                        <div class="rekon-usage-row-label">Scrap Qty</div>
                                        <div class="rekon-usage-row-value" id="usage-scrap-qty">0</div>
                                    </div>
                                </div>
                                <div class="rekon-usage-consumption">
                                    <span>Consumption :</span>
                                    <span class="rekon-usage-consumption-value" id="usage-consumption">0</span>
                                    <span>Kgm</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- FABRIC USAGE PERCENTAGE --}}
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100 rekon-fabric-card">
                            <div class="card-header py-2">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-chart-pie mr-1"></i> FABRIC USAGE PERCENTAGE</h6>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <div class="rekon-usage-donut-area">
                                    <canvas id="chart-fabric-usage"></canvas>
                                </div>
                                <div class="rekon-usage-legend">
                                    <div><span class="rekon-usage-dot" style="background:#1f6f8b"></span>Usage</div>
                                    <div><span class="rekon-usage-dot" style="background:#e07b39"></span>Scrap</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= MATERIAL ACHIEVEMENT (mon_rekonsiliasis) ================= --}}
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">MATERIAL ACHIEVEMENT</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area" style="height:380px">
                            <canvas id="chart-material-achievement"></canvas>
                        </div>
                    </div>
                </div>

                {{-- ================= PRODUCTION RESULT (mon_prod_lines + mon_rekonsiliasis) ================= --}}
                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">PRODUCTION RESULT (PCS)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area" style="height:260px">
                                    <canvas id="chart-production-result"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <div class="rekon-pipeline" id="rekon-pipeline">
                                    <!-- diisi JS: Contract -> per-department (mon_prod_lines) -> Warehouse -> Shipment -->
                                </div>
                                <div class="text-muted small mt-2 mb-3">
                                    Cutting = Contract &minus; <code>mon_prod_lines.jumlah</code> (department_id Cutting).
                                    Sewing/Packing/Warehouse diambil dari <code>mon_prod_lines.jumlah</code> per kolom
                                    <code>destination</code>. Shipment = Warehouse &minus; <code>mon_shipments.jumlah_barang</code>.
                                    Semua tahap di-scope ke <code>code_prod</code> yang mengandung kode CPO (5 digit) terpilih.
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm w-100" id="table-loss-steps">
                                        <thead>
                                            <tr>
                                                <th>Process</th>
                                                <th class="right">Input</th>
                                                <th class="right">Output</th>
                                                <th class="right">Loss (Pcs)</th>
                                                <th class="right">Loss (%)</th>
                                            </tr>
                                        </thead>
                                        <tbody><!-- data akan diisi oleh JS --></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= MATERIAL PURCHASE (mon_purchase_orders) & TOP 3 EXCESS (mon_rekonsiliasis) ================= --}}
                <div class="row">
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">PIVOT MATERIAL PURCHASE</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive mon-table-box">
                                    <table class="table table-bordered table-sm w-100" id="table-material-purchase">
                                        <thead>
                                            <tr>
                                                <th>Barang Code</th>
                                                <th>Nama Barang</th>
                                                <th>Satuan</th>
                                                <th>Valas</th>
                                                <th class="right">Jumlah Order</th>
                                                <th class="right">Jumlah Diterima</th>
                                                <th class="right">Sisa</th>
                                                <th class="right">Harga Total</th>
                                            </tr>
                                        </thead>
                                        <tbody><!-- data akan diisi oleh JS --></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-layer-group mr-1"></i> TOP 3 MATERIAL EXCESS (BY STOCK)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area" style="height:260px">
                                    <canvas id="chart-top-excess"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= WORK ORDER / BOM BELUM DI-PO-KAN (mon_boms + mon_purchase_orders) ================= --}}
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">PIVOT WORK ORDER &mdash; Item BOM Belum Diorder</h6>
                        <span class="badge badge-warning" id="badge-workorder-count">0 item</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive mon-table-box">
                            <table class="table table-bordered table-sm w-100" id="table-workorder">
                                <thead>
                                    <tr>
                                        <th>Barang Code</th>
                                        <th>Nama Barang</th>
                                        <th>Departemen</th>
                                        <th>Komponen</th>
                                        <th>Barang Jadi</th>
                                        <th class="right">Total Consumption</th>
                                    </tr>
                                </thead>
                                <tbody><!-- data akan diisi oleh JS --></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ================= SHIPMENT (mon_shipments, sync get_pengeluaran_bc) ================= --}}
                <div class="row">
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">TREN SHIPMENT (PCS / TANGGAL BUKTI)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area" style="height:220px">
                                    <canvas id="chart-shipment-trend"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">SHIPMENT BY KATEGORI BARANG</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area" style="height:220px">
                                    <canvas id="chart-shipment-category"></canvas>
                                </div>
                            </div>
                        </div>
                    </div> -->
                    <div class="col-lg-7 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">PIVOT SHIPMENT &mdash; Dokumen Pengeluaran BC</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive mon-table-box">
                                    <table class="table table-bordered table-sm w-100" id="table-shipment-detail">
                                        <thead>
                                            <tr>
                                                <th>No. Bukti</th>
                                                <th>Tgl Bukti</th>
                                                <th>Jenis Doc</th>
                                                <th>Jenis PS</th>
                                                <th>Barang</th>
                                                <th>Satuan</th>
                                                <th class="right">Jumlah Doc</th>
                                                <th class="right">Jumlah Barang</th>
                                            </tr>
                                        </thead>
                                        <tbody><!-- data akan diisi oleh JS --></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= DETAIL TABLE (mon_rekonsiliasis) ================= --}}
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Detail Rekonsiliasi per Material</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm w-100" id="table-rekon-detail">
                                <thead>
                                    <tr>
                                        <th>No. PO</th>
                                        <th>Jenis PO</th>
                                        <th>Tgl PO</th>
                                        <th>Tgl Pengiriman</th>
                                        <th>Supplier</th>
                                        <th>Barang Code</th>
                                        <th>Nama Barang</th>
                                        <th>Satuan</th>
                                        <th class="right">Jumlah Order</th>
                                        <th class="right">Jumlah Diterima</th>
                                        <th class="right">Out Req (WIP)</th>
                                        <th class="right">Out Prod</th>
                                        <th class="right">Sisa</th>
                                        <th class="right">Saldo WIP</th>
                                        <th class="right">Out Doc (Shipment)</th>
                                        <th class="right">Saldo Gudang</th>
                                        <th class="right">Harga Total</th>
                                    </tr>
                                </thead>
                                <tbody><!-- data akan diisi oleh JS --></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                </div>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

@include('layout.footer')

    </div>
    <!-- End of Content Wrapper -->

</div>
<!-- End of Page Wrapper -->

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<!-- Bootstrap core JavaScript -->
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/js/sb-admin-2.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<!-- Select2 JS & CSS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function(){
    const app = document.getElementById('rekon-app');
    const endpoint = app.dataset.endpoint;
    const syncRekonUrl = app.dataset.syncRekonUrl;
    const syncProdlineUrl = app.dataset.syncProdlineUrl;
    const syncShipmentUrl = app.dataset.syncShipmentUrl;
    const syncWorkOrderUrl = app.dataset.syncWorkorderUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const fBuyer = document.getElementById('f-buyer');
    const fStyle = document.getElementById('f-style');
    const fCpo = document.getElementById('f-cpo');
    const emptyNotice = document.getElementById('rekon-empty-notice');
    const widgets = document.getElementById('rekon-widgets');
    const btnFilterCpo = document.getElementById('btn-filter-cpo');
    const btnSyncRekon = document.getElementById('btn-sync-rekon');
    const btnSyncProdline = document.getElementById('btn-sync-prodline');
    const btnSyncShipment = document.getElementById('btn-sync-shipment');
    const btnSyncWorkOrder = document.getElementById('btn-sync-workorder');

    // Kombinasi Buyer (brand) / Style / CPO (uraian) dari mon_orders, dipakai
    // untuk cascading select2 di frontend (pilih Buyer -> Style & CPO
    // menyempit ke Buyer itu saja; pilih Buyer + Style -> CPO menyempit ke
    // kombinasi keduanya). Tapi Buyer dan Style JUGA bisa langsung dipakai
    // sebagai filter pencarian sendiri (lihat refresh()) -- tidak wajib
    // sampai memilih 1 CPO spesifik.
    let filterOptions = [];
    try { filterOptions = JSON.parse(app.dataset.filterOptions || '[]'); } catch (e) { filterOptions = []; }

    $('.select2-filter').each(function () {
        $(this).select2({ width: '100%', placeholder: $(this).data('placeholder') || '', allowClear: true });
    });

    const fmtNum = (v) => new Intl.NumberFormat('id-ID').format(Number(v || 0));

    function uniqueSorted(values) {
        return [...new Set(values.filter(v => v !== null && v !== undefined && v !== ''))].sort();
    }

    /** Isi ulang <option> sebuah select2, pertahankan value lama kalau masih valid. */
    function populateSelect($el, values) {
        const current = $el.val();
        $el.empty().append('<option value=""></option>');
        values.forEach(v => $el.append(new Option(v, v, false, false)));
        $el.val(values.includes(current) ? current : '').trigger('change');
    }

    /**
     * Cascading: Style mengikuti Buyer terpilih; CPO mengikuti Buyer + Style
     * terpilih. Dipanggil ulang tiap kali Buyer/Style berganti.
     */
    function refreshCascade() {
        const buyer = fBuyer.value;
        const style = fStyle.value;

        const styleRows = filterOptions.filter(r => !buyer || r.brand === buyer);
        populateSelect($(fStyle), uniqueSorted(styleRows.map(r => r.style)));

        const cpoRows = filterOptions.filter(r =>
            (!buyer || r.brand === buyer) && (!style || r.style === style));
        populateSelect($(fCpo), uniqueSorted(cpoRows.map(r => r.uraian)));
    }

    (function initCascadeFilters() {
        populateSelect($(fBuyer), uniqueSorted(filterOptions.map(r => r.brand)));

        const initialUraian = app.dataset.filters ? (JSON.parse(app.dataset.filters).uraian || null) : null;
        if (initialUraian) {
            const match = filterOptions.find(r => r.uraian === initialUraian);
            if (match) {
                $(fBuyer).val(match.brand).trigger('change');
            }
        }

        refreshCascade();

        if (initialUraian) {
            $(fCpo).val(initialUraian).trigger('change');
        }
    })();

    let chartMaterial, chartProduction, chartExcess, chartShipment, chartShipCategory, chartFabricUsage, dtDetail, dtMaterialPurchase, dtWorkOrder, dtShipment;

    function buildUrl(base, params) {
        const url = new URL(base, window.location.origin);
        Object.entries(params).forEach(([k, v]) => { if (v) url.searchParams.set(k, v); });
        return url.toString();
    }

    function renderHeader(header) {
        header = header || {};
        document.getElementById('hdr-cpo').textContent = header.cpo || '-';
        document.getElementById('hdr-brand').textContent = header.brand || '-';
        document.getElementById('hdr-style').textContent = header.style || '-';

        // Kalau search Buyer/Style match lebih dari 1 CPO, tampilkan badge
        // penanda supaya jelas kalau angka di dashboard adalah gabungan.
        const countWrap = document.getElementById('hdr-cpo-count-wrap');
        const countEl = document.getElementById('hdr-cpo-count');
        if (header.cpoCount && header.cpoCount > 1) {
            countEl.textContent = `Gabungan ${header.cpoCount} CPO`;
            countWrap.style.display = '';
        } else {
            countWrap.style.display = 'none';
        }

        document.getElementById('rekon-last-updated').textContent = new Date().toLocaleString('id-ID', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    function renderKpi(summary, shipmentDates) {
        document.getElementById('kpi-contract').textContent = fmtNum(summary.contract_qty);
        document.getElementById('kpi-shipment').textContent = fmtNum(summary.shipment_qty);
        document.getElementById('kpi-achievement').textContent = summary.achievement_pct + '%';
        document.getElementById('kpi-balance').textContent = fmtNum(summary.balance_qty);
        document.getElementById('kpi-shortage').textContent = summary.shortage_pct + '%';
        document.getElementById('kpi-shipdates').textContent = (shipmentDates && shipmentDates.length)
            ? shipmentDates.join(', ')
            : '-';
    }

    /** "FABRIC QTY (KGM)": Need (mon_work_orders) / Order / Received / Out WIP (mon_rekonsiliasis). */
    function renderFabricQty(fabricQty) {
        fabricQty = fabricQty || {};
        document.getElementById('fabric-need').textContent = fmtNum(fabricQty.need);
        document.getElementById('fabric-order').textContent = fmtNum(fabricQty.order);
        document.getElementById('fabric-received').textContent = fmtNum(fabricQty.received);
        document.getElementById('fabric-out-wip').textContent = fmtNum(fabricQty.out_wip);
    }

    /**
     * "FABRIC USAGE": Use For GMT vs Scrap Qty (KGM).
     *  - Use For GMT = mon_rekonsiliasis.out_req - mon_prod_lines.jumlah (barang_code = '01SCRP00001')
     *  - Scrap Qty   = mon_prod_lines.jumlah (barang_code = '01SCRP00001')
     *  - Donut menampilkan proporsi Use For GMT vs Scrap terhadap total out_req.
     */
    function renderFabricUsage(fabricUsage) {
        fabricUsage = fabricUsage || {};
        document.getElementById('usage-for-gmt').textContent = fmtNum(fabricUsage.use_for_gmt);
        document.getElementById('usage-scrap-qty').textContent = fmtNum(fabricUsage.scrap_qty);
        document.getElementById('usage-consumption').textContent = fmtNum(fabricUsage.consumption);

        const ctx = document.getElementById('chart-fabric-usage');
        if (typeof Chart === 'undefined' || !ctx) return;

        const usagePct = Number(fabricUsage.usage_pct || 0);
        const scrapPct = Number(fabricUsage.scrap_pct || 0);

        if (chartFabricUsage) chartFabricUsage.destroy();
        chartFabricUsage = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Usage', 'Scrap'],
                datasets: [{
                    data: [usagePct, scrapPct],
                    backgroundColor: ['#1f6f8b', '#e07b39'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => `${c.label}: ${c.parsed}%` } },
                },
            },
            plugins: [{
                id: 'fabricUsageCenterLabels',
                afterDraw(chart) {
                    const { ctx: c } = chart;
                    const meta = chart.getDatasetMeta(0);
                    if (!meta || !meta.data.length) return;
                    c.save();
                    c.font = 'bold 14px Arial';
                    c.fillStyle = '#fff';
                    c.textAlign = 'center';
                    c.textBaseline = 'middle';
                    chart.data.datasets[0].data.forEach((value, i) => {
                        if (!value) return;
                        const pos = meta.data[i].tooltipPosition();
                        c.fillText(`${value}%`, pos.x, pos.y);
                    });
                    c.restore();
                },
            }],
        });
    }

    function renderMaterialAchievement(rows) {
        const ctx = document.getElementById('chart-material-achievement');
        if (typeof Chart === 'undefined' || !ctx) return;

        const labels = rows.map(r => r.barang_name ?? '-');
        const mk = (key, color, label) => ({ label, data: rows.map(r => r[key]), backgroundColor: color });

        if (chartMaterial) chartMaterial.destroy();
        chartMaterial = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    mk('order_pct', '#4e73df', 'ORDER%'),
                    mk('received_pct', '#f6a533', 'RECEIVED%'),
                    mk('out_prod_pct', '#1cc88a', 'OUT PROD%'),
                    mk('stock_pct', '#36b9cc', 'STOCK%'),
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: v => v + '%' } } },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y}%` } },
                },
            },
        });
    }

    // Warna berbeda per departemen untuk chart PRODUCTION RESULT (PCS)
    const DEPT_COLORS = { Cutting: '#4e73df', Sewing: '#1cc88a', Packing: '#f6a533' };
    const DEPT_ORDER = ['Cutting', 'Sewing', 'Packing'];

    /**
     * PRODUCTION RESULT (PCS): breakdown per department (Cutting/Sewing/Packing)
     * dan per barang_code -- ditampilkan pakai barang_name, tiap departemen
     * punya warna sendiri (grouped bar chart).
     */
    function renderProductionResult(pipeline, materialRows, lossSteps) {
        materialRows = materialRows || [];
        lossSteps = lossSteps || [];

        const materialLabels = [...new Set(materialRows.map(r => r.barang_name ?? '-'))].sort();
        const datasets = DEPT_ORDER.map(dept => ({
            label: dept,
            backgroundColor: DEPT_COLORS[dept],
            data: materialLabels.map(name => {
                const row = materialRows.find(r => r.department_id === dept && (r.barang_name ?? '-') === name);
                return row ? Number(row.jumlah || 0) : 0;
            }),
        }));

        const ctx = document.getElementById('chart-production-result');
        if (typeof Chart !== 'undefined' && ctx) {
            if (chartProduction) chartProduction.destroy();
            chartProduction = new Chart(ctx, {
                type: 'bar',
                data: { labels: materialLabels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${fmtNum(c.parsed.y)}` } },
                    },
                },
            });
        }

        // Flow diagram: Contract -> tiap department (mon_prod_lines) -> Warehouse -> Shipment -> Total Process Loss.
        // Tiap kartu tahapan menampilkan Output Qty + Output % (relatif ke tahap
        // sebelumnya) dan Loss Qty + Loss % (selisih ke tahap sebelumnya), sesuai
        // hasil pipelineLossSteps() (input/output/loss_pcs/loss_pct per pasangan
        // tahap berurutan).
        const stages = [
            { label: 'Contract', value: pipeline.contract, theme: 'neutral' },
            ...pipeline.departments.map(d => ({
                label: d.department_id ?? '-',
                value: d.jumlah,
                theme: 'green',
            })),
            { label: 'Shipment', value: pipeline.shipment, theme: 'navy' },
        ];

        const steps = lossSteps;

        const boxes = stages.map((s, i) => {
            // Tahap pertama (Contract) tidak punya "loss masuk" karena tidak ada
            // tahap sebelumnya -- tampilkan sebagai kartu referensi/baseline.
            const step = i > 0 ? steps[i - 1] : null;

            const outputPct = step && step.input > 0
                ? (100 - Number(step.loss_pct ?? 0))
                : (i === 0 ? 100 : null);

            const lossValue = step ? Number(step.loss_pcs) : null;
            const lossPct = step ? step.loss_pct : null;
            const lossIsGain = lossValue !== null && lossValue < 0;

            const lossRow = step ? `
                <hr class="rekon-pipe-divider">
                <div class="rekon-pipe-loss-value ${lossIsGain ? 'is-gain' : ''}">${lossValue < 0 ? '+' : ''}${fmtNum(Math.abs(lossValue))}</div>
                <div class="rekon-pipe-loss-pct ${lossIsGain ? 'is-gain' : ''}">${lossPct === null ? '-' : (lossValue < 0 ? '+' : '') + Math.abs(lossPct) + '%'}</div>
            ` : `
                <hr class="rekon-pipe-divider">
                <div class="rekon-pipe-loss-value">-</div>
                <div class="rekon-pipe-loss-pct">&nbsp;</div>
            `;

            return `
                <div class="rekon-pipe-box theme-${s.theme}">
                    <div class="rekon-pipe-header">${s.label}</div>
                    <div class="rekon-pipe-body">
                        <div class="rekon-pipe-output">${fmtNum(s.value)}</div>
                        <div class="rekon-pipe-output-pct">${outputPct === null ? '-' : fmtNum(outputPct) + '%'}</div>
                        ${lossRow}
                    </div>
                </div>
                <div class="rekon-pipe-arrow"><i class="fas fa-arrow-right"></i></div>
            `;
        }).join('');

        const lossBox = `
            <div class="rekon-pipe-box theme-loss rekon-pipe-total">
                <div class="rekon-pipe-header">Total Process Loss</div>
                <div class="rekon-pipe-body">
                    <div class="rekon-pipe-output">${fmtNum(pipeline.total_loss)}</div>
                    <div class="rekon-pipe-output-pct">${pipeline.loss_pct}%</div>
                    <div class="text-uppercase" style="font-size:.65rem;">PCS</div>
                </div>
            </div>
        `;

        const legend = `
            <div class="rekon-pipe-legend">
                <span><i class="rekon-pipe-dot dot-output"></i>Output Qty (PCS)</span>
                <span><i class="rekon-pipe-dot dot-loss"></i>Loss Qty (PCS)</span>
            </div>
        `;

        document.getElementById('rekon-pipeline').innerHTML =
            `<div class="d-flex flex-wrap align-items-stretch">${boxes}${lossBox}</div>${legend}`;
    }

    function renderLossSteps(rows) {
        const tbody = document.querySelector('#table-loss-steps tbody');
        tbody.innerHTML = rows.map(r => `
            <tr>
                <td>${r.process}</td>
                <td class="right">${fmtNum(r.input)}</td>
                <td class="right">${fmtNum(r.output)}</td>
                <td class="right ${Number(r.loss_pcs) > 0 ? 'text-danger' : 'text-success'}">${fmtNum(r.loss_pcs)}</td>
                <td class="right">${r.loss_pct === null ? '-' : r.loss_pct + '%'}</td>
            </tr>
        `).join('');
    }

    function renderMaterialPurchase(rows) {
        const tbody = document.querySelector('#table-material-purchase tbody');
        tbody.innerHTML = rows.map(r => `
            <tr>
                <td>${r.barang_code ?? '-'}</td>
                <td>${r.barang_name ?? '-'}</td>
                <td>${r.satuan_order ?? '-'}</td>
                <td>${r.valas ?? '-'}</td>
                <td class="right">${fmtNum(r.jumlah_order)}</td>
                <td class="right">${fmtNum(r.jumlah_diterima)}</td>
                <td class="right">${fmtNum(r.sisa)}</td>
                <td class="right">${fmtNum(r.harga_total)}</td>
            </tr>
        `).join('');

        if (dtMaterialPurchase) dtMaterialPurchase.destroy();
        dtMaterialPurchase = $('#table-material-purchase').DataTable({ pageLength: 10, order: [], autoWidth: false, width: '100%' });
    }

    function renderTopExcess(rows) {
        const ctx = document.getElementById('chart-top-excess');
        if (typeof Chart === 'undefined' || !ctx) return;

        const labels = rows.map(r => r.barang_name ?? '-');
        const data = rows.map(r => Number(r.saldo_gudang || 0));

        if (chartExcess) chartExcess.destroy();
        chartExcess = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ data, backgroundColor: ['#1cc88a', '#f6a533', '#4e73df'] }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
        });
    }

    function renderWorkOrder(rows, count) {
        document.getElementById('badge-workorder-count').textContent = fmtNum(count) + ' item';

        const tbody = document.querySelector('#table-workorder tbody');
        tbody.innerHTML = rows.map(r => `
            <tr>
                <td>${r.barang_code ?? '-'}</td>
                <td>${r.barang_name ?? '-'}</td>
                <td>${r.departemen ?? '-'}</td>
                <td>${r.komponen ?? '-'}</td>
                <td>${r.barang_jadi ?? '-'}</td>
                <td class="right">${fmtNum(r.total_cons)}</td>
            </tr>
        `).join('');

        if (dtWorkOrder) dtWorkOrder.destroy();
        dtWorkOrder = $('#table-workorder').DataTable({ pageLength: 10, order: [], autoWidth: false, width: '100%' });
    }

    function renderShipmentCategory(rows) {
        const ctx = document.getElementById('chart-shipment-category');
        if (typeof Chart === 'undefined' || !ctx) return;

        const labels = rows.map(r => r.barang_category ?? '-');
        const data = rows.map(r => Number(r.jumlah_barang || 0));

        if (chartShipCategory) chartShipCategory.destroy();
        chartShipCategory = new Chart(ctx, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'] }] },
            options: { responsive: true, maintainAspectRatio: false },
        });
    }

    function renderShipment(byDate, detailRows) {
        const ctx = document.getElementById('chart-shipment-trend');
        if (typeof Chart !== 'undefined' && ctx) {
            const labels = byDate.map(r => r.tgl_bukti ?? '-');
            const data = byDate.map(r => Number(r.jumlah_barang || 0));

            if (chartShipment) chartShipment.destroy();
            chartShipment = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Shipment (Pcs)',
                        data,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28,200,138,.15)',
                        fill: true,
                        tension: .25,
                    }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
            });
        }

        const tbody = document.querySelector('#table-shipment-detail tbody');
        tbody.innerHTML = detailRows.map(r => `
            <tr>
                <td>${r.no_bukti ?? '-'}</td>
                <td>${r.tgl_bukti ?? '-'}</td>
                <td>${r.jenis_doc ?? '-'}</td>
                <td>${r.jenis_ps ?? '-'}</td>
                <td>${r.barang_name ?? '-'}</td>
                <td>${r.satuan_doc ?? '-'}</td>
                <td class="right">${fmtNum(r.jumlah_doc)}</td>
                <td class="right">${fmtNum(r.jumlah_barang)}</td>
            </tr>
        `).join('');

        if (dtShipment) dtShipment.destroy();
        dtShipment = $('#table-shipment-detail').DataTable({ pageLength: 10, order: [], autoWidth: false, width: '100%' });
    }

    function renderDetail(rows) {
        const tbody = document.querySelector('#table-rekon-detail tbody');
        tbody.innerHTML = rows.map(r => `
            <tr>
                <td>${r.no_po ?? '-'}</td>
                <td>${r.jenis_po ?? '-'}</td>
                <td>${r.tgl_po ?? '-'}</td>
                <td>${r.tgl_pengiriman ?? '-'}</td>
                <td>${r.supplier_name ?? '-'}</td>
                <td>${r.barang_code ?? '-'}</td>
                <td>${r.barang_name ?? '-'}</td>
                <td>${r.satuan_order ?? '-'}</td>
                <td class="right">${fmtNum(r.jumlah_order)}</td>
                <td class="right">${fmtNum(r.jumlah_doc)}</td>
                <td class="right">${fmtNum(r.out_req)}</td>
                <td class="right">${fmtNum(r.out_prod)}</td>
                <td class="right">${fmtNum(r.sisa)}</td>
                <td class="right">${fmtNum(r.saldo_wip)}</td>
                <td class="right">${fmtNum(r.out_doc)}</td>
                <td class="right">${fmtNum(r.saldo_gudang)}</td>
                <td class="right">${fmtNum(r.harga_total)}</td>
            </tr>
        `).join('');

        if (dtDetail) dtDetail.destroy();
        dtDetail = $('#table-rekon-detail').DataTable({ pageLength: 10, order: [], autoWidth: false, width: '100%' });
    }

    function renderAll(json) {
        renderHeader(json.header);
        renderKpi(json.summary, json.shipmentDates);
        renderFabricQty(json.fabricQty);
        renderFabricUsage(json.fabricUsage);
        renderMaterialAchievement(json.materialAchievement);
        renderProductionResult(json.productionPipeline, json.productionResultByMaterial, json.pipelineLossSteps);
        renderLossSteps(json.pipelineLossSteps);
        renderMaterialPurchase(json.materialPurchase);
        renderTopExcess(json.topMaterialExcess);
        renderWorkOrder(json.workOrder, json.workOrderCount);
        renderShipment(json.shipmentByDate, json.shipmentDetail);
        renderShipmentCategory(json.shipmentByCategory);
        renderDetail(json.detail);
    }

    /**
     * Search sekarang boleh pakai Buyer saja, Style saja, CPO saja, atau
     * kombinasi -- tidak wajib memilih CPO spesifik lagi. Selama minimal
     * satu dari ketiganya terisi, endpoint data dipanggil; service di
     * backend yang meresolve jadi satu atau banyak CPO.
     */
    function refresh() {
        const buyer = fBuyer.value;
        const style = fStyle.value;
        const cpo = fCpo.value;

        if (!buyer && !style && !cpo) {
            if (widgets) widgets.style.display = 'none';
            if (emptyNotice) emptyNotice.style.display = '';
            document.getElementById('rekon-last-updated').textContent = '-';
            return;
        }

        if (emptyNotice) emptyNotice.style.display = 'none';
        if (widgets) widgets.style.display = '';

        const url = buildUrl(endpoint, { uraian: cpo, brand: buyer, style: style });
        fetch(url)
            .then(r => r.json())
            .then(json => renderAll(json))
            .catch(() => Swal.fire('Gagal', 'Tidak bisa memuat data dashboard.', 'error'));
    }

    function runSync(url, label) {
        Swal.fire({
            title: `${label}?`,
            text: 'Menarik data terbaru dari smartit, proses ini bisa memakan waktu.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, sync',
        }).then((res) => {
            if (!res.isConfirmed) return;
            Swal.fire({ title: 'Sync berjalan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            }).then(r => r.json()).then(json => {
                if (json.success) {
                    Swal.fire('Selesai', json.output || 'Sync berhasil.', 'success');
                    refresh();
                } else {
                    Swal.fire('Gagal', json.message || json.output || 'Sync gagal.', 'error');
                }
            }).catch(() => Swal.fire('Gagal', 'Sync gagal dijalankan.', 'error'));
        });
    }

    // Buyer/Style tetap mempersempit pilihan CPO (cascading di dropdown),
    // TAPI sekarang juga langsung memicu pemuatan data dashboard sendiri --
    // jadi user bisa search cukup dengan Buyer saja atau Style saja, tanpa
    // wajib turun sampai pilih 1 CPO spesifik. Dipasang di event khusus
    // Select2 (select2:select / select2:clear), bukan 'change' biasa, supaya
    // tidak ikut ter-trigger saat populateSelect() mengisi ulang <option>
    // secara programatik (yang juga memicu 'change' supaya tampilan Select2
    // ter-refresh) -- ini mencegah loop cascading.
    $(fBuyer).on('select2:select select2:clear', function () {
        $(fStyle).val('').trigger('change');
        $(fCpo).val('').trigger('change');
        refreshCascade();
        refresh();
    });
    $(fStyle).on('select2:select select2:clear', function () {
        $(fCpo).val('').trigger('change');
        refreshCascade();
        refresh();
    });
    $(fCpo).on('select2:select select2:clear', refresh);

    if (btnFilterCpo) btnFilterCpo.addEventListener('click', refresh);
    if (btnSyncRekon) btnSyncRekon.addEventListener('click', () => runSync(syncRekonUrl, 'Sync Rekonsiliasi'));
    if (btnSyncProdline) btnSyncProdline.addEventListener('click', () => runSync(syncProdlineUrl, 'Sync Production Line'));
    if (btnSyncShipment) btnSyncShipment.addEventListener('click', () => runSync(syncShipmentUrl, 'Sync Shipment'));
    if (btnSyncWorkOrder) btnSyncWorkOrder.addEventListener('click', () => runSync(syncWorkOrderUrl, 'Sync Work Order'));

    refresh();
})();
</script>

<style>
    @import url('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');

    .right { text-align: right; }
    .mon-table-box { max-height: 420px; overflow: auto; }

    /* Cegah tabel DataTables menyusut & memicu scrollbar ganda saat kosong */
    .table-responsive table.dataTable { width: 100% !important; }

    /* ===== Header bar biru ala gambar ===== */
    .rekon-hero {
        background: linear-gradient(135deg, #0b3d5c, #123f60);
        color: #fff;
        border-radius: .5rem;
        padding: 16px 22px;
    }
    .rekon-hero-title { font-size: 1.25rem; font-weight: 800; letter-spacing: .5px; }
    .rekon-hero-sub { font-size: .8rem; opacity: .9; }
    .rekon-search .input-group-text { background: #fff; color: #0b3d5c; font-weight: 700; font-size: .75rem; }
    #btn-filter-cpo { font-weight: 700; }

    /* ===== Fix: Select2 di dalam Bootstrap input-group ===== */
    .rekon-search {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
    }
    .rekon-search .input-group-text,
    .rekon-search .select2-container,
    .rekon-search .input-group-append {
        height: 100%; /* agar semua sama tinggi */
    }
    .rekon-search .input-group-append .btn {
        height: 100%;
        border-radius: 0 0.25rem 0.25rem 0; /* atau sesuai */
    }
    .rekon-search.input-group {
        flex-wrap: nowrap;
        width: auto;
    }
    .rekon-search .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
        min-width: 160px;
    }
    .rekon-search .input-group-append .btn {
        height: calc(1.5em + .5rem + 2px);
        display: flex;
        align-items: center;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
    .rekon-search .select2-container .select2-selection--single {
        height: calc(1.5em + .5rem + 2px);
        border-radius: 0;
        display: flex;
        align-items: center;
    }
    .rekon-search .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 1.2;
    }
    .rekon-search .select2-container .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + .5rem);
    }
    .rekon-search .input-group-append #btn-filter-cpo {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        white-space: nowrap;
    }

    .rekon-kpi .card-body { padding: 1rem .75rem; }

    /* ===== Pipeline flow: kartu per tahapan (Output Qty/% + Loss Qty/%) ===== */
    .rekon-pipe-box {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: .5rem;
        overflow: hidden;
        min-width: 128px;
        text-align: center;
        margin-bottom: .5rem;
        box-shadow: 0 1px 2px rgba(0,0,0,.06);
    }
    .rekon-pipe-header {
        padding: .35rem .5rem;
        font-size: .72rem;
        font-weight: 700;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .rekon-pipe-box.theme-green   .rekon-pipe-header { background: #1e824c; }
    .rekon-pipe-box.theme-navy    .rekon-pipe-header { background: #1f3864; }
    .rekon-pipe-box.theme-neutral .rekon-pipe-header { background: #5a5c69; }
    .rekon-pipe-box.theme-loss    .rekon-pipe-header { background: #c0392b; }

    .rekon-pipe-body { padding: .55rem .6rem .65rem; }

    .rekon-pipe-output   { font-size: 1.1rem; font-weight: 800; }
    .rekon-pipe-output-pct { font-size: .78rem; font-weight: 700; margin-top: .05rem; }
    .theme-green   .rekon-pipe-output, .theme-green   .rekon-pipe-output-pct { color: #1e824c; }
    .theme-navy    .rekon-pipe-output, .theme-navy    .rekon-pipe-output-pct { color: #1f3864; }
    .theme-neutral .rekon-pipe-output, .theme-neutral .rekon-pipe-output-pct { color: #2e3a4b; }
    .theme-loss    .rekon-pipe-output, .theme-loss    .rekon-pipe-output-pct { color: #c0392b; }

    .rekon-pipe-divider { border: 0; border-top: 1px dashed #e3e6f0; margin: .45rem 0; }

    .rekon-pipe-loss-value { font-size: .95rem; font-weight: 800; color: #c0392b; }
    .rekon-pipe-loss-pct   { font-size: .75rem; font-weight: 700; color: #c0392b; }
    .rekon-pipe-loss-value.is-gain,
    .rekon-pipe-loss-pct.is-gain { color: #1cc88a; }

    .rekon-pipe-total .rekon-pipe-body { padding-top: .7rem; }

    .rekon-pipe-arrow { display: flex; align-items: center; justify-content: center; color: #4e73df; padding: 0 .5rem; margin-bottom: .5rem; }

    .rekon-pipe-legend {
        display: flex;
        gap: 1.25rem;
        flex-wrap: wrap;
        font-size: .75rem;
        font-weight: 600;
        color: #5a5c69;
        margin-top: .25rem;
    }
    .rekon-pipe-legend span { display: inline-flex; align-items: center; }
    .rekon-pipe-dot {
        display: inline-block;
        width: .55rem;
        height: .55rem;
        border-radius: 50%;
        margin-right: .35rem;
    }
    .rekon-pipe-dot.dot-output { background: #1f3864; }
    .rekon-pipe-dot.dot-loss   { background: #c0392b; }
    /* ===== Fabric Qty (KGM) card ===== */
    .rekon-fabric-card .card-header {
        background: #1f3864;
        color: #fff;
    }
    .rekon-fabric-card .card-header h6 { color: #fff; }
    .rekon-fabric-box {
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        border-radius: .35rem;
        padding: .6rem .5rem;
        text-align: center;
    }
    .rekon-fabric-label {
        font-size: .68rem;
        font-weight: 700;
        color: #5a5c69;
        text-transform: uppercase;
        margin-bottom: .2rem;
    }
    .rekon-fabric-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: #1f3864;
    }
    /* ===== Fabric Usage / Fabric Usage Percentage card ===== */
    .rekon-usage-box {
        position: relative;
        border: 1px dashed #b7b9c8;
        border-radius: .35rem;
        padding: 1.5rem 1.25rem .75rem;
        margin-bottom: 1.1rem;
    }
    .rekon-usage-box-title {
        position: absolute;
        top: -.65rem;
        left: .9rem;
        background: #fff;
        padding: 0 .5rem;
        font-size: .72rem;
        font-weight: 700;
        color: #5a5c69;
        text-transform: uppercase;
        letter-spacing: .03em;
    }
    .rekon-usage-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .55rem 0;
        border-bottom: 1px solid #e3e6f0;
    }
    .rekon-usage-row:last-child { border-bottom: 0; }
    .rekon-usage-row-label {
        font-size: .8rem;
        font-weight: 700;
        color: #5a5c69;
        text-transform: uppercase;
    }
    .rekon-usage-row-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: #1f3864;
        min-width: 130px;
        text-align: right;
        border-bottom: 1px solid #cfd3e0;
        padding-bottom: .15rem;
    }
    .rekon-usage-consumption {
        display: flex;
        align-items: center;
        gap: .4rem;
        font-size: .85rem;
        font-weight: 700;
        color: #5a5c69;
        padding-left: .1rem;
    }
    .rekon-usage-consumption-value {
        font-weight: 800;
        color: #1f3864;
        border-bottom: 1px solid #cfd3e0;
        padding: 0 .5rem;
    }
    .rekon-usage-donut-area {
        position: relative;
        flex: 0 0 auto;
        width: 140px;
        height: 140px;
    }
    .rekon-usage-legend {
        display: flex;
        flex-direction: column;
        gap: .6rem;
        margin-left: 1rem;
        font-size: .78rem;
        font-weight: 700;
        color: #5a5c69;
    }
    /* Card FABRIC QTY/USAGE/USAGE % sekarang berbagi 1 baris (col-lg-4),
       jadi box "Need/Order/Received/Out WIP" dipadatkan jadi 2x2 grid. */
    .rekon-fabric-boxes .rekon-fabric-box {
        padding: .5rem .4rem;
    }
    .rekon-fabric-boxes .rekon-fabric-value {
        font-size: 1.05rem;
    }
    .rekon-usage-dot {
        display: inline-block;
        width: .7rem;
        height: .7rem;
        border-radius: 2px;
        margin-right: .4rem;
        vertical-align: middle;
    }
</style>

</body>
</html>