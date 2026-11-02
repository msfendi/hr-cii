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
    /* PERBAIKAN (2026-11-03): kotak foto wajah dibuat SAMA TINGGI
       dengan peta (260px) supaya keduanya sejajar rapi dan tidak
       menyisakan ruang kosong menganggur. Foto di-scale ke dalam
       kotak (object-fit: contain) agar tidak terpotong. */
    .face-detail-box {
        height: 260px;
        min-height: 260px;
        width: 100%;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        background: #f8f9fc;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 6px;
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
    .face-detail-box {
        /* Isi kotak disusun vertikal: foto utama + strip thumbnail. */
        flex-direction: column;
    }
    /* Di layar kecil, peta & wajah menumpuk kembali dan tingginya
       dikurangi supaya modal tidak terlalu panjang. */
    @media (max-width: 767.98px) {
        #miniMapDetail,
        .face-detail-box {
            height: 220px;
            min-height: 220px;
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
                        <a href="{{ route('face.attendance.admin.export', ['month' => $month]) }}"
                           class="btn btn-sm btn-success shadow-sm rounded-pill px-3">
                            <i class="fas fa-file-excel mr-1"></i> Export CSV
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
                            <div class="col-md-4 mb-3 mb-md-0">
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
                            <div class="col-md-2 mb-3 mb-md-0">
                                <label class="small d-block mb-2" style="visibility:hidden;">Filter</label>
                                <button type="submit" class="btn btn-sm btn-primary rounded-pill btn-block" style="height: 31px;">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </button>
                            </div>
                        </form>
                        <div id="rekapLoadingBanner" class="alert alert-info border-left-info shadow-sm mt-3 mb-0" role="status">
                            <div class="d-flex align-items-center">
                                <span class="spinner-border spinner-border-sm text-info mr-2" role="status" aria-hidden="true"></span>
                                <span>
                                    <strong>Memuat data rekap...</strong>
                                    <span class="d-block small text-gray-700">
                                        Halaman ditampilkan lebih dulu, data presensi diambil di belakang.
                                        Filter bulan / departemen / pencarian akan langsung memuat ulang data.
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div id="rekapErrorBanner" class="alert alert-danger border-left-danger shadow-sm mt-3 mb-0 d-none" role="alert"></div>
                    </div>
                </div>

                {{-- Kontainer data rekap: diisi via AJAX (lazy load) --}}
                <div id="rekapDataContainer">

                @include('face_attendance.partials.admin_summary_data')
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
                                        <th width="22%">Tanggal &amp; Hari</th>
                                        <th width="18%" class="text-center">Jam Masuk</th>
                                        <th width="18%" class="text-center">Jam Pulang</th>
                                        <th width="15%" class="text-center">Mode</th>
                                        <th width="22%" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="rekapHarianBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">
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
                // PERBAIKAN (2026-11-03): foto dibungkus figure yang
                // mengisi kotak .face-detail-box (260px). Sebelumnya
                // setiap foto berada di div .mb-3 dengan max-width 260px,
                // sehingga kalau ada lebih dari satu foto, kotaknya
                // memanjang ke bawah dan menyisakan ruang kosong.
                // Sekarang hanya foto PERTAMA yang ditampilkan besar
                // (paling relevan = scan terakhir), sisanya jadi
                // thumbnail kecil berjajar di bawahnya.
                const first = withImage[0];
                const srcFirst = first.face_image.indexOf('data:') === 0
                    ? first.face_image
                    : 'data:image/jpeg;base64,' + first.face_image;

                const wrap = document.createElement('div');
                wrap.className = 'face-detail-wrap w-100 d-flex flex-column align-items-center justify-content-center';
                wrap.innerHTML =
                    '<img src="' + srcFirst + '" alt="Wajah terakhir" class="face-detail-main">' +
                    '<div class="small text-muted mt-1 text-center">' +
                        '<i class="fas fa-clock mr-1"></i>' +
                        (first.action_type || 'Absensi') + ' - ' + (first.scan_time || '-') +
                    '</div>';
                container.appendChild(wrap);

                // Thumbnail tambahan bila ada lebih dari satu foto.
                if (withImage.length > 1) {
                    const strip = document.createElement('div');
                    strip.className = 'face-detail-strip mt-1';
                    withImage.slice(1).forEach(function (d, idx) {
                        const s = d.face_image.indexOf('data:') === 0
                            ? d.face_image
                            : 'data:image/jpeg;base64,' + d.face_image;
                        const th = document.createElement('img');
                        th.src = s;
                        th.className = 'face-detail-thumb';
                        th.alt = 'Wajah ' + (idx + 2);
                        th.title = (d.action_type || 'Absensi') + ' - ' + (d.scan_time || '-');
                        strip.appendChild(th);
                    });
                    container.appendChild(strip);
                }
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

                rowsHtml +=
                    '<tr>' +
                    '<td class="text-center text-muted align-middle">' + (i + 1) + '</td>' +
                    '<td class="align-middle"><i class="far fa-clock text-muted mr-1"></i>' +
                        '<span class="font-weight-bold">' + (d.scan_time || '-') + '</span></td>' +
                    '<td class="align-middle">' + modeBadge + '</td>' +
                    '<td class="align-middle">' + (d.action_type || '-') + '</td>' +
                    '<td class="align-middle">' + loc + '</td>' +
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
                '<th class="text-center" style="width:85px;">Akurasi</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>' + rowsHtml + '</tbody>' +
                '</table>' +
                '</div>';
        }

        function initMap(lat, lng, accuracy, locations) {
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

            // PERBAIKAN (2026-11-03): polygon lokasi kerja digambar untuk
            // SEMUA lokasi (aktif & non-aktif). Sebelumnya hanya `is_active`
            // yang dikirim server, sehingga kalau sebuah area sedang
            // terkunci (CTPAT lock) polygon-nya hilang dari minimap.
            // Warna dibedakan: aktif = biru, non-aktif = abu-abu.
            // Polygon yang memuat titik absensi diberi warna hijau + label.
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

            // Marker posisi absensi
            const markerIcon = L.divIcon({
                className: 'custom-face-marker',
                html: '<div style="background:#e74a3b;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.35);font-size:14px;">&#128100;</div>',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });

            detailMarker = L.marker([lat, lng], { icon: markerIcon }).addTo(detailMap);
            // PERBAIKAN (2026-11-03): popup marker tidak lagi menampilkan
            // koordinat mentah (lat, lng). Diganti nama lokasi + akurasi,
            // sesuai permintaan user supaya tidak ada angka GPS mentah.
            const popupName = (matchedLoc && matchedLoc.name) || 'Posisi Absensi';
            const accText = (accuracy && accuracy > 0) ? 'Akurasi ±' + Math.round(accuracy) + ' m' : '';
            detailMarker.bindPopup(
                '<strong>' + popupName + '</strong><br>' +
                (accText ? accText : 'Titik lokasi absensi')
            );

            // Circle radius akurasi GPS
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

                html += '<tr>'
                      + '<td class="text-center font-weight-bold">' + (i + 1) + '</td>'
                      + '<td><span class="font-weight-bold text-gray-900 d-block">' + esc(h.tanggal) + '</span>'
                      + '<small class="text-muted">' + esc(h.hari) + '</small></td>'
                      + '<td class="text-center">' + masuk + '</td>'
                      + '<td class="text-center">' + pulang + '</td>'
                      + '<td class="text-center">' + mode + '</td>'
                      + '<td class="text-center">' + status + '</td>'
                      + '</tr>';
            });
            body.innerHTML = html;
        }

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
                initMap(lat, lng, parseFloat(first.accuracy), workLocations);
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

    function loadData() {
        showLoading();
        hideError();
        container.style.opacity = '0.45';

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
            hideLoading();
            initRekapTable();
            document.dispatchEvent(new CustomEvent('rekap:loaded'));
        })
        .catch(function (e) {
            if (e.name === 'AbortError') return;
            container.style.opacity = '1';
            hideLoading();
            showError('Gagal memuat data rekap: ' + e.message + '. Periksa koneksi database lalu tekan Filter untuk mencoba lagi.');
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
