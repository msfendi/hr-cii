<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<style>
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

body{ background-color: #f4f6fb; }

.section-block{ margin-bottom: 2.75rem; }
.section-title{ display:flex; align-items:center; gap:.6rem; margin-bottom:1rem; }
.section-title .section-number{
    display:inline-flex; align-items:center; justify-content:center;
    width:2rem; height:2rem; border-radius:50%;
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%);
    color:#fff; font-weight:700; font-size:.95rem; flex-shrink:0;
}
.section-title h2{ font-size:1.1rem; font-weight:700; color:#3a3b45; margin:0; }

#recapTabs{ border-bottom: 2px solid #e3e6f0; margin-bottom: 1.75rem; }
#recapTabs .nav-link{
    border: none; border-bottom: 3px solid transparent;
    color: #858796; font-weight: 700; font-size: .92rem;
    padding: .75rem 1.1rem; display:flex; align-items:center; gap:.5rem;
}
#recapTabs .nav-link .tab-number{
    display:inline-flex; align-items:center; justify-content:center;
    width:1.6rem; height:1.6rem; border-radius:50%;
    background:#eaecf4; color:#5a5c69; font-size:.78rem; font-weight:700;
    flex-shrink:0; transition: background-color .15s ease, color .15s ease;
}
#recapTabs .nav-link:hover{ color: var(--pr-blue-dark); border-bottom-color: #d9dcec; }
#recapTabs .nav-link.active{ color: var(--pr-blue-dark); background: transparent; border-bottom-color: var(--pr-blue); }
#recapTabs .nav-link.active .tab-number{
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%); color:#fff;
}
.tab-pane .section-title{ display:none; }
.tab-pane .period-badge{ margin-left:.25rem; }

