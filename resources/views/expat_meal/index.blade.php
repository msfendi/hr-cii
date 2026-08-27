<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css">
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

#mealTabs{ border-bottom: 2px solid #e3e6f0; margin-bottom: 1.75rem; }
#mealTabs .nav-link{
    border: none; border-bottom: 3px solid transparent;
    color: #858796; font-weight: 700; font-size: .92rem;
    padding: .75rem 1.1rem; display:flex; align-items:center; gap:.5rem;
}
#mealTabs .nav-link .tab-number{
    display:inline-flex; align-items:center; justify-content:center;
    width:1.6rem; height:1.6rem; border-radius:50%;
    background:#eaecf4; color:#5a5c69; font-size:.78rem; font-weight:700;
    flex-shrink:0; transition: background-color .15s ease, color .15s ease;
}
#mealTabs .nav-link:hover{ color: var(--pr-blue-dark); border-bottom-color: #d9dcec; }
#mealTabs .nav-link.active{ color: var(--pr-blue-dark); background: transparent; border-bottom-color: var(--pr-blue); }
#mealTabs .nav-link.active .tab-number{
    background: linear-gradient(135deg, var(--pr-blue) 0%, var(--pr-blue-dark) 100%); color:#fff;
}
.tab-pane .section-title{ display:none; }

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

