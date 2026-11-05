<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    div.dataTables_wrapper div.dataTables_filter input {
        border-radius: 20px;
        padding: 5px 15px;
        border: 1px solid #d1d3e2;
    }
    .table-rekap th {
        background-color: #4e73df;
        color: white;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        border: none;
    }
    #miniMapDetail {
        /* PERBAIKAN (2026-11-03): tinggi 320px -> 260px supaya modal
           lebih compact. Peta kini berdampingan dengan foto wajah,
           jadi tingginya TIDAK perlu setinggi sebelumnya.
           min-height tetap diset sama supaya Leaflet tidak kolaps
           0 px saat modal baru dibuka. */
        height: 260px;
        min-height: 260px;
        width: 100%;
        /* PERBAIKAN (2026-11-03): min-height + z-index lebih rendah.
           z-index 500 lebih tinggi dari elemen modal Bootstrap lain
           (modal-content memakai 1050, tapi tile Leaflet ikut memakai
           z-index internal 200-800) sehingga peta bisa MENIMPA header,
           tombol Tutup, dan teks di sekitarnya -> tampilan berantakan. */
        border-radius: 8px;
        z-index: 1;
        background: #eef1f7;
        overflow: hidden;
    }
    #miniMapDetail .leaflet-container {
        border-radius: 8px;
        font-family: inherit;
    }
    /* PERBAIKAN V2 (2026-11-05): kotak foto wajah tidak lagi tinggi FIXED
       260px. Grid multi-foto (clock in + clock out) butuh ruang vertikal
       lebih, jadi tinggi dibuat auto dengan batas maksimal + scroll internal.
       Peta tetap 260px sehingga kedua kolom tidak jauh berbeda tinggi. */
    .face-detail-box {
        min-height: 260px;
        max-height: 340px;
        width: 100%;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        background: #f8f9fc;
        overflow-y: auto;
        overflow-x: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        padding: 8px;
    }
    .face-detail-box img {
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 6px;
        border: 2px solid #e3e6f0;
        box-shadow: 0 2px 8px rgba(0,0,0,.10);
    }
    /* Foto utama: mengisi tinggi kotak tanpa melebihi batas. */
    .face-detail-box .face-detail-main {
        max-height: 200px;
        max-width: 100%;
        object-fit: contain;
    }
    /* Deretan thumbnail untuk scan tambahan. */
    .face-detail-strip {
        display: flex;
        flex-wrap: nowrap;
        gap: 4px;
        justify-content: center;
        overflow-x: auto;
        max-width: 100%;
    }
    .face-detail-box .face-detail-thumb {
        width: 44px;
        height: 44px;
        object-fit: cover;
        border: 1px solid #e3e6f0;
        border-radius: 4px;
        box-shadow: none;
        cursor: pointer;
        flex: 0 0 auto;
    }
    /* PERBAIKAN V2 (2026-11-05): grid multi-foto wajah. */
    .face-detail-box .face-grid {
        display: grid;
        gap: 10px;
        width: 100%;
        align-items: start;
    }
    .face-detail-box .face-grid-item {
        margin: 0;
        text-align: center;
    }
    .face-detail-box .face-grid-item img {
        width: 100%;
        object-fit: contain;
        background: #fff;
        border: 2px solid #e3e6f0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,.10);
    }
    .face-detail-box .face-grid-item figcaption {
        font-size: 11px;
        line-height: 1.35;
        margin-top: 4px;
    }
    /* Di layar kecil, peta & wajah menumpuk kembali dan tingginya
       dikurangi supaya modal tidak terlalu panjang. */
    @media (max-width: 767.98px) {
        #miniMapDetail {
            height: 220px;
            min-height: 220px;
        }
        .face-detail-box {
            min-height: 220px;
            max-height: 300px;
        }
    }
    .face-capture-preview {
        max-width: 100%;
        border-radius: 8px;
        border: 3px solid #e3e6f0;
        box-shadow: 0 4px 14px rgba(0,0,0,.12);
    }
    .modal-xl .modal-body {
        /* Modal dipadatkan: padding vertikal dikurangi supaya tidak
           banyak ruang kosong di atas/bawah isi. */
        max-height: 82vh;
        overflow-y: auto;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }
    #modalFaceDetail .modal-header,
    #modalFaceDetail .modal-footer {
        padding-top: 0.6rem;
        padding-bottom: 0.6rem;
    }
    #modalFaceDetail .card-body {
        padding-top: 0.65rem;
        padding-bottom: 0.65rem;
    }
    /* PERBAIKAN (2026-11-03): CSS baris detail expand (.emp-row-detail,
       .table-toggle-icon) DIHAPUS. Penyebab tabel "Log Presensi Wajah"
       berantakan: aturan `.emp-row-detail table th` memakai !important
       pada background/color dan tabel bersarang berada di dalam <td>
       sempit (lebar kolom "#" = 4%), sehingga setiap <th> tertekan dan
       seluruh header (#, Tanggal & Hari, Jam Masuk, ...) menumpuk di
       kolom pertama. Rincian harian kini dirender di dalam modal. */
    .stat-card {
        border-left: 4px solid;
    }
    .stat-card.border-primary { border-left-color: #4e73df !important; }
    .stat-card.border-success { border-left-color: #1cc88a !important; }
    .stat-card.border-info { border-left-color: #36b9cc !important; }
    .stat-card.border-warning { border-left-color: #f6c23e !important; }
</style>

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    @if(auth()->check())
        @include('layout.sidebar')
    @endif

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @if(auth()->check())
                @include('layout.navbar')
            @endif

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-users-cog text-primary mr-2"></i> Rekap Presensi Semua Karyawan
                    </h1>
                    <div class="mt-2 mt-sm-0">
                        {{-- PERBAIKAN 19 (2026-11-04): tombol export diubah dari
                             CSV menjadi EXCEL (.xlsx berstyling) dan WAJIB ikut
                             filter aktif. Sebelumnya hanya `month` yang dikirim,
                             sehingga file berisi semua karyawan walau layar
                             sedang difilter. href diisi JS di bawah. --}}
                        <a href="{{ route('face.attendance.admin.export', ['month' => $month]) }}"
                           id="btnExportExcel"
                           data-base="{{ route('face.attendance.admin.export') }}"
                           class="btn btn-sm btn-success shadow-sm rounded-pill px-3">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </a>
                        <a href="{{ route('face.attendance.settings.index') }}"
                           class="btn btn-sm btn-outline-primary shadow-sm rounded-pill px-3">
                            <i class="fas fa-cog mr-1"></i> Pengaturan
                        </a>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card shadow mb-4 border-left-primary">
                    <div class="card-body">
                        <form method="GET" action="{{ route('face.attendance.admin.index') }}" class="row align-items-end">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterMonth">Pilih Bulan:</label>
                                <input type="month" name="month" id="filterMonth" value="{{ $month }}" 
                                       class="form-control form-control-sm rounded-pill" 
                                       onchange="this.form.submit()" 
                                       style="height: 31px;">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterDepartment">Departemen:</label>
                                <select name="department_id" id="filterDepartment" class="form-control form-control-sm rounded-pill" style="height: 31px;">
                                    <option value="">Semua Departemen</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->ID_DEPT }}" {{ (string)$department === (string)$d->ID_DEPT ? 'selected' : '' }}>
                                            {{ $d->DEPARTEMENT }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- PERBAIKAN 17 (2026-11-04): tombol Filter DIHAPUS.
                                 Ketiga kontrol (bulan, departemen, karyawan) sudah
                                 memicu loadData() otomatis lewat bindFilterAutoReload().
                                 Tombol hanya menambah satu kolom kosong di kanan,
                                 jadi ruangnya dialihkan ke kolom pencarian agar
                                 input lebih lega dan baris filter seimbang
                                 (3 + 3 + 6 = 12 kolom). --}}
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterSearch">Cari (Nama / NPK / Barcode):</label>
                                {{-- PERBAIKAN (2026-11-03): input teks biasa -> Select2.
                                     Data diambil dari API /face-attendance/admin/employees
                                     (union BIODATA + BIODATA_KELUAR) dengan
                                     pencarian di server, jadi tidak memuat ribuan
                                     karyawan ke browser.
                                     Value awal memakai NPK yang sedang difilter;
                                     labelnya dibuat server agar tampilan
                                     "-- NPK (nama)" --}}
                                <select name="q" id="filterSearch" class="form-control form-control-sm rounded-pill" style="height: 31px; width: 100%;">
                                    @if($search !== '')
                                        <option value="{{ $search }}" selected>
                                            {{ $searchSelectedLabel ?? $search }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                        </form>
                        {{-- Banner ini HANYA tampil ketika data belum siap. Pada kunjungan
                             pertama (belum ada @include data) banner muncul lalu hilang
                             setelah AJAX selesai. Kalau data sudah dikirim server,
                             banner tidak perlu tampil sama sekali. --}}
                        <div id="rekapLoadingBanner" class="alert alert-info border-left-info shadow-sm mt-3 mb-0 {{ !empty($loaded) ? 'd-none' : '' }}" role="status" aria-live="polite">
                            <div class="d-flex align-items-center">
                                <span class="spinner-border spinner-border-sm text-info mr-2" role="status" aria-hidden="true"></span>
                                <span>
                                    <strong>Memuat data presensi...</strong>
                                    <span class="d-block small text-gray-700">
                                        Data sedang diambil. Mohon tunggu sebentar.
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div id="rekapErrorBanner" class="alert alert-danger border-left-danger shadow-sm mt-3 mb-0 d-none" role="alert"></div>
                    </div>
                </div>

                {{-- Kontainer data rekap: DIISI VIA AJAX (lazy load).
                     JANGAN @include di sini. Dulu partial ini di-include sehingga
                     server tetap menjalankan seluruh query rekap sebelum halaman
                     dikirim -> halaman pertama berat dan banner loading hanya
                     hiasan (data sudah selesai sebelum banner sempat terlihat).
                     Sekarang halaman dikirim ringan, data menyusul lewat fetch. --}}
                <div id="rekapDataContainer" aria-live="polite" aria-busy="true">
                    <div class="text-center text-muted py-5" id="rekapPlaceholder">
                        <span class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></span>
                        <div class="small">Menyiapkan data presensi...</div>
                    </div>
                </div>{{-- /#rekapDataContainer --}}
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Presensi Wajah + Lokasi -->
<div class="modal fade" id="modalFaceDetail" tabindex="-1" role="dialog" aria-labelledby="modalFaceDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="modalFaceDetailLabel">
                    <i class="fas fa-map-marker-alt mr-2"></i> Detail Presensi
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{-- Ringkasan presensi: kartu rapi di atas, mudah dibaca --}}
                {{-- PERBAIKAN (2026-11-03): ditambah NPK + Departemen karena
                     modal ini sebelumnya menampilkan "KARYAWAN: -" (nama
                     kosong). Field baru diambil dari API /face-attendance/
                     admin/detail/{npk}/{tanggal} -> employee.{name,npk,dept}. --}}
                <div class="card border-left-primary shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row text-center text-md-left">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Karyawan</div>
                                <div class="h6 mb-1 font-weight-bold text-gray-800" id="detailEmployeeName">-</div>
                                <div class="small text-gray-500">
                                    NPK: <span id="detailEmployeeNpk" class="font-weight-bold">-</span>
                                    <span class="mx-1 text-gray-300">|</span>
                                    <span id="detailEmployeeDept">-</span>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tanggal</div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800" id="detailDateLabel">-</div>
                            </div>
                            <div class="col-md-5">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Lokasi Kerja</div>
                                <div class="h6 mb-1 font-weight-bold text-gray-800" id="detailLocationName">-</div>
                                <div class="small text-gray-500" id="detailLocationSub">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PERBAIKAN (2026-11-03): tata letak modal dibuat COMPACT.
                     Sebelumnya: kolom kiri sempit (kolom 5/12) menumpuk
                     peta DI ATAS foto wajah, kolom kanan tabel riwayat.
                     Hasilnya peta dan wajah kecil-kecil, foto wajah
                     memanjang ke bawah, dan menyisakan banyak ruang
                     kosong di kanan-bawah.

                     Sekarang:
                       Baris 1 -> Minimap (kiri)  |  Wajah (kanan)
                       Baris 2 -> Riwayat scan (lebar penuh)

                     Peta dan wajah jadi BERDAMPINGAN, tinggi keduanya
                     disamakan (260px) sehingga tidak ada ruang kosong
                     menganggur, dan tabel riwayat mendapat lebar penuh
                     sehingga kolomnya tidak berdesakan. --}}
                <div class="row">
                    <!-- Kiri: Minimap -->
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h6 class="font-weight-bold text-gray-800 mb-2">
                            <i class="fas fa-map text-primary mr-1"></i> Minimap Lokasi Absensi
                        </h6>
                        <div id="miniMapDetail"></div>
                        {{-- Kolom koordinat di bawah peta dihapus atas
                             permintaan user. Elemen #detailCoords tetap
                             ada (disembunyikan) supaya script pengisi
                             nilainya tidak error. --}}
                        <span id="detailCoords" class="d-none" aria-hidden="true">-</span>
                    </div>

                    <!-- Kanan: Wajah (berdampingan dengan peta) -->
                    <div class="col-md-6">
                        <h6 class="font-weight-bold text-gray-800 mb-2">
                            <i class="fas fa-user-check text-primary mr-1"></i> Wajah Tercapture
                        </h6>
                        <div id="faceImageContainer" class="face-detail-box text-center">
                            <div id="faceImageEmpty" class="text-muted small py-4">
                                <i class="fas fa-image fa-2x mb-2 d-block text-secondary"></i>
                                Tidak ada gambar wajah tersimpan.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PERBAIKAN (2026-11-03): rincian harian dipindah ke sini.
                     Sebelumnya tabel ini adalah baris expand di dalam tabel utama
                     dan tampil berantakan (semua header menumpuk di kolom pertama).
                     Sekarang tampil rapi di dalam modal. -->
                <div class="row mt-1" id="wrapRekapHarian">
                    <div class="col-12">
                        <h6 class="font-weight-bold text-gray-800 mb-2">
                            <i class="fas fa-calendar-alt text-primary mr-1"></i> Rekap Harian
                            <span class="badge badge-primary ml-1" id="rekapHarianCount">0 hari</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" id="tableRekapHarian" style="background:#fff;">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%" class="text-center">#</th>
                                        <th width="20%">Tanggal &amp; Hari</th>
                                        <th width="16%" class="text-center">Jam Masuk</th>
                                        <th width="16%" class="text-center">Jam Pulang</th>
                                        <th width="14%" class="text-center">Mode</th>
                                        <th width="16%" class="text-center">Status</th>
                                        {{-- PERBAIKAN 20 (2026-11-04): kolom aksi baru untuk
                                             membuka minimap + foto capture per hari. --}}
                                        <th width="13%" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="rekapHarianBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">
                                            Belum ada data harian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Riwayat scan: lebar penuh di bawah peta + wajah -->
                <div class="row mt-1">
                    <div class="col-12">
                        <h6 class="font-weight-bold text-gray-800 mb-2">
                            <i class="fas fa-list-ul text-primary mr-1"></i> Riwayat Scan Hari Ini
                        </h6>
                        <div id="faceDetailList" class="small"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- ====================================================================
     PERBAIKAN 20 (2026-11-04): Modal "Peta & Foto Harian".
     --------------------------------------------------------------------
     Dibuka dari tombol aksi pada setiap baris tabel REKAP HARIAN di
     dalam modal Detail Presensi. Isinya:
       - minimap lokasi absensi hari itu (Leaflet + polygon area kerja)
       - foto wajah hasil capture pada hari itu (bisa >1 scan: In/Out)
     Peta SENGAJA tidak dibuat saat halaman dimuat, tetapi saat modal
     dibuka, karena Leaflet salah menghitung ukuran container yang
     masih tersembunyi (peta jadi abu-abu/terpotong).
     ==================================================================== --}}
