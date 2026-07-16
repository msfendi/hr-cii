<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<style>
    .legend-dot{
    display:inline-block;
    width:.7rem;
    height:.7rem;
    border-radius:50%;
    vertical-align:middle;
    margin-right:.25rem;
}

#deptEmployeeDetailTable tbody tr.row-status-keluar td{
    background-color: #fff3cd !important;
}
#deptEmployeeDetailTable tbody tr.row-status-keluar:hover td{
    background-color: #ffe9a8 !important;
}
#deptEmployeeDetailTable tbody tr.row-status-mangkir td{
    background-color: #f8d7da !important;
}
#deptEmployeeDetailTable tbody tr.row-status-mangkir:hover td{
    background-color: #f3b8bf !important;
}

:root{
    --pr-blue: #4e73df;
    --pr-blue-dark: #2e59d9;
    --pr-blue-light: #eef1fd;
    --pr-blue-lighter: #f7f9fe;
    --pr-green: #1cc88a;
    --pr-red: #e74a3b;
    --pr-cyan: #36b9cc;
    --pr-gray-800: #5a5c69;
}

body{
    background-color: #f4f6fb;
}

/* ---------- Section headers ---------- */
.section-block{
    margin-bottom: 2.75rem;
}

.section-title{
    display:flex;
    align-items:center;
    gap:.6rem;
    margin-bottom:1rem;
}

.section-title .section-number{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:2rem;
    height:2rem;
    border-radius:50%;
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%);
    color:#fff;
    font-weight:700;
    font-size:.95rem;
    flex-shrink:0;
}

.section-title h2{
    font-size:1.1rem;
    font-weight:700;
    color:#3a3b45;
    margin:0;
}

.section-divider{
    border:none;
    border-top:1px dashed #d9dcec;
    margin: 0 0 2rem 0;
}

/* ---------- Tabs (section switcher) ---------- */
#recapTabs{
    border-bottom: 2px solid #e3e6f0;
    margin-bottom: 1.75rem;
}

#recapTabs .nav-link{
    border: none;
    border-bottom: 3px solid transparent;
    color: #858796;
    font-weight: 700;
    font-size: .92rem;
    padding: .75rem 1.1rem;
    display:flex;
    align-items:center;
    gap:.5rem;
}

#recapTabs .nav-link .tab-number{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:1.6rem;
    height:1.6rem;
    border-radius:50%;
    background:#eaecf4;
    color:#5a5c69;
    font-size:.78rem;
    font-weight:700;
    flex-shrink:0;
    transition: background-color .15s ease, color .15s ease;
}

#recapTabs .nav-link:hover{
    color: var(--pr-blue-dark);
    border-bottom-color: #d9dcec;
}

#recapTabs .nav-link.active{
    color: var(--pr-blue-dark);
    background: transparent;
    border-bottom-color: var(--pr-blue);
}

#recapTabs .nav-link.active .tab-number{
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%);
    color:#fff;
}

.tab-pane .section-title{
    display:none; /* nomor & judul section sudah terwakili oleh tab, hindari duplikasi */
}

.tab-pane .period-badge{
    margin-left:.25rem;
}

/* ---------- General card polish ---------- */
.card{
    border: none;
    border-radius: .6rem;
    transition: box-shadow .2s ease, transform .2s ease;
}

.card.shadow{
    box-shadow: 0 .15rem 1.5rem 0 rgba(58,59,69,.1) !important;
}

.card.shadow:hover{
    box-shadow: 0 .3rem 2rem 0 rgba(58,59,69,.14) !important;
}

.card-header{
    background: linear-gradient(180deg, #ffffff 0%, var(--pr-blue-lighter) 100%);
    border-bottom: 1px solid #eaecf4;
    border-radius: .6rem .6rem 0 0 !important;
}

.card-header h6{
    font-size: .95rem;
    letter-spacing: .02em;
}

.card-header h6 i{
    color: var(--pr-blue);
}

/* ---------- Filter panel ---------- */
.filter-form label{
    letter-spacing: .03em;
    text-transform: uppercase;
    font-size: .7rem;
}

.btn-primary{
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%);
    border: none;
    box-shadow: 0 .125rem .5rem rgba(78,115,223,.35);
}

