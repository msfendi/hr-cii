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
        animation: pulseWarning 1.8s infinite;
    }
    @keyframes pulseWarning {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); }
        100% { transform: scale(1); }
    }
</style>

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    @if(Session::has('face_attendance_npk') || auth()->check())
        @include('layout.sidebar')
    @endif

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @if(Session::has('face_attendance_npk') || auth()->check())
                @include('layout.navbar')
            @endif

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-camera text-primary mr-2"></i> Presensi Wajah & GPS
                    </h1>
                    <div class="mt-2 mt-sm-0">
                        <a href="{{ route('face.attendance.rekap') }}" class="btn btn-sm btn-info shadow-sm rounded-pill px-3 mr-2">
                            <i class="fas fa-history fa-sm text-white-50 mr-1"></i> Rekap Presensi
                        </a>
                        <form action="{{ route('face.attendance.logout-session') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm rounded-pill px-3">
                                <i class="fas fa-sign-out-alt fa-sm mr-1"></i> Ganti Karyawan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Alert Status Kehadiran Hari Ini -->
                <div class="alert alert-{{ $hasClockIn && $hasClockOut ? 'success' : ($hasClockIn ? 'info' : ($hasClockOut ? 'warning' : 'secondary')) }} border-left-{{ $hasClockIn && $hasClockOut ? 'success' : ($hasClockIn ? 'info' : ($hasClockOut ? 'warning' : 'secondary')) }} shadow-sm mb-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="font-weight-bold text-uppercase d-block small">Status Presensi Hari Ini</span>
                            <h5 class="mb-0 font-weight-bold">
                                @if($hasClockIn && $hasClockOut)
                                    <i class="fas fa-check-double text-success mr-2"></i> Presensi Lengkap
                                @elseif($hasClockIn)
                                    <i class="fas fa-sign-in-alt text-info mr-2"></i> Sudah Clock In, Siap Clock Out
                                @elseif($hasClockOut)
                                    <i class="fas fa-sign-out-alt text-warning mr-2"></i> Sudah Clock Out
                                @else
                                    <i class="fas fa-clock text-secondary mr-2"></i> Belum Clock In
                                @endif
                                <span class="font-weight-normal text-muted small ml-1">({{ $statusText }})</span>
                            </h5>
                        </div>
                        <div class="text-right d-none d-sm-block">
                            {{-- Jam digital DIHAPUS (permintaan user 2026-11-03): mengganggu
                                 dan tick per detiknya ikut memicu error. Tanggal tetap. --}}
                            <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.9rem;">
                                {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Kolom Informasi Karyawan & Jadwal Shift -->
                    <div class="col-lg-4 col-md-5 mb-4">
                        <div class="card shadow border-left-primary h-100">
                            <div class="card-header py-3 bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-user-circle mr-1"></i> Data Karyawan
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="avatar-circle mx-auto mb-2 bg-gradient-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 70px; height: 70px; font-size: 1.8rem; font-weight: bold;">
                                        {{ strtoupper(substr($biodata->NAMA_KARYAWAN ?? 'U', 0, 1)) }}
                                    </div>
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
                                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.8rem;">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th style="width:38px;" class="text-center">#</th>
                                                    <th style="width:80px;">Jam</th>
                                                    <th style="width:100px;" class="text-center">Metode</th>
                                                    <th class="text-center">Keterangan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($todayLogs as $i => $log)
                                                @php
                                                    $logType = $log->calculated_type ?? ($log->inoutmode == 0 ? 'in' : 'out');
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
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @endif

                                <!-- Mini Map Geofencing Lokasi Karyawan -->
                                <div class="mt-3 pt-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted font-weight-bold text-uppercase">
                                            <i class="fas fa-map-marked-alt text-primary mr-1"></i> Mini Map Lokasi
                                        </small>
                                        <span id="miniMapProfileStatus" class="badge badge-light border small text-muted">Mencari GPS...</span>
                                    </div>
                                    <div id="miniMapProfile" class="rounded border shadow-sm" style="height: 180px; width: 100%; z-index: 1;"></div>
                                    <small id="miniMapAccuracyText" class="text-muted d-block text-center mt-1" style="font-size: 0.75rem;">Akurasi GPS: - meter</small>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kamera & Scanning Otomatis -->
                    <div class="col-lg-8 col-md-7 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 bg-gradient-primary text-white d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-white">
                                    <i class="fas fa-video mr-1"></i>
                                    @if($nextAction == 'clock_in')
                                        Kamera Presensi: Clock In (Masuk)
                                    @elseif($nextAction == 'clock_out')
                                        Kamera Presensi: Clock Out (Pulang)
                                    @else
                                        Presensi Selesai
                                    @endif
                                </h6>
                                {{-- Badge status KAMERA (bukan GPS). GPS punya badge sendiri
                                     di card Data Karyawan: miniMapProfileStatus. --}}
                                <span id="cameraBadge" class="badge badge-light text-dark font-weight-bold">
                                    <i class="fas fa-video text-muted mr-1"></i> Kamera Otomatis
                                </span>
                            </div>

                            <div class="card-body p-4 text-center">
                                @if($nextAction == 'completed')
                                    <div class="py-5">
                                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                        <h4 class="font-weight-bold text-gray-800">Presensi Hari Ini Selesai</h4>
                                        <p class="text-muted mb-4">
                                            Anda sudah melakukan Clock In dan Clock Out untuk hari ini.<br>
                                            Terima kasih atas kerja keras Anda!
                                        </p>
                                        <a href="{{ route('face.attendance.rekap') }}" class="btn btn-primary rounded-pill px-4">
                                            <i class="fas fa-history mr-1"></i> Lihat Rekap Bulanan
                                        </a>
                                    </div>
                                @else
                                    <div id="livenessAlertBox" class="alert alert-warning py-3 px-3 mb-3 text-center shadow-sm border-warning liveness-alert-box">
                                        <i class="fas fa-eye text-primary mr-2 fa-lg"></i>
                                        <span id="livenessInstructionText" class="font-weight-bold">
                                            LIVENESS CHECK: Silakan <u>KEDIPKAN MATA</u> atau <u>GELENGKAN KEPALA</u> ke kiri / kanan
                                        </span>
                                    </div>

                                    <div class="position-relative d-inline-block rounded overflow-hidden shadow-sm" style="max-width: 640px; width: 100%; height: 420px; background: #1a1c23;">
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

                                    <div class="mt-3 text-muted small">
                                        <i class="fas fa-info-circle mr-1 text-primary"></i>
                                        Sistem secara otomatis mendeteksi gerakan wajah asli (kedipan mata atau geleng kepala) untuk verifikasi kehadiran.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
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
    const hasClockIn = {{ $hasClockIn ? 'true' : 'false' }};
    const hasClockOut = {{ $hasClockOut ? 'true' : 'false' }};

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

    let initialNoseRatio = null;
    let blinkDetected = false;
    let headTurnDetected = false;

    // Mini Map Leaflet di Card Profil Karyawan
    let miniMapProfile = null;
    let userCircleProfile = null;
    let userMarkerProfile = null;
    let polyGroupProfile = null;

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

        if (activeLocations && activeLocations.length > 0) {
            let bounds = [];
            activeLocations.forEach(loc => {
                if (loc.polygon_geojson) {
                    try {
                        let geo = JSON.parse(loc.polygon_geojson);
                        let poly = L.geoJSON(geo, {
                            style: {
                                color: '#4e73df',
                                fillColor: '#4e73df',
                                fillOpacity: 0.3,
                                weight: 2
                            }
                        }).bindPopup('<b>' + loc.name + '</b><br>Area Presensi Resmi');
                        polyGroupProfile.addLayer(poly);
                        bounds.push(poly.getBounds());
                    } catch(e) {}
                }
            });
            if (bounds.length > 0) {
                let group = new L.featureGroup(bounds.map(b => L.rectangle(b)));
                miniMapProfile.fitBounds(group.getBounds());
            }
        }
    }

    function updateMiniMapProfile(lat, lng, accuracy, inside) {
        initMiniMapProfile();
        if (!miniMapProfile) return;

        if (userCircleProfile) miniMapProfile.removeLayer(userCircleProfile);
        if (userMarkerProfile) miniMapProfile.removeLayer(userMarkerProfile);

        let color = inside ? '#1cc88a' : '#e74a3b';

        userCircleProfile = L.circle([lat, lng], {
            radius: Math.max(accuracy, 15),
            color: color,
            fillColor: color,
            fillOpacity: 0.2,
            weight: 1
        }).addTo(miniMapProfile);

        userMarkerProfile = L.circleMarker([lat, lng], {
            radius: 6,
            color: '#ffffff',
            fillColor: color,
            fillOpacity: 1,
            weight: 2
        }).addTo(miniMapProfile).bindPopup('<b>Posisi Anda</b><br>' + (inside ? '<span class="text-success">Dalam Area Presensi</span>' : '<span class="text-danger">Di Luar Area</span>')).openPopup();

        let allGroup = L.featureGroup([polyGroupProfile, userCircleProfile]);
        miniMapProfile.fitBounds(allGroup.getBounds().pad(0.15));

        const statusBadge = document.getElementById('miniMapProfileStatus');
        const accuracyText = document.getElementById('miniMapAccuracyText');

        if (statusBadge) {
            if (inside) {
                statusBadge.className = 'badge badge-success small';
                statusBadge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Dalam Area';
            } else {
                statusBadge.className = 'badge badge-danger small';
                statusBadge.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Luar Area';
            }
        }
        if (accuracyText) {
            accuracyText.innerHTML = 'Akurasi GPS: <strong>±' + accuracy + '</strong> meter';
        }
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
            reasons.push({
                icon: 'fas fa-clock',
                title: 'Belum Memasuki Window Absensi',
                desc: 'Waktu sekarang belum masuk window clock in atau clock out. '
                    + 'Kamera dimatikan otomatis. Window absensi Anda: ' + windowText + '.'
            });
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
            if (reasons.length > 1) {
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

    function evaluateCameraGate() {
        const now = new Date();

        // ---- Hitung apakah sekarang berada di window absensi ----
        const t = now.getTime();
        const inClockInWindow  = (t >= clockInStartMs  && t <= clockInEndMs);
        const inClockOutWindow = (t >= clockOutStartMs && t <= clockOutEndMs);
        inWindow = inClockInWindow || inClockOutWindow;

        const phBtn = document.getElementById('placeholderBtnContainer');

        // ---- Tentukan apakah kamera BOLEH aktif ----
        const areaOk = gpsVerified;          // syarat 1: di dalam area kerja
        const timeOk = inWindow;             // syarat 2: di dalam window
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
        // Badge status GPS berada di card Data Karyawan (Mini Map Lokasi).
        // Badge card Kamera ("cameraBadge") TIDAK disentuh di sini —
        // kamera dan GPS adalah dua hal yang terpisah.
        const badge = document.getElementById('miniMapProfileStatus');
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
            if (badge) {
                badge.className = 'badge badge-danger';
                badge.innerHTML = 'GPS Tidak Didukung';
            }
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
                // PERBAIKAN: seluruh body callback dibungkus try/catch.
                // Jika ada satu baris saja yang error (mis. elemen tidak ada / polygon rusak),
                // exception tidak boleh mematikan sisa callback sebelum startCamera().
                try {
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
                    if (badge) {
                        badge.className = 'badge badge-success';
                        badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Area Kerja (±' + accuracy + 'm)';
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
                    if (badge) {
                        badge.className = 'badge badge-danger';
                        badge.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Di Luar Area Kerja';
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
                if (badge) {
                    badge.className = 'badge badge-light border small text-muted';
                    badge.innerHTML = 'GPS Tidak Tersedia';
                }
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
        livenessVerified = false;

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
        if (alertBox) {
            alertBox.className = 'alert alert-warning py-3 px-3 mb-3 text-center shadow-sm border-warning liveness-alert-box';
        }
        if (alertText) {
            alertText.innerHTML = 'LIVENESS CHECK: Silakan <u>KEDIPKAN MATA</u> atau <u>GELENGKAN KEPALA</u> ke kiri / kanan';
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

        const video = document.getElementById('webcam');
        const ph = document.getElementById('cameraPlaceholder');
        const phIcon = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc = document.getElementById('placeholderDesc');

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
    // LIVENESS: deteksi kedip & geleng kepala
    //
    // PERBAIKAN KRITIS (2026-11-03):
    // Dulu checkEyeBlink() memakai ambang EAR TETAP (< 0.21). Ambang tetap
    // itu salah: orang berkacamata /looking down / wajah kecil di frame bisa
    // punya EAR < 0.21 TANPA berkedip. Akibatnya liveness terverifikasi
    // palsu -> sistem langsung mengirim presensi. Itu menjelaskan screenshot
    // user: "LIVENESS TERVERIFIKASI!" padahal kamera belum tampil.
    //
    // FIX: pakai BASELINE. Kita rekam EAR saat mata terbuka (baseline),
    // lalu blink baru dianggap sah kalau EAR turun DRASTIS dari baseline
    // (menutup >= 55% dari jarak baseline) DAN kemudian kembali naik.
    // Dengan begitu, kedip harus benar-benar terjadi.
    // -------------------------------------------------------------------------
    let eyeBaseline = null;       // EAR saat mata terbuka
    let eyeClosedSeen = false;    // sudah pernah terdeteksi menutup
    let blinkCount = 0;           // jumlah kedip valid
    const BLINK_REQUIRED = 1;     // minimal 1 kedip valid

    function getEAR(eye) {
        const d1 = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
        const d2 = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
        const d3 = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
        return (d1 + d2) / (2.0 * d3);
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

        const ear = (getEAR(leftEye) + getEAR(rightEye)) / 2.0;
        if (!isFinite(ear) || ear <= 0) return false;

        // -- Baseline belum ada: rekam posisi "mata terbuka" sekarang --
        if (eyeBaseline === null) {
            eyeBaseline = ear;
            eyeClosedSeen = false;
            return false;
        }

        // -- Mata menutup: HARUS turun jauh dari baseline --
        // Turun minimal 45% dari baseline (dan absolut < 0.21 sebagai pagar
        // bawah tambahan). 45% dipilih supaya variasi kecil tidak dihitung.
        const closedThreshold = Math.min(eyeBaseline * 0.55, 0.21);
        if (ear < closedThreshold) {
            eyeClosedSeen = true;
            return false;   // belum sah, tunggu mata membuka kembali
        }

        // -- Mata kembali terbuka SETELAH sempat menutup -> itu kedip valid --
        if (eyeClosedSeen) {
            // Bangun kembali baseline ke posisi mata terbuka sekarang.
            eyeBaseline = eyeBaseline * 0.4 + ear * 0.6;
            eyeClosedSeen = false;
            blinkCount++;
            return blinkCount >= BLINK_REQUIRED;
        }

        // -- Mata tetap terbuka:/+ refres baseline pelan --
        eyeBaseline = eyeBaseline * 0.9 + ear * 0.1;
        return false;
    }

    function checkHeadTurn(landmarks) {
        const positions = landmarks.positions;
        const leftJaw = positions[0];
        const rightJaw = positions[16];
        const noseTip = positions[30];

        const jawWidth = Math.abs(rightJaw.x - leftJaw.x);
        if (jawWidth < 40) return false;

        const currentRatio = (noseTip.x - leftJaw.x) / jawWidth;

        if (initialNoseRatio === null) {
            initialNoseRatio = currentRatio;
            return false;
        }

        if (currentRatio < 0.36 || currentRatio > 0.64 || Math.abs(currentRatio - initialNoseRatio) > 0.16) {
            return true;
        }

        return false;
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

        if (!video || video.paused || video.ended || isScanning || nextAction === 'completed') return;

        if (!isModelLoaded) {
            setTimeout(detectFaceLoop, 400);
            return;
        }

        const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks(true).withFaceDescriptor();

        if (detection) {
            const landmarks = detection.landmarks;

            if (!livenessVerified) {
                const isBlink = checkEyeBlink(landmarks);
                const isTurn = checkHeadTurn(landmarks);

                if (isBlink) {
                    blinkDetected = true;
                    livenessVerified = true;
                } else if (isTurn) {
                    headTurnDetected = true;
                    livenessVerified = true;
                } else {
                    if (overlayText) overlayText.innerHTML = '<i class="fas fa-eye text-warning mr-1"></i> Silakan Kedip / Geleng';
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

                    if (alertBox) alertBox.className = 'alert alert-success py-3 px-3 mb-3 text-center shadow-sm font-weight-bold';
                    if (alertText) alertText.innerHTML = '<i class="fas fa-check-circle mr-1"></i> LIVENESS TERVERIFIKASI! (' + (blinkDetected ? 'Kedipan Mata' : 'Geleng Kepala') + ' Terdeteksi) - Mengirim Presensi...';
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
                            face_image: faceImage
                        })
                    })
                    .then(res => res.json().catch(() => ({
                        // Server membalas non-JSON (mis. halaman error HTML 500).
                        success: false,
                        message: 'Server membalas dengan status ' + res.status + ' dan bukan JSON. Periksa storage/logs/laravel.log.'
                    })).then(data => ({ ok: res.ok, data })))
                    .then(({ ok, data }) => {
                        if (data && data.success) {
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
                            livenessVerified = false;
                            blinkDetected = false;
                            headTurnDetected = false;
                            blinkCount = 0;
                            eyeBaseline = null;
                            eyeClosedSeen = false;
                            initialNoseRatio = null;
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
                        livenessVerified = false;
                        blinkDetected = false;
                        headTurnDetected = false;
                        blinkCount = 0;
                        eyeBaseline = null;
                        eyeClosedSeen = false;
                        initialNoseRatio = null;
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
            if (overlayText) overlayText.innerHTML = '<i class="fas fa-user-clock text-warning mr-1"></i> Posisikan Wajah';
            initialNoseRatio = null;
            setTimeout(detectFaceLoop, 300);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
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

</body>
</html>
