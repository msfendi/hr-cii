<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    .camera-box-sm {
        width: 100%;
        max-width: 380px;
        height: 280px;
        margin: 0 auto;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    .camera-box-sm video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    #faceVideo {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transform: scaleX(-1);
    }
    .scanner-guide-face {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 160px;
        height: 200px;
        border: 2px dashed rgba(255, 255, 255, 0.7);
        border-radius: 50%;
        pointer-events: none;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.3);
    }
    .scanner-guide-face.success {
        border-color: #1cc88a;
        box-shadow: 0 0 20px #1cc88a, 0 0 0 9999px rgba(0, 0, 0, 0.2);
    }
</style>
<body class="bg-gradient-primary">
    @include('sweetalert::alert')

    <div class="container container-center">
        <div class="text-center">
            <img src="{{ asset('img/chutex.svg') . '?v=325' }}" style="width: 150px;">
            <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
            <h1 class="h1 text-white mb-4"><b>HRIS</b></h1>
        </div>
        <div class="card o-hidden border-0 shadow-lg my-5">
            <div class="card-body p-0">
                <!-- Nested Row within Card Body -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">Sign In with Email</h1>
                            </div>
                            <form class="user" action="{{ route('login') }}" method="post">
                                @csrf
                                @if ($message = Session::get('success'))
                                <div class="alert alert-success alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('error'))
                                <div class="alert alert-danger alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('warning'))
                                <div class="alert alert-warning alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('info'))
                                <div class="alert alert-info alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif
                                
                                <div class="form-group">
                                    <input type="email" class="form-control form-control-user @error('email') is-invalid @enderror" id="email" name="email"
                                        placeholder="Email">
                                    @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control form-control-user @error('password') is-invalid @enderror" id="password" name="password"
                                        placeholder="Password">
                                    @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label class="small mb-1">Masukkan Kode Captcha</label>
                                    <div class="text-center mb-2">
                                        <img id="captcha-img"
                                            src="{{ route('captcha.image') }}?t={{ time() }}"
                                            alt="Captcha"
                                            style="border:1px solid #ccc; border-radius:4px; cursor:pointer;"
                                            title="Klik untuk refresh"
                                            onclick="refreshCaptcha()">
                                    </div>
                                    <div class="input-group">
                                        <input type="text" autocomplete="off"
                                            class="form-control form-control-user @error('captcha') is-invalid @enderror"
                                            id="captcha" name="captcha" placeholder="Masukkan kode di atas">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary" onclick="refreshCaptcha()" title="Refresh Captcha">
                                                &#x21bb;
                                            </button>
                                        </div>
                                        @error('captcha')
                                        <div class="invalid-feedback d-block">
                                            {{ $message }}
                                        </div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary btn-user btn-block">Login</button>
                                </div>
                                <!-- <hr> -->
                            </form>
                            <div class="text-center">
                                <!-- <a class="small" href="{{ route('register') }}">Don't have account? Register!</a> -->
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">
                                    @if(!($qrEnabled ?? true) && ($faceEnabled ?? false))
                                        Sign In with Face Recognition
                                    @elseif(($qrEnabled ?? true) && !($faceEnabled ?? false))
                                        Sign In with ID Card
                                    @elseif(($qrEnabled ?? true) && ($faceEnabled ?? false))
                                        Sign In with ID Card / Wajah
                                    @else
                                        Sign In Portal
                                    @endif
                                </h1>
                            </div>

                            @php
                                $showQr   = $qrEnabled ?? true;
                                $showFace = $faceEnabled ?? false;
                                // Tab yang aktif secara default
                                $defaultTab = $showQr ? 'idcard' : ($showFace ? 'face' : 'none');
                            @endphp

                            @if ($showQr && $showFace)
                            {{-- Tab switching ditangani manual (tab.js) supaya TIDAK bergantung
                                 pada plugin Bootstrap JS. Sebelumnya plugin tidak ter-load,
                                 sehingga tab tidak bisa diklik. --}}
                            <ul class="nav nav-pills nav-fill mb-3" id="loginMethodTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active font-weight-bold" data-toggle="tab" data-target="#idcardPane"
                                        href="#idcardPane" role="tab" aria-controls="idcardPane" aria-selected="true"
                                        id="idcardTabLink">
                                        <i class="fas fa-id-card mr-1"></i> ID Card (QR)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link font-weight-bold" data-toggle="tab" data-target="#facePane"
                                        href="#facePane" role="tab" aria-controls="facePane" aria-selected="false"
                                        id="faceTabLink">
                                        <i class="fas fa-camera mr-1"></i> Face Recognition
                                    </a>
                                </li>
                            </ul>
                            @endif

                            <div class="tab-content">
                                {{-- TAB QR CODE ID CARD --}}
                                @if ($showQr)
                                <div class="tab-pane fade {{ $defaultTab === 'idcard' ? 'show active' : '' }}" id="idcardPane" role="tabpanel" aria-labelledby="idcardTabLink">
                                    <div class="camera-box-sm shadow-sm">
                                        <video id="video" muted playsinline></video>
                                    </div>
                                    <p class="text-center text-muted small mt-2 mb-0">
                                        Arahkan QR Code ID Card ke kamera
                                    </p>
                                </div>
                                @endif

                                {{-- TAB FACE RECOGNITION --}}
                                @if ($showFace)
                                <div class="tab-pane fade {{ $defaultTab === 'face' ? 'show active' : '' }}" id="facePane" role="tabpanel" aria-labelledby="faceTabLink">
                                    <div id="faceLoginStatus" class="alert alert-info py-2 px-3 mb-3 small font-weight-bold">
                                        <i class="fas fa-info-circle mr-1"></i> Klik "Mulai Scan Wajah" untuk mulai.
                                    </div>
                                    {{-- PERBAIKAN V31.3c (2026-11-04): halaman /login
                                         DULU tidak punya pemeriksaan liveness sama
                                         sekali walau Settings menu "login" ON. --}}
                                    @if ($livenessEnabled ?? true)
                                    <div id="livenessFaceBanner" class="liveness-face-banner d-flex align-items-center justify-content-center mb-3">
                                        @include('face_attendance._liveness_face')
                                        <span class="liveness-face-text font-weight-bold">LIVENESS CHECK: MENOLEH</span>
                                    </div>
                                    @endif
                                    <div class="camera-box-sm shadow-sm mb-3">
                                        <video id="faceVideo" autoplay muted playsinline></video>
                                        <div id="faceLoginGuide" class="scanner-guide-face"></div>
                                    </div>
                                    <div class="d-flex justify-content-center">
                                        <button type="button" id="faceLoginStart" class="btn btn-success btn-sm rounded-pill px-4 shadow-sm">
                                            <i class="fas fa-camera mr-1"></i> Mulai Scan Wajah
                                        </button>
                                        <button type="button" id="faceLoginStop" class="btn btn-outline-danger btn-sm rounded-pill px-3 ml-2 d-none">
                                            <i class="fas fa-stop mr-1"></i> Berhenti
                                        </button>
                                    </div>
                                </div>
                                @endif

                                {{-- JIKA KEDUA METODE KAMERA DINONAKTIFKAN --}}
                                @if (!$showQr && !$showFace)
                                <div class="text-center py-4">
                                    <div class="alert alert-secondary py-3 px-3 small font-weight-bold">
                                        <i class="fas fa-info-circle mr-1"></i> Login via QR Code dan Face Recognition sedang dinonaktifkan oleh Administrator. Silakan gunakan form login Email &amp; Password.
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

