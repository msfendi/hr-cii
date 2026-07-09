<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<style>
#applicantsSummary .detail-stat-item{ width:100%; }

.vacancy-card{
    border:none;
    border-radius:.9rem;
    transition:transform .15s ease, box-shadow .15s ease;
    overflow:hidden;
}

.vacancy-card:hover{
    transform:translateY(-3px);
    box-shadow:0 .5rem 1.5rem rgba(0,0,0,.1) !important;
}

.vacancy-card .card-accent{
    height:6px;
    width:100%;
}

.accent-open{ background:#1cc88a; }
.accent-upcoming{ background:#36b9cc; }
.accent-expired{ background:#f6c23e; }
.accent-closed{ background:#858796; }
.accent-draft{ background:#d1d3e2; }

.badge-status{
    font-size:.72rem;
    font-weight:700;
    padding:.35em .65em;
    border-radius:50rem;
}

.badge-open{ background:#e6f8f1; color:#159d6f; }
.badge-upcoming{ background:#e3f6f9; color:#1a8fa3; }
.badge-expired{ background:#fef5e0; color:#b8860b; }
.badge-closed{ background:#eaeaea; color:#5a5c69; }
.badge-draft{ background:#eef0fb; color:#6f74b0; }

.tag-pill{
    display:inline-block;
    font-size:.72rem;
    background:#f4f6fb;
    color:#5a5c69;
    border:1px solid #e3e6f0;
    padding:.2rem .55rem;
    border-radius:50rem;
    margin:0 .25rem .25rem 0;
}

.vacancy-position{
    font-size:1.05rem;
    font-weight:700;
    color:#2e2f45;
}

.vacancy-meta{
    font-size:.78rem;
    color:#858796;
}

.deadline-bar{
    height:6px;
    border-radius:50rem;
    background:#eaecf4;
    overflow:hidden;
}

.deadline-bar-fill{
    height:100%;
    border-radius:50rem;
}

.empty-state, .loading-state{
    padding:3.5rem 1rem;
    text-align:center;
    color:#b7b9c8;
}

.criteria-row, .document-row{
    display:flex;
    gap:.5rem;
    margin-bottom:.5rem;
}

.criteria-row input, .document-row input{
    flex:1;
}

#vacancyModal .modal-dialog{
    max-width:760px;
}

.section-label{
    font-size:.78rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.03em;
    color:#4e73df;
    margin-bottom:.6rem;
}

/* ===================== DETAIL MODAL (redesigned) ===================== */

#detailModal .modal-dialog{
    max-width:820px;
}

#detailModal .modal-header{
    border-bottom:none;
    padding-bottom:0;
}

#detailModal .modal-body{
    padding-top:.75rem;
}

.detail-hero{
    border-radius:.9rem;
    padding:1.25rem 1.5rem;
    margin-bottom:1.25rem;
    color:#fff;
    background:linear-gradient(135deg, #4e73df, #6f86e6);
}

.detail-hero.hero-open{ background:linear-gradient(135deg, #17b881, #1cc88a); }
.detail-hero.hero-upcoming{ background:linear-gradient(135deg, #1a8fa3, #36b9cc); }
.detail-hero.hero-expired{ background:linear-gradient(135deg, #d99a1c, #f6c23e); }
.detail-hero.hero-closed{ background:linear-gradient(135deg, #6b6d7d, #858796); }
.detail-hero.hero-draft{ background:linear-gradient(135deg, #9195c9, #b7bade); }

.detail-hero-position{
    font-size:1.3rem;
    font-weight:700;
    line-height:1.3;
}

.detail-hero-meta{
    font-size:.85rem;
    opacity:.9;
    margin-top:.15rem;
}

.detail-hero .badge-status{
    background:rgba(255,255,255,.92);
}

.detail-stats-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:.75rem;
    margin-bottom:1.25rem;
}

@media (max-width: 576px){
    .detail-stats-grid{ grid-template-columns:repeat(2, 1fr); }
}

.detail-stat-item{
    display:flex;
    align-items:center;
    gap:.65rem;
    background:#f8f9fc;
    border:1px solid #eaecf4;
    border-radius:.7rem;
    padding:.65rem .8rem;
}

.detail-stat-icon{
    width:36px;
    height:36px;
    min-width:36px;
    border-radius:.6rem;
    background:#eef2ff;
    color:#4e73df;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:.9rem;
}

.detail-stat-label{
    font-size:.7rem;
    color:#96999f;
    text-transform:uppercase;
    letter-spacing:.02em;
}

.detail-stat-value{
    font-size:.9rem;
    font-weight:700;
    color:#2e2f45;
}

.detail-deadline-wrap{
    background:#f8f9fc;
    border:1px solid #eaecf4;
    border-radius:.7rem;
    padding:.75rem .9rem;
    margin-bottom:1.25rem;
}

.detail-section{
    background:#fff;
    border:1px solid #eaecf4;
    border-radius:.7rem;
    padding:.9rem 1rem;
    margin-bottom:1rem;
    height:100%;
}

.detail-richtext{
    font-size:.88rem;
    color:#4a4c5f;
    line-height:1.6;
}

.detail-richtext p{ margin-bottom:.5rem; }
.detail-richtext ol, .detail-richtext ul{ padding-left:1.25rem; margin-bottom:.5rem; }

.detail-checklist{
    list-style:none;
    padding-left:0;
    margin-bottom:0;
}

.detail-checklist li{
    font-size:.85rem;
    color:#4a4c5f;
    padding:.3rem 0;
    border-bottom:1px dashed #eee;
    display:flex;
    align-items:flex-start;
    gap:.4rem;
}

.detail-checklist li:last-child{ border-bottom:none; }

.detail-checklist li i{ margin-top:.2rem; }

.detail-empty-note{
    font-size:.82rem;
    color:#b7b9c8;
    font-style:italic;
    margin-bottom:0;
}

/* ===================== RICH TEXT EDITOR (job description) ===================== */

.ql-toolbar.ql-snow{
    border:1px solid #d1d3e2;
    border-bottom:none;
    border-radius:.5rem .5rem 0 0;
    background:#f8f9fc;
}

.ql-container.ql-snow{
    border:1px solid #d1d3e2;
    border-radius:0 0 .5rem .5rem;
}

#fDescriptionEditor{
    height:150px;
    background:#fff;
    font-size:.9rem;
}

#fDescriptionEditor .ql-editor.ql-blank::before{
    font-style:normal;
    color:#b7b9c8;
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
                        <h1 class="h3 mb-0 text-gray-800">Job Vacancy</h1>
                        <button type="button" class="btn btn-primary shadow-sm" id="btnAddVacancy">
                            <i class="fas fa-plus fa-sm mr-1"></i> Buat Lowongan Baru
                        </button>
                    </div>

                    {{-- ===================== FILTER PANEL ===================== --}}
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="filterForm">
                                <div class="row align-items-start">

                                    <div class="col-md-3 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Cari Posisi</label>
                                        <input type="text" class="form-control" id="searchPosition" name="search" placeholder="cth: Staff Finance">
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Department</label>
                                        <select id="deptSelect" class="form-control" style="width:100%">
                                            <option value="">Semua Department</option>
                                            @foreach ($departments as $dept)
                                                <option value="{{ $dept->ID_DEPT }}">{{ $dept->DEPARTEMENT }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Tipe Pekerjaan</label>
                                        <select id="typeSelect" class="form-control" style="width:100%">
                                            <option value="">Semua Tipe</option>
                                            <option value="full_time">Full Time</option>
                                            <option value="part_time">Part Time</option>
                                            <option value="contract">Kontrak</option>
                                            <option value="internship">Magang</option>
                                            <option value="daily_worker">Harian Lepas</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label class="small font-weight-bold text-gray-600 mb-1 d-block">Status</label>
                                        <select id="statusSelect" class="form-control" style="width:100%">
                                            <option value="">Semua Status</option>
                                            <option value="open">Dibuka</option>
                                            <option value="upcoming">Akan Dibuka</option>
                                            <option value="expired">Berakhir</option>
                                            <option value="closed">Ditutup</option>
                                            <option value="draft">Draft</option>
                                        </select>
                                    </div>

                                </div>

                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary shadow-sm mr-2">
                                        <i class="fas fa-search fa-sm mr-1"></i> Terapkan Filter
                                    </button>
                                    <button type="button" id="resetFilter" class="btn btn-secondary shadow-sm">
                                        <i class="fas fa-undo fa-sm mr-1"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- ===================== KPI CARDS ===================== --}}
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Lowongan Sedang Dibuka</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiOpen">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-door-open fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Kebutuhan Karyawan</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiNeeded">0 orang</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Total Pelamar</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiTotalApplicants">0 orang</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Segera Berakhir (≤ 7 hari)</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpiClosingSoon">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== GRID LOWONGAN ===================== --}}
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Daftar Lowongan Pekerjaan</h6>
                        </div>
                        <div class="card-body">

                            <div id="loadingState" class="loading-state" style="display:none;">
                                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                <p class="mb-0">Memuat data lowongan...</p>
                            </div>

                            <div id="emptyState" class="empty-state" style="display:none;">
                                <i class="fas fa-briefcase fa-2x mb-2"></i>
                                <p class="mb-0">Belum ada lowongan pekerjaan yang cocok dengan filter ini.</p>
                            </div>

                            <div class="row" id="vacancyGrid"></div>

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

    {{-- ===================== MODAL DETAIL (redesigned) ===================== --}}
    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold">Detail Lowongan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="detailBody">
                    <!-- diisi via JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== MODAL FORM (CREATE/EDIT) ===================== --}}
    <div class="modal fade" id="vacancyModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="vacancyForm">
                    <div class="modal-header">
                        <h5 class="modal-title font-weight-bold" id="vacancyModalTitle">Buat Lowongan Baru</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">

                        <input type="hidden" id="vacancyId" name="id">

                        <div class="section-label"><i class="fas fa-info-circle mr-1"></i>Informasi Umum</div>

                        <div class="form-group">
                            <label class="small font-weight-bold text-gray-600">Posisi yang Dibuka <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fPosition" name="position" placeholder="cth: Staff Accounting" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-gray-600">Department</label>
                                <select id="fDepartment" name="department_id" class="form-control" style="width:100%">
                                    <option value="">Pilih Department</option>
                                    @foreach ($departments as $dept)
                                        <option value="{{ $dept->ID_DEPT }}">{{ $dept->DEPARTEMENT }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-gray-600">Tipe Pekerjaan <span class="text-danger">*</span></label>
                                <select id="fType" name="employment_type" class="form-control" style="width:100%" required>
                                    <option value="full_time">Full Time</option>
                                    <option value="part_time">Part Time</option>
                                    <option value="contract">Kontrak</option>
                                    <option value="internship">Magang</option>
                                    <option value="daily_worker">Harian Lepas</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-gray-600">Jumlah Karyawan Dibutuhkan <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" id="fTotalNeeded" name="total_needed" value="1" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-gray-600">Status <span class="text-danger">*</span></label>
                                <select id="fStatus" name="status" class="form-control" style="width:100%" required>
                                    <option value="draft">Draft (belum dipublikasikan)</option>
                                    <option value="open" selected>Dibuka</option>
                                    <option value="closed">Ditutup</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-gray-600">Tanggal Dibuka <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fOpenDate" name="open_date" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold text-gray-600">Tanggal Ditutup <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fCloseDate" name="close_date" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold text-gray-600">Deskripsi Pekerjaan</label>
                            <div id="fDescriptionEditor"></div>
                            <textarea id="fDescription" name="job_description" style="display:none;"></textarea>
                        </div>

                        <hr>

                        <div class="section-label"><i class="fas fa-list-check mr-1"></i>Kriteria yang Dibutuhkan</div>
                        <div id="criteriaWrapper"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="addCriteria">
                            <i class="fas fa-plus mr-1"></i> Tambah Kriteria
                        </button>

                        <hr>

                        <div class="section-label"><i class="fas fa-file-alt mr-1"></i>Dokumen yang Diperlukan</div>
                        <div id="documentWrapper"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="addDocument">
                            <i class="fas fa-plus mr-1"></i> Tambah Dokumen
                        </button>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveVacancy">
                            <i class="fas fa-save mr-1"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== MODAL DETAIL PELAMAR ===================== --}}
    <div class="modal fade" id="applicantsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0">Daftar Pelamar</h5>
                        <div class="small text-muted" id="applicantsPositionTitle"></div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3" id="applicantsSummary"></div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="applicantsTable" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Nama</th>
                                    <th>No. HP</th>
                                    <th>Pendidikan</th>
                                    <th>Tanggal Melamar</th>
                                    <th style="width:60px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

    <script>
        const routes = {
            data: "{{ route('job-vacancy.data') }}",
            store: "{{ route('job-vacancy.store') }}",
            edit: (id) => `{{ url('job-vacancy') }}/${id}/edit`,
            update: (id) => `{{ url('job-vacancy') }}/${id}`,
            destroy: (id) => `{{ url('job-vacancy') }}/${id}`,
            toggleStatus: (id) => `{{ url('job-vacancy') }}/${id}/toggle-status`,
            applicants: (id) => `{{ url('job-vacancy') }}/${id}/applicants`,
        };

        const csrfToken = "{{ csrf_token() }}";

        const formatDateID = (value) => {
            if (!value) return '-';
            const d = new Date(value);
            if (isNaN(d)) return value;
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        };

        const statusAccent = {
            open: 'accent-open',
            upcoming: 'accent-upcoming',
            expired: 'accent-expired',
            closed: 'accent-closed',
            draft: 'accent-draft',
        };

        const statusBadge = {
            open: 'badge-open',
            upcoming: 'badge-upcoming',
            expired: 'badge-expired',
            closed: 'badge-closed',
            draft: 'badge-draft',
        };

        const statusHero = {
            open: 'hero-open',
            upcoming: 'hero-upcoming',
            expired: 'hero-expired',
            closed: 'hero-closed',
            draft: 'hero-draft',
        };

        function deadlineProgress(openDate, closeDate) {
            const start = new Date(openDate).getTime();
            const end = new Date(closeDate).getTime();
            const now = Date.now();
            if (!start || !end || end <= start) return 0;
            const pct = ((now - start) / (end - start)) * 100;
            return Math.min(100, Math.max(0, pct));
        }

        function deadlineColor(daysLeft) {
            if (daysLeft === null) return '#4e73df';
            if (daysLeft <= 3) return '#e74a3b';
            if (daysLeft <= 7) return '#f6c23e';
            return '#1cc88a';
        }

        function renderVacancyCard(v) {
            const accent = statusAccent[v.computed_status] || 'accent-draft';
            const badge = statusBadge[v.computed_status] || 'badge-draft';
            const progress = deadlineProgress(v.open_date, v.close_date);
            const barColor = deadlineColor(v.days_left);

            let deadlineText = '-';
            if (v.computed_status === 'open') {
                deadlineText = v.days_left === 0 ? 'Berakhir hari ini' : `${v.days_left} hari lagi`;
            } else if (v.computed_status === 'upcoming') {
                deadlineText = `Dibuka ${v.open_date_formatted}`;
            } else if (v.computed_status === 'expired') {
                deadlineText = `Berakhir ${v.close_date_formatted}`;
            } else if (v.computed_status === 'closed') {
                deadlineText = 'Ditutup manual';
            } else {
                deadlineText = 'Belum dipublikasikan';
            }

            const criteriaTags = (v.criteria || []).slice(0, 4)
                .map(c => `<span class="tag-pill">${$('<div>').text(c).html()}</span>`).join('');
            const moreCriteria = (v.criteria || []).length > 4
                ? `<span class="tag-pill">+${v.criteria.length - 4} lainnya</span>` : '';

            const docTags = (v.required_documents || []).slice(0, 3)
                .map(d => `<span class="tag-pill"><i class="fas fa-paperclip mr-1"></i>${$('<div>').text(d).html()}</span>`).join('');
            const moreDocs = (v.required_documents || []).length > 3
                ? `<span class="tag-pill">+${v.required_documents.length - 3} lainnya</span>` : '';

            const toggleLabel = v.status === 'closed' ? 'Buka Kembali' : 'Tutup Lowongan';
            const toggleIcon = v.status === 'closed' ? 'fa-unlock' : 'fa-lock';

            return `
            <div class="col-xl-4 col-md-6 mb-4" data-id="${v.id}">
                <div class="card vacancy-card shadow h-100">
                    <div class="card-accent ${accent}"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="vacancy-position">${$('<div>').text(v.position).html()}</div>
                                <div class="vacancy-meta"><i class="fas fa-building mr-1"></i>${$('<div>').text(v.department).html()} &middot; ${v.employment_type_label}</div>
                            </div>
                            <span class="badge-status ${badge}">${v.computed_status_label}</span>
                        </div>

                        <div class="d-flex flex-wrap align-items-center mb-2 mt-3">
                            <div class="mr-3 mb-2">
                                <i class="fas fa-users text-gray-400 mr-2"></i>
                                <span class="small font-weight-bold text-gray-700">${v.total_needed} orang dibutuhkan</span>
                            </div>
                            <span class="tag-pill mb-2" data-action="applicants" role="button"
                                style="background:#eef2ff;color:#4e73df;border-color:#dce3fc;cursor:pointer;"
                                title="Lihat detail pelamar">
                                <i class="fas fa-user-friends mr-1"></i>${v.applicant_count} orang sudah melamar
                                <i class="fas fa-chevron-right ml-1" style="font-size:.65rem;"></i>
                            </span>
                        </div>

                        <div class="mb-2">
                            <div class="d-flex justify-content-between vacancy-meta mb-1">
                                <span><i class="fas fa-calendar-day mr-1"></i>${v.open_date_formatted}</span>
                                <span><i class="fas fa-calendar-check mr-1"></i>${v.close_date_formatted}</span>
                            </div>
                            <div class="deadline-bar">
                                <div class="deadline-bar-fill" style="width:${progress}%;background:${barColor}"></div>
                            </div>
                            <div class="vacancy-meta mt-1 text-right">${deadlineText}</div>
                        </div>

                        ${criteriaTags ? `<div class="mt-3 mb-1"><div class="vacancy-meta font-weight-bold mb-1">Kriteria</div>${criteriaTags}${moreCriteria}</div>` : ''}
                        ${docTags ? `<div class="mt-2"><div class="vacancy-meta font-weight-bold mb-1">Dokumen</div>${docTags}${moreDocs}</div>` : ''}
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 d-flex justify-content-between">
                        <button class="btn btn-sm btn-light" data-action="detail" title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div>
                            <button class="btn btn-sm btn-light" data-action="toggle" title="${toggleLabel}">
                                <i class="fas ${toggleIcon}"></i>
                            </button>
                            <button class="btn btn-sm btn-light" data-action="edit" title="Edit">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-light text-danger" data-action="delete" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        let vacancyStore = [];
        let quillDescription;
        let applicantsDataTable;
        let applicantsStore = [];

        function escapeHtml(str) {
            return $('<div>').text(str == null ? '' : str).html();
        }

        function initApplicantsTable() {
            if ($.fn.DataTable.isDataTable('#applicantsTable')) {
                applicantsDataTable.clear().destroy();
                $('#applicantsTable tbody').empty();
            }
            applicantsDataTable = $('#applicantsTable').DataTable({
                pageLength: 10,
                lengthMenu: [5, 10, 25, 50],
                order: [[5, 'desc']],
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_-_END_ dari _TOTAL_ pelamar',
                    infoEmpty: 'Tidak ada data',
                    zeroRecords: 'Tidak ditemukan pelamar yang cocok',
                    emptyTable: 'Belum ada pelamar untuk posisi ini.',
                    paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                },
            });
        }

        function renderApplicantsSummary(summary) {
            $('#applicantsSummary').html(`
                <div class="col-md-4 mb-2">
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-user-friends"></i></div>
                        <div>
                            <div class="detail-stat-label">Total Pelamar</div>
                            <div class="detail-stat-value">${summary.total} orang</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-calendar-day"></i></div>
                        <div>
                            <div class="detail-stat-label">Periode Lamar</div>
                            <div class="detail-stat-value" style="font-size:.8rem;">${summary.open_date} - ${summary.close_date}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-briefcase"></i></div>
                        <div>
                            <div class="detail-stat-label">Posisi</div>
                            <div class="detail-stat-value" style="font-size:.85rem;">${escapeHtml(summary.position)}</div>
                        </div>
                    </div>
                </div>
            `);
        }

        function loadApplicants(id, vacancy) {
            $('#applicantsPositionTitle').text(vacancy.position + ' • ' + vacancy.department);
            $('#applicantsSummary').html('<div class="col-12 text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat data pelamar...</div>');

            if (applicantsDataTable) {
                applicantsDataTable.clear().destroy();
                $('#applicantsTable tbody').empty();
            }
            applicantsStore = [];

            fetch(routes.applicants(id))
                .then(async res => {
                    const body = await res.json();
                    if (!res.ok) throw body;
                    return body;
                })
                .then(data => {
                    renderApplicantsSummary(data.summary);
                    initApplicantsTable();

                    applicantsStore = data.applicants;

                    if (!data.applicants.length) {
                        applicantsDataTable.draw();
                        return;
                    }

                    data.applicants.forEach(a => {
                        applicantsDataTable.row.add([
                            a.no,
                            `<div class="font-weight-bold">${escapeHtml(a.name || '-')}</div>
                            <div class="small text-muted">${escapeHtml(a.gender || '-')}</div>`,
                            escapeHtml(a.phone || '-'),
                            escapeHtml(a.education || '-'),
                            escapeHtml(a.applied_at_formatted || '-'),
                            `<button type="button" class="btn btn-sm btn-light" data-detail-id="${a.id}" title="Detail Lengkap">
                                <i class="fas fa-eye"></i>
                            </button>`,
                        ]);
                    });

                    applicantsDataTable.draw();
                })
                .catch(err => {
                    $('#applicantsSummary').html('');
                    const detail = err && err.message ? err.message : 'Terjadi kesalahan saat memuat data pelamar.';
                    Swal.fire('Gagal', detail, 'error');
                });
        }

        function loadVacancies() {
            const params = {
                search: $('#searchPosition').val(),
                department: $('#deptSelect').val(),
                employment_type: $('#typeSelect').val(),
                status: $('#statusSelect').val(),
            };

            $('#loadingState').show();
            $('#emptyState').hide();
            $('#vacancyGrid').empty();

            fetch(routes.data + '?' + new URLSearchParams(params))
                .then(async res => {
                    const body = await res.json();
                    if (!res.ok) throw body;
                    return body;
                })
                .then(data => {
                    $('#loadingState').hide();
                    vacancyStore = data.rows;

                    $('#kpiOpen').text(data.kpi.total_open);
                    $('#kpiNeeded').text(data.kpi.total_needed_open + ' orang');
                    $('#kpiTotalApplicants').text(data.kpi.total_applicants + ' orang');
                    $('#kpiClosingSoon').text(data.kpi.closing_soon);

                    if (!data.rows.length) {
                        $('#emptyState').show();
                        return;
                    }

                    const html = data.rows.map(renderVacancyCard).join('');
                    $('#vacancyGrid').html(html);
                })
                .catch(err => {
                    $('#loadingState').hide();
                    console.error('Gagal memuat data lowongan:', err);
                    const detail = err && err.message ? err.message : 'Terjadi kesalahan saat memuat data.';
                    Swal.fire('Gagal', detail, 'error');
                });
        }

        function findVacancy(id) {
            return vacancyStore.find(v => String(v.id) === String(id));
        }

        function addCriteriaRow(value = '') {
            const row = $(`
                <div class="criteria-row">
                    <input type="text" class="form-control" placeholder="cth: Minimal S1 Akuntansi" value="${$('<div>').text(value).html()}">
                    <button type="button" class="btn btn-outline-danger btn-remove-row"><i class="fas fa-times"></i></button>
                </div>
            `);
            $('#criteriaWrapper').append(row);
        }

        function addDocumentRow(value = '') {
            const row = $(`
                <div class="document-row">
                    <input type="text" class="form-control" placeholder="cth: CV Terbaru" value="${$('<div>').text(value).html()}">
                    <button type="button" class="btn btn-outline-danger btn-remove-row"><i class="fas fa-times"></i></button>
                </div>
            `);
            $('#documentWrapper').append(row);
        }

        function resetVacancyForm() {
            $('#vacancyForm')[0].reset();
            $('#vacancyId').val('');
            $('#fDepartment').val('').trigger('change');
            $('#fType').val('full_time').trigger('change');
            $('#fStatus').val('open').trigger('change');
            $('#criteriaWrapper').empty();
            $('#documentWrapper').empty();
            addCriteriaRow();
            addDocumentRow();
            if (quillDescription) {
                quillDescription.setText('');
            }
            $('#fDescription').val('');
            $('#vacancyModalTitle').text('Buat Lowongan Baru');
        }

        function populateVacancyForm(v) {
            $('#vacancyId').val(v.id);
            $('#fPosition').val(v.position);
            $('#fDepartment').val(v.department_id || '').trigger('change');
            $('#fType').val(v.employment_type).trigger('change');
            $('#fTotalNeeded').val(v.total_needed);
            $('#fStatus').val(v.status).trigger('change');
            $('#fOpenDate').val(v.open_date);
            $('#fCloseDate').val(v.close_date);

            if (quillDescription) {
                quillDescription.root.innerHTML = v.job_description || '';
            }
            $('#fDescription').val(v.job_description || '');

            $('#criteriaWrapper').empty();
            $('#documentWrapper').empty();
            (v.criteria && v.criteria.length ? v.criteria : ['']).forEach(addCriteriaRow);
            (v.required_documents && v.required_documents.length ? v.required_documents : ['']).forEach(addDocumentRow);

            $('#vacancyModalTitle').text('Edit Lowongan Pekerjaan');
        }

        function showDetail(v) {
            const badge = statusBadge[v.computed_status] || 'badge-draft';
            const hero = statusHero[v.computed_status] || 'hero-draft';
            const progress = deadlineProgress(v.open_date, v.close_date);
            const barColor = deadlineColor(v.days_left);

            let deadlineText = '-';
            if (v.computed_status === 'open') {
                deadlineText = v.days_left === 0 ? 'Berakhir hari ini' : `${v.days_left} hari lagi`;
            } else if (v.computed_status === 'upcoming') {
                deadlineText = `Dibuka ${v.open_date_formatted}`;
            } else if (v.computed_status === 'expired') {
                deadlineText = `Berakhir ${v.close_date_formatted}`;
            } else if (v.computed_status === 'closed') {
                deadlineText = 'Ditutup manual';
            } else {
                deadlineText = 'Belum dipublikasikan';
            }

            const criteriaList = (v.criteria || []).length
                ? '<ul class="detail-checklist">' + v.criteria.map(c => `<li><i class="fas fa-check-circle text-success"></i><span>${$('<div>').text(c).html()}</span></li>`).join('') + '</ul>'
                : '<p class="detail-empty-note">Tidak ada kriteria khusus.</p>';

            const docList = (v.required_documents || []).length
                ? '<ul class="detail-checklist">' + v.required_documents.map(d => `<li><i class="fas fa-paperclip text-primary"></i><span>${$('<div>').text(d).html()}</span></li>`).join('') + '</ul>'
                : '<p class="detail-empty-note">Tidak ada dokumen khusus.</p>';

            $('#detailBody').html(`
                <div class="detail-hero ${hero}">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <div class="detail-hero-position">${$('<div>').text(v.position).html()}</div>
                            <div class="detail-hero-meta">
                                <i class="fas fa-building mr-1"></i>${$('<div>').text(v.department).html()}
                                &middot; <i class="fas fa-briefcase mr-1"></i>${v.employment_type_label}
                            </div>
                        </div>
                        <span class="badge-status ${badge} mt-1">${v.computed_status_label}</span>
                    </div>
                </div>

                <div class="detail-stats-grid">
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-users"></i></div>
                        <div>
                            <div class="detail-stat-label">Dibutuhkan</div>
                            <div class="detail-stat-value">${v.total_needed} orang</div>
                        </div>
                    </div>
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-user-friends"></i></div>
                        <div>
                            <div class="detail-stat-label">Pelamar</div>
                            <div class="detail-stat-value">${v.applicant_count} orang</div>
                        </div>
                    </div>
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-calendar-day"></i></div>
                        <div>
                            <div class="detail-stat-label">Dibuka</div>
                            <div class="detail-stat-value">${v.open_date_formatted}</div>
                        </div>
                    </div>
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="detail-stat-label">Ditutup</div>
                            <div class="detail-stat-value">${v.close_date_formatted}</div>
                        </div>
                    </div>
                </div>

                <div class="detail-deadline-wrap">
                    <div class="deadline-bar">
                        <div class="deadline-bar-fill" style="width:${progress}%;background:${barColor}"></div>
                    </div>
                    <div class="vacancy-meta mt-1 text-right">${deadlineText}</div>
                </div>

                ${v.job_description ? `
                <div class="detail-section">
                    <div class="section-label"><i class="fas fa-align-left mr-1"></i>Deskripsi Pekerjaan</div>
                    <div class="detail-richtext">${v.job_description}</div>
                </div>` : ''}

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="detail-section">
                            <div class="section-label"><i class="fas fa-list-check mr-1"></i>Kriteria yang Dibutuhkan</div>
                            ${criteriaList}
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="detail-section">
                            <div class="section-label"><i class="fas fa-file-alt mr-1"></i>Dokumen yang Diperlukan</div>
                            ${docList}
                        </div>
                    </div>
                </div>
            `);

            $('#detailModal').modal('show');
        }

        $(document).ready(function () {
            // Select2 di luar modal (filter panel) - dropdown boleh render ke body seperti biasa
            $('#deptSelect, #typeSelect, #statusSelect').select2({
                width: '100%',
            });

            // Select2 DI DALAM modal wajib pakai dropdownParent, kalau tidak
            // dropdown-nya dirender ke <body> lalu Bootstrap modal menangkap/
            // mengembalikan fokus keyboard ke modal sehingga kotak pencarian
            // Select2 tidak bisa diketik.
            $('#fDepartment, #fType, #fStatus').select2({
                width: '100%',
                dropdownParent: $('#vacancyModal'),
            });

            // Rich text editor untuk deskripsi pekerjaan (bold, italic, numbering, dll)
            quillDescription = new Quill('#fDescriptionEditor', {
                theme: 'snow',
                placeholder: 'Ringkasan tanggung jawab pekerjaan...',
                modules: {
                    toolbar: [
                        [{ header: [false, 1, 2, 3] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ indent: '-1' }, { indent: '+1' }],
                        ['link'],
                        ['clean'],
                    ],
                },
            });

            // Sinkronkan isi editor ke textarea tersembunyi (yang dikirim ke server)
            quillDescription.on('text-change', function () {
                const isEmpty = quillDescription.getText().trim().length === 0;
                $('#fDescription').val(isEmpty ? '' : quillDescription.root.innerHTML);
            });

            loadVacancies();

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                loadVacancies();
            });

            $('#resetFilter').on('click', function () {
                $('#searchPosition').val('');
                $('#deptSelect').val('').trigger('change');
                $('#typeSelect').val('').trigger('change');
                $('#statusSelect').val('').trigger('change');
                loadVacancies();
            });

            $('#btnAddVacancy').on('click', function () {
                resetVacancyForm();
                $('#vacancyModal').modal('show');
            });

            $('#addCriteria').on('click', () => addCriteriaRow());
            $('#addDocument').on('click', () => addDocumentRow());

            $(document).on('click', '.btn-remove-row', function () {
                $(this).closest('.criteria-row, .document-row').remove();
            });
            $(document).on('click', '[data-detail-id]', function () {
                const id = $(this).data('detail-id');
                const a = applicantsStore.find(x => String(x.id) === String(id));
                if (!a) return;

                Swal.fire({
                    title: a.name || 'Detail Pelamar',
                    html: `
                        <div class="text-left small">
                            <p class="mb-1"><strong>No. HP:</strong> ${escapeHtml(a.phone || '-')}</p>
                            <p class="mb-1"><strong>Jenis Kelamin:</strong> ${escapeHtml(a.gender || '-')}</p>
                            <p class="mb-1"><strong>Pendidikan Terakhir:</strong> ${escapeHtml(a.education || '-')}</p>
                            <p class="mb-1"><strong>Alamat:</strong> ${escapeHtml(a.address || '-')}</p>
                            <p class="mb-1"><strong>Posisi Dilamar:</strong> ${escapeHtml(a.position || '-')}</p>
                            <p class="mb-0"><strong>Tanggal Melamar:</strong> ${escapeHtml(a.applied_at_formatted || '-')}</p>
                        </div>
                    `,
                    confirmButtonText: 'Tutup',
                });
            });

            $(document).on('click', '[data-action]', function () {
                const card = $(this).closest('[data-id]');
                const id = card.data('id');
                const action = $(this).data('action');
                const vacancy = findVacancy(id);
                if (!vacancy) return;

                if (action === 'detail') {
                    showDetail(vacancy);
                } else if (action === 'applicants') {
                    $('#applicantsModal').modal('show');
                    loadApplicants(id, vacancy);
                } else if (action === 'edit') {
                    fetch(routes.edit(id))
                        .then(res => res.json())
                        .then(data => {
                            populateVacancyForm(data);
                            $('#vacancyModal').modal('show');
                        });
                } else if (action === 'delete') {
                    Swal.fire({
                        title: 'Hapus lowongan ini?',
                        text: `Lowongan "${vacancy.position}" akan dihapus.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, hapus',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#e74a3b',
                    }).then(result => {
                        if (!result.isConfirmed) return;

                        fetch(routes.destroy(id), {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        })
                            .then(res => res.json())
                            .then(data => {
                                Swal.fire('Berhasil', data.message, 'success');
                                loadVacancies();
                            })
                            .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat menghapus data.', 'error'));
                    });
                } else if (action === 'toggle') {
                    fetch(routes.toggleStatus(id), {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                    })
                        .then(res => res.json())
                        .then(data => {
                            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2000 });
                            loadVacancies();
                        })
                        .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan saat mengubah status.', 'error'));
                }
            });

            $('#vacancyForm').on('submit', function (e) {
                e.preventDefault();

                const id = $('#vacancyId').val();
                const criteria = $('#criteriaWrapper input').map(function () { return $(this).val(); }).get();
                const documents = $('#documentWrapper input').map(function () { return $(this).val(); }).get();

                const payload = {
                    position: $('#fPosition').val(),
                    department_id: $('#fDepartment').val(),
                    employment_type: $('#fType').val(),
                    total_needed: $('#fTotalNeeded').val(),
                    status: $('#fStatus').val(),
                    open_date: $('#fOpenDate').val(),
                    close_date: $('#fCloseDate').val(),
                    job_description: $('#fDescription').val(),
                    criteria,
                    required_documents: documents,
                };

                const isEdit = !!id;
                const url = isEdit ? routes.update(id) : routes.store;

                $('#btnSaveVacancy').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

                fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) throw data;
                        return data;
                    })
                    .then(data => {
                        $('#vacancyModal').modal('hide');
                        Swal.fire('Berhasil', data.message, 'success');
                        loadVacancies();
                    })
                    .catch(err => {
                        const firstError = err && err.errors
                            ? Object.values(err.errors)[0][0]
                            : (err && err.message ? err.message : 'Terjadi kesalahan saat menyimpan data.');
                        Swal.fire('Gagal', firstError, 'error');
                    })
                    .finally(() => {
                        $('#btnSaveVacancy').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan');
                    });
            });
        });
    </script>
</body>

</html>