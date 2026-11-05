<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Custom Styles Face Attendance -->
<style>
    .scanner-guide-box {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 240px;
        height: 280px;
        border: 2px dashed rgba(255, 255, 255, 0.7);
        border-radius: 50%;
        pointer-events: none;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
        transition: all 0.3s ease;
    }
    .scanner-guide-box.liveness-ok {
        border-color: #1cc88a;
        box-shadow: 0 0 20px #1cc88a, 0 0 0 9999px rgba(0, 0, 0, 0.25);
    }
    .liveness-alert-box {
        font-size: 1.05rem;
        letter-spacing: 0.3px;
        /* PERBAIKAN V31.3: zoom teks dihapus. Animasi dipindah ke wajah SVG
           di bawah supaya user melihat GERAKAN yang harus ditiru, bukan
           teks yang membesar-mengecil. */
    }

    /* ==========================================================
       PERBAIKAN V34 (2026-11-05) — CARD STATUS PRESENSI
       ----------------------------------------------------------
       Card status presensi (ganti 2 card lama) dipindah ke ATAS
       card "Data Karyawan". Gaya mengikuti desain mint-green:
         - latar card   : hijau muda/mint
         - aksen kiri   : garis vertikal hijau
         - ikon         : lingkaran hijau + ikon putih
       Varian warna per status:
         success (lengkap) / info (baru clock in) /
         warning (hanya clock out) / secondary (belum clock in)
       ========================================================== */
    .fa-status-card {
        border: 0;
        border-left: 6px solid #1cc88a;
        background-color: #e8f8f0;
        overflow: hidden;
    }
    .fa-status-card .fa-status-icon {
        width: 52px;
        height: 52px;
        flex: 0 0 52px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #1cc88a;
        color: #ffffff;
        font-size: 1.35rem;
        box-shadow: 0 3px 10px rgba(28, 200, 138, 0.35);
    }
    .fa-status-card .fa-status-label {
        color: #0f8f5f;
        letter-spacing: 0.6px;
    }
    .fa-status-card .fa-status-title {
        color: #14663f;
    }
    .fa-status-card .fa-status-desc {
        color: #4a5a52;
    }

    /* Varian per status */
    .fa-status-card.fa-status-info {
        border-left-color: #36b9cc;
        background-color: #e4f7fa;
    }
    .fa-status-card.fa-status-info .fa-status-icon {
        background-color: #36b9cc;
        box-shadow: 0 3px 10px rgba(54, 185, 204, 0.35);
    }
    .fa-status-card.fa-status-info .fa-status-label { color: #127a86; }
    .fa-status-card.fa-status-info .fa-status-title { color: #0d5a63; }

    .fa-status-card.fa-status-warning {
        border-left-color: #f6c23e;
        background-color: #fff6e0;
    }
    .fa-status-card.fa-status-warning .fa-status-icon {
        background-color: #f6c23e;
        color: #6b4b00;
        box-shadow: 0 3px 10px rgba(246, 194, 62, 0.35);
    }
    .fa-status-card.fa-status-warning .fa-status-label { color: #a5791a; }
    .fa-status-card.fa-status-warning .fa-status-title { color: #7a5600; }

    .fa-status-card.fa-status-secondary {
        border-left-color: #8a94a6;
        background-color: #f1f3f7;
    }
    .fa-status-card.fa-status-secondary .fa-status-icon {
        background-color: #8a94a6;
        box-shadow: 0 3px 10px rgba(138, 148, 166, 0.35);
    }
    .fa-status-card.fa-status-secondary .fa-status-label { color: #5c6577; }
    .fa-status-card.fa-status-secondary .fa-status-title { color: #3d4454; }

    /* PERBAIKAN V35 (2026-11-05): varian "expired" (window Clock Out lewat) */
    .fa-status-card.fa-status-danger {
        border-left-color: #dc3545;
        background-color: #fdecee;
    }
    .fa-status-card.fa-status-danger .fa-status-icon {
        background-color: #dc3545;
        box-shadow: 0 3px 10px rgba(220, 53, 69, 0.35);
    }
    .fa-status-card.fa-status-danger .fa-status-label { color: #a52331; }
    .fa-status-card.fa-status-danger .fa-status-title { color: #721c24; }

    @media (max-width: 575.98px) {
        .fa-status-card .fa-status-icon {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            font-size: 1.15rem;
        }
        .fa-status-card .fa-status-title {
            font-size: 1.05rem;
        }
    }

    /* ==========================================================
       PERBAIKAN V34 (2026-11-05) — PANEL KAMERA TANPA CARD
       ----------------------------------------------------------
       Card kamera ("Kamera Presensi: ..." + badge "Kamera
       Otomatis") dihapus. Isi kamera (video, banner window,
       liveness) tetap dirender, hanya tanpa pembungkus card.
       ========================================================== */
    .fa-camera-panel {
        position: relative;
    }

    /* ==========================================================
       PERBAIKAN V35 (2026-11-05) — PANEL KAMERA DISEMBUNYIKAN
       ----------------------------------------------------------
       Saat window absensi sudah lewat ('expired') atau belum dibuka
       ('waiting'), area kamera gelap tidak boleh tampil. Elemen DOM
       di dalamnya tetap ada (display:none, bukan dihapus) supaya
       seluruh JS (startCamera, detectFaceLoop, evaluateCameraGate,
       captureFaceImage, liveness) tetap berjalan normal.
       ========================================================== */
    .fa-camera-hidden {
        display: none !important;
    }

    /* ==========================================================
       PERBAIKAN V31.3b (2026-11-04) — CSS WAJAH DIPINDAH KE PARTIAL
       ----------------------------------------------------------
       Blok CSS wajah animasi (dulu .liveness-face-*, .liveness-eye-lid,
       .liveness-hint-bergantian) DIHAPUS dari file ini dan diganti
       partial face_attendance._liveness_face.

       Dua alasan:
       1. BUG TUMPUKAN KATA — .liveness-hint-bergantian menumpuk dua
          <span> di satu grid-area sehingga teks saling menimpa dan
          tidak terbaca (dilaporkan lewat screenshot).
       2. Halaman login tidak punya animasi sama sekali, jadi harus
          ada satu sumber agar tidak pernah berbeda lagi.
       ========================================================== */
    /* ==========================================================
       PERBAIKAN 2026-11-04 - MOBILE FRIENDLY /face-attendance
       ----------------------------------------------------------
       Sidebar + navbar dihapus. CSS di bawah memastikan konten
       memakai SELURUH lebar layar (tidak ada offset sidebar)
       dan tidak ada ruang kosong sisa navbar di atas.
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

    /* ==========================================================
       PERBAIKAN V32.9 (2026-11-05) — REDESIGN MOBILE FRIENDLY
       ----------------------------------------------------------
       1. Card Kamera Presensi (kolom kanan / cameraCardBody) dihapus
          dari tampilan -> kolom Data Karyawan jadi full width.
       2. Mini map dibuat lebih tinggi di layar kecil supaya marker
          (Clock In + Clock Out) tetap jelas terlihat.
       3. Tabel Riwayat Scan dibungkus .table-responsive (sudah ada)
          dan font mengecil di layar kecil agar tidak memaksa
          scroll horizontal terlalu jauh.
       4. Blok Wajah Tercapture memakai grid responsif.
       ========================================================== */
    @media (max-width: 767.98px) {
        #miniMapProfile {
            height: 240px !important;
        }
        .fa-scan-history-table {
            font-size: 0.68rem !important;
        }
        .fa-scan-history-table th,
        .fa-scan-history-table td {
            padding: 0.3rem 0.25rem !important;
        }
        .fa-face-capture-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(104px, 1fr));
            gap: 10px !important;
        }
        .fa-face-capture-grid > div {
            width: auto !important;
        }
        .fa-face-capture-grid img {
            width: 100% !important;
            height: 130px !important;
        }
    }
    @media (max-width: 575.98px) {
        h1.h4 {
            font-size: 1.15rem;
        }
        .fa-face-capture-grid img {
            height: 112px !important;
        }
    }

    /* ==========================================================
       PERBAIKAN V33 (2026-11-05) — DESKTOP TERLALU LEBAR
       ----------------------------------------------------------
       Gejala: di mode desktop konten melebar sampai tepi layar
       (100% viewport) sehingga tabel & kartu terasa "terjau" dan
       sulit dibaca, sementara di mobile sudah pas (karena layar kecil).

       Solusi: batasi lebar konten dengan max-width yang wajar dan
       pusatkan (margin auto). Kolom yang SEMESTI penuh (tabel
       Riwayat Scan, Wajah Tercapture) tetap memakai lebar penuh
       di dalam container yang sudah dibatasi.

       1320px ~= lebar laptop 1440px dikurangi margin nyaman.
       Breakpoint 1199.98px = di bawah itu layar dianggap tablet/
       mobile, widths dibiarkan 100% (tidak ada letterbox).
       ========================================================== */
    @media (min-width: 1200px) {
        #content > .container-fluid {
            max-width: 1320px;
            margin-left: auto;
            margin-right: auto;
        }
        /* Kartu utama: batasi tinggi/panjang isi agar tidak terlalu
           renggang di monitor lebar. */
        #content > .container-fluid > .row > div > .card .card-body {
            max-width: 100%;
        }
    }
    /* Cegah tabel & grid meluber melewati viewport di desktop sempit
       (1024-1199px) yang biasanya jadi horizontally scroll. */
    @media (max-width: 1199.98px) {
        #content > .container-fluid {
            overflow-x: hidden;
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }
</style>
{{-- SweetAlert seragam untuk karyawan sudah resign (PKWT.TKK terisi) --}}
@include('partials.sweetalert-resign')

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    {{-- PERBAIKAN 2026-11-04: sidebar dihapus agar halaman /face-attendance
         menjadi mobile friendly (dulu FULL oleh navbar + sidebar). --}}

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            {{-- PERBAIKAN 2026-11-04: navbar dihapus demi tampilan mobile. --}}

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h4 h3-md mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-camera text-primary mr-2"></i> Presensi Wajah & GPS
                    </h1>
                    <div class="mt-2 mt-sm-0 d-flex flex-wrap">
                        <a href="{{ route('face.attendance.rekap') }}" class="btn btn-sm btn-info shadow-sm rounded-pill px-3 mr-2 mb-1">
                            <i class="fas fa-history fa-sm text-white-50 mr-1"></i> Rekap Presensi
                        </a>
                        <form action="{{ route('face.attendance.logout-session') }}" method="POST" class="d-inline">
                            @csrf
                            {{-- PERBAIKAN V32.9 (2026-11-05): label diubah dari
                                 "Ganti Karyawan" menjadi "Keluar". --}}
                            <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm rounded-pill px-3 mb-1">
                                <i class="fas fa-sign-out-alt fa-sm mr-1"></i> Keluar
                            </button>
                        </form>
                    </div>
                </div>

                {{-- PERBAIKAN V34 (2026-11-05): Alert "STATUS PRESENSI HARI INI"
                     (gambar ke-2 dari user) DIHAPUS dari sini. Informasi status
                     tersebut kini ter-cover penuh oleh "Card Status Presensi"
                     di bawah yang sudah dipindahkan ke ATAS card "Data Karyawan".
                     --}}

                {{-- ==========================================================
                     CARD STATUS PRESENSI (V34 - 2026-11-05)
                     ----------------------------------------------------------
                     Dipindahkan ke ATAS card "Data Karyawan".
                     Menggantikan 2 card lama:
                       1. Alert "STATUS PRESENSI HARI INI"  -> dihapus
                       2. Card "Presensi Hari Ini Selesai"  -> jadi card ini
                     3 varian status:
                       - Lengkap  : Clock In + Clock Out
                       - Baru In  : baru Clock In, menunggu window Clock Out
                       - Lainnya  : belum / hanya Clock Out / hanya Clock In
                     ========================================================== --}}
                @php
                    $faLengkap   = ($hasClockIn && $hasClockOut);
                    $faBaruIn    = ($hasClockIn && !$hasClockOut && $nextAction !== 'expired');
                    $faHanyaOut  = (!$hasClockIn && $hasClockOut);
                    $faExpired   = ($nextAction === 'expired');

                    if ($faLengkap) {
                        $faWarna = 'success';
                        $faJudul = 'Presensi Hari Ini Selesai';
                        $faIkon  = 'fa-check-double';
                        $faPesan = 'Anda sudah melakukan Clock In dan Clock Out untuk hari ini. Terima kasih atas kerja keras Anda!';
                        $faDetail = $statusText;
                    } elseif ($faExpired) {
                        // PERBAIKAN V35 (2026-11-05): window Clock Out sudah lewat
                        // -> status harus eksplisit menyatakan tidak bisa presensi.
                        $faWarna = 'danger';
                        $faJudul = 'Waktu Clock Out Sudah Lewat, Anda Tidak Bisa Melakukan Presensi';
                        $faIkon  = 'fa-ban';
                        $faPesan = 'Window Clock Out (' . $clockOutStart->format('H:i') . '-' . $clockOutEnd->format('H:i') . ') WIB sudah lewat. '
                            . 'Presensi tidak dapat dilakukan sampai window berikutnya. Silakan hubungi HR untuk keterlambatan.';
                        $faDetail = $statusText;
                    } elseif ($faBaruIn) {
                        $faWarna = 'info';
                        $faJudul = 'Baru Clock In, Menunggu Window Clock Out';
                        $faIkon  = 'fa-sign-in-alt';
                        $faPesan = 'Clock In Anda sudah tercatat. Silakan lakukan Clock Out saat jendela waktu Clock Out dibuka.';
                        $faDetail = $statusText;
                    } elseif ($faHanyaOut) {
                        $faWarna = 'warning';
                        $faJudul = 'Sudah Clock Out';
                        $faIkon  = 'fa-sign-out-alt';
                        $faPesan = 'Clock Out Anda sudah tercatat. Anda belum melakukan Clock In hari ini.';
                        $faDetail = $statusText;
                    } else {
                        $faWarna = 'secondary';
                        $faJudul = 'Belum Clock In';
                        $faIkon  = 'fa-clock';
                        $faPesan = 'Anda belum melakukan Clock In hari ini. Silakan scan wajah saat jendela waktu Clock In dibuka.';
                        $faDetail = $statusText;
                    }
                @endphp

                <div class="card shadow mb-4 fa-status-card fa-status-{{ $faWarna }}">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between">
                            {{-- Blok kiri: ikon + judul status --}}
                            <div class="d-flex align-items-center mb-3 mb-sm-0">
                                <div class="fa-status-icon mr-3">
                                    <i class="fas {{ $faIkon }}"></i>
                                </div>
                                <div>
                                    <span class="font-weight-bold text-uppercase d-block small fa-status-label">Status Presensi Hari Ini</span>
                                    <h5 class="mb-0 font-weight-bold fa-status-title">{{ $faJudul }}</h5>
                                    @if(!empty($faDetail))
                                    <span class="font-weight-normal text-muted small ml-1">({{ $faDetail }})</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Blok kanan: badge tanggal + tombol rekap --}}
                            <div class="text-left text-sm-right d-flex flex-column flex-sm-row align-items-sm-center">
                                <span class="badge badge-primary px-3 py-2 font-weight-bold mb-2 mb-sm-0 mr-sm-3" style="font-size: 0.9rem;">
                                    {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}
                                </span>
                                <a href="{{ route('face.attendance.rekap') }}" class="btn btn-primary rounded-pill px-4">
                                    <i class="fas fa-history mr-1"></i> Lihat Rekap Bulanan
                                </a>
                            </div>
                        </div>

                        <p class="text-muted mb-0 mt-3 fa-status-desc">{{ $faPesan }}</p>
                    </div>
                </div>

                <div class="row">
                    <!-- Kolom Informasi Karyawan & Jadwal Shift -->
                    {{-- PERBAIKAN V32.9 (2026-11-05): kolom kiri menjadi FULL
                         WIDTH karena kolom kanan (card kamera) dihapus. --}}
                    <div class="col-12 mb-4">
                        <div class="card shadow border-left-primary h-100">
                            <div class="card-header py-3 bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user-circle mr-1"></i> Data Karyawan
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    {{-- Foto karyawan diambil dari PKWT.file_pas_foto.
                                         Bila tidak ada / file hilang, tampilkan inisial huruf. --}}
                                    @if(!empty($employeeFaceImg))
                                        <img src="{{ $employeeFaceImg }}"
                                             alt="Foto {{ $biodata->NAMA_KARYAWAN }}"
                                             class="mx-auto mb-2 rounded-circle shadow-sm d-block"
                                             style="width: 70px; height: 70px; object-fit: cover; object-position: center; border: 2px solid #e3e6f0; background:#f8f9fc;"
                                             onerror="this.style.display='none'; var i=this.nextElementSibling; if(i){i.style.display='flex';}">
                                        <div class="avatar-circle mx-auto mb-2 bg-gradient-primary text-white align-items-center justify-content-center rounded-circle"
                                             style="width: 70px; height: 70px; font-size: 1.8rem; font-weight: bold; display: none;">
                                            {{ strtoupper(substr($biodata->NAMA_KARYAWAN ?? 'U', 0, 1)) }}
                                        </div>
                                    @else
                                        <div class="avatar-circle mx-auto mb-2 bg-gradient-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 70px; height: 70px; font-size: 1.8rem; font-weight: bold;">
                                            {{ strtoupper(substr($biodata->NAMA_KARYAWAN ?? 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                    <h5 class="font-weight-bold text-gray-900 mb-0">{{ $biodata->NAMA_KARYAWAN }}</h5>
                                    <span class="badge badge-light border text-muted">NPK: {{ $biodata->NPK }}</span>
                                </div>

                                <hr class="my-3">

                                <div class="mb-2">
                                    <small class="text-muted font-weight-bold text-uppercase d-block">Department</small>
                                    <span class="text-gray-800 font-weight-bold">{{ $biodata->DEPT ?? '-' }}</span>
                                </div>

                                {{-- ============================================================
                                     PERBAIKAN (2026-11-03): tampilan jadwal shift & window
                                     absensi dirapikan supaya mudah dibaca.
                                     Previously Shift, Clock In, and Clock Out
                                     ditumpuk sebagai label kecil + badge, sehingga
                                     jam window (mis. 07:00-09:00 WIB) terlihat
                                     padat dan sulit dipindai. Sekarang jadi
                                     "kartu jadwal" dengan blok jam besar, status
                                     aktif, dan penanda singkat.
                                     ============================================================ --}}
                                <div class="card border-0 shadow-sm mt-3 mb-3" style="background:#f8f9fc;">
                                    <div class="card-body p-3">

                                        {{-- Jadwal Shift --}}
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <small class="text-muted font-weight-bold text-uppercase mb-0">
                                                <i class="fas fa-calendar-alt text-primary mr-1"></i> Jadwal Shift
                                            </small>
                                            @if($shift)
                                                <span class="badge badge-primary px-2 py-1 font-weight-bold">
                                                    {{ $shift->shift_name }}
                                                </span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1 font-weight-bold">
                                                    Normal
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-center py-2 mb-3" style="background:#fff; border-radius: .35rem; border: 1px solid #e3e6f0;">
                                            <div class="text-gray-800 font-weight-bold" style="font-size: 1.15rem; letter-spacing: .5px;">
                                                {{ substr($workStart, 0, 5) }} <span class="text-muted" style="font-size: .9rem;">-</span> {{ substr($workEnd, 0, 5) }}
                                            </div>
                                            <small class="text-muted d-block" style="font-size: .72rem; letter-spacing: .3px;">
                                                JAM KERJA
                                            </small>
                                        </div>

                                        {{-- Window Absensi --}}
                                        <small class="text-muted font-weight-bold text-uppercase d-block mb-2">
                                            <i class="fas fa-clock text-primary mr-1"></i> Window Absensi
                                        </small>

                                        <div class="d-flex align-items-stretch mb-2">
                                            <div class="flex-fill text-center px-2 py-2" style="background:#fff; border-radius: .35rem 0 0 .35rem; border: 1px solid #e3e6f0; border-right: none;">
                                                <small class="d-block font-weight-bold text-success" style="font-size: .72rem; letter-spacing: .3px;">
                                                    <i class="fas fa-sign-in-alt mr-1"></i> CLOCK IN
                                                </small>
                                                <span class="d-block text-gray-900 font-weight-bold" style="font-size: .95rem;">
                                                    {{ $clockInStart->format('H:i') }} - {{ $clockInEnd->format('H:i') }}
                                                </span>
                                                <small class="d-block text-muted" style="font-size: .68rem;">WIB</small>
                                            </div>
                                            <div class="flex-fill text-center px-2 py-2" style="background:#fff; border-radius: 0 .35rem .35rem 0; border: 1px solid #e3e6f0;">
                                                <small class="d-block font-weight-bold text-warning" style="font-size: .72rem; letter-spacing: .3px;">
                                                    <i class="fas fa-sign-out-alt mr-1"></i> CLOCK OUT
                                                </small>
                                                <span class="d-block text-gray-900 font-weight-bold" style="font-size: .95rem;">
                                                    {{ $clockOutStart->format('H:i') }} - {{ $clockOutEnd->format('H:i') }}
                                                </span>
                                                <small class="d-block text-muted" style="font-size: .68rem;">WIB</small>
                                            </div>
                                        </div>

                                        {{-- Status scan hari ini --}}
                                        <div class="d-flex align-items-center justify-content-between px-2 py-2" style="background:#fff; border-radius: .35rem; border: 1px solid #e3e6f0;">
                                            <span class="small text-gray-700">
                                                <i class="fas fa-clipboard-check text-gray-500 mr-1"></i> Status Hari Ini
                                            </span>
                                            <span>
                                                @if($hasClockIn)
                                                    <span class="badge badge-success px-2 py-1" title="Clock In {{ $clockInTime }}">
                                                        <i class="fas fa-check-circle mr-1"></i> In {{ $clockInTime }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-light border text-muted px-2 py-1">Belum Clock In</span>
                                                @endif
                                                @if($hasClockOut)
                                                    <span class="badge badge-warning px-2 py-1 text-dark" title="Clock Out {{ $clockOutTime }}">
                                                        <i class="fas fa-check-circle mr-1"></i> Out {{ $clockOutTime }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-light border text-muted px-2 py-1">Belum Clock Out</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                @if(count($todayLogs) > 0)
                                <div class="mt-3">
                                    <small class="text-muted font-weight-bold text-uppercase d-block mb-2">Riwayat Scan Hari Ini</small>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0 fa-scan-history-table" style="font-size: 0.8rem;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width:38px;" class="text-center">#</th>
                                                    <th style="width:80px;">Jam</th>
                                                    <th style="width:100px;" class="text-center">Metode</th>
                                                    <th class="text-center">Keterangan</th>
                                                    {{-- PERBAIKAN V32.8 (2026-11-05): tampilkan koordinat
                                                         attendance (lat/long + akurasi) dan wajah yang
                                                         TERCAPTURE saat scan, bukan cuma foto profil. --}}
                                                    <th style="width:110px;" class="text-center">Koordinat</th>
                                                    <th style="width:90px;" class="text-center">Wajah Tercapture</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($todayLogs as $i => $log)
                                                @php
                                                    $logType = $log->calculated_type ?? ($log->inoutmode == 0 ? 'in' : 'out');
                                                    $hasLat  = ($log->latitude !== null && $log->latitude !== '');
                                                    $hasLng  = ($log->longitude !== null && $log->longitude !== '');
                                                    $faceB64 = $log->face_image ?? null;
                                                @endphp
                                                <tr>
                                                    <td class="text-center text-muted align-middle">{{ $i + 1 }}</td>
                                                    <td class="align-middle font-weight-bold">
                                                        {{ \Carbon\Carbon::parse($log->scan_date)->format('H:i:s') }}
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        @if($log->verifymode == 15)
                                                            <span class="badge badge-primary"><i class="fas fa-user-check mr-1"></i>Wajah</span>
                                                        @else
                                                            <span class="badge badge-info"><i class="fas fa-fingerprint mr-1"></i>Finger</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <span class="badge badge-{{ $logType == 'in' ? 'success' : 'warning' }}">
                                                            <i class="fas {{ $logType == 'in' ? 'fa-sign-in-alt' : 'fa-sign-out-alt' }} mr-1"></i>
                                                            {{ $logType == 'in' ? 'Clock In' : 'Clock Out' }}
                                                        </span>
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        @if($hasLat && $hasLng)
                                                            <div class="font-weight-bold" style="font-size:.72rem;line-height:1.25;">
                                                                {{ number_format((float) $log->latitude, 6) }},<br>
                                                                {{ number_format((float) $log->longitude, 6) }}
                                                            </div>
                                                            <div class="text-muted" style="font-size:.65rem;">
                                                                <i class="fas fa-crosshairs mr-1"></i>±{{ $log->accuracy !== null ? round((float) $log->accuracy) : '-' }} m
                                                            </div>
                                                            @if($log->inside_polygon !== null)
                                                                <span class="badge badge-{{ ((int) $log->inside_polygon) === 1 ? 'success' : 'danger' }} mt-1" style="font-size:.58rem;">
                                                                    {{ ((int) $log->inside_polygon) === 1 ? 'Dalam Area' : 'Di Luar Area' }}
                                                                </span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted" style="font-size:.7rem;">
                                                                <i class="fas fa-minus mr-1"></i>Tidak ada
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="align-middle text-center">
                                                        @if(!empty($faceB64))
                                                            <a href="javascript:void(0)" class="fa-scan-thumb"
                                                               data-img="data:image/jpeg;base64,{{ $faceB64 }}"
                                                               data-type="{{ $logType == 'in' ? 'Clock In' : 'Clock Out' }}"
                                                               data-time="{{ \Carbon\Carbon::parse($log->scan_date)->format('H:i:s') }}"
                                                               title="Lihat wajah {{ $logType == 'in' ? 'Clock In' : 'Clock Out' }}">
                                                                <img src="data:image/jpeg;base64,{{ $faceB64 }}"
                                                                     alt="Wajah {{ $logType == 'in' ? 'Clock In' : 'Clock Out' }}"
                                                                     style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:2px solid {{ $logType == 'in' ? '#1cc88a' : '#f6c23e' }};cursor:pointer;">
                                                            </a>
                                                        @else
                                                            <span class="text-muted" style="font-size:.7rem;">
                                                                <i class="fas fa-user-slash mr-1"></i>Tidak ada
                                                            </span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @endif

                                {{-- PERBAIKAN V32.8 (2026-11-05): tampilkan foto wajah yang
                                     TERCAPTURE pada setiap scan (clock in & clock out),
                                     masing-masing dengan jam + koordinat. Sebelumnya
                                     hanya ada foto profil kecil di atas, sehingga
                                     wajah hasil scan tidak pernah terlihat. --}}
                                @php
                                    $faceCaptures = [];
                                    foreach ($todayLogs as $log) {
                                        if (!empty($log->face_image)) {
                                            $faceCaptures[] = [
                                                'type' => ($log->calculated_type ?? 'in') === 'in' ? 'Clock In' : 'Clock Out',
                                                'time' => \Carbon\Carbon::parse($log->scan_date)->format('H:i:s'),
                                                'img'  => $log->face_image,
                                                'lat'  => $log->latitude,
                                                'lng'  => $log->longitude,
                                                'acc'  => $log->accuracy,
                                            ];
                                        }
                                    }
                                @endphp
                                @if(count($faceCaptures) > 0)
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted font-weight-bold text-uppercase d-block mb-2">
                                        <i class="fas fa-camera text-primary mr-1"></i> Wajah Tercapture ({{ count($faceCaptures) }})
                                    </small>
                                    <div class="d-flex flex-wrap fa-face-capture-grid" style="gap:10px;">
                                        @foreach($faceCaptures as $fc)
                                        <div class="text-center" style="width:132px;">
                                            <img src="data:image/jpeg;base64,{{ $fc['img'] }}"
                                                 alt="Wajah {{ $fc['type'] }}"
                                                 class="rounded shadow-sm"
                                                 style="width:132px;height:165px;object-fit:cover;object-position:center top;border:2px solid {{ $fc['type'] === 'Clock In' ? '#1cc88a' : '#f6c23e' }};">
                                            <div class="badge badge-{{ $fc['type'] === 'Clock In' ? 'success' : 'warning' }} mt-1" style="font-size:.62rem;">
                                                <i class="fas {{ $fc['type'] === 'Clock In' ? 'fa-sign-in-alt' : 'fa-sign-out-alt' }} mr-1"></i>
                                                {{ $fc['type'] }} {{ $fc['time'] }}
                                            </div>
                                            @if($fc['lat'] !== null && $fc['lng'] !== null)
                                            <div class="text-muted" style="font-size:.6rem;line-height:1.2;">
                                                {{ number_format((float) $fc['lat'], 5) }}, {{ number_format((float) $fc['lng'], 5) }}
                                            </div>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <!-- Mini Map Geofencing Lokasi Karyawan -->
                                {{-- ============================================================
                                     PERBAIKAN V34 (2026-11-05) — MINIMAP JADI "HASIL ATTENDANCE"
                                     ----------------------------------------------------------
                                     Permintaan user: "Mini Map Lokasi di Presensi Wajah & GPS
                                     diubah menjadi minimap hasil dari attendance, seperti
                                     minimap pada Detail Presensi Wajah di /face-attendance/rekap".

                                     Perubahan:
                                       - Judul: "Mini Map Lokasi" -> "Minimap Lokasi Absensi"
                                       - Isi peta sekarang menampilkan HASIL ATTENDANCE
                                         (marker Clock In / Clock Out + polygon lokasi
                                         kerja), BUKAN marker posisi GPS live yang
                                         mendominasi.
                                       - Gaya marker & polygon DISAMAKAN dengan
                                         halaman rekap (divIcon orang, hijau=In,
                                         merah=Out, polyline putus-putus, polygon
                                         hijau bila memuat titik absensi).
                                     ============================================================ --}}
                                <div class="mt-3 pt-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted font-weight-bold text-uppercase">
                                            <i class="fas fa-map-marked-alt text-primary mr-1"></i> Minimap Lokasi Absensi
                                        </small>
                                        <span id="miniMapProfileStatus" class="badge badge-light border small text-muted">Memuat Peta...</span>
                                    </div>
                                    <div id="miniMapProfile" class="rounded border shadow-sm" style="height: 180px; width: 100%; z-index: 1;"></div>
                                    <small id="miniMapAccuracyText" class="text-muted d-block text-center mt-1" style="font-size: 0.75rem;">Belum ada data absensi hari ini</small>
                                    {{-- PERBAIKAN V34 (2026-11-05): status GPS dipisah dari
                                         badge minimap. Badge sekarang menampilkan
                                         JUMLAH ABSENSI, sedangkan status GPS tampil
                                         di baris kecil ini supaya keduanya tidak
                                         saling menimpa. --}}
                                    <small id="faGpsStatusText" class="text-muted d-block text-center" style="font-size: 0.72rem; margin-top: 2px;"></small>
                                    {{-- PERBAIKAN V32.8 (2026-11-05): daftar koordinat tiap
                                         scan (clock in / clock out) supaya minimap bisa
                                         menampilkan SEJUMAH marker sesuai jumlah
                                         pemakaian face attendance hari ini.
                                         Array dihitung lebih dulu di blok PHP terpisah;
                                         directive @json() hanya menerima SATU
                                         ekspresi, sehingga closure multi-line
                                         di dalamnya akan merusak parsing Blade. --}}
                                    @php
                                        $faScanPoints = collect($todayLogs)->map(function ($l) {
                                            return [
                                                'lat'  => $l->latitude !== null ? (float) $l->latitude : null,
                                                'lng'  => $l->longitude !== null ? (float) $l->longitude : null,
                                                'acc'  => $l->accuracy !== null ? (float) $l->accuracy : null,
                                                'type' => (($l->calculated_type ?? 'in') === 'in') ? 'in' : 'out',
                                                'time' => \Carbon\Carbon::parse($l->scan_date)->format('H:i:s'),
                                            ];
                                        })->values()->all();
                                    @endphp
                                    <script type="application/json" id="faScanPointsJson">@json($faScanPoints)</script>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ============================================================
                         PERBAIKAN V33 (2026-11-05) — CARD cameraCardBody DIHAPUS
                         ------------------------------------------------------------
                         Permintaan user: "saya maunya cameraCardBody dihapus".

                         Sebelumnya (V32.9) kolom ini disembunyikan dengan
                         class "d-none" supaya elemen DOM (video, canvas, tombol)
                         tetap ada untuk JS auto-presensi. Sekarang:
                           - wrapper kolom + <div id="cameraCardBody"> + header
                             collapsible DIHAPUS TOTAL dari markup,
                           - isi kamera (video, placeholder, banner window,
                             liveness) dipindah ke card UTAMA "Data Karyawan"
                             sebagai satu blok di dalam card-body,
                           - <video id="webcam">, <canvas id="faceCaptureCanvas">,
                             <div id="cameraPlaceholder">, <div id="scannerGuide">,
                             <div id="camOverlayBadge">, <div id="overlayText">,
                             <div id="windowWaitBanner">, <div id="livenessAlertBox">
                             tetap ADA dengan ID yang sama supaya SELURUH JS
                             (startCamera, detectFaceLoop, evaluateCameraGate,
                             captureFaceImage, liveness) tidak perlu diubah
                             satu baris pun dan auto-presensi tetap jalan.
                           - Toggle buka/tutup kartu kamera (toggleCameraCard)
                             tidak dipakai lagi; dilepas dari JS.
                         ============================================================ --}}
                    @if($nextAction != 'completed')
                    {{-- ============================================================
                         PERBAIKAN V35 (2026-11-05) — PANEL KAMERA DISEMBUNYIKAN
                         SAAT TIDAK ADA YANG BISA DIPINDAI.
                         ------------------------------------------------------------
                         Permintaan user (gambar ke-1): "hapus semua elemen ini".
                         Elemen yang ditunjuk termasuk card kamera gelap
                         "Belum Memasuki Window Absensi" beserta video,
                         placeholder gelap, ikon jam, dan badge "Posisikan
                         Wajah".

                         SOLUSI: wrapper .fa-camera-panel diberi class
                         "fa-camera-hidden" yang applying `display:none`.
                         Elemen DOM di dalamnya (video, canvas, placeholder,
                         scannerGuide, camOverlayBadge, overlayText,
                         windowWaitBanner, livenessAlertBox) TETAP ADA di
                         DOM — jadi startCamera(), detectFaceLoop(),
                         evaluateCameraGate(), captureFaceImage(), dan
                         liveness tetap berjalan tanpa perubahan satu baris
                         pun. Yang hilang hanya PERILAKAT VISUALNYA.

                         Wrapper ini disembunyikan saat:
                           - nextAction = 'expired'  (window sudah lewat)
                           - nextAction = 'waiting'  (belum masuk window)
                           - sudah Clock In tapi belum Clock Out
                             (tidak ada yang perlu dipindai)
                         yaitu ketika kamera memang tidak boleh menyala.

                         Saat nextAction = 'clock_in' / 'clock_out' (kamera
                         WAJIB aktif), panel tetap tampil normal.
                         ============================================================ --}}
                    <div class="col-12 mb-4">
                        {{-- Panel kamera disembunyikan saat:
                               - nextAction = 'expired'  (window sudah lewat)
                               - nextAction = 'waiting'  (belum masuk window)
                               - sudah Clock In tapi belum Clock Out
                                 (tidak ada yang perlu dipindai)
                             Saat 'clock_in' atau 'clock_out' (kamera WAJIB aktif),
                             panel tetap tampil normal.
                             'completed' tidak masuk sini karena seluruh blok
                             kamera tidak dirender. --}}
                        <div class="fa-camera-panel @if($nextAction === 'expired' || $nextAction === 'waiting' || ($hasClockIn && !$hasClockOut)) fa-camera-hidden @endif">
                            <div class="card-body p-4 text-center">
                                {{-- ============================================================
                                     PERBAIKAN V35 (2026-11-05) — BANNER WINDOW + BANNER
                                     LIVENESS + KALIMAT BANTUAN DIHAPUS DARI MARKUP.

                                     Permintaan user (gambar ke-1): "hapus semua
                                     elemen ini di /face-attendance" yang menunjuk:
                                       1. Banner "Window Presensi Hari Ini Sudah
                                          Berakhir" (+ Window: ... + countdown)
                                       2. Banner "LIVENESS CHECK: Silakan BERKEDIP
                                          atau MENOLEH ..."
                                       3. Card kamera gelap "Belum Memasuki Window
                                          Absensi" (+ ikon jam + deskripsi window)
                                       4. Kalimat "Sistem secara otomatis mendeteksi
                                          gerakan wajah asli ..."

                                     Yang TETAP ADA (agar JS & auto-presensi tidak
                                     rusak, karena element ini diakses lewat
                                     getElementById):
                                       - <video id="webcam">
                                       - <canvas id="faceCaptureCanvas">
                                       - <div id="scannerGuide">
                                       - <div id="camOverlayBadge"> + #overlayText
                                       - <div id="cameraPlaceholder"> + sub-elemennya
                                       - <div id="windowWaitBanner"> (dibuat ulang
                                         oleh JS bila perlu, default-nya kosong)
                                       - <div id="livenessAlertBox"> (dibuat ulang
                                         oleh JS bila perlu, default-nya kosong)

                                     Banner window & banner liveness sekarang
                                     TIDAK dirender sebagai HTML statis. JS tetap
                                     bisa mengisinya bila perlu, tapi kondisi
                                     default (di luar window / liveness belum aktif)
                                     tidak menampilkan banner tersebut.
                                     ============================================================ --}}
                                <div id="windowWaitBanner" class="d-none" role="status" aria-live="polite"></div>

                                <div class="position-relative mx-auto rounded overflow-hidden shadow-sm" style="max-width: 640px; width: 100%; height: 420px; background: #1a1c23;">
                                    <video id="webcam" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>

                                    <div id="scannerGuide" class="scanner-guide-box"></div>

                                    <div id="camOverlayBadge" class="position-absolute bg-dark text-white px-3 py-1 rounded-pill small font-weight-bold shadow" style="top: 15px; left: 15px; opacity: 0.9; z-index: 10;">
                                        <span id="overlayText"><i class="fas fa-user-clock text-warning mr-1"></i> Posisikan Wajah</span>
                                    </div>

                                    <div id="cameraPlaceholder" class="position-absolute d-flex flex-column align-items-center justify-content-center text-white" style="top:0; left:0; width:100%; height:100%; background: #2e3340; z-index: 5;">
                                        <i class="fas fa-clock fa-3x text-warning mb-3" id="placeholderIcon"></i>
                                        <h5 class="font-weight-bold" id="placeholderTitle">Memeriksa Jadwal Presensi...</h5>
                                        <p class="text-gray-400 small px-4 mb-3" id="placeholderDesc">
                                            Menyiapkan sistem presensi...
                                        </p>
                                        <div id="placeholderBtnContainer">
                                            <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="retryCamera()">
                                                <i class="fas fa-video mr-1"></i> Mulai Kamera
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Canvas tersembunyi untuk capture wajah saat liveness berhasil -->
                                <canvas id="faceCaptureCanvas" width="320" height="320" style="display: none;"></canvas>

                                {{-- PERBAIKAN V35 (2026-11-05): #livenessAlertBox TIDAK lagi
                                     dirender sebagai banner HTML statis. JS tetap dapat
                                     mengisi elemen ini saat liveness sedang aktif.
                                     Div kosong default supaya getElementById tidak null. --}}
                                <div id="livenessAlertBox" class="d-none"></div>
                            </div>
                        </div>
                    </div>
                    @else
                    {{-- PERBAIKAN V34 (2026-11-05): Card "Presensi Hari Ini
                         Selesai" lama (gambar ke-1 dari user) DIHAPUS dari
                         sini karena informasinya sudah ter-cover penuh oleh
                         "Card Status Presensi" yang sekarang berada di ATAS
                         card "Data Karyawan". --}}
                    @endif
                </div>

            </div>
        </div>

        @include('layout.footer')

{{-- PERBAIKAN (2026-11-03): SweetAlert2 WAWAS DIMUAT.
     Halaman ini memanggil Swal.fire() pada handler submit presensi, tapi
     SweetAlert2 tidak pernah dimuat => "ReferenceError: Swal is not defined"
     sehingga pesan error tidak pernah tampil dan user mengira proses
     membeku. CDN ini sama dengan yang dipakai halaman auth/login.blade.php.
--}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- face-api.js -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

<script>
    const activeLocations = @json($locations);
    const npk = "{{ $biodata->NPK }}";
    const nextAction = "{{ $nextAction }}";
    // PERBAIKAN 23 (2026-11-02): pakai `let`, bukan `const`, supaya bisa
    // diperbarui segera setelah presensi tersimpan (syncPresensiFlag())
    // tanpa perlu reload halaman. Nilai awal tetap dari server.
    let hasClockIn = {{ $hasClockIn ? 'true' : 'false' }};
    let hasClockOut = {{ $hasClockOut ? 'true' : 'false' }};

    // Timestamp window absensi (milidetik)
    const clockInStartMs = {{ $clockInStart->timestamp * 1000 }};
    const clockInEndMs = {{ $clockInEnd->timestamp * 1000 }};
    const clockOutStartMs = {{ $clockOutStart->timestamp * 1000 }};
    const clockOutEndMs = {{ $clockOutEnd->timestamp * 1000 }};

    const clockInStartStr = "{{ $clockInStart->format('H:i') }}";
    const clockInEndStr = "{{ $clockInEnd->format('H:i') }}";
    const clockOutStartStr = "{{ $clockOutStart->format('H:i') }}";
    const clockOutEndStr = "{{ $clockOutEnd->format('H:i') }}";

    let currentLat = null;
    let currentLng = null;
    let currentAccuracy = null;
    let isGpsValid = false;
    let isModelLoaded = false;
    let isScanning = false;
    let livenessVerified = false;
    let cameraActive = false;

    // ==================================================================
    // PERBAIKAN 25 (2026-11-05): TOGGLE LIVENESS CHECK DARI ADMIN
    // ==================================================================
    // Dibaca dari menu Manage Face Recognition -> Pengaturan Liveness Check.
    // OFF = cocokkan wajah langsung tanpa kedip/geleng, foto tetap diambil.
    // Default true (fail-safe): variabel tidak terkirim -> liveness tetap ON.
    const LIVENESS_REQUIRED = Boolean(@json((bool) ($livenessEnabled ?? true)));

    // ==================================================================
    // PERBAIKAN V30 (2026-11-04): NONCE ANTI-FAKE-GPS
    // ==================================================================
    // Server menerbitkan BEBERAPA token sekali pakai setiap halaman ini
    // dibuka (V31: 5 token, berlaku 30 menit). Token dikirim balik saat
    // submit presensi dan divalidasi server.
    // Kenapa lebih dari satu: satu halaman dipakai clock-in DAN clock-out,
    // ditambah retry kalau submit gagal. Dengan satu token, clock-out selalu
    // ditandai is_suspicious padahal karyawan tidak berbuat salah.
    // Kalau kosong, presensi tetap jalan tetapi akan DITANDAI server.
    // PERBAIKAN V31.1 (2026-11-05): terima DUA bentuk sekaligus.
    // Controller V31 mengirim ARRAY 5 token. Versi lama mengirim string.
    // Membungkus dengan [(string) $attendanceNonce] saat nilainya array
    // memicu "Array to string conversion", jadi bentuknya diperiksa dulu.
    window.__attNonces = (function (sumber) {
        var hasil = [];
        if (Array.isArray(sumber)) {
            sumber.forEach(function (t) {
                if (typeof t === 'string' && t.length > 0) { hasil.push(t); }
            });
        } else if (typeof sumber === 'string' && sumber.length > 0) {
            hasil.push(sumber);
        }
        return hasil;
    })(@json($attendanceNonce ?? []));

    // PERBAIKAN V31 (2026-11-05): isi ulang token setelah presensi berhasil.
    // Tanpa ini, clock-in menghabiskan token dan clock-out berikutnya jatuh
    // ke token sisa. Kalau sisa habis (mis. banyak retry), presensi tetap
    // tersimpan tetapi tidak perlu sampai kehilangan token sama sekali.
    window.__attNonceRefill = function (daftar) {
        if (!Array.isArray(daftar)) { return; }
        daftar.forEach(function (t) {
            if (typeof t === 'string' && t.length > 0 && window.__attNonces.indexOf(t) === -1) {
                window.__attNonces.push(t);
            }
        });
    };

    // Saat OFF, tantangan dianggap selesai agar alur kirim presensi yang
    // sudah ada berjalan tanpa perubahan lain.
    if (!LIVENESS_REQUIRED) {
        livenessVerified = true;
    }

    // ==================================================================
    // STATE MESIN KAMERA — REBUILD TOTAL (2026-11-03)
    // ==================================================================
    // DESKRIPSI MASALAH:
    //   Placeholder macet di "Menyiapkan Kamera..." setelah user GiveUp /
    //   ditolak / browser error. Root cause-nya STATE LATCH yang TIDAK
    //   PERNAH DIBERSIHKAN:
    //
    //     - `cameraStarting` = true ditulis sebelum getUserMedia(). Tick
    //       gate punya early-return `if (cameraStarting) return;`, jadi
    //       TIDAK ADA yang bisa mengubahnya lagi -> deadlock.
    //     - `cameraFailed` = true setelah error, dan gate punya
    //       `if (cameraFailed) return;`. Flag itu hanya dibersihkan oleh
    //       retryCamera() (tombol manual). Kalau user tidak menekan
    //       tombol, kamera MATI PERMANEN sampai refresh halaman.
    //     - `cameraAutoStarted` = true diset sebelum startCamera(); kalau
    //       startCamera() gagal, latch tidak pernah di-reset -> auto-start
    //       tidak akan terjadi lagi.
    //
    // DESAIN BARU (mengikuti pola sederhana /face-attendance/login):
    //   Tidak ada latch yang bisa mengunci kamera selamanya. Semua flag
    //   selalu dibersihkan di blok `finally`, dan percobaan ulang
    //   dilakukan otomatis dengan jeda (cooldown) supaya tidakspam
    //   popup izin browser.
    // ==================================================================

    // true = sedang memanggil getUserMedia(). Dijaga supaya tidak ada
    // dua permintaan izin sekaligus.
    let cameraStarting = false;

    // true = percobaan terakhir gagal. Dipakai untuk:
    //   - menampilkan pesan error yang jelas, dan
    //   - menjeda percobaan otomatis berikutnya (tidak spam popup izin).
    // TIDAK lagi menjadi penghalang permanen: tick akan mencoba lagi
    // setelah cooldown habis.
    let cameraFailed = false;

    // Waktu (ms epoch) percobaan otomatis berikutnya yang diizinkan.
    // Mencegah popup izin browser muncul berulang-ulang tiap detik.
    let cameraRetryAfter = 0;

    // Jeda minimum (ms) sebelum mencoba otomatis lagi setelah gagal.
    const CAMERA_RETRY_COOLDOWN_MS = 8000;

    // Batas waktu (ms) menunggu izin kamera.
    const CAMERA_PERMISSION_TIMEOUT_MS = 20000;

    // State alur GPS-first. gpsVerified = true setelah GPS berhasil DAN user
    // berada di dalam area polygon.
    let gpsVerified = false;       // true = GPS sudah berhasil & user di dalam area
    let gpsChecked = false;        // true = percobaan GPS sudah pernah dijalankan

    // PERBAIKAN (2026-11-03): cameraAutoStarted dihapus. Latch ini
    //AHAS masalah: diset true SEBELUM startCamera() dan tidak pernah
    // di-reset kalau percobaan gagal, sehingga auto-start mati
    // permanen. Sekarang replacement-nya adalah `cameraRetryAfter`
    // (cooldown berbasis waktu), yang self-healing: begitu cooldown
    // habis dan syarat terpenuhi, kamera otomatis mencoba lagi.

    // SYARAT KAMERA (ditetapkan user 2026-11-03):
    //   Kamera HANYA boleh menyala kalau DUA syarat ini terpenuhi SEKALIGUS:
    //     1. GPS terverifikasi DI DALAM area kerja  (gpsVerified === true)
    //     2. Waktu sekarang DI DALAM window absensi (inWindow  === true)
    //   Di luar area  -> kamera TIDAK menyala.
    //   Di luar window -> kamera TIDAK menyala.
    // inWindow dihitung ulang setiap detik oleh evaluateCameraGate().
    let inWindow = false;

    let blinkDetected = false;
    let headTurnDetected = false;

    // Mini Map Leaflet di Card Profil Karyawan
    let miniMapProfile = null;
    let polyGroupProfile = null;
    // PERBAIKAN V34 (2026-11-05): variabel marker posisi GPS LIVE
    // (userCircleProfile / userMarkerProfile) DIHAPUS. Minimap sekarang
    // menampilkan hasil attendance, bukan posisi real-time user.
    // PERBAIKAN V32.8 (2026-11-05): marker untuk setiap scan face attendance
    // hari ini (clock in & clock out). Sebelumnya minimap hanya menampilkan
    // 1 marker (posisi GPS saat ini), sehingga jejak 2 penggunaan presensi
    // tidak terlihat.
    let scanMarkersProfile = [];

    // PERBAIKAN V32.8: baca daftar titik scan dari JSON di DOM.
    function getFaScanPoints() {
        const el = document.getElementById('faScanPointsJson');
        if (!el) return [];
        try {
            const parsed = JSON.parse(el.textContent || el.innerText || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            console.warn('[FaceAttendance] Gagal parse titik scan:', e);
            return [];
        }
    }

    // PERBAIKAN V34 (2026-11-05): helper untuk mengisi badge status +
    // teks jumlah absensi. Dipanggil dari renderScanMarkersProfile() agar
    // badge benar sejak peta pertama kali digambar, tidak hanya setelah
    // GPS berhasil (kalau GPS gagal, badge tetap harus tampil benar).
    function updateMiniMapStatusProfile() {
        const statusBadge = document.getElementById('miniMapProfileStatus');
        const accuracyText = document.getElementById('miniMapAccuracyText');
        const points = getFaScanPoints().filter(p =>
            p && p.lat !== null && p.lng !== null &&
            !isNaN(parseFloat(p.lat)) && !isNaN(parseFloat(p.lng))
        );
        const scanCount = points.length;

        if (statusBadge) {
            if (scanCount > 0) {
                statusBadge.className = 'badge badge-success small';
                statusBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> ' + scanCount + ' Absensi';
            } else {
                statusBadge.className = 'badge badge-light border small text-muted';
                statusBadge.innerHTML = 'Belum Absensi';
            }
        }
        if (accuracyText) {
            if (scanCount > 0) {
                const list = points.map(p => (p.type === 'in' ? 'In' : 'Out') + ' ' + (p.time || '')).join(' · ');
                accuracyText.innerHTML = 'Absensi hari ini: <strong>' + scanCount + '</strong> scan (' + list + ')';
            } else {
                accuracyText.innerHTML = 'Belum ada data absensi hari ini';
            }
        }
        return scanCount;
    }

    // PERBAIKAN V34 (2026-11-05): marker scan DIBUAT SEPERSI rekap.
    // Gaya: divIcon lingkaran bewajah (👤) dengan warna hijau = Clock In,
    // merah = Clock Out; garis putus-putus menghubungkan titik-titiknya.
    // Tujuannya: minimap di halaman ini menampilkan HASIL ATTENDANCE,
    // sama seperti "Minimap Lokasi Absensi" di /face-attendance/rekap.
    function renderScanMarkersProfile() {
        if (!miniMapProfile) return;

        scanMarkersProfile.forEach(m => {
            try { miniMapProfile.removeLayer(m); } catch (e) {}
        });
        scanMarkersProfile = [];

        const points = getFaScanPoints().filter(p =>
            p && p.lat !== null && p.lng !== null &&
            !isNaN(parseFloat(p.lat)) && !isNaN(parseFloat(p.lng))
        );

        // Badge + teks jumlah absensi selalu diperbarui, walau tidak ada
        // titik (status jadi "Belum Absensi").
        updateMiniMapStatusProfile();

        if (points.length === 0) return;

        const scanPts = [];

        points.forEach((p, idx) => {
            const isIn = p.type === 'in';
            const bg = isIn ? '#1cc88a' : '#e74a3b';
            const label = (isIn ? 'Clock In' : 'Clock Out') + (p.time ? ' ' + p.time : '');
            const slat = parseFloat(p.lat);
            const slng = parseFloat(p.lng);
            const sAcc = (p.acc !== null && p.acc !== undefined && !isNaN(parseFloat(p.acc)))
                ? Math.round(parseFloat(p.acc)) : null;

            scanPts.push([slat, slng]);

            try {
                const icon = L.divIcon({
                    className: 'custom-face-marker',
                    html: '<div style="background:' + bg + ';color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.35);font-size:13px;border:2px solid #fff;">&#128100;</div>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });

                const mk = L.marker([slat, slng], { icon: icon }).addTo(miniMapProfile);
                mk.bindPopup(
                    '<strong>' + label + '</strong>' +
                    (sAcc !== null ? '<br>Akurasi ±' + sAcc + ' m' : '')
                );
                mk.bindTooltip(label, { direction: 'top', offset: [0, -12] });
                scanMarkersProfile.push(mk);
            } catch (e) {
                console.warn('[FaceAttendance] Gagal gambar marker scan:', e);
            }
        });

        // Garis penghubung antar scan (urutan waktu) bila ada >= 2 titik.
        if (scanPts.length >= 2) {
            try {
                const line = L.polyline(scanPts, {
                    color: '#4e73df',
                    weight: 2,
                    opacity: 0.55,
                    dashArray: '6,6'
                }).addTo(miniMapProfile);
                scanMarkersProfile.push(line);
            } catch (e) {}
        }

        // Fokus ke titik-titik absensi (bukan ke posisi GPS live).
        try {
            const bounds = L.latLngBounds(scanPts);
            if (bounds.isValid()) {
                miniMapProfile.fitBounds(bounds, { padding: [35, 35], maxZoom: 17 });
            }
        } catch (e) {}
    }

    // PERBAIKAN V33 (2026-11-05): fungsi toggleCameraCard() dan
    // restoreCameraCardState() DIHAPUS. Card Kamera Presensi (beserta
    // id="cameraCardBody") sudah tidak ada di markup, jadi kedua fungsi
    // ini tidak punya target dan hanya jadi kode mati yang bisa
    // membingungkan. Kamera sekarang selalu tampil di card itself.

    // PERBAIKAN V34 (2026-11-05): fungsi focusOnUserProfile() DIHAPUS.
    // Dulu dipakai untuk auto-zoom ke posisi GPS LIVE. Sekarang minimap
    // menampilkan HASIL ATTENDANCE, jadi zoom ditentukan oleh
    // fitBounds() ke titik-titik absensi (lihat renderScanMarkersProfile).
    // Fungsi ini jadi kode mati kalau tidak dihapus.

    function initMiniMapProfile() {
        if (miniMapProfile) return;
        const container = document.getElementById('miniMapProfile');
        if (!container) return;

        // PERBAIKAN (KRITIS): Leaflet dimuat dari CDN (unpkg.com). Kalau CDN
        // diblokir / tidak terjangkau di jaringan user, `L` jadi undefined dan
        // `L.map(...)` melempar ReferenceError. Dulu exception ini mematikan
        // seluruh refreshLocation() SEBELUM getCurrentPosition() dipanggil
        // -> gpsVerified tetap false -> kamera tidak pernah nyala otomatis
        // dan placeholder menggantung di "Menyiapkan Kamera...".
        // Mini Map itu fitur tambahan; GPS + kamera TIDAK boleh bergantung padanya.
        if (typeof L === 'undefined' || !L || typeof L.map !== 'function') {
            console.warn('[FaceAttendance] Leaflet tidak tersedia, Mini Map dilewati.');
            return;
        }

        try {
            miniMapProfile = L.map('miniMapProfile', {
                zoomControl: false,
                attributionControl: false
            }).setView([-6.2088, 106.8456], 14);
        } catch (e) {
            console.warn('[FaceAttendance] Gagal membuat Mini Map:', e);
            miniMapProfile = null;
            return;
        }

        // PERBAIKAN: tileLayer & featureGroup juga dibungkus try/catch.
        // Kalau salah satu gagal, Mini Map dilewati — GPS + kamera tetap jalan.
        try {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(miniMapProfile);

            polyGroupProfile = L.featureGroup().addTo(miniMapProfile);
        } catch (e) {
            console.warn('[FaceAttendance] Gagal memuat layer Mini Map:', e);
            return;
        }

        // PERBAIKAN V34 (2026-11-05): polygon lokasi kerja sekarang digambar
        // oleh renderLocationPolygonsProfile() supaya warnanya bisa membedakan
        // polygon yang memuat titik absensi (hijau) vs polygon lain (biru/abu),
        // sama seperti halaman rekap. Blok lama yang menggambar semua polygon
        // biru DISEKIPSI supaya tidak ada polygon dobel.
        renderLocationPolygonsProfile();

        // PERBAIKAN V32.8: gambar marker scan segera setelah peta siap, supaya
        // marker terlihat walau GPS belum selesai diambil.
        renderScanMarkersProfile();
    }

    // PERBAIKAN V34 (2026-11-05): minimap kini menampilkan HASIL ATTENDANCE,
    // sama seperti "Minimap Lokasi Absensi" di /face-attendance/rekap.
    //   - Marker posisi GPS LIVE ("Posisi Anda") DIHAPUS supaya tidak
    //     mendominasi peta. Peta fokus ke titik-titik absensi.
    //   - Polygon lokasi kerja digambar dengan warna:
    //       hijau  = polygon yang memuat titik absensi (match)
    //       biru   = polygon aktif lain
    //       abu    = polygon non-aktif
    //   - Fungsi GPS di halaman ini TETAP dipakai untuk gerbang kamera
    //     (harus di dalam area + di dalam window) -- hanya visualnya yang
    //     berubah.
    function updateMiniMapProfile(lat, lng, accuracy, inside) {
        initMiniMapProfile();
        if (!miniMapProfile) return;

        // PERBAIKAN V34: marker posisi GPS live sudah dihapus, jadi tidak
        // ada lagi layer yang perlu dibersihkan di sini.

        // Gambar ulang polygon lokasi kerja dengan pewarnaan match.
        renderLocationPolygonsProfile();

        // Gambar marker untuk SETIAP scan face attendance hari ini.
        renderScanMarkersProfile();

        // Badge + teks jumlah absensi (helper dipanggil juga dari
        // renderScanMarkersProfile, di sini untuk memastikan status GPS
        // ikut terupdate bila belum ada scan sama sekali).
        updateMiniMapStatusProfile();
    }

    // PERBAIKAN V34 (2026-11-05): gambar polygon lokasi kerja dengan
    // pewarnaan seperti halaman rekap (hijau bila memuat titik absensi).
    function renderLocationPolygonsProfile() {
        if (!miniMapProfile) return;

        if (polyGroupProfile) {
            try { miniMapProfile.removeLayer(polyGroupProfile); } catch (e) {}
        }
        polyGroupProfile = L.featureGroup().addTo(miniMapProfile);

        const points = getFaScanPoints().filter(p =>
            p && p.lat !== null && p.lng !== null &&
            !isNaN(parseFloat(p.lat)) && !isNaN(parseFloat(p.lng))
        );

        let matchedName = null;
        for (let i = 0; i < points.length; i++) {
            const loc = findLocationByCoordsProfile(activeLocations, parseFloat(points[i].lat), parseFloat(points[i].lng));
            if (loc) { matchedName = loc.name; break; }
        }

        if (!activeLocations || activeLocations.length === 0) return;

        activeLocations.forEach(loc => {
            if (!loc.polygon_geojson) return;
            try {
                const geo = JSON.parse(loc.polygon_geojson);
                const isActive = loc.is_active !== false;
                const isMatched = matchedName !== null && loc.name === matchedName;
                const color = isMatched ? '#1cc88a' : (isActive ? '#4e73df' : '#858796');
                const fillOpacity = isMatched ? 0.25 : (isActive ? 0.15 : 0.08);

                const poly = L.geoJSON(geo, {
                    style: {
                        color: color,
                        fillColor: color,
                        fillOpacity: fillOpacity,
                        weight: isMatched ? 3 : 2
                    }
                }).bindPopup(
                    '<strong>' + (loc.name || 'Lokasi Kerja') + '</strong><br>' +
                    'Status: ' + (isActive ? 'Aktif' : 'Non-aktif')
                );
                polyGroupProfile.addLayer(poly);
            } catch (e) {}
        });
    }

    // PERBAIKAN V34: helper point-in-polygon versi client untuk mencari
    // polygon lokasi kerja yang memuat koordinat absensi. Sama seperti
    // findLocationByCoords() di halaman rekap.
    function findLocationByCoordsProfile(locations, lat, lng) {
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

    // Ray-casting polygon intersection
    // Format GeoJSON: polygon[i][0] = longitude, polygon[i][1] = latitude
    function pointInPolygon(lat, lng, polygon) {
        if (!polygon || !polygon.length) return true;
        let inside = false;
        for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
            let p_lng_i = polygon[i][0], p_lat_i = polygon[i][1];
            let p_lng_j = polygon[j][0], p_lat_j = polygon[j][1];

            let intersect = ((p_lat_i > lat) !== (p_lat_j > lat)) &&
                (lng < (p_lng_j - p_lng_i) * (lat - p_lat_i) / ((p_lat_j - p_lat_i) || 0.0000001) + p_lng_i);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    async function loadModels() {
        const loadFrom = async (url) => {
            if (faceapi.nets.tinyFaceDetector && !faceapi.nets.tinyFaceDetector.isLoaded) {
                await faceapi.nets.tinyFaceDetector.loadFromUri(url);
            }
            if (faceapi.nets.faceLandmark68TinyNet && !faceapi.nets.faceLandmark68TinyNet.isLoaded) {
                try {
                    await faceapi.nets.faceLandmark68TinyNet.loadFromUri(url);
                } catch(e) {}
            }
            if (faceapi.nets.faceLandmark68Net && !faceapi.nets.faceLandmark68Net.isLoaded && !faceapi.nets.faceLandmark68TinyNet.isLoaded) {
                await faceapi.nets.faceLandmark68Net.loadFromUri(url);
            }
            if (faceapi.nets.faceRecognitionNet && !faceapi.nets.faceRecognitionNet.isLoaded) {
                await faceapi.nets.faceRecognitionNet.loadFromUri(url);
            }
        };

        const candidates = [
            "{{ asset('vendor-face-api/model') }}",
            "{{ asset('models') }}",
            "/vendor-face-api/model",
            "/models",
            "vendor-face-api/model",
            "models"
        ];

        for (let url of candidates) {
            try {
                await loadFrom(url);
                isModelLoaded = true;
                return;
            } catch (err) {
                // Silently try next candidate
            }
        }
    }

    // Tick per detik: hitung ulang `inWindow`, lalu tegakkan ATURAN KAMERA
    // (ditetapkan user 2026-11-03):
    //
    //   Kamera HANYA menyala kalau SEKALIGUS:
    //     (1) GPS terverifikasi DI DALAM area kerja   -> gpsVerified === true
    //     (2) waktu sekarang DI DALAM window absensi  -> inWindow   === true
    //
    //   Di luar area   -> kamera TIDAK menyala (dan dimatikan bila sedang nyala).
    //   Di luar window -> kamera TIDAK menyala (dan dimatikan bila sedang nyala).
    //
    // PERBAIKAN (2026-11-03): jam digital DIHAPUS atas permintaan user, jadi
    // fungsi ini tidak lagi menulis ke elemen jam. Logikanya murni sebagai
    // gate kamera.
    //
    // PERBAIKAN PENTING: dulu fungsi ini memanggil startCamera() lewat
    // autoStartCamera() SETIAP DETIK. Kalau izin kamera belum dijawab,
    // startCamera() menulis ulang placeholder "Menyiapkan Kamera..." terus
    // menerus, sehingga user melihat pesan menggantung yang seolah macet —
    // padahal itu hanya UI yang ditimpa berulang. Sekarang startCamera()
    // dipanggil langsung dari tick, tapi DILINDUNGI oleh dua hal:
    //   1. `cameraStarting` — mencegah dua permintaan izin sekaligus.
    //   2. `cameraRetryAfter` — cooldown setelah gagal, supaya popup izin
    //      browser tidak spam tiap detik.
    // Keduanya selalu dibersihkan (flag `cameraStarting` di blok `finally`),
    // jadi tidak pernah ada jalan buntu.
    // -------------------------------------------------------------------------
    // SweetAlert2 fallback
    //
    // PERBAIKAN (2026-11-03): halaman ini memanggil showAlert(). SweetAlert2
    // dimuat via CDN; kalau CDN diblokir / offline, Swal jadi "undefined" dan
    // handler submit melempar ReferenceError. Akibatnya pesan error tidak
    // pernah tampil (user mengira proses membeku) dan state lokal tidak
    // di-reset => user tidak bisa mencoba lagi.
    //
    // Solusi: sediakan showAlert() yang memakai Swal bila tersedia, kalau
    // tidak jatuh ke alert() bawaan browser. Semua pemanggilan showAlert()
    // diganti showAlert() supaya tidak pernah ReferenceError.
    // -------------------------------------------------------------------------
    function showAlert(options) {
        const opt = options || {};
        if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
            try {
                return Swal.fire(opt);
            } catch (e) {
                // jatuh ke alert() di bawah
            }
        }
        const title = opt.title || '';
        const body = opt.html || opt.text || '';
        const icon = opt.icon === 'success' ? 'OK' : (opt.icon === 'error' ? 'GAGAL' : 'INFO');
        const plain = String(body).replace(/<[^>]*>/g, ' ');
        window.alert(icon + (title ? ' - ' + title : '') + (plain ? '\n\n' + plain : ''));
        return Promise.resolve({ isConfirmed: true, dismissed: false });
    }

    /**
     * ============================================================
     * PERBAIKAN (2026-11-03): helper show/hide placeholder kamera.
     * ============================================================
     * MASALAH: elemen #cameraPlaceholder punya class "d-flex" DAN
     * disembunyikan dengan class "d-none".
     *
     * Di sb-admin-2.min.css, ".d-none{display:none!important}" ada di
     * posisi 109835, sedangkan ".d-flex{display:flex!important}" ada di
     * posisi 110098 (LEBIH AKHIR). Karena keduanya specificity sama
     * (0,1,0) dan sama-sama !important, yang menang adalah SOURCE
     * ORDER -> .d-flex. Akibatnya placeholder TIDAK pernah hilang
     * dan tetap menutupi video -> user hanya melihat ikon + teks
     * "Kamera Aktif", bukan kamera real.
     *
     * Halaman /face-attendance/login sejak awal memakai
     * style.setProperty('display','none','important') sehingga
     * placeholder hilang dengan benar di sana.
     *
     * SOLUSI: samakan dengan login — gunakan inline style !important
     * PLUS class, supaya bebas dari masalah source order.
     * ============================================================
     */
    function hideCameraPlaceholder() {
        const ph = document.getElementById('cameraPlaceholder');
        if (!ph) return;
        ph.classList.add('d-none');
        ph.style.setProperty('display', 'none', 'important');
    }

    function showCameraPlaceholder() {
        const ph = document.getElementById('cameraPlaceholder');
        if (!ph) return;
        ph.classList.remove('d-none');
        ph.style.setProperty('display', 'flex', 'important');
    }

    /**
     * ============================================================
     * PERBAIKAN (2026-11-03): helper pesan gate kamera.
     * ============================================================
     * Sebelumnya logika "alasan kamera mati" diduplikasi di 2 tempat:
     * evaluateCameraGate() dan startCamera(). Duplikasi itu rawan
     * tidak sinkron — judul/pesan bisa berbeda antara yang tampil di
     * placeholder dan yang tampil saat user menekan tombol manual.
     *
     * Sekarang satu fungsi (applyGateNotice) dipakai dua-duanya, jadi
     * pesan selalu sama.
     *
     * Aturan pesan (sesuai permintaan user):
     *   - Di luar window clock in / clock out -> "Belum Memasuki Window
     *     Absensi" + jam window yang valid.
     *   - Di luar area GPS                 -> "Tidak Dalam Area Face
     *     Attendance" + ajakan mendekat.
     *   - Dua-duanya gagal                 -> tampilkan KEDUA alasan,
     *     karena user harus tahu seluruh syarat yang belum terpenuhi.
     * ============================================================
     */
    function applyGateNotice(areaOk, timeOk) {
        const phIcon  = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc  = document.getElementById('placeholderDesc');

        const windowText = 'Clock In ' + clockInStartStr + '-' + clockInEndStr
            + ' atau Clock Out ' + clockOutStartStr + '-' + clockOutEndStr + ' WIB';

        // Susun daftar alasan yang belum terpenuhi.
        const reasons = [];

        if (!areaOk) {
            if (gpsChecked) {
                reasons.push({
                    icon: 'fas fa-map-marker-alt',
                    title: 'Tidak Dalam Area Face Attendance',
                    desc: 'Anda berada di luar area face attendance yang terdaftar. '
                        + 'Kamera dimatikan otomatis. Mendekatlah ke area kerja, '
                        + 'lalu sistem akan menyalakan kamera secara otomatis.'
                });
            } else {
                reasons.push({
                    icon: 'fas fa-map-marker-alt',
                    title: 'Memeriksa Area Face Attendance...',
                    desc: 'Mohon tunggu, sedang memeriksa posisi Anda terhadap area face attendance.'
                });
            }
        }

        if (!timeOk) {
            // ============================================================
            // PERBAIKAN V35 (2026-11-05): TAMBAHKAN KASUS "WINDOW SUDAH
            // LEWAT" — ini akar masalah yang dilaporkan user.
            //
            // DULU: di luar window apa pun selalu muncul teks
            // "Belum Memasuki Window Absensi" yang menyesatkan, karena
            // user yang clock in jam 07:47 dan sekarang jam 21:39
            // (window Clock Out 17:00-21:00 sudah lewat) tetap diberi
            // tahu "waktu sekarang belum masuk window".
            //
            // SEKARANG: bila sudah Clock In DAN sekarang sudah melewati
            // akhir window Clock Out, teksnya eksplisit menyatakan
            // bahwa presensi TIDAK BISA dilakukan.
            // ============================================================
            const nowMs = Date.now();
            const sudahLewatSemuaWindow = (nowMs > clockOutEndMs)
                || (nowMs > clockInEndMs && nowMs > clockOutEndMs);

            if (hasClockIn && !hasClockOut && sudahLewatSemuaWindow) {
                reasons.push({
                    icon: 'fas fa-ban',
                    title: 'Waktu Clock Out Sudah Lewat, Anda Tidak Bisa Melakukan Presensi',
                    desc: 'Window Clock Out (' + clockOutStartStr + '-' + clockOutEndStr
                        + ') WIB sudah lewat. Presensi tidak dapat dilakukan sampai '
                        + 'window berikutnya. Kamera dimatikan otomatis.'
                });
            } else {
                reasons.push({
                    icon: 'fas fa-clock',
                    title: 'Belum Memasuki Window Absensi',
                    desc: 'Waktu sekarang belum masuk window clock in atau clock out. '
                        + 'Kamera dimatikan otomatis. Window absensi Anda: ' + windowText + '.'
                });
            }
        }

        if (reasons.length === 0) return;

        // Judul & ikon memakai alasan PERTAMA; deskripsi merangkum SEMUA
        // alasan supaya user tidak perlu menunggu tab berikutnya.
        const first = reasons[0];

        if (phIcon) {
            phIcon.className = first.icon + ' fa-3x text-warning mb-3';
        }
        if (phTitle) {
            // Judul:
            //  - 1 alasan  -> judul alasan tersebut (paling informatif).
            //  - 2 alasan  -> "Kamera Belum Dapat Digunakan", TAPI kalau
            //    salah satu alasannya "sedang memeriksa area" (GPS belum
            //    selesai), judulnya tetap "Memeriksa Area..." supaya user
            //    tahu itu sementara, bukan error permanen.
            //
            // PERBAIKAN V35 (2026-11-05): bila SUDAH Clock In DAN window
            // Clock Out sudah lewat, status "tidak bisa presensi" harus
            // jadi JUDUL UTAMA — bukan ditenggelkan jadi "Kamera Belum
            // Dapat Digunakan". Ini yang dilaporkan user: jam sudah lewat
            // tapi status masih terlihat seperti sekadar masalah kamera.
            const expiredReason = reasons.find(function(r) {
                return r.title.indexOf('Waktu Clock Out Sudah Lewat') === 0;
            });
            if (expiredReason) {
                phTitle.innerText = expiredReason.title;
            } else if (reasons.length > 1) {
                const checking = reasons.some(function(r) {
                    return r.title.indexOf('Memeriksa') === 0;
                });
                phTitle.innerText = checking
                    ? 'Memeriksa Area Face Attendance...'
                    : 'Kamera Belum Dapat Digunakan';
            } else {
                phTitle.innerText = first.title;
            }
        }
        if (phDesc) {
            phDesc.innerText = reasons.map(function(r) { return r.desc; }).join(' ');
        }
    }

    /**
     * ============================================================
     * PERBAIKAN 22 (2026-11-04): BANNER "MENUNGGU WINDOW".
     * ============================================================
     * Ketentuan user: bila belum masuk window Clock In maupun Clock Out,
     * status = "menunggu window", kamera OFF, dan tampilkan banner.
     *
     * Fungsi ini hanya mengurus BANNER. Penegakan "kamera off" tetap
     * dipegang evaluateCameraGate() (yang memanggil stopCamera()), jadi
     * tidak ada dua tempat berbeda yang memutuskan soal kamera.
     *
     * Dipanggil:
     *   - dari evaluateCameraGate() setiap tick (1 detik),
     *   - sekali saat DOM siap (lewat evaluateCameraGate() awal).
     *
     * @param {boolean} inClockIn  sekarang di window clock in
     * @param {boolean} inClockOut sekarang di window clock out
     */
    function updateWindowWaitBanner(inClockIn, inClockOut, masihAdaKerjaan) {
        // ============================================================
        // PERBAIKAN V35 (2026-11-05) — BANNER WINDOW DIHAPUS TOTAL.
        // ============================================================
        // Permintaan user: hapus elemen banner "Window Presensi Hari Ini
        // Sudah Berakhir" beserta sub-elemennya (#windowWaitTitle,
        // #windowWaitDesc, #windowWaitRange, #windowWaitCountdown).
        //
        // Fungsi ini TETAP ADA karena dipanggil dari
        // evaluateCameraGate() tiap detik — tapi sekarang hanya_RESET
        // placeholder kamera dengan teks ringkas, tanpa banner.
        //
        // Catatan: #windowWaitBanner sendiri masih ada di DOM (div
        // kosong, class d-none) supaya tidak ada error ReferenceError
        // bila ada pemanggilan lain.
        // ============================================================
        const banner = document.getElementById('windowWaitBanner');
        if (banner) banner.classList.add('d-none');

        // Kamera boleh nyala -> tidak ada yang perlu ditulis.
        if (masihAdaKerjaan) return;

        const nowMs = Date.now();
        const phIcon  = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc  = document.getElementById('placeholderDesc');
        const phBtn   = document.getElementById('placeholderBtnContainer');

        let judul, keterangan;

        // Kasus 1: sudah clock in, window Clock In masih berjalan.
        if (inClockIn && hasClockIn && !hasClockOut) {
            judul = 'Clock In Sudah Tercatat';
            keterangan = 'Anda sudah Clock In hari ini. Window Clock Out dibuka pukul '
                + clockOutStartStr + ' WIB.';
        }
        // Kasus 2: sudah clock out -> presensi hari ini selesai.
        else if (inClockOut && hasClockOut) {
            judul = 'Presensi Hari Ini Selesai';
            keterangan = 'Presensi hari ini sudah lengkap. Presensi berikutnya menunggu '
                + 'window Clock In berikutnya.';
        }
        // Kasus 3: terlalu pagi (sebelum window Clock In dibuka).
        else if (nowMs < clockInStartMs) {
            judul = 'Menunggu Window Clock In';
            keterangan = 'Window Clock In dibuka pukul ' + clockInStartStr + ' WIB.';
        }
        // Kasus 4: di antara dua window.
        else if (nowMs > clockInEndMs && nowMs < clockOutStartMs) {
            judul = 'Menunggu Window Clock Out';
            keterangan = 'Window Clock In sudah ditutup pukul ' + clockInEndStr
                + ' WIB. Window Clock Out dibuka pukul ' + clockOutStartStr + ' WIB.';
        }
        // Kasus 5: sudah lewat semua window hari ini.
        else {
            judul = 'Window Presensi Hari Ini Sudah Berakhir';
            keterangan = 'Window Clock In (' + clockInStartStr + '-' + clockInEndStr
                + ') dan Clock Out (' + clockOutStartStr + '-' + clockOutEndStr
                + ') WIB sudah lewat. Presensi tidak dapat dilakukan sampai '
                + 'window berikutnya.';
        }

        // Tulis hanya bila berubah -> mencegah kedipan teks tiap detik.
        if (phTitle && phTitle.innerText !== judul) phTitle.innerText = judul;
        if (phDesc && phDesc.innerText !== keterangan) phDesc.innerText = keterangan;

        // Ikon jam + sembunyikan tombol "Mulai Kamera" (kamera wajib mati).
        if (phIcon) phIcon.className = 'fas fa-clock fa-3x text-warning mb-3';
        if (phBtn) phBtn.innerHTML = '';
    }

    /**
     * PERBAIKAN 23 (2026-11-02): tandai bahwa presensi baru saja tersimpan,
     * lalu jalankan gate supaya kamera langsung mati tanpa menunggu reload.
     *
     * @param {string} actionType label dari server, mis. 'Clock In (Masuk)'
     *                            atau 'Clock Out (Pulang)'. Boleh juga kode
     *                            pendek 'clock_in' / 'clock_out'.
     */
    function syncPresensiFlag(actionType) {
        // PERBAIKAN V31.2: server mengirim LABEL ('Clock In (Masuk)'),
        // bukan kode ('clock_in'). Perbandingan lama tidak pernah cocok,
        // sehingga status selalu diambil dari `nextAction` yang BASI
        // (nilai saat halaman dimuat). Akibatnya status internal bisa
        // terbalik dan kamera menyala/mati di waktu yang salah.
        // Sekarang kedua bentuk diterima.
        const aksi = String(actionType || '').toLowerCase();
        const isClockIn  = aksi.indexOf('clock in') !== -1  || aksi === 'clock_in';
        const isClockOut = aksi.indexOf('clock out') !== -1 || aksi === 'clock_out';

        if (isClockIn) {
            hasClockIn = true;
        } else if (isClockOut) {
            hasClockOut = true;
        } else {
            // Server tidak mengirim info aksi: pakai nextAction sebagai
            // petunjuk TERAKHIR. Ini hanya cadangan, bukan jalur utama.
            // PERBAIKAN V35: 'expired' BUKAN 'clock_out' -- jangan set
            // hasClockOut=true karena itu akan mengarang presensi.
            if (nextAction === 'clock_in') hasClockIn = true;
            else if (nextAction === 'clock_out') hasClockOut = true;
        }

        // Jalankan gate seketika: matikan kamera bila sudah tidak ada kerjaan.
        try {
            evaluateCameraGate();
        } catch (e) {
            console.warn('[PERBAIKAN 23] gate setelah simpan gagal:', e);
        }
    }

    function evaluateCameraGate() {
        const now = new Date();

        // ---- Hitung apakah sekarang berada di window absensi ----
        const t = now.getTime();
        const inClockInWindow  = (t >= clockInStartMs  && t <= clockInEndMs);
        const inClockOutWindow = (t >= clockOutStartMs && t <= clockOutEndMs);
        inWindow = inClockInWindow || inClockOutWindow;

        // -----------------------------------------------------------------
        // PERBAIKAN 23 (2026-11-02): kamera hanya menyala bila masih ADA
        // yang perlu dipindai di window yang sedang berjalan.
        // -----------------------------------------------------------------
        // Masalah yang dilaporkan user: shift malam (23:00-08:00), window
        // Clock In 19:00-23:00. User sudah clock in 19:22, tapi kamera
        // tetap nyala sampai jam 23:00. Penyebabnya: gate lama hanya
        // memeriksa `inWindow` (berada di window), TIDAK memeriksa apakah
        // aksi untuk window itu sudah dilakukan.
        //
        // Aturan baru:
        //   - Di window Clock In  + belum clock in -> kamera BOLEH nyala
        //   - Di window Clock In  + SUDAH clock in -> kamera OFF + banner
        //   - Di window Clock Out + belum clock out -> kamera BOLEH nyala
        //   - Di window Clock Out + SUDAH clock out -> kamera OFF + banner
        //
        // `hasClockIn`/`hasClockOut` dikirim server saat halaman dimuat.
        // Bila presensi baru saja tersimpan, storeAttendance() memanggil
        // syncPresensiFlag() supaya gate langsung bereaksi tanpa refresh.
        // -----------------------------------------------------------------
        const butuhClockIn  = inClockInWindow  && !hasClockIn;
        const butuhClockOut = inClockOutWindow && !hasClockOut;
        const masihAdaKerjaan = butuhClockIn || butuhClockOut;

        // Banner "menunggu window" hanya untuk kondisi DI LUAR window ATAU
        // sudah selesai di window ini. Di dalam window tapi aksi sudah
        // beres -> banner "sudah selesai", bukan "menunggu window".
        updateWindowWaitBanner(inClockInWindow, inClockOutWindow, masihAdaKerjaan);

        const phBtn = document.getElementById('placeholderBtnContainer');

        // ---- Tentukan apakah kamera BOLEH aktif ----
        const areaOk = gpsVerified;          // syarat 1: di dalam area kerja
        const timeOk = masihAdaKerjaan;      // syarat 2: di window DAN belum selesai

        const mayOpenCamera = areaOk && timeOk;

        // Kalau syarat tidak terpenuhi tapi kamera sedang nyala -> MATIKAN.
        if (!mayOpenCamera && cameraActive) {
            stopCamera();
        }

        if (cameraActive) {
            hideCameraPlaceholder();
            return;
        }

        // Sedang menunggu getUserMedia() — jangan ganggu.
        // PENTING: ini BUKAN penghalang permanen. cameraStarting selalu
        // dibersihkan di blok finally startCamera(), jadi tidak ada
        // jalan buntu di sini.
        if (cameraStarting) return;

        // ---- Belum aktif: tampilkan alasan & tombol bila diizinkan ----
        if (!mayOpenCamera) {
            showCameraPlaceholder();
            if (phBtn) phBtn.classList.add('d-none');   // tombol disembunyikan
            applyGateNotice(areaOk, timeOk);
            // Reset cooldown supaya begitu syarat terpenuhi lagi, kamera
            // boleh langsung mencoba (tidak menunggu sisa cooldown).
            cameraRetryAfter = 0;
            return;
        }

        // ---- Kedua syarat terpenuhi -> izinkan & auto-start ----
        // PERBAIKAN (2026-11-03): jangan tulis placeholder "Menyiapkan Kamera..."
        // dari sini. Tick berjalan setiap detik; placeholder itu hanya boleh
        // ditulis oleh startCamera() saat benar-benar memanggil getUserMedia().
        // Dulu tick menulisnya terus-menerus, jadi user melihat "Menyiapkan
        // Kamera..." menggantung selamanya padahal yang terjadi hanya UI yang
        // ditimpa tiap detik.
        if (phBtn) phBtn.classList.remove('d-none');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (phIcon) phIcon.className = 'fas fa-camera-slash fa-3x text-danger mb-3';
            if (phTitle) phTitle.innerText = 'Kamera Tidak Didukung Browser';
            if (phDesc) phDesc.innerText = 'Browser ini tidak mendukung akses kamera. Anda mengakses halaman lewat "'
                + location.protocol + '". Kamera HANYA bisa aktif lewat HTTPS atau localhost.';
            cameraFailed = true;
            return;
        }

        // -----------------------------------------------------------------
        // PERBAIKAN INTI (2026-11-03): latch `cameraAutoStarted` dihapus.
        // -----------------------------------------------------------------
        // Dulu ada baris:
        //     if (cameraAutoStarted) return;
        //     cameraAutoStarted = true;
        // Flag itu diset SEBELUM startCamera() dan TIDAK PERNAH di-reset
        // kalau percobaan gagal -> setelah satu kegagalan, auto-start
        // mati permanen sampai refresh halaman. Akibatnya user melihat
        // placeholder "Menyiapkan Kamera..." yang tidak pernah berubah.
        //
        // Sekarang digantikan cooldown berbasis waktu (`cameraRetryAfter`):
        //   - Percobaan pertama langsung jalan.
        //   - Kalau gagal, tunggu CAMERA_RETRY_COOLDOWN_MS sebelum mencoba
        //     lagi (mencegah popup izin browser spam tiap detik).
        //   - Setelah cooldown habis, kamera otomatis mencoba lagi —
        //     self-healing, tidak pernah terkunci permanen.
        // -----------------------------------------------------------------
        const nowMs = Date.now();
        if (cameraFailed && nowMs < cameraRetryAfter) {
            // PERBAIKAN V31.2: phTitle/phDesc DULU TIDAK ADA di fungsi ini.
            // Keduanya hanya dideklarasikan lokal di applyGateNotice() dan
            // startCamera(), sehingga baris di bawah melempar
            // "ReferenceError: phTitle is not defined".
            //
            // Akibat nyata: setiap kali kamera gagal (mis. izin kamera belum
            // diberikan / user menutup prompt izin), evaluateCameraGate()
            // CRASH di titik ini. Karena tick berjalan tiap detik, gate tidak
            // pernah menyelesaikan sisanya, dan UI tidak pernah memberi tahu
            // user bahwa sistem sedang menunggu izin kamera.
            const phTitle = document.getElementById('placeholderTitle');
            const phDesc  = document.getElementById('placeholderDesc');

            // Masih dalam masa jeda. Tampilkan countdown supaya user tahu
            // sistem sedang menunggu, bukan macet.
            const sisaDetik = Math.ceil((cameraRetryAfter - nowMs) / 1000);
            if (phTitle) phTitle.innerText = 'Menunggu Izin Kamera';
            if (phDesc) phDesc.innerText = 'Izin kamera belum diberikan. Sistem akan mencoba lagi dalam '
                + sisaDetik + ' detik. Anda juga bisa klik ikon kamera di address bar dan pilih "Izinkan".';
            return;
        }

        startCamera();
    }

    /**
     * PERBAIKAN (2026-11-03): fungsi `autoStartCamera()` DIHAPUS.
     *
     * Fungsi lama memakai latch `cameraAutoStarted` yang diset `true`
     * SEBELUM `startCamera()` dan tidak pernah di-reset kalau percobaan
     * gagal. Akibatnya:
     *   - Satu kegagalan -> auto-start mati permanen.
     *   - Placeholder menggantung di "Menyiapkan Kamera..." selamanya.
     *
     * Sekarang `evaluateCameraGate()` memanggil `startCamera()` langsung,
     * dengan cooldown berbasis waktu (`cameraRetryAfter`) sebagai
     * pengganti latch. Pendekatan ini self-healing: setelah cooldown
     * habis, kamera otomatis mencoba lagi tanpa perlu refresh halaman.
     */

    // Tombol "Mulai Kamera" / "Coba Lagi" — HANYA menyalakan kamera.
    // Tidak ada logika GPS di sini: validasi lokasi dihandled Mini Map
    // pada card Data Karyawan.
    //
    // PERBAIKAN (2026-11-03): tombol ini MELEWATI cooldown, karena user
    // secara eksplisit meminta percobaan ulang. State tetap dibersihkan
    // supaya startCamera() berjalan dari kondisi bersih.
    //
    // PENTING: stream lama harus benar-benar DIHENTIKAN dulu. Kalau tidak,
    // startCamera() akan melihat track yang masih 'live' dan keluar lebih
    // awal (`if (existingVideo.srcObject) { ... hasLiveTrack -> return }`),
    // sehingga percobaan ulang tidak pernah benar-benar terjadi.
    function retryCamera() {
        // Hentikan stream lama (kalau ada) supaya kamera tidak dipakai 2x
        // dan startCamera() bisa membuat stream baru.
        const video = document.getElementById('webcam');
        if (video && video.srcObject) {
            try {
                video.srcObject.getTracks().forEach(function (t) {
                    if (t && typeof t.stop === 'function') t.stop();
                });
            } catch (e) { /* abaikan */ }
            video.srcObject = null;
        }

        cameraFailed = false;
        cameraStarting = false;
        cameraActive = false;
        cameraRetryAfter = 0;
        startCamera();
    }

    // Refresh lokasi (Mini Map + badge GPS di card Data Karyawan).
    // Fungsi ini TIDAK pernah memblokir / menunda kamera.
    // PERBAIKAN (samakan dengan /face-attendance/login):
    // Alur WAJIB GPS-first → verifikasi area → kamera otomatis nyala.
    // Sebelumnya startCamera() dipanggil duluan (sebelum GPS selesai), sehingga
    // prompt izin muncul sebelum posisi terverifikasi dan placeholder menggantung
    // di "Menyiapkan Kamera...".
    // (State gpsVerified/gpsChecked/cameraAutoStarted dideklarasikan di atas.)

    // Dipanggil dari callback GPS sukses (di dalam area) dan dari tick clock.
    // Idempoten: aman dipanggil berkali-kali.
    //
    // SYARAT: startCamera() sendiri sudah menegakkan dua syarat (GPS di dalam
    // area + waktu di dalam window). Fungsi ini hanya mencegah pemanggilan
    // berulang saat kamera sudah jalan.
    function autoStartCameraOnGps() {
        if (!gpsVerified) return;                    // GPS belum terverifikasi / di luar area
        if (cameraActive || cameraStarting) return;  // sudah jalan / sedang jalan

        // PERBAIKAN (2026-11-03): latch `cameraAutoStarted` dihapus.
        // Dulu baris `cameraAutoStarted = true;` ada di sini. Selain membuat
        // auto-start mati permanen setelah satu kegagalan, baris itu juga
        // membuat ReferenceError setelah variabelnya dihapus.
        // Sekarang gating sepenuhnya diserahkan ke startCamera() + cooldown.
        startCamera();
    }

    // PERBAIKAN (CRITICAL): fungsi forceStartCamera() yang dulu memaksa kamera
    // menyala TANPA syarat area/window SUDAH DIHAPUS.
    // User menetapkan: kamera TIDAK boleh menyala bila GPS di luar area atau
    // waktu di luar window. Satu-satunya jalan masuk adalah startCamera(), yang
    // menegakkan kedua syarat tersebut.

    // Jaring pengaman bila GPS tidak pernah merespons (prompt izin diabaikan,
    // GPS device mati, dsb). PENTING: fallback ini TIDAK lagi menyalakan kamera,
    // karena tanpa GPS terverifikasi kita TIDAK TAHU user ada di dalam area.
    // Fallback hanya membuka kunci UI agar user bisa menekan "Cek Ulang GPS".
    const GPS_FALLBACK_TIMEOUT_MS = 15000;
    let gpsFallbackTimer = null;

    // ==================================================================
    // PERBAIKAN V31.6 (2026-11-05): TOLAK KOORDINAT JARINGAN/IP
    // ==================================================================
    // Aplikasi ini hanya memakai navigator.geolocation. Tetapi ketika GPS
    // GNSS tidak fix, browser tetap mengembalikan koordinat estimasi dari
    // jaringan (Wi-Fi / seluler / IP) dengan accuracy besar. Estimasi itu
    // BUKAN lokasi device sebenarnya dan tidak boleh dipakai untuk
    // geofence presensi.
    //
    // ==================================================================
    // PERBAIKAN V31.6 DIBATALKAN (2026-11-05): GPS dikembalikan ke
    // perilaku semula. Koordinat dari browser dipakai apa adanya,
    // termasuk estimasi jaringan bila GNSS belum fix.
    // ==================================================================

    function gpsAccuracyLabel(accuracy) {
        if (accuracy === null || accuracy === undefined) return '-';
        return Math.round(accuracy);
    }

    function scheduleGpsFallback() {
        if (gpsFallbackTimer) return;
        gpsFallbackTimer = setTimeout(function () {
            gpsFallbackTimer = null;
            if (gpsChecked || gpsVerified) return;      // GPS sudah merespons
            console.warn('[FaceAttendance] GPS tidak merespons dalam ' +
                (GPS_FALLBACK_TIMEOUT_MS / 1000) + ' detik. Kamera TIDAK dinyalakan ' +
                'karena posisi terhadap area kerja belum diketahui.');
            gpsChecked = true;
            // gpsVerified SENGAJA dibiarkan false -> kamera tetap mati.
            evaluateCameraGate();   // tampilkan alasan "Di Luar Area Kerja"
        }, GPS_FALLBACK_TIMEOUT_MS);
    }

    function cancelGpsFallback() {
        if (gpsFallbackTimer) {
            clearTimeout(gpsFallbackTimer);
            gpsFallbackTimer = null;
        }
    }

    function refreshLocation() {
        // PERBAIKAN V34 (2026-11-05): badge minimap sekarang menampilkan
        // JUMLAH ABSENSI (bukan status GPS), jadi variabel `badge` tidak
        // lagi dipakai di sini. Status GPS ditulis ke elemen terpisah
        // #faGpsStatusText supaya kedua informasi tidak saling menimpa.
        const alertBox = document.getElementById('livenessAlertBox');
        const alertText = document.getElementById('livenessInstructionText');

        // Elemen placeholder dideklarasikan di scope ini supaya tidak ada
        // ReferenceError (phTitle/phDesc/phIcon) yang mematikan callback GPS.
        const ph = document.getElementById('cameraPlaceholder');
        const phIcon = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc = document.getElementById('placeholderDesc');
        const phBtn = document.getElementById('placeholderBtnContainer');

        // PERBAIKAN (KRITIS): Mini Map dipanggil DI DALAM try/catch.
        // Mini Map hanya fitur tampilan; kalau Leaflet gagal dimuat / map gagal
        // dibuat, alur GPS + kamera HARUS tetap jalan. Dulu exception di sini
        // menghentikan refreshLocation() sebelum getCurrentPosition() dipanggil.
        try {
            initMiniMapProfile();
        } catch (miniMapErr) {
            console.warn('[FaceAttendance] Mini Map gagal, GPS tetap dijalankan:', miniMapErr);
        }

        if (!navigator.geolocation) {
            // PERBAIKAN V34 (2026-11-05): badge minimap menampilkan JUMLAH
            // ABSENSI, jadi pesan GPS ditulis ke elemen terpisah.
            const gpsStatusEl0 = document.getElementById('faGpsStatusText');
            if (gpsStatusEl0) gpsStatusEl0.textContent = 'GPS Tidak Didukung Browser';
            if (alertText) alertText.innerHTML = 'Browser tidak mendukung GPS Geolocation.';
            // PERBAIKAN (2026-11-03): FAIL-CLOSED.
            // Dulu di sini gpsVerified = true sehingga kamera tetap nyala
            // tanpa verifikasi area. Itu melanggar aturan "kamera hanya nyala
            // di dalam area kerja". Sekarang GPS tidak didukung = area TIDAK
            // bisa diverifikasi = kamera TIDAK menyala.
            cancelGpsFallback();
            gpsChecked = true;
            gpsVerified = false;      // <-- JANGAN set true
            evaluateCameraGate();     // tampilkan "Di Luar Area Kerja"
            return;
        }

        // PERBAIKAN: armed timer fallback SEBELUM minta GPS, supaya kalau GPS
        // menggantung (izin lokasi ditutup/diabaikan) kamera tetap nyala.
        scheduleGpsFallback();

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                cancelGpsFallback();
                // PERBAIKAN V31.6: seluruh body callback dibungkus try/catch.
                // Jika ada satu baris saja yang error (mis. elemen tidak ada / polygon rusak),
                // exception tidak boleh mematikan sisa callback sebelum startCamera().
                try {
                const rawAccuracy = pos.coords.accuracy;
                currentLat = pos.coords.latitude;
                currentLng = pos.coords.longitude;
                currentAccuracy = pos.coords.accuracy;
                let accuracy = Math.round(pos.coords.accuracy);

                let inside = false;
                if (activeLocations && activeLocations.length > 0) {
                    for (let loc of activeLocations) {
                        try {
                            let geo = loc.polygon_geojson ? JSON.parse(loc.polygon_geojson) : null;
                            let coords = geo && geo.geometry ? geo.geometry.coordinates[0] : [];
                            if (coords && coords.length >= 3 && pointInPolygon(currentLat, currentLng, coords)) {
                                inside = true;
                                break;
                            }
                        } catch (geoErr) {
                            console.error('[FaceAttendance] polygon tidak valid untuk lokasi id=' +
                                (loc && loc.id) + ':', geoErr);
                        }
                    }
                } else {
                    // PENTING (ketentuan user 2026-11-03: "jika gps diluar area
                    // kamera tidak menyala"):
                    // TIDAK ADA area kerja terdaftar = TIDAK ADA area yang bisa
                    // diverifikasi. Dulu di sini `inside = true` sehingga kamera
                    // menyala di mana saja. Sekarang FAIL-CLOSED: anggap di luar
                    // area, kamera tidak menyala, dan tampilkan pesan yang jelas.
                    inside = false;
                    console.error('[FaceAttendance] Tidak ada area kerja (work location) aktif. ' +
                        'Kamera tidak dinyalakan karena area kerja tidak dapat diverifikasi.');
                }

                updateMiniMapProfile(currentLat, currentLng, accuracy, inside);

                if (inside) {
                    isGpsValid = true;
                    gpsChecked = true;
                    gpsVerified = true;   // GPS OK + DI DALAM area → syarat 1 terpenuhi
                    // PERBAIKAN V34 (2026-11-05): badge minimap menampilkan
                    // JUMLAH ABSENSI, jadi status GPS ditulis ke elemen
                    // terpisah (faGpsStatusText) supaya tidak saling menimpa.
                    const gpsStatusOk = document.getElementById('faGpsStatusText');
                    if (gpsStatusOk) {
                        gpsStatusOk.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Dalam Area Kerja (±' + accuracy + 'm)';
                    }
                    // Syarat 1 (area) terpenuhi. startCamera() masih akan memeriksa
                    // syarat 2 (window waktu) sebelum benar-benar menyalakan kamera.
                    autoStartCameraOnGps();
                } else {
                    isGpsValid = false;
                    gpsChecked = true;
                    // User DI LUAR area kerja: kamera TIDAK boleh menyala.
                    // (Ketentuan user 2026-11-03: "jika gps diluar area kamera
                    // tidak menyala".)
                    gpsVerified = false;
                    const gpsStatusOut = document.getElementById('faGpsStatusText');
                    if (gpsStatusOut) {
                        gpsStatusOut.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Di Luar Area Kerja';
                    }
                    if (alertText) {
                        alertText.innerHTML = 'Posisi Anda terdeteksi di luar area lokasi presensi. Kamera tidak dapat dimulai. Mendekatlah ke area kerja, lalu tekan "Cek Ulang GPS".';
                    }
                    // Pastikan kamera mati bila sebelumnya sempat menyala.
                    if (cameraActive) stopCamera();
                    evaluateCameraGate();   // tampilkan "Di Luar Area Kerja"
                }
                } catch (e) {
                    // Terjadi error saat memproses posisi. Kita TIDAK bisa
                    // memastikan user ada di dalam area, jadi kamera TIDAK
                    // dinyalakan (aman secara default).
                    console.error('[FaceAttendance] refreshLocation callback error:', e);
                    gpsChecked = true;
                    gpsVerified = false;
                    if (cameraActive) stopCamera();
                    evaluateCameraGate();
                }
            },
            (err) => {
                cancelGpsFallback();
                // GPS gagal (ditolak / tidak tersedia). Tanpa GPS kita tidak tahu
                // apakah user berada di dalam area kerja, jadi kamera TIDAK
                // dinyalakan. (Ketentuan user 2026-11-03.)
                gpsChecked = true;
                gpsVerified = false;
                // PERBAIKAN V34 (2026-11-05): badge minimap sekarang
                // menampilkan JUMLAH ABSENSI, bukan status GPS. Jadi di sini
                // badge TIDAK lagi ditulis; pesan GPS dipindah ke elemen
                // status GPS terpisah supaya dua informasi tidak saling
                // menimpa.
                const gpsStatusEl = document.getElementById('faGpsStatusText');
                if (gpsStatusEl) gpsStatusEl.textContent = 'GPS Tidak Tersedia';
                console.warn('[FaceAttendance] GPS gagal, kamera tidak dinyalakan:', err);
                if (cameraActive) stopCamera();
                evaluateCameraGate();   // tampilkan "Di Luar Area Kerja"
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function stopCamera() {
        const video = document.getElementById('webcam');
        const camBadge = document.getElementById('cameraBadge');
        if (video && video.srcObject) {
            const tracks = video.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            video.srcObject = null;
        }
        cameraActive = false;
        cameraStarting = false;

        // PERBAIKAN (2026-11-03): reset cooldown juga.
        // Kalau kamera dimatikan karena user keluar area/window, lalu user
        // kembali ke dalam area, kamera harus bisa langsung menyala lagi —
        // tidak boleh menunggu sisa cooldown dari percobaan gagal sebelumnya.
        cameraFailed = false;
        cameraRetryAfter = 0;

        // PERBAIKAN (2026-11-03): reset SEMUA state UI yang menyesatkan.
        // Dulu stopCamera() hanya mematikan stream, tapi banner "LIVENESS
        // TERVERIFIKASI", badge "Wajah Terverifikasi", dan badge header
        // "Kamera Aktif" tetap menyala -> user melihat UI yang saling
        // bertentangan (kamera mati tapi badge bilang aktif & liveness
        // terverifikasi). Lei juga state liveness harus dibersihkan supaya
        // liveness palsu tidak ikut terkirim.
        isScanning = false;
        // PERBAIKAN 25: bila liveness OFF, jangan minta ulang kedip/geleng.
        livenessVerified = !LIVENESS_REQUIRED;

        if (camBadge) {
            camBadge.className = 'badge badge-light text-dark font-weight-bold';
            camBadge.innerHTML = '<i class="fas fa-video text-muted mr-1"></i> Kamera Otomatis';
        }

        // Badge overlay di atas video
        const overlayText = document.getElementById('overlayText');
        if (overlayText) {
            overlayText.innerHTML = '<i class="fas fa-user-clock text-warning mr-1"></i> Posisikan Wajah';
        }
        const overlayBadge = document.getElementById('camOverlayBadge');
        if (overlayBadge) overlayBadge.style.opacity = '0.9';

        // Banner liveness -> kembalikan ke instruksi normal
        const alertBox = document.getElementById('livenessAlertBox');
        const alertText = document.getElementById('livenessInstructionText');
        // PERBAIKAN 26: saat liveness OFF, elemen banner memang tidak dirender
        // (lihat blok kondisional di markup atas), jadi alertBox/alertText bernilai null dan blok
        // ini otomatis tidak berbuat apa-apa. Tidak ada teks liveness yang
        // muncul kembali lewat jalur reset.
        if (alertBox) {
            // PERBAIKAN V31.3b (2026-11-04): JANGAN tulis ulang className penuh.
            // Kode lama menetapkan ulang seluruh daftar kelas tanpa "d-flex",
            // sehingga wajah SVG (inline-block) dan teks jatuh ke bawah dan
            // menyusun diri sebagai blok -> terlihat seperti teks bertumpuk
            // dan wajah pindah baris. Sekarang hanya kelas warna yang diubah.
            alertBox.classList.remove('alert-success', 'alert-danger');
            alertBox.classList.add('alert-warning', 'border-warning');
            alertBox.classList.add('d-flex', 'align-items-center', 'justify-content-center');
            alertBox.classList.remove('text-center');
        }
        if (alertText && LIVENESS_REQUIRED) {
            // PERBAIKAN V31.3 (2026-11-04): "GELENGKAN KEPALA" DIHAPUS.
            // Detektor yang benar-benar dipakai adalah YAW (putaran horizontal
            // kepala) — lihat checkHeadTurn(). Geleng adalah gerakan BOLAK-BALIK,
            // bukan menoleh satu arah, sehingga instruksinya menyesatkan.
            //
            // PERBAIKAN V31.3b: isi #livenessInstructionText HANYA teks (tidak
            // lagi memuat <span> yang ditumpuk). Menulis innerHTML di sini aman
            // karena tidak ada wajah SVG di dalamnya — wajah ada di luar span ini.
            alertText.innerHTML = 'LIVENESS CHECK: Silakan <u>BERKEDIP</u> atau <u>MENOLEH</u> sedikit ke kiri / kanan';
        }

        // PERBAIKAN (2026-11-03): reset TEKS placeholder juga.
        // Kalau tidak, placeholder masih menampilkan teks basi
        // "Kamera Aktif" (atau "Menyiapkan Kamera...") saat tampil lagi
        // karena alasan lain — user akan melihat informasi yang salah.
        // Teks netral supaya tick berikutnya bebas menulis alasan yang tepat.
        const ph = document.getElementById('cameraPlaceholder');
        const phIcon = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc = document.getElementById('placeholderDesc');
        if (phIcon) phIcon.className = 'fas fa-map-marker-alt fa-3x text-warning mb-3';
        if (phTitle) phTitle.innerText = 'Memeriksa Area Kerja...';
        if (phDesc) phDesc.innerText = 'Mohon tunggu, sedang memeriksa posisi Anda terhadap area kerja.';
    }

    async function startCamera() {
        // ATURAN KAMERA (ditetapkan user 2026-11-03):
        //   Kamera HANYA boleh menyala kalau SEKALIGUS:
        //     (1) GPS terverifikasi DI DALAM area kerja  -> gpsVerified === true
        //     (2) waktu sekarang DI DALAM window absensi -> inWindow    === true
        //
        // Guard ini adalah pertahanan TERAKHIR: dipakai juga untuk tombol
        // "Mulai Kamera" / retryCamera(), supaya tombol manual pun tidak bisa
        // memaksa kamera nyala di luar area atau di luar window.
        //
        // PERBAIKAN (2026-11-03): hitung ULANG `inWindow` di sini.
        // Sebelumnya `inWindow` hanya dihitung oleh evaluateCameraGate() yang
        // jalan tiap detik. Kalau user menekan tombol "Mulai Kamera" tepat
        // setelah window tertutup (atau setelah halaman dormisi beberapa
        // menit), nilai `inWindow` masih yang LAMA -> tombol manual bisa
        // menyalakan kamera di luar window. Sekarang dicek fresh.
        const nowMsGate = Date.now();
        inWindow = (nowMsGate >= clockInStartMs && nowMsGate <= clockInEndMs)
                || (nowMsGate >= clockOutStartMs && nowMsGate <= clockOutEndMs);

        if (!gpsVerified) {
            cameraActive = false;
            showCameraPlaceholder();
            applyGateNotice(false, inWindow);
            return;
        }
        if (!inWindow) {
            cameraActive = false;
            showCameraPlaceholder();
            applyGateNotice(true, false);
            return;
        }

        // Catatan: JANGAN pakai cameraStarting/cameraFailed sebagai penahan di sini.
        // Kalau prompt izin dibiarkan menggantung, getUserMedia tidak pernah resolve
        // dan flag itu terkunci -> tombol "Mulai Kamera" jadi tidak bisa dipakai.
        if (cameraActive) return;

        // PERBAIKAN (2026-11-03): kalau getUserMedia SEDANG berjalan,
        // jangan mulai yang kedua (browser akan error NotReadableError
        // karena kamera dipakai 2x).
        if (cameraStarting) return;

        // Kalau sedang/start ulang, abort stream sebelumnya supaya tidak
        // memicu NotReadableError (kamera dipakai 2x).
        //
        // PERBAIKAN (2026-11-03): Dulu hanya dicek `srcObject.active`.
        // Masalahnya: `MediaStream.active` tetap TRUE walaupun semua track
        // sudah `ended` (setelah stopCamera() memanggil track.stop()).
        // Akibatnya setelah user keluar area lalu masuk lagi, startCamera()
        // keluar lebih awal di baris ini -> kamera TIDAK pernah menyala
        // kembali, padahal user sudah kembali ke dalam area.
        //
        // Sekarang dicek track-nya secara nyata: kalau track sudah ended,
        // stream lama dibersihkan dan kamera boleh dimulai ulang.
        const existingVideo = document.getElementById('webcam');
        if (existingVideo && existingVideo.srcObject) {
            const oldTracks = existingVideo.srcObject.getVideoTracks
                ? existingVideo.srcObject.getVideoTracks()
                : [];
            const hasLiveTrack = oldTracks.some(t => t && t.readyState === 'live');
            if (hasLiveTrack) {
                return; // kamera benar-benar masih hidup -> jangan start dua kali
            }
            // Track sudah mati (ended). Bersihkan supaya startCamera() bisa
            // membuat stream baru.
            try {
                oldTracks.forEach(t => { if (t && typeof t.stop === 'function') t.stop(); });
                existingVideo.srcObject = null;
            } catch (e) {
                // abaikan, tetap lanjut start
            }
        }

        const ph = document.getElementById('cameraPlaceholder');
        const phIcon = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc = document.getElementById('placeholderDesc');

        // PERBAIKAN V33 (2026-11-05): <video id="webcam"> hanya ada di DOM
        // kalau Blade merender blok kamera (bukan saat presensi sudah
        // 'completed'). evaluateCameraGate() tetap berjalan tiap detik dan
        // SELALU memanggil startCamera(), sehingga pada kondisi itu
        // `document.getElementById('webcam')` mengembalikan null dan baris
        // `video.srcObject = stream` melempar:
        //   TypeError: Cannot set properties of null (setting 'srcObject')
        // Guard di bawahstop crash tanpa merusak alur auto-presensi.
        //
        // Catatan: video diambil ULANG di sini (bukan hanya di baris atas)
        // karena elemen bisa di-re-render / di-mount ulang oleh DOM.
        const video = document.getElementById('webcam');
        if (!video) {
            cameraActive = false;
            cameraStarting = false;
            cameraFailed = false;
            cameraRetryAfter = 0;
            // Jangan tampilkan placeholder kamera kalau memang tidak ada
            // elemen video (mis. presensi hari ini sudah selesai).
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraFailed = true;
            cameraRetryAfter = Date.now() + CAMERA_RETRY_COOLDOWN_MS;
            showCameraPlaceholder();
            if (phIcon) phIcon.className = 'fas fa-camera-slash fa-3x text-danger mb-3';
            if (phTitle) phTitle.innerText = 'Kamera Tidak Didukung Browser';
            if (phDesc) phDesc.innerText = 'Browser ini tidak mendukung akses kamera. Gunakan Chrome / Edge versi terbaru dan pastikan halaman diakses lewat HTTPS (bukan HTTP).';
            return;
        }

        // PERBAIKAN: tandai sedang memulai, supaya tick berikutnya tidak
        // memanggil startCamera() lagi dan tidak menimpa placeholder.
        cameraStarting = true;

        // Beri tahu user bahwa kamera sedang disiapkan (bukan "Memeriksa Area").
        showCameraPlaceholder();
        if (phIcon) phIcon.className = 'fas fa-video fa-3x text-warning mb-3';
        if (phTitle) phTitle.innerText = 'Menyiapkan Kamera...';
        if (phDesc) phDesc.innerText = 'Mohon tunggu, sedang meminta akses kamera. Jika browser menampilkan permintaan izin, pilih "Izinkan".';

        // TIMEOUT: getUserMedia() TIDAK PERNAH resolve kalau user menutup/mengabaikan
        // prompt izin. Tanpa timeout, cameraStarting terkunci true selamanya dan
        // placeholder menggantung di "Menyiapkan Kamera...".
        //
        // CATATAN: nilai CAMERA_PERMISSION_TIMEOUT_MS sekarang dideklarasikan
        // di blok state global (lihat deklarasi state di atas), supaya bisa
        // dipakai ulang tanpa duplikasi.

        // Simpan promise mentah supaya kalau timeout dulu yang terjadi, stream
        // yang akhirnya tiba tetap bisa dihentikan (jangan sampai kamera nyala
        // diam-diam tanpa UI-nya).
        let rawStreamPromise = null;

        try {
            // Constraint dibuat "ideal" (bukan nilai tetap) supaya tidak memicu
            // OverconstrainedError pada perangkat yang tidak mendukung 640x480.
            // Samakan dengan modul FaceRecognition (public/js/face-recognition.js).
            rawStreamPromise = navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 640 },
                    height: { ideal: 480 }
                },
                audio: false
            });

            const stream = await withTimeout(
                rawStreamPromise,
                CAMERA_PERMISSION_TIMEOUT_MS,
                'IZIN_KAMERA_TIMEOUT'
            );
            video.srcObject = stream;
            video.setAttribute('playsinline', 'true');
            video.muted = true;
            cameraActive = true;
            cameraFailed = false;
            cameraRetryAfter = 0;
            // PERBAIKAN KRITIS (2026-11-03): placeholder HARUS hilang di sini.
            // Sebelumnya hanya 'ph.classList.add(\'d-none\')'. Padahal elemen
            // ini punya class "d-flex", dan di sb-admin-2.min.css .d-flex
            // (pos 110098) muncul SETELAH .d-none (pos 109835). Keduanya
            // !important dengan specificity sama -> source order menang,
            // .d-flex tetap berlaku -> placeholder tetap menutupi video.
            // Efeknya user hanya melihat ikon + "Kamera Aktif", bukan
            // kamera real. Inline !important mengalahkan aturan stylesheet.
            hideCameraPlaceholder();
            // PERBAIKAN (2026-11-03): placeholder disembunyikan TAPI teksnya
            // dibiarkan apa adanya. Kalau nanti placeholder tampil lagi
            // (mis. karena kamera dimatikan lalu gagal start), user akan
            // melihat teks basi "Menyiapkan Kamera..." yang menyesatkan —
            // persis keluhan yang dilaporkan.
            // Sekarang placeholder diset ke status AKTIF saat kamera nyala.
            if (phIcon) phIcon.className = 'fas fa-video fa-3x text-success mb-3';
            if (phTitle) phTitle.innerText = 'Kamera Aktif';
            if (phDesc) phDesc.innerText = 'Kamera siap. Posisikan wajah di dalam bingkai untuk verifikasi.';
            const camBadge = document.getElementById('cameraBadge');
            if (camBadge) {
                camBadge.className = 'badge badge-success font-weight-bold';
                camBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Kamera Aktif';
            }

            video.onloadedmetadata = () => {
                video.play().catch(() => {});
                detectFaceLoop();
            };
            // Pastikan play() dijalankan walau metadata sudah siap sebelum handler dipasang.
            if (video.readyState >= 2) {
                video.play().catch(() => {});
                detectFaceLoop();
            }
        } catch (err) {
            // PERBAIKAN: sebelumnya error ditelan diam-diam sehingga placeholder
            // tertahan di "Memeriksa Area Lokasi Kerja..." tanpa penjelasan.
            cameraActive = false;
            cameraFailed = true;
            // Set cooldown: jangan mencoba ulang setiap detik (popup izin
            // browser akan spam). Setelah cooldown habis, tick akan mencoba
            // otomatis lagi — jadi kamera tidak pernah terkunci permanen.
            cameraRetryAfter = Date.now() + CAMERA_RETRY_COOLDOWN_MS;
            showCameraPlaceholder();

            // Kalau timeout duluan, stream mungkin sudah terlanjur arrives.
            // Hentikan biar kamera tidak nyala diam-diam tanpa kontrol UI.
            if (rawStreamPromise && err && err.name === 'IZIN_KAMERA_TIMEOUT') {
                rawStreamPromise
                    .then(function (lateStream) {
                        try {
                            lateStream.getTracks().forEach(function (t) { t.stop(); });
                        } catch (e) { /* ignore */ }
                    })
                    .catch(function () { /* ignore */ });
            }

            const name = (err && err.name) ? err.name : '';
            if (name === 'IZIN_KAMERA_TIMEOUT') {
                if (phIcon) phIcon.className = 'fas fa-clock fa-3x text-warning mb-3';
                if (phTitle) phTitle.innerText = 'Menunggu Izin Kamera';
                if (phDesc) phDesc.innerText = 'Izin kamera belum diberikan. Klik ikon kamera di address bar browser, pilih "Izinkan", lalu tekan tombol Mulai Kamera.';
            } else if (name === 'NotAllowedError' || name === 'SecurityError') {
                if (phIcon) phIcon.className = 'fas fa-camera-slash fa-3x text-danger mb-3';
                if (phTitle) phTitle.innerText = 'Izin Kamera Ditolak';
                if (phDesc) phDesc.innerText = 'Klik ikon kamera di address bar browser, pilih "Izinkan", lalu tekan tombol Mulai Kamera.';
            } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                if (phIcon) phIcon.className = 'fas fa-camera-slash fa-3x text-danger mb-3';
                if (phTitle) phTitle.innerText = 'Kamera Tidak Ditemukan';
                if (phDesc) phDesc.innerText = 'Tidak ada kamera yang terdeteksi pada perangkat ini. Hubungkan kamera lalu muat ulang halaman.';
            } else if (name === 'NotReadableError') {
                if (phIcon) phIcon.className = 'fas fa-camera-slash fa-3x text-danger mb-3';
                if (phTitle) phTitle.innerText = 'Kamera Sedang Dipakai Aplikasi Lain';
                if (phDesc) phDesc.innerText = 'Tutup aplikasi lain yang memakai kamera (Zoom, Teams, dll), lalu tekan Mulai Kamera.';
            } else {
                if (phIcon) phIcon.className = 'fas fa-exclamation-triangle fa-3x text-danger mb-3';
                if (phTitle) phTitle.innerText = 'Kamera Gagal Dinyalakan';
                if (phDesc) phDesc.innerText = 'Terjadi kendala: ' + (name || String(err)) + '. Pastikan halaman diakses melalui HTTPS dan izin kamera aktif.';
            }
            console.error('[FaceAttendance] startCamera gagal:', err);
        } finally {
            // ==================================================================
            // PERBAIKAN INTI (2026-11-03): blok `finally` = JAMINAN state bersih.
            // ==================================================================
            // Dulu `cameraStarting = false` hanya ditulis di dalam catch dan
            // setelah stream siap. Kalau ada jalur kode yang tidak masuk ke
            // kedua tempat itu, flag `cameraStarting` tetap true selamanya, dan
            // karena evaluateCameraGate() punya `if (cameraStarting) return;`,
            // kamera terkunci permanen -> placeholder menggantung di
            // "Menyiapkan Kamera...".
            //
            // Dengan `finally`, flag ini SELALU dibersihkan, apa pun yang
            // terjadi (sukses, error, timeout, exception tak terduga).
            // Ini membuat mesin kamera self-healing.
            // ==================================================================
            cameraStarting = false;
        }
    }

    /**
     * Jalankan promise dengan batas waktu. Kalau lewat batas, reject dengan
     * error bernama IZIN_KAMERA_TIMEOUT supaya caller bisa menanganinya.
     */
    function withTimeout(promise, ms, timeoutName) {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                const e = new Error('Izin kamera belum diberikan dalam ' + Math.round(ms / 1000) + ' detik.');
                e.name = timeoutName || 'TIMEOUT';
                reject(e);
            }, ms);

            promise.then(
                (val) => { clearTimeout(timer); resolve(val); },
                (err) => { clearTimeout(timer); reject(err); }
            );
        });
    }

    // -------------------------------------------------------------------------
    // LIVENESS OPSI 1 (2026-11-04) — kedip yang lebih toleran & geleng yang stabil
    //
    // MASALAH YANG DIPERBAIKI
    //  1. Kedip sering "tidak terbaca". Kedip manusia hanya 100-400 ms. Kode
    //     lama butuh satu frame TEPAT saat mata menutup. Kalau frame itu
    //     terlewat (kamera 15 fps, wajah agak jauh), kedip hilang walaupun
    //     user sudah berkedip.
    //  2. Ambang `Math.min(baseline*0.55, 0.21)` terlalu tajam untuk mata
    //     sipit, dan tidak memperhitungkan jarak wajah ke kamera.
    //  3. Geleng tidak konsisten karena ambangnya PIKSEl MENTAH (`jawWidth<40`)
    //     dan rasio tetap (0.36/0.64). Wajah jauh -> tidak pernah terbaca.
    //
    // PENDEKATAN BARU
    //  A. Semua ukuran dibuat RELATIF terhadap lebar wajah (jarak mata luar
    //     kiri-ke-kanan), bukan piksel mentah. Jadi kamera jauh/dekat sama saja.
    //  B. Kedip dihitung dengan STATE MACHINE + toleransi beberapa frame,
    //     bukan satu frame. Kedip lambat (sampai ~600 ms) tetap terbaca.
    //  C. Geleng memakai sudut yaw relatif terhadap posisi netral yang
    //     diukur sendiri, dengan zona mati dan histeresis. Tidak ada lagi
    //     `jawWidth<40` yang membuang wajah kecil diam-diam.
    //
    // Yang TIDAK diubah: liveness tetap hanya diperiksa bila LIVENESS_REQUIRED
    // (toggle ON), dan tetap satu tantangan saja (kedip ATAU geleng).
    // -------------------------------------------------------------------------
    let eyeBaseline = null;       // rasio tinggi/lebar mata saat terbuka
    let eyeClosedFrames = 0;      // berapa frame mata terlihat menutup
    let blinkCount = 0;           // jumlah kedip valid
    const BLINK_REQUIRED = 1;     // minimal 1 kedip valid

    // Toleransi: minimal 1 frame menutup, maksimal 30 frame (~2 detik).
    // Kalau lebih dari itu, dianggap bukan kedip (mata memang terpejam/ngantuk)
    // dan state di-reset supaya tidak "terbaca" saat mata akhirnya dibuka.
    const BLINK_MIN_CLOSED_FRAMES = 1;
    const BLINK_MAX_CLOSED_FRAMES = 30;

    // Mata dianggap menutup bila rasio tinggi/lebar turun di bawah 62% dari
    // baseline (turun >=38%). Dulu 55% (turun 45%) — terlalu tajam.
    const BLINK_CLOSE_RATIO = 0.62;
    // Pagar bawah absolut yang LONGGAR; hanya untuk mencegah rasio mustahil.
    const BLINK_ABS_FLOOR = 0.10;

    // Lebar wajah untuk normalisasi (dalam piksel, dihitung tiap frame)
    function getFaceWidth(landmarks) {
        try {
            const p = landmarks.positions;
            // 0 = sudut rahang kiri, 16 = sudut rahang kanan
            return Math.abs(p[16].x - p[0].x);
        } catch (e) {
            return 0;
        }
    }

    /**
     * Rasio bukaan mata: tinggi kelopak / lebar mata.
     * Sengaja TIDAK memakai lebar wajah, karena rasio ini sudah bebas skala
     * (pembilang dan penyebut sama-sama piksel pada objek yang sama).
     */
    function getEyeOpenRatio(eye) {
        const d1 = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
        const d2 = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
        const d3 = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
        if (!isFinite(d3) || d3 <= 0) return null;
        const r = (d1 + d2) / (2.0 * d3);
        if (!isFinite(r) || r <= 0) return null;
        return r;
    }

    function checkEyeBlink(landmarks) {
        let leftEye, rightEye;
        try {
            leftEye = landmarks.getLeftEye();
            rightEye = landmarks.getRightEye();
        } catch (e) {
            return false;
        }
        if (!leftEye || !rightEye || leftEye.length < 6 || rightEye.length < 6) return false;

        const lr = getEyeOpenRatio(leftEye);
        const rr = getEyeOpenRatio(rightEye);
        if (lr === null || rr === null) return false;

        // Ambil rasio TERBESAR dari dua mata. Kalau satu mata tertutup/tertutup
        // rambut, mata yang lain masih bisa membuktikan kedipan.
        const ratio = Math.max(lr, rr);

        // -- Baseline belum ada: rekam posisi "mata terbuka" sekarang --
        if (eyeBaseline === null) {
            eyeBaseline = ratio;
            eyeClosedFrames = 0;
            return false;
        }

        // -- Fase 1: mata menutup --
        const closedThreshold = Math.max(eyeBaseline * BLINK_CLOSE_RATIO, BLINK_ABS_FLOOR);
        if (ratio < closedThreshold) {
            // Hitung frame menutup. Ini yang membuat kedip lambat tetap
            // terbaca: kita tidak butuh satu frame "sempurna", cukup
            // beberapa frame berturut-turut di bawah ambang.
            eyeClosedFrames++;
            return false;
        }

        // -- Fase 2: mata sudah membuka kembali --
        if (eyeClosedFrames >= BLINK_MIN_CLOSED_FRAMES && eyeClosedFrames <= BLINK_MAX_CLOSED_FRAMES) {
            // Kedip sah. Baseline diperbarui pelan ke posisi terbuka sekarang
            // supaya perubahan cahaya tidak membuat ambang melenceng.
            eyeBaseline = eyeBaseline * 0.6 + ratio * 0.4;
            eyeClosedFrames = 0;
            blinkCount++;
            return blinkCount >= BLINK_REQUIRED;
        }

        // Terlalu lama menutup (kemungkinan mata memang terpejam) -> reset
        // tanpa dihitung, dan bangun ulang baseline ke kondisi sekarang.
        eyeClosedFrames = 0;
        eyeBaseline = eyeBaseline * 0.7 + ratio * 0.3;
        return false;
    }

    // ---- Geleng kepala: pakai yaw relatif, bukan rasio tetap ----
    let yawNeutral = null;          // yaw saat wajah dianggap netral
    let yawNeutralSamples = 0;      // berapa sampel dipakai membentuk netral
    let yawExceededFrames = 0;      // frame berturut-turut melewati ambang
    let headTurnDone = false;       // latch: sudah pernah lolos (tidak bolak-balik)

    // Sudut yaw kasar dari geometri wajah, bebas skala:
    //   rasio = posisi hidung di antara dua sudut rahang (0..1)
    //   yaw   = (rasio - 0.5) * 2   -> negatif = menoleh ke kiri, positif = ke kanan
    function getYawRatio(landmarks) {
        try {
            const p = landmarks.positions;
            const leftJaw = p[0], rightJaw = p[16], noseTip = p[30];
            const jawWidth = Math.abs(rightJaw.x - leftJaw.x);
            if (!isFinite(jawWidth) || jawWidth <= 0) return null;
            const ratio = (noseTip.x - leftJaw.x) / jawWidth;
            if (!isFinite(ratio)) return null;
            return (ratio - 0.5) * 2;
        } catch (e) {
            return null;
        }
    }

    function checkHeadTurn(landmarks) {
        if (headTurnDone) return true;

        const yaw = getYawRatio(landmarks);
        if (yaw === null) return false;

        // -- Bentuk posisi netral dari beberapa sampel awal --
        // Pakai rata-rata berjalan supaya satu frame goyang tidak menggeser
        // acuan. Setelah 10 sampel, netral dikunci (tidak drift lagi).
        if (yawNeutralSamples < 10) {
            yawNeutral = yawNeutral === null ? yaw : (yawNeutral * 0.8 + yaw * 0.2);
            yawNeutralSamples++;
            return false;
        }

        const delta = yaw - yawNeutral;

        // Zona mati 0.11 (~6 derajat) supaya gerakan mikro tidak dihitung.
        // PERBAIKAN V31.3 (2026-11-04): ambang lulus DITURUNKAN dari 0.26 (~15
        // derajat) ke 0.17 (~10 derajat). Dengan 0.26 user harus memutar leher
        // cukup jauh, dan banyak yang gagal berulang lalu mengira sistem rusak.
        // 0.17 masih di atas zona mati 0.11 dan masih jauh di luar gerakan
        // mikro posisi duduk, jadi tidak longgar berlebihan.
        // Tetap butuh 2 frame berturut-turut (histeresis) agar noise tidak lolos.
        const DEAD_ZONE = 0.11;
        const PASS_THRESHOLD = 0.17;
        const PASS_FRAMES = 2;

        if (Math.abs(delta) >= PASS_THRESHOLD) {
            yawExceededFrames++;
            if (yawExceededFrames >= PASS_FRAMES) {
                headTurnDone = true;
                return true;
            }
            return false;
        }

        // Masuk zona mati / gerakan kecil -> reset hitungan frame
        if (Math.abs(delta) < DEAD_ZONE) {
            yawExceededFrames = 0;
        }
        return false;
    }

    /** Reset state liveness (dipakai saat ganti karyawan / kamera diulang). */
    function resetLivenessState() {
        eyeBaseline = null;
        eyeClosedFrames = 0;
        blinkCount = 0;
        yawNeutral = null;
        yawNeutralSamples = 0;
        yawExceededFrames = 0;
        headTurnDone = false;
        blinkDetected = false;
        headTurnDetected = false;
    }
    /**
     * Capture wajah dari video ke canvas, lalu return base64 JPEG (tanpa prefix data:).
     * Crop area tengah video agar fokus ke wajah.
     */
    function captureFaceImage() {
        try {
            const video = document.getElementById('webcam');
            const canvas = document.getElementById('faceCaptureCanvas');
            if (!video || !canvas || !video.videoWidth || !video.videoHeight) return null;

            const ctx = canvas.getContext('2d');
            const vw = video.videoWidth;
            const vh = video.videoHeight;

            // Crop sisi tengah (rasio 1:1) area video
            const side = Math.min(vw, vh);
            const sx = (vw - side) / 2;
            const sy = (vh - side) / 2;

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(video, sx, sy, side, side, 0, 0, canvas.width, canvas.height);

            // Kualitas 0.8 -> base64 sekitar 30-80KB
            const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
            return dataUrl;
        } catch (err) {
            console.warn('Gagal capture wajah:', err);
            return null;
        }
    }

    async function detectFaceLoop() {
        const video = document.getElementById('webcam');
        const alertBox = document.getElementById('livenessAlertBox');
        const alertText = document.getElementById('livenessInstructionText');
        const overlayText = document.getElementById('overlayText');
        const guideBox = document.getElementById('scannerGuide');

        // PERBAIKAN V35 (2026-11-05): 'expired' (window Clock Out sudah lewat)
        // juga harus menghentikan loop deteksi — sama seperti 'completed'.
        if (!video || video.paused || video.ended || isScanning
            || nextAction === 'completed' || nextAction === 'expired') return;

        if (!isModelLoaded) {
            setTimeout(detectFaceLoop, 400);
            return;
        }

        const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks(true).withFaceDescriptor();

        if (detection) {
            const landmarks = detection.landmarks;

            // PERBAIKAN 25: blok tantangan hanya berjalan bila liveness ON.
            if (LIVENESS_REQUIRED && !livenessVerified) {
                const isBlink = checkEyeBlink(landmarks);
                const isTurn = checkHeadTurn(landmarks);

                if (isBlink) {
                    blinkDetected = true;
                    livenessVerified = true;
                } else if (isTurn) {
                    headTurnDetected = true;
                    livenessVerified = true;
                } else {
                    // PERBAIKAN OPSI 1: beri umpan balik yang jelas & bergerak,
                    // supaya user tahu sistem masih bekerja. Dulu teksnya
                    // statis "Silakan Kedip / Geleng" sehingga saat kedip
                    // pelan user mengira tidak terbaca sama sekali.
                    if (overlayText) {
                        const petunjuk = (blinkCount === 0 && yawExceededFrames === 0)
                            ? 'Silakan Berkedip atau Menoleh Sedikit'
                            : 'Tahan sebentar...';
                        overlayText.innerHTML = '<i class="fas fa-eye text-warning mr-1"></i> ' + petunjuk;
                    }
                }
            }

            if (livenessVerified) {
                // PERBAIKAN (2026-11-03): latch isScanning TIDAK boleh dipasang
                // sebelum submit sukses. Dulu `isScanning = true` ditulis di
                // sini, lalu `detectFaceLoop` langsung `return` karena
                // `isScanning` true. Kalau submit GAGAL (server error / DB
                // error), flag di-reset LATE di dalam .catch/.then - tapi
                // karena `return` sudah keluar dari loop, tidak ada loop yang
                // terjadwal ulang => layar beku total, user tidak bisa tries
                // lagi. Perbaikan: pakai flag "submitSedangBerjalan" yang
                // di-reset di SEMUA jalur (sukses/gagal), dan JANGAN return
                // permanen dari loop.
                if (!isScanning) {
                    isScanning = true;
                    if (guideBox) guideBox.classList.add('liveness-ok');

                    if (alertBox) {
                        // PERBAIKAN V31.3b: jangan timpa className penuh — lihat
                        // catatan di fungsi reset banner. Hanya ubah warna.
                        alertBox.classList.remove('alert-warning', 'alert-danger', 'border-warning');
                        alertBox.classList.add('alert-success', 'font-weight-bold');
                        alertBox.classList.add('d-flex', 'align-items-center', 'justify-content-center');
                    }
                    // PERBAIKAN 25: jangan menulis "LIVENESS TERVERIFIKASI" saat
                    // liveness dimatikan admin -- itu informasi palsu.
                    if (alertText) alertText.innerHTML = !LIVENESS_REQUIRED
                        ? '<i class="fas fa-check-circle mr-1"></i> Wajah Dikenali - Mengirim Presensi...'
                        : '<i class="fas fa-check-circle mr-1"></i> LIVENESS TERVERIFIKASI! (' + (blinkDetected ? 'Kedipan Mata' : 'Menoleh') + ' Terdeteksi) - Mengirim Presensi...';
                    if (overlayText) overlayText.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> Wajah Terverifikasi';

                    const descriptorArray = Array.from(detection.descriptor);

                    // Capture wajah (base64) + koordinat GPS untuk disimpan ke rekap absensi
                    const faceImage = captureFaceImage();

                    fetch("{{ route('face.attendance.store') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            npk: npk,
                            descriptor: descriptorArray,
                            latitude: currentLat,
                            longitude: currentLng,
                            accuracy: currentAccuracy,
                            face_image: faceImage,
                            // PERBAIKAN V30/V31: token sekali pakai dari
                            // server. Presensi TIDAK PERNAH ditolak karena
                            // token; hanya request TANPA token sama sekali
                            // yang ditandai is_suspicious.
                            nonce: Array.isArray(window.__attNonces) && window.__attNonces.length
                                ? window.__attNonces.shift()   // pakai & buang
                                : null
                        })
                    })
                    .then(res => res.json().catch(() => ({
                        // Server membalas non-JSON (mis. halaman error HTML 500).
                        success: false,
                        message: 'Server membalas dengan status ' + res.status + ' dan bukan JSON. Periksa storage/logs/laravel.log.'
                    })).then(data => ({ ok: res.ok, data })))
                    .then(({ ok, data }) => {
                        if (data && data.success) {
                            // PERBAIKAN 23 (2026-11-02): presensi tersimpan ->
                            // tandai flag lokal & matikan kamera SEKARANG, jangan
                            // tunggu reload. Tanpa ini kamera tetap menyala
                            // selama jeda antara "berhasil" dan reload, persis
                            // seperti yang dilaporkan user.
                            // PERBAIKAN V31: server mengirim token baru setiap
                            // presensi berhasil, supaya aksi berikutnya di
                            // halaman yang sama tetap punya token sah.
                            if (typeof window.__attNonceRefill === 'function') {
                                window.__attNonceRefill(data.nonce_baru);
                            }
                            syncPresensiFlag(data.action_type);
                            // PERBAIKAN (2026-11-03): kalau presensi utama tersimpan
                            // tapi detail wajah gagal, server mengirim `warning`.
                            // Tampilkan sebagai peringatan (bukan error), supaya
                            // user tahu presensinya SUDAH masuk.
                            if (data.warning) {
                                showAlert({
                                    icon: 'warning',
                                    title: 'Presensi Tercatat (Ada Peringatan)',
                                    html: '<strong>' + (data.message || '') + '</strong><br><small>' + data.warning + '</small>'
                                }).then(() => {
                                    window.location.reload();
                                });
                                return;
                            }
                            showAlert({
                                icon: 'success',
                                title: 'Presensi Berhasil!',
                                html: '<strong>' + data.message + '</strong><br><small class="text-muted">Aksi: ' + (data.action_type || '') + '</small>',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            // ===========================================================
                            // PERBAIKAN V31.8 (2026-11-04) - KARYAWAN SUDAH KELUAR
                            // ===========================================================
                            // Server menolak dengan `inactive_employee` (PKWT.TKK
                            // terisi). Tampilkan alert yang jelas dan JANGAN
                            // jadwalkan ulang loop scan - mengulang tidak akan
                            // pernah berhasil dan hanya membebani kamera/server.
                            if (data && data.inactive_employee) {
                                showResignAlert({
                                    message: (data && data.message) ? data.message : 'Data kepegawaian Anda sudah tercatat keluar.',
                                    action : 'Presensi tidak dapat dikirim.',
                                    npk    : (data && data.npk) ? data.npk : null
                                });
                                // Berhenti total: matikan kamera & hentikan loop.
                                isScanning = false;
                                livenessVerified = !LIVENESS_REQUIRED;
                                resetLivenessState();
                                if (guideBox) guideBox.classList.remove('liveness-ok');
                                if (alertBox) {
                                    alertBox.classList.remove('alert-warning', 'alert-success', 'alert-danger', 'border-warning');
                                    alertBox.classList.add('alert-danger', 'font-weight-bold');
                                }
                                if (alertText) alertText.innerHTML = '<i class="fas fa-user-slash mr-1"></i> Karyawan Sudah Resign - Presensi Tidak Dikirim';
                                if (overlayText) overlayText.innerHTML = '<i class="fas fa-user-slash text-danger mr-1"></i> Presensi Ditolak';
                                return;
                            }

                            // PERBAIKAN: tampilkan pesan error yang SEBENARNYA dari
                            // server, termasuk detail kegagalan simpan ke database.
                            // Dulu kegagalan insert ditelan diam-diam sehingga user
                            // mengira presensi berhasil padahal data tidak tersimpan.
                            const msg = (data && data.message) ? data.message : 'Presensi gagal diproses.';
                            const detail = (data && data.error) ? ('\n\nDetail: ' + data.error) : '';
                            showAlert({
                                icon: 'error',
                                title: 'Presensi Gagal',
                                text: msg + detail
                            });
                            // Reset LOKAL (bukan confiar ke timer apa pun) lalu
                            // jadwalkan ulang loop supaya user bisa mencoba lagi.
                            isScanning = false;
                            livenessVerified = !LIVENESS_REQUIRED;
                            // PERBAIKAN OPSI 1: satu titik reset untuk semua
                            // state liveness (kedip + geleng + netral yaw).
                            resetLivenessState();
                            if (guideBox) guideBox.classList.remove('liveness-ok');
                            setTimeout(detectFaceLoop, 1500);
                        }
                    })
                    .catch(err => {
                        // PERBAIKAN: jangan diam saja. Tampilkan error jaringan agar
                        // user tahu presensi TIDAK tercatat.
                        console.error('[FaceAttendance] submit error:', err);
                        showAlert({
                            icon: 'error',
                            title: 'Gagal Mengirim Presensi',
                            text: (err && err.message) ? err.message : 'Kesalahan jaringan. Silakan coba lagi.'
                        });
                        isScanning = false;
                        livenessVerified = !LIVENESS_REQUIRED;
                        resetLivenessState();
                        if (guideBox) guideBox.classList.remove('liveness-ok');
                        setTimeout(detectFaceLoop, 1500);
                    });
                }
                // Jadwalkan frame berikutnya. Loop TIDAK berhenti permanen.
                setTimeout(detectFaceLoop, 300);
                return;
            }

            setTimeout(detectFaceLoop, 150);
        } else {
            // PERBAIKAN 25: saat liveness OFF jangan menjanjikan kedip/geleng,
            // cukup minta arahkan wajah -- itu memang yang dibutuhkan.
            if (overlayText) overlayText.innerHTML = LIVENESS_REQUIRED
                ? '<i class="fas fa-user-clock text-warning mr-1"></i> Posisikan Wajah'
                : '<i class="fas fa-user-clock text-warning mr-1"></i> Arahkan Wajah ke Kamera';
            // PERBAIKAN OPSI 1: state liveness TIDAK di-reset saat wajah
            // sesaat hilang dari frame. Dulu acuan netral dihapus di sini,
            // sehingga setiap wajah bergerak keluar-masuk frame user harus
            // mengulang dari awal -> geleng terasa "tidak pernah terbaca".
            setTimeout(detectFaceLoop, 300);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // PERBAIKAN V33 (2026-11-05): restoreCameraCardState() dihapus
        // bersamaan dengan card cameraCardBody. Kamera tidak lagi punya
        // state buka/tutup; card-nya selalu tampil.

        // PERBAIKAN (2026-11-03): kamera TIDAK lagi dinyalakan langsung di
        // sini. evaluateCameraGate() yang memutuskan, dan dia hanya menyalakan
        // kamera kalau DUA syarat terpenuhi: GPS terverifikasi di dalam area
        // DAN waktu sekarang di dalam window absensi.
        //
        // evaluateCameraGate() dipanggil langsung saat DOM siap supaya
        // kondisi segera dievaluasi (tidak menunggu 1 detik penuh), lalu
        // diulang tiap detik untuk mengevaluasi ulang area & waktu.
        evaluateCameraGate();
        setInterval(evaluateCameraGate, 1000);

        // Mini Map bersifat opsional: kalau Leaflet gagal dimuat, GPS & kamera
        // tetap jalan (di-guard di dalam initMiniMapProfile).
        initMiniMapProfile();

        loadModels();

        // GPS dijalankan untuk verifikasi area kerja. Callback suksesnya
        // (autoStartCameraOnGps) akan memicu evaluateCameraGate() lagi supaya
        // kamera langsung mencoba menyala kalau syarat waktunya juga terpenuhi.
        refreshLocation();
    });
