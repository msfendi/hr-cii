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
                 data-sync-subkon-url="{{ route('monitoring.rekonsiliasi.sync-subkon') }}"
                 data-import-stage-remark-url="{{ route('monitoring.rekonsiliasi.stage-remark.import') }}"
                 data-import-prod-qc-url="{{ route('monitoring.rekonsiliasi.prod-qc.import') }}"
                 data-delete-stage-remark-url="{{ route('monitoring.rekonsiliasi.stage-remark.destroy', ['id' => '__ID__']) }}"
                 @canRoute('monitoring.rekonsiliasi.stage-remark.destroy')
                 data-can-delete-stage-remark="1"
                 @endcanRoute>

                {{-- ================= HEADER BAR ================= --}}
                <div class="rekon-hero shadow mb-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="rekon-hero-title">
                            <i class="fas fa-balance-scale mr-2"></i> DASHBOARD RECONCILIATION
                        </div>
                        <div class="d-flex align-items-center flex-wrap" style="gap:16px">
                            <div class="rekon-search input-group input-group-sm">
                                {{-- Kelima select di bawah ini BOLAK-BALIK saling menyaring: opsi
                                     masing-masing sudah di-cascade dari server berdasarkan filter lain
                                     yang aktif (lihat MonitoringRekonsiliasiService::cascadedFilterOptions()),
                                     dan akan di-refresh lagi setiap kali salah satu select berubah
                                     (lihat renderAll() di script bawah). --}}
                                <select id="f-buyer" class="form-control select2-filter" style="min-width:150px" data-placeholder="Cari Buyer...">
                                    <option value=""></option>
                                    @foreach($buyerOptions as $b)
                                        <option value="{{ $b }}" @selected(($filters['brand'] ?? null) === $b)>{{ $b }}</option>
                                    @endforeach
                                </select>
                                <select id="f-style" class="form-control select2-filter" style="min-width:150px" data-placeholder="Cari Style...">
                                    <option value=""></option>
                                    @foreach($styleOptions as $s)
                                        <option value="{{ $s }}" @selected(($filters['style'] ?? null) === $s)>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <select id="f-ocf" class="form-control select2-filter" style="min-width:180px" data-placeholder="Cari OCF...">
                                    <option value=""></option>
                                    @foreach($ocfOptions as $o)
                                        <option value="{{ $o }}" @selected(($filters['ocf'] ?? null) === $o)>{{ $o }}</option>
                                    @endforeach
                                </select>
                                <select id="f-sub-ref" class="form-control select2-filter" style="min-width:180px" data-placeholder="Cari Sub Ref...">
                                    <option value=""></option>
                                    @foreach($subRefOptions as $sr)
                                        <option value="{{ $sr }}" @selected(($filters['sub_ref'] ?? null) === $sr)>{{ $sr }}</option>
                                    @endforeach
                                </select>
                                <!-- <select id="f-cpo" class="form-control select2-filter" style="min-width:220px" data-placeholder="Cari CPO...">
                                    <option value=""></option>
                                    @foreach($cpoOptions as $v)
                                        <option value="{{ $v }}" @selected(($filters['uraian'] ?? null) === $v)>{{ $v }}</option>
                                    @endforeach
                                </select> -->
                                <select id="f-negara" class="form-control select2-filter" style="min-width:170px" data-placeholder="Semua Negara...">
                                    <option value=""></option>
                                    @foreach($negaraOptions as $n)
                                        <option value="{{ $n->negara_code }}" @selected(($filters['negara'] ?? null) === $n->negara_code)>{{ $n->negara_name }}</option>
                                    @endforeach
                                </select>
                                {{-- Tombol reset EKSPLISIT: tidak bergantung pada ikon "x" bawaan
                                     select2 (klik "x" terbukti tidak selalu benar-benar mengosongkan
                                     value <select> di DOM setelah option-nya di-rebuild lewat
                                     populateSelect()). Tombol ini memaksa kelima select ke null lewat
                                     API resmi select2 (.val(null).trigger('change')) lalu memanggil
                                     refresh() langsung, supaya clear filter selalu deterministik. --}}
                                <button type="button" id="btn-reset-filter" class="btn btn-outline-light btn-sm" title="Reset semua filter">
                                    <i class="fas fa-times"></i> Reset Filter
                                </button>
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
                                @canRoute('monitoring.rekonsiliasi.sync-subkon')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @if($canSyncAny)
                                    <button id="btn-sync-all" type="button" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-sync-alt fa-sm"></i> Sync All Data
                                    </button>
                                @endif

                                {{-- Import Stage Remark (mon_stage_remarks) & Prod QC (mon_prod_qc) --
                                     sengaja ditaruh 1 kelompok (di dalam div.d-flex yang sama) dengan
                                     tombol "Sync All Data" di atas, sesuai permintaan. --}}
                                @php $canImportAny = false; @endphp
                                @canRoute('monitoring.rekonsiliasi.stage-remark.import')
                                    @php $canImportAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.prod-qc.import')
                                    @php $canImportAny = true; @endphp
                                @endcanRoute
                                @if($canImportAny)
                                    <div class="dropdown">
                                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button"
                                                id="btn-import-menu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-file-import fa-sm"></i> Import Data
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="btn-import-menu">
                                            @canRoute('monitoring.rekonsiliasi.stage-remark.import')
                                                <h6 class="dropdown-header">Stage Remark</h6>
                                                <a class="dropdown-item" href="{{ route('monitoring.rekonsiliasi.stage-remark.template') }}">
                                                    <i class="fas fa-download fa-sm mr-1"></i> Download Template
                                                </a>
                                                <a class="dropdown-item" href="#" id="btn-import-stage-remark">
                                                    <i class="fas fa-upload fa-sm mr-1"></i> Import Excel
                                                </a>
                                            @endcanRoute
                                            @canRoute('monitoring.rekonsiliasi.prod-qc.import')
                                                <div class="dropdown-divider"></div>
                                                <h6 class="dropdown-header">Prod QC</h6>
                                                <a class="dropdown-item" href="{{ route('monitoring.rekonsiliasi.prod-qc.template') }}">
                                                    <i class="fas fa-download fa-sm mr-1"></i> Download Template
                                                </a>
                                                <a class="dropdown-item" href="#" id="btn-import-prod-qc">
                                                    <i class="fas fa-upload fa-sm mr-1"></i> Import Excel
                                                </a>
                                            @endcanRoute
                                        </div>
                                    </div>
                                    <input type="file" id="file-stage-remark" accept=".xlsx,.xls" style="display:none">
                                    <input type="file" id="file-prod-qc" accept=".xlsx,.xls" style="display:none">
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
                        <span id="hdr-sub-ref-wrap" style="display:none">
                            &nbsp;|&nbsp; SUB REF <span id="hdr-sub-ref">-</span>
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
                            <strong>CPO</strong>, <strong>OCF</strong>, <strong>Sub Ref</strong>, atau <strong>Negara</strong>
                            pada kolom di atas untuk menampilkan data dashboard. Kalau cuma Buyer dan/atau Style yang dipilih
                            (tanpa CPO spesifik), data dari semua CPO yang cocok akan digabung. <strong>OCF</strong>,
                            <strong>Sub Ref</strong>, dan <strong>Negara</strong> bisa dipilih sendirian (menggabungkan
                            semua CPO yang cocok) atau dikombinasikan dengan Buyer/Style/OCF/Sub Ref/CPO untuk
                            mempersempit hasilnya. Data tidak dimuat otomatis saat halaman dibuka karena cukup berat
                            kalau ditarik untuk semua CPO sekaligus.
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

                    <div class="col-lg-4 mb-4">
                        {{-- ================= PLAN VS ACTUAL SHIPMENT REPORT CHART ================= --}}
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
                        <!-- <div class="card shadow h-100 rekon-fabric-card">
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
                                <div class="rekon-usage-consumption">
                                    <span>Consumption :</span>
                                    <span class="rekon-usage-consumption-value" id="usage-consumption">0</span>
                                    <span>Kgm</span>
                                </div>
                            </div>
                        </div> -->
                    </div>
                </div>

                {{-- ================= MATERIAL ACHIEVEMENT & SHIPMENT BY DATE ================= --}}
                <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">MATERIAL ACHIEVEMENT</h6>
                            </div>
                            <div class="card-body">
                                <div class="rekon-ma-formula mb-3">
                                </div>
                                <div class="row">
                                    <div class="col-lg-6 mb-4 mb-lg-0">
                                        <h6 class="text-center text-muted text-uppercase small font-weight-bold mb-2">Sewing Trim</h6>
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
    .rekon-pipe-box.theme-blue    .rekon-pipe-header { background: #2e75b6; }

    .rekon-pipe-body { padding: .55rem .6rem .65rem; }

    .rekon-pipe-output   { font-size: 1.1rem; font-weight: 800; }
    .rekon-pipe-output-pct { font-size: .78rem; font-weight: 700; margin-top: .05rem; }
    .theme-green   .rekon-pipe-output, .theme-green   .rekon-pipe-output-pct { color: #1e824c; }
    .theme-navy    .rekon-pipe-output, .theme-navy    .rekon-pipe-output-pct { color: #1f3864; }
    .theme-neutral .rekon-pipe-output, .theme-neutral .rekon-pipe-output-pct { color: #2e3a4b; }
    .theme-loss    .rekon-pipe-output, .theme-loss    .rekon-pipe-output-pct { color: #c0392b; }
    .theme-blue    .rekon-pipe-output, .theme-blue    .rekon-pipe-output-pct { color: #2e75b6; }

    /* Grup visual "Work In Process (Chutex)" & "Sabkon Process" --
       membungkus beberapa rekon-pipe-box sekaligus dengan border + judul
       supaya kedua flow (internal vs subkon pabrik luar) terlihat sebagai
       2 jalur terpisah, bukan 1 rantai linear tunggal. */
    .rekon-pipe-group {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        border: 1px dashed #d8dae4;
        border-radius: .6rem;
        padding: 1.5rem .75rem .5rem;
        margin: 0 .75rem .5rem;
        position: relative;
        background: #fbfbfe;
    }
    .rekon-pipe-group.theme-blue { border-color: #b8d3ec; background: #f5f9fd; }
    .rekon-pipe-group-title {
        position: absolute;
        top: -.6rem;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        padding: 0 .6rem;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: #5a5c69;
        white-space: nowrap;
    }
    .rekon-pipe-group.theme-blue .rekon-pipe-group-title { color: #2e75b6; }

    .rekon-pipe-divider { border: 0; border-top: 1px dashed #e3e6f0; margin: .45rem 0; }

    /* Warna loss sesuai permintaan: negatif = merah, positif = hijau */
    .rekon-pipe-loss-value.loss-negative,
    .rekon-pipe-loss-pct.loss-negative {
        color: #c0392b !important; /* merah */
        font-weight: bold;
    }
    .rekon-pipe-loss-value.loss-positive,
    .rekon-pipe-loss-pct.loss-positive {
        color: #1cc88a !important; /* hijau */
        font-weight: bold;
    }
    .rekon-pipe-loss-value.loss-zero,
    .rekon-pipe-loss-pct.loss-zero {
        color: #5a5c69 !important; /* abu-abu */
        font-weight: bold;
    }

    .rekon-pipe-total .rekon-pipe-body { padding-top: .7rem; }
    /* Box mandiri (tidak dihubungkan panah): Total Process Loss (menempel
       di ekor grup "Work In Process (Chutex)") & Balance Garment Stock
       (menempel di ekor Shipment (Total)) -- butuh jarak eksplisit karena
       tidak ada arrow di antaranya seperti box lain. */
    .rekon-pipe-total { margin-left: .75rem; }

    /* Remark (mon_stage_remarks), ditampilkan di bawah persentase loss
       pada tiap stage box (Cutting/Sewing/QC/Packing/Warehouse). */
    .rekon-pipe-remarks {
        margin-top: .4rem;
        padding-top: .35rem;
        border-top: 1px dashed #e3e6f0;
        text-align: left;
    }
    .rekon-pipe-remark-item {
        font-size: .68rem;
        color: #5a5c69;
        line-height: 1.3;
        margin-bottom: .2rem;
        word-break: break-word;
    }
    .rekon-pipe-remark-item:last-child { margin-bottom: 0; }
    .rekon-pipe-remark-item .fa-comment-dots { color: #4e73df; }

    /* Ikon hapus kecil di tiap baris remark (mon_stage_remarks). Hanya
       dirender di JS kalau data-can-delete-stage-remark="1" (lihat
       canDeleteStageRemark), sesuai permission user ke route
       monitoring.rekonsiliasi.stage-remark.destroy. */
    .rekon-pipe-remark-item { display: flex; align-items: flex-start; justify-content: space-between; gap: .3rem; }
    .rekon-pipe-remark-text { flex: 1 1 auto; }
    .rekon-pipe-remark-delete {
        flex: 0 0 auto;
        cursor: pointer;
        color: #b7b9c8;
        font-size: .68rem;
        margin-top: .15rem;
    }
    .rekon-pipe-remark-delete:hover { color: #e74a3b; }

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
    .rekon-pipe-dot.dot-sabkon { background: #2e75b6; }
    .rekon-pipe-dot.dot-pct    { background: #b7b9c8; }

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
    const syncSubkonUrl = app.dataset.syncSubkonUrl;
    const importStageRemarkUrl = app.dataset.importStageRemarkUrl;
    const importProdQcUrl = app.dataset.importProdQcUrl;
    const deleteStageRemarkUrl = app.dataset.deleteStageRemarkUrl;
    const canDeleteStageRemark = app.dataset.canDeleteStageRemark === '1';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const fBuyer = document.getElementById('f-buyer');
    const fStyle = document.getElementById('f-style');
    const fOcf = document.getElementById('f-ocf');
    const fSubRef = document.getElementById('f-sub-ref');
    // const fCpo = document.getElementById('f-cpo');
    const fNegara = document.getElementById('f-negara');
    const emptyNotice = document.getElementById('rekon-empty-notice');
    const widgets = document.getElementById('rekon-widgets');
    const btnSyncAll = document.getElementById('btn-sync-all');

    $('.select2-filter').each(function () {
        $(this).select2({ width: '100%', placeholder: $(this).data('placeholder') || '', allowClear: true });
    });

    const fmtNum = (v) => new Intl.NumberFormat('id-ID').format(Number(v || 0));

    // Escape teks bebas (mis. remark dari mon_stage_remarks) sebelum
    // disisipkan lewat innerHTML, supaya aman dari HTML/markup liar.
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[ch]));
    }

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



    /**
     * Sama seperti populateSelect(), tapi untuk dropdown Negara yang
     * opsinya berupa objek {negara_code, negara_name} (bukan string polos).
     */
    function populateNegaraSelect($el, rows) {
        const current = $el.val();
        const codes = rows.map(r => r.negara_code);
        $el.empty().append('<option value=""></option>');
        rows.forEach(r => $el.append(new Option(r.negara_name, r.negara_code, false, false)));
        $el.val(codes.includes(current) ? current : '').trigger('change');
    }

    /**
     * Update KELIMA dropdown filter (Buyer/Style/OCF/CPO/Negara) sekaligus
     * dari hasil cascade server (lihat MonitoringRekonsiliasiService::
     * cascadedFilterOptions(), dikirim oleh controller di key buyerOptions/
     * styleOptions/cpoOptions/ocfOptions/negaraOptions pada tiap response
     * endpoint data()). Ini yang membuat filter BOLAK-BALIK: pilih OCF ->
     * Buyer/Style/CPO/Negara ikut menyaring; pilih Style -> Buyer/CPO/OCF/
     * Negara ikut menyaring; dst -- pilihan yang masih valid tetap
     * dipertahankan, yang sudah tidak valid otomatis ke-clear karena tidak
     * ada lagi di daftar opsi baru.
     */
    function applyCascadedFilterOptions(json) {
        if (Array.isArray(json.buyerOptions)) populateSelect($(fBuyer), json.buyerOptions);
        if (Array.isArray(json.styleOptions)) populateSelect($(fStyle), json.styleOptions);
        if (Array.isArray(json.ocfOptions)) populateSelect($(fOcf), json.ocfOptions);
        if (Array.isArray(json.subRefOptions)) populateSelect($(fSubRef), json.subRefOptions);
        // if (Array.isArray(json.cpoOptions)) populateSelect($(fCpo), json.cpoOptions);
        if (Array.isArray(json.negaraOptions)) populateNegaraSelect($(fNegara), json.negaraOptions);
    }

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

        const subRefWrap = document.getElementById('hdr-sub-ref-wrap');
        if (fSubRef.value) {
            document.getElementById('hdr-sub-ref').textContent = fSubRef.value;
            subRefWrap.style.display = '';
        } else {
            subRefWrap.style.display = 'none';
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
    // document.getElementById('usage-for-gmt').textContent = fmtPct(usagePct);        // <-- ubah
    // document.getElementById('usage-scrap-qty').textContent = fmtPct(scrapPct);      // <-- ubah
    // document.getElementById('usage-consumption').textContent = '100,0%';            // <-- ubah (atau fmtPct(100))

    // Ubah judul box (jika belum diubah di HTML)
    // const titleEl = document.querySelector('.rekon-usage-box-title');
    // if (titleEl) titleEl.textContent = 'Qty Usage (%)';

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
    // Warna baru sesuai palet FABRIC USAGE PERCENTAGE
    // qtyKey menunjuk ke field qty mentah (bukan persentase) yang dikirim
    // service (need_qty/order_qty/dst) supaya tooltip bisa menampilkan qty
    // asli di samping persentase saat bar di-hover.
    const mk = (key, color, label, qtyKey) => ({ label, data: rows.map(r => r[key]), backgroundColor: color, qtyKey });

    if (meta.chart) meta.chart.destroy();
    meta.chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                mk('need_pct', '#6c757d', 'NEED%', 'need_qty'),
                mk('order_pct', '#1f6f8b', 'ORDER%', 'order_qty'),
                mk('received_pct', '#e07b39', 'RECEIVED%', 'received_qty'),
                mk('out_prod_pct', '#44af69', 'OUT PROD%', 'out_prod_qty'),
                mk('stock_pct', '#f5a623', 'STOCK%', 'stock_qty'),
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
                        // Title tooltip pakai nama asli item (sebelum
                        // dianonimkan jadi "Fabric A/B/..." di sumbu-X),
                        // jatuh balik ke barang_name kalau real_barang_name
                        // tidak ada (mis. grup Aksesoris/Packing yang
                        // memang tidak dianonimkan).
                        title: (items) => {
                            if (!items.length) return '';
                            const r = rows[items[0].dataIndex];
                            return r ? (r.real_barang_name || r.barang_name) : '';
                        },
                        // Baris harga (mon_rekonsiliasis.harga_total) tampil
                        // tepat di bawah title, sebelum baris NEED%/ORDER%/dst.
                        afterTitle: (items) => {
                            if (!items.length) return '';
                            const r = rows[items[0].dataIndex];
                            if (!r || r.harga_total === undefined || r.harga_total === null) return '';
                            return 'Harga: Rp ' + fmtNum(r.harga_total);
                        },
                        // Baris qty ditambahkan setelah persentase, mengambil
                        // nilai qty mentah dari field yang ditunjuk qtyKey
                        // (mis. 'need_qty' untuk dataset NEED%).
                        label: (c) => {
                            const r = rows[c.dataIndex];
                            const qtyKey = c.dataset.qtyKey;
                            const qty = (r && qtyKey) ? r[qtyKey] : undefined;
                            const qtyStr = (qty === undefined || qty === null) ? '' : `  |  Qty: ${fmtNum(qty)}`;
                            return `${c.dataset.label}: ${c.parsed.y.toFixed(1).replace('.', ',')}%${qtyStr}`;
                        }
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
        { label: 'NEED%', desc: 'Kebutuhan (Work Order Request) -- patokan 100%', color: '#6c757d' },
        { label: 'ORDER%', desc: 'Jumlah Order ÷ Need', color: '#1f6f8b' },
        { label: 'RECEIVED%', desc: '(Jumlah Diterima - Out Doc) ÷ Need', color: '#e07b39' },
        { label: 'OUT PROD%', desc: 'Out Req (WIP) ÷ (Jumlah Diterima - Out Doc)', color: '#44af69' },
        { label: 'STOCK%', desc: 'Saldo Gudang ÷ (Jumlah Diterima - Out Doc)', color: '#f5a623' }
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
                // Simpan nama asli (sebelum dianonimkan) supaya tetap bisa
                // ditampilkan di tooltip saat di-hover -- label sumbu-X
                // (barang_name) tetap "Fabric A/B/..." seperti semula.
                return { ...r, real_barang_name: r.barang_name, barang_name: `Fabric ${letter}` };
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

        // Cari loss step lewat NAMA proses (bukan index array) -- lebih
        // aman karena sekarang ada 2 chain terpisah (Produksi Internal &
        // Sabkon) yang tidak lagi berurutan linear dalam 1 array.
        const findStep = (process) => lossSteps.find(s => s.process === process) || null;

        /**
         * Render daftar remark manual (mon_stage_remarks) di bawah 1 box
         * pipeline, tiap baris punya ikon hapus sendiri (bukan 1 ikon
         * pensil global seperti sebelumnya). Terima remarks sebagai array
         * of string (format lama) ATAU array of object {id, remark}
         * (format baru, wajib supaya tombol hapus bisa jalan -- kalau
         * masih string, ikon hapus disembunyikan karena tidak ada id-nya).
         */
        function renderRemarkList(remarks) {
            remarks = Array.isArray(remarks) ? remarks : [];
            if (!remarks.length) return '';

            const items = remarks.map(r => {
                const isObj = r && typeof r === 'object';
                const text = isObj ? (r.remark ?? r.text ?? '') : r;
                const remarkId = isObj ? (r.id ?? null) : null;
                const deleteBtn = (canDeleteStageRemark && remarkId !== null)
                    ? `<i class="fas fa-trash-alt rekon-pipe-remark-delete" title="Hapus remark" data-remark-id="${remarkId}"></i>`
                    : '';

                return `
                    <div class="rekon-pipe-remark-item">
                        <span class="rekon-pipe-remark-text"><i class="fas fa-comment-dots mr-1"></i>${escapeHtml(text)}</span>
                        ${deleteBtn}
                    </div>
                `;
            }).join('');

            return `<div class="rekon-pipe-remarks">${items}</div>`;
        }

        // Render 1 box stage. `pct` dipakai kalau step-nya tidak
        // menyediakan basis input/output sendiri (mis. Contract, atau
        // cabang Sabkon yang basisnya dihitung terhadap Kontrak, bukan
        // stage sebelumnya).
        function renderBox(label, value, theme, step, pct, remarks) {
            let lossValue = step ? Number(step.loss_pcs || 0) : null;
            let lossPct   = step ? Number(step.loss_pct || 0) : null;

            let outputPct = pct;
            if (outputPct === null && step && step.input > 0) {
                outputPct = (Number(step.output) / Number(step.input)) * 100;
            }

            let lossColorClass = 'loss-zero';
            let lossSign = '';
            if (lossValue !== null) {
                if (lossValue < 0) {
                    lossColorClass = 'loss-negative';
                    lossSign = '-';
                } else if (lossValue > 0) {
                    lossColorClass = 'loss-positive';
                    lossSign = '+';
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

            const remarksRow = renderRemarkList(remarks);

            return `
                <div class="rekon-pipe-box theme-${theme}">
                    <div class="rekon-pipe-header">${label}</div>
                    <div class="rekon-pipe-body">
                        <div class="rekon-pipe-output">${fmtNum(value)}</div>
                        <div class="rekon-pipe-output-pct">${outputPctDisplay}</div>
                        ${lossRow}
                        ${remarksRow}
                    </div>
                </div>
            `;
        }

        const arrow = `<div class="rekon-pipe-arrow"><i class="fas fa-arrow-right"></i></div>`;

        // ===== Total Contract =====
        const contractBox = renderBox('Total Contract', pipeline.contract, 'neutral', null, 100, []);

        // ===== Grup Work In Process (Chutex): Cutting → Sewing → QC → Packing → Warehouse =====
        // Urutan & datanya TIDAK berubah dari sebelumnya, cuma dibungkus
        // di dalam 1 grup visual (rekon-pipe-group).
        const internalProcess = {
            'Cutting':   'Contract → Cutting',
            'Sewing':    'Cutting → Sewing',
            'QC':        'Sewing → QC',
            'Packing':   'QC → Packing',
            'Warehouse': 'Packing → Warehouse',
        };
        const internalBoxes = (pipeline.departments || []).map(d => {
            const label = d.department_id ?? '-';
            const step = findStep(internalProcess[label] || '');
            return renderBox(label, d.jumlah, 'green', step, null, d.remarks);
        }).join(arrow);

        // ===== Grup Sabkon Process: Sabkon → Warehouse (Chutex) =====
        // Cabang terpisah dari flow internal, sumbernya mon_subkons
        // (di-scope lewat filter OCF -- lihat
        // MonitoringRekonsiliasiService::subkonSumByField()). Sabkon selalu
        // dianggap 100% tercapai, jadi persentasenya flat 100% dan loss
        // selalu 0 (nilai loss/loss% sudah 0 dari backend).
        const sabkonProcess = {
            'Sabkon':             'Sabkon Process',
            'Warehouse (Chutex)': 'Sabkon → Warehouse (Chutex)',
        };
        const sabkonBoxes = (pipeline.sabkon || []).map(d => {
            const label = d.department_id ?? '-';
            const step = findStep(sabkonProcess[label] || '');
            return renderBox(label, d.jumlah, 'blue', step, 100, d.remarks);
        }).join(arrow);

        // ===== Shipment (Total) =====
        // Basis sekarang Total Contract (bukan lagi output Cutting):
        //  - Shipment loss   = shipment − total contract
        //  - Shipment loss % = shipment / total contract
        const shipmentBox = renderBox('Shipment (Total)', pipeline.shipment, 'navy', findStep('Cutting → Shipment'), null, []);

        // ===== Balance Garment Stock (kotak baru di samping Shipment) =====
        // = (Warehouse [Work In Process] + Warehouse [Sabkon]) − Shipment (Total)
        const balanceGarmentStock = Number(pipeline.balance_garment_stock || 0);
        const balanceRemarks = Array.isArray(pipeline.balance_garment_stock_remarks) ? pipeline.balance_garment_stock_remarks : [];

        let balanceColorClass = 'loss-zero';
        let balanceSign = '';
        if (balanceGarmentStock < 0) {
            balanceColorClass = 'loss-negative';
            balanceSign = '-';
        } else if (balanceGarmentStock > 0) {
            balanceColorClass = 'loss-positive';
        }

        const balanceRemarksRow = renderRemarkList(balanceRemarks);

        const balanceBox = `
            <div class="rekon-pipe-box theme-neutral rekon-pipe-total">
                <div class="rekon-pipe-header">Balance Garment Stock</div>
                <div class="rekon-pipe-body">
                    <div class="rekon-pipe-output ${balanceColorClass}">${balanceSign}${fmtNum(Math.abs(balanceGarmentStock))}</div>
                    <div class="text-uppercase" style="font-size:.65rem;">PCS</div>
                    ${balanceRemarksRow}
                </div>
            </div>
        `;

        // ===== Total Process Loss =====
        // = loss sewing + loss qc + loss packing
        // %  = total process loss / total contract
        // Sekarang ditempatkan DI DALAM grup "Work In Process (Chutex)",
        // menempel setelah box Warehouse TANPA tanda panah pemisah.
        const totalLoss    = Number(pipeline.total_loss || 0);
        const totalLossPct = Number(pipeline.loss_pct || 0);

        let totalLossColorClass = 'loss-zero';
        let totalLossSign = '';
        if (totalLoss < 0) {
            totalLossColorClass = 'loss-negative';
            totalLossSign = '-';
        } else if (totalLoss > 0) {
            totalLossColorClass = 'loss-positive';
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

        // Digabung ke internalBoxes TANPA arrow di depannya (beda dengan
        // box lain dalam grup yang dipisah oleh `arrow`).
        const internalGroupHtml = internalBoxes + lossBox;

        const legend = `
            <div class="rekon-pipe-legend">
                <span><i class="rekon-pipe-dot dot-output"></i>Output Qty (PCS)</span>
                <span><i class="rekon-pipe-dot dot-loss"></i>Loss Qty (PCS)</span>
                <span><i class="rekon-pipe-dot dot-sabkon"></i>Sabkon Input/Output (PCS)</span>
                <span><i class="rekon-pipe-dot dot-pct"></i>Persentase terhadap Kontrak</span>
            </div>
        `;

        const html = `
            <div class="d-flex flex-wrap align-items-stretch justify-content-center">
                ${contractBox}
                <div class="rekon-pipe-group">
                    <div class="rekon-pipe-group-title">Work In Process (Chutex)</div>
                    <div class="d-flex flex-wrap align-items-stretch justify-content-center">${internalGroupHtml}</div>
                </div>
                <div class="rekon-pipe-group theme-blue">
                    <div class="rekon-pipe-group-title">Sabkon Process</div>
                    <div class="d-flex flex-wrap align-items-stretch justify-content-center">${sabkonBoxes}</div>
                </div>
                ${shipmentBox}
                ${balanceBox}
            </div>
            ${legend}
        `;

        document.getElementById('rekon-pipeline').innerHTML = html;
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

        // Plan vs Actual Shipment Report
        renderShipmentByDate(json.shipmentByDate);

        renderShipCalDetailTable(json.shipmentDetail);

        applyCascadedFilterOptions(json);
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

    function currentFilters() {
        return {
            brand: fBuyer.value,
            style: fStyle.value,
            ocf: fOcf.value,
            sub_ref: fSubRef.value,
            // uraian: fCpo.value,
            negara: fNegara.value,
        };
    }

    // Penjaga urutan request refresh(): tiap kali refresh() dipanggil
    // (mis. clear 2 dropdown berturut-turut dengan cepat: OCF lalu Uraian),
    // request AJAX sebelumnya yang masih berjalan di-ABORT dan responsnya
    // diabaikan kalau ternyata datang belakangan -- supaya response BASI
    // (dari kondisi filter sebelum di-clear) tidak menimpa hasil dari
    // filter state TERBARU. Tanpa ini, dua request yang selesai tidak
    // berurutan bisa membuat clear/cascade filter "tidak berpengaruh".
    let refreshSeq = 0;
    let refreshAbortController = null;

    function refresh() {
        const seq = ++refreshSeq;
        if (refreshAbortController) refreshAbortController.abort();
        refreshAbortController = new AbortController();

        const { uraian, brand, style, negara, ocf, sub_ref } = currentFilters();
        const hasAnyFilter = !!(uraian || brand || style || negara || ocf || sub_ref);

        if (!hasAnyFilter) {
            if (widgets) widgets.style.display = 'none';
            if (emptyNotice) emptyNotice.style.display = '';
            document.getElementById('rekon-last-updated').textContent = '-';
        } else {
            if (emptyNotice) emptyNotice.style.display = 'none';
            if (widgets) widgets.style.display = '';

            Swal.fire({
                title: 'Memuat data...',
                text: 'Mengambil data dashboard untuk filter terpilih, mohon tunggu.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
        }

        // Fetch TETAP dijalankan meski belum ada filter sama sekali --
        // payload widget-nya kosong (lihat MonitoringRekonsiliasiController::
        // emptyPayload()), tapi dropdown Buyer/Style/OCF/CPO/Negara tetap
        // dikirim balik supaya kelima select bisa direset/di-cascade ulang
        // dengan benar (bolak-balik) setiap kali salah satu filter berubah,
        // termasuk saat filter dikosongkan lagi.
        const url = buildUrl(endpoint, { uraian, brand, style, negara, ocf, sub_ref });
        fetch(url, { signal: refreshAbortController.signal })
            .then(r => r.json())
            .then(json => {
                // Response basi (sudah ada refresh() yang lebih baru dipanggil
                // setelah ini) -- abaikan, jangan render/populate apapun.
                if (seq !== refreshSeq) return;

                if (hasAnyFilter) {
                    renderAll(json);
                    Swal.close();
                } else {
                    applyCascadedFilterOptions(json);
                }
            })
            .catch((err) => {
                if (err && err.name === 'AbortError') return; // sengaja di-cancel oleh refresh() berikutnya
                if (seq !== refreshSeq) return;
                if (hasAnyFilter) {
                    Swal.close();
                    Swal.fire('Gagal', 'Tidak bisa memuat data dashboard.', 'error');
                }
            });


        if (hasAnyFilter && calendarUrl) {
            loadShipCalendarMonth(shipCalState.year, shipCalState.month);
        }
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
            { url: syncSubkonUrl, label: 'Sync Subkon' },
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
    // Kelima select saling menyaring dua arah (bolak-balik) lewat
    // applyCascadedFilterOptions() yang dipanggil dari dalam refresh(),
    // jadi TIDAK perlu lagi memaksa clear select lain saat salah satu
    // berubah -- pilihan yang masih valid otomatis dipertahankan, yang
    // sudah tidak valid otomatis ke-clear karena tidak ada lagi di opsi baru.
    $(fBuyer).on('select2:select select2:clear', refresh);
    $(fStyle).on('select2:select select2:clear', refresh);
    $(fOcf).on('select2:select select2:clear', refresh);
    $(fSubRef).on('select2:select select2:clear', refresh);
    // $(fCpo).on('select2:select select2:clear', refresh);
    $(fNegara).on('select2:select select2:clear', refresh);

    /**
     * Tombol "Reset Filter": klik ikon "x" bawaan select2 terbukti tidak
     * selalu benar-benar mengosongkan value <select> di DOM (URL request
     * masih membawa filter lama walau tampilan sudah terlihat ter-clear).
     * Tombol ini memaksa kelima select ke null lewat API resmi select2
     * (.val(null).trigger('change')) SEBELUM memanggil refresh() secara
     * langsung -- tidak bergantung pada event select2:clear sama sekali,
     * jadi hasilnya selalu pasti: semua dropdown balik menampilkan data
     * penuh (unfiltered).
     */
    const btnResetFilter = document.getElementById('btn-reset-filter');
    btnResetFilter?.addEventListener('click', () => {
        [fBuyer, fStyle, fOcf, fSubRef, fNegara].forEach(el => {
            $(el).val(null).trigger('change');
        });
        refresh();
    });

    if (btnSyncAll) btnSyncAll.addEventListener('click', runSyncAll);

    /**
     * Import Stage Remark (mon_stage_remarks) & Prod QC (mon_prod_qc):
     * klik menu dropdown -> buka file picker tersembunyi -> begitu file
     * dipilih, langsung upload lewat fetch (multipart/form-data) ke
     * endpoint import masing-masing. Template Excel-nya (dengan dropdown
     * ocf_no/code_prod/department_id) diunduh lewat link biasa
     * (route stage-remark.template / prod-qc.template), tidak perlu JS.
     */
    function uploadImportFile(url, file, successMessage) {
        if (!url || !file) return;

        const formData = new FormData();
        formData.append('file', file);

        Swal.fire({
            title: 'Mengimpor data...',
            html: 'Mohon tunggu, sedang memproses file Excel.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });

        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData,
        })
            .then(async (response) => {
                const json = await response.json().catch(() => ({}));
                if (!response.ok || !json.success) {
                    const detail = Array.isArray(json.errors) ? json.errors.join('<br>') : '';
                    throw new Error((json.message || 'Import gagal.') + (detail ? `<br><br><small>${detail}</small>` : ''));
                }
                return json;
            })
            .then((json) => {
                Swal.fire({ icon: 'success', title: 'Berhasil', html: json.message || successMessage });
            })
            .catch((err) => {
                Swal.fire({ icon: 'error', title: 'Gagal', html: err.message || 'Import gagal diproses.' });
            });
    }

    const fileStageRemark = document.getElementById('file-stage-remark');
    const fileProdQc = document.getElementById('file-prod-qc');
    const btnImportStageRemark = document.getElementById('btn-import-stage-remark');
    const btnImportProdQc = document.getElementById('btn-import-prod-qc');

    btnImportStageRemark?.addEventListener('click', (e) => {
        e.preventDefault();
        fileStageRemark?.click();
    });
    btnImportProdQc?.addEventListener('click', (e) => {
        e.preventDefault();
        fileProdQc?.click();
    });
    fileStageRemark?.addEventListener('change', () => {
        uploadImportFile(importStageRemarkUrl, fileStageRemark.files[0], 'Import Stage Remark berhasil.');
        fileStageRemark.value = '';
    });
    fileProdQc?.addEventListener('change', () => {
        uploadImportFile(importProdQcUrl, fileProdQc.files[0], 'Import Prod QC berhasil.');
        fileProdQc.value = '';
    });

    /**
     * Hapus 1 remark manual (mon_stage_remarks) lewat ikon tempat sampah
     * kecil di tiap baris remark (lihat renderRemarkList()). Event
     * di-delegasikan ke #rekon-app (bukan dipasang langsung ke tiap ikon)
     * karena box pipeline di-render ulang total lewat innerHTML setiap
     * kali refresh() jalan -- listener langsung akan hilang begitu
     * elemen lama dibuang.
     */
    app.addEventListener('click', (e) => {
        const btn = e.target.closest('.rekon-pipe-remark-delete');
        if (!btn) return;

        const remarkId = btn.dataset.remarkId;
        if (!remarkId || !deleteStageRemarkUrl) return;

        Swal.fire({
            title: 'Hapus remark ini?',
            text: 'Remark yang sudah dihapus tidak bisa dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#e74a3b',
        }).then((result) => {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Menghapus remark...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading(),
            });

            fetch(deleteStageRemarkUrl.replace('__ID__', remarkId), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            })
                .then(async (response) => {
                    const json = await response.json().catch(() => ({}));
                    if (!response.ok || !json.success) {
                        throw new Error(json.message || 'Remark gagal dihapus.');
                    }
                    return json;
                })
                .then((json) => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        html: json.message || 'Remark berhasil dihapus.',
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    refresh();
                })
                .catch((err) => {
                    Swal.fire({ icon: 'error', title: 'Gagal', html: err.message || 'Remark gagal dihapus.' });
                });
        });
    });

    refresh();
})();
</script>

</body>
</html>