@include('layout.footerscript')

{{-- PERBAIKAN V32.7 (2026-11-05): ZXing kini dimuat dari FILE LOKAL
     (public/vendor/zxing/zxing.min.js, versi 0.21.3 dipin) dengan fallback
     CDN. alasan:
       1) script sebelumnya berada SETELAH </body> (urutan render tidak
          dijamin -> ReferenceError "ZXing is not defined"),
       2) CDN bisa diblokir / gagal di jaringan produksi -> ZXing undefined,
       3) @latest bisa mengarahkan ke versi dengan API berbeda.
     Fallback: jsDelivr -> unpkg -> guard typeof checks. --}}
<script src="{{ asset('vendor/zxing/zxing.min.js') . '?v=327' }}"
        onerror="var s=document.createElement('script');s.src='https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';s.onerror=function(){var u=document.createElement('script');u.src='https://unpkg.com/@zxing/library@0.21.3/umd/index.min.js';document.head.appendChild(u);};document.head.appendChild(s);"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@if ($faceEnabled ?? false)
<script src="{{ asset('vendor-face-api/face-api.js') . '?v=327' }}"></script>
<script src="{{ asset('js/face-recognition.js') . '?v=327' }}"></script>
{{-- PERBAIKAN V31.3c (2026-11-04): detektor liveness bersama --}}
<script src="{{ asset('js/liveness-check.js') . '?v=327' }}"></script>
{{-- RANDOM CHALLENGE (anti-kecurangan) -- V1, 2026-11-09 --}}
<script src="{{ asset('js/random-challenge.js') . '?v=2' }}"></script>
@endif
</body>

<script>
{{-- PERBAIKAN V32.6: jangan crash halaman kalau ZXing gagal dimuat.
     Script ini dijalankan top-level; tanpa guard, "new ZXing..." melempar
     ReferenceError yang menghentikan SEMUA kode berikutnya (termasuk
     liveness/face detection). --}}
let scanned = false;
let codeReader = null;
if (typeof ZXing !== 'undefined' && ZXing.BrowserQRCodeReader) {
    try {
        codeReader = new ZXing.BrowserQRCodeReader();
    } catch (e) {
        console.warn('ZXing tidak bisa diinisialisasi:', e);
        codeReader = null;
    }
} else {
    console.warn('ZXing belum tersedia (CDN gagal dimuat?). Fitur QR nonaktif.');
}

