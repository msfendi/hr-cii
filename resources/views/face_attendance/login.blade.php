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
{{-- SweetAlert seragam untuk karyawan sudah resign (PKWT.TKK terisi) --}}
@include('partials.sweetalert-resign')
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

                    {{-- ============================================================
                         PERBAIKAN V33 (2026-11-05) — STEP 1: INPUT NPK DULU
                         ------------------------------------------------------------
                         Permintaan user: "masukkan npk dulu baru keluar camera,
                         jadi descriptor yang dicocokan sesuai dengan npk yang
                         dimasukkan agar lebih akurat. jika tanpa npk maka ada
                         kemungkinan salah mengambil wajah orang lain".

                         Implementasi:
                           - Section "npkStep" berisi form input NPK.
                           - Setelah NPK lolos validasi (server), section ini
                             disembunyikan dan section "cameraStep" ditampilkan.
                           - NPK yang tervalidasi dikirim bersama descriptor
                             ke route login-auth, sehingga pencocokan wajah
                             HANYA dilakukan terhadap NPK tersebut.
                           - Kalau setting admin mematikan "input NPK dulu",
                             section npkStep TIDAK dirender sama sekali dan
                             kamera langsung terbuka (flow lama).

                         Keamanan: NPK yang dipakai untuk pencocokan diambil
                         dari server (employee_faces), bukan dari input mentah
                         user, sehingga user tidak bisa mengarang NPK.
                         ============================================================ --}}
                    @if($npkRequired ?? true)
                    <div id="npkStep">
                        <div class="mb-3">
                            <h5 class="font-weight-bold text-gray-800 mb-1">Masukkan NPK Anda</h5>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle mr-1 text-primary"></i>
                                Wajah akan dicocokkan hanya dengan data wajah NPK yang Anda masukkan.
                            </p>
                        </div>

                        <form id="npkForm" autocomplete="off" novalidate>
                            @csrf
                            <div class="form-group mb-2">
                                <label for="npkInput" class="sr-only">NPK</label>
                                <input type="text"
                                       id="npkInput"
                                       name="npk"
                                       class="form-control form-control-lg text-center"
                                       placeholder="Contoh: C-00827"
                                       inputmode="text"
                                       autocapitalize="characters"
                                       maxlength="20"
                                       required>
                            </div>
                            <button type="submit"
                                    id="npkSubmitBtn"
                                    class="btn btn-primary btn-lg rounded-pill px-5 w-100">
                                <i class="fas fa-arrow-right mr-1"></i> Lanjut ke Kamera
                            </button>
                        </form>

                        <div id="npkAlert" class="alert d-none mt-3 mb-0 text-left" role="alert"></div>
                    </div>
                    @endif

                    {{-- STEP 2: area kamera. Hanya ditampilkan SETELAH NPK
                         tervalidasi (kalau setting aktif). --}}
                    <div id="cameraStep" class="@if($npkRequired ?? true) d-none @endif">
                    {{-- PERBAIKAN V33 (2026-11-05): badge karyawan yang NPK-nya
                         sudah tervalidasi. Muncul setelah step NPK lolos, supaya
                         user yakin dia sedang login sebagai orang yang benar. --}}
                    <div id="npkVerifiedBadge" class="alert alert-success py-2 px-3 mb-3 shadow-sm border-success font-weight-bold d-none">
                    </div>

                    <!-- Alert Liveness Banner -->
                    {{-- PERBAIKAN 26 (2026-11-02): banner liveness HANYA dirender bila
                         toggle liveness ON. Saat OFF, banner tidak ada di HTML. --}}
                    @if($livenessEnabled ?? true)
                    {{-- PERBAIKAN V31.3b (2026-11-04): halaman login DULU hanya berisi
                         ikon mata statis + teks. Sekarang memakai partial wajah yang
                         SAMA dengan halaman /face-attendance, sehingga animasi
                         menoleh + berkedip tampil di kedua halaman dan tidak mungkin
                         berbeda lagi kalau salah satu diubah. --}}
                    <div id="livenessAlertBox" class="alert alert-warning py-2 px-3 mb-3 shadow-sm border-warning liveness-alert-box d-flex align-items-center justify-content-center">
                        @include('face_attendance._liveness_face', ['ukuran' => 56])
                        <span id="livenessInstructionText" class="font-weight-bold ml-2">
                            LIVENESS CHECK: Silakan <u>BERKEDIP</u> atau <u>MENOLEH</u> sedikit ke kiri / kanan
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

                    </div><!-- /#cameraStep -->

                </div>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 & JS Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
{{-- PERBAIKAN V14 (2026-11-06): halaman ini sekarang memakai detektor
     liveness BERSAMA dari public/js/liveness-check.js, sama seperti
     /self-register-face, /employee-payroll, dan /pengajuan-cuti/login.
     Tujuannya: perilaku "menoleh" dan toleransi sensor hilang seragam
     di semua halaman, dan perbaikan cukup dilakukan di satu file. --}}
