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
        /* PERBAIKAN V31.4 (2026-11-04): kamera menghadap pengguna (selfie)
           HARUS dicerminkan seperti di /login, /face-attendance, dan
           self-register. Sebelumnya halaman ini satu-satunya yang tidak
           memakai scaleX(-1), sehingga wajah tampak kiri-kanan tertukar.
           Catatan penting: ini HANYA tampilan (CSS). Data yang dikirim
           ke server tetap frame asli dari kamera, dan landmark face-api
           juga dihitung dari frame asli, jadi tidak mengganggu
           pencocokan wajah maupun liveness. */
        transform: scaleX(-1);
    }

    /* PERBAIKAN V31.5 (2026-11-04): oval pembantu.
       Sebelumnya CSS ini SUDAH ADA tetapi tidak pernah tampil, karena
       kurung penutup .camera-box video hilang (lihat catatan di atas),
       sehingga browser membuang seluruh aturan sesudahnya. */
    .scanner-guide-face {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 200px;
        height: 250px;
        border: 2px dashed rgba(255, 255, 255, 0.75);
        border-radius: 50%;
        pointer-events: none;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.32);
        transition: all 0.3s ease;
        z-index: 4;
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
                            {{-- PERBAIKAN V13 (2026-11-06): input NPK dulu
                                 sebelum kamera menyala. --}}
                            @if ($npkRequired ?? true)
                            <div id="cutiNpkStep">
                                <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">NPK Karyawan</label>
                                    <input type="text" id="cuti_npk_input" class="form-control rounded-pill"
                                        placeholder="Masukkan NPK Anda" autocomplete="off" maxlength="50">
                                    <small class="text-muted">Wajib diisi sebelum kamera dibuka.</small>
                                </div>
                                <button type="button" id="cutiNpkNextBtn" class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm mt-2"
                                    onclick="validateCutiNpk()">
                                    <i class="fas fa-arrow-right mr-1"></i> Lanjut Scan Wajah
                                </button>
                            </div>
                            @endif

                            <div id="cutiFaceCameraStep" @if ($npkRequired ?? true) style="display:none" @endif>
                            <div class="text-center">
                                <div id="faceStatusAlert" class="alert alert-info py-2 px-3 mb-3 small font-weight-bold">
                                    <i class="fas fa-info-circle mr-1"></i> Klik "Mulai Kamera Wajah" untuk login dengan wajah terdaftar.
                                </div>

                                @if ($livenessEnabled ?? true)
                                {{-- PERBAIKAN V31.3c (2026-11-04): halaman login cuti
                                     DULU tanpa pemeriksaan liveness. --}}
                                <div id="livenessFaceBanner" class="liveness-face-banner d-flex align-items-center justify-content-center mb-3">
                                    @include('face_attendance._liveness_face')
                                    <span class="liveness-face-text font-weight-bold">LIVENESS CHECK: MENOLEH</span>
                                </div>
                                @endif

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
<script src="{{ asset('vendor-face-api/face-api.js') . '?v=325' }}"></script>
<script src="{{ asset('js/face-recognition.js') . '?v=325' }}"></script>
{{-- PERBAIKAN V31.3c (2026-11-04): detektor liveness bersama --}}
<script src="{{ asset('js/liveness-check.js') . '?v=325' }}"></script>

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

// PERBAIKAN V13 (2026-11-06): NPK yang sudah tervalidasi.
// Dipakai untuk membatasi pencocokan wajah ke satu karyawan saja,
// sehingga wajah orang lain tidak bisa lolos.
let cutiValidatedNpk = '';

/**
 * PERBAIKAN V13 (2026-11-06): validasi NPK sebelum kamera menyala
 * di halaman pengajuan cuti.
 */
function validateCutiNpk() {
    const input = document.getElementById('cuti_npk_input');
    const npk = (input ? input.value : '').trim();

    if (!npk) {
        Swal.fire('NPK Wajib Diisi', 'Masukkan NPK Anda sebelum membuka kamera.', 'warning');
        return;
    }

    const btn = document.getElementById('cutiNpkNextBtn');
    if (btn) btn.disabled = true;

    Swal.fire({
        title: 'Memvalidasi NPK...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch("{{ route('pengajuan-cuti.validate-npk') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ npk: npk })
    })
        .then(async (res) => {
            const data = await res.json();

            if (!res.ok || !data.ok) {
                Swal.fire({
                    icon: 'error',
                    title: data.inactive ? 'Karyawan Sudah Keluar' : 'NPK Tidak Valid',
                    text: data.message || 'NPK tidak dapat diverifikasi. Silakan coba lagi.'
                });
                if (btn) btn.disabled = false;
                return;
            }

            cutiValidatedNpk = data.npk || npk;
            if (input) input.value = cutiValidatedNpk;

            Swal.close();

            const npkStep = document.getElementById('cutiNpkStep');
            const camStep = document.getElementById('cutiFaceCameraStep');
            if (npkStep) npkStep.style.display = 'none';
            if (camStep) camStep.style.display = 'block';
        })
        .catch((err) => {
            console.error('validateCutiNpk:', err);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Validasi NPK',
                text: 'Terjadi kesalahan jaringan. Silakan coba lagi.'
            });
            if (btn) btn.disabled = false;
        });
}

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

