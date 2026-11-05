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
           lebih compact karena peta kini berdampingan dengan foto
           wajah, bukan ditumpuk. */
        height: 260px;
        /* PERBAIKAN (2026-11-03): min-height mencegah peta kolaps menjadi
           0 px saat modal dibuka di dalam modal-scrollable. Peta 0 px
           membuat tata letak "berantakan" (kolom kiri menyusut, tabel
           kanan meluber). */
        min-height: 260px;
        width: 100%;
        border-radius: 8px;
        /* PERBAIKAN (2026-11-03): z-index diturunkan dari 500 ke 1.
           Nilai 500 membuat tile Leaflet (z-index internal 200-800)
           MENIMPA header modal, badge, dan tombol Tutup sehingga
           tata letak terlihat berantakan. */
        z-index: 1;
        background: #eef1f7;
        overflow: hidden;
    }
    /* PERBAIKAN V2 (2026-11-05): kotak foto wajah tidak lagi tinggi FIXED
       260px supaya grid multi-foto (clock in + clock out) muat.
       Tinggi auto dengan batas maksimal + scroll internal. */
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
    .face-detail-box .face-detail-main {
        max-height: 200px;
        max-width: 100%;
        object-fit: contain;
        border-radius: 6px;
        border: 2px solid #e3e6f0;
        box-shadow: 0 2px 8px rgba(0,0,0,.10);
    }
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
    /* PERBAIKAN (2026-11-03): pastikan isi peta Leaflet tidak menimpa
       tombol Tutup dan tidak keluar dari kartu pada layar kecil. */
    #miniMapDetail .leaflet-container {
        border-radius: 8px;
        font-family: inherit;
    }
    .face-capture-preview {
        max-width: 100%;
        border-radius: 8px;
        border: 3px solid #e3e6f0;
        box-shadow: 0 4px 14px rgba(0,0,0,.12);
    }
    .modal-xl .modal-body {
        max-height: 78vh;
        overflow-y: auto;
    }
    /* PERBAIKAN (2026-11-03): kartu ringkasan dibuat rapi dan tidak
       meluber. Sebelumnya teks panjang (nama PT / departemen) memaksa
       kolom melebar sehingga tampilan terlihat berantakan. */
    #modalFaceDetail .card-body .h6 {
        word-break: break-word;
        overflow-wrap: anywhere;
    }
    /* Riwayat scan: tampilkan sebagai kartu, bukan tabel mentah. */
    #faceDetailList .scan-item {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        padding: .6rem .75rem;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        background: #fff;
        margin-bottom: .5rem;
    }
    #faceDetailList .scan-item .scan-time {
        font-weight: 700;
        color: #4e73df;
        min-width: 62px;
    }
    #faceDetailList .scan-item .scan-meta {
        color: #858796;
        font-size: .8rem;
    }
    @media (max-width: 575.98px) {
        #miniMapDetail { height: 240px; min-height: 240px; }
    }

    /* ==========================================================
       PERBAIKAN 2026-11-04 - MOBILE FRIENDLY /face-attendance/rekap
       ----------------------------------------------------------
       Sidebar + navbar dihapus. CSS di bawah memastikan konten
       rekapitulasi memakai SELURUH lebar layar (dulu terpotong
       oleh sidebar sehingga tabel terasa sempit).
       ========================================================== */
    #wrapper {
        display: block !important;
        width: 100% !important;
        min-height: 100vh !important;
    }
    #content-wrapper {
        width: 100% !important;
        margin-left: 0 !important;
        padding-left: 0 !important;
    }
    #content {
        padding-top: 0 !important;
        top: 0 !important;
    }
    @media (max-width: 575.98px) {
        .container-fluid {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }
    }