</script>

{{-- PERBAIKAN V32.8 (2026-11-05): modal untuk memperbesar wajah yang
     TERCAPTURE saat scan (klik thumbnail di tabel Riwayat Scan). --}}
<div class="modal fade" id="faFaceZoomModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header py-2 bg-dark text-white">
                <h6 class="modal-title font-weight-bold mb-0" id="faFaceZoomTitle">
                    <i class="fas fa-camera mr-1"></i> Wajah Tercapture
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0 bg-dark">
                <img id="faFaceZoomImg" src="" alt="Wajah Tercapture" style="width:100%;display:block;">
            </div>
            <div class="modal-footer py-2 bg-dark border-0">
                <small id="faFaceZoomMeta" class="text-white-50 mr-auto"></small>
                <button type="button" class="btn btn-sm btn-outline-light" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    // PERBAIKAN V32.8 (2026-11-05): klik thumbnail wajah -> tampilkan besar.
    $(document).on('click', '.fa-scan-thumb', function (e) {
        e.preventDefault();
        var $t = $(this);
        $('#faFaceZoomImg').attr('src', $t.data('img') || '');
        $('#faFaceZoomTitle').html('<i class="fas fa-camera mr-1"></i> Wajah ' + ($t.data('type') || ''));
        $('#faFaceZoomMeta').text('Jam: ' + ($t.data('time') || '-'));
        $('#faFaceZoomModal').modal('show');
    });
</script>

</body>
</html>
