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
                 data-negara-options='@json($negaraOptions)'
                 data-ocf-options='@json($ocfOptions)'
                 data-endpoint="{{ route('monitoring.rekonsiliasi.data') }}"
                 data-calendar-url="{{ route('monitoring.rekonsiliasi.calendar') }}"
                 data-calendar-detail-url="{{ route('monitoring.rekonsiliasi.calendar.detail') }}"
                 data-sync-rekon-url="{{ route('monitoring.rekonsiliasi.sync') }}"
                 data-sync-prodline-url="{{ route('monitoring.rekonsiliasi.sync-prod-line') }}"
                 data-sync-shipment-url="{{ route('monitoring.rekonsiliasi.sync-shipment') }}"
                 data-sync-workorder-url="{{ route('monitoring.rekonsiliasi.sync-work-order') }}"
                 data-sync-ms-barang-url="{{ route('monitoring.rekonsiliasi.sync-ms-barang') }}"
                 data-sync-ms-negara-url="{{ route('monitoring.rekonsiliasi.sync-ms-negara') }}"
                 data-sync-ms-supplier-url="{{ route('monitoring.rekonsiliasi.sync-ms-supplier') }}"
                 data-negara-options-url="{{ route('monitoring.rekonsiliasi.negara-options') }}">

                {{-- ================= HEADER BAR ================= --}}
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
                                <select id="f-ocf" class="form-control select2-filter" style="min-width:180px" data-placeholder="Cari OCF...">
                                    <option value=""></option>
                                    @foreach($ocfOptions as $o)
                                        <option value="{{ $o }}" @selected(($filters['ocf'] ?? null) === $o)>{{ $o }}</option>
                                    @endforeach
                                </select>
                                <select id="f-negara" class="form-control select2-filter" style="min-width:170px" data-placeholder="Semua Negara...">
                                    <option value=""></option>
                                    @foreach($negaraOptions as $n)
                                        <option value="{{ $n->negara_code }}" @selected(($filters['negara'] ?? null) === $n->negara_code)>{{ $n->negara_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="text-white small">
                                <div class="text-uppercase" style="opacity:.75">Last Updated</div>
                                <div class="font-weight-bold" id="rekon-last-updated">--</div>
                            </div>
                            <div class="d-flex" style="gap:6px">
                                @php $canSyncAny = false; @endphp
                                @canRoute('monitoring.rekonsiliasi.sync')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-prod-line')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-shipment')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-work-order')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-ms-barang')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-ms-negara')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-ms-supplier')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @if($canSyncAny)
                                    <button id="btn-sync-all" type="button" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-sync-alt fa-sm"></i> Sync All Data
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="rekon-hero-sub mt-2">
                        CPO : <span id="hdr-cpo">-</span>
                        &nbsp;|&nbsp; BRAND <span id="hdr-brand">-</span>
                        &nbsp;|&nbsp; STYLE <span id="hdr-style">-</span>
                        <span id="hdr-negara-wrap" style="display:none">
                            &nbsp;|&nbsp; NEGARA <span id="hdr-negara">-</span>
                        </span>
                        <span id="hdr-ocf-wrap" style="display:none">
                            &nbsp;|&nbsp; OCF <span id="hdr-ocf">-</span>
                        </span>
                        <span id="hdr-cpo-count-wrap" style="display:none">
                            &nbsp;|&nbsp; <span class="badge badge-light" id="hdr-cpo-count"></span>
                        </span>
                    </div>
                </div>

                {{-- ================= EMPTY STATE ================= --}}
                <div id="rekon-empty-notice" class="card shadow mb-4" style="display:none">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-filter fa-2x mb-3 d-block"></i>
                        <div class="font-weight-bold mb-1">Belum ada filter yang dipilih</div>
                        <div class="small">
                            Pilih salah satu atau kombinasi dari <strong>Buyer</strong>, <strong>Style</strong>,
                            <strong>CPO</strong>, <strong>OCF</strong>, atau <strong>Negara</strong> pada kolom di atas
                            untuk menampilkan data dashboard. Kalau cuma Buyer dan/atau Style yang dipilih (tanpa CPO
                            spesifik), data dari semua CPO yang cocok akan digabung. <strong>OCF</strong> dan
                            <strong>Negara</strong> bisa dipilih sendirian (menggabungkan semua CPO yang cocok) atau
                            dikombinasikan dengan Buyer/Style/CPO untuk mempersempit hasilnya. Data tidak dimuat
                            otomatis saat halaman dibuka karena cukup berat kalau ditarik untuk semua CPO sekaligus.
                        </div>
                    </div>
                </div>

                {{-- ================= WIDGET DATA ================= --}}
                <div id="rekon-widgets" style="display:none">

                {{-- ================= KPI CARDS ================= --}}
                <div class="row">
                    <!-- <div class="col-md mb-4">
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
                    </div> -->
                    <!-- <div class="col-md mb-4">
                        <div class="card shadow h-100 py-2 rekon-kpi">
                            <div class="card-body text-center">
                                <table class="table table-bordered table-sm mb-0 rekon-ship-calendar-sm" id="mon-ship-calendar">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Min</th><th class="text-center">Sen</th>
                                            <th class="text-center">Sel</th><th class="text-center">Rab</th>
                                            <th class="text-center">Kam</th><th class="text-center">Jum</th>
                                            <th class="text-center">Sab</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div> -->
                </div>

                {{-- ================= PRODUCTION FLOW / STAGE PIPELINE ================= --}}
                <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-body">
                                <div class="rekon-pipeline" id="rekon-pipeline">
                                    <!-- diisi JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                
                {{-- ================= PROCESS LOSS PER TAHAP (tabel) ================= --}}
                <!-- <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Process Loss per Tahap</h6>
                            </div>
                            <div class="card-body">
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
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                {{-- ================= SHIPMENT DATE (kalender) ================= --}}
                <!-- <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Shipment Date</h6>
                        <div class="d-flex align-items-center" style="gap:10px">
                            <button id="ship-cal-prev" type="button" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-left"></i></button>
                            <span id="ship-cal-label" class="font-weight-bold text-gray-700" style="min-width:140px; text-align:center;">--</span>
                            <button id="ship-cal-next" type="button" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="mon-table-box" id="ship-cal-detail-box">
                                    <table class="table table-bordered table-sm mon-table mon-table-fixed w-100" id="table-ship-cal-detail">
                                        <thead>
                                            <tr>
                                                <th>Tgl Bukti</th><th>Uraian</th><th>No. Bukti</th>
                                                <th>Jenis Doc</th><th>Jenis PS</th><th>Supplier</th>
                                                <th>Barang</th><th class="right">Jumlah Barang</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                {{-- ================= FABRIC QTY / USAGE ================= --}}
                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100 rekon-fabric-card">
                            <div class="card-header py-2">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-shopping-basket mr-1"></i> FABRIC ACHIEVEMENT</h6>
                            </div>
                            <div class="card-body">
                                <h6 class="text-center text-muted text-uppercase small font-weight-bold mb-2">Fabric</h6>
                                <div class="chart-area" style="height:340px">
                                    <canvas id="chart-material-achievement-fabric"></canvas>
                                </div>
                                <!-- <div class="row rekon-fabric-boxes">
                                    <div class="col-4 mb-2">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Need</div>
                                            <div class="rekon-fabric-value" id="fabric-need">0</div>
                                        </div>
                                    </div>
                                    <div class="col-4 mb-2">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Order</div>
                                            <div class="rekon-fabric-value" id="fabric-order">0</div>
                                        </div>
                                    </div>
                                    <div class="col-4 mb-2">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Received</div>
                                            <div class="rekon-fabric-value" id="fabric-received">0</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Out WIP</div>
                                            <div class="rekon-fabric-value" id="fabric-out-wip">0</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="rekon-fabric-box">
                                            <div class="rekon-fabric-label">Stock</div>
                                            <div class="rekon-fabric-value" id="fabric-stock">0</div>
                                        </div>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card shadow h-100 rekon-fabric-card">
                            <div class="card-header py-2">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-tshirt mr-1"></i> FABRIC USAGE</h6>
                            </div>
                            <div class="card-body">
                                <div class="rekon-usage-box">
                                    <span class="rekon-usage-box-title">Qty Usage (%)</span>
                                    <div class="rekon-usage-row">
                                        <div class="rekon-usage-row-label">Use For GMT</div>
                                        <div class="rekon-usage-row-value" id="usage-for-gmt">0</div>
                                    </div>
                                    <div class="rekon-usage-row">
                                        <div class="rekon-usage-row-label">Scrap Qty</div>
                                        <div class="rekon-usage-row-value" id="usage-scrap-qty">0</div>
                                    </div>
                                </div>
                                <!-- <div class="rekon-usage-consumption">
                                    <span>Consumption :</span>
                                    <span class="rekon-usage-consumption-value" id="usage-consumption">0</span>
                                    <span>Kgm</span>
                                </div> -->
                            </div>
                        </div>
                    </div>

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

                {{-- ================= MATERIAL ACHIEVEMENT & SHIPMENT BY DATE ================= --}}
                <div class="row">
                    <div class="col-lg-10 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">MATERIAL ACHIEVEMENT</h6>
                            </div>
                            <div class="card-body">
                                <div class="rekon-ma-formula mb-3">
                                </div>
                                <div class="row">
                                    <div class="col-lg-6 mb-4 mb-lg-0">
                                        <h6 class="text-center text-muted text-uppercase small font-weight-bold mb-2">Aksesoris Trim</h6>
                                        <div class="chart-area" style="height:320px">
                                            <canvas id="chart-material-achievement-aksesoris"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <h6 class="text-center text-muted text-uppercase small font-weight-bold mb-2">Packing Trim</h6>
                                        <div class="chart-area" style="height:320px">
                                            <canvas id="chart-material-achievement-packing"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ================= SHIPMENT BY DATE CHART ================= --}}
                    <div class="col-lg-2 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calendar-alt mr-1"></i> SHIPMENT BY DATE</h6>
                            </div>
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="chart-area" style="height:340px">
                                    <canvas id="chart-shipment-by-date"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= DETAIL TABLE ================= --}}
                <!-- <div class="card shadow mb-4">
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
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div> -->

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

