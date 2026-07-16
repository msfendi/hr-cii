<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<body id="page-top">
    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid px-4">

                    {{-- Page Header --}}
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h4 class="mb-0 font-weight-bold text-gray-800">Manajemen Kontrak Karyawan</h4>
                            <small class="text-muted">Pantau & kelola kontrak yang akan segera berakhir</small>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm" data-toggle="modal"
                                data-target="#modalImport">
                                <i class="fas fa-file-import mr-1"></i> Import
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="btnExport">
                                <i class="fas fa-file-excel mr-1"></i> Export
                            </button>
                            <button class="btn btn-outline-success btn-sm" id="btnExportAll">
                                <i class="fas fa-file-excel mr-1"></i> Export All Contract
                            </button>
                            {{-- <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambah">
                                <i class="fas fa-plus mr-1"></i> Tambah Kontrak
                            </button> --}}
                        </div>
                    </div>

                    {{-- Summary Cards --}}
                    <div class="row mb-3">
                        <div class="col-xl-3 col-md-6 mb-2">
                            <div class="card border-left-danger shadow-sm h-100 py-2" style="cursor:pointer"
                                data-urgensi="urgent">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">≤ 7 Hari</div>
                                    <div class="h5 mb-0 font-weight-bold" id="cnt-urgent">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-2">
                            <div class="card border-left-warning shadow-sm h-100 py-2" style="cursor:pointer"
                                data-urgensi="soon">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">8–14 Hari
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold" id="cnt-soon">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-2">
                            <div class="card border-left-primary shadow-sm h-100 py-2" style="cursor:pointer"
                                data-urgensi="upcoming">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">15–30 Hari
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold" id="cnt-upcoming">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-2">
                            <div class="card border-left-success shadow-sm h-100 py-2" style="cursor:pointer"
                                data-urgensi="all">
                                <div class="card-body py-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total</div>
                                    <div class="h5 mb-0 font-weight-bold" id="cnt-all">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Filter Bar --}}
                    <div class="card shadow-sm mb-3">
                        <div class="card-body py-2 px-3">
                            <div class="row align-items-end g-2">
                                <div class="col-md-2">
                                    <label class="small font-weight-bold mb-1">Periode</label>
                                    <input type="month" id="filterMonth" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="small font-weight-bold mb-1">Status</label>
                                    <select id="filterStatus" class="form-control form-control-sm">
                                        <option value="ALL">Semua Status</option>
                                        <option value="AKTIF" selected>AKTIF</option>
                                        <option value="HABIS">HABIS</option>
                                        <option value="DIPERPANJANG">DIPERPANJANG</option>
                                        <option value="DIAKHIRI">DIAKHIRI</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="small font-weight-bold mb-1">Bagian</label>
                                    <select id="filterBagian" class="form-control form-control-sm">
                                        <option value="">Semua Bagian</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="small font-weight-bold mb-1">Cari NPK / Nama</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" id="filterNpk" class="form-control"
                                            placeholder="C-00001 atau nama karyawan…">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" id="btnClearNpk"
                                                title="Hapus pencarian">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="small font-weight-bold mb-1">Urgensi</label>
                                    <div class="btn-group btn-group-sm w-100">
                                        <button class="btn btn-danger active" data-urgensi="urgent">≤7hr</button>
                                        <button class="btn btn-outline-warning" data-urgensi="soon">8-14hr</button>
                                        <button class="btn btn-outline-primary" data-urgensi="upcoming">15-30hr</button>
                                        <button class="btn btn-outline-secondary" data-urgensi="all">Semua</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DataTable --}}
                    <div class="card shadow-sm mb-4">
                        <div class="card-body px-3 pt-3 pb-2">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm mb-0" id="contractTable"
                                    width="100%">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="pl-3">#</th>
                                            <th>NPK</th>
                                            <th>Nama</th>
                                            <th>Bagian</th>
                                            <th class="text-center">Ke-</th>
                                            <th>Mulai</th>
                                            <th>Berakhir</th>
                                            <th class="text-center">Sisa</th>
                                            <th class="text-center">Cut-off</th>
                                            <th class="text-center">Durasi</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-right">Gaji Pokok</th>
                                            <th class="text-right">Tunjangan</th>
                                            <th class="text-right">PPH21</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>{{-- /container --}}
            </div>
            @include('layout.footer')
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
    MODAL: TAMBAH KONTRAK
    ════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white py-3">
                    <h6 class="modal-title mb-0"><i class="fas fa-plus-circle mr-2"></i>Tambah Kontrak</h6>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">NPK <span class="text-danger">*</span></label>
                        <input type="text" id="inp_npk" class="form-control form-control-sm" placeholder="C-00001">
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Kontrak Ke-</label>
                                <input type="number" id="inp_ke" class="form-control form-control-sm" value="1" min="1">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Durasi</label>
                                <select id="inp_duration" class="form-control form-control-sm">
                                    <option value="" selected disabled>Pilih Durasi</option>
                                    <option value="1">1 bulan</option>
                                    <option value="2">2 bulan</option>
                                    <option value="3">3 bulan</option>
                                    <option value="4">4 bulan</option>
                                    <option value="5">5 bulan</option>
                                    <option value="6">6 bulan</option>
                                    <option value="7">7 bulan</option>
                                    <option value="8">8 bulan</option>
                                    <option value="9">9 bulan</option>
                                    <option value="10">10 bulan</option>
                                    <option value="11">11 bulan</option>
                                    <option value="12">12 bulan</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Tgl Mulai <span
                                        class="text-danger">*</span></label>
                                <input type="date" id="inp_start" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Tgl Berakhir <span
                                        class="text-danger">*</span></label>
                                <input type="date" id="inp_end" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Gaji Pokok</label>
                                <input type="number" id="inp_salary" class="form-control form-control-sm" value="0"
                                    min="0">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Tunjangan</label>
                                <input type="number" id="inp_allowance" class="form-control form-control-sm" value="0"
                                    min="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Status</label>
                        <select id="inp_status" class="form-control form-control-sm">
                            <option value="AKTIF" selected>AKTIF</option>
                            <option value="HABIS">HABIS</option>
                            <option value="DIPERPANJANG">DIPERPANJANG</option>
                            <option value="DIAKHIRI">DIAKHIRI</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSimpanKontrak">
                        <i class="fas fa-save mr-1"></i>Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
    MODAL: PERPANJANG KONTRAK
    ════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalPerpanjang" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white py-3">
                    <h6 class="modal-title mb-0"><i class="fas fa-redo mr-2"></i>Perpanjang Kontrak</h6>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ext_id">
                    <div class="alert alert-light border mb-3 py-2">
                        <strong id="ext_nama"></strong>
                        <span class="text-muted ml-1">(<span id="ext_npk"></span>)</span>
                        <div class="small text-muted mt-1">Kontrak lama → DIPERPANJANG. Kontrak baru ke-<span
                                id="ext_ke_baru"></span> dibuat otomatis.</div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Durasi Baru</label>
                                <select id="ext_duration" class="form-control form-control-sm">
                                    <option value="" selected disabled>Pilih Durasi</option>
                                    <option value="1">1 bulan</option>
                                    <option value="2">2 bulan</option>
                                    <option value="3">3 bulan</option>
                                    <option value="4">4 bulan</option>
                                    <option value="5">5 bulan</option>
                                    <option value="6">6 bulan</option>
                                    <option value="7">7 bulan</option>
                                    <option value="8">8 bulan</option>
                                    <option value="9">9 bulan</option>
                                    <option value="10">10 bulan</option>
                                    <option value="11">11 bulan</option>
                                    <option value="12">12 bulan</option>
                                    <option value="24">24 bulan</option>
                                    <option value="36">36 bulan</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="small font-weight-bold">Gaji Baru</label>
                                <input type="number" id="ext_salary" class="form-control form-control-sm" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold">Tunjangan Baru</label>
                                <input type="number" id="ext_allowance" class="form-control form-control-sm" min="0">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold">PPH21 Baru</label>
                                <input type="number" id="ext_pph21" class="form-control form-control-sm" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Gaji Harian Baru</label>
                        <input type="number" id="ext_daily_salary" class="form-control form-control-sm" min="0">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success btn-sm" id="btnKonfirmasiPerpanjang">
                        <i class="fas fa-check mr-1"></i>Perpanjang
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
    MODAL: UPDATE FINANSIAL
    ════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalFinansial" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning py-3">
                    <h6 class="modal-title text-white mb-0"><i class="fas fa-money-bill-wave mr-2"></i>Update Finansial
                    </h6>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="uf_id">

                    {{-- Detail Info Kontrak --}}
                    <div class="alert alert-light border mb-3 py-2 px-3">
                        <div class="d-flex align-items-center mb-1">
                            <strong id="uf_nama" class="mr-2"></strong>
                            <span class="badge badge-dark" id="uf_npk"></span>
                        </div>
                        <div class="row text-muted">
                            <div class="col-6">
                                <i class="fas fa-building mr-1"></i>Bagian: <strong id="uf_bagian">—</strong>
                            </div>
                            <div class="col-6">
                                <i class="fas fa-file-contract mr-1"></i>Kontrak Ke: <strong id="uf_ke">—</strong>
                            </div>
                        </div>
                        <div class="row text-muted mt-1">
                            <div class="col-6">
                                <i class="fas fa-calendar-alt mr-1"></i>Periode: <strong id="uf_periode">—</strong>
                            </div>
                            <div class="col-6">
                                <i class="fas fa-clock mr-1"></i>Durasi: <strong id="uf_durasi">—</strong>
                            </div>
                        </div>
                        <div class="row text-muted mt-1">
                            <div class="col-6">
                                <i class="fas fa-info-circle mr-1"></i>Status: <span id="uf_status"></span>
                            </div>
                            <div class="col-6">
                                <i class="fas fa-hourglass-half mr-1"></i>Sisa: <span id="uf_sisa"></span>
                            </div>
                        </div>
                    </div>

                    <hr class="mt-0 mb-3">
                    
                    {{-- Tanggal Mulai & Berakhir --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Tgl Mulai</label>
                                <input type="date" id="uf_start_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Tgl Berakhir</label>
                                <input type="date" id="uf_end_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Gaji Pokok</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" id="uf_salary" class="form-control text-right rupiah-input"
                                        placeholder="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Tunjangan</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" id="uf_allowance" class="form-control text-right rupiah-input"
                                        placeholder="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">PPH21</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" id="uf_pph21" class="form-control text-right rupiah-input"
                                        placeholder="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Gaji Harian</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                    <input type="text" id="uf_daily_salary" class="form-control text-right rupiah-input"
                                        placeholder="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="custom-control custom-checkbox mb-3 mt-2">
                        <input type="checkbox" class="custom-control-input" id="uf_is_split">
                        <label class="custom-control-label font-weight-bold" for="uf_is_split">Lakukan Split
                            Kontrak</label>
                    </div>

                    <div id="split_fields" class="d-none alert alert-secondary p-3 mb-0 mt-3">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Bulan Split</label>
                                    <input type="month" id="uf_split_date" class="form-control form-control-sm">
                                </div>
                            </div>

                        </div>
                        <hr class="mt-0 mb-3 border-secondary">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Gaji Pokok Baru</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input type="text" id="uf_split_salary"
                                            class="form-control text-right rupiah-input" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="small font-weight-bold">Tunjangan Baru</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input type="text" id="uf_split_allowance"
                                            class="form-control text-right rupiah-input" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold">PPH21 Baru</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input type="text" id="uf_split_pph21"
                                            class="form-control text-right rupiah-input" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label class="small font-weight-bold">Gaji Harian Baru</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input type="text" id="uf_split_daily"
                                            class="form-control text-right rupiah-input" placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-warning btn-sm" id="btnSimpanFinansial">
                        <i class="fas fa-save mr-1"></i>Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
    MODAL: IMPORT
    ════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modalImport" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white py-3">
                    <h6 class="modal-title mb-0"><i class="fas fa-file-import mr-2"></i>Import Kontrak</h6>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="small text-muted">Gunakan template untuk format data.</span>
                        <a href="{{ route('employees-contract.template') }}"
                            class="btn btn-outline-success btn-sm font-weight-bold">
                            <i class="fas fa-download mr-1"></i>Download Template
                        </a>
                    </div>
                    <div class="form-group mb-1">
                        <label class="small font-weight-bold">Pilih File (.xlsx / .xls)</label>
                        <input type="file" id="importFile" class="form-control form-control-sm" accept=".xlsx,.xls">
                    </div>
                    <div id="importResult" class="d-none alert mt-2 mb-0 py-2 small"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success btn-sm" id="btnDoImport">
                        <i class="fas fa-upload mr-1"></i>Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast container --}}
    <div style="position:fixed;top:20px;right:20px;z-index:9999;" id="toastWrap"></div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script>
        // ─────────────────────────────────────────────────────────────────────────────
        // Config
        // ─────────────────────────────────────────────────────────────────────────────
        const ROUTES = {
            getData: "{{ route('employees-contract.get-data') }}",
            store: "{{ route('employees-contract.store') }}",
            stop: "{{ url('employees-contract/stop') }}",
            finish: "{{ url('employees-contract/finish') }}",
            extend: "{{ url('employees-contract/extend') }}",
            bagian: "{{ route('employees-contract.bagian') }}",
            importUrl: "{{ route('employees-contract.import') }}",
            exportUrl: "{{ route('employees-contract.export') }}",
            exportAllUrl: "{{ route('employees-contract.export-all') }}",
            salaryUrl: "{{ url('employees-contract/update-salary') }}",
            splitUrl: "{{ url('employees-contract/split') }}",
        };
        const CSRF = "{{ csrf_token() }}";

        // State filter urgensi aktif
        let activeUrgensi = 'urgent';

        // ─────────────────────────────────────────────────────────────────────────────
        // Helpers
        // ─────────────────────────────────────────────────────────────────────────────
        function apiPost(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload),
            }).then(r => r.json());
        }

        function fmtDate(d) {
            if (!d) return '—';
            return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        }

        function fmtRp(val) {
            if (String(val).includes('*')) return val;
            const n = Math.round(parseFloat(val));
            if (isNaN(n) || val === null || val === undefined || val === '') return '—';
            return 'Rp\u00a0' + n.toLocaleString('id-ID');
        }

        /** Build query string dari semua filter aktif */
        function buildUrl() {
            const p = new URLSearchParams({
                month: $('#filterMonth').val() || '',
                status: $('#filterStatus').val() || 'AKTIF',
                bagian: $('#filterBagian').val() || '',
                npk: $('#filterNpk').val().trim(),
            });
            return ROUTES.getData + '?' + p.toString();
        }

        /** Badge sisa hari berwarna sesuai urgensi */
        function sisaBadge(sisa, urgensi) {
            const label = sisa > 0 ? sisa + ' hari'
                : sisa === 0 ? 'Hari ini'
                    : Math.abs(sisa) + ' hari lalu';
            const cls = { urgent: 'danger', soon: 'warning', upcoming: 'primary', normal: 'secondary' }[urgensi] || 'secondary';
            return `<span class="badge badge-${cls}">${label}</span>`;
        }

        /** Info cut-off gaji (tgl 7 & tgl 20) */
        function cutoffHtml(ke7, ke20) {
            const fmt = (v, lbl) => {
                const cls = v > 0 ? 'text-danger' : v === 0 ? 'text-success' : 'text-primary';
                return `<small class="${cls}">${lbl}: ${v >= 0 ? '+' : ''}${v}hr</small>`;
            };
            return fmt(ke7, 'Tgl7') + '<br>' + fmt(ke20, 'Tgl20');
        }

        /** Badge status kontrak */
        function statusBadge(s) {
            const map = { AKTIF: 'success', HABIS: 'secondary', DIPERPANJANG: 'info', DIAKHIRI: 'danger' };
            return `<span class="badge badge-${map[s] || 'light'}">${s}</span>`;
        }

        /** Toast notifikasi */
        function showToast(msg, type = 'success') {
            const id = 'toast_' + Date.now();
            const cls = type === 'success' ? 'bg-success' : 'bg-danger';
            $('#toastWrap').append(
                `<div id="${id}" class="alert ${cls} text-white shadow mb-2 py-2 px-3" style="min-width:260px">${msg}</div>`
            );
            setTimeout(() => $('#' + id).fadeOut(400, function () { $(this).remove(); }), 3500);
        }

        // ─────────────────────────────────────────────────────────────────────────────
        // Summary cards
        // ─────────────────────────────────────────────────────────────────────────────
        function updateSummary() {
            fetch(buildUrl())
                .then(r => r.json())
                .then(({ data }) => {
                    const counts = { urgent: 0, soon: 0, upcoming: 0, all: data.length };
                    data.forEach(r => { if (counts[r.urgensi] !== undefined) counts[r.urgensi]++; });
                    Object.entries(counts).forEach(([k, v]) => $('#cnt-' + k).text(v));
                });
        }

        // ─────────────────────────────────────────────────────────────────────────────
        // Filter urgensi (client-side, tidak reload AJAX)
        // ─────────────────────────────────────────────────────────────────────────────
        function setUrgensi(val) {
            activeUrgensi = val;

            // Aktifkan tombol
            $('[data-urgensi]').each(function () {
                const u = $(this).data('urgensi');
                if ($(this).closest('.btn-group').length) {
                    // tombol di filter bar
                    const outlineMap = { urgent: 'outline-danger', soon: 'outline-warning', upcoming: 'outline-primary', all: 'outline-secondary' };
                    const solidMap = { urgent: 'danger', soon: 'warning', upcoming: 'primary', all: 'secondary' };
                    $(this).toggleClass('active', u === val)
                        .toggleClass(solidMap[u], u === val)
                        .toggleClass(outlineMap[u], u !== val);
                }
            });

            // Filter DataTables client-side
            $.fn.dataTable.ext.search = [];
            if (val !== 'all') {
                $.fn.dataTable.ext.search.push(function (settings, rowData, rowIndex) {
                    const row = table.row(rowIndex).data();
                    return row && row.urgensi === val;
                });
            }
            table.draw();
        }

        // ─────────────────────────────────────────────────────────────────────────────
        // Reload tabel (bersihkan filter urgensi agar summary tidak ambigu)
        // ─────────────────────────────────────────────────────────────────────────────
        function reloadTable() {
            $.fn.dataTable.ext.search = [];
            activeUrgensi = 'all';
            setUrgensi('all');
            table.ajax.url(buildUrl()).load(updateSummary);
        }

        // ─────────────────────────────────────────────────────────────────────────────
        // DataTable
        // ─────────────────────────────────────────────────────────────────────────────
        let table;

        $(document).ready(function () {

            // Isi dropdown bagian
            fetch(ROUTES.bagian)
                .then(r => r.json())
                .then(list => list.forEach(b => $('#filterBagian').append(`<option value="${b}">${b}</option>`)));

            // Inisialisasi DataTable
            table = $('#contractTable').DataTable({
                processing: true,
                ajax: { url: buildUrl(), dataSrc: 'data' },
                order: [[7, 'asc']],
                pageLength: 25,
                columns: [
                    { data: null, className: 'pl-3', render: (d, t, r, m) => m.row + 1, orderable: false },
                    { data: 'npk' },
                    { data: 'nama' },
                    { data: 'bagian' },
                    { data: 'contract_ke', className: 'text-center' },
                    { data: 'start_date', render: fmtDate },
                    { data: 'end_date', render: fmtDate },
                    { data: 'sisa_hari', className: 'text-center', render: (d, t, r) => sisaBadge(r.sisa_hari, r.urgensi) },
                    { data: 'ke_cutoff7', className: 'text-center', render: (d, t, r) => cutoffHtml(r.ke_cutoff7, r.ke_cutoff20), orderable: false },
                    { data: 'month_duration', className: 'text-center', render: d => d ? d + ' bln' : '—' },
                    { data: 'status_contract', className: 'text-center', render: statusBadge },
                    { data: 'salary', className: 'text-right', render: fmtRp },
                    { data: 'allowance', className: 'text-right', render: fmtRp },
                    { data: 'pph21', className: 'text-right', render: fmtRp },
                    {
                        data: null,
                        orderable: false,
                        className: 'text-center',
                        render: (d, t, r) => {
                            const isAktif = r.status_contract === 'AKTIF';
                            if (!isAktif) return '<span class="text-muted small">—</span>';

                            return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-success btn-xs"
                                onclick="openPerpanjang('${r.id}','${r.nama}','${r.npk}',${r.contract_ke},'${r.salary || 0}','${r.allowance || 0}','${r.pph21 || 0}','${r.daily_salary || 0}')"
                                title="Perpanjang">
                                <i class="fas fa-redo"></i>
                            </button>
                            <button class="btn btn-secondary btn-xs"
                                onclick="doFinish('${r.id}','${r.nama}')"
                                title="Selesai">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-danger btn-xs"
                                onclick="doStop('${r.id}','${r.nama}')"
                                title="Akhiri">
                                <i class="fas fa-ban"></i>
                            </button>
                            
                            ${r.can_edit ? `
                            <button class="btn btn-warning btn-xs"
                                onclick='openFinansial(${JSON.stringify(r).replace(/'/g, "\\'")})'
                                title="Update Finansial">
                                <i class="fas fa-money-bill-wave"></i>
                            </button>
                            ` : ''}
                        </div>`;
                        },
                    },
                ],
                initComplete: function () {
                    updateSummary();
                    // Terapkan filter urgensi default setelah data pertama dimuat
                    setUrgensi('urgent');
                },
                language: { url: '' },
            });

            // ── Filters (server-side reload) ──────────────────────────────────────────
            $('#filterMonth, #filterStatus, #filterBagian').on('change', reloadTable);

            let npkTimer;
            $('#filterNpk').on('input', function () {
                clearTimeout(npkTimer);
                npkTimer = setTimeout(reloadTable, 400);
            });

            $('#btnClearNpk').on('click', function () {
                $('#filterNpk').val('');
                reloadTable();
            });

            // ── Tombol urgensi ────────────────────────────────────────────────────────
            $('[data-urgensi]').on('click', function () {
                setUrgensi($(this).data('urgensi'));
            });

            // ── Baca ?npk dari URL (redirect dari halaman biodata) ────────────────────
            const urlP = new URLSearchParams(window.location.search);
            if (urlP.get('npk')) {
                $('#filterNpk').val(urlP.get('npk'));
                if (urlP.get('status')) $('#filterStatus').val(urlP.get('status'));
                reloadTable();
            }

            // ── Auto-hitung tgl berakhir di modal tambah ──────────────────────────────
            $('#inp_start, #inp_duration').on('change', function () {
                const s = $('#inp_start').val();
                const dur = parseInt($('#inp_duration').val()) || 0;
                if (s && dur) {
                    const d = new Date(s);
                    d.setMonth(d.getMonth() + dur);
                    d.setDate(d.getDate() - 1);
                    $('#inp_end').val(d.toISOString().slice(0, 10));
                }
            });

            // ── Simpan kontrak baru ───────────────────────────────────────────────────
            $('#btnSimpanKontrak').on('click', function () {
                const payload = {
                    npk: $('#inp_npk').val().trim(),
                    contract_ke: $('#inp_ke').val(),
                    start_date: $('#inp_start').val(),
                    end_date: $('#inp_end').val(),
                    month_duration: $('#inp_duration').val(),
                    status_contract: $('#inp_status').val(),
                    salary: $('#inp_salary').val() || 0,
                    allowance: $('#inp_allowance').val() || 0,
                };

                if (!payload.npk || !payload.start_date || !payload.end_date) {
                    showToast('NPK, Tgl Mulai, dan Tgl Berakhir wajib diisi.', 'danger');
                    return;
                }

                apiPost(ROUTES.store, payload).then(res => {
                    showToast(res.message, res.success ? 'success' : 'danger');
                    if (res.success) { $('#modalTambah').modal('hide'); reloadTable(); }
                });
            });

            // ── Perpanjang kontrak ────────────────────────────────────────────────────
            $('#btnKonfirmasiPerpanjang').on('click', function () {
                const btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Memproses…');

                apiPost(ROUTES.extend + '/' + $('#ext_id').val(), {
                    month_duration: $('#ext_duration').val(),
                    salary: $('#ext_salary').val() || 0,
                    allowance: $('#ext_allowance').val() || 0,
                    pph21: $('#ext_pph21').val() || 0,
                    daily_salary: $('#ext_daily_salary').val() || 0,
                }).then(res => {
                    btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Perpanjang');
                    showToast(res.message, res.success ? 'success' : 'danger');
                    if (res.success) { $('#modalPerpanjang').modal('hide'); reloadTable(); }
                });
            });

            // ── Tampilkan/Sembunyikan Field Split ─────────────────────────────────────
            $('#uf_is_split').on('change', function () {
                if ($(this).is(':checked')) {
                    $('#split_fields').removeClass('d-none');
                } else {
                    $('#split_fields').addClass('d-none');
                }
            });

            // ── Update finansial atau Split ───────────────────────────────────────────
            $('#btnSimpanFinansial').on('click', function () {
                const isSplit = $('#uf_is_split').is(':checked');
                const btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan…');

                let url = ROUTES.salaryUrl + '/' + $('#uf_id').val();
                let payload = {
                    salary: parseRupiah($('#uf_salary').val()),
                    allowance: parseRupiah($('#uf_allowance').val()),
                    pph21: parseRupiah($('#uf_pph21').val()),
                    daily_salary: parseRupiah($('#uf_daily_salary').val()),
                };
                
                // Sertakan tanggal hanya jika diisi
                const ufStart = $('#uf_start_date').val();
                const ufEnd   = $('#uf_end_date').val();
                if (ufStart) payload.start_date = ufStart;
                if (ufEnd)   payload.end_date   = ufEnd;

                if (isSplit) {
                    url = ROUTES.splitUrl + '/' + $('#uf_id').val();
                    payload.split_date = $('#uf_split_date').val();
                    payload.salary = parseRupiah($('#uf_split_salary').val());
                    payload.allowance = parseRupiah($('#uf_split_allowance').val());
                    payload.pph21 = parseRupiah($('#uf_split_pph21').val());
                    payload.daily_salary = parseRupiah($('#uf_split_daily').val());

                    if (!payload.split_date) {
                        btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan');
                        showToast('Tanggal split wajib diisi!', 'danger');
                        return;
                    }
                }

                apiPost(url, payload).then(res => {
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan');
                    showToast(res.message, res.success ? 'success' : 'danger');
                    if (res.success) { $('#modalFinansial').modal('hide'); reloadTable(); }
                }).catch(() => {
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Simpan');
                    showToast('Terjadi kesalahan jaringan.', 'danger');
                });
            });

            // ── Import ────────────────────────────────────────────────────────────────
            $('#btnDoImport').on('click', function () {
                const file = $('#importFile')[0].files[0];
                if (!file) { showToast('Pilih file terlebih dahulu.', 'danger'); return; }

                const fd = new FormData();
                fd.append('file', file);
                fd.append('_token', CSRF);

                const btn = $(this).prop('disabled', true).text('Mengupload…');

                fetch(ROUTES.importUrl, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        btn.prop('disabled', false).html('<i class="fas fa-upload mr-1"></i>Upload');
                        const cls = res.success ? 'alert-success' : 'alert-danger';
                        $('#importResult').removeClass('d-none alert-success alert-danger').addClass(cls).text(res.message);
                        if (res.success) reloadTable();
                    });
            });

            // ── Export ────────────────────────────────────────────────────────────────
            $('#btnExport').on('click', function () {
                const [y, m] = ($('#filterMonth').val() || `${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}`).split('-');
                window.location.href = ROUTES.exportUrl + `?month=${m}&year=${y}`;
            });

            // ── Export All ────────────────────────────────────────────────────────────
            $('#btnExportAll').on('click', function () {
                window.location.href = ROUTES.exportAllUrl;
            });
        });

        // ─────────────────────────────────────────────────────────────────────────────
        // Action handlers (dipanggil dari render kolom)
        // ─────────────────────────────────────────────────────────────────────────────

        function openPerpanjang(id, nama, npk, contractKe, salary, allowance, pph21, daily_salary) {
            $('#ext_id').val(id);
            $('#ext_nama').text(nama);
            $('#ext_npk').text(npk);
            $('#ext_ke_baru').text(contractKe + 1);
            $('#ext_salary').val(String(salary).includes('*') ? '' : salary);
            $('#ext_allowance').val(String(allowance).includes('*') ? '' : allowance);
            $('#ext_pph21').val(String(pph21).includes('*') ? '' : pph21);
            $('#ext_daily_salary').val(String(daily_salary).includes('*') ? '' : daily_salary);
            $('#modalPerpanjang').modal('show');
        }

        function doFinish(id, nama) {
            Swal.fire({
                title: 'Selesaikan Kontrak?',
                html: `Kontrak <strong>${nama}</strong> akan ditandai sebagai <span class="badge badge-secondary">HABIS</span> (berakhir normal).`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check mr-1"></i> Ya, Selesaikan',
                cancelButtonText: 'Batal',
                reverseButtons: true,
            }).then(result => {
                if (!result.isConfirmed) return;
                apiPost(ROUTES.finish + '/' + id, {}).then(res => {
                    showToast(res.message, res.success ? 'success' : 'danger');
                    if (res.success) reloadTable();
                });
            });
        }

        function doStop(id, nama) {
            Swal.fire({
                title: 'Akhiri Kontrak?',
                html: `Kontrak <strong>${nama}</strong> akan <span class="text-danger font-weight-bold">DIAKHIRI</span> lebih awal (resign / pemutusan).`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                confirmButtonText: '<i class="fas fa-ban mr-1"></i> Ya, Akhiri',
                cancelButtonText: 'Batal',
                reverseButtons: true,
            }).then(result => {
                if (!result.isConfirmed) return;
                apiPost(ROUTES.stop + '/' + id, {}).then(res => {
                    showToast(res.message, res.success ? 'success' : 'danger');
                    if (res.success) reloadTable();
                });
            });
        }

        /** Format angka ke Rupiah-friendly string (1.500.000) */
        function toRupiah(val) {
            const n = parseInt(val) || 0;
            return n.toLocaleString('id-ID');
        }

        /** Parse Rupiah string kembali ke angka */
        function parseRupiah(str) {
            if (!str) return 0;
            return parseInt(String(str).replace(/\./g, '').replace(/[^0-9]/g, '')) || 0;
        }

        function openFinansial(row) {
            $('#uf_id').val(row.id);
            // Detail info
            $('#uf_nama').text(row.nama);
            $('#uf_npk').text(row.npk);
            $('#uf_bagian').text(row.bagian || '—');
            $('#uf_ke').text(row.contract_ke || '—');
            $('#uf_periode').text(fmtDate(row.start_date) + ' — ' + fmtDate(row.end_date));
            $('#uf_durasi').text(row.month_duration ? row.month_duration + ' bulan' : '—');
            $('#uf_status').html(statusBadge(row.status_contract));
            $('#uf_sisa').html(sisaBadge(row.sisa_hari, row.urgensi));
            // Date inputs
            $('#uf_start_date').val(row.start_date ? row.start_date.slice(0, 10) : '');
            $('#uf_end_date').val(row.end_date ? row.end_date.slice(0, 10) : '');
            // Financial inputs (formatted)
            $('#uf_salary').val(toRupiah(row.salary || 0));
            $('#uf_allowance').val(toRupiah(row.allowance || 0));
            $('#uf_pph21').val(toRupiah(row.pph21 || 0));
            $('#uf_daily_salary').val(toRupiah(row.daily_salary || 0));

            // Reset state split
            $('#uf_is_split').prop('checked', false);
            $('#split_fields').addClass('d-none');
            $('#uf_split_date').val('');
            $('#uf_split_salary').val(toRupiah(row.salary || 0));
            $('#uf_split_allowance').val(toRupiah(row.allowance || 0));
            $('#uf_split_pph21').val(toRupiah(row.pph21 || 0));
            $('#uf_split_daily').val(toRupiah(row.daily_salary || 0));

            $('#modalFinansial').modal('show');
        }

        // Auto-format Rupiah on typing
        $(document).on('input', '.rupiah-input', function () {
            const raw = parseRupiah($(this).val());
            const pos = this.selectionStart;
            const oldLen = $(this).val().length;
            $(this).val(raw > 0 ? toRupiah(raw) : '');
            const newLen = $(this).val().length;
            this.setSelectionRange(pos + (newLen - oldLen), pos + (newLen - oldLen));
        });
    </script>
</body>

</html>