<div class="modal fade" id="modalPetaFotoHarian" tabindex="-1" role="dialog"
     aria-labelledby="modalPetaFotoHarianLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;overflow:hidden;">

            <div class="modal-header text-white border-0 py-3"
                 style="background:linear-gradient(135deg,#4e73df,#224abe);">
                <h6 class="modal-title mb-0 font-weight-bold" id="modalPetaFotoHarianLabel">
                    <i class="fas fa-map-marked-alt mr-2"></i> Peta &amp; Foto Absensi
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-3">
                {{-- Identitas singkat: siapa & tanggal berapa --}}
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 px-2">
                    <div>
                        <div class="font-weight-bold text-gray-900" id="pfKaryawan">-</div>
                        <small class="text-muted" id="pfTanggal">-</small>
                    </div>
                    <div class="text-right">
                        <small class="text-muted d-block">Jumlah scan wajah</small>
                        <span class="badge badge-primary px-2 py-1" id="pfJumlah">0</span>
                    </div>
                </div>

                <div class="row">
                    {{-- KIRI: minimap --}}
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="card border-0 shadow-sm h-100 mb-0">
                            <div class="card-header bg-white py-2 border-bottom">
                                <small class="font-weight-bold text-gray-900">
                                    <i class="fas fa-map-pin text-danger mr-1"></i> Minimap Lokasi Absensi
                                </small>
                            </div>
                            <div class="card-body p-2">
                                <div id="petaFotoHarian" style="height:260px;width:100%;border-radius:8px;background:#eef2f7;"></div>
                                <div class="mt-2 small text-muted" id="pfLokasiInfo"></div>
                            </div>
                        </div>
                    </div>

                    {{-- KANAN: foto capture --}}
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 mb-0">
                            <div class="card-header bg-white py-2 border-bottom">
                                <small class="font-weight-bold text-gray-900">
                                    <i class="fas fa-camera text-primary mr-1"></i> Foto Tercapture
                                </small>
                            </div>
                            <div class="card-body p-2">
                                <div id="pfFotoWrap"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (wajib sebelum DataTables) -->
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    // PERBAIKAN (2026-11-03): DataTable TIDAK lagi diinisialisasi di
    // $(document).ready(). Sebelumnya ada dua inisialisasi:
    //   1. $(document).ready -> $('#tableRekapAdmin').DataTable({...})
    //   2. initRekapTable()  -> dipanggil setelah AJAX memuat fragment
    // Keduanya berlomba: yang pertama membungkus tabel lama, yang kedua
    // mengganti isinya. Akibatnya DataTable tetap ter-attach ke tabel
    // LAMA yang sudah dibuang -> "tampilan rusak", baris tidak muncul,
    // pagination/search tidak jalan.
    //
    // Sekarang: hanya initRekapTable() yang boleh membuat DataTable,
    // dan SELALU destroys instance lama dulu kalau ada.
    //
    // PERBAIKAN (2026-11-03 #2): initRekapTable() DIDEFINISIKAN di blok
    // <script> KEDUA (setelah blok ini). Function declaration tidak
    // menyeberang batas blok <script>, jadi memanggilnya dari sini
    // memunculkan:
    //     Uncaught ReferenceError: initRekapTable is not defined
    // Pemanggilan awal dipindah ke blok kedua, tepat setelah fungsinya
    // dideklarasikan. Lihat "Inisialisasi awal setelah fungsi didefinisikan".

    // PERBAIKAN (2026-11-03): handler toggle baris detail (.btn-toggle-emp)
    // DIHAPUS karena baris expand sudah tidak ada — rincian harian kini
    // tampil di dalam modal. Handler lama hanya menyisakan dead code.

    // ===== Detail Lokasi & Wajah Presensi =====
    (function() {
        let detailMap = null;
        let detailMarker = null;
        let detailCircle = null;
        let detailPolyGroup = null;

        // Polygon lokasi kerja aktif (dari server) untuk digambar di minimap
        const workLocations = @json($workLocationsForMap ?? [])

        function destroyMap() {
            if (detailMap) {
                detailMap.remove();
                detailMap = null;
            }
            detailMarker = null;
            detailCircle = null;
            detailPolyGroup = null;
        }

        function renderFaceImages(details) {
            const container = document.getElementById('faceImageContainer');
            const listContainer = document.getElementById('faceDetailList');
            container.innerHTML = '';
            listContainer.innerHTML = '';

            const withImage = details.filter(function(d) { return d.face_image; });

            if (withImage.length === 0) {
                container.innerHTML = '<div class="text-muted small py-4">' +
                    '<i class="fas fa-image fa-2x mb-2 d-block text-secondary"></i>' +
                    'Tidak ada gambar wajah tersimpan.</div>';
            } else {
                // PERBAIKAN V2 (2026-11-05): SEMUA foto wajah (clock in + clock out)
                // ditampilkan dengan ukuran yang sama dalam grid.
                // Sebelumnya hanya foto PERTAMA yang besar dan sisanya jadi
                // thumbnail 44px di bawahnya, sehingga penggunaan face
                // attendance kedua (clock out) nyaris tidak terlihat.
                //
                // GRID: 1 foto -> 1 kolom besar; 2 foto -> 2 kolom; 3+ -> 3 kolom.
                const count = withImage.length;
                const cols = count === 1 ? 1 : (count === 2 ? 2 : 3);
                const maxH = count === 1 ? 190 : 130;

                const grid = document.createElement('div');
                grid.className = 'face-grid';
                grid.style.display = 'grid';
                grid.style.gridTemplateColumns = 'repeat(' + cols + ', minmax(0, 1fr))';
                grid.style.gap = '10px';
                grid.style.width = '100%';
                grid.style.alignItems = 'start';

                withImage.forEach(function (d, idx) {
                    const s = d.face_image.indexOf('data:') === 0
                        ? d.face_image
                        : 'data:image/jpeg;base64,' + d.face_image;

                    const cell = document.createElement('figure');
                    cell.className = 'face-grid-item mb-0';
                    cell.style.margin = '0';
                    cell.style.textAlign = 'center';
                    cell.innerHTML =
                        '<img src="' + s + '" alt="Wajah ' + (idx + 1) + '" ' +
                        'style="width:100%;max-height:' + maxH + 'px;object-fit:contain;background:#fff;' +
                        'border:2px solid #e3e6f0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.10);">' +
                        '<figcaption class="small text-muted mt-1" style="font-size:11px;line-height:1.35;">' +
                        '<i class="fas fa-clock mr-1"></i>' +
                        (d.action_type || 'Absensi') + '<br>' +
                        '<span class="font-weight-bold text-dark">' + (d.scan_time || '-') + '</span>' +
                        '</figcaption>';
                    grid.appendChild(cell);
                });

                container.appendChild(grid);
            }

            // Daftar seluruh scan (wajah + fingerprint) -> tabel rapi.
            // PERBAIKAN (2026-11-03): baris koordinat mentah (lat, lng)
            // TIDAK ditampilkan. Peta di atas sudah menunjukkan posisi.
            // Akurasi GPS tetap tampil sebagai indikator kualitas sinyal.
            if (details.length === 0) {
                listContainer.innerHTML =
                    '<div class="text-muted small text-center py-3">' +
                    '<i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>' +
                    'Belum ada riwayat scan pada hari ini.</div>';
                return;
            }

            var rowsHtml = '';
            details.forEach(function(d, i) {
                var isFace = d.mode_label === 'Wajah';

                // PERBAIKAN (2026-11-03): kolom Lokasi tidak lagi menampilkan
                // koordinat mentah (mis. "-7.629019, 110.827461"). Dipakai
                // nama area kerja: location_resolved (dari server) atau
                // location_name, lalu fallback ke polygon yang memuat titik.
                var rawLoc = d.location_name || '';
                var isCoordText = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(rawLoc.trim());
                var locResolved = d.location_resolved || '';
                var locByCoords = '';
                if (!locResolved && d.latitude !== null && d.latitude !== undefined) {
                    var ml = findLocationByCoords(workLocations, parseFloat(d.latitude), parseFloat(d.longitude));
                    if (ml) locByCoords = ml.name || '';
                }
                var loc = locResolved
                    || (rawLoc && !isCoordText ? rawLoc : '')
                    || locByCoords
                    || '-';

                var acc = (d.accuracy !== null && d.accuracy !== undefined && d.accuracy !== '')
                    ? Math.round(parseFloat(d.accuracy)) + ' m'
                    : '-';
                // PERBAIKAN (2026-11-03): modul ini KHUSUS presensi wajah.
                // Sebelumnya cabang "else" memberi label "Fingerprint",
                // sehingga data wajah yang tidak punya penanda mode
                // tampil seolah-olah sidik jari. Kini cabang else hanya
                // dipakai bila record benar-benar bukan wajah.
                var modeBadge = isFace
                    ? '<span class="badge badge-primary"><i class="fas fa-user-check mr-1"></i>Wajah</span>'
                    : '<span class="badge badge-secondary"><i class="fas fa-question-circle mr-1"></i>Lainnya</span>';
                var accBadge = (acc === '-')
                    ? '<span class="text-muted">-</span>'
                    : '<span class="badge badge-light border text-dark">' + acc + '</span>';

                // PERBAIKAN V2 (2026-11-05): kolom Koordinat Attendance.
                // Menampilkan lat, lng mentah masing-masing scan (khususnya
                // face attendance) supaya bisa dibandingkan antar-scan.
                var coordText = '-';
                if (d.latitude !== null && d.latitude !== undefined && d.latitude !== '' &&
                    d.longitude !== null && d.longitude !== undefined && d.longitude !== '') {
                    var clat = parseFloat(d.latitude);
                    var clng = parseFloat(d.longitude);
                    if (!isNaN(clat) && !isNaN(clng)) {
                        coordText = '<span class="font-monospace small" style="font-size:11px;">' +
                            clat.toFixed(6) + ', ' + clng.toFixed(6) + '</span>';
                    }
                }

                rowsHtml +=
                    '<tr>' +
                    '<td class="text-center text-muted align-middle">' + (i + 1) + '</td>' +
                    '<td class="align-middle"><i class="far fa-clock text-muted mr-1"></i>' +
                        '<span class="font-weight-bold">' + (d.scan_time || '-') + '</span></td>' +
                    '<td class="align-middle">' + modeBadge + '</td>' +
                    '<td class="align-middle">' + (d.action_type || '-') + '</td>' +
                    '<td class="align-middle">' + loc + '</td>' +
                    '<td class="align-middle">' + coordText + '</td>' +
                    '<td class="text-center align-middle">' + accBadge + '</td>' +
                    '</tr>';
            });

            listContainer.innerHTML =
                '<div class="table-responsive">' +
                '<table class="table table-sm table-hover table-bordered mb-0 align-middle">' +
                '<thead class="thead-light">' +
                '<tr>' +
                '<th class="text-center" style="width:40px;">#</th>' +
                '<th style="width:90px;">Jam</th>' +
                '<th style="width:110px;">Metode</th>' +
                '<th>Keterangan</th>' +
                '<th>Lokasi</th>' +
                '<th>Koordinat</th>' +
                '<th class="text-center" style="width:85px;">Akurasi</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>' + rowsHtml + '</tbody>' +
                '</table>' +
                '</div>';
        }

        function initMap(lat, lng, accuracy, locations, allScans) {
            destroyMap();

            const el = document.getElementById('miniMapDetail');
            detailMap = L.map(el, {
                zoomControl: true,
                attributionControl: false
            }).setView([lat, lng], 17);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(detailMap);

            // PERBAIKAN V2 (2026-11-05): polygon lokasi kerja digambar untuk
            // SEMUA lokasi (aktif & non-aktif), dengan warna berbeda.
            // Polygon yang memuat titik absensi diberi warna hijau.
            const matchedLoc = findLocationByCoords(locations, lat, lng);
            if (locations && locations.length > 0) {
                detailPolyGroup = L.layerGroup().addTo(detailMap);

                locations.forEach(function(loc) {
                    if (!loc.coordinates || loc.coordinates.length < 3) return;
                    const latLngs = loc.coordinates.map(function(c) {
                        return [c[1], c[0]]; // GeoJSON = [lng, lat]
                    });

                    const isActive = loc.is_active !== false; // default dianggap aktif
                    const isMatched = matchedLoc && matchedLoc.name === loc.name;
                    const color = isMatched ? '#1cc88a' : (isActive ? '#4e73df' : '#858796');
                    const fillOpacity = isMatched ? 0.25 : (isActive ? 0.15 : 0.08);

                    const layer = L.polygon(latLngs, {
                        color: color,
                        weight: isMatched ? 3 : 2,
                        fillColor: color,
                        fillOpacity: fillOpacity
                    });

                    const statusTxt = isActive ? 'Aktif' : 'Non-aktif';
                    layer.bindPopup(
                        '<strong>' + (loc.name || 'Lokasi Kerja') + '</strong><br>' +
                        (loc.description ? loc.description + '<br>' : '') +
                        'Status: ' + statusTxt
                    );
                    layer.addTo(detailPolyGroup);
                });
            }

            // PERBAIKAN V2 (2026-11-05): MINIMAP MENAMPILKAN SEMUA scan
            // face attendance pada hari tersebut, bukan hanya scan pertama.
            // Sebelumnya hanya 1 marker (dari faceDetails[0]) sehingga
            // clock out tidak terlihat di peta.
            //
            // Setiap scan dengan koordinat valid digambar marker-nya sendiri
            // dengan warna berbeda (hijau = Clock In, merah = Clock Out),
            // plus garis penghubung (polyline) bila ada >1 scan supaya
            // perpindahan posisi terlihat.
            const scans = Array.isArray(allScans) ? allScans : [];
            const scanPts = [];
            let markerIdx = 0;

            scans.forEach(function(s) {
                if (s.latitude === null || s.latitude === undefined ||
                    s.longitude === null || s.longitude === undefined) return;
                const slat = parseFloat(s.latitude);
                const slng = parseFloat(s.longitude);
                if (isNaN(slat) || isNaN(slng)) return;

                scanPts.push([slat, slng]);

                const label = (s.action_type || 'Absensi');
                const isIn = /clock\s*in|masuk/i.test(label);
                const bg = isIn ? '#1cc88a' : '#e74a3b';
                const sAcc = (s.accuracy !== null && s.accuracy !== undefined && s.accuracy !== '')
                    ? Math.round(parseFloat(s.accuracy)) : null;
                const accTxt = sAcc !== null && !isNaN(sAcc) ? ('Akurasi ±' + sAcc + ' m') : 'Akurasi tidak tersedia';
                const locName = s.location_resolved || s.location_name || 'Posisi Absensi';

                const icon = L.divIcon({
                    className: 'custom-face-marker',
                    html: '<div style="background:' + bg + ';color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.35);font-size:13px;border:2px solid #fff;">&#128100;</div>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });

                const mk = L.marker([slat, slng], { icon: icon }).addTo(detailMap);
                mk.bindPopup(
                    '<strong>' + label + '</strong> - ' + (s.scan_time || '-') + '<br>' +
                    locName + '<br>' + accTxt
                );
                // Marker pertama tetap disimpan di detailMarker (dipakai
                // oleh kode lama).
                if (markerIdx === 0) {
                    detailMarker = mk;
                }
                markerIdx++;
            });

            // Garis penghubung antar scan (hanya bila ada >= 2 titik).
            if (scanPts.length >= 2) {
                L.polyline(scanPts, {
                    color: '#4e73df',
                    weight: 2,
                    opacity: 0.55,
                    dashArray: '6,6'
                }).addTo(detailMap);
            }

            // Circle radius akurasi GPS untuk scan PERTAMA (yang jadi titik
            // tengah / acuan) — sama seperti perilaku sebelumnya.
            if (accuracy && accuracy > 0) {
                detailCircle = L.circle([lat, lng], {
                    radius: accuracy,
                    color: '#e74a3b',
                    weight: 1,
                    fillColor: '#e74a3b',
                    fillOpacity: 0.12
                }).addTo(detailMap);
            }

            setTimeout(function() {
                if (detailMap) detailMap.invalidateSize();
            }, 200);
        }

        // ===== Render Rekap Harian di dalam modal =====
        // PERBAIKAN (2026-11-03): rincian harian sebelumnya adalah baris
        // expand di tabel utama dan tampil berantakan (seluruh header
        // menumpuk di kolom pertama karena dibatasi lebar kolom "#").
        // Kini dirender di dalam modal dari atribut data-hari (JSON).
        function renderRekapHarian(hari) {
            const body = document.getElementById('rekapHarianBody');
            const countEl = document.getElementById('rekapHarianCount');
            if (!body) return;

            const list = Array.isArray(hari) ? hari : [];
            if (countEl) countEl.textContent = list.length + ' hari';

            if (!list.length) {
                body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">'
                    + 'Belum ada data harian.</td></tr>';
                return;
            }

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            let html = '';
            list.forEach(function(h, i) {
                // Jam masuk
                const masuk = h.masuk
                    ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-sign-in-alt mr-1"></i>'
                      + esc(h.masuk) + '</span>'
                    : '<span class="badge badge-light border text-muted px-2 py-1">Belum Scan</span>';

                // Jam pulang
                const pulang = h.pulang
                    ? '<span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-sign-out-alt mr-1"></i>'
                      + esc(h.pulang) + '</span>'
                    : '<span class="badge badge-light border text-muted px-2 py-1">Belum Scan</span>';

                // Mode presensi
                let mode = '';
                (h.modes || []).forEach(function(m) {
                    const ikon = (m === 'Wajah') ? 'fa-user-check' : 'fa-fingerprint';
                    const warna = (m === 'Wajah') ? 'primary' : 'info';
                    mode += '<span class="badge badge-' + warna + ' px-2 py-1 mr-1">'
                          + '<i class="fas ' + ikon + ' mr-1"></i>' + esc(m) + '</span>';
                });
                if (!mode) mode = '<span class="text-muted small">-</span>';

                // Status
                let status;
                if (h.lengkap) {
                    status = '<span class="badge badge-success px-2 py-1">'
                           + '<i class="fas fa-check-double mr-1"></i> Lengkap</span>';
                } else if (h.ada_masuk) {
                    status = '<span class="badge badge-info px-2 py-1">'
                           + '<i class="fas fa-sign-in-alt mr-1"></i> Clock In Saja</span>';
                } else {
                    status = '<span class="badge badge-warning px-2 py-1">'
                           + '<i class="fas fa-sign-out-alt mr-1"></i> Clock Out Saja</span>';
                }

                // ============================================================
                // PERBAIKAN 20 (2026-11-04): tombol aksi per hari.
                // ------------------------------------------------------------
                // Data foto & koordinat dikirim lewat atribut data-scan.
                // Tombolnya HANYA aktif bila hari itu punya scan wajah;
                // kalau tidak, ditampilkan sebagai tombol mati dengan
                // keterangan, supaya tidak ada klik yang berujung modal
                // kosong (mis. hari yang hanya berisi scan fingerprint).
                // ============================================================
                let aksi;
                const scanHari = Array.isArray(h.scan) ? h.scan : [];
                if (scanHari.length > 0) {
                    aksi = '<button type="button" '
                         + 'class="btn btn-sm btn-primary rounded-pill px-3 btn-peta-foto" '
                         + 'data-scan="' + esc(JSON.stringify(scanHari)) + '" '
                         + 'data-tanggal="' + esc(h.tanggal + (h.hari ? ' (' + h.hari + ')' : '')) + '">'
                         + '<i class="fas fa-map-marked-alt mr-1"></i> Lihat'
                         + '</button>';
                } else {
                    aksi = '<button type="button" class="btn btn-sm btn-light border rounded-pill px-3" disabled '
                         + 'title="Tidak ada foto wajah pada hari ini">'
                         + '<i class="fas fa-image mr-1 text-muted"></i> Tidak ada'
                         + '</button>';
                }

                html += '<tr>'
                      + '<td class="text-center font-weight-bold">' + (i + 1) + '</td>'
                      + '<td><span class="font-weight-bold text-gray-900 d-block">' + esc(h.tanggal) + '</span>'
                      + '<small class="text-muted">' + esc(h.hari) + '</small></td>'
                      + '<td class="text-center">' + masuk + '</td>'
                      + '<td class="text-center">' + pulang + '</td>'
                      + '<td class="text-center">' + mode + '</td>'
                      + '<td class="text-center">' + status + '</td>'
                      + '<td class="text-center">' + aksi + '</td>'
                      + '</tr>';
            });
            body.innerHTML = html;
        }

        // ====================================================================
        // PERBAIKAN 20 (2026-11-04): buka modal "Peta & Foto" per hari.
        // --------------------------------------------------------------------
        // Memakai event delegation pada document, karena tombol-tombol ini
        // dibuat ulang setiap kali tabel Rekap Harian dirender. Tanpa
        // delegation, tombol hasil render baru tidak akan bereaksi.
        // ====================================================================
        let petaHarian = null;      // instance Leaflet
        let lapisanHarian = null;   // layer group (polygon + marker + circle)

        function escHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function hancurkanPetaHarian() {
            if (petaHarian) {
                try { petaHarian.remove(); } catch (e) { /* sudah hilang */ }
                petaHarian = null;
            }
            lapisanHarian = null;
        }

        function renderFotoHarian(list) {
            const wrap = document.getElementById('pfFotoWrap');
            if (!wrap) return;

            if (!list.length) {
                wrap.innerHTML = '<div class="text-center text-muted py-4">'
                    + '<i class="fas fa-image fa-2x d-block mb-2 text-secondary"></i>'
                    + 'Tidak ada foto pada hari ini.</div>';
                return;
            }

            let html = '';
            list.forEach(function(s, i) {
                const jam = s.jam || '-';
                const label = s.label || '';
                const badgeWarna = /out/i.test(label) ? 'warning text-dark' : 'success';

                // Foto disimpan sebagai data-URI base64 di database.
                // Bila kosong (mis. scan gagal simpan gambar), tampilkan
                // placeholder agar tata letak tidak rusak.
                const img = s.foto
                    ? '<img src="' + escHtml(s.foto) + '" alt="Foto absensi ' + escHtml(jam) + '" '
                      + 'class="img-fluid rounded shadow-sm w-100" style="object-fit:cover;max-height:230px;">'
                    : '<div class="d-flex align-items-center justify-content-center rounded bg-light border" '
                      + 'style="height:150px;"><span class="text-muted small">'
                      + '<i class="fas fa-user-slash mr-1"></i> Foto tidak tersedia</span></div>';

                html += '<div class="mb-2' + (i > 0 ? ' mt-3 pt-3 border-top' : '') + '">'
                      + img
                      + '<div class="d-flex justify-content-between align-items-center mt-2 px-1">'
                      + '<span class="badge badge-' + badgeWarna + ' px-2 py-1">'
                      + '<i class="fas fa-clock mr-1"></i>' + escHtml(label ? label + ' - ' + jam : jam)
                      + '</span>'
                      + (s.akurasi ? '<small class="text-muted">Akurasi &plusmn;' + Math.round(parseFloat(s.akurasi)) + ' m</small>' : '')
                      + '</div></div>';
            });
            wrap.innerHTML = html;
        }

        function gambarPetaHarian(lat, lng, akurasi, infoLokasi) {
            const el = document.getElementById('petaFotoHarian');
            if (!el) return;
            if (!window.L) {
                el.innerHTML = '<div class="text-center text-muted small py-5">'
                    + 'Pustaka peta belum termuat.</div>';
                return;
            }

            hancurkanPetaHarian();

            petaHarian = L.map(el, { zoomControl: true, attributionControl: false })
                .setView([lat, lng], 17);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(petaHarian);

            lapisanHarian = L.layerGroup().addTo(petaHarian);

            // Polygon area kerja: hijau untuk area yang memuat titik absensi,
            // biru untuk area aktif lain, abu-abu untuk non-aktif.
            let cocok = null;
            try {
                cocok = findLocationByCoords(workLocations, lat, lng);
            } catch (e) {
                cocok = null;
            }

            (workLocations || []).forEach(function(loc) {
                if (!loc.coordinates || loc.coordinates.length < 3) return;
                const latLngs = loc.coordinates.map(function(c) { return [c[1], c[0]]; });
                const aktif = loc.is_active !== false;
                const iniCocok = cocok && cocok.name === loc.name;
                const warna = iniCocok ? '#1cc88a' : (aktif ? '#4e73df' : '#858796');

                L.polygon(latLngs, {
                    color: warna,
                    weight: iniCocok ? 3 : 2,
                    fillColor: warna,
                    fillOpacity: iniCocok ? 0.25 : (aktif ? 0.15 : 0.08)
                }).bindPopup('<strong>' + escHtml(loc.name || 'Lokasi Kerja') + '</strong>')
                  .addTo(lapisanHarian);
            });

            const ikon = L.divIcon({
                className: 'custom-face-marker',
                html: '<div style="background:#e74a3b;color:#fff;border-radius:50%;width:28px;height:28px;'
                    + 'display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.35);'
                    + 'font-size:14px;">&#128100;</div>',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });

            L.marker([lat, lng], { icon: ikon })
                .bindPopup('<strong>' + escHtml(cocok && cocok.name ? cocok.name : 'Posisi Absensi') + '</strong>')
                .addTo(lapisanHarian);

            if (akurasi && akurasi > 0) {
                L.circle([lat, lng], {
                    radius: akurasi,
                    color: '#e74a3b',
                    weight: 1,
                    fillColor: '#e74a3b',
                    fillOpacity: 0.12
                }).addTo(lapisanHarian);
            }

            const info = document.getElementById('pfLokasiInfo');
            if (info) {
                const namaLokasi = (cocok && cocok.name) || infoLokasi || '';
                info.innerHTML = (namaLokasi ? '<i class="fas fa-building mr-1"></i>' + escHtml(namaLokasi) : '')
                    + (akurasi ? '<br><i class="fas fa-satellite-dish mr-1"></i>Akurasi GPS &plusmn;'
                       + Math.round(parseFloat(akurasi)) + ' m' : '');
            }

            // Ukuran container baru pasti setelah modal tampil.
            setTimeout(function() {
                if (petaHarian) petaHarian.invalidateSize();
            }, 250);
        }

        function tampilkanPetaFotoHarian(scan, tanggalLabel) {
            const list = Array.isArray(scan) ? scan : [];

            const elJumlah = document.getElementById('pfJumlah');
            if (elJumlah) elJumlah.textContent = list.length;

            const elTanggal = document.getElementById('pfTanggal');
            if (elTanggal) {
                // Nama karyawan diambil dari modal Detail Presensi yang
                // sedang terbuka (id: detailEmployeeName), supaya modal
                // peta/foto tetap berada dalam konteks orang yang sama.
                const elSumber = document.getElementById('detailEmployeeName');
                const elNama = document.getElementById('pfKaryawan');
                if (elNama) {
                    const nama = elSumber ? elSumber.textContent.trim() : '';
                    elNama.textContent = nama || '-';
                }
                elTanggal.textContent = tanggalLabel || '-';
            }

            renderFotoHarian(list);

            // Peta memakai scan PERTAMA yang punya koordinat. Kalau tidak
            // ada koordinat sama sekali, container diberi keterangan.
            let acuan = null;
            for (let i = 0; i < list.length; i++) {
                const s = list[i];
                if (s && s.lat !== null && s.lat !== undefined && s.lat !== ''
                       && s.lng !== null && s.lng !== undefined && s.lng !== '') {
                    acuan = s;
                    break;
                }
            }

            const el = document.getElementById('petaFotoHarian');
            if (!acuan) {
                hancurkanPetaHarian();
                if (el) {
                    el.innerHTML = '<div class="text-center text-muted small py-5">'
                        + '<i class="fas fa-map-marker-slash fa-2x d-block mb-2 text-secondary"></i>'
                        + 'Koordinat lokasi tidak tersimpan pada scan ini.</div>';
                }
                const info = document.getElementById('pfLokasiInfo');
                if (info) info.innerHTML = '';
                return;
            }

            if (el) el.innerHTML = '';
            gambarPetaHarian(
                parseFloat(acuan.lat),
                parseFloat(acuan.lng),
                acuan.akurasi ? parseFloat(acuan.akurasi) : 0,
                acuan.lokasi || ''
            );
        }

        $(document).on('click', '.btn-peta-foto', function() {
            const $b = $(this);
            let scan = [];

            // jQuery .data() sudah mem-parse JSON pada atribut data-scan.
            const raw = $b.attr('data-scan');
            if (raw) {
                try { scan = JSON.parse(raw); } catch (e) { scan = []; }
            }

            tampilkanPetaFotoHarian(scan, $b.attr('data-tanggal') || '');

            if (window.jQuery && jQuery.fn.modal) {
                // Modal ini dibuka DI ATAS modal Detail Presensi. Bootstrap 4
                // memberi z-index sama pada semua modal, sehingga modal kedua
                // bisa tertimbun / overlay-nya menutupi isi. Naikkan z-index
                // modal + backdrop-nya secara eksplisit.
                jQuery('#modalPetaFotoHarian').css('z-index', 1080).modal('show');
                setTimeout(function() {
                    jQuery('.modal-backdrop').last().css('z-index', 1075);
                    // Modal Detail Presensi di belakang tidak boleh ikut
                    // menerima scroll saat modal peta aktif.
                    jQuery('#modalFaceDetail').css('overflow', 'hidden');
                }, 80);
            }
        });

        // Kembalikan keadaan modal Detail Presensi setelah modal peta ditutup,
        // lalu gambar ulang ukuran peta detailnya (Leaflet perlu ini).
        $(document).on('hidden.bs.modal', '#modalPetaFotoHarian', function() {
            hancurkanPetaHarian();
            jQuery('#modalFaceDetail').css('overflow', '');
            if (detailMap) {
                setTimeout(function() {
                    try { detailMap.invalidateSize(); } catch (e) { /* abaikan */ }
                }, 150);
            }
        });

        $(document).on('click', '[data-npk]', function() {
            const $btn = $(this);
            const npk = $btn.attr('data-npk');
            // PERBAIKAN (2026-11-03): nama karyawan diambil dari atribut
            // sebagai nilai awal. Sumber kebenaran sebenarnya adalah
            // response API (employee.name) yang dipakai di applyData(),
            // karena atribut bisa kosong / ter-escape.
            const empName = $btn.attr('data-emp-name') || '-';
            const empDept = $btn.attr('data-emp-dept') || '';
            const date = $btn.attr('data-date') || '';
            const dateLabel = $btn.attr('data-date-label') || '-';

            // Rekap harian: baca JSON dari atribut data-hari.
            // jQuery .data() otomatis mem-parse JSON, tetapi bila atribut
            // belum ada (tombol lama) nilainya undefined -> array kosong.
            let hari = $btn.attr('data-hari');
            try {
                hari = hari ? JSON.parse(hari) : [];
            } catch (e) {
                hari = [];
            }
            renderRekapHarian(hari);

            document.getElementById('detailEmployeeName').textContent = empName;
            document.getElementById('detailEmployeeNpk').textContent = npk || '-';
            document.getElementById('detailEmployeeDept').textContent = empDept || '-';
            document.getElementById('detailDateLabel').textContent = dateLabel;
            document.getElementById('detailLocationName').textContent = '-';
            document.getElementById('detailLocationSub').textContent = '-';
            document.getElementById('detailCoords').textContent = '-';

            if (!npk || !date) {
                renderFaceImages([]);
                destroyMap();
                return;
            }

            fetch("{{ url('/face-attendance/admin/detail') }}/" + encodeURIComponent(npk) + "/" + encodeURIComponent(date), {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.success) {
                    applyData(data);
                } else {
                    renderFaceImages([]);
                    destroyMap();
                }
            })
            .catch(function() {
                renderFaceImages([]);
                destroyMap();
            });
        });

        function applyData(data) {
            const scans = data.scans || [];
            const faceDetails = data.face_details || [];
            const employee = data.employee || {};

            // PERBAIKAN (2026-11-03): isi identitas karyawan dari API.
            // Sebelumnya modal menampilkan "KARYAWAN: -" karena nama hanya
            // diambil dari atribut tombol dan tidak pernah di-refresh dari
            // response server. Sekarang API yang jadi sumber utama, atribut
            // tombol hanya fallback.
            const nameEl = document.getElementById('detailEmployeeName');
            nameEl.textContent = employee.name || '-';
            document.getElementById('detailEmployeeNpk').textContent = employee.npk || '-';
            document.getElementById('detailEmployeeDept').textContent = employee.dept && employee.dept !== '-' ? employee.dept : '-';

            // Gabungkan scan + detail wajah (face_details sudah punya scan_time & action_type)
            const merged = [];
            faceDetails.forEach(function(f) {
                merged.push(Object.assign({}, f, {
                    mode_label: 'Wajah',
                    action_type: f.action_type || 'Absensi Wajah'
                }));
            });
            scans.forEach(function(s) {
                if (s.mode_label !== 'Wajah') {
                    merged.push(s);
                }
            });
            merged.sort(function(a, b) {
                return String(a.scan_time || '').localeCompare(String(b.scan_time || ''));
            });

            const first = faceDetails.length > 0 ? faceDetails[0] : null;

            if (first && first.latitude !== null && first.longitude !== null) {
                const lat = parseFloat(first.latitude);
                const lng = parseFloat(first.longitude);

                // PERBAIKAN (2026-11-03): nama lokasi kerja.
                //  - first.location_name bisa berupa koordinat mentah (fallback
                //    dari FaceAttendanceLog::locationName()) -> tidak informatif.
                //  - API sudah mengirim location_resolved (hasil pencocokan
                //    point-in-polygon ke nama area kerja).
                //  - Kalau keduanya kosong, cari polygon yang memuat koordinat
                //    ini di sisi frontend sebagai fallback terakhir.
                const resolved = first.location_resolved || '';
                const rawName = first.location_name || '';
                const isCoordText = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(rawName.trim());
                const finalName = resolved
                    || (rawName && !isCoordText ? rawName : '')
                    || findLocationByCoords(workLocations, lat, lng)
                    || '-';

                document.getElementById('detailLocationName').textContent = finalName;

                // Baris kedua: akurasi GPS + penanda polygon aktif/tidak.
                const acc = parseFloat(first.accuracy);
                const accText = (!isNaN(acc) && acc > 0) ? 'Akurasi GPS ±' + Math.round(acc) + ' m' : 'Akurasi GPS tidak tersedia';
                const matched = findLocationByCoords(workLocations, lat, lng);
                const statusText = matched
                    ? (matched.is_active ? 'Polygon aktif' : 'Polygon non-aktif')
                    : 'Di luar polygon lokasi kerja';
                document.getElementById('detailLocationSub').textContent = accText + ' · ' + statusText;

                // PERBAIKAN (2026-11-03): #detailCoords TIDAK lagi diisi
                // koordinat mentah. Elemennya tetap ada (d-none) supaya
                // tidak error, tapi isinya cuma penanda. Koordinat tidak
                // perlu disimpan di DOM karena peta sudah menunjuk lokasi.
                document.getElementById('detailCoords').textContent = 'tersembunyi';

                // PERBAIKAN V2 (2026-11-05): kirim SELURUH scan (wajah +
                // fingerprint) ke minimap supaya semua titik absensi hari itu
                // tampil, bukan hanya scan pertama.
                initMap(lat, lng, parseFloat(first.accuracy), workLocations, merged);
            } else {
                document.getElementById('detailLocationName').textContent = '-';
                document.getElementById('detailLocationSub').textContent = 'Tidak ada data lokasi pada scan ini';
                document.getElementById('detailCoords').textContent = '-';
                destroyMap();
            }

            renderFaceImages(merged);
        }

        // PERBAIKAN (2026-11-03): helper untuk mencari polygon lokasi kerja
        // yang memuat koordinat tertentu (point-in-polygon versi client).
        // Dipakai sebagai fallback penamaan lokasi & penanda status polygon.
        function findLocationByCoords(locations, lat, lng) {
            if (!locations || !locations.length) return null;
            for (let i = 0; i < locations.length; i++) {
                const coords = locations[i].coordinates;
                if (!coords || coords.length < 3) continue;
                let inside = false;
                const n = coords.length;
                let j = n - 1;
                for (let k = 0; k < n; k++) {
                    const lngI = parseFloat(coords[k][0]);
                    const latI = parseFloat(coords[k][1]);
                    const lngJ = parseFloat(coords[j][0]);
                    const latJ = parseFloat(coords[j][1]);
                    const intersect = ((latI > lat) !== (latJ > lat)) &&
                        (lng < (lngJ - lngI) * (lat - latI) / ((latJ - latI) || 0.0000001) + lngI);
                    if (intersect) inside = !inside;
                    j = k;
                }
                if (inside) return locations[i];
            }
            return null;
        }

        // Bersihkan peta saat modal ditutup
        $('#modalFaceDetail').on('hidden.bs.modal', function() {
            destroyMap();
        });

        // PERBAIKAN (2026-11-03): Leaflet mengukur container saat peta
        // dibuat. Karena peta dibuat sebelum modal sepenuhnya tampil,
        // ukurannya bisa terbaca 0x0 sehingga tile & POLYGON lokasi kerja
        // tidak digambar (keluhan "polygon GPS tidak ada").
        $('#modalFaceDetail').on('shown.bs.modal', function() {
            if (detailMap) {
                detailMap.invalidateSize();
                try {
                    const layers = [];
                    if (detailPolyGroup) {
                        detailPolyGroup.eachLayer(function(l) {
                            if (typeof l.getBounds === 'function') layers.push(l);
                        });
                    }
                    if (detailMarker) layers.push(detailMarker);
                    if (layers.length > 1) {
                        detailMap.fitBounds(L.featureGroup(layers).getBounds().pad(0.25));
                    }
                } catch (e) {
                    // Diamkan: peta tetap tampil walau fit bounds gagal.
                }
            }
            setTimeout(function() {
                if (detailMap) detailMap.invalidateSize();
            }, 350);
        });
    })();
</script>


<script>
/**
 * Lazy load data rekap presensi.
 * Halaman + filter tampil instan; data berat diambil via AJAX setelah render.
 * Mencegah request timeout / 504 saat query att_log besar.
 */
(function () {
    var container = document.getElementById('rekapDataContainer');
    if (!container) return;

    var loading = document.getElementById('rekapLoadingBanner');
    var errBox  = document.getElementById('rekapErrorBanner');

    function showLoading() { if (loading) loading.classList.remove('d-none'); }
    function hideLoading() { if (loading) loading.classList.add('d-none'); }

    function showError(msg) {
        if (!errBox) return;
        errBox.textContent = msg;
        errBox.classList.remove('d-none');
    }
    function hideError() {
        if (!errBox) return;
        errBox.classList.add('d-none');
        errBox.textContent = '';
    }

    // ================================================================
    // PERBAIKAN (2026-11-03): Select2 untuk filter Karyawan + Departemen
    // ================================================================
    // "Cari (Nama / NPK / Barcode)" -> Select2 dengan AJAX ke
    //   /face-attendance/admin/employees  (union BIODATA + BIODATA_KELUAR)
    // "Departemen" -> Select2 dengan AJAX ke
    //   /face-attendance/admin/departments
    //
    // Kedua-duanya searching di SERVER, jadi browser tidak pernah memuat
    // seluruh master karyawan (ratusan/ribuan baris).
    // ================================================================
    function initSelect2() {
        if (typeof window.jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $ = jQuery;

        // --- Karyawan (union BIODATA + BIODATA_KELUAR) ---
        var $emp = $('#filterSearch');
        if ($emp.length && !$emp.hasClass('select2-hidden-accessible')) {
            $emp.select2({
                placeholder: 'Ketik nama / NPK / barcode...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 2,
                delay: 300,
                ajax: {
                    url: '{{ route('face.attendance.admin.employees') }}',
                    type: 'GET',
                    dataType: 'json',
                    delay: 300,
                    cache: true,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            limit: 25
                        };
                    },
                    processResults: function (data) {
                        return { results: data || [] };
                    }
                },
                templateResult: function (item) {
                    if (!item.id) {
                        return item.text || 'Karyawan tidak ditemukan';
                    }
                    var badge = item.sumber === 'Keluar'
                        ? ' <span class="badge badge-secondary badge-sm">Keluar</span>'
                        : '';
                    return $('<div>').append(
                        $('<div>').append(
                            $('<span>').text(item.name || item.npk),
                            $('<span>').html(' &middot; ' + (item.npk || '') + badge))
                    );
                }
            });
        }

        // --- Departemen ---
        var $dept = $('#filterDepartment');
        if ($dept.length && !$dept.hasClass('select2-hidden-accessible')) {
            $dept.select2({
                placeholder: 'Semua Departemen',
                allowClear: true,
                width: '100%',
                delay: 250,
                ajax: {
                    url: '{{ route('face.attendance.admin.departments') }}',
                    type: 'GET',
                    dataType: 'json',
                    delay: 250,
                    cache: true,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        return { results: data || [] };
                    }
                }
            });
        }
    }

    function buildUrl() {
        var month = document.getElementById('filterMonth');
        var dept  = document.getElementById('filterDepartment');
        var q     = document.getElementById('filterSearch');
        var params = new URLSearchParams(window.location.search);
        if (month && month.value) params.set('month', month.value);
        // PERBAIKAN: nilai filter selalu dikirim (meski kosong) supaya
        // filter yang dikosongkan benar-benar ter-reset, bukan diam-diam
        // memakai filter lama dari URL.
        params.set('department_id', dept ? (dept.value || '') : '');
        params.set('q', q ? (q.value || '') : '');
        params.set('only_data', '1');
        return '{{ route('face.attendance.admin.index') }}?' + params.toString();
    }

    var inflight = null;

    // ================================================================
    // PERBAIKAN 19 (2026-11-04): sinkronkan href tombol Export Excel
    // dengan filter yang SEDANG aktif.
    // ----------------------------------------------------------------
    // Dulu tombol export hanya membawa `month`, sehingga meski layar
    // difilter per departemen / kata kunci, file yang diunduh tetap
    // berisi semua karyawan. Sekarang href dibangun dari nilai filter
    // yang benar-benar ada di form, memakai parameter yang sama dengan
    // request rekap (`month`, `department_id`, `q`). Export hanya
    // dipanggil saat diklik, jadi tidak menambah beban memuat halaman.
    // ================================================================
    function syncExportLink() {
        var btn = document.getElementById('btnExportExcel');
        if (!btn) return;

        var base  = btn.getAttribute('data-base') || btn.getAttribute('href') || '';
        var month = document.getElementById('filterMonth');
        var dept  = document.getElementById('filterDepartment');
        var q     = document.getElementById('filterSearch');

        var qs = new URLSearchParams();
        qs.set('month', (month && month.value) ? month.value : '');
        qs.set('department_id', dept ? (dept.value || '') : '');
        qs.set('q', q ? (q.value || '') : '');

        btn.setAttribute('href', base + '?' + qs.toString());
    }

    function loadData() {
        // Selalu selaraskan tautan export sebelum memuat data.
        syncExportLink();

        showLoading();
        hideError();
        container.setAttribute('aria-busy', 'true');

        // Hanya redupkan bila sudah ada isi (muat ulang). Pada kunjungan
        // pertama cukup placeholder spinner yang tampil.
        if (!document.getElementById('rekapPlaceholder')) {
            container.style.opacity = '0.45';
        }

        if (inflight && inflight.abort) inflight.abort();
        inflight = new AbortController();

        fetch(buildUrl(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: inflight.signal
        })
        .then(function (r) {
            if (!r.ok) throw new Error('Server membalas ' + r.status + ' ' + r.statusText);
            return r.text();
        })
        .then(function (html) {
            container.innerHTML = html;
            container.style.opacity = '1';
            container.setAttribute('aria-busy', 'false');
            hideLoading();
            initRekapTable();
            document.dispatchEvent(new CustomEvent('rekap:loaded'));
        })
        .catch(function (e) {
            if (e.name === 'AbortError') return;
            container.style.opacity = '1';
            container.setAttribute('aria-busy', 'false');
            hideLoading();
            // Jangan biarkan spinner berputar selamanya saat gagal.
            if (document.getElementById('rekapPlaceholder')) {
                container.innerHTML = '';
            }
            showError('Gagal memuat data rekap: ' + e.message + '. Periksa koneksi database, data akan dimuat ulang otomatis saat filter diubah.');
        });
    }

    // Inisialisasi DataTables untuk konten yang baru dimuat
    //
    // PERBAIKAN (2026-11-03):
    //  - Selalu DESTROY instance lama sebelum membuat yang baru. Kalau tidak,
    //    DataTable tetap memegang wrapper dari tabel LAMA (yang sudah
    //    dibuang AJAX), sehingga tampilan rusak dan baris tidak muncul.
    //  - Opsi "Semua" (-1) dihapus dari lengthMenu. Dengan ribuan
    //    baris, merender semua sekaligus membuat browser hang.
    function initRekapTable() {
        if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
        var $t = jQuery('#tableRekapAdmin');
        if (!$t.length) return;

        // Buang instance lama (termasuk wrapper & event listener-nya).
        try {
            if (jQuery.fn.DataTable.isDataTable($t[0])) {
                $t.DataTable().destroy();
            }
        } catch (e) {
            console.warn('[AdminSummary] Gagal destroy DataTable lama:', e);
        }
        // Hapus wrapper sisa kalau destroy tidak sempurna.
        try {
            var dt = $t.closest('.dataTables_wrapper');
            if (dt.length) {
                $t.unwrap();
                dt.remove();
            }
        } catch (e) {
            console.warn('[AdminSummary] Gagal bersihkan wrapper DataTable:', e);
        }

        $t.DataTable({
            responsive: false,
            pageLength: 25,
            lengthMenu: [[25, 50, 100], [25, 50, 100]],
            order: [],
            columnDefs: [{ orderable: false, targets: -1 }],
            language: {
                search: 'Cari cepat:',
                lengthMenu: 'Tampilkan _MENU_ baris',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ karyawan',
                infoEmpty: 'Tidak ada data',
                zeroRecords: 'Tidak ada data yang cocok',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
            }
        });
    }

    // Inisialisasi awal setelah fungsi didefinisikan.
    // Dipanggil di blok <script> yang sama dengan definisinya supaya tidak
    // terkena "initRekapTable is not defined".
    // DOMContentLoaded dipakai karena blok ini berada di akhir body:
    // kalau dokumen sudah siap, jalankan langsung.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRekapTable);
    } else {
        initRekapTable();
    }

    // Form filter: jangan reload halaman penuh, cukup muat ulang data
    var form = document.querySelector('form[action="{{ route('face.attendance.admin.index') }}"]');
    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var params = new URLSearchParams(window.location.search);
            var month = document.getElementById('filterMonth');
            var dept  = document.getElementById('filterDepartment');
            var q     = document.getElementById('filterSearch');
            if (month && month.value) params.set('month', month.value);
            // PERBAIKAN: selalu set (meski kosong) supaya filter yang
            // dikosongkan benar-benar ter-reset.
            params.set('department_id', dept ? (dept.value || '') : '');
            params.set('q', q ? (q.value || '') : '');
            window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
            loadData();
        });
    }

    // ================================================================
    // PERBAIKAN (2026-11-03): Select2 + auto reload saat filter berubah.
    // ================================================================
    // Dulu Select2 tidak ada sama sekali dan Departemen memakai
    // onchange="this.form.submit()" (submit form penuh). Sekarang:
    //   - Select2 diinisialisasi untuk Karyawan & Departemen.
    //   - Perubahan ANYA filter (bulan / dept / karyawan) langsung
    //     memuat ulang data via AJAX, tanpa reload halaman penuh.
    // ================================================================
    function bindFilterAutoReload() {
        var $ = jQuery;

        // Bulan
        var $month = $('#filterMonth');
        if ($month.length) {
            $month.on('change', function () {
                var params = new URLSearchParams(window.location.search);
                params.set('month', this.value);
                var dept = document.getElementById('filterDepartment');
                var q = document.getElementById('filterSearch');
                params.set('department_id', dept ? (dept.value || '') : '');
                params.set('q', q ? (q.value || '') : '');
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                loadData();
            });
        }

        // Departemen (Select2)
        var $dept = $('#filterDepartment');
        if ($dept.length) {
            $dept.on('change', function () {
                var params = new URLSearchParams(window.location.search);
                var month = document.getElementById('filterMonth');
                var q = document.getElementById('filterSearch');
                if (month && month.value) params.set('month', month.value);
                params.set('department_id', this.value || '');
                params.set('q', q ? (q.value || '') : '');
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                loadData();
            });
        }

        // Karyawan (Select2)
        var $emp = $('#filterSearch');
        if ($emp.length) {
            $emp.on('change', function () {
                var params = new URLSearchParams(window.location.search);
                var month = document.getElementById('filterMonth');
                var dept = document.getElementById('filterDepartment');
                if (month && month.value) params.set('month', month.value);
                params.set('department_id', dept ? (dept.value || '') : '');
                params.set('q', this.value || '');
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                loadData();
            });
        }
    }

    function boot() {
        // PERBAIKAN (2026-11-03): initSelect2() bisa dipanggil sebelum
        // library Select2 selesai dimuat (footerscript di-include SETELAH
        // blok script ini). Tanpa penjagaan, dropdown kembali menjadi
        // <select> biasa dan daftar karyawan tampak kosong.
        // Solusi: coba berkali-kali dengan jeda singkat sampai
        // jQuery + select2 benar-benar tersedia (maks ~5 detik).
        var attempts = 0;
        function ensureSelect2() {
            attempts++;
            var siap = (typeof window.jQuery !== 'undefined')
                && window.jQuery.fn && window.jQuery.fn.select2;

            if (siap) {
                initSelect2();
                return;
            }
            if (attempts < 50) {
                setTimeout(ensureSelect2, 100);
            } else {
                // Menyerah: biarkan <select> biasa tetap berfungsi.
                if (window.console && console.warn) {
                    console.warn('[presensi-wajah] Select2 tidak tersedia; '
                        + 'dropdown memakai <select> biasa.');
                }
            }
        }

        ensureSelect2();
        bindFilterAutoReload();
        loadData();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>

{{-- PERBAIKAN (2026-11-03): halaman ini sebelumnya HANYA meng-include
     layout.header + layout.navbar, tanpa layout.footerscript. Akibatnya:

     1. Library Select2 TIDAK PERNAH DIMUAT.
        initSelect2() sudah benar, tetapi langsung berhenti di
        `if (typeof window.jQuery === 'undefined' || !jQuery.fn.select2) return;`
        sehingga "Departemen" dan "Cari (Nama / NPK / Barcode)" tampil
        sebagai <select> biasa dan AJAX-nya tidak pernah berjalan
        (inilah sebab daftar karyawan tampak kosong).

     2. Tombol burger di navbar TIDAK BERFUNGSI.
        Handler #sidebarToggleTop ditulis di layout.footerscript.
        Karena include-nya tidak ada, tombol hanya hiasan.
        Inilah sebabnya masalah ini HANYA terjadi di halaman ini.

     footerscript memuat: jquery, bootstrap.bundle, select2, easing,
     sb-admin-2, serta handler sidebar toggle.
     Catatan: jQuery sudah dimuat di atas; footerscript memuat ulang di
     posisi yang sama seperti layout lain sehingga aman. --}}
@include('layout.footerscript')

</body>
</html>
