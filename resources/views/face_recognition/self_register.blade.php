<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pendaftaran Biometrik Wajah Mandiri - PT Chutex International Indonesia</title>
    
    @include('layout.header')

    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 12px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sr-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.28);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            animation: fadeIn 0.35s ease-in-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .sr-header {
            background: #ffffff;
            padding: 24px 24px 16px 24px;
            border-bottom: 1px solid #edf2f7;
            text-align: center;
        }
        .sr-header .badge-icon {
            width: 50px;
            height: 50px;
            background: #e8f0fe;
            color: #1e3c72;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 10px;
            box-shadow: 0 4px 10px rgba(30, 60, 114, 0.12);
        }
        .sr-body {
            padding: 24px 28px 28px 28px;
        }
        .sr-progress {
            display: flex;
            height: 6px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .sr-progress span {
            flex: 1;
            background: #e2e8f0;
            transition: background 0.3s;
        }
        .sr-progress span.done {
            background: #1e3c72;
        }
        .sr-step-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 6px;
        }
        .form-control-custom {
            border-radius: 10px;
            border: 1.5px solid #d1d5db;
            padding: 12px 14px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control-custom:focus {
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.15);
        }
        .camera-square {
            position: relative;
            width: 100%;
            max-width: 360px;
            height: 360px;
            margin: 0 auto;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
        }
        .camera-square video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        .camera-square canvas {
            display: none;
        }
        .scanner-guide-oval {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 190px;
            height: 240px;
            border: 2px dashed rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            pointer-events: none;
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .scanner-guide-oval.success {
            border-color: #1cc88a;
            border-style: solid;
            box-shadow: 0 0 25px #1cc88a, 0 0 0 9999px rgba(0, 0, 0, 0.2);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="sr-card">
    <div class="sr-header">
        <div>
            <span class="badge-icon">
                <i class="fas fa-id-card-alt"></i>
            </span>
        </div>
        <h5 class="font-weight-bold text-dark mb-1">Registrasi Wajah Mandiri</h5>
        <p class="small text-muted mb-0">PT Chutex International Indonesia &mdash; Sistem Biometrik HR</p>
    </div>

    <div class="sr-body">
        <div class="sr-progress" id="srProgress">
            <span class="done"></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div id="srWizard">

            <!-- ================= STEP 1 : VERIFIKASI IDENTITAS ================= -->
            <div class="sr-pane" id="paneStep1">
                <div class="sr-step-title">
                    <i class="fas fa-user-check text-primary mr-1"></i> Langkah 1 &mdash; Verifikasi NPK &amp; Tanggal Lahir
                </div>
                <p class="small text-muted mb-3">Masukkan NPK dan 6 digit tanggal lahir (YYMMDD) sesuai data absensi/biodata Anda.</p>
                
                <form id="formVerify" autocomplete="off">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold small text-secondary mb-1">
                            <i class="fas fa-id-badge text-primary mr-1"></i> NPK Karyawan
                        </label>
                        <input type="text" name="npk" class="form-control form-control-custom font-weight-bold text-uppercase" placeholder="CONTOH: 12345" required autofocus>
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-weight-bold small text-secondary mb-1">
                            <i class="fas fa-calendar-alt text-primary mr-1"></i> Tanggal Lahir (Format: YYMMDD)
                        </label>
                        <input type="text" name="tgl_lahir" maxlength="6" pattern="[0-9]{6}" class="form-control form-control-custom font-weight-bold" placeholder="Contoh: 980512 (12 Mei 1998)" required>
                        <small class="form-text text-muted mt-1">
                            <i class="fas fa-info-circle mr-1"></i> Contoh: Lahir 12 Mei 1998 &rarr; ketik <b>980512</b>.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg rounded-pill font-weight-bold shadow-sm" id="btnVerify" style="letter-spacing: 0.3px; padding: 12px 20px;">
                        Verifikasi Data Diri <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </form>
            </div>

            <!-- ================= STEP 2 : KONFIRMASI DATA BIODATA ================= -->
            <div class="sr-pane d-none" id="paneStep2">
                <div class="sr-step-title">
                    <i class="fas fa-user-circle text-primary mr-1"></i> Langkah 2 &mdash; Konfirmasi Identitas
                </div>
                <p class="small text-muted mb-3">Pastikan data berikut adalah identitas Anda sebelum melanjutkan ke pengambilan foto wajah.</p>

                <div class="card bg-light border-0 rounded-lg p-3 mb-3">
                    <div class="form-group mb-2">
                        <label class="small text-muted font-weight-bold mb-0">NPK</label>
                        <input type="text" id="vNpk" class="form-control-plaintext font-weight-bold text-dark py-0" readonly>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small text-muted font-weight-bold mb-0">Nama Lengkap</label>
                        <input type="text" id="vNama" class="form-control-plaintext font-weight-bold text-dark py-0" readonly>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-2">
                                <label class="small text-muted font-weight-bold mb-0">Jenis Kelamin</label>
                                <input type="text" id="vJk" class="form-control-plaintext font-weight-bold text-dark py-0" readonly>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-2">
                                <label class="small text-muted font-weight-bold mb-0">Bagian / Dept</label>
                                <input type="text" id="vBagian" class="form-control-plaintext font-weight-bold text-dark py-0" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small text-muted font-weight-bold mb-0">Status Karyawan</label>
                        <input type="text" id="vStatus" class="form-control-plaintext font-weight-bold text-dark py-0" readonly>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <button class="btn btn-outline-secondary rounded-pill px-3" id="btnBackTo1">
                        <i class="fas fa-arrow-left mr-1"></i> Kembali
                    </button>
                    <button class="btn btn-primary rounded-pill px-4 font-weight-bold shadow-sm" id="btnConfirmData">
                        Benar, Lanjut Ambil Foto <i class="fas fa-camera ml-1"></i>
                    </button>
                </div>
            </div>

            <!-- ================= STEP 3 : AMBIL FOTO WAJAH ================= -->
            <div class="sr-pane d-none" id="paneStep3">
                <div class="sr-step-title">
                    <i class="fas fa-camera text-primary mr-1"></i> Langkah 3 &mdash; Ambil Foto Wajah
                </div>
                <p class="small text-muted mb-2">Posisikan wajah di tengah panduan oval. Kamera akan <b>mengambil foto otomatis</b> saat wajah terdeteksi stabil.</p>
                
                <div class="camera-square shadow-sm mb-2">
                    <video id="recVideo" autoplay muted playsinline></video>
                    <canvas id="recCanvas"></canvas>
                    <div class="scanner-guide-oval" id="recGuide"></div>
                </div>
                <p class="text-center small mt-2 font-weight-bold mb-1" id="recStatus">Menyiapkan kamera...</p>
                
                <div class="d-flex justify-content-between mt-3">
                    <button class="btn btn-outline-secondary rounded-pill px-3" id="btnRestartReg">
                        <i class="fas fa-redo mr-1"></i> Ulang Awal
                    </button>
                    <button class="btn btn-outline-primary rounded-pill px-4 font-weight-bold shadow-sm" id="btnCapture" disabled>
                        <i class="fas fa-camera mr-1"></i> Ambil Manual
                    </button>
                </div>
            </div>

            <!-- ================= STEP 4 : LIVE VERIFY WAJAH ================= -->
            <div class="sr-pane d-none" id="paneStep4">
                <div class="sr-step-title">
                    <i class="fas fa-shield-alt text-primary mr-1"></i> Langkah 4 &mdash; Verifikasi Wajah Langsung
                </div>
                <p class="small text-muted mb-2">Lihat langsung ke kamera untuk verifikasi instan menggunakan face-api.</p>
                
                <div class="camera-square shadow-sm mb-2">
                    <video id="verVideo" autoplay muted playsinline></video>
                    <div class="scanner-guide-oval" id="verGuide"></div>
                </div>
                <p class="text-center small mt-2 font-weight-bold mb-1" id="verStatus">Menyiapkan verifikasi face-api...</p>
                
                <div class="d-flex justify-content-center mt-3">
                    <button class="btn btn-outline-warning rounded-pill px-4 d-none" id="btnRetake">
                        <i class="fas fa-redo mr-1"></i> Ambil Ulang Foto
                    </button>
                    <button class="btn btn-success rounded-pill px-4 font-weight-bold d-none" id="btnFinish">
                        <i class="fas fa-check-circle mr-1"></i> Selesai &amp; Kembali ke Menu
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="{{ asset('vendor-face-api/face-api.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const VERIFY_URL  = "{{ route('self-register-face.verify') }}";
    const STORE_URL   = "{{ route('self-register-face.store') }}";
    const CONFIRM_URL = "{{ route('self-register-face.confirm') }}";

    let modelsLoaded = false;
    let recStream = null;
    let verStream = null;
    let regToken = null;
    let regNpk = null;
    let capturedDescriptor = null;
    let autoDetectTimer = null;
    let verTimer = null;
    let isCapturing = false;
    let isVerified = false;

    function setProgress(n) {
        const bars = document.querySelectorAll('#srProgress span');
        bars.forEach((b, i) => b.classList.toggle('done', i < n));
    }

    function showPane(id) {
        document.querySelectorAll('.sr-pane').forEach(p => p.classList.add('d-none'));
        document.getElementById(id).classList.remove('d-none');
    }

    async function loadModels() {
        if (modelsLoaded) return;

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
                modelsLoaded = true;
                console.log("Model face-api berhasil dimuat dari:", url);
                return;
            } catch (err) {
                console.warn("Gagal load model dari", url, err);
            }
        }

        throw new Error("Gagal memuat model face recognition dari semua lokasi.");
    }

    async function detectOnce(videoEl) {
        if (!videoEl || videoEl.readyState < 2) return null;
        try {
            const det = await faceapi.detectSingleFace(
                videoEl, 
                new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 })
            )
            .withFaceLandmarks(true)
            .withFaceDescriptor();
            return det || null;
        } catch (e) {
            return null;
        }
    }

    function stopAllStreams() {
        if (autoDetectTimer) {
            clearInterval(autoDetectTimer);
            autoDetectTimer = null;
        }
        if (verTimer) {
            clearInterval(verTimer);
            verTimer = null;
        }
        [recStream, verStream].forEach(s => { 
            if (s) {
                s.getTracks().forEach(t => t.stop()); 
            }
        });
        recStream = verStream = null;
    }

    function resetWizard() {
        stopAllStreams();
        regToken = null;
        regNpk = null;
        capturedDescriptor = null;
        isCapturing = false;
        isVerified = false;
        document.getElementById('formVerify').reset();
        setProgress(1);
        showPane('paneStep1');
    }

    // ---------- STEP 1 : VERIFIKASI NPK & TGL LAHIR ----------
    document.getElementById('formVerify').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnVerify');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Memeriksa Data...';

        try {
            const fd = new FormData(this);
            const npkInput = (fd.get('npk') || '').trim();
            const res = await fetch(VERIFY_URL, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: fd
            }).then(r => r.json()).catch(() => ({ status: false, message: 'Gagal terhubung ke server.' }));

            if (!res.status) {
                // Jika wajah sudah terdaftar di sistem -> JANGAN lanjut ke step 2 & beri notif hubungi HRD
                if (res.already_registered) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Wajah Sudah Terdaftar',
                        html: '<div class="text-left">' +
                              '<p class="mb-2">Data biometrik wajah untuk NPK <b>' + npkInput + '</b> sudah aktif terdaftar di sistem.</p>' +
                              '<div class="alert alert-warning py-2 px-3 small mb-0 border-0" style="background-color: #fff3cd; color: #856404; border-radius: 8px;">' +
                              '<i class="fas fa-exclamation-triangle mr-1"></i> <b>Harap hubungi HRD jika ada kesalahan data atau ingin memperbarui wajah.</b>' +
                              '</div></div>',
                        confirmButtonText: 'Mengerti',
                        confirmButtonColor: '#1e3c72'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Verifikasi Gagal',
                        text: res.message || 'Data tidak ditemukan atau tanggal lahir tidak cocok.',
                        confirmButtonColor: '#1e3c72'
                    });
                }
                return;
            }

            regToken = res.token;
            regNpk   = res.employee.npk;
            const emp = res.employee;

            document.getElementById('vNpk').value = emp.npk;
            document.getElementById('vNama').value = emp.nama;
            document.getElementById('vJk').value = emp.jk || '-';
            document.getElementById('vBagian').value = emp.bagian || '-';
            document.getElementById('vStatus').value = emp.status || '-';

            setProgress(2);
            showPane('paneStep2');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Verifikasi Data Diri <i class="fas fa-arrow-right ml-1"></i>';
        }
    });

    document.getElementById('btnBackTo1').onclick = () => { 
        setProgress(1); 
        showPane('paneStep1'); 
    };

    // ---------- STEP 2 -> STEP 3 (KAMERA & AUTO-CAPTURE) ----------
    document.getElementById('btnConfirmData').onclick = async function () {
        setProgress(3);
        showPane('paneStep3');
        const st = document.getElementById('recStatus');
        const capBtn = document.getElementById('btnCapture');
        capBtn.disabled = true;
        
        st.innerHTML = '<span class="text-primary font-weight-bold"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat model AI wajah...</span>';
        try {
            await loadModels();
        } catch (err) {
            st.innerHTML = '<span class="text-danger font-weight-bold"><i class="fas fa-times-circle mr-1"></i> Gagal memuat model face recognition.</span>';
            return;
        }

        st.innerHTML = '<span class="text-info font-weight-bold"><i class="fas fa-camera mr-1"></i> Menyalakan kamera...</span>';
        try {
            recStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 640 } },
                audio: false
            });
            document.getElementById('recVideo').srcObject = recStream;
            capBtn.disabled = false;
            startAutoCapture();
        } catch (err) {
            st.innerHTML = '<span class="text-danger font-weight-bold"><i class="fas fa-times-circle mr-1"></i> Tidak dapat mengakses kamera: ' + err.message + '</span>';
        }
    };

    function startAutoCapture() {
        let steadyCount = 0;
        isCapturing = false;
        const st = document.getElementById('recStatus');
        const guide = document.getElementById('recGuide');
        guide.classList.remove('success');
        st.innerHTML = '<span class="text-muted"><i class="fas fa-user mr-1"></i> Posisikan wajah di tengah panduan oval...</span>';

        if (autoDetectTimer) clearInterval(autoDetectTimer);

        autoDetectTimer = setInterval(async () => {
            if (isCapturing) return;
            const video = document.getElementById('recVideo');
            if (!video || video.readyState < 2) return;

            let det = null;
            try {
                det = await detectOnce(video);
            } catch (e) {
                det = null;
            }

            if (isCapturing) return;

            if (det && det.descriptor) {
                const box = det.detection.box;
                if (box && box.width >= 100) {
                    steadyCount++;
                    guide.classList.add('success');
                    if (steadyCount === 1) {
                        st.innerHTML = '<span class="text-info font-weight-bold"><i class="fas fa-smile mr-1"></i> Wajah terdeteksi! Tahan posisi...</span>';
                    } else if (steadyCount >= 2) {
                        isCapturing = true;
                        clearInterval(autoDetectTimer);
                        st.innerHTML = '<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Wajah stabil! Mengambil foto otomatis...</span>';
                        await captureAndSave(det);
                    }
                    return;
                }
            }

            steadyCount = 0;
            guide.classList.remove('success');
            st.innerHTML = '<span class="text-muted"><i class="fas fa-user mr-1"></i> Posisikan wajah di tengah panduan oval...</span>';
        }, 250);
    }

    async function captureAndSave(det) {
        const video = document.getElementById('recVideo');
        capturedDescriptor = Array.from(det.descriptor);

        const cv = document.getElementById('recCanvas');
        cv.width = video.videoWidth || 640;
        cv.height = video.videoHeight || 640;
        const ctx = cv.getContext('2d');
        ctx.drawImage(video, 0, 0, cv.width, cv.height);
        const faceImg = cv.toDataURL('image/jpeg', 0.9);

        const st = document.getElementById('recStatus');
        st.innerHTML = '<span class="text-primary font-weight-bold"><i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan foto wajah ke sistem...</span>';

        Swal.fire({
            title: 'Menyimpan...',
            text: 'Sedang menyimpan data wajah Anda ke sistem.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const res = await fetch(STORE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify({
                token: regToken,
                face_img: faceImg,
                descriptor: capturedDescriptor
            })
        }).then(r => r.json()).catch(() => ({ status: false, message: 'Gagal menghubungi server.' }));

        Swal.close();

        if (!res.status) {
            Swal.fire({ icon: 'error', title: 'Gagal', text: res.message || 'Gagal menyimpan foto wajah.' });
            st.innerHTML = '<span class="text-danger">Penyimpanan gagal. Mengulang deteksi...</span>';
            setTimeout(startAutoCapture, 1500);
            return;
        }

        Swal.fire({
            icon: 'success',
            title: 'Foto Wajah Berhasil Diambil!',
            text: 'Melanjutkan ke verifikasi langsung...',
            timer: 1000,
            showConfirmButton: false
        });

        setTimeout(() => {
            if (autoDetectTimer) clearInterval(autoDetectTimer);
            startVerification();
        }, 900);
    }

    // Manual capture fallback
    document.getElementById('btnCapture').onclick = async function () {
        if (isCapturing) return;
        const st = document.getElementById('recStatus');
        st.innerHTML = '<span class="text-info font-weight-bold"><i class="fas fa-spinner fa-spin mr-1"></i> Menganalisis wajah...</span>';
        const video = document.getElementById('recVideo');
        let det = null;
        try {
            det = await detectOnce(video);
        } catch (e) {
            det = null;
        }
        if (!det || !det.descriptor) {
            st.innerHTML = '<span class="text-danger font-weight-bold">Wajah tidak terdeteksi. Posisikan wajah di dalam oval.</span>';
            return;
        }
        isCapturing = true;
        if (autoDetectTimer) clearInterval(autoDetectTimer);
        await captureAndSave(det);
    };

    document.getElementById('btnRestartReg').onclick = () => {
        stopAllStreams();
        resetWizard();
    };

    // ---------- STEP 4 : LIVE VERIFY DENGAN FACE-API (INSTAN, TANPA COUNTDOWN) ----------
    async function startVerification() {
        stopAllStreams();
        setProgress(4);
        showPane('paneStep4');

        const st = document.getElementById('verStatus');
        const verGuide = document.getElementById('verGuide');
        const retakeBtn = document.getElementById('btnRetake');
        retakeBtn.classList.add('d-none');
        verGuide.classList.remove('success');

        st.innerHTML = '<span class="text-info font-weight-bold"><i class="fas fa-camera mr-1"></i> Menyalakan kamera verifikasi...</span>';

        try {
            verStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 640 } },
                audio: false
            });
            document.getElementById('verVideo').srcObject = verStream;
        } catch (err) {
            st.innerHTML = '<span class="text-danger font-weight-bold">Gagal membuka kamera: ' + err.message + '</span>';
            return;
        }

        st.innerHTML = '<span class="text-primary font-weight-bold"><i class="fas fa-eye mr-1"></i> Lihat ke kamera untuk verifikasi wajah...</span>';

        isVerified = false;
        let isVerifying = false;

        // Munculkan tombol Ambil Ulang Foto jika setelah 8 detik belum cocok
        setTimeout(() => {
            if (!isVerified) {
                retakeBtn.classList.remove('d-none');
            }
        }, 8000);

        if (verTimer) clearInterval(verTimer);

        verTimer = setInterval(async () => {
            if (isVerified || isVerifying) return;
            isVerifying = true;

            const video = document.getElementById('verVideo');
            if (!video || video.readyState < 2) {
                isVerifying = false;
                return;
            }

            try {
                const det = await detectOnce(video);
                if (!det || !det.descriptor) {
                    verGuide.classList.remove('success');
                    st.innerHTML = '<span class="text-muted"><i class="fas fa-user mr-1"></i> Mendeteksi wajah... Hadapkan wajah lurus ke kamera.</span>';
                    isVerifying = false;
                    return;
                }

                const liveDesc = Array.from(det.descriptor);

                // Gunakan face-api Euclidean distance secara instan di browser
                let dist = 1.0;
                if (typeof faceapi !== 'undefined' && faceapi.euclideanDistance) {
                    dist = faceapi.euclideanDistance(liveDesc, capturedDescriptor);
                } else {
                    let sum = 0;
                    for (let i = 0; i < liveDesc.length; i++) {
                        const d = liveDesc[i] - capturedDescriptor[i];
                        sum += d * d;
                    }
                    dist = Math.sqrt(sum);
                }

                // Threshold toleransi face-api (0.55 stabil untuk model Tiny)
                if (dist <= 0.55) {
                    isVerified = true;
                    clearInterval(verTimer);
                    verGuide.classList.add('success');
                    st.innerHTML = '<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Wajah Terverifikasi Cocok!</span>';

                    // Konfirmasi 1x ke server di background untuk update database
                    try {
                        await fetch(CONFIRM_URL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': CSRF
                            },
                            body: JSON.stringify({
                                npk: regNpk,
                                descriptor: liveDesc
                            })
                        });
                    } catch (e) {
                        console.warn('Server confirm background warning:', e);
                    }

                    document.getElementById('btnFinish').classList.remove('d-none');
                    stopAllStreams();

                    Swal.fire({
                        icon: 'success',
                        title: 'Registrasi Wajah Berhasil!',
                        text: 'Wajah Anda berhasil diverifikasi secara instan dengan face-api.',
                        confirmButtonText: 'Selesai &amp; Menuju Login',
                        confirmButtonColor: '#1e3c72'
                    }).then(() => {
                        window.location.href = "{{ route('login') }}";
                    });
                    return;
                } else {
                    st.innerHTML = '<span class="text-warning"><i class="fas fa-info-circle mr-1"></i> Mencocokkan wajah... Harap menghadap lurus ke kamera.</span>';
                    isVerifying = false;
                }
            } catch (e) {
                isVerifying = false;
            }
        }, 250);
    }

    document.getElementById('btnRetake').onclick = async function () {
        stopAllStreams();
        setProgress(3);
        showPane('paneStep3');
        document.getElementById('btnRetake').classList.add('d-none');
        const st = document.getElementById('recStatus');
        st.innerHTML = '<span class="text-info font-weight-bold">Menyalakan kamera kembali...</span>';
        try {
            recStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 640 } },
                audio: false
            });
            document.getElementById('recVideo').srcObject = recStream;
            document.getElementById('btnCapture').disabled = false;
            startAutoCapture();
        } catch (e) {
            st.innerHTML = '<span class="text-danger font-weight-bold">Gagal membuka kamera: ' + e.message + '</span>';
        }
    };

    document.getElementById('btnFinish').onclick = () => {
        window.location.href = "{{ route('login') }}";
    };
</script>

</body>
</html>
