<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<body class="bg-gradient-primary">
@include('sweetalert::alert')

<style>
    .sr-card { max-width: 640px; margin: 3rem auto; border-radius: 14px; }
    .sr-btn-row { gap: 12px; }
    .sr-btn-row .btn { margin: 0; }
    .sr-step-title { font-size: 1rem; font-weight: 700; color: #2a5298; margin-bottom: .75rem; }
    .camera-square {
        width: 100%; max-width: 360px; margin: 0 auto;
        aspect-ratio: 1 / 1; background: #0b1220; border-radius: 12px;
        overflow: hidden; position: relative; display: flex; align-items: center; justify-content: center;
    }
    .camera-square video { width: 100%; height: 100%; object-fit: cover; }
    .camera-square canvas { display: none; }
    .sr-progress { display:flex; gap:.4rem; margin-bottom:1.25rem; }
    .sr-progress span { flex:1; height:6px; border-radius:3px; background:#e3e8f0; }
    .sr-progress span.done { background:#2a5298; }
    .sr-ro { background:#f4f6fa !important; }
</style>

<div class="container">
    <div class="text-center mt-4">
        <img src="{{ asset('img/chutex.svg') }}" style="width:130px;">
        <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
        <h2 class="h5 text-white mb-0"><b>Self Register Face Recognition</b></h2>
    </div>

    <div class="card sr-card shadow-lg">
        <div class="card-body">
            <div class="sr-progress" id="srProgress">
                <span></span><span></span><span></span><span></span><span></span>
            </div>

            <!-- ================= STEP 1 : INPUT IDENTITAS ================= -->
            <div class="sr-pane" id="paneStep1">
                <div class="sr-step-title">Langkah 1 &mdash; Verifikasi Identitas</div>
                <p class="small text-muted">Masukkan NPK, Nomor KTP, dan Tanggal Lahir (format YYMMDD) untuk dicocokkan dengan data karyawan.</p>
                <form id="formVerify" autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label>NPK</label>
                        <input type="text" class="form-control" name="npk" required placeholder="Contoh: C-00827">
                    </div>
                    <div class="form-group">
                        <label>No. Identitas (KTP)</label>
                        <input type="text" class="form-control" name="ktp" required placeholder="Nomor KTP">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir (YYMMDD)</label>
                        <input type="text" class="form-control" name="tgl_lahir" required maxlength="6" pattern="[0-9]{6}" placeholder="Contoh: 950714">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block" id="btnVerify">Cocokkan Data</button>
                </form>
                <a href="/" class="btn btn-link btn-sm mt-2">&larr; Kembali ke beranda</a>
            </div>

            <!-- ================= STEP 2 : VERIFIKASI DATA (READ ONLY) ================= -->
            <div class="sr-pane d-none" id="paneStep2">
                <div class="sr-step-title">Langkah 2 &mdash; Periksa Data Anda</div>
                <p class="small text-muted">Data di bawah diambil dari berkas karyawan dan tidak dapat diubah.</p>
                <div class="row small">
                    <div class="col-6 form-group"><label class="mb-0 text-muted">NPK</label><input class="form-control sr-ro" id="vNpk" readonly></div>
                    <div class="col-6 form-group"><label class="mb-0 text-muted">Nama</label><input class="form-control sr-ro" id="vNama" readonly></div>
                    <div class="col-6 form-group"><label class="mb-0 text-muted">No. KTP</label><input class="form-control sr-ro" id="vKtp" readonly></div>
                    <div class="col-6 form-group"><label class="mb-0 text-muted">Jenis Kelamin</label><input class="form-control sr-ro" id="vJk" readonly></div>
                    <div class="col-6 form-group"><label class="mb-0 text-muted">Bagian</label><input class="form-control sr-ro" id="vBagian" readonly></div>
                    <div class="col-6 form-group"><label class="mb-0 text-muted">Status</label><input class="form-control sr-ro" id="vStatus" readonly></div>
                </div>
                <div class="d-flex sr-btn-row px-3 mb-3">
                    <button class="btn btn-secondary" id="btnBackTo1">Perbaiki Data</button>
                    <button class="btn btn-primary flex-grow-1" id="btnConfirmData">Konfirmasi &amp; Lanjut</button>
                </div>
            </div>

            <!-- ================= STEP 4 : KAMERA RECORD WAJAH ================= -->
            <div class="sr-pane d-none" id="paneStep4">
                <div class="sr-step-title">Langkah 3 &mdash; Ambil Foto Wajah</div>
                <p class="small text-muted">Pastikan wajah terlihat jelas, pencahayaan cukup, lalu tekan <b>Ambil &amp; Simpan</b>.</p>
                <div class="camera-square">
                    <video id="recVideo" autoplay muted playsinline></video>
                    <canvas id="recCanvas"></canvas>
                </div>
                <p class="text-center small mt-2" id="recStatus">Menyiapkan kamera...</p>
                <div class="d-flex sr-btn-row px-3 mt-2 mb-3">
                    <button class="btn btn-secondary" id="btnRestartReg">Ulang dari Awal</button>
                    <button class="btn btn-primary flex-grow-1" id="btnCapture" disabled>Ambil &amp; Simpan</button>
                </div>
            </div>

            <!-- ================= STEP 5 : VERIFIKASI WAJAH ================= -->
            <div class="sr-pane d-none" id="paneStep5">
                <div class="sr-step-title">Langkah 4 &mdash; Verifikasi Wajah</div>
                <p class="small text-muted">Lihat langsung ke kamera untuk membuktikan bahwa wajah yang tersimpan adalah milik Anda.</p>
                <div class="camera-square">
                    <video id="verVideo" autoplay muted playsinline></video>
                </div>
                <p class="text-center small mt-2" id="verStatus">Memuat model verifikasi...</p>
                <div class="d-flex sr-btn-row px-3 mt-2 mb-3">
                    <button class="btn btn-secondary d-none" id="btnRetake">Ambil Ulang Foto</button>
                    <button class="btn btn-primary flex-grow-1 d-none" id="btnFinish">Selesai</button>
                </div>
            </div>

        </div>
    </div>
</div>

@include('layout.footerscript')
<script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function () {
    const VERIFY_URL  = "{{ route('self-register-face.verify') }}";
    const STORE_URL   = "{{ route('self-register-face.store') }}";
    const CONFIRM_URL = "{{ route('self-register-face.confirm') }}";
    const MODEL_URL   = "{{ asset('vendor-face-api/model') }}";
    const CSRF        = document.querySelector('meta[name="csrf-token"]').content;

    let modelsLoaded = false;
    let regToken = null;
    let regNpk   = null;
    let recStream = null;
    let verStream = null;
    let capturedDescriptor = null;

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
        try {
            console.log('Loading face-api models from:', MODEL_URL);
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]);
            modelsLoaded = true;
            console.log('Face-api models loaded successfully.');
        } catch (err) {
            console.error('Failed to load face-api models:', err);
            throw new Error('Gagal memuat model face-api: ' + (err.message || err));
        }
    }
    async function detectOnce(videoEl) {
        // Gunakan TinyFaceLandmarks (faceLandmark68TinyNet) yg sudah di-load
        const det = await faceapi.detectSingleFace(videoEl, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks(faceapi.nets.faceLandmark68TinyNet)
            .withFaceDescriptor();
        return det || null;
    }
    function stopAllStreams() {
        [recStream, verStream].forEach(s => { if (s) s.getTracks().forEach(t => t.stop()); });
        recStream = verStream = null;
    }

    // ---------- STEP 1 -> 2 ----------
    document.getElementById('formVerify').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnVerify');
        btn.disabled = true;
        try {
            const fd = new FormData(this);
            const res = await fetch(VERIFY_URL, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: fd
            }).then(r => r.json()).catch(() => ({ status:false, message:'Gagal terhubung ke server.' }));

            if (!res.status) {
                Swal.fire({ icon:'error', title:'Tidak Cocok', text:res.message||'Data tidak ditemukan.' });
                return;
            }
            const emp = res.employee;

            // NPK ditemukan & wajah sudah terdaftar -> info saja, tidak lanjut
            if (res.already_registered) {
                await Swal.fire({
                    icon: 'info',
                    title: 'Wajah Sudah Terdaftar',
                    html: '<div class="mb-2">NPK: <b>' + emp.npk + '</b><br>Nama: <b>' + emp.nama + '</b></div>Data wajah sudah terdaftar.<br>Untuk memperbarui data wajah, silakan hubungi HRD.',
                    confirmButtonText: 'OK',
                    allowOutsideClick: false
                });
                return; // tetap di Langkah 1
            }

            regToken = res.token;
            regNpk   = emp.npk;
            vNpk.value = emp.npk; vNama.value = emp.nama; vKtp.value = emp.ktp;
            vJk.value = emp.jk || '-'; vBagian.value = emp.bagian || '-'; vStatus.value = emp.status || '-';
            setProgress(2); showPane('paneStep2');
        } finally { btn.disabled = false; }
    });
    document.getElementById('btnBackTo1').onclick = () => { setProgress(1); showPane('paneStep1'); };

    // ---------- STEP 2 -> 3(SweetAlert) -> 4(kamera) ----------
    document.getElementById('btnConfirmData').onclick = async function () {
        const ok = await Swal.fire({
            icon: 'warning', title: 'Pastikan Data Benar',
            text: 'Periksa kembali data Anda sebelum registrasi wajah. Jika ragu, tekan "Perbaiki Data".',
            showCancelButton: true, confirmButtonText: 'Ya, Data Sudah Benar', cancelButtonText: 'Periksa Kembali',
            reverseButtons: true
        });
        if (!ok.isConfirmed) return;

        setProgress(3); showPane('paneStep4');
        const st = document.getElementById('recStatus');
        const capBtn = document.getElementById('btnCapture');
        st.textContent = 'Memuat model...';
        try { await loadModels(); } catch (err) { st.textContent = 'Gagal memuat model face recognition.'; return; }
        st.textContent = 'Mengakses kamera...';
        try {
            recStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 640 }, audio: false });
            document.getElementById('recVideo').srcObject = recStream;
            capBtn.disabled = false;
            st.textContent = 'Posisikan wajah di tengah kotak, lalu tekan Ambil & Simpan.';
        } catch (err) {
            st.textContent = 'Tidak dapat mengakses kamera: ' + err.message;
        }
    };
    document.getElementById('btnRestartReg').onclick = () => { stopAllStreams(); resetWizard(); };

    // ---------- STEP 4 : capture + store ----------
    document.getElementById('btnCapture').onclick = async function () {
        const st = document.getElementById('recStatus');
        const video = document.getElementById('recVideo');
        const cv = document.getElementById('recCanvas');
        const capBtn = this;
        capBtn.disabled = true;

        // 1) Capture frame dulu (freeze)
        cv.width = video.videoWidth; cv.height = video.videoHeight;
        cv.getContext('2d').drawImage(video, 0, 0);
        const faceImg = cv.toDataURL('image/jpeg', 0.9);

        // 2) Tampilkan SINGLE SweetAlert dengan loading, lalu update content
        Swal.fire({
            title: 'Menganalisis Wajah...',
            text: 'Memproses landmark wajah...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        // 3) Analisis wajah (detect + landmarks + descriptor)
        let det;
        try { det = await detectOnce(video); } catch (e) { det = null; }

        if (!det) {
            Swal.close(); // close loading modal
            await Swal.fire({ icon: 'error', title: 'Gagal', text: 'Wajah tidak terdeteksi. Coba lagi dengan pencahayaan lebih baik.', confirmButtonText: 'OK' });
            capBtn.disabled = false;
            return;
        }

        capturedDescriptor = Array.from(det.descriptor);

        // 4) Update SAME modal ke success "Tersimpan"
        await Swal.fire({
            icon: 'success',
            title: 'Tersimpan',
            text: 'Data wajah berhasil dianalisis. Menyimpan ke server...',
            timer: 1500,
            showConfirmButton: false
        });

        // 5) Kirim ke server - show loading again
        st.textContent = 'Menyimpan wajah ke server...';
        Swal.fire({
            title: 'Menyimpan...',
            text: 'Mengirim data wajah ke server.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const res = await fetch(STORE_URL, {
            method: 'POST',
            headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF },
            body: JSON.stringify({ token: regToken, face_img: faceImg, descriptor: capturedDescriptor })
        }).then(r => r.json()).catch(() => ({ status:false, message:'Gagal menyimpan.' }));

        Swal.close(); // close saving modal
        if (!res.status) {
            Swal.fire({ icon:'error', title:'Gagal', text:'Gagal menyimpan wajah.' });
            st.textContent = 'Simpan gagal. Tekan Ambil & Simpan untuk mencoba lagi.';
            this.disabled = false;
            return;
        }
        Swal.fire({ icon:'success', title:'Tersimpan', text:'Foto wajah berhasil disimpan. Lanjut verifikasi.', timer:1500, showConfirmButton:false });
        setTimeout(startVerification, 1200);
    };

    // ---------- STEP 5 : live verify vs stored ----------
    async function startVerification() {
        setProgress(4); showPane('paneStep5');
        const st = document.getElementById('verStatus');
        st.textContent = 'Memuat model...';
        try { await loadModels(); } catch(e){ st.textContent='Gagal memuat model.'; return; }
        st.textContent = 'Mengakses kamera...';
        try {
            verStream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:640, height:640 }, audio:false });
            document.getElementById('verVideo').srcObject = verStream;
        } catch(e){ st.textContent='Tidak dapat mengakses kamera: '+e.message; return; }

        st.textContent = 'Hadapkan wajah ke kamera...';
        let tries = 0, done = false;
        const loop = setInterval(async () => {
            if (done) return; done = true;
            const video = document.getElementById('verVideo');
            try {
                const det = await detectOnce(video);
                if (!det) { st.textContent='Wajah belum terdeteksi, coba dekatkan wajah.'; done=false; return; }
                const res = await fetch(CONFIRM_URL, {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF },
                    body: JSON.stringify({ npk: regNpk, descriptor: Array.from(det.descriptor) })
                }).then(r=>r.json()).catch(()=>({status:false,matched:false}));

                if (res.matched) {
                    clearInterval(loop);
                    setProgress(5);
                    st.textContent = 'Verifikasi berhasil!';
                    document.getElementById('btnFinish').classList.remove('d-none');
                    Swal.fire({ icon:'success', title:'Registrasi Selesai', text:'Wajah berhasil diverifikasi dan siap digunakan.' });
                    stopAllStreams();
                } else {
                    tries++;
                    if (tries >= 3) {
                        clearInterval(loop);
                        st.textContent = 'Verifikasi gagal 3x. Silakan ambil ulang foto.';
                        document.getElementById('btnRetake').classList.remove('d-none');
                        stopAllStreams();
                        return;
                    }
                    st.textContent = 'Belum cocok ('+tries+'/3). Posisikan wajah lebih jelas...';
                    done = false;
                }
            } catch(e){ done=false; }
        }, 900);
    }
    document.getElementById('btnRetake').onclick = async function () {
        stopAllStreams();
        setProgress(3); showPane('paneStep4');
        document.getElementById('btnRetake').classList.add('d-none');
        const st = document.getElementById('recStatus');
        st.textContent = 'Mengakses kamera...';
        try {
            recStream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user', width:640, height:640 }, audio:false });
            document.getElementById('recVideo').srcObject = recStream;
            document.getElementById('btnCapture').disabled = false;
            st.textContent = 'Ambil ulang foto wajah Anda.';
        } catch(e){ st.textContent='Kamera gagal: '+e.message; }
    };
    document.getElementById('btnFinish').onclick = () => { window.location.href = '/'; };

    function resetWizard() {
        regToken = null; regNpk = null; capturedDescriptor = null;
        document.getElementById('formVerify').reset();
        setProgress(1); showPane('paneStep1');
    }
    window.addEventListener('beforeunload', stopAllStreams);
})();
</script>

</body>
</html>