.card{ border: none; border-radius: .6rem; transition: box-shadow .2s ease, transform .2s ease; }
.card.shadow{ box-shadow: 0 .15rem 1.5rem 0 rgba(58,59,69,.1) !important; }
.card.shadow:hover{ box-shadow: 0 .3rem 2rem 0 rgba(58,59,69,.14) !important; }
.card-header{
    background: linear-gradient(180deg, #ffffff 0%, var(--pr-blue-lighter) 100%);
    border-bottom: 1px solid #eaecf4; border-radius: .6rem .6rem 0 0 !important;
}
.card-header h6{ font-size: .95rem; letter-spacing: .02em; }
.card-header h6 i{ color: var(--pr-blue); }

.filter-form label{ letter-spacing: .03em; text-transform: uppercase; font-size: .7rem; }
.btn-primary{
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%);
    border: none; box-shadow: 0 .125rem .5rem rgba(78,115,223,.35);
}
.btn-primary:hover{ background: linear-gradient(135deg, var(--pr-blue-dark) 0%, #234ac2 100%); }

.period-badge{
    display:inline-block; background: var(--pr-blue-light); color: var(--pr-blue-dark);
    font-weight:700; font-size:.75rem; padding:.25rem .6rem; border-radius:1rem;
}

.kpi-card{ position: relative; overflow: hidden; border-radius: .75rem; color: #fff; padding: 1.25rem 1.4rem; min-height: 108px; }
.kpi-card .kpi-icon{ position: absolute; right: -.5rem; bottom: -.75rem; font-size: 4.2rem; opacity: .18; }
.kpi-card .kpi-label{ font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; opacity: .85; font-weight: 700; }
.kpi-card .kpi-value{ font-size: 1.4rem; font-weight: 700; margin-top: .25rem; }
.kpi-card.kpi-primary{ background: linear-gradient(135deg, #4e73df 0%, #3f5fc9 60%, #2e59d9 100%); }
.kpi-card.kpi-success{ background: linear-gradient(135deg, #1cc88a 0%, #17b57a 60%, #13a06d 100%); }
.kpi-card.kpi-info   { background: linear-gradient(135deg, #36b9cc 0%, #2ba6b8 60%, #2093a4 100%); }
.kpi-card.kpi-warning{ background: linear-gradient(135deg, #f6c23e 0%, #eab90f 60%, #dda711 100%); }

.table thead th{
    background-color: var(--pr-blue-light); color: var(--pr-blue-dark);
    border-bottom: 2px solid var(--pr-blue) !important; font-size: .74rem;
    text-transform: uppercase; letter-spacing: .03em; font-weight: 700; vertical-align: middle;
}
.table-sm td, .table-sm th{ padding:.4rem .5rem; font-size:.82rem; }
.table tbody tr:hover{ background-color: var(--pr-blue-light); }
.table tfoot td, .table tfoot th{ background-color: #f4f6fb; border-top: 2px solid var(--pr-blue) !important; }
.dataTables_wrapper{ font-size:.85rem; }

.expat-row{ cursor:pointer; }
.expat-row:hover{ background-color: var(--pr-blue-light); }
.expat-row td:first-child{ color: var(--pr-blue-dark); font-weight: 600; }

.section-hint{ font-size: .74rem; color: #8a8fa3; }

.modal-header{
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%);
    color: #fff; border-radius: .5rem .5rem 0 0;
}
.modal-header .close{ color: #fff; opacity: .85; text-shadow: none; }
.modal-header .close:hover{ opacity: 1; color: #fff; }

.badge-days-danger{ background-color:#f8d7da; color:#c0293c; }
.badge-days-warning{ background-color:#fff3cd; color:#8a6d00; }
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
                            <i class="fas fa-globe-asia text-primary mr-2"></i>Dashboard Expat
                        </h1>
                    </div>

                    <ul class="nav nav-tabs" id="recapTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-link-1" data-toggle="tab" href="#section-1" role="tab">
                                <span class="tab-number">1</span> Rekap Biaya
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-2" data-toggle="tab" href="#section-2" role="tab">
                                <span class="tab-number">2</span> Detail per Expat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-3" data-toggle="tab" href="#section-3" role="tab">
                                <span class="tab-number">3</span> Dokumen &amp; Kepatuhan
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="recapTabsContent">

                    {{-- ===================== SECTION 1: REKAP BIAYA ===================== --}}
                    <div class="tab-pane fade show active section-block" id="section-1" role="tabpanel">
                        <div class="section-title"><span class="section-number">1</span><h2>Rekap Biaya</h2></div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filter</h6>
                            </div>
                            <div class="card-body">
                                <form id="filterForm" class="filter-form">
                                    <div class="row align-items-start">
                                        <div class="col-md-2 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Tahun</label>
                                            <select id="yearSelect" class="form-control" style="width:100%">
                                                @foreach ($years as $y)
                                                    <option value="{{ $y }}">{{ $y }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Expat (NPK)</label>
                                            <select id="npkSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Expat</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Kewarganegaraan</label>
                                            <select id="nationalitySelect" class="form-control" style="width:100%">
                                                <option value="">Semua</option>
                                                @foreach ($nationalities as $n)
                                                    <option value="{{ $n }}">{{ $n }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Jenis Biaya</label>
                                            <select id="costTypeSelect" class="form-control" style="width:100%">
                                                <option value="all">Semua (Direct + On Leave)</option>
                                                <option value="direct">Direct Cost</option>
                                                <option value="onleave">On Leave</option>
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

                        <div class="row">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="kpi-card kpi-primary shadow h-100">
                                    <div class="kpi-label">Total Biaya</div>
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
                                    <div class="kpi-label">Jumlah Transaksi</div>
                                    <div class="kpi-value" id="kpiTotalTransaksi">0</div>
                                    <i class="fas fa-receipt kpi-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-7 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">
                                            <i class="fas fa-chart-bar mr-1"></i> Tren Biaya Bulanan
                                            <span class="text-gray-500 font-weight-normal" id="rangeLabel"></span>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="chartEmpty" class="text-center py-5" style="display:none;">
                                            <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                            <p class="text-gray-500 mt-2 mb-0">Tidak ada data.</p>
                                        </div>
                                        <div style="height:350px"><canvas id="recapChart"></canvas></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table mr-1"></i> Rincian per Bulan</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
                                            <table class="table table-sm table-bordered mb-0 small">
                                                <thead>
                                                    <tr>
                                                        <th>Bulan</th>
                                                        <th class="text-right">Direct</th>
                                                        <th class="text-right">On Leave</th>
                                                        <th class="text-right">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="breakdownTableBody"></tbody>
                                                <tfoot>
                                                    <tr class="font-weight-bold">
                                                        <td>Grand Total</td>
                                                        <td class="text-right" id="footerDirectTotal">Rp 0</td>
                                                        <td class="text-right" id="footerOnleaveTotal">Rp 0</td>
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

                    {{-- ===================== SECTION 2: DETAIL PER EXPAT ===================== --}}
                    <div class="tab-pane fade section-block" id="section-2" role="tabpanel">
                        <div class="section-title">
                            <span class="section-number">2</span><h2>Detail per Expat</h2>
                            <span class="period-badge" id="detailPeriodLabel">-</span>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filter</h6>
                            </div>
                            <div class="card-body">
                                <form id="detailFilterForm" class="filter-form">
                                    <div class="row align-items-start">
                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Tahun</label>
                                            <select id="detailYearSelect" class="form-control" style="width:100%">
                                                @foreach ($years as $y)
                                                    <option value="{{ $y }}">{{ $y }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Expat (NPK)</label>
                                            <select id="detailNpkSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Expat</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Kewarganegaraan</label>
                                            <select id="detailNationalitySelect" class="form-control" style="width:100%">
                                                <option value="">Semua</option>
                                                @foreach ($nationalities as $n)
                                                    <option value="{{ $n }}">{{ $n }}</option>
                                                @endforeach
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
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie mr-1"></i> Biaya per Komponen</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="componentChartEmpty" class="text-center py-5" style="display:none;">
                                            <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                            <p class="text-gray-500 mt-2 mb-0">Belum ada data.</p>
                                        </div>
                                        <div style="height:280px"><canvas id="componentChart"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plane-departure mr-1"></i> On Leave per Tipe</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="onleaveChartEmpty" class="text-center py-5" style="display:none;">
                                            <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                            <p class="text-gray-500 mt-2 mb-0">Belum ada data.</p>
                                        </div>
                                        <div style="height:280px"><canvas id="onleaveTypeChart"></canvas></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users mr-1"></i> Rekap Biaya per Expat</h6>
                                <small class="section-hint">Klik baris untuk melihat rincian transaksi.</small>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 small" id="expatRecapTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>NPK</th>
                                                <th>Nama</th>
                                                <th>Posisi</th>
                                                <th>Kewarganegaraan</th>
                                                <th>Status</th>
                                                <th class="text-right">Direct Cost</th>
                                                <th class="text-right">On Leave</th>
                                                <th class="text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot>
                                            <tr class="font-weight-bold">
                                                <td colspan="5">Grand Total</td>
                                                <td class="text-right" id="recapFooterDirect">Rp 0</td>
                                                <td class="text-right" id="recapFooterOnleave">Rp 0</td>
                                                <td class="text-right" id="recapFooterTotal">Rp 0</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== SECTION 3: DOKUMEN & KEPATUHAN ===================== --}}
                    <div class="tab-pane fade section-block" id="section-3" role="tabpanel">
                        <div class="section-title"><span class="section-number">3</span><h2>Dokumen &amp; Kepatuhan</h2></div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filter</h6>
                            </div>
                            <div class="card-body">
                                <form id="docFilterForm" class="filter-form">
                                    <div class="row align-items-start">
                                        <div class="col-md-4 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Ambang Batas (Hari)</label>
                                            <select id="daysSelect" class="form-control" style="width:100%">
                                                <option value="7">7 Hari</option>
                                                <option value="30" selected>30 Hari</option>
                                                <option value="60">60 Hari</option>
                                                <option value="90">90 Hari</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Kewarganegaraan</label>
                                            <select id="docNationalitySelect" class="form-control" style="width:100%">
                                                <option value="">Semua</option>
                                                @foreach ($nationalities as $n)
                                                    <option value="{{ $n }}">{{ $n }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <button type="submit" class="btn btn-primary shadow-sm mr-2">
                                            <i class="fas fa-search fa-sm mr-1"></i> Terapkan Filter
                                        </button>
                                        <button type="button" id="docResetFilter" class="btn btn-outline-secondary shadow-sm">
                                            <i class="fas fa-undo fa-sm mr-1"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-primary shadow h-100">
                                    <div class="kpi-label">Total Expat</div>
                                    <div class="kpi-value" id="kpiTotalExpat">0</div>
                                    <i class="fas fa-users kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-success shadow h-100">
                                    <div class="kpi-label">Expat Aktif</div>
                                    <div class="kpi-value" id="kpiActiveExpat">0</div>
                                    <i class="fas fa-user-check kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-warning shadow h-100">
                                    <div class="kpi-label">Dokumen Akan Expired</div>
                                    <div class="kpi-value" id="kpiExpiringCount">0</div>
                                    <i class="fas fa-exclamation-triangle kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-info shadow h-100">
                                    <div class="kpi-label">Jenis Dokumen Terdampak</div>
                                    <div class="kpi-value" id="kpiDocTypesAffected">0</div>
                                    <i class="fas fa-file-alt kpi-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-4 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar mr-1"></i> Dokumen per Jenis</h6>
                                    </div>
                                    <div class="card-body">
                                        <div style="height:280px"><canvas id="docTypeChart"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-clock mr-1"></i> Daftar Dokumen Akan Expired</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered table-hover mb-0 small" id="docTable" width="100%">
                                                <thead>
                                                    <tr>
                                                        <th>NPK</th>
                                                        <th>Nama</th>
                                                        <th>Jenis Dokumen</th>
                                                        <th>Tanggal Expired</th>
                                                        <th class="text-right">Sisa Hari</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
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
            @include('layout.footer')
        </div>
    </div>

    {{-- ===================== MODAL: DETAIL TRANSAKSI PER EXPAT ===================== --}}
    <div class="modal fade" id="expatDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="expatDetailModalLabel"><i class="fas fa-receipt mr-1"></i> Rincian Transaksi</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <h6 class="font-weight-bold text-primary small text-uppercase">Direct Cost</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered mb-0 small">
                            <thead>
                                <tr><th>Tanggal</th><th>Komponen</th><th class="text-right">Jumlah</th><th>Keterangan</th></tr>
                            </thead>
                            <tbody id="modalDirectBody"></tbody>
                        </table>
                    </div>

                    <h6 class="font-weight-bold text-primary small text-uppercase">On Leave</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 small">
                            <thead>
                                <tr><th>Tanggal</th><th>Tipe</th><th>Komponen</th><th class="text-right">Jumlah</th></tr>
                            </thead>
                            <tbody id="modalOnleaveBody"></tbody>
                        </table>
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

        const rupiah = (value) => new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', minimumFractionDigits: 0
        }).format(value || 0);

        function showLoadingSwal(msg = 'Memuat data...') {
            Swal.fire({
                title: msg, allowOutsideClick: false, allowEscapeKey: false,
                showConfirmButton: false, didOpen: () => Swal.showLoading(),
            });
        }
        function hideLoadingSwal() { Swal.close(); }

        function formatMonthIndonesia(value) {
            if (!value) return '';
            if (/^\d{4}-\d{2}$/.test(value)) {
                const [y, m] = value.split('-');
                const bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                return bulan[parseInt(m) - 1] + ' ' + y;
            }
            return value;
        }

        function formatDateIndo(value) {
            if (!value) return '-';
            const d = new Date(value);
            if (isNaN(d)) return value;
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
        }

        function employeeSelect2Config() {
            return {
                placeholder: 'Semua Expat',
                allowClear: true,
                ajax: {
                    url: "{{ route('expat-dashboard.search-employee') }}",
                    dataType: 'json',
                    delay: 300,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.results })
                },
                minimumInputLength: 0
            };
        }

        /* =====================================================================
         * SECTION 1: REKAP BIAYA
         * ===================================================================== */

        let recapChart = null;

        function loadChartData() {
            const params = {
                year: $('#yearSelect').val(),
                npk: $('#npkSelect').val(),
                nationality: $('#nationalitySelect').val(),
                cost_type: $('#costTypeSelect').val(),
            };
            showLoadingSwal('Memuat rekap biaya...');

            fetch("{{ route('expat-dashboard.chart-data') }}?" + new URLSearchParams(params))
                .then(res => res.json())
                .then(data => {
                    const hasData = data.values.some(v => v > 0);
                    $('#chartEmpty').toggle(!hasData);

                    $('#kpiGrandTotal').text(rupiah(data.grand_total));
                    $('#kpiAvgMonth').text(rupiah(data.avg_per_month));
                    $('#kpiTotalTransaksi').text(data.total_transaksi);
                    $('#rangeLabel').text('(' + formatMonthIndonesia(data.range.start) + ' s/d ' + formatMonthIndonesia(data.range.end) + ')');

                    if (recapChart) recapChart.destroy();
                    const ctx = document.getElementById('recapChart').getContext('2d');

                    recapChart = new Chart(ctx, {
                        data: {
                            labels: data.labels.map(l => formatMonthIndonesia(l)),
                            datasets: [
                                {
                                    type: 'bar', label: 'Direct Cost', data: data.direct_values,
                                    backgroundColor: 'rgba(78,115,223,0.85)', hoverBackgroundColor: '#2e59d9',
                                    borderRadius: 6, maxBarThickness: 22, stack: 'cost',
                                },
                                {
                                    type: 'bar', label: 'On Leave', data: data.onleave_values,
                                    backgroundColor: 'rgba(54,185,204,0.85)', hoverBackgroundColor: '#2093a4',
                                    borderRadius: 6, maxBarThickness: 22, stack: 'cost',
                                },
                                {
                                    type: 'line', label: 'Total', data: data.values,
                                    borderColor: '#e74a3b', backgroundColor: '#e74a3b',
                                    borderWidth: 2.5, tension: .35, pointRadius: 3,
                                    pointBackgroundColor: '#e74a3b', pointBorderColor: '#fff', pointBorderWidth: 1.5,
                                    fill: false,
                                }
                            ]
                        },
                        options: {
                            maintainAspectRatio: false,
                            layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: { stacked: true, grid: { display: false, drawBorder: false } },
                                y: {
                                    stacked: true,
                                    ticks: { callback: (val) => rupiah(val) },
                                    grid: { color: "rgb(234, 236, 244)", drawBorder: false, borderDash: [3] }
                                },
                            },
                            plugins: {
                                legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16 } },
                                tooltip: {
                                    backgroundColor: 'rgb(255,255,255)', titleColor: '#6e707e', bodyColor: '#5a5c69',
                                    borderColor: '#dddfeb', borderWidth: 1, padding: 12, cornerRadius: 8,
                                    callbacks: { label: (ctx) => `${ctx.dataset.label}: ${rupiah(ctx.parsed.y)}` }
                                }
                            }
                        }
                    });

                    let rows = '';
                    data.labels.forEach((label, i) => {
                        rows += `<tr>
                            <td>${formatMonthIndonesia(label)}</td>
                            <td class="text-right">${rupiah(data.direct_values[i])}</td>
                            <td class="text-right">${rupiah(data.onleave_values[i])}</td>
                            <td class="text-right font-weight-bold">${rupiah(data.values[i])}</td>
                        </tr>`;
                    });
                    $('#breakdownTableBody').html(rows);

                    $('#footerDirectTotal').text(rupiah(data.direct_values.reduce((a, b) => a + b, 0)));
                    $('#footerOnleaveTotal').text(rupiah(data.onleave_values.reduce((a, b) => a + b, 0)));
                    $('#footerTotal').text(rupiah(data.grand_total));
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data.', 'error'))
                .finally(() => hideLoadingSwal());
        }

        /* =====================================================================
         * SECTION 2: DETAIL PER EXPAT
         * ===================================================================== */

        let expatRecapTable = null;
        let componentChart = null;
        let onleaveTypeChart = null;

        function initExpatRecapTable() {
            expatRecapTable = $('#expatRecapTable').DataTable({
                data: [],
                columns: [
                    { data: 'npk', title: 'NPK' },
                    { data: 'name', title: 'Nama' },
                    { data: 'position', title: 'Posisi', defaultContent: '-' },
                    { data: 'nationality', title: 'Kewarganegaraan', defaultContent: '-' },
                    {
                        data: 'status', title: 'Status', className: 'text-center',
                        render: (data) => data === 'aktif'
                            ? '<span class="badge badge-success">Aktif</span>'
                            : '<span class="badge badge-secondary">Non-Aktif</span>'
                    },
                    { data: 'direct_cost', title: 'Direct Cost', className: 'text-right', render: (d, type) => type === 'display' ? rupiah(d) : d },
                    { data: 'onleave_cost', title: 'On Leave', className: 'text-right', render: (d, type) => type === 'display' ? rupiah(d) : d },
                    { data: 'total_cost', title: 'Total', className: 'text-right font-weight-bold', render: (d, type) => type === 'display' ? rupiah(d) : d },
                ],
                order: [[7, 'desc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                createdRow: function (row, data) {
                    $(row).addClass('expat-row').attr('data-npk', data.npk).attr('data-name', data.name);
                },
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ expat', infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total data)', zeroRecords: 'Tidak ada expat yang cocok',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
                }
            });
        }

        function renderComponentChart(costByComponent) {
            if (componentChart) componentChart.destroy();
            $('#componentChartEmpty').toggle(!costByComponent.length);
            if (!costByComponent.length) return;

            componentChart = new Chart(document.getElementById('componentChart'), {
                type: 'doughnut',
                data: {
                    labels: costByComponent.map(c => c.name),
                    datasets: [{
                        data: costByComponent.map(c => c.total),
                        backgroundColor: ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#5a5c69','#2e59d9']
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${rupiah(ctx.parsed)}` } }
                    }
                }
            });
        }

        function renderOnleaveTypeChart(onleaveByType) {
            if (onleaveTypeChart) onleaveTypeChart.destroy();
            $('#onleaveChartEmpty').toggle(!onleaveByType.length);
            if (!onleaveByType.length) return;

            onleaveTypeChart = new Chart(document.getElementById('onleaveTypeChart'), {
                type: 'pie',
                data: {
                    labels: onleaveByType.map(o => o.leave_type),
                    datasets: [{
                        data: onleaveByType.map(o => o.total),
                        backgroundColor: ['#f6c23e','#e74a3b','#4e73df','#1cc88a','#36b9cc']
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }
                }
            });
        }

        function loadDetailData() {
            const params = {
                year: $('#detailYearSelect').val(),
                npk: $('#detailNpkSelect').val(),
                nationality: $('#detailNationalitySelect').val(),
            };
            showLoadingSwal('Memuat detail per expat...');

            fetch("{{ route('expat-dashboard.recap-data') }}?" + new URLSearchParams(params))
                .then(res => res.json())
                .then(data => {
                    $('#detailPeriodLabel').text('Tahun ' + params.year);

                    expatRecapTable.clear();
                    expatRecapTable.rows.add(data.recap);
                    expatRecapTable.draw();

                    $('#recapFooterDirect').text(rupiah(data.grand_direct));
                    $('#recapFooterOnleave').text(rupiah(data.grand_onleave));
                    $('#recapFooterTotal').text(rupiah(data.grand_direct + data.grand_onleave));

                    renderComponentChart(data.cost_by_component);
                    renderOnleaveTypeChart(data.onleave_by_type);
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data.', 'error'))
                .finally(() => hideLoadingSwal());
        }

        $(document).on('click', '.expat-row', function () {
            const npk = $(this).data('npk');
            const name = $(this).data('name');
            const year = $('#detailYearSelect').val();

            showLoadingSwal('Memuat rincian transaksi...');
            fetch("{{ route('expat-dashboard.recap-data') }}/../transaction-detail?" + new URLSearchParams({ npk, year }))
                .then(res => res.json())
                .then(data => {
                    $('#expatDetailModalLabel').html(`<i class="fas fa-receipt mr-1"></i> Rincian Transaksi - ${name}`);

                    let directRows = '';
                    if (!data.direct.length) {
                        directRows = '<tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>';
                    } else {
                        data.direct.forEach(d => {
                            directRows += `<tr>
                                <td>${formatDateIndo(d.tanggal)}</td>
                                <td>${d.komponen}</td>
                                <td class="text-right">${rupiah(d.amount)}</td>
                                <td>${d.remark || '-'}</td>
                            </tr>`;
                        });
                    }
                    $('#modalDirectBody').html(directRows);

                    let onleaveRows = '';
                    if (!data.onleave.length) {
                        onleaveRows = '<tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>';
                    } else {
                        data.onleave.forEach(o => {
                            onleaveRows += `<tr>
                                <td>${formatDateIndo(o.tanggal)}</td>
                                <td>${o.leave_type}</td>
                                <td>${o.komponen}</td>
                                <td class="text-right">${rupiah(o.amount)}</td>
                            </tr>`;
                        });
                    }
                    $('#modalOnleaveBody').html(onleaveRows);

                    $('#expatDetailModal').modal('show');
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data.', 'error'))
                .finally(() => hideLoadingSwal());
        });

        /* =====================================================================
         * SECTION 3: DOKUMEN & KEPATUHAN
         * ===================================================================== */

        let docTable = null;
        let docTypeChart = null;

        function initDocTable() {
            docTable = $('#docTable').DataTable({
                data: [],
                columns: [
                    { data: 'npk', title: 'NPK' },
                    { data: 'name', title: 'Nama' },
                    { data: 'doc_type', title: 'Jenis Dokumen' },
                    { data: 'expiry_date', title: 'Tanggal Expired', render: (d) => formatDateIndo(d) },
                    {
                        data: 'days_left', title: 'Sisa Hari', className: 'text-right',
                        render: (d) => {
                            const cls = d <= 7 ? 'badge-danger' : 'badge-warning';
                            return `<span class="badge ${cls}">${d} hari</span>`;
                        }
                    },
                ],
                order: [[4, 'asc']],
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ dokumen', infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total data)', zeroRecords: 'Tidak ada dokumen yang cocok',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
                }
            });
        }

        function renderDocTypeChart(countByType) {
            if (docTypeChart) docTypeChart.destroy();
            const labels = Object.keys(countByType);
            const values = Object.values(countByType);

            docTypeChart = new Chart(document.getElementById('docTypeChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Jumlah Dokumen',
                        data: values,
                        backgroundColor: 'rgba(246,194,62,0.85)',
                        hoverBackgroundColor: '#dda711',
                        borderRadius: 6,
                        maxBarThickness: 32,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false, drawBorder: false } },
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgb(234, 236, 244)', drawBorder: false, borderDash: [3] } }
                    }
                }
            });
        }

        function loadDocumentData() {
            const params = {
                days: $('#daysSelect').val(),
                nationality: $('#docNationalitySelect').val(),
            };
            showLoadingSwal('Memuat data dokumen...');

            fetch("{{ route('expat-dashboard.document-data') }}?" + new URLSearchParams(params))
                .then(res => res.json())
                .then(data => {
                    $('#kpiTotalExpat').text(data.total_expat);
                    $('#kpiActiveExpat').text(data.total_active);
                    $('#kpiExpiringCount').text(data.expiring_count);
                    $('#kpiDocTypesAffected').text(Object.values(data.count_by_type).filter(v => v > 0).length);

                    docTable.clear();
                    docTable.rows.add(data.expiring);
                    docTable.draw();

                    renderDocTypeChart(data.count_by_type);
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data.', 'error'))
                .finally(() => hideLoadingSwal());
        }

        /* =====================================================================
         * INIT
         * ===================================================================== */

        $(document).ready(function () {
            initExpatRecapTable();
            initDocTable();

            // Section 1
            $('#yearSelect').select2({ minimumResultsForSearch: 0 });
            $('#npkSelect').select2(employeeSelect2Config());
            $('#nationalitySelect').select2({ placeholder: 'Semua', allowClear: true });
            $('#costTypeSelect').select2({ minimumResultsForSearch: 0 });

            $('#filterForm').on('submit', function (e) { e.preventDefault(); loadChartData(); });
            $('#resetFilter').on('click', function () {
                $('#yearSelect').prop('selectedIndex', 0).trigger('change');
                $('#npkSelect').val(null).trigger('change');
                $('#nationalitySelect').val(null).trigger('change');
                $('#costTypeSelect').val('all').trigger('change');
                loadChartData();
            });

            // Section 2
            $('#detailYearSelect').select2({ minimumResultsForSearch: 0 });
            $('#detailNpkSelect').select2(employeeSelect2Config());
            $('#detailNationalitySelect').select2({ placeholder: 'Semua', allowClear: true });

            $('#detailFilterForm').on('submit', function (e) { e.preventDefault(); loadDetailData(); });
            $('#detailResetFilter').on('click', function () {
                $('#detailYearSelect').prop('selectedIndex', 0).trigger('change');
                $('#detailNpkSelect').val(null).trigger('change');
                $('#detailNationalitySelect').val(null).trigger('change');
                loadDetailData();
            });

            // Section 3
            $('#daysSelect').select2({ minimumResultsForSearch: 0 });
            $('#docNationalitySelect').select2({ placeholder: 'Semua', allowClear: true });

            $('#docFilterForm').on('submit', function (e) { e.preventDefault(); loadDocumentData(); });
            $('#docResetFilter').on('click', function () {
                $('#daysSelect').val('30').trigger('change');
                $('#docNationalitySelect').val(null).trigger('change');
                loadDocumentData();
            });

            // Lazy-load per tab
            const loadedTabs = { 1: false, 2: false, 3: false };
            loadChartData();
            loadedTabs[1] = true;

            $('#recapTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('href');

                if (target === '#section-2' && !loadedTabs[2]) { loadDetailData(); loadedTabs[2] = true; }
                if (target === '#section-3' && !loadedTabs[3]) { loadDocumentData(); loadedTabs[3] = true; }

                if (target === '#section-2') { if (expatRecapTable) expatRecapTable.columns.adjust(); if (componentChart) componentChart.resize(); if (onleaveTypeChart) onleaveTypeChart.resize(); }
                if (target === '#section-3') { if (docTable) docTable.columns.adjust(); if (docTypeChart) docTypeChart.resize(); }
            });
        });
    </script>
</body>
</html>