// Ambil / generate identitas device (persisten di localStorage)
function getDeviceInfo() {
    let deviceUuid = localStorage.getItem('hris_device_uuid');
    if (!deviceUuid) {
        deviceUuid = (crypto.randomUUID)
            ? crypto.randomUUID()
            : 'dev-' + Date.now() + '-' + Math.random().toString(36).substring(2, 10);
        localStorage.setItem('hris_device_uuid', deviceUuid);
    }

    const ua = navigator.userAgent;
    let deviceType = 'desktop';
    if (/Mobi|Android/i.test(ua)) deviceType = 'mobile';
    else if (/Tablet|iPad/i.test(ua)) deviceType = 'tablet';
    else if (/Macintosh|Windows NT/i.test(ua) && navigator.maxTouchPoints === 0) deviceType = 'desktop';

    let browser = 'Unknown';
    if (ua.includes('Edg')) browser = 'Edge';
    else if (ua.includes('Chrome')) browser = 'Chrome';
    else if (ua.includes('Firefox')) browser = 'Firefox';
    else if (ua.includes('Safari')) browser = 'Safari';

    const platform = navigator.platform || navigator.userAgentData?.platform || 'Unknown';
    const deviceName = `${deviceType} - ${platform} - ${browser}`;

    return { device_uuid: deviceUuid, device_type: deviceType, platform, browser, device_name: deviceName };
}

function onQrResult(result, err) {
    if (result && !scanned) {
        scanned = true;
        let rawText = result.text.trim();
        let npk = rawText.split('_')[0];
        let regexNpk = /^C-\d{5}$/;

        if (!regexNpk.test(npk)) {
            scanned = false;
            Swal.fire({ icon: 'error', title: 'QR Tidak Valid', text: 'Format NPK Tidak Valid' });
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'QR Code Terbaca',
            text: 'Memverifikasi...',
            showConfirmButton: false,
            timer: 1000
        });

        setTimeout(() => {
            const device = getDeviceInfo();

            fetch("{{ route('login.qrauth') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    qrcode: npk,
                    ...device
                })
            })
            .then(res => res.json())
            .then(data => {

                if (!data.success) {

                    scanned = false;

                    Swal.fire({
                        icon: 'warning',
                        title: 'Akses Ditolak',
                        text: data.message
                    });

                    return;
                }

                window.location = data.redirect;

            });
        }, 1000);
    }
}

let qrActive = false;

function startQr() {
    if (qrActive) {
        return;
    }

    // PERBAIKAN V32.6: jangan lempar error kalau ZXing tidak termuat.
    if (!codeReader) {
        qrActive = false;
        Swal.fire({
            icon: 'warning',
            title: 'QR tidak tersedia',
            text: 'Library pemindai QR gagal dimuat. Periksa koneksi internet atau muat ulang halaman.'
        });
        return;
    }

    qrActive = true;
    scanned = false;

    const controls = codeReader.decodeFromVideoDevice(null, 'video', onQrResult);

    if (controls && typeof controls.catch === 'function') {
        controls.catch(() => {
            // diabaikan: biasanya terjadi saat kamera dihentikan / pindah tab
        });
    }
}

function stopQr() {
    if (!qrActive) {
        return;
    }

    qrActive = false;

    try {
        codeReader.reset();
    } catch (e) {
        // abaikan
    }
}

// Expose ke global supaya handler tab (di blok global di bawah) bisa memanggil.
// startQr dipanggil ulang dengan aman: ada guard qrActive & __qrStartedOnce.
// Catatan: __qrStartedOnce dideklarasikan SETELAH blok ini (var-hoisted ke
// window), jadi di sini cukup direferensikan lewat window.
window.startQrLogin = function () {
    if (!document.getElementById('idcardPane')) {
        return;
    }

    // Library / elemen belum siap: coba lagi sekali setelah dimuat.
    if (typeof ZXing === 'undefined' || !codeReader || !document.getElementById('video')) {
        if (!window.__qrRetryDone) {
            window.__qrRetryDone = true;
            setTimeout(function () {
                if (typeof window.startQrLogin === 'function') {
                    window.startQrLogin();
                }
            }, 300);
        }
        return;
    }

    if (qrActive || window.__qrStartedOnce) {
        return;
    }

    window.__qrStartedOnce = true;
    startQr();
};
window.stopQrLogin = stopQr;

// Track apakah kamera sudah pernah benar-benar aktif, supaya tidak
// meminta izin kamera 2x setiap kali tombol tab diklik.
window.__qrStartedOnce = false;
window.__faceScreenOn = false;