<!-- DataTables -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- CSS Tambahan -->
<style>
    .mon-table-box table thead th {
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
    }
    .mon-table-box .dataTables_scrollHeadInner table thead th {
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 10;
    }
    .mon-table-box table {
        border-collapse: separate;
        border-spacing: 0;
    }
    .mon-table-box table thead th {
        border-bottom: 2px solid #dee2e6;
    }

    #rekon-widgets .card-header,
    #rekon-widgets .card .card-header {
        background: #1f3864 !important;
        color: #fff !important;
    }
    #rekon-widgets .card-header h6,
    #rekon-widgets .card-header .m-0,
    #rekon-widgets .card-header * {
        color: #fff !important;
    }

    #rekon-widgets .dataTable thead th,
    #rekon-widgets .table thead th {
        background: #1f3864 !important;
        color: #fff !important;
    }

    #rekon-widgets .dataTable tbody td,
    #rekon-widgets .table tbody td {
        color: #000 !important;
    }

    .mon-table-box.no-scroll {
        max-height: none !important;
        overflow: visible !important;
    }

    .right { text-align: right; }
    .mon-table-box { max-height: 420px; overflow: auto; }
    .table-responsive table.dataTable { width: 100% !important; }

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

    .rekon-search {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
    }
    .rekon-search .input-group-text,
    .rekon-search .select2-container,
    .rekon-search .input-group-append {
        height: 100%;
    }
    .rekon-search .input-group-append .btn {
        height: 100%;
        border-radius: 0 0.25rem 0.25rem 0;
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

    /* Warna loss sesuai permintaan: negatif = merah, positif = hijau */
    .rekon-pipe-loss-value.loss-negative,
    .rekon-pipe-loss-pct.loss-negative {
        color: #c0392b !important; /* merah */
    }
    .rekon-pipe-loss-value.loss-positive,
    .rekon-pipe-loss-pct.loss-positive {
        color: #1cc88a !important; /* hijau */
    }
    .rekon-pipe-loss-value.loss-zero,
    .rekon-pipe-loss-pct.loss-zero {
        color: #5a5c69 !important; /* abu-abu */
    }

    .rekon-pipe-total .rekon-pipe-body { padding-top: .7rem; }

    .rekon-pipe-arrow { display: flex; align-items: center; justify-content: center; color: #4e73df; padding: 0 .5rem; margin-bottom: .5rem; }

    .rekon-ship-calendar-sm th,
    .rekon-ship-calendar-sm td {
        font-size: .72rem;
        padding: .25rem .15rem;
    }
    .rekon-ship-calendar-sm td { line-height: 1.1; }

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
        width: 230px;
        height: 230px;
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

    .rekon-kpi .card-body {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        height: 100%;
    }

    .rekon-ma-formula {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem 1.5rem;
        justify-content: center;
        font-size: .72rem;
        color: #5a5c69;
        background: #f8f9fc;
        border: 1px dashed #d8dae4;
        border-radius: .35rem;
        padding: .5rem .75rem;
    }
    .rekon-ma-formula strong { color: #1f3864; }
</style>

<script>
(function(){
    const app = document.getElementById('rekon-app');
    const endpoint = app.dataset.endpoint;
    const calendarUrl = app.dataset.calendarUrl;
    const calendarDetailUrl = app.dataset.calendarDetailUrl;
    const syncRekonUrl = app.dataset.syncRekonUrl;
    const syncProdlineUrl = app.dataset.syncProdlineUrl;
    const syncShipmentUrl = app.dataset.syncShipmentUrl;
    const syncWorkOrderUrl = app.dataset.syncWorkorderUrl;
    const syncMsBarangUrl = app.dataset.syncMsBarangUrl;
    const syncMsNegaraUrl = app.dataset.syncMsNegaraUrl;
    const syncMsSupplierUrl = app.dataset.syncMsSupplierUrl;
    const negaraOptionsUrl = app.dataset.negaraOptionsUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const fBuyer = document.getElementById('f-buyer');
    const fStyle = document.getElementById('f-style');
    const fCpo = document.getElementById('f-cpo');
    const fOcf = document.getElementById('f-ocf');
    const fNegara = document.getElementById('f-negara');
    const emptyNotice = document.getElementById('rekon-empty-notice');
    const widgets = document.getElementById('rekon-widgets');
    const btnSyncAll = document.getElementById('btn-sync-all');

    let filterOptions = [];
    try { filterOptions = JSON.parse(app.dataset.filterOptions || '[]'); } catch (e) { filterOptions = []; }

    $('.select2-filter').each(function () {
        $(this).select2({ width: '100%', placeholder: $(this).data('placeholder') || '', allowClear: true });
    });

    const fmtNum = (v) => new Intl.NumberFormat('id-ID').format(Number(v || 0));

    function fmtPct(value) {
        if (value === null || value === undefined || value === '') return '-';
        const num = parseFloat(value);
        if (isNaN(num)) return '-';
        const sign = num < 0 ? '-' : '';
        const abs = Math.abs(num);
        const rounded = Math.round(abs * 10) / 10;
        const formatted = rounded.toFixed(1).replace('.', ',');
        return sign + formatted + '%';
    }

    function fmtPctWithSign(value) {
        if (value === null || value === undefined || value === '') return '-';
        const num = parseFloat(value);
        if (isNaN(num)) return '-';
        const abs = Math.abs(num);
        const rounded = Math.round(abs * 10) / 10;
        const formatted = rounded.toFixed(1).replace('.', ',');
        return (num < 0 ? '' : '+') + formatted + '%';
    }

    // Format tanggal Indonesia
    function formatTanggalIndonesia(value) {
        if (!value) return '';
        const datePart = String(value).split(' ')[0];
        const [y, m, d] = datePart.split('-').map(Number);
        if (!y || !m || !d) return value;
        const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        if (!monthNames[m-1]) return value;
        return `${d} ${monthNames[m-1]} ${y}`;
    }

    function uniqueSorted(values) {
        return [...new Set(values.filter(v => v !== null && v !== undefined && v !== ''))].sort();
    }

    function populateSelect($el, values) {
        const current = $el.val();
        $el.empty().append('<option value=""></option>');
        values.forEach(v => $el.append(new Option(v, v, false, false)));
        $el.val(values.includes(current) ? current : '').trigger('change');
    }

    function refreshCascade() {
        const buyer = fBuyer.value;
        const style = fStyle.value;

        const styleRows = filterOptions.filter(r => !buyer || r.brand === buyer);
        populateSelect($(fStyle), uniqueSorted(styleRows.map(r => r.style)));

        const cpoRows = filterOptions.filter(r =>
            (!buyer || r.brand === buyer) && (!style || r.style === style));
        populateSelect($(fCpo), uniqueSorted(cpoRows.map(r => r.uraian)));
    }

    let ocfOptions = [];
    try { ocfOptions = JSON.parse(app.dataset.ocfOptions || '[]'); } catch (e) { ocfOptions = []; }

    (function initCascadeFilters() {
        populateSelect($(fBuyer), uniqueSorted(filterOptions.map(r => r.brand)));
        populateSelect($(fOcf), uniqueSorted(ocfOptions));

        const initialFilters = app.dataset.filters ? JSON.parse(app.dataset.filters) : {};
        const initialUraian = initialFilters.uraian || null;
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
        if (initialFilters.ocf) {
            $(fOcf).val(initialFilters.ocf).trigger('change');
        }
    })();

    let chartMaterialFabric, chartMaterialAksesoris, chartMaterialPacking, chartFabricUsage, chartShipmentByDate, dtDetail;

    function wrapLabel(label, maxWidth = 14) {
        const words = String(label ?? '-').split(' ');
        const lines = [];
        let current = '';
        words.forEach(w => {
            if ((current + ' ' + w).trim().length > maxWidth) {
                if (current) lines.push(current.trim());
                current = w;
            } else {
                current = (current + ' ' + w).trim();
            }
        });
        if (current) lines.push(current);
        return lines.length ? lines : ['-'];
    }

    const HORIZONTAL_WRAP_X_TICKS = { autoSkip: false, maxRotation: 0, minRotation: 0 };

    function buildUrl(base, params) {
        const url = new URL(base, window.location.origin);
        Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') url.searchParams.set(k, v); });
        return url.toString();
    }

    function renderHeader(header) {
        header = header || {};
        document.getElementById('hdr-cpo').textContent = header.cpo || '-';
        document.getElementById('hdr-brand').textContent = header.brand || '-';
        document.getElementById('hdr-style').textContent = header.style || '-';

        const negaraWrap = document.getElementById('hdr-negara-wrap');
        const negaraLabel = fNegara.options[fNegara.selectedIndex]?.text || '';
        if (fNegara.value) {
            document.getElementById('hdr-negara').textContent = negaraLabel || fNegara.value;
            negaraWrap.style.display = '';
        } else {
            negaraWrap.style.display = 'none';
        }

        const ocfWrap = document.getElementById('hdr-ocf-wrap');
        if (fOcf.value) {
            document.getElementById('hdr-ocf').textContent = fOcf.value;
            ocfWrap.style.display = '';
        } else {
            ocfWrap.style.display = 'none';
        }

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

    function renderKpi(summary) {
        // document.getElementById('kpi-contract').textContent = fmtNum(summary.contract_qty);
        // document.getElementById('kpi-shipment').textContent = fmtNum(summary.shipment_qty);
        // document.getElementById('kpi-achievement').textContent = fmtPct(summary.achievement_pct);
        // document.getElementById('kpi-balance').textContent = fmtNum(summary.balance_qty);
    }

    function renderFabricQty(fabricQty) {
        fabricQty = fabricQty || {};
        // document.getElementById('fabric-need').textContent = fmtNum(fabricQty.need);
        // document.getElementById('fabric-order').textContent = fmtNum(fabricQty.order);
        // document.getElementById('fabric-received').textContent = fmtNum(fabricQty.received);
        // document.getElementById('fabric-out-wip').textContent = fmtNum(fabricQty.out_wip);
        // document.getElementById('fabric-stock').textContent = fmtNum(fabricQty.stock);
    }

    function renderFabricUsage(fabricUsage) {
    fabricUsage = fabricUsage || {};

    // Ambil nilai persentase dari backend (jika ada)
    const usagePct = Number(fabricUsage.usage_pct) || 0;
    const scrapPct = Number(fabricUsage.scrap_pct) || 0;

    // Tampilkan sebagai persentase dengan 1 desimal dan koma
    document.getElementById('usage-for-gmt').textContent = fmtPct(usagePct);        // <-- ubah
    document.getElementById('usage-scrap-qty').textContent = fmtPct(scrapPct);      // <-- ubah
    // document.getElementById('usage-consumption').textContent = '100,0%';            // <-- ubah (atau fmtPct(100))

    // Ubah judul box (jika belum diubah di HTML)
    const titleEl = document.querySelector('.rekon-usage-box-title');
    if (titleEl) titleEl.textContent = 'Qty Usage (%)';

    // Ubah satuan di bagian Consumption
    // const consumptionSpan = document.querySelector('.rekon-usage-consumption span:last-child');
    // if (consumptionSpan) consumptionSpan.textContent = '%';  // <-- ubah

    // (lanjutkan kode untuk chart donut, tidak diubah)
    const ctx = document.getElementById('chart-fabric-usage');
    if (typeof Chart === 'undefined' || !ctx) return;

    if (chartFabricUsage) chartFabricUsage.destroy();
    chartFabricUsage = new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [usagePct, scrapPct],
                backgroundColor: ['#1f6f8b', '#e07b39'],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '40%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => `${c.parsed.toFixed(1).replace('.', ',')}%`
                    }
                },
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
                    const formatted = value.toFixed(1).replace('.', ',') + '%';
                    c.fillText(formatted, pos.x, pos.y);
                });
                c.restore();
            },
        }],
    });
}

    const MATERIAL_ACHIEVEMENT_CHARTS = {
        fabric:    { canvasId: 'chart-material-achievement-fabric' },
        aksesoris: { canvasId: 'chart-material-achievement-aksesoris' },
        packing:   { canvasId: 'chart-material-achievement-packing' },
    };

    function buildMaterialAchievementChart(group, rows) {
    const meta = MATERIAL_ACHIEVEMENT_CHARTS[group];
    const ctx = document.getElementById(meta.canvasId);
    if (typeof Chart === 'undefined' || !ctx) return;

    const labels = rows.map(r => wrapLabel(r.barang_name));
    const mk = (key, color, label) => ({ label, data: rows.map(r => r[key]), backgroundColor: color });

    if (meta.chart) meta.chart.destroy();
    meta.chart = new Chart(ctx, {
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
            layout: { padding: { top: 30 } },
            scales: {
                x: { ticks: HORIZONTAL_WRAP_X_TICKS },
                y: { beginAtZero: true, ticks: { callback: v => v + '%' } },
            },
            plugins: {
                legend: { display: false },  // <- matikan legend bawaan
                tooltip: {
                    callbacks: {
                        label: (c) => `${c.dataset.label}: ${c.parsed.y.toFixed(1).replace('.', ',')}%`
                    }
                },
            },
        },
        // === INI BAGIAN YANG DIPERBAIKI ===
        plugins: [{
    id: 'materialAchievementValueLabels',
    afterDraw(chart) {
        const { ctx, data, chartArea: { top, bottom, left, right } } = chart;
        ctx.save();
        ctx.font = 'bold 11px Arial';
        ctx.textAlign = 'center';
        ctx.fillStyle = '#2e3a4b';
        data.datasets.forEach((dataset, di) => {
            const meta = chart.getDatasetMeta(di);
            meta.data.forEach((bar, i) => {
                const value = dataset.data[i];
                if (value === null || value === undefined || value === 0) return;
                // Ubah format di sini
                const label = `${Math.round(value)}`;
                let x = bar.x;
                let y = bar.y - 6;
                let baseline = 'bottom';
                if (bar.y - top < 5) {
                    y = top - 4;
                    baseline = 'bottom';
                } else if (y < top) {
                    y = bar.y + 10;
                    baseline = 'top';
                }
                ctx.textBaseline = baseline;
                if (x > left && x < right && y > top - 20 && y < bottom) {
                    ctx.fillText(label, x, y);
                }
            });
        });
        ctx.restore();
    }
}]
    });
}

