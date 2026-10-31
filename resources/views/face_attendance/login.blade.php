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
                    <div id="livenessAlertBox" class="alert alert-warning py-2 px-3 mb-3 text-center shadow-sm border-warning liveness-alert-box">
                        <i class="fas fa-eye text-primary mr-1"></i>
                        <span id="livenessInstructionText" class="font-weight-bold">
                            LIVENESS CHECK: Silakan <u>KEDIPKAN MATA</u> atau <u>GELENGKAN KEPALA</u> ke kiri / kanan
                        </span>
                    </div>

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
    let isGpsValid = false;
    let isModelLoaded = false;
    let isScanning = false;
    let livenessVerified = false;

    let initialNoseRatio = null;
    let blinkDetected = false;
    let headTurnDetected = false;

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
                    if (alertBox) alertBox.className = 'alert alert-warning py-2 px-3 mb-3 text-center shadow-sm border-warning liveness-alert-box';
                    if (alertText) alertText.innerHTML = 'LIVENESS CHECK: Silakan <u>KEDIPKAN MATA</u> atau <u>GELENGKAN KEPALA</u> ke kiri / kanan';
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

        if (!video || video.paused || video.ended || isScanning) return;

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

                if (alertBox) alertBox.className = 'alert alert-success py-2 px-3 mb-3 text-center shadow-sm font-weight-bold';
                if (alertText) alertText.innerHTML = '<i class="fas fa-check-circle mr-1"></i> LIVENESS TERVERIFIKASI! Mengirim Data Login...';
                if (overlayText) overlayText.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> Memproses Wajah...';

                const descriptorArray = Array.from(detection.descriptor);

                fetch("{{ route('face.attendance.login-auth') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        descriptor: descriptorArray
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Login Berhasil!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
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
        initMiniMap();
        loadModels();
        checkGPS();
    });
</script>

</body>
</html>