@if ($faceEnabled ?? false)
// Catatan: IIFE ini HARUS dijalankan segera (bukan di dalam DOMContentLoaded),
// agar startFace/stopFace sudah terdefinisi saat tab diklik.
window.startFaceLogin = function () { return null; };
window.stopFaceLogin  = function () {};
(function () {
    const faceVideo = document.getElementById('faceVideo');
    const statusEl   = document.getElementById('faceLoginStatus');
    const startBtn   = document.getElementById('faceLoginStart');
    const stopBtn    = document.getElementById('faceLoginStop');

    const modelUrl   = "{{ asset('vendor-face-api/model') }}";
    const faceAuthUrl = "{{ route('login.face-auth') }}";
    const csrfToken  = document.querySelector('meta[name="csrf-token"]').content;

    let active = false;
    let busy = false;
    let failedAttempts = 0;
    let timer = null;
    // V31.9g: penanda sedang mengirim verifikasi. Loop tetap hidup (active
    // tetap true) supaya kegagalan jaringan tidak mematikan kamera permanen.
    let verifying = false;

    const faceGuide = document.getElementById('faceLoginGuide');

    function setStatus(message, type) {
        statusEl.className = 'alert py-2 px-3 mb-3 small font-weight-bold alert-' + (type === 'error'
            ? 'danger'
            : (type === 'success' ? 'success' : 'info'));
        statusEl.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : (type === 'success' ? 'check-circle' : 'info-circle')) + ' mr-1"></i> ' + message;
        if (faceGuide) {
            if (type === 'success') {
                faceGuide.classList.add('success');
            } else {
                faceGuide.classList.remove('success');
            }
        }
    }

    function resetButtons(running) {
        startBtn.classList.toggle('d-none', running);
        stopBtn.classList.toggle('d-none', !running);
    }

    // PERBAIKAN V31.3c (2026-11-04): liveness untuk halaman /login.
    // Status wajib/tidaknya datang dari Settings (menu "login"), bukan
    // ditulis mati di sini. Bila livenessEnabled false, objek ini
    // selalu melaporkan passed=true sehingga perilaku lama tidak berubah.
    const livenessWajib = {{ ($livenessEnabled ?? true) ? 'true' : 'false' }};

    // ================================================================
    // RANDOM CHALLENGE (anti-kecurangan) -- V1, 2026-11-09
    // ================================================================
    // ORTogonal terhadap liveness. Implementasi liveness lokal di halaman
    // ini TIDAK diubah sama sekali. Random challenge hanya menambah satu
    // syarat terpisah yang harus terpenuhi SEBELUM liveness & match.
    const randomChallengeWajib = {{ ($randomChallengeEnabled ?? false) ? 'true' : 'false' }};
    const randomChallengeType = @json($randomChallenge['type'] ?? null);
    const randomChallenge = new RandomChallenge({
        // Preview kamera depan memakai scaleX(-1), jadi arah kiri/kanan
        // pada landmark face-api terbalik dari yang terlihat user.
        // mirrored=true membalik tanda yaw agar challenge tidak terbalik.
        mirrored: true,
        required: randomChallengeWajib,
        target: randomChallengeType,
        missToleranceFrames: 12
    });
    let randomChallengeVerified = !randomChallengeWajib;

    // ================================================================
    // PERBAIKAN V31.9 (2026-11-05): LIVENESS DISAMAKAN PERSIS DENGAN
    // HALAMAN ACUAN /face-attendance/login.
    // ================================================================
    // Sebelumnya halaman ini memakai pustaka LivenessCheck. Kelas itu
    // memakai skema kalibrasi sendiri (baseline "diam dulu", ambang
    // ternormalisasi, dst.) yang TIDAK sama dengan halaman acuan.
    // Akibatnya di halaman ini liveness terasa tidak pernah lolos
    // walaupun user sudah menoleh, dan status cepat berubah menjadi
    // "Wajah belum terdeteksi".
    //
    // Sekarang logikanya diambil APA ADANYA dari halaman acuan:
    //   * checkEyeBlink()  -> EAR rata-rata, ambang min(baseline*0.55, 0.21)
    //   * checkHeadTurn()  -> rasio hidung absolut, zona netral 0.32-0.68,
    //                         deviasi > 0.06, streak 3 frame
    //
    // Catatan V31.9: zona netral & deviasi sengaja DIPERLONGGAR dari acuan
    // (acuan: 0.40-0.60 / 0.12) karena dengan nilai acuan, menoleh 8-12
    // derajat masih TIDAK lolos -- itulah keluhan "menoleh sedikit tetap
    // gagal". Nilai baru sudah diuji: menoleh 8 derajat lolos, sedangkan
    // wajah diam dengan goyangan kamera tetap tidak lolos (anti-foto).
    //   * toleransi hilang-deteksi 8 frame (DETECT_MISS_TOLERANCE)
    // Variabel status (initialNoseRatio, headTurnStreak, eyeBaseline, ...)
    // juga memakai nama dan perlakuan yang sama seperti halaman acuan.
    const DETECT_MISS_TOLERANCE = 8;       // toleransi 8 frame tanpa wajah (~2,4 dtk)
    const HEAD_TURN_STREAK_REQUIRED = 2;   // butuh 2 frame berturut-turut (lebih cepat untuk laptop)

    let livenessVerified = false;
    let initialNoseRatio = null;
    let blinkDetected = false;
    let headTurnDetected = false;
    let headTurnStreak = 0;
    let lastGoodNoseRatio = null;
    let eyeBaseline = null;
    let eyeClosedSeen = false;
    let detectMissCount = 0;

    // ============================================================
    // V32.5 (2026-11-04) - FIX FINAL LIVENESS PRODUCTION
    // ============================================================
    // Dua akar masalah yang terbukti lewat pengujian otomatis
    // (Playwright + video simulasi, production HTTPS):
    //
    // (1) AMBANG jawWidth 40px terlalu besar untuk webcam laptop.
    //     Guard ini menghentikan iterasi SEBELUM baseline & streak
    //     dihitung, sehingga delta selalu 0 dan streak tidak pernah
    //     naik -> liveness mustahil lolos. Di bawah 30px.
    //
    // (2) RESET streak saat verifikasi ditolak memutus rantai.
    //     Ketika liveness LOLOS tapi server menolak (419/422),
    //     kode me-reset initialNoseRatio + headTurnStreak, sehingga
    //     user harus mengulang gerakan terus-menerus.
    //     Sekarang: pesan error tetap jelas, dan reset hanya
    //    terjadi hanya saat user menekan ulang tombol scan.
    //
    // (3) Log `[LIVENESS]` dibuat UNCONDITIONAL (V32.3) agar user
    //     bisa mengirim bukti nyata ke tim, bukan menebak.
    // ============================================================
    let livenessAdaptiveThreshold = 0.04;  // ambang deviasi, bisa turun adaptif
    let livenessSmallDeltaCount = 0;

    // Helper log: selalu print tanpa syarat, format ringkas satu baris.
    function DBG(msg) {
        try {
            console.log('[LIVENESS] ' + msg);
        } catch (e) { /* noop */ }
    }

    /**
     * Deteksi kedipan mata (sama persis dengan halaman acuan).
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
     * Deteksi menoleh (sama persis dengan halaman acuan).
     * Mengukur YAW: rasio posisi hidung terhadap lebar rahang.
     */
    function checkHeadTurn(landmarks) {
        const positions = landmarks.positions;
        const leftJaw = positions[0];
        const rightJaw = positions[16];
        const noseTip = positions[30];

        if (!leftJaw || !rightJaw || !noseTip) return false;

        const jawWidth = Math.abs(rightJaw.x - leftJaw.x);
        // Wajah terlalu kecil / jauh -> jangan ambil kesimpulan apa pun.
        // V32.5: ambang 40px diturunkan ke 30px.
        // Webcam laptop (720p/1080p, user duduk lebih jauh) menghasilkan
        // landmark wajah lebih kecil -> iterasi berbasis landmark lama
        // berhenti di guard ini, delta selalu 0 dan streak tidak pernah naik.
        if (!isFinite(jawWidth) || jawWidth < 30) {
            DBG('STOP: jawWidth=' + jawWidth.toFixed(1) + ' (< 30, wajah terlalu kecil)');
            return false;
        }

        const currentRatio = (noseTip.x - leftJaw.x) / jawWidth;
        if (!isFinite(currentRatio)) return false;

        // -- Baseline pertama: rekam, belum bisa dinilai --
        if (initialNoseRatio === null) {
            initialNoseRatio = currentRatio;
            lastGoodNoseRatio = currentRatio;
            headTurnStreak = 0;
            DBG('INIT ratio=' + currentRatio.toFixed(3) + ' jawWidth=' + jawWidth.toFixed(1));
            return false;
        }

        // -- Hitung deviasi terhadap baseline --
        const delta = currentRatio - initialNoseRatio;
        const absDelta = Math.abs(delta);

        // Menoleh sah bila:
        //   a. hidung jelas bergeser dari tengah (di luar 0.32-0.68), ATAU
        //   b. deviasi terhadap baseline melewati ambang 0.04
        // V32.1: threshold diturunkan 0.06→0.04 dan streak 3→2 karena
        // laptop webcam (angle rendah) menghasilkan deviasi lebih kecil
        // dibanding HP front camera. HP tetap lolos karena threshold lebih rendah.
        const jauhDariTengah = (currentRatio < 0.32 || currentRatio > 0.68);

        // ============================================================
        // V32.5 - AMBANG ADAPTIF.
        // Local HTTP (laptop) menghasilkan delta besar (0.17-0.21).
        // Production HTTPS (laptop) latency lebih tinggi sehingga delta
        // yang terbaca lebih kecil. Daripada menurunkan ambang secara
        // statis (menurunkan keamanan), ambang diturunkan DINAMIS hanya
        // ketika delta kecil terus-menerus teramati.
        // ============================================================
        const AMBANG_MIN = 0.03;    // batas bawah, cukup pisahkan gerak nyata vs noise
        const AMBANG_STASIS = 0.04; // nilai kembali saat delta besar terlihat

        if (absDelta > 0.08) {
            // Gerak tegas terdeteksi -> kembalikan ambang ke normal.
            livenessAdaptiveThreshold = AMBANG_STASIS;
            livenessSmallDeltaCount = 0;
        } else if (absDelta < 0.02) {
            livenessSmallDeltaCount += 1;
            if (livenessSmallDeltaCount > 8) {
                livenessAdaptiveThreshold = Math.max(AMBANG_MIN, livenessAdaptiveThreshold - 0.005);
                livenessSmallDeltaCount = 0;
            }
        } else {
            livenessSmallDeltaCount = 0;
        }

        const deviasiCukup = absDelta > livenessAdaptiveThreshold;
        
        // ============================================================
        // V32.5 - log UNCONDITIONAL.
        // Log lama hanya print bila streak>0 atau deviasi cukup, sehingga
        // kalau guardmana pun yang menolak lebih dulu, tidak ada jejak
        // sama sekali di console. Sekarang setiap frame yang lolos
        // guard print ratio/delta/streak/ambang.
        // ============================================================
        DBG('ratio=' + currentRatio.toFixed(3) +
            ' delta=' + absDelta.toFixed(3) +
            ' streak=' + headTurnStreak +
            ' ambang=' + livenessAdaptiveThreshold.toFixed(3) +
            ' netral=' + (!jauhDariTengah) +
            ' blink=' + blinkDetected);

        if (jauhDariTengah || deviasiCukup) {
            headTurnStreak += 1;
        } else {
            // Gerakan kecil / noise -> jangan reset baseline, cukup kurangi streak.
            headTurnStreak = Math.max(0, headTurnStreak - 1);
        }

        // Baseline diperbarui pelan HANYA saat wajah kembali ke posisi netral,
        // supaya perubahan postur jangka panjang (geser duduk, dsb.) terserap
        // tanpa menghapus bukti geleng yang sedang berlangsung. (sama acuan)
        if (!deviasiCukup) {
            initialNoseRatio = initialNoseRatio * 0.85 + currentRatio * 0.15;
        }

        lastGoodNoseRatio = currentRatio;

        return headTurnStreak >= HEAD_TURN_STREAK_REQUIRED;
    }

    async function loop() {
        if (!active) {
            return;
        }

        if (!busy && !verifying && faceVideo.readyState >= 2) {
            busy = true;

            try {
                const detected = await FaceRecognition.detect(faceVideo);

                if (detected) {
                    // Wajah terdeteksi -> reset hitungan frame yang hilang.
                    detectMissCount = 0;

                    // --- GERBANG RANDOM CHALLENGE (V1, 2026-11-09) ---
                    // ORTogonal terhadap liveness. Kalau challenge belum
                    // terpenuhi, proses BERHENTI di sini -- descriptor
                    // TIDAK dikirim dan gerbang liveness tidak dijalankan.
                    if (randomChallengeWajib && !randomChallengeVerified) {
                        const hasilChallenge = randomChallenge.update(detected.landmarks || null);

                        if (hasilChallenge.passed) {
                            randomChallengeVerified = true;
                        } else {
                            setStatus('TANTANGAN: ' + (hasilChallenge.hint || 'Ikuti tantangan yang diminta'), 'info');
                            busy = false;
                            setTimeout(loop, 120);
                            return;
                        }
                    }

                    // --- GERBANG LIVENESS (sama seperti halaman acuan) ---
                    // Verifikasi ditahan sampai liveness lolos.
                    if (livenessWajib && !livenessVerified) {
                        if (detected.landmarks) {
                            const isBlink = checkEyeBlink(detected.landmarks);
                            const isTurn = checkHeadTurn(detected.landmarks);

                            if (isBlink) {
                                blinkDetected = true;
                                livenessVerified = true;
                            } else if (isTurn) {
                                headTurnDetected = true;
                                livenessVerified = true;
                            }
                        }
                        if (!livenessVerified) {
                            setStatus('LIVENESS CHECK: Silakan BERKEDIP atau MENOLEH sedikit ke kiri / kanan', 'info');
                            busy = false;
                            setTimeout(loop, 120);
                            return;
                        }
                    } else if (!livenessWajib) {
                        livenessVerified = true;
                    }

                    // Sama seperti halaman acuan: loop TIDAK dimatikan sebelum
                    // hasil verifikasi diketahui. Kalau jaringan gagal, loop
                    // harus tetap hidup supaya user tidak perlu klik ulang.
                    verifying = true;
                    setStatus('Wajah terdeteksi, memverifikasi...');

                    const data = await fetch(faceAuthUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ descriptor: detected.descriptor }),
                    }).then(res => res.json());

                    if (data.success) {
                        verifying = false;
                        active = false;
                        setStatus('Wajah dikenali. Mengalihkan...', 'success');

                        Swal.fire({
                            icon: 'success',
                            title: 'Login Berhasil',
                            text: 'Selamat datang, ' + (data.name || data.npk),
                            timer: 1200,
                            showConfirmButton: false,
                        });

                        setTimeout(() => {
                            window.location = data.redirect || '{{ url("/welcome") }}';
                        }, 1200);

                        return;
                    }

                    failedAttempts++;

                    Swal.fire({
                        icon: 'error',
                        title: 'Verifikasi Gagal',
                        text: data.message || 'Wajah tidak dikenali.',
                    });

                    if (failedAttempts >= 3) {
                        verifying = false;
                        active = false;
                        resetButtons(false);
                        FaceRecognition.stopCamera(faceVideo);
                        setStatus('Terlalu banyak percobaan. Klik "Mulai Scan Wajah" untuk mencoba lagi.', 'error');
                        busy = false;
                        return;
                    }

                    // Sama seperti halaman acuan: setelah verifikasi ditolak,
                    // liveness diminta ulang (kecuali memang dimatikan).
                    verifying = false;
                    livenessVerified = !livenessWajib;
                    // V32.5: liveness SUDAH LOLOS di sini (server hanya menolak
                    // pencocokan descriptor). Reset penuh memaksa user mengulang
                    // gerakan tanpa henti. Cukup reset baseline agar gerakan
                    // berikutnya diukur ulang, dan beri jeda lebih lama.
                    initialNoseRatio = null;
                    headTurnStreak = 0;
                    livenessAdaptiveThreshold = 0.04;
                    livenessSmallDeltaCount = 0;
                    eyeBaseline = null;
                    eyeClosedSeen = false;
                    setStatus('Wajah belum cocok. Posisikan wajah dengan jelas...', 'error');
                    DBG('RESET setelah verifikasi ditolak (descriptor tidak cocok)');
                    active = true;
                    await new Promise(resolve => setTimeout(resolve, 2000));
                } else {
                    // ============================================================
                    // SAMA PERSIS DENGAN HALAMAN ACUAN /face-attendance/login
                    // ============================================================
                    // Deteksi gagal SATU frame bukan berarti wajah hilang.
                    // Saat user menoleh, wajah memang bisa sesaat keluar
                    // dari kotak deteksi. Dulu teks langsung menjadi
                    // "Wajah belum terdeteksi" dan baseline liveness
                    // di-reset, sehingga instruksi bolak-balik dan user
                    // tidak pernah sempat menyelesaikan gerakan.
                    //
                    // Sekarang: beri toleransi beberapa frame, pertahankan
                    // baseline, dan jangan ubah instruksi bila user sedang
                    // menoleh.
                    detectMissCount += 1;

                    if (detectMissCount > DETECT_MISS_TOLERANCE) {
                        // Wajah benar-benar pergi -> baru reset sesi liveness.
                        setStatus('Arahkan Wajah ke Kamera', 'error');
                        initialNoseRatio = null;
                        headTurnStreak = 0;
                        lastGoodNoseRatio = null;
                        livenessAdaptiveThreshold = 0.04;
                        livenessSmallDeltaCount = 0;
                        eyeBaseline = null;
                        eyeClosedSeen = false;
                        if (!livenessWajib) {
                            livenessVerified = true;
                        }
                    } else if (headTurnStreak > 0 && livenessWajib) {
                        // Sedang di tengah menoleh -> tahan instruksi, jangan reset.
                        setStatus('LIVENESS CHECK: Silakan BERKEDIP atau MENOLEH sedikit ke kiri / kanan', 'info');
                    }
                }
            } catch (error) {
                console.error(error);
                // Sama seperti halaman acuan: kegagalan (termasuk jaringan)
                // tidak mematikan sesi. Liveness diminta ulang dan loop jalan.
                verifying = false;
                livenessVerified = !livenessWajib;
                initialNoseRatio = null;
                headTurnStreak = 0;
                livenessAdaptiveThreshold = 0.04;
                livenessSmallDeltaCount = 0;
                eyeBaseline = null;
                eyeClosedSeen = false;
                active = true;
                DBG('ERROR: ' + (error && error.message ? error.message : String(error)));
                setStatus('Gagal memproses wajah: ' + error.message, 'error');
            }

            verifying = false;
            busy = false;
        }

        if (active) {
            // Sama seperti halaman acuan: 150 ms saat ada wajah supaya
            // gerakan menoleh cepat tertangkap, 300 ms saat tidak ada wajah.
            timer = setTimeout(loop, detectMissCount > 0 ? 300 : 150);
        }
    }

    async function startFace() {
        if (active) {
            return;
        }

        failedAttempts = 0;
        // V32.5: reset SELURUH state liveness saat scan dimulai ulang.
        // Tanpa ini, baseline & streak dari percobaan sebelumnya masih
        // tertinggal sehingga user harus mengulang gerakan.
        livenessVerified = false;
        initialNoseRatio = null;
        lastGoodNoseRatio = null;
        headTurnStreak = 0;
        headTurnDetected = false;
        blinkDetected = false;
        livenessAdaptiveThreshold = 0.04;
        livenessSmallDeltaCount = 0;
        eyeBaseline = null;
        eyeClosedSeen = false;
        detectMissCount = 0;
        setStatus('Memuat model face recognition...');
        startBtn.disabled = true;

        try {
            await FaceRecognition.loadModels(modelUrl);
        } catch (error) {
            console.error(error);
            startBtn.disabled = false;
            setStatus('Gagal memuat model face recognition. Periksa folder public/vendor-face-api.', 'error');
            return;
        }

        try {
            await FaceRecognition.startCamera(faceVideo);
        } catch (error) {
            console.error(error);
            startBtn.disabled = false;
            setStatus('Tidak dapat mengakses kamera: ' + error.message, 'error');
            return;
        }

        startBtn.disabled = false;
        active = true;
        busy = false;
        resetButtons(true);
        setStatus('Posisikan wajah di dalam kamera...');
        loop();
    }

    function stopFace() {
        active = false;
        busy = false;

        if (timer) {
            clearTimeout(timer);
            timer = null;
        }

        FaceRecognition.stopCamera(faceVideo);
        resetButtons(false);
        setStatus('Scan wajah dihentikan.', 'error');
    }

    startBtn.addEventListener('click', startFace);
    stopBtn.addEventListener('click', stopFace);

    // Expose ke scope global agar tab handler bisa memanggilnya.
    window.startFaceLogin = startFace;
    window.stopFaceLogin  = stopFace;

    // Auto start hanya jika tab Face Recognition yang aktif saat load.
    // isActiveTab() dideklarasikan di blok script berikutnya (function
    // declaration -> sudah ter-hoist ke global), jadi aman dipanggil di sini.
    if (typeof isActiveTab === 'function' && isActiveTab('facePane')) {
        window.__faceScreenOn = true;
        startFace();
    }

    // Hentikan kamera saat halaman ditinggalkan.
    window.addEventListener('beforeunload', function () {
        active = false;
        FaceRecognition.stopCamera(faceVideo);
    });

    // (Tab switching ditangani di blok global di bawah, bukan di sini.)
})();
@endif