<script src="{{ asset('js/liveness-check.js') }}?v=14"></script>

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
    // PERBAIKAN V33 (2026-11-05): STEP 1 — INPUT NPK DULU
    // ==================================================================
    // currentNpk menyimpan NPK yang SUDAH tervalidasi server.
    // Nilai ini ikut dikirim ke route login-auth supaya pencocokan
    // descriptor wajah hanya dilakukan terhadap NPK tersebut.
    //
    // Kalau setting admin "input NPK dulu" dimatikan, currentNpk tetap
    // null dan server memakai flow lama (cari ke semua karyawan).
    let currentNpk = null;
    let npkStepActive = @json((bool) ($npkRequired ?? true));

    // Tampilkan pesan error di box step NPK.
    function showNpkAlert(message, type = 'danger') {
        const box = document.getElementById('npkAlert');
        if (!box) return;
        box.classList.remove('d-none', 'alert-danger', 'alert-success', 'alert-warning');
        box.classList.add('alert-' + type);
        box.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> ' + message;
    }

    function clearNpkAlert() {
        const box = document.getElementById('npkAlert');
        if (!box) return;
        box.classList.add('d-none');
        box.innerHTML = '';
    }

    /**
     * Kirim NPK ke server untuk divalidasi.
     * Kalau valid: sembunyikan step NPK, tampilkan step kamera, mulai
     *               pemuatan model wajah + kamera + GPS.
     * Kalau tidak valid: tampilkan pesan, tetap di step NPK.
     */
    function submitNpk(event) {
        if (event) event.preventDefault();

        const input = document.getElementById('npkInput');
        const btn = document.getElementById('npkSubmitBtn');
        if (!input) return;

        const npk = input.value.trim();
        if (!npk) {
            showNpkAlert('NPK wajib diisi.');
            input.focus();
            return;
        }

        clearNpkAlert();
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Memeriksa...';
        }

        fetch("{{ route('face.attendance.login.validate-npk') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ npk: npk })
        })
        .then(res => res.json().catch(() => ({
            success: false,
            message: 'Respons server tidak valid (HTTP ' + res.status + ').'
        })))
        .then(data => {
            // PERBAIKAN 1 (2026-11-06): karyawan sudah keluar (PKWT.TKK terisi)
            // -> blokir lanjut ke kamera, beri alert serupa self-register.
            if (data.inactive_employee) {
                showResignAlert({
                    message: data.message || 'Karyawan sudah keluar, tidak bisa lanjut face attendance.',
                    action : 'Pendaftaran presensi wajah tidak dapat dilanjutkan.',
                    npk    : data.npk || null
                });
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-arrow-right mr-1"></i> Lanjut ke Kamera';
                }
                return;
            }

            if (data.success) {
                currentNpk = data.npk || npk;

                const npkStep = document.getElementById('npkStep');
                const cameraStep = document.getElementById('cameraStep');
                if (npkStep) npkStep.classList.add('d-none');
                if (cameraStep) cameraStep.classList.remove('d-none');

                // Info karyawan yang terverifikasi, ditampilkan di atas
                // area kamera supaya user yakin dia tidak salah NPK.
                const badge = document.getElementById('npkVerifiedBadge');
                if (badge) {
                    badge.innerHTML = '<i class="fas fa-user-check mr-1"></i> '
                        + (data.name || currentNpk)
                        + ' <span class="text-muted">(' + currentNpk + ')</span>';
                    badge.classList.remove('d-none');
                }

                // Mulai alur yang selama ini berjalan otomatis.
                initFaceLoginFlow();
            } else {
                showNpkAlert(data.message || 'NPK tidak valid.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-arrow-right mr-1"></i> Lanjut ke Kamera';
                }
                input.focus();
                input.select();
            }
        })
        .catch(err => {
            console.error('[FaceAttendance] validasi NPK gagal:', err);
            showNpkAlert('Gagal terhubung ke server. Periksa koneksi lalu coba lagi.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-arrow-right mr-1"></i> Lanjut ke Kamera';
            }
        });
    }

    // ==================================================================
    // PERBAIKAN V31.6 (2026-11-05): TOLAK KOORDINAT BERBASIS IP/JARINGAN
    // ==================================================================
    // complaints: "gps membaca location dari ip, seharusnya location real
    // dari device".
    //
    // fakta TEKNIS: aplikasi ini memang HANYA memanggil
    // navigator.geolocation (bukan API ip-api/ipinfo). Tapi browser/
    // sistem operasi, ketika GPS GNSS tidak berhasil fix, TETAP
    // mengembalikan koordinat-butiran yang diestimasi dari jaringan
    // (Wi-Fi / seluler / IP). Koordinat itu BERBEDA dari lokasi device
    // sebenarnya dan biasanya datang dengan accuracy besar
    // (ratusan sampai ribuan meter).
    //
    // akibatnya user bisa lolos geofence padahal posisinya salah.
    //
    // Solusi (standar industri, bukan tebakan):
    //   - minta koordinat dengan enableHighAccuracy (sudah ada),
    //   - TOLAK koordinat yang accuracy-nya terlalu besar,
    //   -retry dengan watchPosition agar GPS GNSS sempat fix,
    //   - kalau tetap tidak fix, tampilkan pesan jelas dan JANGAN
    //     menganggap koordinat jaringan sebagai lokasi asli.
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

    let blinkDetected = false;
    let headTurnDetected = false;

    // ==================================================================
    // PERBAIKAN V14 (2026-11-06): liveness memakai detektor BERSAMA.
    // ==================================================================
    // Sebelumnya halaman ini punya implementasi liveness LOKAL
    // (checkEyeBlink + checkHeadTurn) yang berbeda dari halaman
    // /self-register-face. Akibatnya:
    //   * ambang "menoleh" berbeda antar halaman -> hasil tidak konsisten
    //   * saat user MENOLEH, wajah sesaat keluar kotak deteksi, dan
    //     streak langsung di-reset -> user harus mengulang terus
    //     ("terlalu sensitif").
    //
    // Sekarang keduanya memakai LivenessCheck dari
    // public/js/liveness-check.js, dengan toleransi sensor hilang
    // (missToleranceFrames) sehingga satu frame/dua frame lost tidak
    // menghapus progress.
    // ==================================================================
    const liveness = new LivenessCheck({
        required: true,
        missToleranceFrames: 12
    });

    // Toleransi frame tanpa wajah (sensor hilang / wajah keluar kotak
    // deteksi sesaat). Nilai ini sejajar dengan toleransi internal
    // LivenessCheck, jadi wajah hilang sampai ~12 frame (~1,8 detik)
    // TIDAK menghapus progress liveness.
    const DETECT_MISS_TOLERANCE = 12;      // toleransi 12 frame tanpa wajah (~1,8 dtk)
    let detectMissCount = 0;

    // Mini Map Leaflet
    let miniMap = null;
    let userCircle = null;
    let userMarker = null;
    let polyGroup = null;

    // PERBAIKAN V1.1: fokus peta ke titik GPS user.
    // PERBAIKAN V1.2: zoom dihitung dari ukuran container ASLI + akurasi GPS,
    // supaya tampilannya street-level (bukan skala kota/regional).
    // Rumus lama salah: zoom dibatasi min 11 dan mengabaikan lebar container,
    // sehingga mini map selalu menampilkan ~30 km (kota + kecamatan sekitar).
    function focusOnUser(mapInstance, lat, lng, accuracy) {
        if (!mapInstance) return;
        const acc = Math.max(Number(accuracy) || 0, 0);

        // 1. Tentukan lebar viewport efektif (px) dari container asli.
        const el = mapInstance.getContainer && mapInstance.getContainer();
        const w = (el && el.clientWidth) ? el.clientWidth : 320;
        const h = (el && el.clientHeight) ? el.clientHeight : 180;

        // 2. Target cakupan tanah (meter) yang ditampilkan:
        //    - cukup lebar untuk melihat area geofencing kecil di sekitar user
        //    - tetap sempit supaya area geofencing yang jauh tidak ikut terlihat
        //    Radius akurasi GPS dipakai sebagai baseline, minimal 250m, maksimal 900m.
        //    (akurasi 66m -> 330m -> street-level, +/- sekitar 350-450m terlihat)
        const targetMeters = Math.min(Math.max(acc * 5, 250), 900);

        // 3. Konversi target meter -> level zoom Leaflet, memperhitungkan
        //    ukuran viewport (lebar = tujuan agar area muat, tinggi ikut skala).
        const mpp = 156543.03392 * Math.cos(lat * Math.PI / 180);
        const zoom = Math.max(13, Math.min(18, Math.round(
            Math.log2(mpp * w / targetMeters)
        )));

        mapInstance.setView([lat, lng], zoom, { animate: true, duration: 0.6 });
    }

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
                // PERBAIKAN: jangan fitBounds ke seluruh area geofencing di sini.
                // Mini Map harus fokus ke titik GPS user, bukan ke seluruh area
                // terdaftar (area yang jauh membuat bounds sangat lebar -> peta
                // zoom-out ke skala regional/whole island).
                // View awal dibiarkan pada koordinat user (di-set oleh updateMiniMap).
                void group;
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

        // PERBAIKAN (inti): auto-zoom ke titik GPS user.
        // Sebelumnya: fitBounds(area geofencing + user) -> kalau ada area yang
        // jauh, peta ikut zoom-out sehingga lokasi user cuma titipan kecil.
        // Sekarang: setView ke koordinat user dengan zoom yang dihitung dari
        // akurasi GPS, dibatasi agar tidak pernah tampil terlalu jauh.
        focusOnUser(miniMap, lat, lng, accuracy);

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
                const rawAccuracy = pos.coords.accuracy;
                let accuracy = gpsAccuracyLabel(rawAccuracy);

                currentLat = pos.coords.latitude;
                currentLng = pos.coords.longitude;
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
                    if (alertBox) {
                        // PERBAIKAN V31.3b (2026-11-04): jangan timpa className penuh.
                        // Dulu kelas "d-flex" ikut terhapus sehingga wajah animasi
                        // jatuh ke baris bawah dan teks tampak bertumpuk.
                        // Sekarang hanya kelas warna yang diubah.
                        alertBox.classList.remove('alert-danger', 'alert-success');
                        alertBox.classList.add('alert-warning', 'border-warning');
                        alertBox.classList.add('d-flex', 'align-items-center', 'justify-content-center');
                        alertBox.classList.remove('text-center');
                    }
                    if (alertText && LIVENESS_REQUIRED) alertText.innerHTML = 'LIVENESS CHECK: Silakan <u>BERKEDIP</u> atau <u>MENOLEH</u> sedikit ke kiri / kanan';
                    startCamera();
                } else {
                    isGpsValid = false;
                    if (alertBox) {
                        alertBox.classList.remove('alert-warning', 'alert-success', 'border-warning');
                        alertBox.classList.add('alert-danger');
                        alertBox.classList.add('d-flex', 'align-items-center', 'justify-content-center');
                        alertBox.classList.remove('text-center');
                    }
                    if (alertText) alertText.innerHTML = 'Posisi Anda terdeteksi di luar area polygon lokasi presensi.';
                    showGpsWarning();
                }
            },
            (err) => {
                if (alertBox) {
                        alertBox.classList.remove('alert-warning', 'alert-success', 'border-warning');
                        alertBox.classList.add('alert-danger');
                        alertBox.classList.add('d-flex', 'align-items-center', 'justify-content-center');
                        alertBox.classList.remove('text-center');
                    }
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
                // PERBAIKAN V14 (2026-11-06): pakai detektor BERSAMA.
                // `update()` sudah menangani toleransi sensor hilang
                // secara internal, jadi cukup terus diteruskan dengan
                // landmarks (atau null kalau wajah tidak ada).
                const hasilLiveness = liveness.update(landmarks);

                if (hasilLiveness.passed) {
                    if (hasilLiveness.blink) {
                        blinkDetected = true;
                    } else if (hasilLiveness.turn) {
                        headTurnDetected = true;
                    }
                    livenessVerified = true;
                } else {
                    if (overlayText) overlayText.innerHTML = '<i class="fas fa-eye text-warning mr-1"></i> ' + (hasilLiveness.hint || 'Silakan Berkedip atau Menoleh Sedikit');
                }
            }

            if (livenessVerified) {
                isScanning = true;
                if (guideBox) guideBox.classList.add('liveness-ok');

                if (alertBox) {
                    alertBox.classList.remove('alert-warning', 'alert-danger', 'border-warning');
                    alertBox.classList.add('alert-success', 'font-weight-bold');
                    alertBox.classList.add('d-flex', 'align-items-center', 'justify-content-center');
                    alertBox.classList.remove('text-center');
                }
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
                        face_image: faceImage,
                        // PERBAIKAN V33 (2026-11-05): NPK yang sudah
                        // divalidasi di STEP 1 ikut dikirim, sehingga server
                        // hanya mencocokkan descriptor wajah terhadap NPK
                        // ini (bukan ke seluruh karyawan).
                        npk: currentNpk
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
                        // =======================================================
                        // PERBAIKAN V31.8 (2026-11-04) - KARYAWAN SUDAH KELUAR
                        // =======================================================
                        // Wajah terbaca & cocok, tapi PKWT.TKK terisi ->
                        // karyawan sudah keluar. Login DITOLAK dan presensi
                        // tidak dikirim ke server.
                        if (data.inactive_employee) {
                            showResignAlert({
                                message: data.message || 'Data kepegawaian Anda sudah tercatat keluar.',
                                action : 'Login dan presensi ditolak.',
                                npk    : data.npk || null
                            }).then(() => {
                                isScanning = false;
                                livenessVerified = !LIVENESS_REQUIRED;
                                resetLivenessState();
                            });
                            return;
                        }

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
                            liveness.reset();
                            setTimeout(detectFaceLoop, 1500);
                        });
                    }
                })
                .catch(err => {
                    isScanning = false;
                    livenessVerified = !LIVENESS_REQUIRED;
                    if (guideBox) guideBox.classList.remove('liveness-ok');
                    liveness.reset();
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

            // PERBAIKAN V14 (2026-11-06): teruskan "wajah hilang" ke
            // detektor BERSAMA. Di dalamnya sudah ada toleransi
            // missToleranceFrames, jadi 1-2 frame lost (wajar saat
            // menoleh) TIDAK menghapus progress liveness.
            if (!livenessVerified) {
                liveness.update(null);
            }

            if (detectMissCount > DETECT_MISS_TOLERANCE) {
                // Wajah benar-benar pergi -> baru reset sesi liveness.
                // PERBAIKAN 25: saat liveness OFF, jangan pernah menampilkan
                // instruksi kedip/geleng dan jangan membatalkan livenessVerified.
                if (overlayText) overlayText.innerHTML = LIVENESS_REQUIRED
                    ? '<i class="fas fa-user-clock text-warning mr-1"></i> Posisikan Wajah'
                    : '<i class="fas fa-user-clock text-warning mr-1"></i> Arahkan Wajah ke Kamera';

                // Reset penuh hanya setelah toleransi terlampaui.
                // Reset sesi liveness BERSAMA (satu sumber kebenaran).
                liveness.reset();
                if (!LIVENESS_REQUIRED) {
                    livenessVerified = true;
                }
            } else if (LIVENESS_REQUIRED) {
                // Wajah sesaat hilang (wajar saat menoleh) -> tahan
                // instruksi, jangan reset progress.
                if (overlayText) overlayText.innerHTML = '<i class="fas fa-eye text-warning mr-1"></i> ' + (liveness.hint() || 'Silakan Berkedip atau Menoleh Sedikit');
            }

            setTimeout(detectFaceLoop, 300);
        }
    }

    /**
     * PERBAIKAN V33 (2026-11-05): alur inisialisasi login wajah.
     *
     * Dulu semua dipanggil otomatis saat DOMContentLoaded. Sekarang:
     *   - Kalau setting "input NPK dulu" AKTIF, alur ini baru dijalankan
     *     setelah NPK user lolos validasi server (lihat submitNpk()).
     *   - Kalau setting OFF, alur ini tetap jalan otomatis seperti dulu.
     *
     * Flag `faceFlowStarted` mencegah dua kali pemicu (mis. user klik
     * tombol Lanjut dua kali), supaya model wajah tidak dimuat ganda dan
     * kamera tidak dibuka dua kali (NotReadableError).
     */
    let faceFlowStarted = false;

    function initFaceLoginFlow() {
        if (faceFlowStarted) return;
        faceFlowStarted = true;

        initMiniMap();
        loadModels();
        checkGPS();
    }

    document.addEventListener('DOMContentLoaded', () => {
        // PERBAIKAN V33 (2026-11-05): kalau NPK harus diinput dulu, alur
        // login (peta, model wajah, kamera, GPS) TIDAK dijalankan sampai
        // NPK user tervalidasi. Form NPK juga dikaitkan ke handler submit.
        const npkForm = document.getElementById('npkForm');
        if (npkForm) {
            npkForm.addEventListener('submit', submitNpk);
            const npkInput = document.getElementById('npkInput');
            if (npkInput) {
                npkInput.addEventListener('input', clearNpkAlert);
                // Fokus otomatis ke input NPK supaya user bisa langsung ketik.
                setTimeout(() => npkInput.focus(), 250);
            }
        }

        if (npkStepActive) {
            // Tunggu NPK tervalidasi -> submitNpk() memanggil initFaceLoginFlow().
            return;
        }

        // Setting "input NPK dulu" dimatikan: flow lama, langsung jalan.
        initFaceLoginFlow();
    });
</script>

</body>
</html>
