<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body class="bg-gradient-primary">
@include('sweetalert::alert')

<style>
    .camera-square {
        width: 100%; max-width: 320px; margin: 0 auto;
        aspect-ratio: 1 / 1; background: #0b1220; border-radius: 12px;
        overflow: hidden; position: relative; display: flex; align-items: center; justify-content: center;
    }
    .camera-square video { width: 100%; height: 100%; object-fit: cover; }
</style>

<div class="container container-center">
    <div class="text-center mt-5">
        <img src="{{ asset('img/chutex.svg') }}" style="width:150px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h1 class="h1 text-white mb-4"><b>Pengajuan Cuti Online</b></h1>
    </div>

    <div class="card shadow-lg my-5">
        <div class="card-body">

            <ul class="nav nav-tabs" id="loginTab">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#manual">
                        Manual Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#qrlogin">
                        QR Code Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#facelogin">
                        Face Recognition
                    </a>
                </li>
            </ul>

            <div class="tab-content mt-4">

                <!-- TAB 1 : MANUAL -->
                <div class="tab-pane fade show active" id="manual">
                    <form method="POST" action="{{ route('pengajuan-cuti.verify-manual') }}">
                        @csrf
                        <div class="form-group">
                            <label>NPK</label>
                            <input type="text" class="form-control" name="npk" required placeholder="Masukkan NPK Anda">
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password" required placeholder="Masukkan Password Anda">
                        </div>

                        <button class="btn btn-primary btn-block">
                            Masuk Pengajuan Cuti
                        </button>
                    </form>
                </div>

                <!-- TAB 2 : QR CODE -->
                <div class="tab-pane fade" id="qrlogin">
                    <div>
                        @if(!($qrEnabled ?? true))
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-ban mr-1"></i><strong>QR Login dinonaktifkan.</strong><br>
                            <small>Fitur QR Login sedang dinonaktifkan oleh admin.</small>
                        </div>
                        @else
                        <div class="camera-square">
                            <video id="video"></video>
                        </div>
                        <p class="text-center text-muted mt-2">Arahkan QR Code ID Card Anda ke kamera utama.</p>
                        @endif
                    </div>
                </div>

                <!-- TAB 3 : FACE RECOGNITION -->
                <div class="tab-pane fade" id="facelogin">
                    <div>
                        <div class="camera-square">
                            <video id="faceVideo" autoplay muted playsinline></video>
                        </div>
                        <p class="text-center small mt-2 text-muted" id="faceStatus">Hadapkan wajah ke kamera untuk login pengajuan cuti.</p>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

@include('layout.footerscript')
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ==================== QR SCANNER ====================
let scanned = false;
let codeReader = new ZXing.BrowserQRCodeReader();

function startScanner() {
    scanned = false;
    codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {
        if (result && !scanned) {
            scanned = true;
            codeReader.reset();

            let arr = result.text.split("_");
            let npk = arr[0];

            Swal.fire({
                icon: 'success',
                title: 'QR Code Berhasil Dibaca',
                text: 'Sedang memproses login cuti...',
                showConfirmButton: false,
                timer: 1500,
                allowOutsideClick: false
            });

            setTimeout(function(){
                window.location.href = "{{ route('pengajuan-cuti.qr-login') }}?npk=" + npk;
            }, 1000);
        }
    });
}

function stopScanner() {
    try { codeReader.reset(); } catch(e) {}
}

// ==================== FACE RECOGNITION ====================
let faceActive = false;
let faceBusy   = false;
let faceStream = null;
let faceModelsLoaded = false;

const MODEL_URL = "{{ asset('vendor-face-api/model') }}";
const FACE_LOGIN_URL = "{{ route('pengajuan-cuti.face-login') }}";
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

function setFaceStatus(msg, isError) {
    const el = document.getElementById('faceStatus');
    if (!el) return;
    el.textContent = msg;
    el.className = 'text-center small mt-2 ' + (isError ? 'text-danger' : 'text-muted');
}

async function loadFaceModels() {
    if (faceModelsLoaded) return;
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
    await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
    await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
    faceModelsLoaded = true;
}

async function startFaceScanner() {
    if (faceActive) return;

    setFaceStatus('Memuat model face recognition...');
    try {
        await loadFaceModels();
    } catch(e) {
        setFaceStatus('Gagal memuat model face recognition.', true);
        return;
    }

    setFaceStatus('Mengakses kamera...');
    const video = document.getElementById('faceVideo');
    try {
        faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 640 }, audio: false });
        video.srcObject = faceStream;
    } catch(e) {
        setFaceStatus('Tidak dapat mengakses kamera: ' + e.message, true);
        return;
    }

    faceActive = true;
    faceBusy   = false;
    setFaceStatus('Hadapkan wajah ke kamera...');
    faceLoop();
}

function stopFaceScanner() {
    faceActive = false;
    faceBusy   = false;
    if (faceStream) {
        faceStream.getTracks().forEach(t => t.stop());
        faceStream = null;
    }
}

async function faceLoop() {
    if (!faceActive) return;

    const video = document.getElementById('faceVideo');
    if (!faceBusy && video && video.readyState >= 2) {
        faceBusy = true;
        try {
            const det = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks().withFaceDescriptor();

            if (det) {
                faceActive = false;
                setFaceStatus('Wajah terdeteksi, memverifikasi...');

                const res = await fetch(FACE_LOGIN_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ descriptor: Array.from(det.descriptor) })
                }).then(r => r.json()).catch(() => ({ status: false, message: 'Gagal terhubung ke server.' }));

                if (res.status) {
                    setFaceStatus('Wajah dikenali! Mengalihkan...', false);
                    stopFaceScanner();

                    Swal.fire({
                        icon: 'success',
                        title: 'Login Berhasil',
                        text: 'Selamat datang, ' + (res.name || res.npk),
                        timer: 1200,
                        showConfirmButton: false
                    });

                    setTimeout(() => { window.location.href = res.redirect; }, 1000);
                    return;
                }

                setFaceStatus(res.message || 'Wajah tidak cocok. Coba lagi...', true);
                await new Promise(r => setTimeout(r, 2000));
                faceActive = true;
            } else {
                setFaceStatus('Hadapkan wajah Anda ke kamera...');
            }
        } catch(e) {
            setFaceStatus('Gagal scan: ' + e.message, true);
        }
        faceBusy = false;
    }

    if (faceActive) {
        setTimeout(faceLoop, 700);
    }
}

// ==================== TAB SWITCHING ====================
$(document).ready(function() {
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = e.target.hash;
        if (target === '#qrlogin') {
            stopFaceScanner();
            startScanner();
        } else if (target === '#facelogin') {
            stopScanner();
            startFaceScanner();
        } else {
            stopScanner();
            stopFaceScanner();
        }
    });
});
</script>

</body>
</html>