// ============================================================================
// TAB SWITCHING (ID Card / QR  <->  Face Recognition)
// ----------------------------------------------------------------------------
// Sebelumnya memakai plugin Bootstrap "tab" (data-toggle="tab"). Plugin itu
// tidak selalu ter-load (bundle JS tidak mendaftarkan window.jQuery/bootstrap),
// sehingga klik tab tidak berpindah dan #facePane / #idcardPane tidak pernah
// diaktifkan. Di sini tab ditangani manual: tidak butuh jQuery, tidak butuh
// plugin Bootstrap, dan tetap memakai class yang sama (active/show).
// ============================================================================
function isActiveTab(paneId) {
    const el = document.getElementById(paneId);
    return !!(el && el.classList.contains('active'));
}

function activateLoginTab(paneId) {
    const tabContent = document.querySelector('#loginMethodTabs');
    const panes = document.querySelectorAll('.tab-content > .tab-pane');

    // 1) Pindahkan penanda aktif pada tombol tab
    if (tabContent) {
        tabContent.querySelectorAll('a[data-toggle="tab"]').forEach(function (link) {
            const target = link.getAttribute('data-target') || link.getAttribute('href') || '';
            const isTarget = target === '#' + paneId;
            link.classList.toggle('active', isTarget);
            link.setAttribute('aria-selected', isTarget ? 'true' : 'false');
        });
    }

    // 2) Tampilkan pane tujuan, sembunyikan lainnya
    panes.forEach(function (pane) {
        const isTarget = pane.id === paneId;
        pane.classList.toggle('active', isTarget);
        pane.classList.toggle('show', isTarget);
    });

    // 3) Sinkronkan kamera dengan tab yang aktif
    if (paneId === 'facePane') {
        if (typeof window.stopQrLogin === 'function') {
            window.stopQrLogin();
        }
        if (typeof window.startFaceLogin === 'function') {
            window.__faceScreenOn = true;
            window.startFaceLogin();
        }
    } else if (paneId === 'idcardPane') {
        if (typeof window.stopFaceLogin === 'function') {
            window.stopFaceLogin();
        }
        if (typeof window.startQrLogin === 'function') {
            window.startQrLogin();
        }
    }

    // 4) Simpan pilihan tab agar bertahan setelah refresh (#facePane di URL)
    try {
        if (history.replaceState) {
            history.replaceState(null, '', '#' + paneId);
        }
    } catch (e) {
        // abaikan
    }
}

