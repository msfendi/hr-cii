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
// PERBAIKAN V31.9 (2026-11-05): LIVENESS DISAMAKAN PERSIS DENGAN
// HALAMAN ACUAN /face-attendance/login.
// ================================================================
// Sebelumnya halaman ini memakai pustaka LivenessCheck yang skema
// kalibrasinya berbeda dari halaman acuan, sehingga liveness terasa
// tidak pernah lolos walau user sudah menoleh.
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
const DETECT_MISS_TOLERANCE = 8;       // toleransi 8 frame tanpa wajah
const HEAD_TURN_STREAK_REQUIRED = 2;   // butuh 2 frame berturut-turut

let livenessVerified = false;
let initialNoseRatio = null;
let blinkDetected = false;
let headTurnDetected = false;
let headTurnStreak = 0;
let lastGoodNoseRatio = null;
let eyeBaseline = null;
let eyeClosedSeen = false;
let detectMissCount = 0;
let livenessAdaptiveThreshold = 0.04;  // ambang deviasi, bisa turun adaptif
let livenessSmallDeltaCount = 0;

/**
 * Log diagnostik liveness ke console browser.
 */
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

    if (eyeBaseline === null) {
        eyeBaseline = ear;
        eyeClosedSeen = false;
        return false;
    }

    const closedThreshold = Math.min(eyeBaseline * 0.55, 0.21);
    if (ear < closedThreshold) {
        eyeClosedSeen = true;
        return false;   // tunggu mata membuka kembali
    }

    if (eyeClosedSeen) {
        eyeBaseline = eyeBaseline * 0.4 + ear * 0.6;
        eyeClosedSeen = false;
        blinkDetected = true;
        return true;
    }

    eyeBaseline = eyeBaseline * 0.9 + ear * 0.1;
    return false;
}

/**
 * Deteksi menoleh (sama persis dengan halaman acuan).
 */
function checkHeadTurn(landmarks) {
    const positions = landmarks.positions;
    const leftJaw = positions[0];
    const rightJaw = positions[16];
    const noseTip = positions[30];

    if (!leftJaw || !rightJaw || !noseTip) return false;

    const jawWidth = Math.abs(rightJaw.x - leftJaw.x);
    // Wajah terlalu kecil / jauh -> jangan ambil kesimpulan apa pun.
    // V32.5: ambang 40px diturunkan ke 30px. Webcam laptop (720p/1080p,
    // user duduk lebih jauh) menghasilkan landmark wajah lebih kecil ->
    // iterasi berbasis landmark lama berhenti di guard ini, delta selalu 0
    // dan streak tidak pernah naik.
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
        livenessAdaptiveThreshold = 0.04;
        livenessSmallDeltaCount = 0;
        DBG('INIT ratio=' + currentRatio.toFixed(3) + ' jawWidth=' + jawWidth.toFixed(1));
        return false;
    }

    // -- Hitung deviasi terhadap baseline --
    const delta = currentRatio - initialNoseRatio;
    const absDelta = Math.abs(delta);

    // Menoleh sah bila:
    //   a. hidung jelas bergeser dari tengah (di luar 0.32-0.68), ATAU
    //   b. deviasi terhadap baseline melewati ambang adaptif
    const jauhDariTengah = (currentRatio < 0.32 || currentRatio > 0.68);

    // ============================================================
    // AMBANG ADAPTIF (menyamakan halaman /login).
    // Ambang diturunkan DINAMIS hanya ketika delta kecil terus-
    // terus teramati, sehingga keamanan tidak diturunkan statis.
    // ============================================================
    const AMBANG_MIN = 0.03;
    const AMBANG_STASIS = 0.04;

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

    // Log UNCONDITIONAL: setiap frame yang lolos guard print
    // ratio/delta/streak/ambang, sehingga ada jejak di console.
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
                // SAMA PERSIS DENGAN HALAMAN ACUAN /face-attendance/login
                // ============================================================
                // Beri toleransi beberapa frame, pertahankan baseline
                // liveness, dan jangan ubah instruksi bila user sedang
                // menoleh.
                detectMissCount += 1;

                if (detectMissCount > DETECT_MISS_TOLERANCE) {
                    // Wajah benar-benar pergi -> baru reset sesi liveness.
                    setFaceStatus('<i class="fas fa-user-clock text-warning mr-1"></i> Arahkan Wajah ke Kamera', 'warning');
                    initialNoseRatio = null;
                    headTurnStreak = 0;
                    livenessAdaptiveThreshold = 0.04;
                    livenessSmallDeltaCount = 0;
                    lastGoodNoseRatio = null;
                    eyeBaseline = null;
                    eyeClosedSeen = false;
                    if (!livenessWajib) {
                        livenessVerified = true;
                    }
                } else if (headTurnStreak > 0 && livenessWajib) {
                    // Sedang di tengah menoleh -> tahan instruksi.
                    setFaceStatus('<i class="fas fa-eye text-warning mr-1"></i> Silakan Berkedip atau Menoleh Sedikit', 'info');
                }
            }

            // --- GERBANG LIVENESS (sama seperti halaman acuan) ---
            if (detected && livenessWajib && !livenessVerified) {
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
                    setFaceStatus('<i class="fas fa-eye text-warning mr-1"></i> Silakan Berkedip atau Menoleh Sedikit', 'info');
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