.btn-primary:hover{
    background: linear-gradient(135deg, var(--pr-blue-dark) 0%, #234ac2 100%);
}

.period-badge{
    display:inline-block;
    background: var(--pr-blue-light);
    color: var(--pr-blue-dark);
    font-weight:700;
    font-size:.75rem;
    padding:.25rem .6rem;
    border-radius:1rem;
}

/* ---------- KPI cards ---------- */
.kpi-card{
    position: relative;
    overflow: hidden;
    border-radius: .75rem;
    color: #fff;
    padding: 1.25rem 1.4rem;
    min-height: 108px;
}

.kpi-card .kpi-icon{
    position: absolute;
    right: -.5rem;
    bottom: -.75rem;
    font-size: 4.2rem;
    opacity: .18;
}

.kpi-card .kpi-label{
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    opacity: .85;
    font-weight: 700;
}

.kpi-card .kpi-value{
    font-size: 1.4rem;
    font-weight: 700;
    margin-top: .25rem;
}

.kpi-card.kpi-primary{ background: linear-gradient(135deg, #4e73df 0%, #3f5fc9 60%, #2e59d9 100%); }
.kpi-card.kpi-success{ background: linear-gradient(135deg, #1cc88a 0%, #17b57a 60%, #13a06d 100%); }
.kpi-card.kpi-info   { background: linear-gradient(135deg, #36b9cc 0%, #2ba6b8 60%, #2093a4 100%); }

/* ---------- Tables ---------- */
.table thead th{
    background-color: var(--pr-blue-light);
    color: var(--pr-blue-dark);
    border-bottom: 2px solid var(--pr-blue) !important;
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    font-weight: 700;
    vertical-align: middle;
}

.table-sm td,
.table-sm th{
    padding:.4rem .5rem;
    font-size:.82rem;
}

.table tbody tr:hover{
    background-color: var(--pr-blue-light);
}

.table tfoot td, .table tfoot th{
    background-color: #f4f6fb;
    border-top: 2px solid var(--pr-blue) !important;
}

.dataTables_wrapper{
    font-size:.85rem;
}

.dataTables_wrapper table td{
    padding:.5rem;
}

.dataTables_scrollBody{
    border-bottom: none !important;
}

.dataTables_scrollHead{
    box-shadow: 0 2px 4px rgba(78,115,223,.08);
}

.dataTables_scrollFoot{
    box-shadow: 0 -2px 6px rgba(78,115,223,.1);
}

.dataTables_scrollFoot table tfoot td{
    background-color: #eef1fd;
}

.badge-earning{
    color: var(--pr-gray-800);
    font-weight: 600;
}

.badge-deduction{
    color: var(--pr-red);
    font-weight: 600;
}

.overtime-dept-row,
.dept-payroll-row{
    cursor:pointer;
}

.overtime-dept-row:hover,
.dept-payroll-row:hover{
    background-color: var(--pr-blue-light);
}

.dept-payroll-row td:first-child{
    color: var(--pr-blue-dark);
    font-weight: 600;
}

.dept-payroll-row td:first-child i{
    font-size: .7rem;
    margin-right: .35rem;
    opacity: .6;
}

.ot-special-col{
    background-color:#fdecef !important;
    color:#c0293c;
    font-weight: 600;
}

.section-hint{
    font-size: .74rem;
    color: #8a8fa3;
}

.modal-header{
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%);
    color: #fff;
    border-radius: .5rem .5rem 0 0;
}

.modal-header .close{
    color: #fff;
    opacity: .85;
    text-shadow: none;
}

.modal-header .close:hover{
    opacity: 1;
    color: #fff;
}

.legend-chip{
    display:inline-block;
    width:.85rem;
    height:.85rem;
    border-radius:.2rem;
    vertical-align:middle;
    margin-right:.3rem;
}

/* ---------- Tab loading state ---------- */
.tab-loading{
    text-align:center;
    padding: 3rem 0;
    color:#8a8fa3;
}

.tab-loading i{
    font-size:1.8rem;
    color:#c3c8dd;
}

</style>
<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                @include('layout.navbar')

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-chart-pie text-primary mr-2"></i>Dashboard Payroll
                        </h1>
                    </div>

                    {{-- ===================== INFO FILTER ROLE PAYROLL ===================== --}}
                    @if($payrollRole === null)
                        <div class="alert alert-danger py-2 px-3 mb-4">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Akun Anda belum terdaftar di <strong>role_payrolls</strong>, sehingga data pada halaman ini kosong.
                            Silakan hubungi Admin untuk pengaturan akses.
                        </div>
                    @elseif($payrollRole !== \App\Services\PayrollRoleFilterService::ROLE_ALL)
                        <div class="alert alert-info py-2 px-3 mb-4">
                            <i class="fas fa-info-circle mr-1"></i>
                            Data pada halaman ini ditampilkan sesuai akses role payroll Anda: <strong>{{ $payrollRoleLabel }}</strong>
                        </div>
                    @endif

                    {{-- =====================================================================
                         TAB NAVIGATION
                         Tiap section hanya fetch data AJAX saat tab-nya pertama kali dibuka
                         (lihat script "INIT" di bagian bawah). Ini mencegah 3 section fetch
                         data bareng-bareng saat halaman pertama kali dimuat.
                    ===================================================================== --}}
                    <ul class="nav nav-tabs" id="recapTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-link-1" data-toggle="tab" href="#section-1" role="tab" aria-controls="section-1" aria-selected="true">
                                <span class="tab-number">1</span> Rekap Payroll
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-2" data-toggle="tab" href="#section-2" role="tab" aria-controls="section-2" aria-selected="false">
                                <span class="tab-number">2</span> Detail Payroll
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-3" data-toggle="tab" href="#section-3" role="tab" aria-controls="section-3" aria-selected="false">
                                <span class="tab-number">3</span> Overtime
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="recapTabsContent">

                    {{-- =====================================================================
                         SECTION 1: REKAP PAYROLL (per bulan, 12 bulan rolling)
                    ===================================================================== --}}
                    <div class="tab-pane fade show active section-block" id="section-1" role="tabpanel" aria-labelledby="tab-link-1">
                        <div class="section-title">
                            <span class="section-number">1</span>
                            <h2>Rekap Payroll</h2>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </h6>
                            </div>
                            <div class="card-body">
                                <form id="filterForm" class="filter-form">
                                    <div class="row align-items-start">

                                        <div class="col-md-2 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Bulan Akhir</label>
                                            <input type="month" class="form-control" id="endMonth" name="end_month">
                                            <small class="form-text text-muted mb-0">Otomatis mundur 12 bulan</small>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Karyawan (NPK)</label>
                                            <select id="npkSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Karyawan</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Department</label>
                                            <select id="deptSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Department</option>
                                                @foreach ($departments as $dept)
                                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Komponen Payroll</label>
                                            <select id="componentSelect" class="form-control" style="width:100%">
                                                <option value="total_salary">Total Take Home Pay</option>
                                                <optgroup label="Earning">
                                                    @foreach ($components['earning'] as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </optgroup>
                                                <optgroup label="Deduction">
                                                    @foreach ($components['deduction'] as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </optgroup>
                                            </select>
                                        </div>

                                    </div>

                                    <div class="d-flex">
                                        <button type="submit" class="btn btn-primary shadow-sm mr-2">
                                            <i class="fas fa-search fa-sm mr-1"></i> Terapkan Filter
                                        </button>
                                        <button type="button" id="resetFilter" class="btn btn-outline-secondary shadow-sm">
                                            <i class="fas fa-undo fa-sm mr-1"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- KPI CARDS --}}
                        <div class="row">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="kpi-card kpi-primary shadow h-100">
                                    <div class="kpi-label" id="componentLabel">Total Take Home Pay</div>
                                    <div class="kpi-value" id="kpiGrandTotal">Rp 0</div>
                                    <i class="fas fa-coins kpi-icon"></i>
                                </div>
                            </div>

                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="kpi-card kpi-success shadow h-100">
                                    <div class="kpi-label">Rata-rata / Bulan</div>
                                    <div class="kpi-value" id="kpiAvgMonth">Rp 0</div>
                                    <i class="fas fa-chart-line kpi-icon"></i>
                                </div>
                            </div>

                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="kpi-card kpi-info shadow h-100">
                                    <div class="kpi-label">Jumlah Karyawan (Max/Bulan)</div>
                                    <div class="kpi-value" id="kpiMaxEmployees">0</div>
                                    <i class="fas fa-users kpi-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="row">

                            {{-- CHART --}}
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow h-100">

                                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-chart-bar mr-1"></i>
                                            Rekap Payroll per Bulan
                                            <span class="text-gray-500 font-weight-normal"
                                                id="rangeLabel"></span>
                                        </h6>
                                    </div>

                                    <div class="card-body">

                                        <div id="chartLoading" class="text-center py-5" style="display:none;">
                                            <i class="fas fa-spinner fa-spin fa-2x text-gray-400"></i>
                                            <p class="text-gray-500 mt-2 mb-0">Memuat data...</p>
                                        </div>

                                        <div id="chartEmpty" class="text-center py-5" style="display:none;">
                                            <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                            <p class="text-gray-500 mt-2 mb-0">Tidak ada data.</p>
                                        </div>

                                        <div style="height:350px">
                                            <canvas id="recapChart"></canvas>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            {{-- RINCIAN BULAN --}}
                            <div class="col-lg-6 mb-4">

                                <div class="card shadow h-100">

                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-table mr-1"></i>
                                            Rincian per Bulan
                                        </h6>
                                    </div>

                                    <div class="card-body p-2">

                                        <div class="table-responsive">

                                            <table class="table table-sm table-bordered table-hover mb-0 small">

                                                <thead>
                                                <tr>
                                                    <th rowspan="2" class="align-middle">Bulan</th>
                                                    <th colspan="2" class="text-center" style="color:#17a673;">Karyawan Aktif</th>
                                                    <th colspan="2" class="text-center" style="color:#e0a800;">Karyawan Keluar</th>
                                                    <th colspan="2" class="text-center" style="color:#c0293c;">Karyawan Mangkir</th>
                                                    <th rowspan="2" class="align-middle text-right">Total Keseluruhan</th>
                                                </tr>
                                                <tr>
                                                    <th class="text-right" style="color:#17a673;">Jumlah</th>
                                                    <th class="text-right" style="color:#17a673;">Total</th>
                                                    <th class="text-right" style="color:#e0a800;">Jumlah</th>
                                                    <th class="text-right" style="color:#e0a800;">Total</th>
                                                    <th class="text-right" style="color:#c0293c;">Jumlah</th>
                                                    <th class="text-right" style="color:#c0293c;">Total</th>
                                                </tr>
                                                </thead>

                                                <tbody id="breakdownTableBody"></tbody>

                                                <tfoot>
                                                    <tr class="font-weight-bold">
                                                        <td>Grand Total</td>
                                                        <td class="text-right" id="footerAktifCount">-</td>
                                                        <td class="text-right" id="footerAktifTotal">Rp 0</td>
                                                        <td class="text-right" id="footerKeluarCount">-</td>
                                                        <td class="text-right" id="footerKeluarTotal">Rp 0</td>
                                                        <td class="text-right" id="footerMangkirCount">-</td>
                                                        <td class="text-right" id="footerMangkirTotal">Rp 0</td>
                                                        <td class="text-right" id="footerTotal">Rp 0</td>
                                                    </tr>
                                                </tfoot>

                                            </table>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>
                    </div>

                    {{-- =====================================================================
                         SECTION 2: DETAIL PAYROLL (per karyawan & per department, 1 periode)
                    ===================================================================== --}}
                    <div class="tab-pane fade section-block" id="section-2" role="tabpanel" aria-labelledby="tab-link-2">
                        <div class="section-title">
                            <span class="section-number">2</span>
                            <h2>Detail Payroll</h2>
                            <span class="period-badge" id="detailPeriodLabel">-</span>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </h6>
                            </div>
                            <div class="card-body">
                                <form id="detailFilterForm" class="filter-form">
                                    <div class="row align-items-start">

                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Periode Payroll</label>
                                            <select id="detailPeriodSelect" class="form-control" style="width:100%">
                                                @foreach ($periods as $p)
                                                    <option value="{{ $p->id }}">
                                                        {{ $p->name }}{{ !$p->is_closed ? ' — Live' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Karyawan (NPK)</label>
                                            <select id="detailNpkSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Karyawan</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Department</label>
                                            <select id="detailDeptSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Department</option>
                                                @foreach ($departments as $dept)
                                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Komponen Payroll</label>
                                            <select id="detailComponentSelect" class="form-control" style="width:100%">
                                                <option value="total_salary">Total Take Home Pay</option>
                                                <optgroup label="Earning">
                                                    @foreach ($components['earning'] as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </optgroup>
                                                <optgroup label="Deduction">
                                                    @foreach ($components['deduction'] as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </optgroup>
                                            </select>
                                        </div>

                                    </div>

                                    <div class="d-flex">
                                        <button type="submit" class="btn btn-primary shadow-sm mr-2">
                                            <i class="fas fa-search fa-sm mr-1"></i> Terapkan Filter
                                        </button>
                                        <button type="button" id="detailResetFilter" class="btn btn-outline-secondary shadow-sm">
                                            <i class="fas fa-undo fa-sm mr-1"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="row">

                            {{-- RINCIAN PER KARYAWAN (setengah) --}}
                            <div class="col-lg-6">
                                <div class="card shadow mb-4 h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-id-badge mr-1"></i> Rincian per Karyawan
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover mb-0 small" id="employeeTable" width="100%" cellspacing="0">
                                                <thead>
                                                    <tr>
                                                        <th>NPK</th>
                                                        <th>Nama</th>
                                                        <th>Bagian</th>
                                                        <th>Status</th>
                                                        <th>Tanggal Keluar</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- RINCIAN PAYROLL PER DEPARTMENT (setengah, tepat di sebelah kanan) --}}
                            <div class="col-lg-6">
                                <div class="card shadow mb-4 h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-building mr-1"></i> Rincian Payroll per Department
                                        </h6>
                                        <small class="section-hint">Klik nama department untuk melihat rincian semua komponen gaji per karyawan.</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered table-hover mb-0 small" id="deptPayrollTable" width="100%" cellspacing="0">
                                                <thead>
                                                    <tr>
                                                        <th>Department</th>
                                                        <th class="text-right">Jumlah Karyawan</th>
                                                        <th class="text-right" id="deptComponentLabel">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                                <tfoot>
                                                    <tr class="font-weight-bold">
                                                        <td>Grand Total</td>
                                                        <td class="text-right" id="deptPayrollFooterCount">-</td>
                                                        <td class="text-right" id="deptPayrollFooterTotal">Rp 0</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- =====================================================================
                         SECTION 3: OVERTIME (1 periode payroll)
                    ===================================================================== --}}
                    <div class="tab-pane fade section-block" id="section-3" role="tabpanel" aria-labelledby="tab-link-3">
                        <div class="section-title">
                            <span class="section-number">3</span>
                            <h2>Overtime</h2>
                            <span class="period-badge" id="overtimePeriodLabel">-</span>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </h6>
                            </div>
                            <div class="card-body">
                                <form id="overtimeFilterForm" class="filter-form">
                                    <div class="row align-items-start">

                                        <div class="col-md-4 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Periode Payroll</label>
                                            <select id="overtimePeriodSelect" class="form-control" style="width:100%">
                                                @foreach ($periods as $p)
                                                    <option value="{{ $p->id }}">
                                                        {{ $p->name }}{{ !$p->is_closed ? ' — Live' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Department</label>
                                            <select id="overtimeDeptSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Department</option>
                                                @foreach ($departments as $dept)
                                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                    </div>

                                    <div class="d-flex">
                                        <button type="submit" class="btn btn-primary shadow-sm mr-2">
                                            <i class="fas fa-search fa-sm mr-1"></i> Terapkan Filter
                                        </button>
                                        <button type="button" id="overtimeResetFilter" class="btn btn-outline-secondary shadow-sm">
                                            <i class="fas fa-undo fa-sm mr-1"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="kpi-card kpi-primary shadow h-100">
                                    <div class="kpi-label">Biaya Lembur Biasa</div>
                                    <div class="kpi-value" id="kpiOvertimeCostRegular">Rp 0</div>
                                    <i class="fas fa-coins kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="kpi-card kpi-success shadow h-100">
                                    <div class="kpi-label">Biaya Lembur Khusus</div>
                                    <div class="kpi-value" id="kpiOvertimeCostSpecial">Rp 0</div>
                                    <i class="fas fa-coins kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="kpi-card kpi-info shadow h-100">
                                    <div class="kpi-label">Total Biaya Lembur</div>
                                    <div class="kpi-value" id="kpiOvertimeCostTotal">Rp 0</div>
                                    <i class="fas fa-money-bill-wave kpi-icon"></i>
                                </div>
                            </div>
                        </div>
                        {{-- RINCIAN LEMBUR PER MINGGU --}}
                        <div class="row">
                            <div class="col-lg-12 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-calendar-week mr-1"></i> Rincian Lembur per Minggu
                                        </h6>
                                        <small class="section-hint">
                                            Minggu dihitung mulai dari tanggal awal periode payroll (7 hari per minggu).
                                        </small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0 small">
                                                <thead>
                                                    <tr>
                                                        <th style="width:90px;">Minggu</th>
                                                        <th>Rentang Tanggal</th>
                                                        <th class="text-right">Lembur Biasa (Jam)</th>
                                                        <th class="text-right">Lembur Khusus (Jam)</th>
                                                        <th class="text-right">Total (Jam)</th>
                                                        <th class="text-right">Biaya Biasa</th>
                                                        <th class="text-right">Biaya Khusus</th>
                                                        <th class="text-right">Biaya Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="overtimeByWeekTableBody"></tbody>
                                                <tfoot>
                                                    <tr class="font-weight-bold">
                                                        <td colspan="2">Rata-rata / Minggu</td>
                                                        <td class="text-right" id="footerAvgRegularWeek">0 Jam</td>
                                                        <td class="text-right" id="footerAvgSpecialWeek">0 Jam</td>
                                                        <td class="text-right" id="footerAvgTotalWeek">0 Jam</td>
                                                        <td class="text-right" id="footerAvgCostRegularWeek">Rp 0</td>
                                                        <td class="text-right" id="footerAvgCostSpecialWeek">Rp 0</td>
                                                        <td class="text-right" id="footerAvgCostTotalWeek">Rp 0</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- TOP 5 DEPARTMENT OVERTIME --}}
                        <div class="row">
                            <div class="col-lg-12 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-trophy mr-1"></i> Top 5 Department Overtime
                                        </h6>
                                        <small class="section-hint">5 department dengan total jam lembur tertinggi pada periode ini.</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0 small">
                                                <thead>
                                                    <tr>
                                                        <th style="width:40px;">#</th>
                                                        <th>Department</th>
                                                        <th class="text-right">Lembur Biasa (Jam)</th>
                                                        <th class="text-right">Lembur Khusus (Jam)</th>
                                                        <th class="text-right">Total (Jam)</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="top5DeptTableBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- CHART: Total Overtime per Tanggal (Biasa vs Khusus) --}}
                        <div class="row">
                            <div class="col-lg-12 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-chart-bar mr-1"></i>
                                            Total Overtime per Tanggal
                                        </h6>
                                        <small class="section-hint">Lembur biasa vs lembur khusus dalam periode terpilih.</small>
                                    </div>
                                    <div class="card-body">
                                        <div id="overtimeChartEmpty" class="text-center py-5" style="display:none;">
                                            <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                            <p class="text-gray-500 mt-2 mb-0">Tidak ada data overtime pada periode ini.</p>
                                        </div>
                                        <div style="height:320px">
                                            <canvas id="overtimeChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- RINCIAN OVERTIME PER DEPT (full lebar) --}}
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-clock mr-1"></i>
                                            Rincian Overtime per Dept
                                        </h6>
                                        <small class="section-hint">
                                            Kolom angka menunjukkan tanggal lembur (tgl/bln) pada periode terpilih. Klik baris department untuk melihat rincian per tanggal per karyawan.
                                            <span class="legend-chip" style="background-color:#fdecef;border:1px solid #f3c2cb;"></span>
                                            = hari libur (Sabtu/Minggu atau libur nasional)
                                        </small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive">
                                            <div id="overtimeDeptTableWrapper">
                                                <table class="table table-sm table-bordered table-hover mb-0 small" id="overtimeDeptTable" width="100%"></table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>
                    <!-- /.tab-content -->

                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            @include('layout.footer')
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    {{-- ===================== MODAL: RINCIAN OVERTIME PER DEPT ===================== --}}
    <div class="modal fade" id="overtimeDetailModal" tabindex="-1" role="dialog" aria-labelledby="overtimeDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="overtimeDetailModalLabel"><i class="fas fa-clock mr-1"></i> Rincian Overtime</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        <span class="legend-chip" style="background-color:#fdecef;border:1px solid #f3c2cb;"></span>
                        Kolom tanggal berwarna merah = hari libur (Sabtu/Minggu atau libur nasional)
                    </p>
                    {{-- Thead/tbody/tfoot dibangun dinamis via JS sesuai tanggal overtime
                         milik department yang dipilih pada periode terpilih. scrollY dipakai
                         supaya body bisa di-scroll sementara baris Grand Total (tfoot) tetap terlihat. --}}
                    <!-- overtimeDetailTable (di dalam modal) -->
                    <div class="table-responsive">
                        <div id="overtimeDetailTableWrapper">
                            <table class="table table-sm table-bordered mb-0 small" id="overtimeDetailTable" width="100%"></table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== MODAL: DETAIL KOMPONEN GAJI PER KARYAWAN PER DEPARTMENT ===================== --}}
    <div class="modal fade" id="deptEmployeeDetailModal" tabindex="-1" role="dialog" aria-labelledby="deptEmployeeDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deptEmployeeDetailModalLabel"><i class="fas fa-building mr-1"></i> Detail Komponen Gaji</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        <span class="badge-earning">&#9679;</span> Earning &nbsp;
                        <span class="badge-deduction">&#9679;</span> Deduction &nbsp;
                        <span class="text-muted">— geser tabel ke samping untuk melihat semua komponen.</span>
                    </p>
                    <p class="small text-muted mb-2">
                        <span class="legend-dot" style="background-color:#ffe69c;"></span> Karyawan Keluar &nbsp;&nbsp;
                        <span class="legend-dot" style="background-color:#f1aeb5;"></span> Karyawan Mangkir
                    </p>
                    {{-- Thead/tbody/tfoot dibangun dinamis via JS berdasarkan
                         dept_employee_details dari response detail-data. scrollX
                         untuk kolom komponen yang banyak, scrollY supaya baris
                         Grand Total tetap terlihat. --}}
                    <!-- deptEmployeeDetailTable (di dalam modal) -->
                        <div class="table-responsive">
                            <div id="deptEmployeeDetailTableWrapper">
                                <table class="table table-sm table-bordered mb-0 small" id="deptEmployeeDetailTable" width="100%"></table>
                            </div>
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        Chart.defaults.font.family = 'Nunito, -apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
        Chart.defaults.color = '#858796';

        // Metadata komponen (label + kelompok earning/deduction), dipakai untuk
        // membangun kolom pada modal "Detail Komponen Gaji per Karyawan".
        const componentMeta = @json($components);

        const rupiah = (value) => new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', minimumFractionDigits: 0
        }).format(value || 0);

        const jamFormat = (value) => new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0, maximumFractionDigits: 2
        }).format(value || 0);

        function showLoadingSwal(msg = 'Memuat data...') {
            Swal.fire({
                title: msg,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading(),
            });
        }
        function hideLoadingSwal() {
            Swal.close();
        }

        function resetDataTableWrapper(wrapperId, tableId, tableClass) {
            const $wrapper = $('#' + wrapperId);

            // Hancurkan instance lama kalau ada
            if ($.fn.DataTable.isDataTable('#' + tableId)) {
                try { $('#' + tableId).DataTable().destroy(); } catch (e) { /* noop */ }
            }

            // Buang SELURUH struktur (termasuk div scrollHead/scrollBody bawaan DataTables)
            // dan buat <table> baru dari nol -> menghindari state korup DataTables.
            $wrapper.empty();
            $wrapper.html(`<table class="${tableClass}" id="${tableId}" width="100%"></table>`);

            return $('#' + tableId);
        }

        function formatMonthIndonesia(value){

            if(!value) return '';

            // format YYYY-MM
            if(/^\d{4}-\d{2}$/.test(value)){
                const [y,m]=value.split('-');

                const bulan=[
                    'Januari','Februari','Maret','April','Mei','Juni',
                    'Juli','Agustus','September','Oktober','November','Desember'
                ];

                return bulan[parseInt(m)-1]+' '+y;
            }

            // format lain gunakan Date
            const d=new Date(value);

            if(!isNaN(d)){
                return d.toLocaleDateString('id-ID',{
                    month:'long',
                    year:'numeric'
                });
            }

            return value;
        }

        function setDefaultMonthRange() {
            const now = new Date();
            const end = new Date(now.getFullYear(), now.getMonth(), 1);
            const toMonthValue = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;

            $('#endMonth').val(toMonthValue(end));
        }

        /* =====================================================================
         * SECTION 1: REKAP PAYROLL (per bulan)
         * ===================================================================== */

        let recapChart = null;

        function loadChartData() {
            const params = {
                end_month: $('#endMonth').val(),
                npk: $('#npkSelect').val(),
                dept: $('#deptSelect').val(),
                component: $('#componentSelect').val(),
            };
            showLoadingSwal('Memuat rekap payroll...');
            $('#chartLoading').show();
            $('#chartEmpty').hide();

            fetch("{{ route('payroll-recap.chart-data') }}?" + new URLSearchParams(params))
                .then(res => res.json())
                .then(data => {
                    $('#chartLoading').hide();

                    const hasData = data.values.some(v => v > 0);
                    $('#chartEmpty').toggle(!hasData);

                    // KPI
                    $('#componentLabel').text(data.component_label);
                    $('#kpiGrandTotal').text(rupiah(data.grand_total));
                    $('#kpiAvgMonth').text(rupiah(data.avg_per_month));
                    $('#kpiMaxEmployees').text(data.max_employees);
                    $('#rangeLabel').text(
                        '(' +
                        formatMonthIndonesia(data.range.start) +
                        ' s/d ' +
                        formatMonthIndonesia(data.range.end) +
                        ')'
                    );

                    // Chart - grouped bar (Aktif vs Keluar) + garis Total Keseluruhan
                    if (recapChart) recapChart.destroy();
                    const ctx = document.getElementById('recapChart').getContext('2d');

                    const gradAktif = ctx.createLinearGradient(0, 0, 0, 300);
                    gradAktif.addColorStop(0, 'rgba(28,200,138,0.95)');
                    gradAktif.addColorStop(1, 'rgba(28,200,138,0.55)');

                    const gradKeluar = ctx.createLinearGradient(0, 0, 0, 300);
                    gradKeluar.addColorStop(0, 'rgba(231,74,59,0.95)');
                    gradKeluar.addColorStop(1, 'rgba(231,74,59,0.55)');

                    const gradMangkir = ctx.createLinearGradient(0, 0, 0, 300);
                    gradMangkir.addColorStop(0, 'rgba(246,169,11,0.95)');
                    gradMangkir.addColorStop(1, 'rgba(246,169,11,0.55)');

                    recapChart = new Chart(ctx, {
                        data: {
                            labels: data.labels.map(e=>formatMonthIndonesia(e)),
                            datasets: [
                                {
                                    type: 'bar',
                                    label: 'Karyawan Aktif',
                                    data: data.aktif_values,
                                    backgroundColor: gradAktif,
                                    hoverBackgroundColor: "#17a673",
                                    borderRadius: 6,
                                    maxBarThickness: 20,
                                    order: 2,
                                },
                                {
                                    type: 'bar',
                                    label: 'Karyawan Keluar',
                                    data: data.keluar_values,
                                    backgroundColor: gradKeluar,
                                    hoverBackgroundColor: "#d52a1a",
                                    borderRadius: 6,
                                    maxBarThickness: 20,
                                    order: 2,
                                },
                                {
                                    type: 'bar',
                                    label: 'Karyawan Mangkir',
                                    data: data.mangkir_values,
                                    backgroundColor: gradMangkir,
                                    hoverBackgroundColor: "#dd960a",
                                    borderRadius: 6,
                                    maxBarThickness: 20,
                                    order: 2,
                                },
                                {
                                    type: 'line',
                                    label: 'Total Keseluruhan',
                                    data: data.values,
                                    borderColor: '#4e73df',
                                    backgroundColor: '#4e73df',
                                    borderWidth: 2.5,
                                    tension: .35,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#4e73df',
                                    pointBorderColor: '#fff',
                                    pointBorderWidth: 1.5,
                                    fill: false,
                                    order: 1,
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: { grid: { display: false, drawBorder: false } },
                                y: {
                                    ticks: { callback: (val) => rupiah(val) },
                                    grid: {
                                        color: "rgb(234, 236, 244)",
                                        drawBorder: false,
                                        borderDash: [3],
                                    }
                                },
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: { usePointStyle: true, boxWidth: 8, padding: 16 }
                                },
                                tooltip: {
                                    backgroundColor: 'rgb(255,255,255)',
                                    titleColor: '#6e707e',
                                    bodyColor: '#5a5c69',
                                    borderColor: '#dddfeb',
                                    borderWidth: 1,
                                    padding: 12,
                                    cornerRadius: 8,
                                    displayColors: true,
                                    callbacks: {
                                        label: (ctx) => {
                                            const idx = ctx.dataIndex;
                                            if (ctx.dataset.label === 'Total Keseluruhan') {
                                                return `Total Keseluruhan: ${rupiah(ctx.parsed.y)}`;
                                            }
                                            const countsByDataset = [data.aktif_counts, data.keluar_counts, data.mangkir_counts];
                                            const count = countsByDataset[ctx.datasetIndex][idx];
                                            return `${ctx.dataset.label}: ${rupiah(ctx.parsed.y)} (${count} karyawan)`;
                                        }
                                    }
                                }
                            }
                        }
                    });

                    // Tabel breakdown per bulan
                    let rows = '';
                    data.labels.forEach((label, i) => {
                        rows += `<tr>
                            <td>${formatMonthIndonesia(label)}</td>
                            <td class="text-right">${data.aktif_counts[i]}</td>
                            <td class="text-right">${rupiah(data.aktif_values[i])}</td>
                            <td class="text-right">${data.keluar_counts[i]}</td>
                            <td class="text-right">${rupiah(data.keluar_values[i])}</td>
                            <td class="text-right">${data.mangkir_counts[i]}</td>
                            <td class="text-right">${rupiah(data.mangkir_values[i])}</td>
                            <td class="text-right font-weight-bold">${rupiah(data.values[i])}</td>
                        </tr>`;
                    });
                    $('#breakdownTableBody').html(rows);

                    const totalAktif   = data.aktif_values.reduce((a, b) => a + b, 0);
                    const totalKeluar  = data.keluar_values.reduce((a, b) => a + b, 0);
                    const totalMangkir = data.mangkir_values.reduce((a, b) => a + b, 0);
                    $('#footerAktifCount').text(Math.max(...data.aktif_counts));
                    $('#footerAktifTotal').text(rupiah(totalAktif));
                    $('#footerKeluarCount').text(Math.max(...data.keluar_counts));
                    $('#footerKeluarTotal').text(rupiah(totalKeluar));
                    $('#footerMangkirCount').text(Math.max(...data.mangkir_counts));
                    $('#footerMangkirTotal').text(rupiah(totalMangkir));
                    $('#footerTotal').text(rupiah(data.grand_total));
                })
                .catch(err => {
                    $('#chartLoading').hide();
                    // console.error('Error fetching recap chart data:', err);
                    Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data.', 'error');
                }).finally(() => hideLoadingSwal());
        }

        /* =====================================================================
         * SECTION 2: DETAIL PAYROLL (per karyawan & per department, 1 periode)
         * ===================================================================== */

        let employeeTable = null;
        let deptPayrollTable = null;
        let deptEmployeeDetailsData = {};
        let deptEmployeeDetailTable = null;

        function initEmployeeTable() {
            employeeTable = $('#employeeTable').DataTable({
                data: [],
                columns: [
                    { data: 'npk', title: 'NPK' },
                    { data: 'nama', title: 'Nama', defaultContent: '-' },
                    { data: 'bagian', title: 'Dept', defaultContent: '-' },
                    {
                        data: 'status',
                        title: 'Status',
                        className: 'text-center',
                        render: (data) => {
                            if (data === 'mangkir') {
                                return '<span class="badge badge-danger">Mangkir</span>';
                            }
                            if (data === 'keluar') {
                                return '<span class="badge badge-warning">Keluar</span>';
                            }
                            return '<span class="badge badge-success">Aktif</span>';
                        }
                    },
                    { data: 'tkk_formatted', title: 'Tanggal Keluar', defaultContent: '-' },
                    {
                        data: 'total',
                        title: 'Total',
                        className: 'text-right',
                        render: (data, type) => type === 'display' ? rupiah(data) : data
                    },
                ],
                order: [[5, 'desc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ karyawan',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada karyawan yang cocok',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
                }
            });
        }

        function updateEmployeeTable(employees) {
            if (!employeeTable) return;
            employeeTable.clear();
            employeeTable.rows.add(employees);
            employeeTable.draw();
        }

        function initDeptPayrollTable() {
            deptPayrollTable = $('#deptPayrollTable').DataTable({
                data: [],
                columns: [
                    {
                        data: 'dept_name',
                        title: 'Department',
                        render: (data) => `<i class="fas fa-chevron-circle-right text-primary" style="opacity:.55;"></i> ${data}`
                    },
                    { data: 'employee_count', title: 'Jumlah Karyawan', className: 'text-right' },
                    {
                        data: 'total',
                        title: 'Total',
                        className: 'text-right font-weight-bold',
                        render: (data, type) => type === 'display' ? rupiah(data) : data
                    },
                ],
                order: [[0, 'asc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                createdRow: function (row, data) {
                    $(row).addClass('dept-payroll-row')
                        .attr('data-dept-id', data.dept_id)
                        .attr('data-dept-name', data.dept_name)
                        .attr('title', 'Klik untuk melihat rincian komponen gaji per karyawan');
                },
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ department',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada department yang cocok',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
                }
            });
        }

        function updateDeptPayrollTable(payrollByDept, componentLabel) {
            $('#deptComponentLabel').text('Total ' + (componentLabel || ''));

            if (!deptPayrollTable) return;
            deptPayrollTable.clear();
            deptPayrollTable.rows.add(payrollByDept);
            deptPayrollTable.draw();

            const totalEmployees = payrollByDept.reduce((acc, d) => acc + (d.employee_count || 0), 0);
            const totalAll = payrollByDept.reduce((acc, d) => acc + (d.total || 0), 0);
            $('#deptPayrollFooterCount').text(payrollByDept.length ? totalEmployees : '-');
            $('#deptPayrollFooterTotal').text(rupiah(totalAll));
        }

        /*
         * PERBAIKAN: saat tidak ada data (employees kosong), tabel modal
         * TIDAK LAGI diinisialisasi sebagai DataTable — cukup dirender
         * sebagai <table> HTML biasa berisi pesan "Tidak ada data".
         *
         * Penyebab error sebelumnya ("Cannot set properties of undefined
         * (setting '_DT_CellIndex')"): saat kosong, tbody hanya berisi 1
         * baris dengan 1 <td colspan="N">, padahal thead punya N kolom.
         * Dengan scrollX/scrollY aktif, DataTables mencoba menghitung
         * lebar tiap kolom dari sel-sel body dan mengakses index kolom
         * yang tidak ada pada baris tsb -> undefined -> crash.
         */
        function renderDeptEmployeeDetail(employees, deptName) {
            const earningKeys   = Object.keys(componentMeta.earning || {});
            const deductionKeys = Object.keys(componentMeta.deduction || {});
            const allKeys       = [...earningKeys, ...deductionKeys];

            $('#deptEmployeeDetailModalLabel').html(`<i class="fas fa-building mr-1"></i> Detail Komponen Gaji - ${deptName}`);

            // ---- THEAD (selalu sama, dipakai baik ada data maupun tidak) ----
            let theadHtml = '<thead><tr><th>NPK</th><th>Nama</th>';
            earningKeys.forEach(key => {
                theadHtml += `<th class="text-right badge-earning">${componentMeta.earning[key]}</th>`;
            });
            deductionKeys.forEach(key => {
                theadHtml += `<th class="text-right badge-deduction">${componentMeta.deduction[key]}</th>`;
            });
            theadHtml += '<th class="text-right">Total Take Home Pay</th></tr></thead>';

            const $table = resetDataTableWrapper(
                'deptEmployeeDetailTableWrapper',
                'deptEmployeeDetailTable',
                'table table-sm table-bordered mb-0 small'
            );

            const sortedEmployees = [...employees].sort((a, b) =>
                String(a.nama).localeCompare(String(b.nama), 'id', { sensitivity: 'base' })
            );

            const totalCols = 3 + allKeys.length;

            // ---- Data kosong -> render pesan saja, JANGAN inisialisasi DataTable ----
            if (!sortedEmployees.length) {
                $table.html(
                    theadHtml +
                    `<tbody><tr><td colspan="${totalCols}" class="text-center text-muted py-4">Tidak ada data untuk department ini.</td></tr></tbody>`
                );
                deptEmployeeDetailTable = null;
                return;
            }

            // ---- TBODY (ada data) ----
            let bodyHtml = '';
            sortedEmployees.forEach(emp => {
                const rowClass = emp.status === 'mangkir'
                    ? 'row-status-mangkir'
                    : (emp.status === 'keluar' ? 'row-status-keluar' : '');

                bodyHtml += `<tr class="${rowClass}"><td>${emp.npk}</td><td>${emp.nama}</td>`;
                earningKeys.forEach(key => {
                    const val = (emp.components && emp.components[key]) || 0;
                    bodyHtml += `<td class="text-right">${val ? rupiah(val) : '-'}</td>`;
                });
                deductionKeys.forEach(key => {
                    const val = (emp.components && emp.components[key]) || 0;
                    bodyHtml += `<td class="text-right badge-deduction">${val ? rupiah(val) : '-'}</td>`;
                });
                bodyHtml += `<td class="text-right font-weight-bold">${rupiah(emp.total_salary)}</td>`;
                bodyHtml += '</tr>';
            });

            // ---- TFOOT (Grand Total per komponen) ----
            let footHtml = '<tfoot><tr class="font-weight-bold"><td colspan="2">Grand Total</td>';
            allKeys.forEach(key => {
                const sum = sortedEmployees.reduce((acc, e) => acc + ((e.components && e.components[key]) || 0), 0);
                const cls = deductionKeys.includes(key) ? 'text-right badge-deduction' : 'text-right';
                footHtml += `<td class="${cls}">${sum ? rupiah(sum) : '-'}</td>`;
            });
            const grandSalary = sortedEmployees.reduce((acc, e) => acc + (e.total_salary || 0), 0);
            footHtml += `<td class="text-right">${rupiah(grandSalary)}</td></tr></tfoot>`;

            $table.html(theadHtml + '<tbody>' + bodyHtml + '</tbody>' + footHtml);

            deptEmployeeDetailTable = $table.DataTable({
                order: [[allKeys.length + 2, 'desc']],
                scrollX: true,
                scrollY: '400px',
                scrollCollapse: true,
                paging: false,
                searching: sortedEmployees.length > 8,
                info: false,
                language: {
                    search: 'Cari:',
                    zeroRecords: 'Tidak ada data.',
                    emptyTable: 'Tidak ada data.',
                }
            });
        }

        // Klik baris department pada card "Rincian Payroll per Department" ->
        // buka modal detail semua komponen gaji per karyawan (periode terpilih).
        $(document).on('click', '.dept-payroll-row', function () {
            const deptId   = $(this).data('dept-id');
            const deptName = $(this).data('dept-name');
            const employees = deptEmployeeDetailsData[deptId] || [];

            renderDeptEmployeeDetail(employees, deptName);
            $('#deptEmployeeDetailModal').modal('show');
        });

        $('#deptEmployeeDetailModal').on('shown.bs.modal', function () {
            if (deptEmployeeDetailTable) {
                deptEmployeeDetailTable.columns.adjust();
            }
        });

        function periodLabelHtml(period) {
            if (!period.is_closed) {
                return `${period.name} <span class="badge badge-warning ml-1">Live / Prediksi</span>`;
            }
            return period.name;
        }

        function loadDetailData() {
            const params = {
                period_id: $('#detailPeriodSelect').val(),
                npk: $('#detailNpkSelect').val(),
                dept: $('#detailDeptSelect').val(),
                component: $('#detailComponentSelect').val(),
            };
            showLoadingSwal('Memuat detail payroll...');

            fetch("{{ route('payroll-recap.detail-data') }}?" + new URLSearchParams(params))
                .then(res => res.json())
                .then(data => {
                    $('#detailPeriodLabel').text(data.period.name);
                    $('#detailPeriodLabel').html(periodLabelHtml(data.period));
                    updateEmployeeTable(data.employees);
                    updateDeptPayrollTable(data.payroll_by_dept || [], data.component_label);
                    deptEmployeeDetailsData = data.dept_employee_details || {};
                })
                .catch(err => {
                    // console.error('Error fetching detail data:', err);
                    Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data.', 'error');
                })
                .finally(() => hideLoadingSwal());
        }

        /* =====================================================================
         * SECTION 3: OVERTIME (1 periode payroll)
         * ===================================================================== */

        let overtimeChart = null;
        let overtimeEmployeesData = [];
        let overtimeDeptTable = null;
        let overtimeDetailTable = null;

        function renderTop5Dept(top5) {
            let rows = '';
            if (!top5.length) {
                rows = `<tr><td colspan="5" class="text-center text-muted">Tidak ada data.</td></tr>`;
            } else {
                top5.forEach((d, i) => {
                    rows += `<tr>
                        <td>${i + 1}</td>
                        <td>${d.dept_name}</td>
                        <td class="text-right">${jamFormat(d.overtime_jam)}</td>
                        <td class="text-right">${jamFormat(d.special_overtime_jam)}</td>
                        <td class="text-right font-weight-bold">${jamFormat(d.total_jam)}</td>
                    </tr>`;
                });
            }
            $('#top5DeptTableBody').html(rows);
        }

        function formatDateShort(dateStr) {
            // dateStr format YYYY-MM-DD -> dd/mm
            const [, m, d] = dateStr.split('-');
            return `${d}/${m}`;
        }

        function renderOvertimeByWeek(weeks) {
            let rows = '';
            if (!weeks.length) {
                rows = `<tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>`;
            } else {
                weeks.forEach(w => {
                    rows += `<tr>
                        <td>Minggu ${w.week}</td>
                        <td>${formatDateShort(w.start_date)} - ${formatDateShort(w.end_date)}</td>
                        <td class="text-right">${jamFormat(w.regular_jam)}</td>
                        <td class="text-right">${jamFormat(w.special_jam)}</td>
                        <td class="text-right font-weight-bold">${jamFormat(w.total_jam)}</td>
                        <td class="text-right">${rupiah(w.regular_cost)}</td>
                        <td class="text-right">${rupiah(w.special_cost)}</td>
                        <td class="text-right font-weight-bold">${rupiah(w.total_cost)}</td>
                    </tr>`;
                });
            }
            $('#overtimeByWeekTableBody').html(rows);

            const avgRegular = weeks.length ? weeks.reduce((a, b) => a + b.regular_jam, 0) / weeks.length : 0;
            const avgSpecial = weeks.length ? weeks.reduce((a, b) => a + b.special_jam, 0) / weeks.length : 0;
            const avgCostRegular = weeks.length ? weeks.reduce((a, b) => a + b.regular_cost, 0) / weeks.length : 0;
            const avgCostSpecial = weeks.length ? weeks.reduce((a, b) => a + b.special_cost, 0) / weeks.length : 0;

            $('#footerAvgRegularWeek').text(jamFormat(avgRegular) + ' Jam');
            $('#footerAvgSpecialWeek').text(jamFormat(avgSpecial) + ' Jam');
            $('#footerAvgTotalWeek').text(jamFormat(avgRegular + avgSpecial) + ' Jam');
            $('#footerAvgCostRegularWeek').text(rupiah(avgCostRegular));
            $('#footerAvgCostSpecialWeek').text(rupiah(avgCostSpecial));
            $('#footerAvgCostTotalWeek').text(rupiah(avgCostRegular + avgCostSpecial));
        }

        function renderOvertimeChart(overtimeByDate) {
            if (overtimeChart) overtimeChart.destroy();

            const hasData = overtimeByDate.some(d => d.regular_jam > 0 || d.special_jam > 0);
            $('#overtimeChartEmpty').toggle(!hasData);

            const ctx = document.getElementById('overtimeChart').getContext('2d');

            overtimeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: overtimeByDate.map(d => d.label),
                    datasets: [
                        {
                            label: 'Lembur Biasa',
                            data: overtimeByDate.map(d => d.regular_jam),
                            backgroundColor: 'rgba(78,115,223,0.8)',
                            hoverBackgroundColor: '#2e59d9',
                            borderRadius: 4,
                            maxBarThickness: 24,
                            stack: 'ot',
                        },
                        {
                            label: 'Lembur Khusus',
                            data: overtimeByDate.map(d => d.special_jam),
                            backgroundColor: 'rgba(231,74,59,0.8)',
                            hoverBackgroundColor: '#c0293c',
                            borderRadius: 4,
                            maxBarThickness: 24,
                            stack: 'ot',
                        },
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, grid: { display: false, drawBorder: false } },
                        y: {
                            stacked: true,
                            ticks: { callback: (v) => jamFormat(v) },
                            grid: { color: 'rgb(234, 236, 244)', drawBorder: false, borderDash: [3] },
                        },
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 8, padding: 16 }
                        },
                        tooltip: {
                            backgroundColor: 'rgb(255,255,255)',
                            titleColor: '#6e707e',
                            bodyColor: '#5a5c69',
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: (ctx) => {
                                    const idx = ctx.dataIndex;
                                    const d = overtimeByDate[idx]; // pastikan overtimeByDate disimpan sebagai variabel scope function saat dipanggil
                                    const cost = ctx.dataset.label === 'Lembur Biasa' ? d.regular_cost : d.special_cost;
                                    return `${ctx.dataset.label}: ${jamFormat(ctx.parsed.y)} jam (${rupiah(cost)})`;
                                }
                            }
                        }
                    }
                }
            });
        }

        /*
         * PERBAIKAN (bug pemicu error di screenshot):
         * Sebelumnya, saat overtimeByDept kosong, tbody diisi 1 baris
         * <td colspan="N"> sementara thead tetap punya N kolom terpisah.
         * Karena tabel ini pakai scrollX + scrollY, DataTables menghitung
         * lebar setiap kolom dari sel-sel body -> mengakses index kolom
         * yang tidak ada pada baris colspan tsb -> "Cannot set properties
         * of undefined (setting '_DT_CellIndex')".
         *
         * Solusi: kalau tidak ada data department overtime sama sekali,
         * render tabel HTML biasa (pesan "Tidak ada data") TANPA memanggil
         * .DataTable() sama sekali. DataTable baru diinisialisasi saat
         * benar-benar ada baris data yang jumlah selnya konsisten dengan
         * jumlah kolom header.
         */
        function renderOvertimeByDept(overtimeByDept, overtimeDates, overtimeMatrix) {

            const sorted = [...overtimeByDept].sort((a, b) =>
                String(a.dept_name).localeCompare(String(b.dept_name), 'id', { sensitivity: 'base' })
            );

            const getJam = (deptId, dateKey) =>
                (overtimeMatrix[deptId] && overtimeMatrix[deptId][dateKey]) || 0;

            const dateColClass = (d) =>
                'text-right' + ((d.is_weekend || d.is_holiday) ? ' ot-special-col' : '');

            // ---- THEAD (selalu sama, dipakai baik ada data maupun tidak) ----
            let theadHtml = '<thead><tr><th>Department</th>';
            overtimeDates.forEach(d => {
                theadHtml += `<th class="${dateColClass(d)}">${d.label}</th>`;
            });
            theadHtml += '<th class="text-right">Lembur (Jam)</th>' +
                        '<th class="text-right">Lembur Khusus (Jam)</th>' +
                        '<th class="text-right">Total (Jam)</th></tr></thead>';

            const $table = resetDataTableWrapper(
                'overtimeDeptTableWrapper',
                'overtimeDeptTable',
                'table table-sm table-bordered table-hover mb-0 small'
            );

            const totalCols = 4 + overtimeDates.length;

            // ---- Data kosong -> render pesan saja, JANGAN inisialisasi DataTable ----
            if (!sorted.length) {
                $table.html(
                    theadHtml +
                    `<tbody><tr><td colspan="${totalCols}" class="text-center text-muted py-4">Tidak ada data overtime.</td></tr></tbody>`
                );
                overtimeDeptTable = null;
                return;
            }

            // ---- TBODY (ada data) ----
            let bodyHtml = '';
            sorted.forEach(dept => {
                bodyHtml += `<tr class="overtime-dept-row" data-dept="${dept.dept_id}" data-dept-name="${dept.dept_name}">` +
                            `<td><i class="fas fa-layer-group text-primary mr-1" style="opacity:.5;"></i>${dept.dept_name}</td>`;

                overtimeDates.forEach(d => {
                    const jam = getJam(dept.dept_id, d.key);
                    bodyHtml += `<td class="${dateColClass(d)}">${jam ? jamFormat(jam) : '-'}</td>`;
                });

                bodyHtml += `<td class="text-right">${jamFormat(dept.overtime_jam)}</td>` +
                            `<td class="text-right">${jamFormat(dept.special_overtime_jam)}</td>` +
                            `<td class="text-right font-weight-bold">${jamFormat(dept.total_jam)}</td>` +
                            '</tr>';
            });

            // ---- TFOOT (Grand Total) ----
            let footHtml = '<tfoot><tr class="font-weight-bold"><td>Grand Total</td>';
            overtimeDates.forEach(d => {
                const sum = sorted.reduce((acc, dept) => acc + getJam(dept.dept_id, d.key), 0);
                footHtml += `<td class="${dateColClass(d)}">${sum ? jamFormat(sum) : '-'}</td>`;
            });
            const grandReg   = sorted.reduce((acc, d) => acc + (d.overtime_jam || 0), 0);
            const grandSpec  = sorted.reduce((acc, d) => acc + (d.special_overtime_jam || 0), 0);
            const grandTotal = sorted.reduce((acc, d) => acc + (d.total_jam || 0), 0);
            footHtml += `<td class="text-right">${jamFormat(grandReg)}</td>` +
                        `<td class="text-right">${jamFormat(grandSpec)}</td>` +
                        `<td class="text-right">${jamFormat(grandTotal)}</td>` +
                        '</tr></tfoot>';

            $table.html(theadHtml + '<tbody>' + bodyHtml + '</tbody>' + footHtml);

            overtimeDeptTable = $table.DataTable({
                order: [[0, 'asc']],
                scrollX: true,
                scrollY: '360px',
                scrollCollapse: true,
                paging: false,
                searching: sorted.length > 8,
                info: false,
                language: {
                    search: 'Cari:',
                    zeroRecords: 'Tidak ada data overtime.',
                    emptyTable: 'Tidak ada data overtime.',
                }
            });
        }

        /*
         * PERBAIKAN: sama seperti renderOvertimeByDept() di atas — modal
         * "Rincian Overtime" per department juga bisa kosong (mis. dept
         * dipilih tapi ternyata tidak ada baris overtime pada tanggal
         * manapun di periode terpilih), jadi diberi guard yang sama:
         * skip inisialisasi DataTable kalau tidak ada data karyawan.
         */
        function renderOvertimeDetail(details, deptName) {
            // Kumpulkan tanggal unik (kolom) untuk department ini, urut kronologis,
            // sekaligus tandai tanggal mana yang special (weekend/libur nasional).
            const dateMap = {};
            details.forEach(e => {
                if (!dateMap[e.tanggal]) {
                    const [, mm, dd] = e.tanggal.split('-'); // e.tanggal format: YYYY-MM-DD
                    dateMap[e.tanggal] = {
                        key: e.tanggal,
                        label: `${dd}/${mm}`,
                        special: !!(e.is_weekend || e.is_holiday)
                    };
                }
            });
            const dates = Object.values(dateMap).sort((a, b) => a.key.localeCompare(b.key));

            // Pivot per karyawan: jam per tanggal + total lembur biasa & khusus
            const empMap = {};
            details.forEach(e => {
                if (!empMap[e.npk]) {
                    empMap[e.npk] = {
                        npk: e.npk,
                        nama: e.nama || '-',
                        dates: {},
                        totalReg: 0,
                        totalKhusus: 0,
                    };
                }
                const emp = empMap[e.npk];
                emp.dates[e.tanggal] = (emp.dates[e.tanggal] || 0) + e.jam;
                if (e.jenis === 'special_overtime') {
                    emp.totalKhusus += e.jam;
                } else {
                    emp.totalReg += e.jam;
                }
            });

            const employees = Object.values(empMap).sort((a, b) =>
                String(a.nama).localeCompare(String(b.nama), 'id', { sensitivity: 'base' })
            );

            // ---- THEAD (selalu sama, dipakai baik ada data maupun tidak) ----
            let theadHtml = '<thead><tr><th>NPK</th><th>Nama</th>';
            dates.forEach(d => {
                const cls = 'text-right' + (d.special ? ' ot-special-col' : '');
                theadHtml += `<th class="${cls}">${d.label}</th>`;
            });
            theadHtml += '<th class="text-right">Total Lembur (Jam)</th>' +
                         '<th class="text-right">Total Lembur Khusus (Jam)</th></tr></thead>';

            $('#overtimeDetailModalLabel').html('<i class="fas fa-clock mr-1"></i> Rincian Overtime - ' + deptName);

            const $table = resetDataTableWrapper(
                'overtimeDetailTableWrapper',
                'overtimeDetailTable',
                'table table-sm table-bordered mb-0 small'
            );

            const totalCols = 4 + dates.length;

            // ---- Data kosong -> render pesan saja, JANGAN inisialisasi DataTable ----
            if (!employees.length) {
                $table.html(
                    theadHtml +
                    `<tbody><tr><td colspan="${totalCols}" class="text-center text-muted py-4">Tidak ada data.</td></tr></tbody>`
                );
                overtimeDetailTable = null;
                return;
            }

            // ---- TBODY (ada data) ----
            let bodyHtml = '';
            employees.forEach(emp => {
                bodyHtml += `<tr><td>${emp.npk}</td><td>${emp.nama}</td>`;
                dates.forEach(d => {
                    const jam = emp.dates[d.key] || 0;
                    const cls = 'text-right' + (d.special ? ' ot-special-col' : '');
                    bodyHtml += `<td class="${cls}">${jam ? jamFormat(jam) : '-'}</td>`;
                });
                bodyHtml += `<td class="text-right">${jamFormat(emp.totalReg)}</td>`;
                bodyHtml += `<td class="text-right">${jamFormat(emp.totalKhusus)}</td>`;
                bodyHtml += '</tr>';
            });

            // ---- TFOOT ----
            let footHtml = '<tfoot><tr class="font-weight-bold"><td colspan="2">Grand Total</td>';
            dates.forEach(d => {
                const sum = employees.reduce((acc, emp) => acc + (emp.dates[d.key] || 0), 0);
                const cls = 'text-right' + (d.special ? ' ot-special-col' : '');
                footHtml += `<td class="${cls}">${sum ? jamFormat(sum) : '-'}</td>`;
            });
            const grandReg    = employees.reduce((acc, e) => acc + e.totalReg, 0);
            const grandKhusus = employees.reduce((acc, e) => acc + e.totalKhusus, 0);
            footHtml += `<td class="text-right">${jamFormat(grandReg)}</td>`;
            footHtml += `<td class="text-right">${jamFormat(grandKhusus)}</td>`;
            footHtml += '</tr></tfoot>';

            $table.html(theadHtml + '<tbody>' + bodyHtml + '</tbody>' + footHtml);

            overtimeDetailTable = $table.DataTable({
                order: [[1, 'asc']],
                scrollX: true,
                scrollY: '360px',
                scrollCollapse: true,
                paging: false,
                searching: employees.length > 8,
                info: false,
                language: {
                    search: 'Cari:',
                    zeroRecords: 'Tidak ada data.',
                    emptyTable: 'Tidak ada data.',
                }
            });
        }

        // Klik baris department pada card "Rincian Overtime per Dept" -> tampilkan modal
        $(document).on('click', '.overtime-dept-row', function () {
            const deptId   = $(this).data('dept');
            const deptName = $(this).data('dept-name');

            const details = overtimeEmployeesData
                .filter(e => String(e.dept_id) === String(deptId))
                .sort((a, b) => a.tanggal.localeCompare(b.tanggal) || a.npk.localeCompare(b.npk));

            renderOvertimeDetail(details, deptName);
            $('#overtimeDetailModal').modal('show');
        });

        $('#overtimeDetailModal').on('shown.bs.modal', function () {
            if (overtimeDetailTable) {
                overtimeDetailTable.columns.adjust();
            }
        });

        function loadOvertimeData() {
            const params = {
                period_id: $('#overtimePeriodSelect').val(),
                dept: $('#overtimeDeptSelect').val(),
            };
            showLoadingSwal('Memuat data overtime...');

            fetch("{{ route('payroll-recap.overtime-data') }}?" + new URLSearchParams(params))
                .then(res => res.json())
                .then(data => {
                    $('#overtimePeriodLabel').html(periodLabelHtml(data.period));

                    try {
                        renderOvertimeChart(data.overtime_by_date || []);
                    } catch (e) { console.error('[overtime] renderOvertimeChart:', e); }

                    $('#kpiOvertimeCostRegular').text(rupiah(data.overtime_total_cost_regular));
                    $('#kpiOvertimeCostSpecial').text(rupiah(data.overtime_total_cost_special));
                    $('#kpiOvertimeCostTotal').text(rupiah(data.overtime_total_cost));

                    overtimeEmployeesData = data.overtime_employees || [];

                    try {
                        renderOvertimeByDept(
                            data.overtime_by_dept || [],
                            data.overtime_dates || [],
                            data.overtime_matrix || {}
                        );
                    } catch (e) { console.error('[overtime] renderOvertimeByDept:', e); }

                    try {
                        renderTop5Dept(data.top5_dept || []);
                    } catch (e) { console.error('[overtime] renderTop5Dept:', e); }

                    try {
                        renderOvertimeByWeek(data.overtime_by_week || []);
                    } catch (e) { console.error('[overtime] renderOvertimeByWeek:', e); }
                })
                .catch(err => {
                    console.error('[overtime] fetch error:', err);
                    Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data.', 'error');
                })
                .finally(() => hideLoadingSwal());
        }

        /* =====================================================================
         * INIT
         * ===================================================================== */

        $(document).ready(function () {
            setDefaultMonthRange();
            initEmployeeTable();
            initDeptPayrollTable();

            // ---- Section 1 filters ----
            $('#npkSelect').select2({
                placeholder: 'Semua Karyawan',
                allowClear: true,
                ajax: {
                    url: "{{ route('payroll-recap.search-employee') }}",
                    dataType: 'json',
                    delay: 300,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.results })
                },
                minimumInputLength: 0
            });
            $('#deptSelect').select2({ placeholder: 'Semua Department', allowClear: true });
            $('#componentSelect').select2({ minimumResultsForSearch: 0 });

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                loadChartData();
            });
            $('#resetFilter').on('click', function () {
                setDefaultMonthRange();
                $('#npkSelect').val(null).trigger('change');
                $('#deptSelect').val(null).trigger('change');
                $('#componentSelect').val('total_salary').trigger('change');
                loadChartData();
            });

            // ---- Section 2 filters ----
            $('#detailPeriodSelect').select2({ minimumResultsForSearch: 0 });
            $('#detailNpkSelect').select2({
                placeholder: 'Semua Karyawan',
                allowClear: true,
                ajax: {
                    url: "{{ route('payroll-recap.search-employee') }}",
                    dataType: 'json',
                    delay: 300,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.results })
                },
                minimumInputLength: 0
            });
            $('#detailDeptSelect').select2({ placeholder: 'Semua Department', allowClear: true });
            $('#detailComponentSelect').select2({ minimumResultsForSearch: 0 });

            $('#detailFilterForm').on('submit', function (e) {
                e.preventDefault();
                loadDetailData();
            });
            $('#detailResetFilter').on('click', function () {
                $('#detailPeriodSelect').prop('selectedIndex', 0).trigger('change');
                $('#detailNpkSelect').val(null).trigger('change');
                $('#detailDeptSelect').val(null).trigger('change');
                $('#detailComponentSelect').val('total_salary').trigger('change');
                loadDetailData();
            });

            // ---- Section 3 filters ----
            $('#overtimePeriodSelect').select2({ minimumResultsForSearch: 0 });
            $('#overtimeDeptSelect').select2({ placeholder: 'Semua Department', allowClear: true });

            $('#overtimeFilterForm').on('submit', function (e) {
                e.preventDefault();
                loadOvertimeData();
            });
            $('#overtimeResetFilter').on('click', function () {
                $('#overtimePeriodSelect').prop('selectedIndex', 0).trigger('change');
                $('#overtimeDeptSelect').val(null).trigger('change');
                loadOvertimeData();
            });

            // ---- Lazy-load per tab: tiap section HANYA fetch AJAX saat tab-nya
            //      pertama kali dibuka, bukan sekaligus di awal seperti sebelumnya.
            const loadedTabs = { 1: false, 2: false, 3: false };

            // Section 1 adalah tab default yang aktif, jadi langsung load.
            loadChartData();
            loadedTabs[1] = true;

            $('#recapTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('href'); // '#section-2' / '#section-3'

                if (target === '#section-2' && !loadedTabs[2]) {
                    loadDetailData();
                    loadedTabs[2] = true;
                }

                if (target === '#section-3' && !loadedTabs[3]) {
                    loadOvertimeData();
                    loadedTabs[3] = true;
                }

                // Pastikan DataTable & Chart yang di-render saat tab tersembunyi
                // menyesuaikan ukuran begitu tab-nya terlihat.
                if (target === '#section-2') {
                    if (employeeTable) employeeTable.columns.adjust();
                    if (deptPayrollTable) deptPayrollTable.columns.adjust();
                }
                if (target === '#section-3') {
                    if (overtimeChart) overtimeChart.resize();
                    if (overtimeDeptTable) overtimeDeptTable.columns.adjust();
                }
            });
        });
    </script>
</body>

</html>