// PERBAIKAN V31.3c (2026-11-04): liveness untuk login pengajuan cuti.
// Status wajib/tidaknya dari Settings (menu "cuti").
const livenessWajib = {{ ($livenessEnabled ?? true) ? 'true' : 'false' }};

// ================================================================
// PERBAIKAN V14 (2026-11-06): PAKAI LivenessCheck BERSAMA.
// ================================================================
// Semua halaman face login memakai satu file:
// public/js/liveness-check.js
//   - /face-attendance/login (acuan)
//   - /self-register-face
//   - /employee-payroll
//   - /pengajuan-cuti/login (halaman ini)
//
// Tujuannya: ambang "menoleh" seragam dan toleransi sensor hilang
// seragam, sehingga user tidak perlu mengulang gerakan terus-menerus
// ("terlalu sensitif").
//
// missToleranceFrames: 12 frame (~1,8 detik) wajah boleh hilang
// tanpa menghapus progress liveness.
const liveness = new LivenessCheck({
    required: true,
    missToleranceFrames: 12
});

const DETECT_MISS_TOLERANCE = 12;      // toleransi 12 frame tanpa wajah

let livenessVerified = false;
let blinkDetected = false;
let headTurnDetected = false;
let detectMissCount = 0;

/**
 * Log diagnostik liveness ke console browser.
 */
function DBG(msg) {
    try {
        console.log('[LIVENESS] ' + msg);
    } catch (e) { /* noop */ }
}

async function loopFaceDetection() {
    if (!faceActive) return;

    if (!faceBusy) {
        faceBusy = true;
        try {
            const detected = await FaceRecognition.detect(faceVideo);

            if (detected) {
                // Wajah terdeteksi -> reset hitungan frame hilang.
                detectMissCount = 0;
            } else {
                // ============================================================
                // PERBAIKAN V14: toleransi sensor hilang (detektor BERSAMA)
                // ============================================================
                // Wajah yang hilang SEJENAK (wajar saat menoleh) tidak boleh
                // menghapus progress liveness. Hanya kalau hilang lebih
                // lama dari DETECT_MISS_TOLERANCE barulah sesi di-reset.
                detectMissCount += 1;

                if (livenessWajib && !livenessVerified) {
                    liveness.update(null);
                }

                if (detectMissCount > DETECT_MISS_TOLERANCE) {
                    // Wajah benar-benar pergi -> baru reset sesi liveness.
                    setFaceStatus('<i class="fas fa-user-clock text-warning mr-1"></i> Arahkan Wajah ke Kamera', 'warning');
                    liveness.reset();
                    if (!livenessWajib) {
                        livenessVerified = true;
                    }
                } else if (livenessWajib) {
                    // Wajah sesaat hilang -> tahan instruksi, jangan reset.
                    setFaceStatus('<i class="fas fa-eye text-warning mr-1"></i> ' + (liveness.hint() || 'Silakan Berkedip atau Menoleh Sedikit'), 'info');
                }
            }

            // --- GERBANG LIVENESS (detektor BERSAMA) ---
            if (detected && livenessWajib && !livenessVerified) {
                const hasilLiveness = liveness.update(detected.landmarks || null);

                if (hasilLiveness.passed) {
                    if (hasilLiveness.blink) {
                        blinkDetected = true;
                    } else if (hasilLiveness.turn) {
                        headTurnDetected = true;
                    }
                    livenessVerified = true;
                }

                if (!livenessVerified) {
                    setFaceStatus('<i class="fas fa-eye text-warning mr-1"></i> ' + (hasilLiveness.hint || 'Silakan Berkedip atau Menoleh Sedikit'), 'info');
                    faceBusy = false;
                    setTimeout(loopFaceDetection, 120);
                    return;
                }
            } else if (!livenessWajib) {
                livenessVerified = true;
            }

            if (detected && detected.descriptor) {
                setFaceStatus('<i class="fas fa-spinner fa-spin mr-1"></i> Wajah terdeteksi! Memverifikasi identitas...', 'warning');
                if (faceGuide) faceGuide.classList.add('success');

                const data = await FaceRecognition.postJson("{{ route('pengajuan-cuti.face-login') }}", {
                    descriptor: detected.descriptor,
                    npk: cutiValidatedNpk
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
        // Sama seperti halaman acuan: 150 ms saat ada wajah,
        // 300 ms saat tidak ada wajah.
        faceTimer = setTimeout(loopFaceDetection, detectMissCount > 0 ? 300 : 150);
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