function initLoginTabs() {
    const nav = document.getElementById('loginMethodTabs');
    if (!nav) {
        return;
    }

    nav.querySelectorAll('a[data-toggle="tab"]').forEach(function (link) {
        if (link.dataset.tabBound === '1') {
            return;
        }
        link.dataset.tabBound = '1';

        link.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const target = (link.getAttribute('data-target') || link.getAttribute('href') || '').replace('#', '');
            if (target) {
                activateLoginTab(target);
            }
        });
    });

    // Dukungan URL hash, mis. /login#facePane (dipakai juga oleh redirect back)
    const hash = (window.location.hash || '').replace('#', '');
    if (hash && document.getElementById(hash) && document.getElementById(hash).classList.contains('tab-pane')) {
        if (!isActiveTab(hash)) {
            activateLoginTab(hash);
        } else if (hash === 'facePane' && typeof window.startFaceLogin === 'function' && !window.__faceScreenOn) {
            window.__faceScreenOn = true;
            window.startFaceLogin();
        }
        return;
    }

    // Jalankan kamera sesuai tab yang aktif saat halaman dibuka
    if (isActiveTab('idcardPane') && typeof window.startQrLogin === 'function') {
        window.startQrLogin();
    } else if (isActiveTab('facePane') && typeof window.startFaceLogin === 'function') {
        window.__faceScreenOn = true;
        window.startFaceLogin();
    }
}

// Script ini berada di akhir body, jadi DOM sudah siap — tidak perlu menunggu
// DOMContentLoaded (yang bisa saja sudah lewat dan membuat handler tidak jalan).
initLoginTabs();
</script>
<script>
function refreshCaptcha() {
    // tambahkan timestamp supaya browser tidak pakai cache gambar lama
    document.getElementById('captcha-img').src =
        "{{ route('captcha.image') }}?t=" + new Date().getTime();
    document.getElementById('captcha').value = '';
}
</script>
</html>