function updateFormulaWithColors() {
    const formulaDiv = document.querySelector('.rekon-ma-formula');
    if (!formulaDiv) return;
    const items = [
        { label: 'ORDER%', desc: 'Jumlah Order', color: '#4e73df' },
        { label: 'RECEIVED%', desc: 'Jumlah Diterima ÷ Jumlah Order', color: '#f6a533' },
        { label: 'OUT PROD%', desc: 'Out Req (WIP) ÷ Jumlah Diterima', color: '#1cc88a' },
        { label: 'STOCK%', desc: 'Saldo Gudang ÷ Jumlah Diterima', color: '#36b9cc' }
    ];
    formulaDiv.innerHTML = items.map(item =>
        `<span><span style="display:inline-block;width:12px;height:12px;background:${item.color};border-radius:2px;margin-right:4px;"></span><strong>${item.label}</strong> = ${item.desc}</span>`
    ).join('');
}

    function renderMaterialAchievement(rows) {
        rows = rows || [];
        const byGroup = { fabric: [], aksesoris: [], packing: [] };
        rows.forEach(r => {
            if (byGroup[r.material_group]) byGroup[r.material_group].push(r);
        });

        if (byGroup.fabric.length) {
            byGroup.fabric = byGroup.fabric.map((r, idx) => {
                const letter = String.fromCharCode(65 + idx);
                return { ...r, barang_name: `Fabric ${letter}` };
            });
        }

        buildMaterialAchievementChart('fabric', byGroup.fabric);
        buildMaterialAchievementChart('aksesoris', byGroup.aksesoris);
        buildMaterialAchievementChart('packing', byGroup.packing);
        updateFormulaWithColors();
    }

    // ========== renderProductionResult: menggunakan data dari backend ==========
    function renderProductionResult(pipeline, materialRows, lossSteps) {
        // materialRows tidak dipakai, hanya untuk kompatibilitas
        lossSteps = lossSteps || [];

        const stages = [
            { label: 'Contract', value: pipeline.contract, theme: 'neutral' },
            ...pipeline.departments.map(d => ({
                label: d.department_id ?? '-',
                value: d.jumlah,
                theme: 'green',
            })),
            { label: 'Shipment', value: pipeline.shipment, theme: 'navy' },
        ];

        const boxes = stages.map((s, i) => {
            // Step ke-i (i=0 tidak punya step karena step pertama adalah Contract→Cutting)
            const step = i > 0 && i - 1 < lossSteps.length ? lossSteps[i - 1] : null;

            // Ambil loss langsung dari backend
            let lossValue = step ? Number(step.loss_pcs || 0) : null;
            let lossPct   = step ? Number(step.loss_pct || 0) : null;

            // Output persentase berdasarkan input/output
            let outputPct = null;
            if (step && step.input > 0) {
                outputPct = (Number(step.output) / Number(step.input)) * 100;
            } else if (i === 0) {
                outputPct = 100;
            }

            // Tentukan warna dan tanda
            let lossColorClass = 'loss-zero';
            let lossSign = '';
            if (lossValue !== null) {
                if (lossValue < 0) {
                    lossColorClass = 'loss-negative';
                    lossSign = '-';
                } else if (lossValue > 0) {
                    lossColorClass = 'loss-positive';
                    // tidak pakai tanda plus
                } else {
                    lossColorClass = 'loss-zero';
                }
            }

            let lossDisplay = '-';
            if (lossValue !== null) {
                lossDisplay = lossSign + fmtNum(Math.abs(lossValue));
            }

            let lossPctDisplay = '-';
            if (lossPct !== null) {
                lossPctDisplay = lossSign + fmtPct(Math.abs(lossPct));
            }

            const lossRow = step ? `
                <hr class="rekon-pipe-divider">
                <div class="rekon-pipe-loss-value ${lossColorClass}">${lossDisplay}</div>
                <div class="rekon-pipe-loss-pct ${lossColorClass}">${lossPctDisplay}</div>
            ` : `
                <hr class="rekon-pipe-divider">
                <div class="rekon-pipe-loss-value">-</div>
                <div class="rekon-pipe-loss-pct">&nbsp;</div>
            `;

            const outputPctDisplay = outputPct !== null ? fmtPct(outputPct) : '-';

            return `
                <div class="rekon-pipe-box theme-${s.theme}">
                    <div class="rekon-pipe-header">${s.label}</div>
                    <div class="rekon-pipe-body">
                        <div class="rekon-pipe-output">${fmtNum(s.value)}</div>
                        <div class="rekon-pipe-output-pct">${outputPctDisplay}</div>
                        ${lossRow}
                    </div>
                </div>
                <div class="rekon-pipe-arrow"><i class="fas fa-arrow-right"></i></div>
            `;
        }).join('');

        // Total Process Loss – dari backend (sudah dihitung di service)
        const totalLoss   = Number(pipeline.total_loss || 0);
        const totalLossPct = Number(pipeline.loss_pct || 0);

        let totalLossColorClass = 'loss-zero';
        let totalLossSign = '';
        if (totalLoss < 0) {
            totalLossColorClass = 'loss-negative';
            totalLossSign = '-';
        } else if (totalLoss > 0) {
            totalLossColorClass = 'loss-positive';
        } else {
            totalLossColorClass = 'loss-zero';
        }

        const lossBox = `
            <div class="rekon-pipe-box theme-loss rekon-pipe-total">
                <div class="rekon-pipe-header">Total Process Loss</div>
                <div class="rekon-pipe-body">
                    <div class="rekon-pipe-output ${totalLossColorClass}">${totalLossSign}${fmtNum(Math.abs(totalLoss))}</div>
                    <div class="rekon-pipe-output-pct ${totalLossColorClass}">${totalLossSign}${fmtPct(Math.abs(totalLossPct))}</div>
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
            `<div class="d-flex flex-wrap align-items-stretch justify-content-center">${boxes}${lossBox}</div>${legend}`;
    }

    // ========== renderLossSteps dengan tanda/warna dari backend ==========
    function renderLossSteps(rows) {
        // const tbody = document.querySelector('#table-loss-steps tbody');
        // tbody.innerHTML = rows.map(r => {
        //     const loss = Number(r.loss_pcs || 0);
        //     const lossPct = Number(r.loss_pct || 0);

        //     let lossClass = 'loss-zero';
        //     let sign = '';
        //     if (loss < 0) {
        //         lossClass = 'loss-negative';
        //         sign = '-';
        //     } else if (loss > 0) {
        //         lossClass = 'loss-positive';
        //     } else {
        //         lossClass = 'loss-zero';
        //     }

        //     return `
        //         <tr>
        //             <td>${r.process}</td>
        //             <td class="right">${fmtNum(r.input)}</td>
        //             <td class="right">${fmtNum(r.output)}</td>
        //             <td class="right ${lossClass}">${sign}${fmtNum(Math.abs(loss))}</td>
        //             <td class="right ${lossClass}">${sign}${fmtPct(Math.abs(lossPct))}</td>
        //         </tr>
        //     `;
        // }).join('');
    }

    function renderDetail(rows) {
        // const tbody = document.querySelector('#table-rekon-detail tbody');
        // tbody.innerHTML = rows.map(r => `
        //     <tr>
        //         <td>${r.no_po ?? '-'}</td>
        //         <td>${r.jenis_po ?? '-'}</td>
        //         <td>${formatTanggalIndonesia(r.tgl_po)}</td>
        //         <td>${formatTanggalIndonesia(r.tgl_pengiriman)}</td>
        //         <td>${r.supplier_name ?? '-'}</td>
        //         <td>${r.barang_code ?? '-'}</td>
        //         <td>${r.barang_name ?? '-'}</td>
        //         <td>${r.satuan_order ?? '-'}</td>
        //         <td class="right">${fmtNum(r.jumlah_order)}</td>
        //         <td class="right">${fmtNum(r.jumlah_doc)}</td>
        //         <td class="right">${fmtNum(r.out_req)}</td>
        //         <td class="right">${fmtNum(r.out_prod)}</td>
        //         <td class="right">${fmtNum(r.sisa)}</td>
        //         <td class="right">${fmtNum(r.saldo_wip)}</td>
        //         <td class="right">${fmtNum(r.out_doc)}</td>
        //         <td class="right">${fmtNum(r.saldo_gudang)}</td>
        //         <td class="right">${fmtNum(r.harga_total)}</td>
        //     </tr>
        // `).join('');

        // if (dtDetail) dtDetail.destroy();
        // dtDetail = $('#table-rekon-detail').DataTable({
        //     pageLength: 10,
        //     order: [],
        //     autoWidth: false,
        //     width: '100%',
        //     scrollY: '400px',
        //     scrollCollapse: true,
        //     fixedHeader: true,
        // });
    }

    // ========== renderShipmentByDate ==========
    function renderShipmentByDate(shipmentDetail) {
        const ctx = document.getElementById('chart-shipment-by-date');
        if (!ctx) return;

        // Aggregate by date
        const map = {};
        (shipmentDetail || []).forEach(row => {
            const date = row.tgl_bukti ? row.tgl_bukti.split(' ')[0] : null;
            if (!date) return;
            const qty = parseFloat(row.jumlah_barang) || 0;
            map[date] = (map[date] || 0) + qty;
        });

        const sortedDates = Object.keys(map).sort();
        const labels = sortedDates.map(d => formatTanggalIndonesia(d)); // tampilan pendek
        const data = sortedDates.map(d => map[d]);

        // Tampilkan hanya jika ada data
        if (chartShipmentByDate) chartShipmentByDate.destroy();

        if (data.length === 0) {
            // Tampilkan pesan kosong
            ctx.parentElement.innerHTML = `
                <div class="text-center text-muted small" style="height:100%;display:flex;align-items:center;justify-content:center;">
                    <span>Tidak ada data shipment untuk filter ini</span>
                </div>
            `;
            return;
        }

        // Re-create canvas karena kita mungkin menghapus konten
        // Lebih baik kita tidak menghapus canvas, kita tetap pakai canvas
        // Tapi jika kita menampilkan pesan di parent, kita harus mengembalikan canvas.
        // Sederhananya: kita hanya tampilkan chart jika ada data.
        // Jika tidak ada, kita kosongkan dan tampilkan pesan.
        // Tapi karena kita pakai canvas, kita bisa buat chart tetap dengan data kosong.
        // Namun lebih baik kita sembunyikan canvas dan tampilkan pesan.
        // Tapi kita akan buat chart tetap, dengan data kosong, chart akan kosong.
        // Untuk pengalaman lebih baik, kita tampilkan pesan di atas canvas.
        // Kita akan buat chart dengan data, jika data kosong, tampilkan pesan di atas canvas.
        // Cara sederhana: jika data kosong, kita kosongkan div parent dan isi dengan teks.
        // Tapi kita ingin canvas tetap ada untuk di-render ulang.
        // Kita akan buat chart dengan data, jika data kosong, kita tetap buat chart kosong.
        // Namun lebih baik kita tampilkan pesan di dalam card body.
        // Kita akan gunakan pendekatan: jika data ada, render chart; jika tidak, tampilkan pesan.

        // Kita akan render chart selalu, tetapi dengan data kosong.
        // Tapi agar tidak error, kita buat dataset dengan data kosong.
        // Untuk value labels, kita perlu handle case data kosong.

        // Tetapi cara yang lebih bersih: kita tidak buat chart jika data kosong, kita tampilkan pesan.
        // Kita akan hapus canvas dan ganti dengan teks, tapi kita akan simpan canvas sebagai referensi.
        // Agar lebih sederhana, kita render chart dengan data kosong dan sembunyikan label.

        chartShipmentByDate = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Shipment (Pcs)',
                    data: data,
                    backgroundColor: '#1f6f8b', // biru
                    borderColor: '#1f6f8b',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) => `Total: ${fmtNum(c.parsed.y)}`
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 20
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => fmtNum(v)
                        }
                    }
                }
            },
            plugins: [{
                id: 'shipmentValueLabels',
                afterDraw(chart) {
                    const { ctx, data, chartArea: { top, bottom, left, right } } = chart;
                    ctx.save();
                    ctx.font = 'bold 10px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillStyle = '#2e3a4b';
                    const meta = chart.getDatasetMeta(0);
                    if (!meta || !meta.data) return;
                    meta.data.forEach((bar, i) => {
                        const value = data.datasets[0].data[i];
                        if (value === null || value === undefined || value === 0) return;
                        const label = fmtNum(value);
                        let x = bar.x;
                        let y = bar.y - 5;
                        let baseline = 'bottom';
                        if (bar.y - top < 10) {
                            y = bar.y + 10;
                            baseline = 'top';
                        }
                        ctx.textBaseline = baseline;
                        if (x > left && x < right && y > top - 20 && y < bottom) {
                            ctx.fillText(label, x, y);
                        }
                    });
                    ctx.restore();
                }
            }]
        });
    }

    function renderAll(json) {
        renderHeader(json.header);
        renderKpi(json.summary);
        renderFabricQty(json.fabricQty);
        renderFabricUsage(json.fabricUsage);
        renderMaterialAchievement(json.materialAchievement);
        renderProductionResult(json.productionPipeline, json.productionResultByMaterial, json.pipelineLossSteps);
        renderLossSteps(json.pipelineLossSteps);
        renderDetail(json.detail);

        // Shipment by Date
        renderShipmentByDate(json.shipmentDetail);

        renderShipCalDetailTable(json.shipmentDetail);

        if (Array.isArray(json.ocfOptions)) {
            populateSelect($(fOcf), json.ocfOptions);
        }
    }

    // ================= SHIPMENT DATE: kalender =================
    const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const today = new Date();

    const shipCalState = {
        year: today.getFullYear(),
        month: today.getMonth() + 1,
        requestSeq: 0,
    };

    const shipCalLabel = document.getElementById('ship-cal-label');
    // const shipCalBody  = document.querySelector('#mon-ship-calendar tbody');
    const shipCalPrev  = document.getElementById('ship-cal-prev');
    const shipCalNext  = document.getElementById('ship-cal-next');
    let dtShipCalDetail;

    function pad2(n){ return String(n).padStart(2, '0'); }
    function toIsoDate(y, m, d){ return `${y}-${pad2(m)}-${pad2(d)}`; }

    function buildShipCalendarQuery(extra) {
        const params = buildQueryParams(currentFilters());
        Object.entries(extra || {}).forEach(([k, v]) => params.append(k, v));
        return params;
    }

    function buildQueryParams(filters) {
        const params = new URLSearchParams();
        Object.entries(filters || {}).forEach(([k, v]) => { if (v) params.append(k, v); });
        return params;
    }

    function setShipCalNavDisabled(disabled) {
        if (shipCalPrev) shipCalPrev.disabled = disabled;
        if (shipCalNext) shipCalNext.disabled = disabled;
    }

    function renderShipCalendarGrid(year, month, dayMap) {
        if (!shipCalLabel || !shipCalBody) return;
        shipCalLabel.textContent = `${monthNames[month - 1]} ${year}`;

        const firstDow = new Date(year, month - 1, 1).getDay();
        const daysInMonth = new Date(year, month, 0).getDate();

        let cells = [];
        for (let i = 0; i < firstDow; i++) cells.push(null);
        for (let d = 1; d <= daysInMonth; d++) cells.push(d);
        while (cells.length % 7 !== 0) cells.push(null);

        let html = '';
        for (let w = 0; w < cells.length / 7; w++) {
            html += '<tr>';
            for (let c = 0; c < 7; c++) {
                const d = cells[w * 7 + c];
                if (!d) { html += '<td class="bg-light"></td>'; continue; }

                const iso = toIsoDate(year, month, d);
                const info = dayMap[iso];
                const isToday = iso === toIsoDate(today.getFullYear(), today.getMonth() + 1, today.getDate());

                let cls = 'text-center';
                if (info) cls += ' bg-info text-white';
                if (isToday) cls += ' font-weight-bold';

                html += `<td class="${cls}" style="vertical-align:middle;" title="${info ? `${info.jumlah_doc} dokumen` : ''}">
                    <div>${d}</div>
                    ${info ? `<span class="badge badge-pill badge-light" style="font-size:.6rem;">${info.jumlah_doc}</span>` : ''}
                </td>`;
            }
            html += '</tr>';
        }
        shipCalBody.innerHTML = html;
    }

    function loadShipCalendarMonth(year, month) {
        if (!calendarUrl) return;

        shipCalState.year = year;
        shipCalState.month = month;

        const seq = ++shipCalState.requestSeq;
        setShipCalNavDisabled(true);

        const params = buildShipCalendarQuery({ year, month });
        fetch(`${calendarUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(json => {
                if (seq !== shipCalState.requestSeq) return;
                const dayMap = {};
                (json.days || []).forEach(row => { dayMap[row.tanggal] = row; });
                renderShipCalendarGrid(year, month, dayMap);
            })
            .catch(() => {
                if (seq !== shipCalState.requestSeq) return;
                renderShipCalendarGrid(year, month, {});
            })
            .finally(() => {
                if (seq === shipCalState.requestSeq) setShipCalNavDisabled(false);
            });
    }

    function renderShipCalDetailTable(rows) {
        if (dtShipCalDetail) { dtShipCalDetail.destroy(); dtShipCalDetail = null; }

        dtShipCalDetail = $('#table-ship-cal-detail').DataTable({
            data: rows || [],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            autoWidth: false,
            width: '100%',
            scrollY: '400px',
            scrollCollapse: true,
            fixedHeader: true,
            columns: [
                { data: 'tgl_bukti', defaultContent: '', render: v => formatTanggalIndonesia(v) },
                { data: 'uraian', defaultContent: '' },
                { data: 'no_bukti', defaultContent: '' },
                { data: 'jenis_doc', defaultContent: '' },
                { data: 'jenis_ps', defaultContent: '' },
                { data: 'supplier_name', defaultContent: '' },
                { data: 'barang_name', defaultContent: '' },
                { data: 'jumlah_barang', className: 'right', render: v => fmtNum(v) },
            ]
        });
    }

    shipCalPrev?.addEventListener('click', () => {
        let { year, month } = shipCalState;
        month--;
        if (month < 1) { month = 12; year--; }
        loadShipCalendarMonth(year, month);
    });
    shipCalNext?.addEventListener('click', () => {
        let { year, month } = shipCalState;
        month++;
        if (month > 12) { month = 1; year++; }
        loadShipCalendarMonth(year, month);
    });

    function refreshNegaraOptions() {
        if (!negaraOptionsUrl) return;

        const filters = currentFilters();
        const params = buildQueryParams(filters);
        fetch(`${negaraOptionsUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                const current = fNegara.value;
                const options = data.map(n => `<option value="${n.negara_code}">${n.negara_name}</option>`);
                fNegara.innerHTML = `<option value=""></option>` + options.join('');
                if (data.some(n => n.negara_code === current)) {
                    fNegara.value = current;
                } else {
                    fNegara.value = '';
                }
                $(fNegara).trigger('change');
            })
            .catch(() => {});
    }

    function currentFilters() {
        return {
            uraian: fCpo.value,
            brand: fBuyer.value,
            style: fStyle.value,
            negara: fNegara.value,
            ocf: fOcf.value,
        };
    }

    function refresh() {
        const { uraian, brand, style, negara, ocf } = currentFilters();

        if (!uraian && !brand && !style && !negara && !ocf) {
            if (widgets) widgets.style.display = 'none';
            if (emptyNotice) emptyNotice.style.display = '';
            document.getElementById('rekon-last-updated').textContent = '-';
            return;
        }

        if (emptyNotice) emptyNotice.style.display = 'none';
        if (widgets) widgets.style.display = '';

        Swal.fire({
            title: 'Memuat data...',
            text: 'Mengambil data dashboard untuk filter terpilih, mohon tunggu.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
        });

        const url = buildUrl(endpoint, { uraian, brand, style, negara, ocf });
        fetch(url)
            .then(r => r.json())
            .then(json => {
                renderAll(json);
                Swal.close();
            })
            .catch(() => {
                Swal.close();
                Swal.fire('Gagal', 'Tidak bisa memuat data dashboard.', 'error');
            });

        if (calendarUrl) {
            loadShipCalendarMonth(shipCalState.year, shipCalState.month);
        }

        refreshNegaraOptions();
    }

    function runSyncAll() {
        const steps = [
            { url: syncMsNegaraUrl, label: 'Sync Master Negara' },
            { url: syncMsSupplierUrl, label: 'Sync Master Supplier' },
            { url: syncMsBarangUrl, label: 'Sync Master Barang' },
            { url: syncRekonUrl, label: 'Sync Rekonsiliasi' },
            { url: syncProdlineUrl, label: 'Sync Production Line' },
            { url: syncShipmentUrl, label: 'Sync Shipment' },
            { url: syncWorkOrderUrl, label: 'Sync Work Order' },
        ].filter(s => !!s.url);

        Swal.fire({
            title: 'Sync semua data?',
            html: `Proses berikut dijalankan berurutan dan bisa memakan waktu cukup lama:<br><small>${steps.map(s => s.label).join(' &rarr; ')}</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, sync semua',
        }).then((res) => {
            if (!res.isConfirmed) return;
            runSyncStep(steps, 0, []);
        });
    }

    function runSyncStep(steps, index, results) {
        if (index >= steps.length) {
            Swal.close();
            setTimeout(() => {
                const failed = results.filter(r => !r.success);
                if (failed.length === 0) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Selesai',
                        text: 'Semua proses sync berhasil dijalankan.',
                        allowOutsideClick: true,
                        allowEscapeKey: true,
                    }).then(refresh);
                } else {
                    const failedLabels = failed.map(r => r.label).join(', ');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Selesai dengan error',
                        text: `Berhasil ${results.length - failed.length}/${results.length} proses. Gagal: ${failedLabels}.`,
                        allowOutsideClick: true,
                        allowEscapeKey: true,
                    }).then(refresh);
                }
            }, 200);
            return;
        }

        const step = steps[index];
        Swal.close();

        const startedAt = Date.now();
        let elapsedTimer = null;

        Swal.fire({
            title: `${step.label}... (${index + 1}/${steps.length})`,
            html: 'Menarik data terbaru dari smartit, mohon tunggu.<br><small id="sync-elapsed" class="text-muted">0 detik berjalan</small>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
                elapsedTimer = setInterval(() => {
                    const secs = Math.round((Date.now() - startedAt) / 1000);
                    const el = document.getElementById('sync-elapsed');
                    if (el) {
                        el.textContent = secs > 30
                            ? `${secs} detik berjalan -- proses smartit memang bisa lama, mohon tunggu`
                            : `${secs} detik berjalan`;
                    }
                }, 1000);
            },
            willClose: () => {
                if (elapsedTimer) clearInterval(elapsedTimer);
            },
        });

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15 * 60 * 1000);

        fetch(step.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            signal: controller.signal,
        })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(json => {
                results.push({ label: step.label, success: !!json.success, message: json.message || json.output });
                runSyncStep(steps, index + 1, results);
            })
            .catch((err) => {
                clearTimeout(timeoutId);
                const timedOut = err && err.name === 'AbortError';
                results.push({
                    label: step.label,
                    success: false,
                    message: timedOut ? 'Timeout -- proses lebih dari 15 menit.' : 'Request gagal dikirim.',
                });
                runSyncStep(steps, index + 1, results);
            });
    }

    // ===== EVENT LISTENER =====
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
    $(fOcf).on('select2:select select2:clear', refresh);
    $(fNegara).on('select2:select select2:clear', refresh);

    if (btnSyncAll) btnSyncAll.addEventListener('click', runSyncAll);

    refresh();
})();
</script>

</body>
</html>