</style>

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    {{-- PERBAIKAN 2026-11-04: sidebar dihapus agar halaman /face-attendance/rekap
         menjadi mobile friendly (dulu FULL oleh navbar + sidebar sehingga konten
         rekapitulasi terasa sempit). --}}

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            {{-- PERBAIKAN 2026-11-04: navbar dihapus demi tampilan mobile. --}}

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-history text-primary mr-2"></i> Rekapitulasi Presensi Karyawan
                    </h1>
                    <div class="mt-2 mt-sm-0">
                        <a href="{{ route('face.attendance.index') }}" class="btn btn-sm btn-primary shadow-sm rounded-pill px-3">
                            <i class="fas fa-camera fa-sm text-white-50 mr-1"></i> Kamera Presensi
                        </a>
                    </div>
                </div>

                <!-- Card Profil Ringkas -->
                <div class="card shadow mb-4 border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8 mb-3 mb-md-0">
                                <h5 class="font-weight-bold text-gray-900 mb-1">{{ $biodata->NAMA_KARYAWAN ?? 'Karyawan' }}</h5>
                                <span class="badge badge-light border text-muted mr-2">NPK: {{ $biodata->NPK ?? '-' }}</span>
                                <span class="badge badge-info">{{ $biodata->DEPT ?? '-' }}</span>
                            </div>
                            <div class="col-md-4 text-md-right">
                                <form method="GET" action="{{ route('face.attendance.rekap') }}" class="form-inline justify-content-md-end">
                                    <label class="mr-2 font-weight-bold text-gray-700 small">Pilih Bulan:</label>
                                    <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm rounded-pill mr-2" onchange="this.form.submit()">
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="fas fa-filter mr-1"></i> Filter
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Table Rekapitulasi Presensi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar-alt mr-1"></i> Log Presensi Bulan {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }}
                        </h6>
                        <span class="badge badge-primary px-3 py-1 font-weight-bold">
                            Total: {{ count($groupedLogs) }} Hari Kerja
                        </span>
                    </div>
                    <div class="card-body">

                        @if(empty($groupedLogs))
                            {{-- Empty state DI LUAR tabel.
                                 Baris "colspan" di dalam tbody membuat DataTables menghitung
                                 1 sel <td> (colspan diabaikan) sehingga muncul warning
                                 "Incorrect column count". --}}
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x mb-3 d-block text-secondary"></i>
                                <p class="mb-0 text-muted">Belum ada data presensi untuk bulan ini.</p>
                            </div>
                        @endif

                        <!-- Tampilan Desktop (DataTables) -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-bordered table-hover table-rekap" id="tableRekapDesktop" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="3%" class="text-center">#</th>
                                        <th width="14%">Tanggal & Hari</th>
                                        <th width="14%" class="text-center">Jam Masuk (Clock In)</th>
                                        <th width="14%" class="text-center">Jam Pulang (Clock Out)</th>
                                        <th width="14%" class="text-center">Jadwal Shift</th>
                                        <th width="13%" class="text-center">Mode Presensi</th>
                                        <th width="12%" class="text-center">Status</th>
                                        <th width="10%" class="text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $no = 1; @endphp
                                    @forelse($groupedLogs as $log)
                                    <tr>
                                        <td class="text-center font-weight-bold">{{ $no++ }}</td>
                                        <td>
                                            <span class="font-weight-bold text-gray-900 d-block">{{ $log['formatted_date'] }}</span>
                                            <small class="text-muted">{{ $log['day_name'] }}</small>
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_in'])
                                                <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                                                    <i class="fas fa-sign-in-alt mr-1"></i> {{ $log['clock_in'] }}
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">Belum Melakukan Scan</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_out'])
                                                <span class="badge badge-warning px-3 py-2 font-weight-bold text-dark" style="font-size: 0.85rem;">
                                                    <i class="fas fa-sign-out-alt mr-1"></i> {{ $log['clock_out'] }}
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">Belum Melakukan Scan</span>
                                            @endif
                                        </td>
                                        {{-- PERBAIKAN (2026-11-03): kolom jadwal shift
                                             per tanggal. Menampilkan nama shift +
                                             jam kerja. Kalau karyawan tidak punya
                                             shift khusus tanggal itu, tampilkan
                                             "Normal" (jadwal default). --}}
                                        <td class="text-center">
                                            @if(!empty($log['shift_custom']))
                                                <span class="badge badge-primary px-2 py-1 font-weight-bold" style="font-size: 0.78rem;">
                                                    <i class="fas fa-calendar-alt mr-1"></i> {{ $log['shift_name'] }}
                                                </span>
                                                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                                    {{ substr($log['shift_work_start'], 0, 5) }} - {{ substr($log['shift_work_end'], 0, 5) }}
                                                </div>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1" style="font-size: 0.78rem;">
                                                    <i class="fas fa-calendar-alt mr-1"></i> Normal
                                                </span>
                                                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                                    {{ substr($log['shift_work_start'], 0, 5) }} - {{ substr($log['shift_work_end'], 0, 5) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @foreach($log['modes'] as $mode)
                                                <span class="badge badge-{{ $mode == 'Wajah' ? 'primary' : 'info' }} px-2 py-1 mr-1">
                                                    <i class="fas {{ $mode == 'Wajah' ? 'fa-user-check' : 'fa-fingerprint' }} mr-1"></i> {{ $mode }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_in'] && $log['clock_out'])
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> Lengkap</span>
                                            @elseif($log['clock_in'])
                                                <span class="badge badge-info px-2 py-1"><i class="fas fa-sign-in-alt mr-1"></i> Clock In Saja</span>
                                            @else
                                                <span class="badge badge-warning px-2 py-1"><i class="fas fa-sign-out-alt mr-1"></i> Clock Out Saja</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(!empty($log['face_details']))
                                                <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-sm px-3"
                                                        data-toggle="modal" data-target="#modalFaceDetail"
                                                        data-face-details="{{ json_encode($log['face_details'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                                                        data-date="{{ $log['date'] }}"
                                                        data-emp-name="{{ $log['employee_name'] ?? '' }}"
                                                        data-emp-npk="{{ $log['employee_npk'] ?? '' }}"
                                                        data-emp-dept="{{ $log['employee_dept'] ?? '' }}"
                                                        data-date-label="{{ $log['formatted_date'] }} ({{ $log['day_name'] }})">
                                                    <i class="fas fa-map-marker-alt mr-1"></i> Lihat
                                                </button>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                        {{-- Kosong: pesan empty-state sudah ditampilkan di atas tabel.
                                             Sengaja TIDAK ada <td> di sini supaya DataTables tidak
                                             menghitung jumlah sel tidak sesuai jumlah kolom. --}}
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Tampilan Mobile (Card List) -->
                        <div class="d-block d-md-none">
                            @forelse($groupedLogs as $log)
                            <div class="card mb-3 border-left-{{ ($log['clock_in'] && $log['clock_out']) ? 'success' : 'info' }} shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-gray-900">{{ $log['formatted_date'] }} ({{ $log['day_name'] }})</span>
                                        <span class="badge badge-{{ ($log['clock_in'] && $log['clock_out']) ? 'success' : 'info' }}">
                                            {{ ($log['clock_in'] && $log['clock_out']) ? 'Lengkap' : 'Partial' }}
                                        </span>
                                    </div>
                                    <div class="row text-center border-top border-bottom py-2 my-2">
                                        <div class="col-6 border-right">
                                            <small class="text-muted d-block">Jam Masuk</small>
                                            <strong class="text-success">{{ $log['clock_in'] ?? '-' }}</strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Jam Pulang</small>
                                            <strong class="text-warning">{{ $log['clock_out'] ?? '-' }}</strong>
                                        </div>
                                    </div>
                                    {{-- PERBAIKAN (2026-11-03): tampilkan jadwal shift
                                         juga di tampilan mobile, supaya konsisten
                                         dengan tabel desktop. --}}
                                    <div class="d-flex justify-content-between align-items-center small py-1 mb-1">
                                        <span class="text-muted">
                                            <i class="fas fa-calendar-alt text-primary mr-1"></i> Jadwal Shift
                                        </span>
                                        <span>
                                            <span class="badge badge-{{ !empty($log['shift_custom']) ? 'primary' : 'secondary' }} px-2 py-1">
                                                {{ $log['shift_name'] }}
                                            </span>
                                            <span class="text-muted ml-1">
                                                {{ substr($log['shift_work_start'], 0, 5) }} - {{ substr($log['shift_work_end'], 0, 5) }}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small text-muted">
                                        <span>Mode: {{ implode(', ', $log['modes']) }}</span>
                                        <span>Total Scan: {{ $log['total_scans'] }}x</span>
                                    </div>
                                    @if(!empty($log['face_details']))
                                    <div class="mt-2 pt-2 border-top">
                                        <button type="button" class="btn btn-sm btn-primary btn-block rounded-pill shadow-sm"
                                                data-toggle="modal" data-target="#modalFaceDetail"
                                                data-face-details="{{ json_encode($log['face_details'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                                                data-date="{{ $log['date'] }}"
                                                data-emp-name="{{ $log['employee_name'] ?? '' }}"
                                                data-emp-npk="{{ $log['employee_npk'] ?? '' }}"
                                                data-emp-dept="{{ $log['employee_dept'] ?? '' }}"
                                                data-date-label="{{ $log['formatted_date'] }} ({{ $log['day_name'] }})">
                                            <i class="fas fa-map-marker-alt mr-1"></i> Lihat Lokasi & Wajah
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <div class="text-center py-4 text-muted">
                                Belum ada data presensi.
                            </div>
                            @endforelse
                        </div>

                    </div>
                </div>

            </div>
        </div>

        @include('layout.footer')

<!-- Modal Detail Lokasi & Wajah -->
<div class="modal fade" id="modalFaceDetail" tabindex="-1" aria-labelledby="modalFaceDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="modalFaceDetailLabel">
                    <i class="fas fa-map-marker-alt mr-2"></i> Detail Presensi Wajah
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

                {{-- PERBAIKAN (2026-11-03): tata letak COMPACT, sama seperti
                     di admin_summary. Peta dan foto wajah dibuat
                     BERDAMPINGAN (kiri-kanan) dengan tinggi sama,
                     tabel riwayat dipindah ke bawah selebar penuh.
                     Sebelumnya peta menumpuk di atas wajah dalam kolom
                     sempit (kolom 5/12) sehingga banyak ruang kosong. --}}
                <div class="row">
                    <!-- Kiri: Minimap -->
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h6 class="font-weight-bold text-gray-800 mb-2">
                            <i class="fas fa-map text-primary mr-1"></i> Minimap Lokasi Absensi
                        </h6>
                        <div id="miniMapDetail"></div>
                        {{-- Baris "Koordinat: lat, lng" di bawah peta
                             dihapus atas permintaan user. Elemen
                             #detailCoords tetap ada (disembunyikan)
                             supaya script pengisi nilainya tidak error. --}}
                        <div class="d-none" aria-hidden="true">
                            <span id="detailCoords" class="d-none" aria-hidden="true">-</span>
                        </div>
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

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    $(document).ready(function() {
        // Inisialisasi hanya bila tabel punya baris data. Baris kosong dengan
        // colspan membuat DataTables menghitung jumlah sel tidak sesuai kolom
        // dan memunculkan warning "Incorrect column count".
        var $rekapTable = $('#tableRekapDesktop');
        if ($rekapTable.length === 0) return;

        var rekapRowCount = $rekapTable.find('tbody tr').length;
        if (rekapRowCount === 0) {
            $rekapTable.addClass('d-none');
            return;
        }

        $rekapTable.DataTable({
            "order": [[0, "asc"]],
            "pageLength": 25,
            "language": {
                "search": "Cari Presensi:",
                "lengthMenu": "Tampilkan _MENU_ data",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ hari",
                "infoEmpty": "Menampilkan 0 data",
                "zeroRecords": "Tidak ada data presensi yang sesuai",
                "paginate": {
                    "first": "Awal",
                    "last": "Akhir",
                    "next": "Berikutnya",
                    "previous": "Sebelumnya"
                }
            }
        });
    });

    // ===== Detail Lokasi & Wajah Presensi (Face Attendance) =====
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
                container.innerHTML = '<div id="faceImageEmpty" class="text-muted small py-4">' +
                    '<i class="fas fa-image fa-2x mb-2 d-block text-secondary"></i>' +
                    'Tidak ada gambar wajah tersimpan.</div>';
            } else {
                // PERBAIKAN V2 (2026-11-05): SEMUA foto wajah (clock in + clock
                // out) ditampilkan dengan ukuran sama dalam grid. Sebelumnya
                // hanya foto pertama yang besar, sisanya thumbnail 44px.
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

            // Daftar seluruh scan -> disusun sebagai tabel rapi & mudah dibaca.
            // PERBAIKAN (2026-11-03): koordinat mentah (lat, lng) TIDAK
            // ditampilkan. Peta di atas sudah menunjukkan posisi. Akurasi
            // GPS tetap tampil sebagai indikator kualitas sinyal.
            if (details.length === 0) {
                listContainer.innerHTML =
                    '<div class="text-muted small text-center py-3">' +
                    '<i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>' +
                    'Belum ada riwayat scan pada hari ini.</div>';
                return;
            }

            var rowsHtml = '';
            details.forEach(function(d, i) {
                // PERBAIKAN (2026-11-03): data dari atribut tombol
                // (data-face-details) bisa TIDAK punya field mode_label,
                // dan dulu itu membuat semuanya berlabel "Fingerprint".
                // Data di modal ini selalu berasal dari presensi wajah,
                // jadi default-nya 'Wajah' kecuali server menyatakan lain
                // secara eksplisit.
                var isFace = (d.mode_label ? d.mode_label === 'Wajah' : true);

                // PERBAIKAN (2026-11-03): kolom Lokasi menampilkan nama area
                // kerja, bukan koordinat mentah.
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
                var modeBadge = isFace
                    ? '<span class="badge badge-primary"><i class="fas fa-user-check mr-1"></i>Wajah</span>'
                    : '<span class="badge badge-info"><i class="fas fa-fingerprint mr-1"></i>Fingerprint</span>';
                var accBadge = (acc === '-')
                    ? '<span class="text-muted">-</span>'
                    : '<span class="badge badge-light border text-dark">' + acc + '</span>';

                // PERBAIKAN V2 (2026-11-05): kolom Koordinat Attendance.
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

                    const isActive = loc.is_active !== false;
                    const isMatched = matchedLoc && matchedLoc.name === loc.name;
                    const color = isMatched ? '#1cc88a' : (isActive ? '#4e73df' : '#858796');
                    const fillOpacity = isMatched ? 0.25 : (isActive ? 0.15 : 0.08);

                    const layer = L.polygon(latLngs, {
                        color: color,
                        weight: isMatched ? 3 : 2,
                        fillColor: color,
                        fillOpacity: fillOpacity
                    });

                    layer.bindPopup(
                        '<strong>' + (loc.name || 'Lokasi Kerja') + '</strong><br>' +
                        (loc.description ? loc.description + '<br>' : '') +
                        'Status: ' + (isActive ? 'Aktif' : 'Non-aktif')
                    );
                    layer.addTo(detailPolyGroup);
                });
            }

            // PERBAIKAN V2 (2026-11-05): minimap menampilkan SEMUA scan
            // face attendance pada hari tersebut (clock in + clock out),
            // bukan hanya scan pertama. Warna marker: hijau = Clock In,
            // merah = Clock Out. Garis putus-putus menghubungkan titik-titiknya.
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
                if (markerIdx === 0) {
                    detailMarker = mk;
                }
                markerIdx++;
            });

            if (scanPts.length >= 2) {
                L.polyline(scanPts, {
                    color: '#4e73df',
                    weight: 2,
                    opacity: 0.55,
                    dashArray: '6,6'
                }).addTo(detailMap);
            }

            // Circle radius akurasi GPS untuk scan pertama (acuan).
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

        $(document).on('click', '[data-face-details]', function() {
            const $btn = $(this);
            const raw = $btn.attr('data-face-details');
            const dateLabel = $btn.attr('data-date-label') || '-';
            const date = $btn.attr('data-date') || '';
            let details = [];
            try {
                details = raw ? JSON.parse(raw) : [];
            } catch (e) {
                details = [];
            }

            document.getElementById('detailDateLabel').textContent = dateLabel;

            // PERBAIKAN (2026-11-03): isi identitas karyawan dari atribut
            // tombol. Sebelumnya #detailEmployeeName / #detailEmployeeNpk /
            // #detailEmployeeDept tidak pernah diisi siapa pun sehingga modal
            // selalu menampilkan "-" (nama & NPK & departemen tidak ada).
            const empName = $btn.attr('data-emp-name') || '';
            const empNpk  = $btn.attr('data-emp-npk') || '';
            const empDept = $btn.attr('data-emp-dept') || '';
            document.getElementById('detailEmployeeName').textContent = empName || '-';
            document.getElementById('detailEmployeeNpk').textContent  = empNpk || '-';
            document.getElementById('detailEmployeeDept').textContent = empDept || '-';

            // Refresh dari server (penting agar gambar/coordinat terbaru & tidak reliant cache HTML)
            if (date) {
                fetch("{{ url('/face-attendance/detail') }}/" + encodeURIComponent(date), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.success && Array.isArray(data.details)) {
                        applyFaceDetails(data.details, dateLabel);
                    } else {
                        applyFaceDetails(details, dateLabel);
                    }
                })
                .catch(function() {
                    applyFaceDetails(details, dateLabel);
                });
            } else {
                applyFaceDetails(details, dateLabel);
            }
        });

        function applyFaceDetails(details, dateLabel) {
            document.getElementById('detailDateLabel').textContent = dateLabel;

            if (!details || !details.length) {
                document.getElementById('detailLocationName').textContent = '-';
                document.getElementById('detailLocationSub').textContent = '-';
                document.getElementById('detailCoords').textContent = '-';
                renderFaceImages(details || []);
                destroyMap();
                return;
            }

            const first = details[0];
            const lat = parseFloat(first.latitude);
            const lng = parseFloat(first.longitude);

            // PERBAIKAN (2026-11-03): nama lokasi kerja.
            //  - first.location_name bisa berupa koordinat mentah (fallback
            //    dari FaceAttendanceLog::locationName()).
            //  - location_resolved = hasil pencocokan point-in-polygon.
            const resolved = first.location_resolved || '';
            const rawName = first.location_name || '';
            const isCoordText = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(rawName.trim());
            const matched = (!isNaN(lat) && !isNaN(lng)) ? findLocationByCoords(workLocations, lat, lng) : null;
            const finalName = resolved
                || (rawName && !isCoordText ? rawName : '')
                || (matched ? matched.name : '')
                || '-';

            document.getElementById('detailLocationName').textContent = finalName;

            const acc = parseFloat(first.accuracy);
            const accText = (!isNaN(acc) && acc > 0) ? 'Akurasi GPS ±' + Math.round(acc) + ' m' : 'Akurasi GPS tidak tersedia';
            const statusText = matched
                ? (matched.is_active !== false ? 'Polygon aktif' : 'Polygon non-aktif')
                : 'Di luar polygon lokasi kerja';
            document.getElementById('detailLocationSub').textContent = accText + ' · ' + statusText;

            // PERBAIKAN (2026-11-03): #detailCoords tidak lagi diisi
            // koordinat mentah, hanya penanda (elemen tetap d-none).
            document.getElementById('detailCoords').textContent = 'tersembunyi';

            renderFaceImages(details);

            if (!isNaN(lat) && !isNaN(lng)) {
                // PERBAIKAN V2 (2026-11-05): kirim SELURUH scan ke minimap
                // supaya semua titik absensi hari itu tampil (clock in + out).
                initMap(lat, lng, parseFloat(first.accuracy), workLocations, details);
            } else {
                destroyMap();
            }
        }

        // PERBAIKAN (2026-11-03): helper point-in-polygon versi client untuk
        // mencari polygon lokasi kerja yang memuat koordinat absensi.
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

        // PERBAIKAN (2026-11-03): Leaflet menghitung ukuran container saat
        // peta dibuat. Karena peta dibuat ketika modal belum sepenuhnya
        // tampil, ukurannya bisa terbaca 0x0 sehingga tile dan POLYGON
        // tidak pernah tergambar (keluhan "polygon GPS tidak ada").
        // invalidateSize() setelah modal benar-benar tampil memaksa Leaflet
        // menghitung ulang dan menggambar seluruh layer.
        $('#modalFaceDetail').on('shown.bs.modal', function() {
            if (detailMap) {
                detailMap.invalidateSize();
                // Fit ulang ke seluruh polygon + marker yang sudah digambar,
                // supaya polygon tidak berada di luar area pandang.
                try {
                    const layers = [];
                    if (detailPolyGroup) {
                        detailPolyGroup.eachLayer(function(l) {
                            if (typeof l.getBounds === 'function') layers.push(l);
                        });
                    }
                    if (detailMarker) layers.push(detailMarker);

                    if (layers.length > 1) {
                        const group = L.featureGroup(layers);
                        detailMap.fitBounds(group.getBounds().pad(0.25));
                    }
                } catch (e) {
                    // Diamkan: peta tetap tampil walau fit bounds gagal.
                }
            }
            // Ulangi sekali lagi setelah transisi CSS selesai.
            setTimeout(function() {
                if (detailMap) detailMap.invalidateSize();
            }, 350);
        });
    })();
</script>

</body>
</html>
