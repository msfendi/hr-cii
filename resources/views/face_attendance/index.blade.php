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
                            <span class="badge badge-light border px-3 py-2 font-weight-bold mr-1 text-primary shadow-sm" style="font-size: 0.95rem; font-family: monospace;">
                                <i class="fas fa-clock mr-1"></i> <span id="liveClockDisplay">--:--:-- WIB</span>
                            </span>
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

                                <div class="mb-2">
                                    <small class="text-muted font-weight-bold text-uppercase d-block">Jadwal Shift Hari Ini</small>
                                    <span class="badge badge-info px-2 py-1">
                                        <i class="fas fa-clock mr-1"></i> {{ $shift ? $shift->shift_name : 'Normal' }} ({{ substr($workStart, 0, 5) }} - {{ substr($workEnd, 0, 5) }})
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <small class="text-muted font-weight-bold text-uppercase d-block">Window Absensi</small>
                                    <div class="small mt-1">
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                            <span><i class="fas fa-sign-in-alt text-success mr-1"></i> Clock In:</span>
                                            <span class="font-weight-bold {{ $hasClockIn ? 'text-success' : 'text-dark' }}">
                                                {{ $clockInStart->format('H:i') }} - {{ $clockInEnd->format('H:i') }} WIB
                                                @if($hasClockIn) <i class="fas fa-check-circle ml-1"></i> @endif
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <span><i class="fas fa-sign-out-alt text-warning mr-1"></i> Clock Out:</span>
                                            <span class="font-weight-bold {{ $hasClockOut ? 'text-success' : 'text-dark' }}">
                                                {{ $clockOutStart->format('H:i') }} - {{ $clockOutEnd->format('H:i') }} WIB
                                                @if($hasClockOut) <i class="fas fa-check-circle ml-1"></i> @endif
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                @if(count($todayLogs) > 0)
                                <div class="mt-3">
                                    <small class="text-muted font-weight-bold text-uppercase d-block mb-1">Riwayat Scan Hari Ini</small>
                                    <ul class="list-group list-group-flush small">
                                        @foreach($todayLogs as $log)
                                        @php
                                            $logType = $log->calculated_type ?? ($log->inoutmode == 0 ? 'in' : 'out');
                                        @endphp
                                        <li class="list-group-item px-1 py-1 d-flex justify-content-between align-items-center bg-transparent">
                                            <span>
                                                @if($log->verifymode == 15)
                                                    <i class="fas fa-user-check text-primary mr-1" title="Face Recog"></i>
                                                @else
                                                    <i class="fas fa-fingerprint text-info mr-1" title="Fingerprint"></i>
                                                @endif
                                                {{ \Carbon\Carbon::parse($log->scan_date)->format('H:i:s') }}
                                            </span>
                                            <span class="badge badge-{{ $logType == 'in' ? 'success' : 'warning' }}">
                                                <i class="fas {{ $logType == 'in' ? 'fa-sign-in-alt' : 'fa-sign-out-alt' }} mr-1"></i>
                                                {{ $logType == 'in' ? 'Clock In' : 'Clock Out' }}
                                            </span>
                                        </li>
                                        @endforeach
                                    </ul>
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
                                <span id="gpsBadge" class="badge badge-light text-dark font-weight-bold">
                                    <i class="fas fa-spinner fa-spin text-warning mr-1"></i> Cek GPS...
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
                                                <button class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="checkGPS()">
                                                    <i class="fas fa-sync-alt mr-1"></i> Cek Ulang GPS
                                                </button>
                                            </div>
                                        </div>
                                    </div>

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
    let isGpsValid = false;
    let isModelLoaded = false;
    let isScanning = false;
    let livenessVerified = false;
    let cameraActive = false;
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

        miniMapProfile = L.map('miniMapProfile', {
            zoomControl: false,
            attributionControl: false
        }).setView([-6.2088, 106.8456], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(miniMapProfile);

        polyGroupProfile = L.featureGroup().addTo(miniMapProfile);

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

    // Format Jam Digital HH:mm:ss
    function formatTimeDigits(num) {
        return num < 10 ? '0' + num : num;
    }

    // Fungsi Update Live Clock & Cek Window Absensi
    function updateLiveClockAndWindow() {
        const now = new Date();
        const h = formatTimeDigits(now.getHours());
        const m = formatTimeDigits(now.getMinutes());
        const s = formatTimeDigits(now.getSeconds());
        const nowMs = now.getTime();

        const liveClockEl = document.getElementById('liveClockDisplay');
        if (liveClockEl) {
            liveClockEl.innerText = h + ':' + m + ':' + s + ' WIB';
        }

        if (nextAction === 'completed') return;

        // Evaluasi Window Saat Ini
        let currentStatus = '';
        let currentWindowActive = false;

        if (!hasClockIn) {
            // Target: Clock In (clockInStartMs s/d clockInEndMs)
            if (nowMs < clockInStartMs) {
                currentWindowActive = false;
                const diffSec = Math.floor((clockInStartMs - nowMs) / 1000);
                const diffMin = Math.floor(diffSec / 60);
                const remSec = diffSec % 60;
                currentStatus = 'Menunggu Window Clock In (Mulai ' + clockInStartStr + ' WIB - ' + diffMin + 'm ' + remSec + 's)';
            } else if (nowMs <= clockInEndMs) {
                currentWindowActive = true;
                currentStatus = 'Window Clock In Aktif (' + clockInStartStr + ' - ' + clockInEndStr + ' WIB)';
            } else {
                currentWindowActive = false;
                currentStatus = 'Window Clock In Berakhir (' + clockInEndStr + ' WIB)';
            }
        } else if (!hasClockOut) {
            // Target: Clock Out (clockOutStartMs s/d clockOutEndMs)
            if (nowMs < clockOutStartMs) {
                currentWindowActive = false;
                const diffSec = Math.floor((clockOutStartMs - nowMs) / 1000);
                const diffHour = Math.floor(diffSec / 3600);
                const diffMin = Math.floor((diffSec % 3600) / 60);
                const remSec = diffSec % 60;
                let countdownStr = '';
                if (diffHour > 0) countdownStr += diffHour + 'j ';
                countdownStr += diffMin + 'm ' + remSec + 's';
                currentStatus = 'Menunggu Window Clock Out (Mulai ' + clockOutStartStr + ' WIB - ' + countdownStr + ')';
            } else if (nowMs <= clockOutEndMs) {
                currentWindowActive = true;
                currentStatus = 'Window Clock Out Aktif (' + clockOutStartStr + ' - ' + clockOutEndStr + ' WIB)';
            } else {
                currentWindowActive = false;
                currentStatus = 'Window Clock Out Berakhir (' + clockOutEndStr + ' WIB)';
            }
        }

        inWindow = currentWindowActive;

        // Tangani Tampilan Placeholder & Pemicu Otomatis Kamera
        const ph = document.getElementById('cameraPlaceholder');
        const phIcon = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc = document.getElementById('placeholderDesc');
        const phBtn = document.getElementById('placeholderBtnContainer');

        if (!inWindow) {
            // Belum waktunya: Tampilkan status menunggu, JANGAN tampilkan "Memeriksa Lokasi Kerja"
            if (cameraActive) {
                stopCamera();
            }
            if (ph) ph.classList.remove('d-none');
            if (phIcon) phIcon.className = 'fas fa-business-time fa-3x text-warning mb-3';
            if (phTitle) phTitle.innerText = currentStatus;
            if (phDesc) {
                if (!hasClockIn) {
                    phDesc.innerText = 'Presensi Clock In dibuka 15 menit sebelum shift (' + clockInStartStr + ' - ' + clockInEndStr + ' WIB). Kamera akan otomatis menyala saat memasuki window.';
                } else {
                    phDesc.innerText = 'Presensi Clock Out dibuka mulai pukul ' + clockOutStartStr + ' sampai ' + clockOutEndStr + ' WIB. Kamera akan otomatis aktif tanpa perlu refresh halaman.';
                }
            }
            if (phBtn) phBtn.classList.add('d-none');
        } else {
            // Waktunya sudah masuk window
            if (phBtn) phBtn.classList.remove('d-none');
            if (isGpsValid) {
                if (!cameraActive) {
                    startCamera();
                }
            } else {
                // Di dalam window namun GPS belum selesai / belum valid
                if (!cameraActive) {
                    if (ph) ph.classList.remove('d-none');
                    if (phIcon) phIcon.className = 'fas fa-map-marker-alt fa-3x text-warning mb-3';
                    if (phTitle) phTitle.innerText = 'Memeriksa Area Lokasi Kerja...';
                    if (phDesc) phDesc.innerText = 'Kamera akan otomatis aktif jika Anda berada di dalam area lokasi kerja yang diizinkan.';
                }
            }
        }
    }

    function checkGPS() {
        const badge = document.getElementById('gpsBadge');
        const alertBox = document.getElementById('livenessAlertBox');
        const alertText = document.getElementById('livenessInstructionText');

        initMiniMapProfile();

        if (!navigator.geolocation) {
            if (badge) {
                badge.className = 'badge badge-danger';
                badge.innerHTML = 'GPS Tidak Didukung';
            }
            if (alertText) alertText.innerHTML = 'Browser tidak mendukung GPS Geolocation.';
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentLat = pos.coords.latitude;
                currentLng = pos.coords.longitude;
                let accuracy = Math.round(pos.coords.accuracy);

                let inside = false;
                if (activeLocations && activeLocations.length > 0) {
                    for (let loc of activeLocations) {
                        let geo = loc.polygon_geojson ? JSON.parse(loc.polygon_geojson) : null;
                        let coords = geo && geo.geometry ? geo.geometry.coordinates[0] : [];
                        if (pointInPolygon(currentLat, currentLng, coords)) {
                            inside = true;
                            break;
                        }
                    }
                } else {
                    inside = true;
                }

                updateMiniMapProfile(currentLat, currentLng, accuracy, inside);

                if (inside) {
                    isGpsValid = true;
                    if (badge) {
                        badge.className = 'badge badge-success';
                        badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Area Kerja (±' + accuracy + 'm)';
                    }
                    if (alertBox) alertBox.className = 'alert alert-warning py-3 px-3 mb-3 text-center shadow-sm border-warning liveness-alert-box';
                    if (alertText) alertText.innerHTML = 'LIVENESS CHECK: Silakan <u>KEDIPKAN MATA</u> atau <u>GELENGKAN KEPALA</u> ke kiri / kanan';
                    
                    if (inWindow && !cameraActive) {
                        startCamera();
                    }
                } else {
                    isGpsValid = false;
                    if (badge) {
                        badge.className = 'badge badge-danger';
                        badge.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Di Luar Area Kerja';
                    }
                    if (alertBox) alertBox.className = 'alert alert-danger py-3 px-3 mb-3 text-center shadow-sm';
                    if (alertText) alertText.innerHTML = 'Posisi Anda berada di luar area polygon lokasi presensi.';
                    if (inWindow) {
                        showGpsWarning('Di Luar Area Presensi', 'Anda terdeteksi di luar batas polygon lokasi kerja yang diizinkan.');
                    }
                }
            },
            (err) => {
                if (badge) {
                    badge.className = 'badge badge-danger';
                    badge.innerHTML = '<i class="fas fa-times-circle mr-1"></i> Gagal GPS';
                }
                if (alertBox) alertBox.className = 'alert alert-danger py-3 px-3 mb-3 text-center shadow-sm';
                if (alertText) alertText.innerHTML = 'Gagal mengakses GPS. Pastikan izin lokasi perangkat telah diaktifkan.';
                if (inWindow) {
                    showGpsWarning('Izin GPS Ditolak', 'Harap aktifkan GPS dan berikan izin lokasi pada browser Anda.');
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function showGpsWarning(title = 'Di Luar Area Presensi', desc = 'Anda terdeteksi di luar batas polygon lokasi kerja yang diizinkan.') {
        const ph = document.getElementById('cameraPlaceholder');
        const phIcon = document.getElementById('placeholderIcon');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc = document.getElementById('placeholderDesc');
        const phBtn = document.getElementById('placeholderBtnContainer');
        if (ph) ph.classList.remove('d-none');
        if (phIcon) phIcon.className = 'fas fa-map-marker-alt fa-3x text-warning mb-3';
        if (phTitle) phTitle.innerText = title;
        if (phDesc) phDesc.innerText = desc;
        if (phBtn) phBtn.classList.remove('d-none');
    }

    function stopCamera() {
        const video = document.getElementById('webcam');
        if (video && video.srcObject) {
            const tracks = video.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            video.srcObject = null;
        }
        cameraActive = false;
    }

    async function startCamera() {
        if (!inWindow || !isGpsValid || nextAction === 'completed' || cameraActive) return;

        const video = document.getElementById('webcam');
        const ph = document.getElementById('cameraPlaceholder');

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480, facingMode: 'user' }
            });
            video.srcObject = stream;
            cameraActive = true;
            if (ph) ph.classList.add('d-none');

            video.onloadedmetadata = () => {
                video.play().catch(() => {});
                detectFaceLoop();
            };
        } catch (err) {
            // Abaikan error AbortError atau permission sementara
            cameraActive = false;
        }
    }

    function checkEyeBlink(landmarks) {
        const leftEye = landmarks.getLeftEye();
        const rightEye = landmarks.getRightEye();

        const getEAR = (eye) => {
            const d1 = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
            const d2 = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
            const d3 = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
            return (d1 + d2) / (2.0 * d3);
        };

        const ear = (getEAR(leftEye) + getEAR(rightEye)) / 2.0;
        return ear < 0.21;
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
                isScanning = true;
                if (guideBox) guideBox.classList.add('liveness-ok');

                if (alertBox) alertBox.className = 'alert alert-success py-3 px-3 mb-3 text-center shadow-sm font-weight-bold';
                if (alertText) alertText.innerHTML = '<i class="fas fa-check-circle mr-1"></i> LIVENESS TERVERIFIKASI! (' + (blinkDetected ? 'Kedipan Mata' : 'Geleng Kepala') + ' Terdeteksi) - Mengirim Presensi...';
                if (overlayText) overlayText.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> Wajah Terverifikasi';

                const descriptorArray = Array.from(detection.descriptor);

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
                        longitude: currentLng
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Presensi Berhasil!',
                            html: '<strong>' + data.message + '</strong><br><small class="text-muted">Aksi: ' + (data.action_type || '') + '</small>',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Presensi Ditolak',
                            text: data.message
                        }).then(() => {
                            isScanning = false;
                            livenessVerified = false;
                            if (guideBox) guideBox.classList.remove('liveness-ok');
                            initialNoseRatio = null;
                            setTimeout(detectFaceLoop, 1500);
                        });
                    }
                })
                .catch(err => {
                    isScanning = false;
                    livenessVerified = false;
                    if (guideBox) guideBox.classList.remove('liveness-ok');
                    initialNoseRatio = null;
                    setTimeout(detectFaceLoop, 1500);
                });
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
        updateLiveClockAndWindow();
        setInterval(updateLiveClockAndWindow, 1000);
        initMiniMapProfile();
        loadModels();
        checkGPS();
    });
</script>

</body>
</html>
