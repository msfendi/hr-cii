@extends('hris_v2.layout')

@section('judul', 'Employee Login — HRIS Portal v2')

@push('styles')
<style>
    .auth-hero { padding-bottom: 26px; }
    .auth-hero h1 { font-size: clamp(1.85rem, 4.2vw, 2.9rem); }
    .auth-card { padding: 30px 28px !important; }
    .reg-card { padding: 22px 24px; }
    .reg-card .v2-menu-icon { background: rgba(34, 211, 238, .16); border-color: rgba(34, 211, 238, .4); color: #7fe6f6; }
    .v2 .form-control-lg { font-size: 1.02rem; padding: .82rem 1rem; }
    @media (max-width: 575.98px) { .auth-card { padding: 22px 18px !important; } }
</style>
@endpush

@section('content')
<section class="v2-hero auth-hero">
    <span class="v2-kicker">Employee portal</span>
    <h1>Sign in with your face — or your birth date.</h1>
    <p>Scan your face, or enter your NPK with the date of birth printed on your PKWT. No password to remember.</p>
</section>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">

        <div class="v2-card auth-card">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <div class="d-flex align-items-center">
                    <div class="v2-menu-icon mr-3"><i class="fas fa-id-badge"></i></div>
                    <div>
                        <h4 class="v2-card-title mb-1">Employee Login</h4>
                        <div class="v2-footnote">A single login for payslips, face attendance, and leave requests.</div>
                    </div>
                </div>
            </div>

            {{-- TAB: scan wajah / NPK + tanggal lahir --}}
            <ul class="nav nav-pills nav-justified mb-3" role="tablist" id="tabsLoginKaryawan">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active font-weight-bold" href="#panelWajah" data-tab="wajah" role="tab" aria-selected="true">
                        <i class="fas fa-camera mr-1"></i> Face scan
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link font-weight-bold" href="#panelLahir" data-tab="lahir" role="tab" aria-selected="false">
                        <i class="fas fa-keyboard mr-1"></i> NPK + Birth date
                    </a>
                </li>
            </ul>

            <div id="panelWajah">

            {{-- LANGKAH 1: NPK --}}
            <div class="v2-step aktif" id="stepNpk">
                <div class="form-group">
                    <label class="font-weight-bold">Employee NPK</label>
                    <input type="text" id="inputNpk" class="form-control form-control-lg"
                           placeholder="Example: C-00827" value="{{ $npk }}" autocomplete="off" autofocus>
                    <small class="v2-footnote">Enter the NPK first, then complete face verification.</small>
                </div>
                <div id="npkAlert" class="alert alert-danger d-none mb-3"></div>
                <button class="btn v2-btn v2-btn-primary btn-block btn-lg" id="btnLanjut">
                    <i class="fas fa-arrow-right mr-1"></i> Continue
                </button>
            </div>

            {{-- LANGKAH 2: VERIFIKASI WAJAH --}}
            <div class="v2-step" id="stepWajah">
                <div class="alert alert-info d-flex align-items-center mb-3">
                    <i class="fas fa-user-check mr-2"></i>
                    <div class="d-flex flex-wrap align-items-center">
                        <span class="mr-2">NPK <strong id="txtNpk"></strong> — <span id="txtNama"></span></span>
                        <button type="button" class="btn v2-btn v2-btn-ghost btn-sm px-3" id="btnGantiNpk">
                            <i class="fas fa-sync-alt mr-1"></i>Change NPK</button>
                    </div>
                </div>

                <div id="wajahBody">
                    <div class="v2-camera-shell mb-3">
                        <video id="webcam" autoplay muted playsinline></video>
                        <div class="v2-guide" id="scannerGuide"></div>
                        <div class="v2-overlay-text" id="overlayText">Preparing camera...</div>
                    </div>

                    <div class="alert alert-warning d-flex align-items-center justify-content-center mb-3" id="livenessAlertBox">
                        <span id="livenessInstructionText">Preparing face detection...</span>
                    </div>

                    <div id="verifyAlert" class="alert alert-danger d-none mb-3"></div>

                    <button class="btn v2-btn v2-btn-primary btn-block" id="btnUlangi" style="display:none;">
                        <i class="fas fa-redo mr-1"></i> Try Again
                    </button>
                </div>
            </div>

            </div>{{-- /panelWajah --}}

            {{-- TAB 2: NPK + TANGGAL LAHIR (tanpa kamera) --}}
            <div id="panelLahir" class="d-none">
                <div class="form-group">
                    <label class="font-weight-bold" for="inputNpkLahir">Employee NPK</label>
                    <input type="text" id="inputNpkLahir" class="form-control form-control-lg"
                           placeholder="Example: C-00827" autocomplete="off">
                </div>
                <div class="form-group mb-2">
                    <label class="font-weight-bold" for="inputTglLahir">Date of birth</label>
                    <input type="text" id="inputTglLahir" class="form-control form-control-lg"
                           placeholder="Example: 980512" inputmode="numeric" maxlength="6" autocomplete="off">
                    <small class="v2-footnote">6 digits in <strong>YYMMDD</strong> order (year, month, day) as recorded on your PKWT. Example: born 12 May 1998 → <strong>980512</strong>.</small>
                </div>

                <div id="lahirAlert" class="alert alert-danger d-none mb-3"></div>

                <button class="btn v2-btn v2-btn-primary btn-block btn-lg" id="btnMasukLahir">
                    <i class="fas fa-sign-in-alt mr-1"></i> Sign in
                </button>
            </div>

            <hr>
            <div class="d-flex justify-content-between">
                <a href="{{ route('v2.portal') }}" class="v2-footnote"><i class="fas fa-arrow-left mr-1"></i>Back to Portal</a>
                <span class="v2-footnote">No face registered yet? Use the <strong>NPK + Birth date</strong> tab above, or the card <strong>Self Register Face</strong> below.</span>
            </div>
        </div>

        {{-- Extra card: self-service face registration (/self-register-face) --}}
        <a href="{{ route('self-register-face.index') }}" class="v2-menu-card mt-3 d-block">
            <div class="v2-card v2-card-hover reg-card">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="v2-menu-icon mr-3">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div>
                            <h5 class="v2-card-title mb-1">Face not registered yet?</h5>
                            <div class="v2-footnote">Register your own face through Self Register Face so you can log in and check in with Face ID.</div>
                        </div>
                    </div>
                    </div>
            </div>
        </a>

    </div>
</div>
@endsection

@push('scripts')
<canvas id="faceCaptureCanvas" width="320" height="320" style="display:none;"></canvas>

<script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
<script>
    // Fallback: if the local file is missing, use the CDN (same pattern as the attendance login page).
    if (typeof faceapi === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"><\/script>');
    }
</script>
<script src="{{ asset('js/liveness-check.js') }}?v=14"></script>
<script src="{{ asset('js/random-challenge.js') }}?v=2"></script>

<script>
(function () {
    const MODE = @json($mode);
    const CHALLENGE = @json($challenge);
    const ROUTES = {
        checkNpk: @json(route('v2.employee.check-npk')),
        challenge: @json(route('v2.employee.challenge')),
        verify: @json(route('v2.employee.verify')),
        loginLahir: @json(route('v2.employee.login-lahir')),
    };
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    const LIVENESS_REQUIRED = MODE === 'liveness';
    const CHALLENGE_REQUIRED = MODE === 'random_challenge';

    let currentNpk = @json($npk);
    let currentName = @json($namaPending);
    let challenge = CHALLENGE;

    const liveness = new LivenessCheck({ required: true, missToleranceFrames: 12, lang: 'en' });
    const randomChallenge = new RandomChallenge({
        mirrored: true,
        required: CHALLENGE_REQUIRED,
        target: challenge ? challenge.type : null,
        missToleranceFrames: 12,
        lang: 'en',
    });

    let stream = null;
    let isModelLoaded = false;
    let isSubmitting = false;
    let livenessVerified = !LIVENESS_REQUIRED;
    let challengeVerified = !CHALLENGE_REQUIRED;

    const el = (id) => document.getElementById(id);

    function setStep(nama) {
        ['stepNpk', 'stepWajah'].forEach(function (s) {
            el(s).classList.toggle('aktif', s === nama);
        });
    }

    function showAlert(id, pesan) {
        const box = el(id);
        if (!box) return;
        box.innerHTML = pesan;
        box.classList.remove('d-none');
    }

    function hideAlert(id) {
        const box = el(id);
        if (box) { box.classList.add('d-none'); box.innerHTML = ''; }
    }

    // ---------------- STEP 1: NPK validation ----------------
    async function lanjutNpk() {
        const npk = (el('inputNpk').value || '').trim();
        hideAlert('npkAlert');

        if (!npk) {
            return showAlert('npkAlert', 'NPK is required.');
        }

        const btn = el('btnLanjut');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Checking NPK...';

        try {
            const res = await fetch(ROUTES.checkNpk, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ npk: npk }),
            });
            const data = await res.json();

            if (!data.success) {
                return showAlert('npkAlert', data.message || 'NPK cannot be processed.');
            }

            currentNpk = data.npk;
            currentName = data.name;
            el('txtNpk').textContent = data.npk;
            el('txtNama').textContent = data.name;

            if (data.challenge) {
                challenge = data.challenge;
                randomChallenge.reset ? randomChallenge.reset(challenge.type) : null;
            }

            setStep('stepWajah');

            // Every mode still scans the face. Mode 'none' only releases
            // the liveness & challenge gates (LIVENESS_REQUIRED/CHALLENGE_REQUIRED),
            // not the camera itself.
            mulaiKamera();
        } catch (e) {
            showAlert('npkAlert', 'Failed to reach the server. Check your connection and try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-arrow-right mr-1"></i> Continue';
        }
    }

    // ---------------- STEP 2: camera & verification ----------------
    async function loadModels() {
        if (typeof faceapi === 'undefined') {
            el('overlayText').innerHTML = 'Face library failed to load. Reload the page.';
            el('livenessInstructionText').innerHTML = 'Face library (face-api) failed to load.';
            return;
        }

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
            "/models"
        ];

        for (let url of candidates) {
            try {
                await loadFrom(url);
                isModelLoaded = true;
                return;
            } catch (err) { /* try the next candidate */ }
        }
    }

    async function mulaiKamera() {
        el('overlayText').innerHTML = 'Preparing camera...';

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false,
            });
        } catch (e) {
            showAlert('verifyAlert', 'Camera unavailable. Allow camera access in the browser and reload.');
            el('btnUlangi').style.display = 'inline-block';
            return;
        }

        const video = el('webcam');
        video.srcObject = stream;
        video.style.transform = 'scaleX(-1)';

        await loadModels();

        if (!isModelLoaded) {
            showAlert('verifyAlert', 'Face model failed to load. Check your connection and reload the page.');
            return;
        }

        if (CHALLENGE_REQUIRED) {
            el('livenessInstructionText').innerHTML = 'CHALLENGE: ' + ((challenge && challenge.hint) || 'Follow the requested movement');
        } else if (!LIVENESS_REQUIRED) {
            // Mode 'none': no movement is requested. Use a neutral message and
            // do not mention the verification mode (requested 13 Nov 2026).
            el('livenessInstructionText').innerHTML = 'Position your face inside the circle.';
        }

        detectLoop();
    }

    function hentikanKamera() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
    }

    function captureFaceImage() {
        const video = el('webcam');
        const canvas = el('faceCaptureCanvas');
        if (!video || !canvas || !video.videoWidth || !video.videoHeight) return null;

        const side = Math.min(video.videoWidth, video.videoHeight);
        const sx = (video.videoWidth - side) / 2;
        const sy = (video.videoHeight - side) / 2;
        canvas.getContext('2d').drawImage(video, sx, sy, side, side, 0, 0, canvas.width, canvas.height);

        try { return canvas.toDataURL('image/jpeg', 0.8); } catch (e) { return null; }
    }

    let isScanning = false;

    async function detectLoop() {
        const video = el('webcam');
        const overlayText = el('overlayText');
        const alertText = el('livenessInstructionText');
        const guideBox = el('scannerGuide');

        if (!video || video.paused || video.ended || isSubmitting) return;

        if (!isModelLoaded) { setTimeout(detectLoop, 400); return; }

        const detection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks(true)
            .withFaceDescriptor();

        if (detection) {
            const landmarks = detection.landmarks;

            // Gate 1: motion challenge (random challenge mode).
            if (CHALLENGE_REQUIRED && !challengeVerified) {
                const hasil = randomChallenge.update(landmarks);
                if (hasil.passed) {
                    challengeVerified = true;
                } else {
                    if (overlayText) overlayText.innerHTML = '<i class="fas fa-random text-warning mr-1"></i> ' + (hasil.hint || 'Complete the requested challenge');
                    if (alertText) alertText.innerHTML = 'CHALLENGE: ' + (hasil.hint || 'Complete the requested challenge');
                    setTimeout(detectLoop, 200);
                    return;
                }
            }

            // Gate 2: liveness (liveness mode).
            if (LIVENESS_REQUIRED && !livenessVerified) {
                const hasil = liveness.update(landmarks);
                if (hasil.passed) {
                    livenessVerified = true;
                } else {
                    if (overlayText) overlayText.innerHTML = '<i class="fas fa-eye text-warning mr-1"></i> ' + (hasil.hint || 'Please blink or turn your head slightly');
                    setTimeout(detectLoop, 200);
                    return;
                }
            }

            if (!isSubmitting && (livenessVerified && challengeVerified)) {
                isSubmitting = true;
                if (guideBox) guideBox.classList.add('liveness-ok');
                if (alertText) alertText.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Verification successful — processing login...';
                if (overlayText) overlayText.innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> Processing face...';

                const descriptor = Array.from(detection.descriptor);
                const faceImage = captureFaceImage();

                return submitVerifikasi(true, descriptor, faceImage);
            }
        } else {
            if (overlayText) overlayText.innerHTML = 'Position your face inside the circle...';
            if (CHALLENGE_REQUIRED && alertText) alertText.innerHTML = 'CHALLENGE: ' + ((challenge && challenge.hint) || 'Follow the requested movement');
        }

        setTimeout(detectLoop, 200);
    }

    async function submitVerifikasi(kirimWajah, descriptor, faceImage) {
        hideAlert('verifyAlert');
        el('overlayText').innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Verifying login...';

        const payload = { npk: currentNpk };
        if (kirimWajah) {
            payload.descriptor = descriptor;
            if (faceImage) payload.face_image = faceImage;
        }

        try {
            const res = await fetch(ROUTES.verify, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                hentikanKamera();
                el('overlayText').innerHTML = '<i class="fas fa-check-circle text-success mr-1"></i> ' + data.message;
                window.location.href = data.redirect_url;
                return;
            }

            // Tantangan kedaluwarsa -> ambil tantangan baru otomatis.
            if (data.need_challenge) {
                const r2 = await fetch(ROUTES.challenge, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                });
                const d2 = await r2.json();
                if (d2.success) {
                    challenge = d2.challenge;
                    challengeVerified = false;
                    if (randomChallenge.reset) randomChallenge.reset(challenge.type);
                }
            }

            isSubmitting = false;
            showAlert('verifyAlert', data.message || 'Verification failed.');
            el('btnUlangi').style.display = 'inline-block';
            el('livenessInstructionText').innerHTML = 'Verification not successful yet. Try again.';
        } catch (e) {
            isSubmitting = false;
            showAlert('verifyAlert', 'Failed to reach the server. Check your connection and try again.');
            el('btnUlangi').style.display = 'inline-block';
        }
    }

    // ---------------- TAB: face scan / NPK + tanggal lahir ----------------
    function pilihTab(nama) {
        document.querySelectorAll('#tabsLoginKaryawan .nav-link').forEach(function (a) {
            const aktif = a.getAttribute('data-tab') === nama;
            a.classList.toggle('active', aktif);
            a.setAttribute('aria-selected', aktif ? 'true' : 'false');
        });

        el('panelWajah').classList.toggle('d-none', nama !== 'wajah');
        el('panelLahir').classList.toggle('d-none', nama !== 'lahir');

        if (nama === 'lahir') {
            // Kamera harus dilepas selama tab tanggal lahir dipakai.
            hentikanKamera();
            el('inputNpkLahir').focus();
        } else {
            hideAlert('lahirAlert');
        }
    }

    async function masukDenganTanggalLahir() {
        const npk = (el('inputNpkLahir').value || '').trim().toUpperCase();
        const tgl = (el('inputTglLahir').value || '').replace(/[^0-9]/g, '');
        hideAlert('lahirAlert');

        if (!npk) {
            return showAlert('lahirAlert', 'NPK is required.');
        }

        if (tgl.length !== 6) {
            return showAlert('lahirAlert', 'Birth date must be 6 digits in YYMMDD format (example: 980512).');
        }

        const btn = el('btnMasukLahir');
        const labelAwal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Signing in...';

        try {
            const res = await fetch(ROUTES.loginLahir, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ npk: npk, tgl_lahir: tgl }),
            });
            const data = await res.json().catch(function () { return {}; });

            if (res.ok && data.success) {
                window.location.href = data.redirect_url || @json(route('v2.employee.home'));
                return;
            }

            if (res.status === 429) {
                return showAlert('lahirAlert', 'Too many attempts. Please wait a minute and try again.');
            }

            if (res.status === 422 && data.errors) {
                const pertama = Object.keys(data.errors)[0];
                const isi = data.errors[pertama];
                return showAlert('lahirAlert', Array.isArray(isi) ? isi[0] : isi);
            }

            showAlert('lahirAlert', data.message || 'Sign in failed. Please try again.');
        } catch (e) {
            showAlert('lahirAlert', 'Failed to reach the server. Check your connection and try again.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = labelAwal;
        }
    }

    // ---------------- Bindings ----------------
    el('btnLanjut').addEventListener('click', lanjutNpk);
    el('inputNpk').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); lanjutNpk(); }
    });
    // Tab + login pakai tanggal lahir
    document.querySelectorAll('#tabsLoginKaryawan .nav-link').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            pilihTab(a.getAttribute('data-tab'));
        });
    });
    el('inputNpkLahir').addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });
    el('inputTglLahir').addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
    ['inputNpkLahir', 'inputTglLahir'].forEach(function (id) {
        el(id).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); masukDenganTanggalLahir(); }
        });
    });
    el('btnMasukLahir').addEventListener('click', masukDenganTanggalLahir);

    el('btnGantiNpk').addEventListener('click', function () {
        hentikanKamera();
        isSubmitting = false;
        setStep('stepNpk');
        el('inputNpk').focus();
    });
    el('btnUlangi').addEventListener('click', function () {
        hideAlert('verifyAlert');
        el('btnUlangi').style.display = 'none';
        isSubmitting = false;
        livenessVerified = !LIVENESS_REQUIRED;
        challengeVerified = !CHALLENGE_REQUIRED;
        if (liveness.reset) liveness.reset();
        if (randomChallenge.reset) randomChallenge.reset(challenge ? challenge.type : null);
        if (!stream) { mulaiKamera(); return; }
        detectLoop();
    });

    // If the NPK was already validated (e.g. page refreshed), go straight to step 2.
    if (@json($npkTerkonfirmasi) && currentNpk) {
        el('txtNpk').textContent = currentNpk;
        el('txtNama').textContent = currentName;
        setStep('stepWajah');
        mulaiKamera();
    }
})();
</script>
@endpush
