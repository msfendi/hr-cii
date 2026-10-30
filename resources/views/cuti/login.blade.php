<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    .camera-box {
        width: 100%;
        max-width: 450px;
        height: 320px;
        margin: 0 auto;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    .camera-box video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .scanner-guide-face {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 180px;
        height: 220px;
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

<div class="container container-center py-4">
    <div class="text-center mt-3 mb-4">
        <img src="{{ asset('img/chutex.svg') }}" style="width:130px;" class="mb-2">
        <h5 class="text-white font-weight-bold mb-1">PT. Chutex International Indonesia</h5>
        <h2 class="text-white font-weight-bold">Pengajuan Cuti Online</h2>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-body p-4">

                    @php
                        $showQr   = $qrEnabled ?? true;
                        $showFace = $faceEnabled ?? true;
                    @endphp

                    <ul class="nav nav-pills nav-justified mb-4" id="loginTab">
                        <li class="nav-item">
                            <a class="nav-link active font-weight-bold" data-toggle="tab" href="#manual">
                                <i class="fas fa-keyboard mr-1"></i> Manual
                            </a>
                        </li>
                        @if($showFace)
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" data-toggle="tab" href="#facelogin">
                                <i class="fas fa-user-circle mr-1"></i> Scan Wajah
                            </a>
                        </li>
                        @endif
                        @if($showQr)
                        <li class="nav-item">
                            <a class="nav-link font-weight-bold" data-toggle="tab" href="#qrlogin">
                                <i class="fas fa-qrcode mr-1"></i> QR Code
                            </a>
                        </li>
                        @endif
                    </ul>

                    <div class="tab-content">

                        <!-- TAB 1: MANUAL LOGIN -->
                        <div class="tab-pane fade show active" id="manual">
                            <form method="POST" action="{{ route('pengajuan-cuti.verify-manual') }}">
                                @csrf
                                <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">NPK Karyawan</label>
                                    <input type="text" class="form-control rounded-pill" name="npk" required placeholder="Masukkan NPK Anda">
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">Password (Format: YYMMDD Tgl Lahir)</label>
                                    <input type="password" class="form-control rounded-pill" name="password" required placeholder="Contoh: 980512">
                                </div>

                                <button class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm mt-4">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Masuk Pengajuan Cuti
                                </button>
                            </form>
                        </div>

                        <!-- TAB 2: FACE RECOGNITION LOGIN -->
                        @if($showFace)
                        <div class="tab-pane fade" id="facelogin">
                            <div class="text-center">
                                <div id="faceStatusAlert" class="alert alert-info py-2 px-3 mb-3 small font-weight-bold">
                                    <i class="fas fa-info-circle mr-1"></i> Klik "Mulai Kamera Wajah" untuk login dengan wajah terdaftar.
                                </div>

                                <div class="camera-box shadow-sm mb-3">
                                    <video id="faceVideo" autoplay playsinline muted></video>
                                    <div id="faceGuide" class="scanner-guide-face"></div>
                                </div>

                                <div class="d-flex justify-content-center">
                                    <button id="btnStartFace" type="button" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm" onclick="startFaceLogin()">
                                        <i class="fas fa-camera mr-1"></i> Mulai Kamera Wajah
                                    </button>
                                    <button id="btnStopFace" type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 ml-2 d-none" onclick="stopFaceLogin()">
                                        <i class="fas fa-stop mr-1"></i> Matikan Kamera
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- TAB 3: QR CODE LOGIN -->
                        @if($showQr)
                        <div class="tab-pane fade" id="qrlogin">
                            <div id="step2" class="text-center">
                                <div class="camera-box shadow-sm mb-3">
                                    <video id="video"></video>
                                </div>
                                <p class="text-muted small mb-0">Arahkan QR Code pada ID Card Anda ke kamera.</p>
                            </div>
                        </div>
                        @endif

                    </div>

                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="{{ url('/') }}" class="text-white small">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Menu Utama HRIS
                </a>
            </div>
        </div>
    </div>
</div>

@include('layout.footerscript')
<script src="https://unpkg.com/@zxing/library@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
<script src="{{ asset('js/face-recognition.js') }}"></script>

<script>
/* ==================== QR SCANNER ==================== */
let scanned = false;
let codeReader = new ZXing.BrowserQRCodeReader();

function startQrScanner() {
    scanned = false;
    const videoElem = document.getElementById('video');
    if (!videoElem) return;

    codeReader.decodeFromVideoDevice(null, 'video', (result, err) => {
        if (result && !scanned) {
            scanned = true;
            codeReader.reset();

            let arr = result.text.split("_");
            let npk = arr[0];

            Swal.fire({
                icon: 'success',
                title: 'QR Code Dikenali',
                text: 'Sedang memproses login cuti...',
                showConfirmButton: false,
                timer: 1500,
                allowOutsideClick: false
            });

            setTimeout(function(){
                window.location.href = "{{ route('pengajuan-cuti.qr-login') }}?npk=" + encodeURIComponent(npk);
            }, 1000);
        }
    });
}

function stopQrScanner() {
    try {
        codeReader.reset();
    } catch(e) {}
}

/* ==================== FACE RECOGNITION LOGIN ==================== */
const modelUrl = "{{ asset('vendor-face-api/model') }}";
const faceVideo = document.getElementById('faceVideo');
const faceStatusAlert = document.getElementById('faceStatusAlert');
const btnStartFace = document.getElementById('btnStartFace');
const btnStopFace = document.getElementById('btnStopFace');
const faceGuide = document.getElementById('faceGuide');

let faceActive = false;
let faceBusy = false;
let faceTimer = null;

function setFaceStatus(msg, type = 'info') {
    if (!faceStatusAlert) return;
    faceStatusAlert.className = 'alert py-2 px-3 mb-3 small font-weight-bold alert-' + type;
    faceStatusAlert.innerHTML = msg;
}

async function startFaceLogin() {
    if (faceActive) return;

    btnStartFace.disabled = true;
    setFaceStatus('<i class="fas fa-spinner fa-spin mr-1"></i> Memuat model face recognition...', 'warning');

    try {
        await FaceRecognition.loadModels(modelUrl);
        setFaceStatus('<i class="fas fa-spinner fa-spin mr-1"></i> Menyalakan kamera...', 'warning');
        await FaceRecognition.startCamera(faceVideo);

        faceActive = true;
        faceBusy = false;
        btnStartFace.classList.add('d-none');
        btnStopFace.classList.remove('d-none');
        btnStartFace.disabled = false;
        setFaceStatus('<i class="fas fa-camera mr-1"></i> Posisikan wajah Anda di dalam lingkaran...', 'info');

        loopFaceDetection();
    } catch (err) {
        console.error('Error startFaceLogin:', err);
        btnStartFace.disabled = false;
        setFaceStatus('<i class="fas fa-exclamation-circle mr-1"></i> ' + (err.message || 'Gagal memuat kamera atau model.'), 'danger');
    }
}

function stopFaceLogin() {
    faceActive = false;
    faceBusy = false;
    if (faceTimer) {
        clearTimeout(faceTimer);
        faceTimer = null;
    }
    FaceRecognition.stopCamera(faceVideo);
    if (faceGuide) faceGuide.classList.remove('success');
    btnStartFace.classList.remove('d-none');
    btnStopFace.classList.add('d-none');
    btnStartFace.disabled = false;
    setFaceStatus('<i class="fas fa-info-circle mr-1"></i> Kamera dimatikan. Klik "Mulai Kamera Wajah" jika ingin login.', 'secondary');
}

async function loopFaceDetection() {
    if (!faceActive) return;

    if (!faceBusy) {
        faceBusy = true;
        try {
            const detected = await FaceRecognition.detect(faceVideo);
            if (detected && detected.descriptor) {
                setFaceStatus('<i class="fas fa-spinner fa-spin mr-1"></i> Wajah terdeteksi! Memverifikasi identitas...', 'warning');
                if (faceGuide) faceGuide.classList.add('success');

                const data = await FaceRecognition.postJson("{{ route('pengajuan-cuti.face-login') }}", {
                    descriptor: detected.descriptor
                });

                if (data.success) {
                    faceActive = false;
                    FaceRecognition.stopCamera(faceVideo);
                    setFaceStatus('<i class="fas fa-check-circle mr-1"></i> ' + data.message, 'success');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Berhasil',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.redirect_url;
                    });
                    return;
                } else {
                    setFaceStatus('<i class="fas fa-times-circle mr-1"></i> ' + (data.message || 'Wajah tidak dikenali.'), 'danger');
                    if (faceGuide) faceGuide.classList.remove('success');
                    await new Promise(r => setTimeout(r, 1800));
                }
            } else {
                if (faceGuide) faceGuide.classList.remove('success');
            }
        } catch (err) {
            console.error('Error saat deteksi:', err);
        } finally {
            faceBusy = false;
        }
    }

    if (faceActive) {
        faceTimer = setTimeout(loopFaceDetection, 250);
    }
}

/* ==================== TAB SWITCH HANDLING ==================== */
$(document).ready(function() {
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = e.target.hash;
        if (target === '#qrlogin') {
            stopFaceLogin();
            startQrScanner();
        } else if (target === '#facelogin') {
            stopQrScanner();
            startFaceLogin();
        } else {
            stopQrScanner();
            stopFaceLogin();
        }
    });
});
</script>

</body>
</html>
