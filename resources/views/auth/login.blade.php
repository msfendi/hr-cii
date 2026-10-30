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
            <img src="{{ asset('img/chutex.svg') }}" style="width: 150px;">
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
                            <ul class="nav nav-pills nav-fill mb-3" id="loginMethodTabs">
                                <li class="nav-item">
                                    <a class="nav-link active font-weight-bold" data-toggle="tab" href="#idcardPane" id="idcardTabLink">
                                        <i class="fas fa-id-card mr-1"></i> ID Card (QR)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link font-weight-bold" data-toggle="tab" href="#facePane" id="faceTabLink">
                                        <i class="fas fa-camera mr-1"></i> Face Recognition
                                    </a>
                                </li>
                            </ul>
                            @endif

                            <div class="tab-content">
                                {{-- TAB QR CODE ID CARD --}}
                                @if ($showQr)
                                <div class="tab-pane fade {{ $defaultTab === 'idcard' ? 'show active' : '' }}" id="idcardPane">
                                    <div class="camera-box-sm shadow-sm">
                                        <video id="video"></video>
                                    </div>
                                    <p class="text-center text-muted small mt-2 mb-0">
                                        Arahkan QR Code ID Card ke kamera
                                    </p>
                                </div>
                                @endif

                                {{-- TAB FACE RECOGNITION --}}
                                @if ($showFace)
                                <div class="tab-pane fade {{ $defaultTab === 'face' ? 'show active' : '' }}" id="facePane">
                                    <div id="faceLoginStatus" class="alert alert-info py-2 px-3 mb-3 small font-weight-bold">
                                        <i class="fas fa-info-circle mr-1"></i> Klik "Mulai Scan Wajah" untuk mulai.
                                    </div>
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
</body>
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@if ($faceEnabled ?? false)
<script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
<script src="{{ asset('js/face-recognition.js') }}"></script>
@endif

<script>
let scanned = false;
const codeReader = new ZXing.BrowserQRCodeReader();

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

@if ($showQr ?? true)
// Hanya jalankan kamera QR jika tab ID Card yang aktif
if (document.getElementById('idcardPane') && document.getElementById('idcardPane').classList.contains('active')) {
    startQr();
}
@endif

@if ($faceEnabled ?? false)
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

    async function loop() {
        if (!active) {
            return;
        }

        if (!busy && faceVideo.readyState >= 2) {
            busy = true;

            try {
                const detected = await FaceRecognition.detect(faceVideo);

                if (detected) {
                    active = false;
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
                        resetButtons(false);
                        FaceRecognition.stopCamera(faceVideo);
                        setStatus('Terlalu banyak percobaan. Klik "Mulai Scan Wajah" untuk mencoba lagi.', 'error');
                        busy = false;
                        return;
                    }

                    setStatus('Wajah belum cocok. Posisikan wajah dengan jelas...', 'error');
                    active = true;
                    await new Promise(resolve => setTimeout(resolve, 2000));
                } else {
                    setStatus('Wajah belum terdeteksi. Hadapkan wajah ke kamera.', 'error');
                }
            } catch (error) {
                console.error(error);
                setStatus('Gagal memproses wajah: ' + error.message, 'error');
            }

            busy = false;
        }

        if (active) {
            timer = setTimeout(loop, 700);
        }
    }

    async function startFace() {
        if (active) {
            return;
        }

        failedAttempts = 0;
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

    // Jalankan kamera Face Recognition saat load HANYA jika tab Face Recognition yang aktif
    const facePaneEl = document.getElementById('facePane');
    if (facePaneEl && facePaneEl.classList.contains('active')) {
        startFace();
    }

    // Hentikan kamera saat halaman ditinggalkan.
    window.addEventListener('beforeunload', function () {
        active = false;
        FaceRecognition.stopCamera(faceVideo);
    });

    // Saat pindah tab: ID Card <-> Face Recognition.
    if (window.jQuery) {
        jQuery('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            const target = jQuery(e.target).attr('href');

            if (target === '#facePane') {
                if (typeof stopQr === 'function') {
                    stopQr();
                }
                startFace();
            } else if (target === '#idcardPane') {
                stopFace();
                if (typeof startQr === 'function') {
                    startQr();
                }
            }
        });
    }
})();
@endif
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