.section-hint{ font-size: .74rem; color: #8a8fa3; }

.badge-kategori-sarapan{ background-color:#fff3cd; color:#8a6d00; }
.badge-kategori-siang{ background-color:#d4edda; color:#1c7a3c; }
.badge-shared-yes{ background-color:#d4edda; color:#1c7a3c; }
.badge-shared-no{ background-color:#eaecf4; color:#5a5c69; }

.meal-detail-section-label{
    font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em;
    color: var(--pr-blue-dark); margin-bottom:.5rem; display:flex; align-items:center;
}
#mealDetailExpatTable tbody td:last-child{ font-weight:700; }
.expat-cost-pill{
    display:inline-flex; align-items:center; gap:.3rem;
    background: var(--pr-blue-lighter); border:1px solid #e3e6f0; border-radius:1rem;
    padding:.15rem .55rem; margin:.12rem .2rem .12rem 0; font-size:.76rem; color:#3a3b45;
}
.expat-cost-pill .pill-price{ font-weight:700; color: var(--pr-blue-dark); }
</style>
<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                @include('layout.navbar')

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4 flex-wrap">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-utensils text-primary mr-2"></i>Dashboard Makan Expat
                        </h1>
                        <div class="d-flex flex-wrap mt-2 mt-sm-0" style="gap:.5rem">
                            <a href="{{ route('expat-meal.template') }}" class="btn btn-sm btn-info shadow-sm">
                                <i class="fas fa-download fa-sm"></i> Download Template
                            </a>
                            <button type="button" class="btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#importModal">
                                <i class="fas fa-upload fa-sm"></i> Import Data
                            </button>
                            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#exportExcelModal">
                                <i class="fas fa-file-excel fa-sm"></i> Export Laporan
                            </button>
                        </div>
                    </div>

                    <ul class="nav nav-tabs" id="mealTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-link-1" data-toggle="tab" href="#section-1" role="tab">
                                <span class="tab-number">1</span> Laporan &amp; Biaya
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-2" data-toggle="tab" href="#section-2" role="tab">
                                <span class="tab-number">2</span> Daftar Peserta Makan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-3" data-toggle="tab" href="#section-3" role="tab">
                                <span class="tab-number">3</span> Menu Makanan
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="mealTabsContent">

                    {{-- ===================== SECTION 1: LAPORAN & BIAYA ===================== --}}
                    <div class="tab-pane fade show active section-block" id="section-1" role="tabpanel">
                        <div class="section-title"><span class="section-number">1</span><h2>Laporan &amp; Biaya</h2></div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> Filter</h6>
                            </div>
                            <div class="card-body">
                                <form id="filterForm" class="filter-form">
                                    <div class="row align-items-start">
                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Tanggal Mulai</label>
                                            <input type="date" id="startDate" class="form-control" value="{{ $defaultStartDate }}">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Tanggal Selesai</label>
                                            <input type="date" id="endDate" class="form-control" value="{{ $defaultEndDate }}">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Expat</label>
                                            <select id="filterNpk" class="form-control">
                                                <option value=""></option>
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
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-primary shadow h-100">
                                    <div class="kpi-label">Total Biaya</div>
                                    <div class="kpi-value" id="kpiTotalBiaya">Rp 0</div>
                                    <div class="small" style="opacity:.85">Periode terpilih</div>
                                    <i class="fas fa-coins kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-info shadow h-100">
                                    <div class="kpi-label">Jumlah Expat</div>
                                    <div class="kpi-value" id="kpiTotalExpat">0</div>
                                    <div class="small" style="opacity:.85"></div>
                                    <i class="fas fa-user-tie kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-success shadow h-100">
                                    <div class="kpi-label">Jumlah Hari</div>
                                    <div class="kpi-value" id="kpiTotalHari">0</div>
                                    <div class="small" style="opacity:.85">Hari tercatat makan</div>
                                    <i class="fas fa-calendar-days kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-warning shadow h-100">
                                    <div class="kpi-label">Total Porsi</div>
                                    <div class="kpi-value" id="kpiTotalPorsi">0</div>
                                    <div class="small" style="opacity:.85">Sarapan + Makan Siang</div>
                                    <i class="fas fa-bowl-food kpi-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-7 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar mr-1"></i> Tren Biaya Makan per Tanggal</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="chartEmpty" class="text-center py-5" style="display:none;">
                                            <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                            <p class="text-gray-500 mt-2 mb-0">Tidak ada data.</p>
                                        </div>
                                        <div style="height:320px"><canvas id="recapChart"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie mr-1"></i> Sarapan vs Makan Siang</h6>
                                    </div>
                                    <div class="card-body">
                                        <div style="height:320px"><canvas id="kategoriChart"></canvas></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table mr-1"></i> Detail Biaya per Hari</h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 small" id="detailTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th class="text-right">Jml Expat Sarapan</th>
                                                <th>Menu Sarapan</th>
                                                <th class="text-right">Harga Sarapan</th>
                                                <th class="text-right">Jml Expat Makan Siang</th>
                                                <th>Menu Makan Siang</th>
                                                <th class="text-right">Harga Makan Siang</th>
                                                <th class="text-right">Total</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== SECTION 2: DAFTAR PESERTA MAKAN ===================== --}}
                    <div class="tab-pane fade section-block" id="section-2" role="tabpanel">
                        <div class="section-title"><span class="section-number">2</span><h2>Daftar Peserta Makan</h2></div>

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:.5rem">
                            <small class="section-hint mb-0">Data mentah hasil import sheet "Daftar Expat". Gunakan filter tanggal pada tab 1.</small>
                            <button type="button" class="btn btn-sm btn-primary shadow-sm" id="btnAddParticipant" data-toggle="modal" data-target="#participantModal">
                                <i class="fas fa-user-plus fa-sm"></i> Tambah Peserta
                            </button>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 small" id="participantTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>NPK</th>
                                                <th>Nama Expat</th>
                                                <th>Kategori</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== SECTION 3: MENU MAKANAN ===================== --}}
                    <div class="tab-pane fade section-block" id="section-3" role="tabpanel">
                        <div class="section-title"><span class="section-number">3</span><h2>Menu Makanan</h2></div>

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:.5rem">
                            <small class="section-hint mb-0">
                                Master menu &amp; harga (hasil import sheet "Makanan"). Menu dengan <strong>Shared = Ya</strong>
                                otomatis dihitung sebagai biaya makan bersama pada tab Laporan &amp; Biaya.
                            </small>
                            <button type="button" class="btn btn-sm btn-primary shadow-sm" id="btnAddMenu" data-toggle="modal" data-target="#menuModal">
                                <i class="fas fa-plus fa-sm"></i> Tambah Menu
                            </button>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 small" id="menuTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Makanan</th>
                                                <th>Kategori</th>
                                                <th class="text-right">Harga</th>
                                                <th>Shared</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    </div>

                    <!-- PARTICIPANT ADD/EDIT MODAL -->
                    <div class="modal fade" id="participantModal" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form id="participantForm">
                            <input type="hidden" id="participantId" name="id">
                            <div class="modal-header">
                              <h5 class="modal-title" id="participantModalTitle"><i class="fas fa-user-plus mr-1"></i> Tambah Peserta Makan</h5>
                              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                              <div class="form-group">
                                <label class="small font-weight-bold text-gray-600">NPK</label>
                                <input type="text" id="participantNpk" name="npk" class="form-control" required>
                              </div>
                              <div class="form-group">
                                <label class="small font-weight-bold text-gray-600">Nama Expat</label>
                                <input type="text" id="participantNama" name="nama_expat" class="form-control" required>
                              </div>
                              <div class="form-row">
                                <div class="form-group col-md-6">
                                  <label class="small font-weight-bold text-gray-600">Tanggal</label>
                                  <input type="date" id="participantTanggal" name="tanggal" class="form-control" value="{{ $defaultEndDate }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                  <label class="small font-weight-bold text-gray-600">Kategori</label>
                                  <select id="participantKategori" name="kategori" class="form-control" required>
                                    @foreach ($kategoriList as $k)
                                        <option value="{{ $k }}">{{ $k }}</option>
                                    @endforeach
                                  </select>
                                </div>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                              <button type="submit" class="btn btn-primary"><i class="fas fa-save fa-sm"></i> Simpan</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- MENU ADD/EDIT MODAL -->
                    <div class="modal fade" id="menuModal" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form id="menuForm">
                            <input type="hidden" id="menuId" name="id">
                            <div class="modal-header">
                              <h5 class="modal-title" id="menuModalTitle"><i class="fas fa-plus mr-1"></i> Tambah Menu Makanan</h5>
                              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                              <div class="form-group">
                                <label class="small font-weight-bold text-gray-600">Nama Makanan</label>
                                <input type="text" id="menuMakanan" name="makanan" class="form-control" required>
                              </div>
                              <div class="form-row">
                                <div class="form-group col-md-6">
                                  <label class="small font-weight-bold text-gray-600">Tanggal</label>
                                  <input type="date" id="menuTanggal" name="tanggal" class="form-control" value="{{ $defaultEndDate }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                  <label class="small font-weight-bold text-gray-600">Kategori</label>
                                  <select id="menuKategori" name="kategori" class="form-control" required>
                                    @foreach ($kategoriList as $k)
                                        <option value="{{ $k }}">{{ $k }}</option>
                                    @endforeach
                                  </select>
                                </div>
                              </div>
                              <div class="form-group">
                                <label class="small font-weight-bold text-gray-600">Harga (Rp)</label>
                                <input type="number" id="menuHarga" name="harga" class="form-control" min="0" step="1" required>
                              </div>
                              <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="menuShared" name="shared">
                                <label class="form-check-label small font-weight-bold text-gray-600" for="menuShared">
                                    Shared (otomatis dihitung sebagai biaya makan bersama)
                                </label>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                              <button type="submit" class="btn btn-primary"><i class="fas fa-save fa-sm"></i> Simpan</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- IMPORT MODAL -->
                    <div class="modal fade" id="importModal" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form id="importForm" enctype="multipart/form-data">
                            @csrf
                            <div class="modal-header">
                              <h5 class="modal-title"><i class="fas fa-upload mr-1"></i> Import Data Makan Expat</h5>
                              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                              <p class="small text-muted mb-3">
                                Gunakan template excel (2 sheet): <strong>Daftar Expat</strong> (npk, nama_expat, tanggal, kategori)
                                dan <strong>Makanan</strong> (tanggal, makanan, kategori, harga, shared). Kedua sheet diimport sekaligus dari satu file.
                              </p>
                              <div class="form-group">
                                <label class="small font-weight-bold text-gray-600">File Excel</label>
                                <input type="file" name="file" id="importFile" class="form-control-file" accept=".xlsx,.xls,.csv" required>
                              </div>
                              <div class="progress mt-2" style="height:16px; display:none;" id="importProgress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%" id="importProgressBar">0%</div>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                              <button type="submit" class="btn btn-success"><i class="fas fa-file-excel fa-sm"></i> Import</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- EXPORT EXCEL MODAL -->
                    <div class="modal fade" id="exportExcelModal" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form id="exportExcelForm" action="{{ route('expat-meal.export-excel') }}" method="GET" target="_blank">
                            <div class="modal-header">
                              <h5 class="modal-title"><i class="fas fa-file-excel mr-1"></i> Export Laporan</h5>
                              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                              <p class="small text-muted mb-3">
                                File excel berisi 2 sheet: <strong>Laporan Makan Expat</strong> (rekap biaya per hari)
                                dan <strong>Rincian per Expat</strong> (rincian & total biaya tiap expat, khusus menu shared).
                              </p>
                              <div class="form-row">
                                <div class="form-group col-md-6">
                                  <label class="small font-weight-bold text-gray-600">Tanggal Mulai</label>
                                  <input type="date" name="start_date" class="form-control" value="{{ $defaultStartDate }}" required>
                                </div>
                                <div class="form-group col-md-6">
                                  <label class="small font-weight-bold text-gray-600">Tanggal Selesai</label>
                                  <input type="date" name="end_date" class="form-control" value="{{ $defaultEndDate }}" required>
                                </div>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                              <button type="submit" class="btn btn-success"><i class="fas fa-download fa-sm"></i> Export Excel</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- DETAIL MAKANAN MODAL -->
                    <div class="modal fade" id="mealDetailModal" tabindex="-1">
                      <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-utensils mr-1"></i> Detail Makanan
                                <small class="d-block text-muted font-weight-normal" id="mealDetailSubtitle"></small>
                            </h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                          </div>
                          <div class="modal-body p-3">
                            <div id="mealDetailEmpty" class="text-center py-4" style="display:none;">
                                <i class="fas fa-inbox fa-2x text-gray-300"></i>
                                <p class="text-gray-500 mt-2 mb-0">Tidak ada data makanan.</p>
                            </div>

                            <div id="mealDetailContent">
                                <div class="meal-detail-section-label">
                                    <i class="fas fa-user-tie mr-1"></i> Total per Expat
                                    <span class="text-muted font-weight-normal ml-1" style="text-transform:none; letter-spacing:normal;">(dari menu shared)</span>
                                </div>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-bordered mb-0 small" id="mealDetailExpatTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Nama Expat</th>
                                                <th class="text-right">Total Biaya</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                                <div class="meal-detail-section-label">
                                    <i class="fas fa-list-ul mr-1"></i> Biaya per Expat Tiap Menu
                                    <span class="text-muted font-weight-normal ml-1" style="text-transform:none; letter-spacing:normal;">(rincian per expat hanya untuk menu shared)</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 small" id="mealDetailTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Kategori</th>
                                                <th>Makanan</th>
                                                <th class="text-right">Harga</th>
                                                <th class="text-center">Shared</th>
                                                <th class="text-center">Jml Expat</th>
                                                <th>Rincian per Expat</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="2" class="text-right">Total</th>
                                                <th class="text-right" id="mealDetailTotal">Rp 0</th>
                                                <th colspan="3"></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                          </div>
                        </div>
                      </div>
                    </div>

                @include('layout.footer')
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let recapChart = null;
        let kategoriChart = null;
        let detailTable = null;
        let participantTable = null;
        let menuTable = null;

        const loadedTabs = { 1: true, 2: false, 3: false };

        function formatRupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 1,
            });
        }

        // Format ringkas untuk label langsung di atas bar/di dalam donut
        // (mis. "Rp 1,2jt", "Rp 850rb") supaya tidak terlalu panjang &
        // tidak saling tumpuk saat datanya banyak.
        function formatRupiahCompact(value) {
            value = Number(value || 0);
            const abs = Math.abs(value);
            if (abs >= 1000000) {
                return 'Rp ' + (value / 1000000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + 'jt';
            }
            if (abs >= 1000) {
                return 'Rp ' + (value / 1000).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + 'rb';
            }
            return formatRupiah(value);
        }

        if (window.ChartDataLabels) {
            Chart.register(ChartDataLabels);
        }

        // Format tanggal ke gaya Indonesia panjang tanpa leading zero,
        // contoh: "2026-08-01" -> "1 Agustus 2026".
        const BULAN_ID = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        function formatTanggalIndo(dateStr) {
            if (!dateStr) return '-';
            const datePart = String(dateStr).split(' ')[0].split('T')[0];
            const parts = datePart.split('-');
            if (parts.length !== 3) return dateStr;
            const [y, m, d] = parts;
            const day = parseInt(d, 10);
            const monthIdx = parseInt(m, 10) - 1;
            if (isNaN(day) || !BULAN_ID[monthIdx]) return dateStr;
            return `${day} ${BULAN_ID[monthIdx]} ${y}`;
        }

        function showLoadingSwal(text) {
            Swal.fire({
                title: text || 'Memuat data...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });
        }
        function hideLoadingSwal() { Swal.close(); }

        function currentFilters() {
            return {
                start_date: $('#startDate').val(),
                end_date: $('#endDate').val(),
                npk: $('#filterNpk').val() || '',
            };
        }

        // URLSearchParams bawaan tidak menangani array dengan baik untuk query
        // string bergaya Laravel (npk[]=a&npk[]=b), jadi dipakai builder manual.
        function buildQuery(params) {
            const usp = new URLSearchParams();
            Object.entries(params).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach((v) => usp.append(`${key}[]`, v));
                } else if (value !== null && value !== undefined && value !== '') {
                    usp.append(key, value);
                }
            });
            return usp;
        }

        function loadExpatOptions() {
            fetch("{{ route('expat-meal.expat-options') }}")
                .then((res) => res.json())
                .then((data) => {
                    const select = $('#filterNpk');
                    data.data.forEach((r) => {
                        select.append(new Option(`${r.nama_expat} (${r.npk})`, r.npk, false, false));
                    });
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat daftar expat.', 'error'));
        }

        function initDetailTable() {
            detailTable = $('#detailTable').DataTable({
                data: [],
                columns: [
                    { data: 'tanggal', render: (d, type) => (type === 'display' ? formatTanggalIndo(d) : d) },
                    { data: 'jumlah_expat_sarapan', className: 'text-right' },
                    { data: 'menu_sarapan' },
                    { data: 'harga_sarapan', className: 'text-right', render: (d) => formatRupiah(d) },
                    { data: 'jumlah_expat_makan_siang', className: 'text-right' },
                    { data: 'menu_makan_siang' },
                    { data: 'harga_makan_siang', className: 'text-right', render: (d) => formatRupiah(d) },
                    { data: 'total_harga', className: 'text-right', render: (d) => `<strong>${formatRupiah(d)}</strong>` },
                    {
                        data: null,
                        orderable: false,
                        render: (row) => `
                            <button type="button" class="btn btn-outline-primary btn-sm btn-detail-meal"
                                data-tanggal="${row.tanggal}" title="Detail">
                                <i class="fas fa-eye fa-xs"></i> Detail
                            </button>
                        `,
                    },
                ],
                pageLength: 10,
                order: [[0, 'asc']],
                language: { emptyTable: 'Tidak ada data pada periode ini.' },
            });
        }

        function initParticipantTable() {
            participantTable = $('#participantTable').DataTable({
                data: [],
                columns: [
                    { data: 'tanggal', render: (d, type) => (type === 'display' ? formatTanggalIndo(d) : d) },
                    { data: 'npk' },
                    { data: 'nama_expat' },
                    {
                        data: 'kategori',
                        render: (d) => `<span class="badge ${d === 'Sarapan' ? 'badge-kategori-sarapan' : 'badge-kategori-siang'}">${d}</span>`
                    },
                    {
                        data: null,
                        orderable: false,
                        render: (row) => `
                            <button type="button" class="btn btn-outline-primary btn-sm btn-edit-participant"
                                data-id="${row.id}" data-npk="${row.npk}" data-nama="${row.nama_expat}"
                                data-tanggal="${row.tanggal}" data-kategori="${row.kategori}" title="Edit">
                                <i class="fas fa-pen fa-xs"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm btn-delete-participant" data-id="${row.id}" title="Hapus">
                                <i class="fas fa-trash fa-xs"></i>
                            </button>
                        `,
                    },
                ],
                pageLength: 10,
                order: [[0, 'asc'], [1, 'asc']],
                language: { emptyTable: 'Belum ada peserta makan pada periode ini.' },
            });
        }

        function initMenuTable() {
            menuTable = $('#menuTable').DataTable({
                data: [],
                columns: [
                    { data: 'tanggal', render: (d, type) => (type === 'display' ? formatTanggalIndo(d) : d) },
                    { data: 'makanan' },
                    {
                        data: 'kategori',
                        render: (d) => `<span class="badge ${d === 'Sarapan' ? 'badge-kategori-sarapan' : 'badge-kategori-siang'}">${d}</span>`
                    },
                    { data: 'harga', className: 'text-right', render: (d) => formatRupiah(d) },
                    {
                        data: 'shared',
                        render: (d) => `<span class="badge ${d ? 'badge-shared-yes' : 'badge-shared-no'}">${d ? 'Ya' : 'Tidak'}</span>`
                    },
                    {
                        data: null,
                        orderable: false,
                        render: (row) => `
                            <button type="button" class="btn btn-outline-primary btn-sm btn-edit-menu"
                                data-id="${row.id}" data-makanan="${row.makanan}" data-kategori="${row.kategori}"
                                data-tanggal="${row.tanggal}" data-harga="${row.harga}" data-shared="${row.shared ? 1 : 0}" title="Edit">
                                <i class="fas fa-pen fa-xs"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm btn-delete-menu" data-id="${row.id}" title="Hapus">
                                <i class="fas fa-trash fa-xs"></i>
                            </button>
                        `,
                    },
                ],
                pageLength: 10,
                order: [[0, 'desc'], [2, 'asc']],
                language: { emptyTable: 'Belum ada menu makanan.' },
            });
        }

        function loadSummary() {
            fetch("{{ route('expat-meal.summary') }}?" + buildQuery(currentFilters()))
                .then((res) => res.json())
                .then((data) => {
                    $('#kpiTotalBiaya').text(formatRupiah(data.total_biaya));
                    $('#kpiTotalExpat').text(data.total_expat);
                    $('#kpiTotalHari').text(data.total_hari);
                    $('#kpiTotalPorsi').text(data.total_porsi);

                    $('#chartEmpty').toggle(!data.recap_per_date.length);
                    renderRecapChart(data.recap_per_date);
                    renderKategoriChart(data.recap_per_kategori);
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat ringkasan.', 'error'));
        }

        function renderRecapChart(rows) {
            if (recapChart) recapChart.destroy();
            recapChart = new Chart(document.getElementById('recapChart'), {
                type: 'bar',
                data: {
                    labels: rows.map((r) => formatTanggalIndo(r.tanggal)),
                    datasets: [
                        { label: 'Total Biaya', data: rows.map((r) => r.total_harga), backgroundColor: 'rgba(78,115,223,.85)', borderRadius: 4, maxBarThickness: 28 },
                    ],
                },
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { top: 24 } },
                    scales: {
                        x: { grid: { display: false, drawBorder: false } },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgb(234, 236, 244)', drawBorder: false, borderDash: [3] },
                            ticks: { callback: (v) => formatRupiah(v) },
                            // Beri ruang ekstra di atas supaya label tidak terpotong.
                            grace: '15%',
                        },
                    },
                    plugins: {
                        tooltip: { callbacks: { label: (ctx) => formatRupiah(ctx.raw) } },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            clip: false,
                            color: '#4e4e50',
                            font: { size: 10, weight: '600' },
                            formatter: (value) => (value ? formatRupiahCompact(value) : ''),
                        },
                    },
                },
            });
        }

        function renderKategoriChart(k) {
            if (kategoriChart) kategoriChart.destroy();
            kategoriChart = new Chart(document.getElementById('kategoriChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Sarapan', 'Makan Siang'],
                    datasets: [{
                        data: [k.sarapan, k.makan_siang],
                        backgroundColor: ['#f6c23e', '#1cc88a'],
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${formatRupiah(ctx.raw)}` } },
                        datalabels: {
                            color: '#fff',
                            font: { size: 12, weight: 'bold' },
                            textStrokeColor: 'rgba(0,0,0,.25)',
                            textStrokeWidth: 2,
                            formatter: (value) => (value ? formatRupiahCompact(value) : ''),
                        },
                    },
                },
            });
        }

        function loadDetail() {
            showLoadingSwal('Memuat detail laporan...');
            fetch("{{ route('expat-meal.detail') }}?" + buildQuery(currentFilters()))
                .then((res) => res.json())
                .then((data) => {
                    detailTable.clear();
                    detailTable.rows.add(data.data);
                    detailTable.draw();
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat detail laporan.', 'error'))
                .finally(() => hideLoadingSwal());
        }

        function loadParticipants() {
            fetch("{{ route('expat-meal.participants') }}?" + buildQuery(currentFilters()))
                .then((res) => res.json())
                .then((data) => {
                    participantTable.clear();
                    participantTable.rows.add(data.data);
                    participantTable.draw();
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat daftar peserta.', 'error'));
        }

        function loadMealDetail(tanggal) {
            $('#mealDetailSubtitle').text(formatTanggalIndo(tanggal));
            $('#mealDetailTable tbody').empty();
            $('#mealDetailExpatTable tbody').empty();
            $('#mealDetailTotal').text(formatRupiah(0));
            $('#mealDetailEmpty').hide();
            $('#mealDetailContent').show();
            $('#mealDetailModal').modal('show');

            showLoadingSwal('Memuat detail makanan...');

            fetch("{{ route('expat-meal.detail-makanan') }}?" + new URLSearchParams({ tanggal }))
                .then((res) => res.json())
                .then((data) => {
                    const tbody = $('#mealDetailTable tbody').empty();
                    const expatTbody = $('#mealDetailExpatTable tbody').empty();

                    if (!data.data.length) {
                        $('#mealDetailContent').hide();
                        $('#mealDetailEmpty').show();
                        $('#mealDetailTotal').text(formatRupiah(0));
                        return;
                    }

                    // --- Total per Expat ---
                    const expatTotals = data.expat_totals || [];
                    if (!expatTotals.length) {
                        expatTbody.append(`
                            <tr><td colspan="2" class="text-center text-muted">Tidak ada menu shared pada tanggal ini.</td></tr>
                        `);
                    } else {
                        expatTotals.forEach((e) => {
                            expatTbody.append(`
                                <tr>
                                    <td>${e.nama}</td>
                                    <td class="text-right">${formatRupiah(e.total)}</td>
                                </tr>
                            `);
                        });
                    }

                    // --- Biaya per Expat Tiap Menu ---
                    data.data.forEach((item) => {
                        let rincian;
                        if (item.shared && item.detail_expat && item.detail_expat.length) {
                            rincian = item.detail_expat
                                .map((e) => `<span class="expat-cost-pill">${e.nama}: <span class="pill-price">${formatRupiah(e.harga)}</span></span>`)
                                .join('');
                        } else {
                            rincian = '<span class="text-muted">Lumpsum, tidak dibagi per expat</span>';
                        }

                        tbody.append(`
                            <tr>
                                <td><span class="badge ${item.kategori === 'Sarapan' ? 'badge-kategori-sarapan' : 'badge-kategori-siang'}">${item.kategori}</span></td>
                                <td>${item.makanan}</td>
                                <td class="text-right">${formatRupiah(item.harga_asli)}</td>
                                <td class="text-center"><span class="badge ${item.shared ? 'badge-shared-yes' : 'badge-shared-no'}">${item.shared ? 'Ya' : 'Tidak'}</span></td>
                                <td class="text-center">${item.jumlah_expat}</td>
                                <td>${rincian}</td>
                            </tr>
                        `);
                    });

                    $('#mealDetailTotal').text(formatRupiah(data.total_harga));
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat detail makanan.', 'error'))
                .finally(() => hideLoadingSwal());
        }

        function loadMenu() {
            fetch("{{ route('expat-meal.menu') }}")
                .then((res) => res.json())
                .then((data) => {
                    menuTable.clear();
                    menuTable.rows.add(data.data);
                    menuTable.draw();
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat menu makanan.', 'error'));
        }

        function refreshAll() {
            loadSummary();
            if (loadedTabs[2]) loadParticipants();
        }

        function postAction(url, payload, confirmTitle, confirmText, loadingText, onDone) {
            return Swal.fire({
                title: confirmTitle,
                text: confirmText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#e74a3b',
            }).then((result) => {
                if (!result.isConfirmed) return;

                showLoadingSwal(loadingText);

                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    body: JSON.stringify(payload),
                })
                    .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
                    .then(({ ok, data }) => {
                        hideLoadingSwal();
                        if (!ok) {
                            Swal.fire('Gagal', data.message || 'Terjadi kesalahan.', 'error');
                            return;
                        }
                        Swal.fire('Berhasil', data.message || 'Data berhasil diperbarui.', 'success');
                        if (onDone) onDone();
                    })
                    .catch(() => {
                        hideLoadingSwal();
                        Swal.fire('Gagal', 'Terjadi kesalahan saat menghubungi server.', 'error');
                    });
            });
        }

        $(document).ready(function () {
            initDetailTable();
            initParticipantTable();
            initMenuTable();

            $('#filterNpk').select2({
                theme: 'bootstrap4',
                placeholder: 'Semua expat',
                allowClear: true,
                width: '100%',
            });
            loadExpatOptions();

            loadSummary();
            loadDetail();

            $('#mealTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('href');

                if (target === '#section-2' && !loadedTabs[2]) { loadParticipants(); loadedTabs[2] = true; }
                if (target === '#section-3' && !loadedTabs[3]) { loadMenu(); loadedTabs[3] = true; }

                if (target === '#section-2' && participantTable) participantTable.columns.adjust();
                if (target === '#section-3' && menuTable) menuTable.columns.adjust();
            });

            $('#detailTable').on('click', '.btn-detail-meal', function () {
                loadMealDetail($(this).data('tanggal'));
            });

            // Export Excel: sertakan filter expat yang sedang aktif (npk)
            // sebagai hidden input sebelum form GET ini disubmit.
            $('#exportExcelForm').on('submit', function () {
                $(this).find('input[name="npk"]').remove();
                const selected = $('#filterNpk').val();
                if (selected) {
                    $('<input>').attr({ type: 'hidden', name: 'npk', value: selected }).appendTo(this);
                }
            });

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                loadSummary();
                loadDetail();
                if (loadedTabs[2]) loadParticipants();
            });

            $('#resetFilter').on('click', function () {
                $('#startDate').val('{{ $defaultStartDate }}');
                $('#endDate').val('{{ $defaultEndDate }}');
                $('#filterNpk').val(null).trigger('change');
                loadSummary();
                loadDetail();
                if (loadedTabs[2]) loadParticipants();
            });

            // --- Modal Peserta: tambah / edit ---
            $('#btnAddParticipant').on('click', function () {
                $('#participantForm')[0].reset();
                $('#participantId').val('');
                $('#participantTanggal').val('{{ $defaultEndDate }}');
                $('#participantModalTitle').html('<i class="fas fa-user-plus mr-1"></i> Tambah Peserta Makan');
            });

            $('#participantTable').on('click', '.btn-edit-participant', function () {
                $('#participantId').val($(this).data('id'));
                $('#participantNpk').val($(this).data('npk'));
                $('#participantNama').val($(this).data('nama'));
                $('#participantTanggal').val($(this).data('tanggal'));
                $('#participantKategori').val($(this).data('kategori'));
                $('#participantModalTitle').html('<i class="fas fa-pen mr-1"></i> Edit Peserta Makan');
                $('#participantModal').modal('show');
            });

            $('#participantTable').on('click', '.btn-delete-participant', function () {
                const id = $(this).data('id');
                postAction(
                    "{{ route('expat-meal.participants.delete') }}",
                    { id },
                    'Hapus data peserta?',
                    'Data peserta makan ini akan dihapus permanen.',
                    'Menghapus data...',
                    () => { loadParticipants(); loadSummary(); loadDetail(); }
                );
            });

            $('#participantForm').on('submit', function (e) {
                e.preventDefault();

                const payload = {
                    id: $('#participantId').val() || null,
                    npk: $('#participantNpk').val(),
                    nama_expat: $('#participantNama').val(),
                    tanggal: $('#participantTanggal').val(),
                    kategori: $('#participantKategori').val(),
                };

                showLoadingSwal('Menyimpan data...');

                fetch("{{ route('expat-meal.participants.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    body: JSON.stringify(payload),
                })
                    .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
                    .then(({ ok, data }) => {
                        hideLoadingSwal();
                        if (!ok) {
                            Swal.fire('Gagal', data.message || 'Terjadi kesalahan.', 'error');
                            return;
                        }
                        $('#participantModal').modal('hide');
                        Swal.fire('Berhasil', data.message || 'Data berhasil disimpan.', 'success');
                        loadParticipants();
                        loadSummary();
                        loadDetail();
                    })
                    .catch(() => {
                        hideLoadingSwal();
                        Swal.fire('Gagal', 'Terjadi kesalahan saat menghubungi server.', 'error');
                    });
            });

            // --- Modal Menu: tambah / edit ---
            $('#btnAddMenu').on('click', function () {
                $('#menuForm')[0].reset();
                $('#menuId').val('');
                $('#menuTanggal').val('{{ $defaultEndDate }}');
                $('#menuModalTitle').html('<i class="fas fa-plus mr-1"></i> Tambah Menu Makanan');
            });

            $('#menuTable').on('click', '.btn-edit-menu', function () {
                $('#menuId').val($(this).data('id'));
                $('#menuMakanan').val($(this).data('makanan'));
                $('#menuKategori').val($(this).data('kategori'));
                $('#menuTanggal').val($(this).data('tanggal'));
                $('#menuHarga').val($(this).data('harga'));
                $('#menuShared').prop('checked', $(this).data('shared') == 1);
                $('#menuModalTitle').html('<i class="fas fa-pen mr-1"></i> Edit Menu Makanan');
                $('#menuModal').modal('show');
            });

            $('#menuTable').on('click', '.btn-delete-menu', function () {
                const id = $(this).data('id');
                postAction(
                    "{{ route('expat-meal.menu.delete') }}",
                    { id },
                    'Hapus menu makanan?',
                    'Menu ini akan dihapus permanen. Laporan biaya yang sudah dihitung sebelumnya tidak berubah otomatis.',
                    'Menghapus data...',
                    () => { loadMenu(); loadSummary(); loadDetail(); }
                );
            });

            $('#menuForm').on('submit', function (e) {
                e.preventDefault();

                const payload = {
                    id: $('#menuId').val() || null,
                    makanan: $('#menuMakanan').val(),
                    kategori: $('#menuKategori').val(),
                    tanggal: $('#menuTanggal').val(),
                    harga: $('#menuHarga').val(),
                    shared: $('#menuShared').is(':checked') ? 1 : 0,
                };

                showLoadingSwal('Menyimpan data...');

                fetch("{{ route('expat-meal.menu.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    body: JSON.stringify(payload),
                })
                    .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
                    .then(({ ok, data }) => {
                        hideLoadingSwal();
                        if (!ok) {
                            Swal.fire('Gagal', data.message || 'Terjadi kesalahan.', 'error');
                            return;
                        }
                        $('#menuModal').modal('hide');
                        Swal.fire('Berhasil', data.message || 'Data berhasil disimpan.', 'success');
                        loadMenu();
                        loadSummary();
                        loadDetail();
                    })
                    .catch(() => {
                        hideLoadingSwal();
                        Swal.fire('Gagal', 'Terjadi kesalahan saat menghubungi server.', 'error');
                    });
            });

            // --- Import ---
            $('#importForm').on('submit', function (e) {
                e.preventDefault();
                const form = this;
                const formData = new FormData(form);

                $('#importProgress').show();

                $.ajax({
                    xhr: function () {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (evt) {
                            if (evt.lengthComputable) {
                                const percent = Math.round((evt.loaded / evt.total) * 100);
                                $('#importProgressBar').css('width', percent + '%').text(percent + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    url: "{{ route('expat-meal.import') }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Import selesai',
                            text: response.message,
                        });
                        $('#importModal').modal('hide');
                        $('#importProgress').hide();
                        $('#importProgressBar').css('width', '0%').text('0%');
                        form.reset();
                        loadSummary();
                        loadDetail();
                        if (loadedTabs[2]) loadParticipants();
                        if (loadedTabs[3]) loadMenu();
                    },
                    error: function (xhr) {
                        let msg = 'Terjadi kesalahan saat import.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        Swal.fire('Import gagal', msg, 'error');
                        $('#importProgress').hide();
                    },
                });
            });
        });
    </script>
</body>
</html>