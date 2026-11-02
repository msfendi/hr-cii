<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login Presensi Wajah - PT Chutex International Indonesia</title>

    <!-- SBAdmin2 & FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.275);
            overflow: hidden;
        }
        .scanner-guide-box {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 220px;
            height: 260px;
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
            font-size: 0.95rem;
            animation: pulseWarning 1.8s infinite;
        }
        @keyframes pulseWarning {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card login-card bg-white">
                <div class="card-header bg-gradient-primary text-white text-center py-3">
                    <h5 class="font-weight-bold mb-0">
                        <i class="fas fa-camera mr-2"></i> Presensi Wajah & GPS
                    </h5>
                    <small>PT Chutex International Indonesia</small>
                </div>

                <div class="card-body p-4 text-center">

                    <!-- Alert Liveness Banner -->
                    {{-- PERBAIKAN 26 (2026-11-02): banner liveness HANYA dirender bila
                         toggle liveness ON. Saat OFF, banner tidak ada di HTML. --}}
                    @if($livenessEnabled ?? true)
                    <div id="livenessAlertBox" class="alert alert-warning py-2 px-3 mb-3 text-center shadow-sm border-warning liveness-alert-box">
                        <i class="fas fa-eye text-primary mr-1"></i>
                        <span id="livenessInstructionText" class="font-weight-bold">
                            LIVENESS CHECK: Silakan <u>KEDIPKAN MATA</u> atau <u>GELENGKAN KEPALA</u> ke kiri / kanan
                        </span>
                    </div>
                    @endif

                    <!-- Kamera Box -->
                    <div class="position-relative d-inline-block rounded overflow-hidden shadow-sm mb-3" style="max-width: 100%; width: 100%; height: 380px; background: #1a1c23;">
                        <video id="webcam" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                        
                        <div id="scannerGuide" class="scanner-guide-box"></div>

                        <!-- Status Badge Overlay -->
                        <div id="camOverlayBadge" class="position-absolute bg-dark text-white px-3 py-1 rounded-pill small font-weight-bold shadow" style="top: 15px; left: 15px; opacity: 0.9; z-index: 10;">
                            <span id="overlayText"><i class="fas fa-user-clock text-warning mr-1"></i> Memuat Kamera...</span>
                        </div>

                        <!-- Placeholder jika GPS di luar / Kamera mati -->
                        <div id="cameraPlaceholder" class="position-absolute d-flex flex-column align-items-center justify-content-center text-white" style="top:0; left:0; width:100%; height:100%; background: #2e3340; z-index: 5;">
                            <i class="fas fa-map-marker-alt fa-3x text-warning mb-3"></i>
                            <h5 class="font-weight-bold" id="placeholderTitle">Memeriksa Area Lokasi Kerja...</h5>
                            <p class="text-gray-400 small px-4 mb-3" id="placeholderDesc">
                                Kamera akan otomatis aktif setelah lokasi GPS Anda terverifikasi di dalam area kerja.
                            </p>
                            <button class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="checkGPS()">
                                <i class="fas fa-sync-alt mr-1"></i> Cek Ulang GPS
                            </button>
                        </div>
                    </div>

                    <!-- Canvas tersembunyi untuk menangkap gambar wajah saat
                         liveness berhasil. Dipakai untuk disimpan sebagai
                         face_image pada presensi yang dicatat SAAT LOGIN.
                         (PERBAIKAN 21 - 2026-11-04) -->
                    <canvas id="faceCaptureCanvas" width="320" height="320" style="display: none;"></canvas>

                    <!-- Mini Map GPS & Area Kerja -->
                    <div class="mb-3 text-left">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="font-weight-bold text-gray-700">
                                <i class="fas fa-map-marked-alt text-primary mr-1"></i> MINI MAP LOKASI
                            </small>
                            <span id="miniMapStatus" class="badge badge-light border small text-muted">Memuat GPS...</span>
                        </div>
                        <div id="miniMap" class="rounded border shadow-sm" style="height: 180px; width: 100%; z-index: 1;"></div>
                        <small id="miniMapAccuracyText" class="text-muted d-block text-center mt-1" style="font-size: 0.75rem;">Akurasi GPS: - meter</small>
                    </div>

                    <!-- Form Manual NPK (Opsi Cadangan) telah dihapus sesuai permintaan -->

                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 & JS Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

<script>
    const activeLocations = @json($locations);

    let currentLat = null;
    let currentLng = null;
    // PERBAIKAN 21 (2026-11-04): akurasi GPS perlu disimpan supaya presensi
    // yang dicatat saat LOGIN membawa data yang sama dengan halaman presensi
    // (index), yaitu latitude + longitude + accuracy.
    let currentAccuracy = null;
    let isGpsValid = false;
    let isModelLoaded = false;
    let isScanning = false;
    let livenessVerified = false;

    // ==================================================================
    // PERBAIKAN 25 (2026-11-05): TOGGLE LIVENESS CHECK DARI ADMIN
    // ==================================================================
    // Nilai ini datang dari menu Manage Face Recognition -> Pengaturan
    // Liveness Check. Kalau dimatikan, halaman ini mencocokkan wajah
    // LANGSUNG tanpa meminta kedip/geleng, dan foto tetap diambil.
    //
    // Memakai json_encode + default true: kalau controller belum di-update atau
    // variabel tidak terkirim, liveness TETAP aktif (fail-safe, jangan
    // sampai keamanan turun tanpa sengaja).
    const LIVENESS_REQUIRED = Boolean(@json((bool) ($livenessEnabled ?? true)));

    // Saat liveness OFF, tantangan langsung dianggap selesai supaya alur
    // pengiriman presensi yang sudah ada tetap jalan tanpa diubah.
    if (!LIVENESS_REQUIRED) {
        livenessVerified = true;
    }

    let initialNoseRatio = null;
    let blinkDetected = false;
    let headTurnDetected = false;

    // === PERBAIKAN 24 (2026-11-02): liveness tidak lagi "gampang bolak-balik" ===
    // Masalah lama: initialNoseRatio di-reset tiap kali deteksi gagal 1 frame.
    // Akibatnya saat user geleng sedikit -> wajah keluar frame sebentar ->
    // baseline hilang -> status balik ke "Posisikan Wajah".
    // Solusi: baseline dipegang dengan EMA, deviasi harus BERTAHAN beberapa
    // frame (headTurnStreak) sebelum dianggap sah, dan deteksi gagal tidak
    // langsung menghapus baseline (diberi toleransi miss beberapa frame).
    const HEAD_TURN_STREAK_REQUIRED = 3;   // butuh 3 frame berturut-turut
    const DETECT_MISS_TOLERANCE = 8;       // toleransi 8 frame tanpa wajah (~2,4 dtk)
    let headTurnStreak = 0;
    let detectMissCount = 0;
    let lastGoodNoseRatio = null;

    // PERBAIKAN 24: baseline kedip (EAR) — lihat checkEyeBlink()
    let eyeBaseline = null;
    let eyeClosedSeen = false;

    // Mini Map Leaflet
    let miniMap = null;
    let userCircle = null;
    let userMarker = null;
    let polyGroup = null;

    function initMiniMap() {
        if (miniMap) return;
        const container = document.getElementById('miniMap');
        if (!container) return;

        miniMap = L.map('miniMap', {
            zoomControl: false,
            attributionControl: false
        }).setView([-6.2088, 106.8456], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(miniMap);

        polyGroup = L.featureGroup().addTo(miniMap);

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
                        polyGroup.addLayer(poly);
                        bounds.push(poly.getBounds());
                    } catch(e) {}
                }
            });
            if (bounds.length > 0) {
                let group = new L.featureGroup(bounds.map(b => L.rectangle(b)));
                miniMap.fitBounds(group.getBounds());
            }
        }
    }

    function updateMiniMap(lat, lng, accuracy, inside) {
        initMiniMap();
        if (!miniMap) return;

        if (userCircle) miniMap.removeLayer(userCircle);
        if (userMarker) miniMap.removeLayer(userMarker);

        let color = inside ? '#1cc88a' : '#e74a3b';

        userCircle = L.circle([lat, lng], {
            radius: Math.max(accuracy, 15),
            color: color,
            fillColor: color,
            fillOpacity: 0.2,
            weight: 1
        }).addTo(miniMap);

        userMarker = L.circleMarker([lat, lng], {
            radius: 6,
            color: '#ffffff',
            fillColor: color,
            fillOpacity: 1,
            weight: 2
        }).addTo(miniMap).bindPopup('<b>Posisi Anda</b><br>' + (inside ? '<span class="text-success">Di Dalam Area</span>' : '<span class="text-danger">Di Luar Area</span>')).openPopup();

        let allGroup = L.featureGroup([polyGroup, userCircle]);
        miniMap.fitBounds(allGroup.getBounds().pad(0.15));

        const statusBadge = document.getElementById('miniMapStatus');
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
                await faceapi.nets.faceLandmark68TinyNet.loadFromUri(url);
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
                // abaikan
            }
        }
    }

    function checkGPS() {
        const alertBox = document.getElementById('livenessAlertBox');
        const alertText = document.getElementById('livenessInstructionText');

        initMiniMap();

        if (!navigator.geolocation) {
            if (alertText) alertText.innerHTML = 'Browser Anda tidak mendukung GPS Geolocation.';
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentLat = pos.coords.latitude;
                currentLng = pos.coords.longitude;
                let accuracy = Math.round(pos.coords.accuracy);
                // PERBAIKAN 21: simpan akurasi agar ikut dikirim saat login.
                currentAccuracy = accuracy;

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

                updateMiniMap(currentLat, currentLng, accuracy, inside);

                if (inside || !activeLocations || activeLocations.length === 0) {
                    isGpsValid = true;
                    // PERBAIKAN 26: saat liveness OFF, banner tidak dirender
                    // (blok kondisional di markup atas) sehingga alertBox/alertText === null dan
                    // baris di bawah tidak berbuat apa-apa. Saat ON, perilaku
                    // lama dipertahankan 100%.
                    if (alertBox) alertBox.className = 'alert alert-warning py-2 px-3 mb-3 text-center shadow-sm border-warning liveness-alert-box';
                    if (alertText && LIVENESS_REQUIRED) alertText.innerHTML = 'LIVENESS CHECK: Silakan <u>KEDIPKAN MATA</u> atau <u>GELENGKAN KEPALA</u> ke kiri / kanan';
                    startCamera();
                } else {
                    isGpsValid = false;
                    if (alertBox) alertBox.className = 'alert alert-danger py-2 px-3 mb-3 text-center shadow-sm';
                    if (alertText) alertText.innerHTML = 'Posisi Anda terdeteksi di luar area polygon lokasi presensi.';
                    showGpsWarning();
                }
            },
            (err) => {
                if (alertBox) alertBox.className = 'alert alert-danger py-2 px-3 mb-3 text-center shadow-sm';
                if (alertText) alertText.innerHTML = 'Gagal mengakses GPS. Mohon aktifkan lokasi pada perangkat Anda.';
                showGpsWarning('Izin GPS Ditolak', 'Harap berikan izin akses lokasi pada browser Anda.');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function showGpsWarning(title = 'Di Luar Area Presensi', desc = 'Anda berada di luar batas polygon area kerja resmi.') {
        const ph = document.getElementById('cameraPlaceholder');
        const phTitle = document.getElementById('placeholderTitle');
        const phDesc = document.getElementById('placeholderDesc');
        if (ph) {
            ph.classList.remove('d-none');
            ph.style.setProperty('display', 'flex', 'important');
        }
        if (phTitle) phTitle.innerText = title;
        if (phDesc) phDesc.innerText = desc;

        // Matikan stream kamera jika aktif
        const video = document.getElementById('webcam');
        if (video && video.srcObject) {
            let tracks = video.srcObject.getTracks();
            tracks.forEach(track => track.stop());
            video.srcObject = null;
        }
    }

    async function startCamera() {
        isGpsValid = true;
        const video = document.getElementById('webcam');
        const ph = document.getElementById('cameraPlaceholder');

        // Jika stream sudah aktif, jangan mulai ulang agar tidak memicu AbortError
        if (video.srcObject && video.srcObject.active) {
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480, facingMode: 'user' }
            });
            video.srcObject = stream;
            
            try {
                await video.play();
            } catch (e) {
                // Abaikan AbortError saat load request baru
                if (e.name !== 'AbortError') {
                    console.warn("Video play warning:", e);
                }
            }

            if (ph) {
                ph.classList.add('d-none');
                ph.style.setProperty('display', 'none', 'important');
            }

            detectFaceLoop();
        } catch (err) {
            console.error("Camera error:", err);
            if (ph) {
                ph.classList.remove('d-none');
                ph.style.setProperty('display', 'flex', 'important');
            }
        }
    }

    /**
     * PERBAIKAN 24 (2026-11-02)
     * Deteksi kedip berbasis BASELINE, bukan ambang mutlak.
     *
     * MASALAH LAMA:
     *   `ear < 0.21` adalah ambang mati. Pada wajah dengan mata agak sipit
     *   atau pencahayaan redup, EAR normal sudah dekat 0.21 sehingga kedip
     *   dianggap "tidak ada"; sebaliknya getaran kecil bisa dianggap kedip.
     *
     * PERBAIKAN (sama seperti halaman presensi):
     *   - Rekam baseline EAR saat mata terbuka.
     *   - Kedip sah hanya bila mata turun ke <= 55% baseline, DAN sempat
     *     membuka kembali (bukan sekadar noise satu frame).
     *
     * @returns {boolean} true bila kedip valid terdeteksi
     */
    function checkEyeBlink(landmarks) {
        let leftEye, rightEye;
        try {
            leftEye = landmarks.getLeftEye();
            rightEye = landmarks.getRightEye();
        } catch (e) {
            return false;
        }
        if (!leftEye || !rightEye || leftEye.length < 6 || rightEye.length < 6) return false;

        const hitungEAR = (eye) => {
            const d1 = Math.hypot(eye[1].x - eye[5].x, eye[1].y - eye[5].y);
            const d2 = Math.hypot(eye[2].x - eye[4].x, eye[2].y - eye[4].y);
            const d3 = Math.hypot(eye[0].x - eye[3].x, eye[0].y - eye[3].y);
            return (d1 + d2) / (2.0 * d3);
        };

        const ear = (hitungEAR(leftEye) + hitungEAR(rightEye)) / 2.0;
        if (!isFinite(ear) || ear <= 0) return false;

        // -- Baseline belum ada: rekam posisi mata terbuka --
        if (eyeBaseline === null) {
            eyeBaseline = ear;
            eyeClosedSeen = false;
            return false;
        }

        // -- Mata menutup: harus turun jauh dari baseline --
        const closedThreshold = Math.min(eyeBaseline * 0.55, 0.21);
        if (ear < closedThreshold) {
            eyeClosedSeen = true;
            return false;   // tunggu mata membuka kembali
        }

        // -- Mata terbuka lagi setelah sempat menutup -> kedip valid --
        if (eyeClosedSeen) {
            eyeBaseline = eyeBaseline * 0.4 + ear * 0.6;
            eyeClosedSeen = false;
            blinkDetected = true;
            return true;
        }

        // -- Mata tetap terbuka: sesuaikan baseline pelan --
        eyeBaseline = eyeBaseline * 0.9 + ear * 0.1;
        return false;
    }

    /**
     * PERBAIKAN 24 (2026-11-02)
     * Deteksi geleng kepala yang TAHAN NOISE.
     *
     * MASALAH LAMA:
     *   - `initialNoseRatio` di-reset tiap frame gagal deteksi, jadi gerakan
     *     kecil yang membuat wajah sesaat keluar frame menghapus baseline
     *     -> status "Posisikan Wajah" muncul lagi terus-menerus.
     *   - Ambang |delta| > 0.16 dan ratio < 0.36 / > 0.64 terlalu jauh untuk
     *     gerakan geleng wajar, sehingga user harus geleng sangat lebar.
     *
     * PERBAIKAN:
     *   1. Baseline disimpan dengan EMA (tidak pernah kembali ke null selama
     *      sesi valid), sehingga gerakan halus tetap terukur.
     *   2. Deviasi harus BERTAHAN `HEAD_TURN_STREAK_REQUIRED` frame
     *      berturut-turut sebelum dianggap sah -> noise 1 frame tidak lolos.
     *   3. Ambang diturunkan ke deviasi 0.10 (dari 0.16) dan zona mati
     *      dirapatkan ke 0.40-0.60, karena rasio hidung realistis pada
     *      wajah tegak berada dekat 0.50.
     *
     * @param {object} landmarks hasil faceapi.withFaceLandmarks
     * @returns {boolean} true bila geleng sudah konsisten beberapa frame
     */
    function checkHeadTurn(landmarks) {
        const positions = landmarks.positions;
        const leftJaw = positions[0];
        const rightJaw = positions[16];
        const noseTip = positions[30];

        if (!leftJaw || !rightJaw || !noseTip) return false;

        const jawWidth = Math.abs(rightJaw.x - leftJaw.x);
        // Wajah terlalu kecil / jauh -> jangan ambil kesimpulan apa pun.
        if (!isFinite(jawWidth) || jawWidth < 40) return false;

        const currentRatio = (noseTip.x - leftJaw.x) / jawWidth;
        if (!isFinite(currentRatio)) return false;

        // -- Baseline pertama: rekam, belum bisa dinilai --
        if (initialNoseRatio === null) {
            initialNoseRatio = currentRatio;
            lastGoodNoseRatio = currentRatio;
            headTurnStreak = 0;
            return false;
        }

        // -- Hitung deviasi terhadap baseline --
        const delta = currentRatio - initialNoseRatio;
        const absDelta = Math.abs(delta);

        // Geleng sah bila:
        //   a. hidung jelas bergeser menjauh dari tengah (di luar 0.40-0.60), ATAU
        //   b. deviasi terhadap baseline melewati ambang yang lebih ramah (0.10)
        const jauhDariTengah = (currentRatio < 0.40 || currentRatio > 0.60);
        const deviasiCukup = absDelta > 0.10;

        if (jauhDariTengah || deviasiCukup) {
            headTurnStreak += 1;
        } else {
            // Gerakan kecil / noise -> jangan reset baseline, cukup kurangi streak.
            headTurnStreak = Math.max(0, headTurnStreak - 1);
        }

        // Baseline diperbarui pelan HANYA saat wajah kembali ke posisi netral,
        // supaya perubahan postur jangka panjang (geser duduk, dsb.) terserap
        // tanpa menghapus bukti geleng yang sedang berlangsung.
        if (!deviasiCukup) {
            initialNoseRatio = initialNoseRatio * 0.85 + currentRatio * 0.15;
        }

        lastGoodNoseRatio = currentRatio;

        return headTurnStreak >= HEAD_TURN_STREAK_REQUIRED;
    }

    /**
     * PERBAIKAN 21 (2026-11-04)
     * Tangkap gambar wajah dari video ke canvas (crop tengah 1:1, JPEG 0.8).
     * Fungsi ini SAMA dengan yang dipakai di halaman presensi
     * (face_attendance/index.blade.php) supaya hasil capture yang disimpan
     * saat LOGIN identik formatnya dengan presensi biasa.
     *
     * @returns {string|null} data-URI JPEG, atau null bila video belum siap
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

        if (!video || video.paused || video.ended || isScanning) return;

        if (!isModelLoaded) {
            setTimeout(detectFaceLoop, 400);
            return;
        }

        const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks(true).withFaceDescriptor();

        if (detection) {
            const landmarks = detection.landmarks;

            // PERBAIKAN 24: deteksi berhasil -> reset hitungan gagal.
            detectMissCount = 0;

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

                if (alertBox) alertBox.className = 'alert alert-success py-2 px-3 mb-3 text-center shadow-sm font-weight-bold';
                // PERBAIKAN 25: saat liveness OFF, jangan klaim liveness terverifikasi.
                if (alertText) alertText.innerHTML = !LIVENESS_REQUIRED
                    ? '<i class="fas fa-check-circle mr-1"></i> Wajah Dikenali - Mengirim Data Login...'
                    : '<i class="fas fa-check-circle mr-1"></i> LIVENESS TERVERIFIKASI! Mengirim Data Login...';
                if (overlayText) overlayText.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> Memproses Wajah...';

                const descriptorArray = Array.from(detection.descriptor);

                // PERBAIKAN 21 (2026-11-04):
                // Ambil gambar wajah SEKALI di sini, lalu kirim bersama
                // descriptor. Server akan memakai data inilah untuk mencatat
                // presensi (att_log + face_attendance_logs) SEKALIGUS saat
                // login, sehingga tidak ada proses presensi kedua kalinya
                // setelah user masuk ke halaman presensi.
                const faceImage = captureFaceImage();

                fetch("{{ route('face.attendance.login-auth') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        descriptor: descriptorArray,
                        // Data presensi - sama seperti yang dikirim
                        // face_attendance/index.blade.php ke
                        // route('face.attendance.store').
                        latitude: currentLat,
                        longitude: currentLng,
                        accuracy: currentAccuracy,
                        face_image: faceImage
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // PERBAIKAN 21 (2026-11-04):
                        // Login sekarang SEKALIGUS mencatat presensi, jadi
                        // user harus tahu hasilnya (Clock In / Clock Out
                        // tercatat, atau tidak tercatat karena di luar window
                        // / GPS di luar area). Timer hanya dipakai saat
                        // presensi sukses supaya user sempat membaca.
                        const att = data.attendance || null;
                        const attOk = att && att.success;
                        const attMsg = data.attendance_message || '';

                        let icon = 'success';
                        let title = 'Login Berhasil!';
                        let html = '<div>' + (data.message || '') + '</div>';

                        if (attOk) {
                            html += '<div class="mt-2 text-success font-weight-bold">'
                                  + '<i class="fas fa-check-circle mr-1"></i>'
                                  + 'Presensi tercatat otomatis.</div>';
                            if (attMsg) {
                                html += '<div class="small text-muted mt-1">' + attMsg + '</div>';
                            }
                        } else if (att) {
                            icon = 'warning';
                            title = 'Login Berhasil';
                            html += '<div class="mt-2 text-warning font-weight-bold">'
                                  + '<i class="fas fa-exclamation-triangle mr-1"></i>'
                                  + 'Presensi belum tercatat otomatis.</div>';
                            if (attMsg) {
                                html += '<div class="small text-muted mt-1">' + attMsg + '</div>';
                            }
                            html += '<div class="small mt-2">Silakan catat presensi di halaman presensi.</div>';
                        }

                        Swal.fire({
                            icon: icon,
                            title: title,
                            html: html,
                            timer: attOk ? 1800 : undefined,
                            showConfirmButton: !attOk,
                            confirmButtonText: 'Lanjut'
                        }).then(() => {
                            window.location.href = data.redirect_url;
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Login Ditolak',
                            text: data.message
                        }).then(() => {
                            isScanning = false;
                            // PERBAIKAN 25: saat liveness OFF, jangan minta ulang
                            // kedip/geleng setelah login ditolak.
                            livenessVerified = !LIVENESS_REQUIRED;
                            if (guideBox) guideBox.classList.remove('liveness-ok');
                            initialNoseRatio = null;
                            setTimeout(detectFaceLoop, 1500);
                        });
                    }
                })
                .catch(err => {
                    isScanning = false;
                    livenessVerified = !LIVENESS_REQUIRED;
                    if (guideBox) guideBox.classList.remove('liveness-ok');
                    initialNoseRatio = null;
                    setTimeout(detectFaceLoop, 1500);
                });
                return;
            }

            setTimeout(detectFaceLoop, 150);
        } else {
            // === PERBAIKAN 24 (2026-11-02) ===
            // Deteksi gagal SATU frame bukan berarti wajah hilang. Saat user
            // geleng, wajah memang bisa sesaat keluar dari kotak deteksi.
            // Dulu: teks langsung jadi "Posisikan Wajah" + baseline di-reset
            //       -> status kedip-geleng dan posisikan wajah bolak-balik.
            // Sekarang: beri toleransi beberapa frame, pertahankan baseline,
            //           dan jangan ubah instruksi bila user sedang geleng.
            detectMissCount += 1;

            if (detectMissCount > DETECT_MISS_TOLERANCE) {
                // Wajah benar-benar pergi -> baru reset sesi liveness.
                // PERBAIKAN 25: saat liveness OFF, jangan pernah menampilkan
                // instruksi kedip/geleng dan jangan membatalkan livenessVerified.
                if (overlayText) overlayText.innerHTML = LIVENESS_REQUIRED
                    ? '<i class="fas fa-user-clock text-warning mr-1"></i> Posisikan Wajah'
                    : '<i class="fas fa-user-clock text-warning mr-1"></i> Arahkan Wajah ke Kamera';
                initialNoseRatio = null;
                headTurnStreak = 0;
                lastGoodNoseRatio = null;
                eyeBaseline = null;
                eyeClosedSeen = false;
                if (!LIVENESS_REQUIRED) {
                    livenessVerified = true;
                }
            } else if (headTurnStreak > 0 && LIVENESS_REQUIRED) {
                // Sedang di tengah geleng -> tahan instruksi, jangan reset.
                if (overlayText) overlayText.innerHTML = '<i class="fas fa-eye text-warning mr-1"></i> Silakan Kedip / Geleng';
            }

            setTimeout(detectFaceLoop, 300);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initMiniMap();
        loadModels();
        checkGPS();
    });
</script>

</body>
</html>
