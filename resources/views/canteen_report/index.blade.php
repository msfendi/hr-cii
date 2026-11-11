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

.badge-window-utama{ background-color:#d4edda; color:#1c7a3c; }
.badge-window-lembur{ background-color:#fff3cd; color:#8a6d00; }
.badge-kantin-1{ background-color:#eef1fd; color:#2e59d9; }
.badge-kantin-2{ background-color:#e6f9f4; color:#13a06d; }
.badge-anomali{ background-color:#f8d7da; color:#c0293c; }
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
                            <i class="fas fa-utensils text-primary mr-2"></i>Canteen Report
                        </h1>
                        <div class="d-flex flex-wrap mt-2 mt-sm-0" style="gap:.5rem">
                            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#manualModal">
                                <i class="fas fa-user-plus fa-sm"></i> Tambah Data Manual
                            </button>
                            <a href="{{ route('canteen-report.template') }}" class="btn btn-sm btn-info shadow-sm">
                                <i class="fas fa-download fa-sm"></i> Download Template
                            </a>
                            <button type="button" class="btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#importShiftModal">
                                <i class="fas fa-upload fa-sm"></i> Import Data
                            </button>
                            <button type="button" class="btn btn-sm btn-danger shadow-sm" data-toggle="modal" data-target="#exportPdfModal">
                                <i class="fas fa-file-pdf fa-sm"></i> Export Rekap PDF
                            </button>
                            <button type="button" class="btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#exportExcelModal">
                                <i class="fas fa-file-excel fa-sm"></i> Export Rekap Excel
                            </button>
                        </div>
                    </div>

                    <ul class="nav nav-tabs" id="recapTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-link-1" data-toggle="tab" href="#section-1" role="tab">
                                <span class="tab-number">1</span> Rekap &amp; Biaya
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-2" data-toggle="tab" href="#section-2" role="tab">
                                <span class="tab-number">2</span> Detail Scan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-link-3" data-toggle="tab" href="#section-3" role="tab">
                                <span class="tab-number">3</span> Cek Duplikat / Anomali
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="recapTabsContent">

                    {{-- ===================== SECTION 1: REKAP & BIAYA ===================== --}}
                    <div class="tab-pane fade show active section-block" id="section-1" role="tabpanel">
                        <div class="section-title"><span class="section-number">1</span><h2>Rekap &amp; Biaya</h2></div>

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
                                        <div class="col-md-4 mb-3">
                                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Departemen</label>
                                            <select id="deptSelect" class="form-control" style="width:100%">
                                                <option value="">Semua Departemen</option>
                                                @foreach ($departments as $d)
                                                    <option value="{{ $d }}">{{ $d }}</option>
                                                @endforeach
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
                                    <div class="kpi-label">Total Kantin 1</div>
                                    <div class="kpi-value" id="kpiTotalKantin1">0</div>
                                    <div class="small" style="opacity:.85" id="kpiUniqueKantin1">Karyawan unik: 0</div>
                                    <div class="small font-weight-bold" style="opacity:.95" id="kpiCostKantin1">Biaya: Rp 0</div>
                                    <i class="fas fa-store kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-info shadow h-100">
                                    <div class="kpi-label">Total Kantin 2</div>
                                    <div class="kpi-value" id="kpiTotalKantin2">0</div>
                                    <div class="small" style="opacity:.85" id="kpiUniqueKantin2">Karyawan unik: 0</div>
                                    <div class="small font-weight-bold" style="opacity:.95" id="kpiCostKantin2">Biaya: Rp 0</div>
                                    <i class="fas fa-store-alt kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-success shadow h-100">
                                    <div class="kpi-label">Total Biaya</div>
                                    <div class="kpi-value" id="kpiGrandTotalCost">Rp 0</div>
                                    <div class="small" style="opacity:.85">@ Rp {{ number_format($costPerMeal, 0, ',', '.') }} / makan</div>
                                    <i class="fas fa-coins kpi-icon"></i>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="kpi-card kpi-warning shadow h-100">
                                    <div class="kpi-label">Anomali Terdeteksi</div>
                                    <div class="kpi-value" id="kpiTotalAnomali">0</div>
                                    <div class="small" style="opacity:.85">Lihat tab Cek Duplikat</div>
                                    <i class="fas fa-triangle-exclamation kpi-icon"></i>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-7 mb-4">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar mr-1"></i> Tren Karyawan Makan per Tanggal</h6>
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
                                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie mr-1"></i> Utama vs Lembur</h6>
                                    </div>
                                    <div class="card-body">
                                        <div style="height:320px"><canvas id="windowChart"></canvas></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table mr-1"></i> Rekap per Tanggal</h6>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 small" id="recapTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th class="text-right">Kantin 1</th>
                                                <th class="text-right">Kantin 2</th>
                                                <th class="text-right">Total</th>
                                                <th class="text-right">Utama</th>
                                                <th class="text-right">Lembur</th>
                                                <th class="text-right">Biaya</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot>
                                            <tr class="font-weight-bold">
                                                <td>Grand Total</td>
                                                <td class="text-right" id="footK1">0</td>
                                                <td class="text-right" id="footK2">0</td>
                                                <td class="text-right" id="footTotal">0</td>
                                                <td class="text-right" id="footUtama">0</td>
                                                <td class="text-right" id="footLembur">0</td>
                                                <td class="text-right" id="footCost">Rp 0</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== SECTION 2: DETAIL SCAN ===================== --}}
                    <div class="tab-pane fade section-block" id="section-2" role="tabpanel">
                        <div class="section-title"><span class="section-number">2</span><h2>Detail Scan</h2></div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap">
                                <div class="mr-2">
                                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Data Scan Kantin</h6>
                                    <small class="section-hint">Menggunakan filter tanggal &amp; departemen yang sama dengan tab Rekap &amp; Biaya.</small>
                                </div>
                                <div class="d-flex flex-wrap mt-2 mt-md-0" style="gap:.5rem">
                                    <div style="min-width:180px">
                                        <select id="kantinFilterSelect" class="form-control form-control-sm">
                                            <option value="">Semua Kantin</option>
                                            <option value="Kantin 1">Kantin 1</option>
                                            <option value="Kantin 2">Kantin 2</option>
                                        </select>
                                    </div>
                                    <div style="min-width:180px">
                                        <select id="windowFilterSelect" class="form-control form-control-sm">
                                            <option value="">Semua Window</option>
                                            <option value="Utama">Istirahat Utama</option>
                                            <option value="Lembur">Istirahat Lembur</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 small" id="detailTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Jam</th>
                                                <th>NPK</th>
                                                <th>Nama</th>
                                                <th>Departemen</th>
                                                <th>Kantin</th>
                                                {{-- PERBAIKAN V41 (2026-11-10): kolom ID Tiket. --}}
                                                <th>ID Tiket</th>
                                                <th>Window</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== SECTION 3: CEK DUPLIKAT / ANOMALI ===================== --}}
                    <div class="tab-pane fade section-block" id="section-3" role="tabpanel">
                        <div class="section-title"><span class="section-number">3</span><h2>Cek Duplikat / Anomali</h2></div>

                        <div class="card shadow mb-4">
                            <div class="card-body">
                                <p class="mb-1"><i class="fas fa-circle-info text-primary mr-1"></i> Aturan scan kantin:</p>
                                <ul class="small text-gray-600 mb-0">
                                    <li>Maksimal 2x scan per karyawan per hari (1x window Istirahat Utama, 1x window Istirahat Lembur).</li>
                                    <li>Istirahat Utama: pukul 11:00 - 13:30. Selain jam tersebut dianggap Istirahat Lembur.</li>
                                    <li>Karyawan tidak boleh scan di 2 kantin berbeda pada window yang sama.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-triangle-exclamation mr-1"></i> Daftar Anomali</h6>
                                <small class="section-hint">Menggunakan filter yang sama dengan tab Rekap &amp; Biaya.</small>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 small" id="duplicateTable" width="100%">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>NPK</th>
                                                <th>Nama</th>
                                                <th>Departemen</th>
                                                <th>Window</th>
                                                <th>Jenis Anomali</th>
                                                <th>Detail</th>
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
                </div>
            </div>

            <!-- MANUAL ADD MODAL -->
            <div class="modal fade" id="manualModal" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form id="manualForm">
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fas fa-user-plus mr-1"></i> Tambah Data Manual</h5>
                      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                      <small class="section-hint d-block mb-3">Gunakan form ini untuk mencatat karyawan / outsource yang makan di kantin tapi tidak scan (mis. security OS).</small>
                      <div class="form-group">
                        <label class="small font-weight-bold text-gray-600">Kantin</label>
                        <select id="manualKantin" name="kantin" class="form-control" required>
                          <option value="Kantin 1">Kantin 1 - Diamond Chickres</option>
                          <option value="Kantin 2">Kantin 2 - Pawon Ndoro Ayu</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="small font-weight-bold text-gray-600">Kategori</label>
                        <select id="manualCategory" name="category" class="form-control" required>
                          <option value="employee">Employee</option>
                          <option value="outsource">Outsource</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label class="small font-weight-bold text-gray-600">NPK / Nama</label>
                        <select id="manualNpk" name="npk" class="form-control" style="width:100%" required></select>
                      </div>
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label class="small font-weight-bold text-gray-600">Tanggal</label>
                          <input type="date" id="manualDate" name="date" class="form-control" value="{{ $defaultStartDate }}" required>
                        </div>
                        <div class="form-group col-md-6">
                          <label class="small font-weight-bold text-gray-600">Jam</label>
                          <input type="time" id="manualTime" name="time" class="form-control">
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

            <!-- IMPORT SHIFT MODAL -->
            <div class="modal fade" id="importShiftModal" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form id="importShiftForm" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fas fa-upload mr-1"></i> Import Data Shift</h5>
                      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                      <small class="section-hint d-block mb-3">Gunakan template Excel (npk, nama_karyawan, bagian, jam_masuk, jam_pulang).</small>
                      <div class="form-group">
                        <label class="small font-weight-bold text-gray-600">File Excel</label>
                        <input type="file" name="file" id="importShiftFile" class="form-control-file" accept=".xlsx,.xls,.csv" required>
                      </div>
                      <div class="form-row">
                        <div class="form-group col-md-6">
                          <label class="small font-weight-bold text-gray-600">Shift</label>
                          <select name="shift" class="form-control" required>
                            <option value="siang">Shift Siang (18:00)</option>
                            <option value="malam">Shift Malam (22:30)</option>
                          </select>
                        </div>
                        <div class="form-group col-md-6">
                          <label class="small font-weight-bold text-gray-600">Kantin</label>
                          <select name="kantin" class="form-control" required>
                            <option value="Kantin 1">Kantin 1 - Diamond Chickres</option>
                            <option value="Kantin 2">Kantin 2 - Pawon Ndoro Ayu</option>
                          </select>
                        </div>
                      </div>
                      <div class="form-group">
                        <label class="small font-weight-bold text-gray-600">Tanggal</label>
                        <input type="date" name="date" class="form-control" value="{{ $defaultStartDate }}" required>
                      </div>
                      <div class="progress mt-2" style="height:16px; display:none;" id="importShiftProgress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%" id="importShiftProgressBar">0%</div>
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

            <!-- EXPORT PDF MODAL -->
            <div class="modal fade" id="exportPdfModal" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form id="exportPdfForm" action="{{ route('canteen-report.export-pdf') }}" method="GET" target="_blank">
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fas fa-file-pdf mr-1"></i> Export Rekap PDF</h5>
                      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                      <div class="form-group">
                        <label class="small font-weight-bold text-gray-600">Kantin</label>
                        <select name="kantin" class="form-control" required>
                          <option value="Kantin 1">Kantin 1 - Diamond Chickres</option>
                          <option value="Kantin 2">Kantin 2 - Pawon Ndoro Ayu</option>
                        </select>
                      </div>
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
                      <button type="submit" class="btn btn-danger"><i class="fas fa-download fa-sm"></i> Export PDF</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- EXPORT EXCEL MODAL -->
            <div class="modal fade" id="exportExcelModal" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form id="exportExcelForm" action="{{ route('canteen-report.export-excel') }}" method="GET" target="_blank">
                    <div class="modal-header">
                      <h5 class="modal-title"><i class="fas fa-file-excel mr-1"></i> Export Rekap Excel</h5>
                      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                      <p class="small text-muted mb-3">
                        File akan berisi 5 sheet: <strong>Summary</strong>, <strong>Normal Break</strong>,
                        <strong>OS</strong>, <strong>Shift Siang</strong>, dan <strong>Shift Malam</strong>.
                      </p>
                      <div class="form-group">
                        <label class="small font-weight-bold text-gray-600">Kantin</label>
                        <select name="kantin" class="form-control" required>
                          <option value="Kantin 1">Kantin 1 - Diamond Chickres</option>
                          <option value="Kantin 2">Kantin 2 - Pawon Ndoro Ayu</option>
                        </select>
                      </div>
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

            <!-- RECEIPT MODAL (Bukti Scan Kantin) - PERBAIKAN LAYOUT (2026-11-10) -->
            <div class="modal fade" id="receiptModal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel"><i class="fas fa-receipt mr-1"></i> Bukti Scan Kantin</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body p-3" id="receiptModalBody">
                    <div class="text-center text-muted">Memuat bukti scan...</div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Tutup</button>
                    <button type="button" class="btn btn-primary" id="btnPrintReceipt"><i class="fas fa-print mr-1"></i> Cetak</button>
                  </div>
                </div>
              </div>
            </div>

            @include('layout.footer')
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        /* PERBAIKAN LAYOUT (2026-11-10): gaya receipt sama persis dengan canteen transaction */
        .receipt {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            font-size: .85rem;
        }
        .receipt .rowline {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dashed #eef0f6;
        }
        .receipt .rowline span:first-child { color: #8a8fa3; }
        #receiptModalMap {
            height: 180px;
            width: 100%;
            z-index: 1;
        }
        .id-ticket-link { cursor: pointer; }
        .id-ticket-link:hover { color: #2e59d9 !important; }

        /* Layout cetak struk Bukti Scan Kantin teroptimasi */
        @media print {
            body * {
                visibility: hidden !important;
            }
            .modal-backdrop, .modal-header, .modal-footer, .btn, .close, #wrapper > *:not(#content-wrapper), #content > nav {
                display: none !important;
            }
            #receiptModalBox, #receiptModalBox * {
                visibility: visible !important;
            }
            #receiptModalBox {
                position: fixed !important;
                left: 50% !important;
                top: 15px !important;
                transform: translateX(-50%) !important;
                width: 440px !important;
                max-width: 440px !important;
                margin: 0 !important;
                padding: 18px 20px !important;
                background: #ffffff !important;
                border: 1px dashed #b0b5be !important;
                border-radius: 12px !important;
                box-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                z-index: 999999 !important;
            }
            #receiptModalBox .row {
                display: flex !important;
                flex-wrap: nowrap !important;
            }
            #receiptModalBox .col-5 {
                flex: 0 0 41.666667% !important;
                max-width: 41.666667% !important;
                width: 41.666667% !important;
                padding-right: 4px !important;
            }
            #receiptModalBox .col-7 {
                flex: 0 0 58.333333% !important;
                max-width: 58.333333% !important;
                width: 58.333333% !important;
                padding-left: 4px !important;
            }
            #receiptModalBox .rowline {
                display: flex !important;
                justify-content: space-between !important;
                padding: 3px 0 !important;
                border-bottom: 1px dashed #e3e6f0 !important;
            }
            #receiptModalFaceImg {
                width: 100% !important;
                height: 180px !important;
                object-fit: cover !important;
                border-radius: 6px !important;
                border: 1px solid #d1d3e2 !important;
            }
            #receiptModalMap {
                height: 180px !important;
                border-radius: 6px !important;
                border: 1px solid #d1d3e2 !important;
                overflow: hidden !important;
                position: relative !important;
            }
            .leaflet-control-zoom,
            .leaflet-control-attribution {
                display: none !important;
            }
        }
    </style>

    <script>
        let recapChart = null;
        let windowChart = null;
        let detailTable = null;
        let duplicateTable = null;

        function formatRupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
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
                dept: $('#deptSelect').val() || '',
            };
        }

        function currentDetailFilters() {
            return Object.assign(currentFilters(), {
                kantin: $('#kantinFilterSelect').val() || '',
                window: $('#windowFilterSelect').val() || '',
            });
        }

        function initDetailTable() {
            detailTable = $('#detailTable').DataTable({
                data: [],
                columns: [
                    { data: 'date' },
                    { data: 'jam' },
                    { data: 'npk' },
                    { data: 'name' },
                    { data: 'dept' },
                    {
                        data: 'kantin',
                        render: (d) => `<span class="badge ${d === 'Kantin 1' ? 'badge-kantin-1' : 'badge-kantin-2'}">${d}</span>`
                    },
                    {
                        // PERBAIKAN V41 (2026-11-10): kolom ID Tiket.
                        // PERBAIKAN LAYOUT (2026-11-10): id_ticket dibuat bisa diklik
                        // untuk membuka modal bukti scan (desain sama persis
                        // dengan canteen transaction).
                        data: 'id_ticket',
                        render: (d, type, row) => {
                            if (!d) return '-';
                            return `<code class="small text-primary cursor-pointer id-ticket-link" data-id_ticket="${d}" style="text-decoration:underline;" title="Klik untuk lihat bukti scan">${d}</code>`;
                        }
                    },
                    {
                        data: 'window',
                        render: (d) => `<span class="badge ${d === 'Utama' ? 'badge-window-utama' : 'badge-window-lembur'}">${d}</span>`
                    },
                    {
                        data: null,
                        orderable: false,
                        render: (row) => {
                            const target = row.kantin === 'Kantin 1' ? 'Kantin 2' : 'Kantin 1';
                            return `
                                <button type="button" class="btn btn-outline-primary btn-sm btn-move-scan" data-id="${row.id}" data-from="${row.kantin}" title="Pindahkan ke ${target}">
                                    <i class="fas fa-right-left fa-xs"></i> Pindah ke ${target}
                                </button>
                            `;
                        },
                    },
                ],
                pageLength: 10,
                order: [[0, 'asc'], [1, 'asc']],
                language: { emptyTable: 'Tidak ada data scan pada periode ini.' },
            });
        }

        function initDuplicateTable() {
            duplicateTable = $('#duplicateTable').DataTable({
                data: [],
                columns: [
                    { data: 'date' },
                    { data: 'npk' },
                    { data: 'name' },
                    { data: 'dept' },
                    {
                        data: 'window',
                        render: (d) => d === '-' ? '-' : `<span class="badge ${d === 'Utama' ? 'badge-window-utama' : 'badge-window-lembur'}">${d}</span>`
                    },
                    { data: 'type', render: (d) => `<span class="badge badge-anomali">${d}</span>` },
                    { data: 'detail' },
                    {
                        data: 'items',
                        orderable: false,
                        render: (items) => {
                            if (!items || !items.length) return '-';
                            return items.map((it) => `
                                <div class="d-flex align-items-center mb-1">
                                    <span class="badge ${it.kantin === 'Kantin 1' ? 'badge-kantin-1' : 'badge-kantin-2'} mr-1">${it.kantin}</span>
                                    <span class="mr-2 small text-gray-600">${it.jam}</span>
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete-scan" data-id="${it.id}" data-kantin="${it.kantin}" title="Hapus data scan ini">
                                        <i class="fas fa-trash fa-xs"></i> Hapus
                                    </button>
                                </div>
                            `).join('');
                        },
                    },
                ],
                pageLength: 10,
                order: [[0, 'asc'], [1, 'asc']],
                language: { emptyTable: 'Tidak ada anomali pada periode ini.' },
            });
        }

        function loadSummary() {
            showLoadingSwal('Memuat rekap...');
            fetch("{{ route('canteen-report.summary') }}?" + new URLSearchParams(currentFilters()))
                .then((res) => res.json())
                .then((data) => {
                    $('#kpiTotalKantin1').text(data.total_kantin_1);
                    $('#kpiUniqueKantin1').text('Karyawan unik: ' + data.unique_kantin_1);
                    $('#kpiCostKantin1').text('Biaya: ' + formatRupiah(data.cost_kantin_1));
                    $('#kpiTotalKantin2').text(data.total_kantin_2);
                    $('#kpiUniqueKantin2').text('Karyawan unik: ' + data.unique_kantin_2);
                    $('#kpiCostKantin2').text('Biaya: ' + formatRupiah(data.cost_kantin_2));
                    $('#kpiGrandTotalCost').text(formatRupiah(data.grand_total_cost));
                    $('#kpiTotalAnomali').text(data.total_anomali);

                    renderRecapTable(data.recap_per_date);
                    renderRecapChart(data.recap_per_date);
                    renderWindowChart(data.recap_per_window);
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data rekap.', 'error'))
                .finally(() => hideLoadingSwal());
        }

        function renderRecapTable(rows) {
            const tbody = $('#recapTable tbody').empty();
            let fK1 = 0, fK2 = 0, fTotal = 0, fUtama = 0, fLembur = 0, fCost = 0;

            $('#chartEmpty').toggle(!rows.length);

            rows.forEach((r) => {
                fK1 += r.kantin_1; fK2 += r.kantin_2; fTotal += r.total;
                fUtama += r.utama; fLembur += r.lembur; fCost += r.cost;
                tbody.append(`
                    <tr>
                        <td>${r.date}</td>
                        <td class="text-right">${r.kantin_1}</td>
                        <td class="text-right">${r.kantin_2}</td>
                        <td class="text-right">${r.total}</td>
                        <td class="text-right">${r.utama}</td>
                        <td class="text-right">${r.lembur}</td>
                        <td class="text-right">${formatRupiah(r.cost)}</td>
                    </tr>
                `);
            });

            $('#footK1').text(fK1);
            $('#footK2').text(fK2);
            $('#footTotal').text(fTotal);
            $('#footUtama').text(fUtama);
            $('#footLembur').text(fLembur);
            $('#footCost').text(formatRupiah(fCost));
        }

        function renderRecapChart(rows) {
            if (recapChart) recapChart.destroy();
            recapChart = new Chart(document.getElementById('recapChart'), {
                type: 'bar',
                data: {
                    labels: rows.map((r) => r.date),
                    datasets: [
                        { label: 'Kantin 1', data: rows.map((r) => r.kantin_1), backgroundColor: 'rgba(78,115,223,.85)', borderRadius: 4, maxBarThickness: 28 },
                        { label: 'Kantin 2', data: rows.map((r) => r.kantin_2), backgroundColor: 'rgba(54,185,204,.85)', borderRadius: 4, maxBarThickness: 28 },
                    ],
                },
                plugins: [ChartDataLabels],
                options: {
                    maintainAspectRatio: false,
                    layout: { padding: { top: 22 } },
                    scales: {
                        x: { stacked: false, grid: { display: false, drawBorder: false } },
                        y: {
                            stacked: false,
                            beginAtZero: true,
                            grid: { color: 'rgb(234, 236, 244)', drawBorder: false, borderDash: [3] },
                            // extra headroom so the labels above the bars don't get clipped
                            suggestedMax: Math.max(1, ...rows.map((r) => Math.max(r.kantin_1, r.kantin_2))) * 1.2,
                        },
                    },
                    plugins: {
                        legend: { position: 'top' },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            offset: 2,
                            color: '#5a5c69',
                            font: { size: 10, weight: '700' },
                            formatter: (value) => (value ? value : ''),
                        },
                    },
                },
            });
        }

        function renderWindowChart(w) {
            if (windowChart) windowChart.destroy();
            const wData = [w.kantin_1_utama, w.kantin_1_lembur, w.kantin_2_utama, w.kantin_2_lembur];
            const wTotal = wData.reduce((a, b) => a + b, 0);
            windowChart = new Chart(document.getElementById('windowChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Kantin 1 - Utama', 'Kantin 1 - Lembur', 'Kantin 2 - Utama', 'Kantin 2 - Lembur'],
                    datasets: [{
                        data: wData,
                        backgroundColor: ['#4e73df', '#aab8f2', '#36b9cc', '#a9e2e9'],
                    }],
                },
                plugins: [ChartDataLabels],
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        datalabels: {
                            color: '#fff',
                            font: { size: 11, weight: '700' },
                            formatter: (value) => {
                                if (!value) return '';
                                const pct = wTotal ? Math.round((value / wTotal) * 100) : 0;
                                return value + '\n(' + pct + '%)';
                            },
                            textAlign: 'center',
                        },
                    },
                },
            });
        }

        function loadDetail() {
            showLoadingSwal('Memuat detail scan...');
            fetch("{{ route('canteen-report.detail') }}?" + new URLSearchParams(currentDetailFilters()))
                .then((res) => res.json())
                .then((data) => {
                    detailTable.clear();
                    detailTable.rows.add(data.data);
                    detailTable.draw();
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data detail.', 'error'))
                .finally(() => hideLoadingSwal());
        }

        function loadDuplicate() {
            fetch("{{ route('canteen-report.duplicate') }}?" + new URLSearchParams(currentFilters()))
                .then((res) => res.json())
                .then((data) => {
                    duplicateTable.clear();
                    duplicateTable.rows.add(data.data);
                    duplicateTable.draw();
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat memuat data anomali.', 'error'));
        }

        function postAction(url, payload, confirmTitle, confirmText, loadingText) {
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
                        if (loadedTabs[3]) loadDuplicate();
                        loadSummary();
                        if (loadedTabs[2]) loadDetail();
                    })
                    .catch(() => {
                        hideLoadingSwal();
                        Swal.fire('Gagal', 'Terjadi kesalahan saat menghubungi server.', 'error');
                    });
            });
        }

        const loadedTabs = { 1: false, 2: false, 3: false };

        function initManualSelect2() {
            const category = $('#manualCategory').val();
            const kantin = $('#manualKantin').val();
            const url = category === 'employee'
                ? "{{ route('canteen-report.employee-options') }}"
                : "{{ route('canteen-report.outsource-options') }}";

            if ($('#manualNpk').hasClass('select2-hidden-accessible')) {
                $('#manualNpk').empty().val(null).trigger('change').select2('destroy');
            }

            $('#manualNpk').select2({
                dropdownParent: $('#manualModal'),
                placeholder: 'Cari NPK / Nama...',
                minimumInputLength: 0,
                ajax: {
                    url: url,
                    dataType: 'json',
                    delay: 300,
                    data: (params) => ({ q: params.term || '', kantin: kantin }),
                    processResults: (data) => ({ results: data.results }),
                    cache: false,
                },
            });
        }

        $(document).ready(function () {
            $('#deptSelect').select2({ placeholder: 'Semua Departemen', allowClear: true });

            $('#manualModal').on('shown.bs.modal', function () {
                initManualSelect2();
            });
            $('#manualCategory, #manualKantin').on('change', function () {
                initManualSelect2();
            });

            $('#manualForm').on('submit', function (e) {
                e.preventDefault();

                const payload = {
                    kantin: $('#manualKantin').val(),
                    category: $('#manualCategory').val(),
                    npk: $('#manualNpk').val(),
                    date: $('#manualDate').val(),
                    time: $('#manualTime').val(),
                };

                if (!payload.npk) {
                    Swal.fire('Perhatian', 'Pilih NPK / Nama terlebih dahulu.', 'warning');
                    return;
                }

                showLoadingSwal('Menyimpan data...');

                fetch("{{ route('canteen-report.manual-store') }}", {
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
                        $('#manualModal').modal('hide');
                        Swal.fire('Berhasil', data.message || 'Data berhasil disimpan.', 'success');
                        loadSummary();
                        if (loadedTabs[2]) loadDetail();
                    })
                    .catch(() => {
                        hideLoadingSwal();
                        Swal.fire('Gagal', 'Terjadi kesalahan saat menghubungi server.', 'error');
                    });
            });

            $('#importShiftForm').on('submit', function (e) {
                e.preventDefault();
                const form = this;
                const formData = new FormData(form);

                $('#importShiftProgress').show();

                $.ajax({
                    xhr: function () {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (evt) {
                            if (evt.lengthComputable) {
                                const percent = Math.round((evt.loaded / evt.total) * 100);
                                $('#importShiftProgressBar').css('width', percent + '%').text(percent + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    url: "{{ route('canteen-report.import') }}",
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
                            showConfirmButton: false,
                            timer: 2000,
                        });
                        $('#importShiftModal').modal('hide');
                        $('#importShiftProgress').hide();
                        $('#importShiftProgressBar').css('width', '0%').text('0%');
                        form.reset();
                        loadSummary();
                        if (loadedTabs[2]) loadDetail();
                    },
                    error: function (xhr) {
                        let msg = 'Terjadi kesalahan saat import.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        Swal.fire('Import gagal', msg, 'error');
                        $('#importShiftProgress').hide();
                    },
                });
            });

            initDetailTable();
            initDuplicateTable();

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                loadSummary();
                if (loadedTabs[2]) loadDetail();
                if (loadedTabs[3]) loadDuplicate();
            });

            $('#resetFilter').on('click', function () {
                $('#startDate').val('{{ $defaultStartDate }}');
                $('#endDate').val('{{ $defaultEndDate }}');
                $('#deptSelect').val(null).trigger('change');
                $('#kantinFilterSelect').val('');
                $('#windowFilterSelect').val('');
                loadSummary();
                if (loadedTabs[2]) loadDetail();
                if (loadedTabs[3]) loadDuplicate();
            });

            $('#kantinFilterSelect').on('change', function () {
                if (loadedTabs[2]) loadDetail();
            });

            $('#windowFilterSelect').on('change', function () {
                if (loadedTabs[2]) loadDetail();
            });

            $('#detailTable').on('click', '.btn-move-scan', function () {
                const id = $(this).data('id');
                const from = $(this).data('from');
                const to = from === 'Kantin 1' ? 'Kantin 2' : 'Kantin 1';

                postAction(
                    "{{ route('canteen-report.move') }}",
                    { id, from },
                    'Pindahkan data scan?',
                    `Data akan dipindahkan dari ${from} ke ${to}.`,
                    'Memindahkan data...'
                );
            });

            $('#duplicateTable').on('click', '.btn-delete-scan', function () {
                const id = $(this).data('id');
                const kantin = $(this).data('kantin');

                postAction(
                    "{{ route('canteen-report.delete') }}",
                    { id, kantin },
                    'Hapus data scan?',
                    `Data scan di ${kantin} pada jam ini akan dihapus permanen.`,
                    'Menghapus data...'
                );
            });

            loadSummary();
            loadedTabs[1] = true;

            $('#recapTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('href');

                if (target === '#section-2' && !loadedTabs[2]) { loadDetail(); loadedTabs[2] = true; }
                if (target === '#section-3' && !loadedTabs[3]) { loadDuplicate(); loadedTabs[3] = true; }

                if (target === '#section-2' && detailTable) detailTable.columns.adjust();
                if (target === '#section-3' && duplicateTable) duplicateTable.columns.adjust();
            });

            // PERBAIKAN LAYOUT (2026-11-10): klik id_ticket -> buka modal bukti scan
            // desain sama persis dengan canteen transaction (renderReceipt + drawReceiptMap).
            let receiptModalMap = null;
            let receiptModalMapGroup = null;
            let receiptModalScanMarker = null;
            let activeLocations = @json($locations ?? []);

            function formatTanggalId(dateStr) {
                if (!dateStr) return '-';
                const m = String(dateStr).match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!m) return dateStr;
                const y = m[1], mo = parseInt(m[2], 10), day = parseInt(m[3], 10);
                const MONTHS_ID = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                const monthName = MONTHS_ID[mo - 1] || m[2];
                return day + ' ' + monthName + ' ' + y;
            }

            function extractPolygonRing(polygonGeojson) {
                if (!polygonGeojson) return [];
                let data = polygonGeojson;
                if (typeof data === 'string') {
                    try { data = JSON.parse(data); } catch(e) { return []; }
                }
                if (!data || typeof data !== 'object') return [];

                // Array polos berisi titik [lng,lat]
                if (Array.isArray(data) && data.length && Array.isArray(data[0]) && typeof data[0][0] !== 'object') {
                    return data
                        .map(p => [parseFloat(p[0]), parseFloat(p[1])])
                        .filter(p => !isNaN(p[0]) && !isNaN(p[1]));
                }

                if (data.type === 'FeatureCollection' && data.features && data.features.length) {
                    const first = data.features[0];
                    if (first.geometry) return extractPolygonRing(first.geometry);
                    if (first.geometry) return extractPolygonRing(first);
                }
                if (data.type === 'Feature' && data.geometry) {
                    return extractPolygonRing(data.geometry);
                }

                let coords = data.coordinates;
                if (!Array.isArray(coords) || coords.length === 0) return [];
                if (Array.isArray(coords[0]) && Array.isArray(coords[0][0]) && Array.isArray(coords[0][0][0])) {
                    coords = coords[0];
                }
                if (Array.isArray(coords[0]) && Array.isArray(coords[0][0])) {
                    coords = coords[0];
                }
                if (!Array.isArray(coords) || coords.length === 0) return [];

                return coords
                    .map(p => [parseFloat(p[0]), parseFloat(p[1])])
                    .filter(p => !isNaN(p[0]) && !isNaN(p[1]));
            }

            function formatPhotoFaceSrc(photo) {
                if (!photo) return '';
                photo = String(photo).trim();
                if (!photo) return '';
                if (photo.startsWith('data:image/')) return photo;
                return 'data:image/jpeg;base64,' + photo;
            }

            function createScanAvatarIcon() {
                return L.divIcon({
                    className: 'canteen-user-marker',
                    html: `
                        <div style="
                            width: 32px;
                            height: 32px;
                            border-radius: 50%;
                            background: #00c08b;
                            border: 2.5px solid #ffffff;
                            box-shadow: 0 2px 6px rgba(0,0,0,0.35);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            transform: translate(-50%, -50%);
                        ">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#3a206e" style="display:block;">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                    `,
                    iconSize: [0, 0],
                    iconAnchor: [0, 0]
                });
            }

            function drawReceiptModalMap(lat, lng) {
                const box = document.getElementById('receiptModalMap');
                if (!box) return;
                if (typeof lat !== 'number' || typeof lng !== 'number' || isNaN(lat) || isNaN(lng)) return;
                if (typeof L === 'undefined') { console.warn('[CanteenReport] Leaflet tidak termuat, peta bukti dinonaktifkan.'); return; }

                try {
                    if (receiptModalMap) {
                        try { receiptModalMap.remove(); } catch(e) {}
                        receiptModalMap = null;
                        receiptModalMapGroup = null;
                        receiptModalScanMarker = null;
                        box.innerHTML = '';
                    }

                    // PERBAIKAN V44: peta bukti scan kantin street-level zoom (zoom 17):
                    // - Inisialisasi langsung dengan center [lat, lng], zoom 17, dan zoomControl: true
                    // - Polygon styling hijau toska (#00c08b), semi-transparan, dengan noClip: true
                    // - Pointer lingkaran toska dengan avatar siluet user di tengahnya
                    receiptModalMap = L.map('receiptModalMap', {
                        center: [lat, lng],
                        zoom: 17,
                        zoomControl: true,
                        attributionControl: false,
                        dragging: true,
                        scrollWheelZoom: false
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(receiptModalMap);

                    receiptModalMapGroup = L.featureGroup().addTo(receiptModalMap);

                    let nearbyPolyBounds = null;

                    // 1) Polygon area kerja kantin — sesuai referensi Gambar 2
                    if (activeLocations && activeLocations.length > 0) {
                        activeLocations.forEach(loc => {
                            if (!loc.polygon_geojson) return;
                            const ring = extractPolygonRing(loc.polygon_geojson);
                            if (ring.length < 3) return;
                            try {
                                const geoJson = {
                                    type: 'Feature',
                                    properties: { name: loc.name || 'Area Lokasi Kerja' },
                                    geometry: { type: 'Polygon', coordinates: [ring] }
                                };
                                const poly = window.L.geoJSON(geoJson, {
                                    style: {
                                        color: '#00c08b',
                                        fillColor: '#00c08b',
                                        fillOpacity: 0.28,
                                        weight: 3,
                                        noClip: true
                                    }
                                });
                                poly.bindPopup('<b>' + (loc.name || 'Area Lokasi Kerja') + '</b>');
                                receiptModalMapGroup.addLayer(poly);

                                const pBounds = poly.getBounds();
                                if (pBounds.isValid()) {
                                    const center = pBounds.getCenter();
                                    if (Math.abs(center.lat - lat) < 0.05 && Math.abs(center.lng - lng) < 0.05) {
                                        nearbyPolyBounds = pBounds;
                                    }
                                }
                            } catch(e) { console.warn('[CanteenReport] Gagal menggambar polygon di peta bukti:', e); }
                        });
                    }

                    // 2) Pointer avatar user — sesuai referensi Gambar 2
                    L.circleMarker([lat, lng], {
                        radius: 9,
                        color: '#ffffff',
                        fillColor: '#1cc88a',
                        fillOpacity: 1,
                        weight: 2,
                        noClip: true
                    }).addTo(receiptModalMap);

                    receiptModalScanMarker = L.marker([lat, lng], {
                        icon: createScanAvatarIcon()
                    });
                    receiptModalScanMarker.bindPopup('<b>Lokasi Scan</b><br>' + lat.toFixed(6) + ', ' + lng.toFixed(6));
                    receiptModalMapGroup.addLayer(receiptModalScanMarker);

                    // Kunci view ke koordinat scan pada zoom 17 (street level)
                    receiptModalMap.setView([lat, lng], 17);

                    setTimeout(() => {
                        if (!receiptModalMap) return;
                        try { receiptModalMap.invalidateSize(); } catch(e) {}
                        try {
                            if (nearbyPolyBounds && nearbyPolyBounds.isValid()) {
                                nearbyPolyBounds.extend([lat, lng]);
                                const calcZ = receiptModalMap.getBoundsZoom(nearbyPolyBounds);
                                if (calcZ >= 16) {
                                    receiptModalMap.fitBounds(nearbyPolyBounds, { padding: [12, 12], maxZoom: 18 });
                                } else {
                                    receiptModalMap.setView([lat, lng], 17);
                                }
                            } else {
                                receiptModalMap.setView([lat, lng], 17);
                            }
                        } catch(e) {
                            receiptModalMap.setView([lat, lng], 17);
                        }
                    }, 150);

                    setTimeout(() => {
                        if (!receiptModalMap) return;
                        try { receiptModalMap.invalidateSize(); } catch(e) {}
                        if (receiptModalMap.getZoom() < 16) {
                            receiptModalMap.setView([lat, lng], 17);
                        }
                    }, 400);
                } catch(err) {
                    console.warn('[CanteenReport] Gagal inisialisasi peta bukti modal:', err);
                }
            }

            function renderReceiptModal(inv) {
                const inside = inv.inside_geomap === null || inv.inside_geomap === undefined ? '-' : (inv.inside_geomap ? 'Ya' : 'Tidak');
                const tglId = formatTanggalId(inv.date);
                const lat = (inv.latitude !== null && inv.latitude !== undefined) ? Number(inv.latitude) : null;
                const lng = (inv.longitude !== null && inv.longitude !== undefined) ? Number(inv.longitude) : null;
                const hasCoords = lat !== null && lng !== null && !isNaN(lat) && !isNaN(lng);
                const photoSrc = formatPhotoFaceSrc(inv.photo_face);

                const box = document.getElementById('receiptModalBody');
                if (!box) return;

                box.innerHTML = `
                    <div class="receipt" id="receiptModalBox">
                        <div class="text-center mb-2">
                            <div class="font-weight-bold">PT Chutex International Indonesia</div>
                            <div class="small text-muted">Bukti transaksi kantin — tunjukkan ke pihak kantin</div>
                        </div>
                        <hr class="my-2">
                        <div class="rounded p-2 mb-2 text-center" style="background:#e8f0fe; border:1px solid #4e73df;">
                            <div style="font-size:1.05rem; font-weight:700; color:#1d3f86;">${esc(inv.canteen_name || ('Kantin ' + inv.canteen_id))}</div>
                            <div style="font-size:1.15rem; font-weight:700; color:#1d3f86;"><i class="far fa-calendar-alt mr-1"></i>${esc(tglId)}</div>
                            <div style="font-size:1.15rem; font-weight:700; color:#1d3f86;"><i class="far fa-clock mr-1"></i>${esc(inv.time)}</div>
                        </div>
                        <div class="rowline"><span>ID Tiket</span><span>${esc(inv.id_ticket || inv.no || '-')}</span></div>
                        <div class="rowline"><span>Lokasi</span><span>${esc(inv.canteen_location || '-')}</span></div>
                        <div class="rowline"><span>Kontak</span><span>${esc(inv.contact_person || '-')}${inv.contact_phone ? ' / ' + esc(inv.contact_phone) : ''}</span></div>
                        <hr class="my-2">
                        <div class="rowline"><span>NPK</span><span>${esc(inv.npk)}</span></div>
                        <div class="rowline"><span>Nama</span><span>${esc(inv.name)}</span></div>
                        <div class="rowline"><span>Dept</span><span>${esc(inv.dept || '-')}</span></div>
                        <div class="rowline"><span>Window</span><span>${esc(inv.window_name)}</span></div>
                        <div class="rowline"><span>Area Geomap</span><span>${esc(inv.location_name || '-')} (${inside})</span></div>
                        ${hasCoords ? `<div class="rowline"><span>Koordinat</span><span>${lat.toFixed(6)}, ${lng.toFixed(6)}</span></div>` : ''}

                        {{-- HASIL SCAN WAJAH DAN MINIMAP: 1 ROW KANAN-KIRI --}}
                        <div class="row no-gutters mt-2 mb-2" style="margin-left:-4px; margin-right:-4px;">
                            <div class="col-6 px-1">
                                <div class="small font-weight-bold text-gray-700 text-center mb-1"><i class="fas fa-camera text-primary mr-1"></i> Foto Wajah</div>
                                <div class="rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden shadow-sm" style="height:170px;">
                                    ${photoSrc
                                        ? `<img src="${photoSrc}" alt="Foto Wajah" style="width:100%; height:100%; object-fit:cover;">`
                                        : `<div class="text-center text-muted p-2"><i class="fas fa-user fa-3x mb-2 text-gray-400"></i><div class="small">Tidak ada foto</div></div>`
                                    }
                                </div>
                            </div>
                            <div class="col-6 px-1">
                                <div class="small font-weight-bold text-gray-700 text-center mb-1"><i class="fas fa-map-marked-alt text-primary mr-1"></i> Lokasi Scan</div>
                                ${hasCoords
                                    ? `<div id="receiptModalMap" class="rounded border shadow-sm" style="height:170px; width:100%; z-index:1;"></div>`
                                    : `<div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted small p-2 text-center" style="height:170px;"><i class="fas fa-map-marker-alt text-gray-400 mr-1"></i> Koordinat tidak tersedia</div>`
                                }
                            </div>
                        </div>

                        <hr class="my-2">
                        <div class="text-center small text-muted">Terima kasih — selamat makan!</div>
                    </div>
                `;

                if (hasCoords) {
                    try {
                        drawReceiptModalMap(lat, lng);
                    } catch(err) {
                        console.warn('[CanteenReport] Gagal memuat peta bukti modal:', err);
                    }
                }
            }

            // Saat modal selesai dibuka, panggil invalidateSize dan pastikan tetap street level zoom (17)
            $('#receiptModal').off('shown.bs.modal.canteenmap').on('shown.bs.modal.canteenmap', function () {
                if (receiptModalMap) {
                    try { receiptModalMap.invalidateSize(); } catch(e) {}
                    if (receiptModalMap.getZoom() < 16) {
                        receiptModalMap.setZoom(17);
                    }
                }
            });

            function esc(str) {
                if (str === null || str === undefined) return '-';
                return String(str).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"').replace(/'/g, '&#039;');
            }

            $('#detailTable').on('click', '.id-ticket-link', function () {
                const idTicket = $(this).data('id_ticket');
                if (!idTicket) return;

                $('#receiptModalBody').html('<div class="text-center text-muted">Memuat bukti scan...</div>');
                $('#receiptModal').modal('show');

                fetch("{{ route('canteen-report.receipt') }}?id_ticket=" + encodeURIComponent(idTicket), {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                })
                .then(res => res.json().catch(()=>({})))
                .then(data => {
                    if (!data.success || !data.invoice) {
                        $('#receiptModalBody').html('<div class="text-center text-danger">Gagal memuat bukti scan: ' + (data.message || 'Unknown error') + '</div>');
                        return;
                    }
                    if (data.locations && data.locations.length) {
                        activeLocations = data.locations;
                    }
                    renderReceiptModal(data.invoice);
                })
                .catch(err => {
                    console.error('[CanteenReport] Error loading receipt:', err);
                    $('#receiptModalBody').html('<div class="text-center text-danger">Terjadi kesalahan jaringan.</div>');
                });
            });

            // Print receipt: cetak langsung dengan layout CSS @media print teroptimasi
            $('#btnPrintReceipt').on('click', function () {
                window.print();
            });
        });
    </script>
</body>
</html>