@extends('hris_v2.layout')

@section('judul', 'Staff Login — HRIS Portal v2')

@push('styles')
<style>
    .auth-hero { padding-bottom: 26px; }
    .auth-hero h1 { font-size: clamp(1.85rem, 4.2vw, 2.9rem); }
    .auth-card { padding: 30px 28px !important; }
    .auth-card .v2-menu-icon { color: #bfe0ff; }
    #captcha-img { filter: none; background: #f7f9fc; padding: 4px 8px; border-radius: 10px; }
    .auth-foot a { font-size: .8rem; color: var(--v2-muted); }
    .auth-foot a:hover { color: #fff; text-decoration: none; }
    @media (max-width: 575.98px) { .auth-card { padding: 22px 18px !important; } }
</style>
@endpush

@section('content')
<section class="v2-hero auth-hero">
    <span class="v2-kicker">Staff portal</span>
    <h1>Sign in to HRIS.</h1>
    <p>One account for payroll, people data, and reports.</p>
</section>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">

        <div class="v2-card auth-card">
            <div class="text-center mb-3">
                <div class="v2-menu-icon mx-auto mb-3"><i class="fas fa-user-shield"></i></div>
                <h4 class="v2-card-title mb-1">Portal User</h4>
                <div class="v2-footnote">Sign in with your registered HRIS account.</div>
            </div>

            {{-- Pesan galat login (kredensial salah, akun dinonaktifkan, dsb).
                 Galat captcha ditampilkan di bawah kolom captcha supaya tidak dobel.
                 Pesan asli berasal dari LoginController (dipakai juga oleh v1),
                 jadi diterjemahkan di sini untuk tampilan v2. --}}
            @php
                $terjemahanGalat = [
                    'Kode captcha salah, silakan coba lagi.' => 'Incorrect captcha code, please try again.',
                    'Akun Anda dinonaktifkan. Silakan hubungi admin HRIS.' => 'Your account is disabled. Please contact the HRIS admin.',
                    'Format NPK salah' => 'Invalid NPK format.',
                    'User tidak ditemukan' => 'User not found.',
                ];
                $terjemahkanGalat = function ($pesan) use ($terjemahanGalat) {
                    $kunci = trim((string) $pesan);

                    return $terjemahanGalat[$kunci] ?? $kunci;
                };

                $pesanGalat = collect($errors->getBag('default')->messages())->except(['captcha'])->flatten()->map($terjemahkanGalat);
            @endphp
            @if($pesanGalat->isNotEmpty())
                <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.85rem;">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    <ul class="mb-0 pl-3">
                        @foreach($pesanGalat as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Login method selector --}}
            <ul class="nav nav-pills nav-justified mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active font-weight-bold" id="tabPasswordLink" data-toggle="pill" href="#panePassword" role="tab">
                        <i class="fas fa-key mr-1"></i> Password
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold {{ $faceEnabled ? '' : 'disabled' }}" id="tabFaceLink" data-toggle="pill" href="#paneFace"
                       role="tab" aria-disabled="{{ $faceEnabled ? 'false' : 'true' }}">
                        <i class="fas fa-smile mr-1"></i> Face ID
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                {{-- ============ PANE 1: PASSWORD ============ --}}
                <div class="tab-pane fade show active" id="panePassword" role="tabpanel">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        {{-- Portal v2: after login the user lands on /welcome (not /home). --}}
                        <input type="hidden" name="redirect_to" value="welcome">
                        <div class="form-group">
                            <label class="font-weight-bold">Email / Username</label>
                            <input type="text" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        {{-- Captcha: sama seperti halaman /login (validasi ada di LoginController@authenticate) --}}
                        <div class="form-group">
                            <label class="font-weight-bold">Enter Captcha Code</label>
                            <div class="text-center mb-2">
                                <img id="captcha-img"
                                     src="{{ route('captcha.image') }}?t={{ time() }}"
                                     alt="Captcha"
                                     style="border:1px solid rgba(255,255,255,.18);border-radius:10px;cursor:pointer;max-width:100%;"
                                     title="Click to refresh"
                                     onclick="refreshCaptcha()">
                            </div>
                            <div class="input-group">
                                <input type="text" autocomplete="off"
                                       class="form-control @error('captcha') is-invalid @enderror"
                                       id="captcha" name="captcha" placeholder="Enter the code above" required>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" onclick="refreshCaptcha()" title="Refresh Captcha">
                                        &#x21bb;
                                    </button>
                                </div>
                                @error('captcha')
                                <div class="invalid-feedback d-block">{{ $terjemahkanGalat($message) }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <button class="btn v2-btn v2-btn-primary btn-block">
                            <i class="fas fa-sign-in-alt mr-1"></i>Sign in</button>
                    </form>
                </div>

                {{-- ============ PANE 2: FACE ID ============ --}}
                <div class="tab-pane fade" id="paneFace" role="tabpanel">
                    @if(!$faceEnabled)
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Face recognition is disabled by admin. Use password login.</div>
                    @else
                        {{-- NPK dulu: kamera menyala OTOMATIS begitu NPK diketik
                             lengkap. Tidak ada tombol buka/tutup kamera; kamera
                             berhenti sendiri saat NPK diubah atau login sukses. --}}
                        <div class="form-group mb-2">
                            <label class="font-weight-bold" for="faceNpk">NPK</label>
                            <input type="text" id="faceNpk" name="face_npk" class="form-control"
                                   inputmode="latin" autocomplete="off" autocapitalize="characters"
                                   spellcheck="false" maxlength="20"
                                   placeholder="Enter your NPK, e.g. C-00827">
                            <small class="v2-footnote d-block mt-1">The camera starts automatically once your NPK is entered.</small>
                        </div>

                        <div id="faceLoginStatus" class="alert alert-info py-2 px-3 mt-3 mb-3" style="font-size:.85rem;">
                            <i class="fas fa-info-circle mr-1"></i>Enter your NPK to start face login.</div>

                        <div class="v2-camera-shell mb-3 d-none" id="faceShell" title="Click the camera area if the camera has not started">
                            <video id="webcam" autoplay muted playsinline></video>
                            <div class="v2-guide" id="faceGuide"></div>
                            <div class="v2-overlay-text" id="faceOverlay">Preparing camera...</div>
                        </div>
                    @endif
                </div>
            </div>

            <hr>
            <div class="d-flex justify-content-between auth-foot">
                <a href="{{ route('v2.portal') }}" class="v2-footnote"><i class="fas fa-arrow-left mr-1"></i>Back to Portal</a>
                <a href="{{ route('v2.employee.login') }}" class="v2-footnote">Employee Login <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function refreshCaptcha() {
    // Add a timestamp so the browser does not reuse a stale cached image.
    document.getElementById('captcha-img').src =
        "{{ route('captcha.image') }}?t=" + new Date().getTime();
    document.getElementById('captcha').value = '';
}
</script>

@if($faceEnabled)
<script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
<script>
    // Fallback: if the local file is missing, use the CDN (same pattern as /login).
    if (typeof faceapi === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"><\/script>');
    }
</script>
<script src="{{ asset('js/liveness-check.js') }}?v=14"></script>
<script src="{{ asset('js/random-challenge.js') }}?v=2"></script>

<script>
(function () {
    'use strict';

    var MODE = @json($mode);
    var FACE_AUTH_URL = @json($faceAuthUrl);
    var CSRF = document.querySelector('meta[name="csrf-token"]').content;

    var LIVENESS_REQUIRED  = MODE === 'liveness';
    var CHALLENGE_REQUIRED = MODE === 'random_challenge';

    var video     = document.getElementById('webcam');
    var guide     = document.getElementById('faceGuide');
    var overlay   = document.getElementById('faceOverlay');
    var statusEl  = document.getElementById('faceLoginStatus');
    var shell     = document.getElementById('faceShell');
    var faceTab   = document.getElementById('tabFaceLink');
    var facePane  = document.getElementById('paneFace');
    var npkInput  = document.getElementById('faceNpk');

    if (!video) { return; }

    // The verification libraries must exist for the modes that need them. Otherwise,
    // show the reason — never leave the camera hanging without an explanation.
    if (LIVENESS_REQUIRED && typeof LivenessCheck !== 'function') {
        setStatus('The liveness library failed to load. Reload the page.', 'error');
        return;
    }
    if (CHALLENGE_REQUIRED && typeof RandomChallenge !== 'function') {
        setStatus('Motion challenge library failed to load. Reload the page.', 'error');
        return;
    }

    var liveness = (typeof LivenessCheck === 'function')
        ? new LivenessCheck({ required: true, missToleranceFrames: 12, lang: 'en' })
        : null;
    var challenge = (typeof RandomChallenge === 'function')
        ? new RandomChallenge({ mirrored: true, required: CHALLENGE_REQUIRED, missToleranceFrames: 12, lang: 'en' })
        : null;

    var stream = null;
    var modelsReady = false;
    var running = false;
    var verifying = false;
    var livenessOk = !LIVENESS_REQUIRED;
    var challengeOk = !CHALLENGE_REQUIRED;
    var failCount = 0;
    var npkAktif = '';
    var sudahMulai = false;

    // Delay after a failed scan. The /login/face-auth endpoint is limited to 10 attempts/minute,
    // so an 8 second delay keeps the automatic retries below that limit.
    var COOLDOWN_MS = 8000;
    var cooldownUntil = 0;

    function setStatus(message, type) {
        var map = { error: 'danger', success: 'success', info: 'info', warning: 'warning' };
        var icon = { error: 'exclamation-circle', success: 'check-circle', info: 'info-circle', warning: 'exclamation-triangle' };
        statusEl.className = 'alert alert-' + (map[type] || 'info') + ' py-2 px-3 mb-3';
        statusEl.style.fontSize = '.85rem';
        statusEl.innerHTML = '<i class="fas fa-' + (icon[type] || 'info-circle') + ' mr-1"></i> ' + message;
        if (guide) {
            guide.classList.toggle('liveness-ok', type === 'success');
        }
    }

    function setRunning(on) {
        running = on;
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        if (video) { video.srcObject = null; }
        setRunning(false);
    }

    function resetGates() {
        verifying = false;
        livenessOk = !LIVENESS_REQUIRED;
        challengeOk = !CHALLENGE_REQUIRED;
        if (liveness && liveness.reset) { liveness.reset(); }
        if (CHALLENGE_REQUIRED && challenge && challenge.pickRandom) {
            challenge.pickRandom();
        } else if (challenge && challenge.reset) {
            challenge.reset();
        }
    }

    async function loadModels() {
        if (modelsReady) { return true; }
        if (typeof faceapi === 'undefined') {
            setStatus('Face recognition library failed to load. Reload the page.', 'error');
            return false;
        }

        var loadFrom = async function (url) {
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

        var candidates = [
            "{{ asset('vendor-face-api/model') }}",
            "{{ asset('models') }}",
            "/vendor-face-api/model",
            "/models"
        ];

        for (var i = 0; i < candidates.length; i++) {
            try {
                await loadFrom(candidates[i]);
                modelsReady = true;
                return true;
            } catch (e) { /* try the next candidate */ }
        }

        setStatus('Face recognition model failed to load. Check your connection and reload.', 'error');
        return false;
    }

    function pesanKameraGagal(e) {
        if (!navigator.mediaDevices) {
            return 'The browser blocks the camera because the page is served over HTTP. Use HTTPS or localhost, or use password login.';
        }
        if (e && (e.name === 'NotAllowedError' || e.name === 'SecurityError')) {
            return 'Camera access was denied by the browser. Allow the camera for this site, then click the camera area to try again.';
        }
        if (e && e.name === 'NotFoundError') {
            return 'No camera detected on this device. Use password login.';
        }
        if (e && e.name === 'NotReadableError') {
            return 'The camera is in use by another app. Close that app, then click the camera area to try again.';
        }
        return 'Camera unavailable. Allow camera access in the browser, then click the camera area to try again.';
    }

    async function startCamera() {
        setStatus('Preparing camera...', 'info');
        overlay.innerHTML = 'Preparing camera...';

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw { name: 'NoMediaDevices' };
            }
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });
        } catch (e) {
            setStatus(pesanKameraGagal(e), 'error');
            overlay.innerHTML = '<i class="fas fa-video-slash mr-1"></i> Camera inactive — click this area to try again';
            setRunning(false);
            return;
        }

        video.srcObject = stream;
        video.style.transform = 'scaleX(-1)';
        await video.play().catch(function () {});

        var ok = await loadModels();
        if (!ok) { stopCamera(); overlay.innerHTML = 'Face model failed to load'; return; }

        cooldownUntil = 0;

        if (CHALLENGE_REQUIRED) {
            challenge.pickRandom();
            overlay.innerHTML = 'CHALLENGE: ' + (challenge.hint() || 'Follow the requested movement');
        } else if (LIVENESS_REQUIRED) {
            overlay.innerHTML = 'Blink or turn your head slightly...';
        } else {
            overlay.innerHTML = 'Face scan — without motion verification';
        }

        setStatus('Point your face inside the circle.', 'info');
        setRunning(true);
        detectLoop();
    }

    async function detectLoop() {
        if (!running) { return; }

        if (video.paused || video.ended) {
            window.setTimeout(detectLoop, 400);
            return;
        }

        // Delay after a failed attempt so we do not hammer the server.
        if (Date.now() < cooldownUntil) {
            overlay.innerHTML = '<i class="fas fa-hourglass-half mr-1"></i> Preparing the next attempt...';
            window.setTimeout(detectLoop, 300);
            return;
        }

        var detection = null;
        try {
            detection = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks(true)
                .withFaceDescriptor();
        } catch (e) {
            window.setTimeout(detectLoop, 400);
            return;
        }

        if (!detection) {
            overlay.innerHTML = 'Position your face inside the circle...';
            window.setTimeout(detectLoop, 250);
            return;
        }

        var landmarks = detection.landmarks;

        // Gate 1: motion challenge (random challenge mode).
        if (CHALLENGE_REQUIRED && !challengeOk) {
            var hasilTantangan = challenge.update(landmarks);
            if (hasilTantangan.passed) {
                challengeOk = true;
            } else {
                overlay.innerHTML = '<i class="fas fa-random mr-1"></i> ' + (hasilTantangan.hint || 'Complete the requested challenge');
                window.setTimeout(detectLoop, 180);
                return;
            }
        }

        // Gate 2: liveness (liveness mode).
        if (LIVENESS_REQUIRED && !livenessOk) {
            var hasilLiveness = liveness.update(landmarks);
            if (hasilLiveness.passed) {
                livenessOk = true;
            } else {
                overlay.innerHTML = '<i class="fas fa-eye mr-1"></i> ' + (hasilLiveness.hint || 'Please blink or turn your head slightly');
                window.setTimeout(detectLoop, 180);
                return;
            }
        }

        if (verifying) { return; }

        verifying = true;
        setStatus('Face verified, processing login...', 'info');
        overlay.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Verifying...';

        submitDescriptor(Array.from(detection.descriptor));
    }

    async function submitDescriptor(descriptor) {
        try {
            var res = await fetch(FACE_AUTH_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF
                },
                // redirect_to=welcome: the v2 portal asks to land on /welcome, not /home.
                // npk: the server only compares this descriptor with employee_faces
                // rows owned by that NPK (no cross-employee matching).
                body: JSON.stringify({ descriptor: descriptor, npk: npkAktif, redirect_to: 'welcome' })
            });

            var data = await res.json();

            if (data.success) {
                stopCamera();
                setStatus('Face recognized. Redirecting...', 'success');
                overlay.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Login successful';

                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful',
                        text: 'Welcome, ' + (data.name || data.npk),
                        timer: 1200,
                        showConfirmButton: false
                    });
                }

                window.setTimeout(function () {
                    window.location = data.redirect || "{{ route('welcome') }}";
                }, 900);
                return;
            }

            failCount++;
            // The camera is NOT stopped: the gates reset and the scan repeats
            // automatically after the cooldown.
            cooldownUntil = Date.now() + COOLDOWN_MS;
            resetGates();
            setStatus(data.message || 'Face not recognized. Retrying automatically...', 'error');
            overlay.innerHTML = '<i class="fas fa-redo mr-1"></i> Scan failed — retrying automatically...';

            if (failCount >= 3) {
                setStatus('Already ' + failCount + ' failed attempts. Use password login or contact HRIS admin.', 'warning');
            }

            window.setTimeout(detectLoop, 300);
        } catch (e) {
            cooldownUntil = Date.now() + COOLDOWN_MS;
            resetGates();
            setStatus('Failed to reach the server. Retrying automatically...', 'error');
            overlay.innerHTML = '<i class="fas fa-redo mr-1"></i> Scan failed — retrying automatically...';
            window.setTimeout(detectLoop, 300);
        }
    }

    // =================================================================
    // NPK first, camera automatic (requested flow):
    // there is no camera button at all. Once a complete NPK is typed, the
    // camera switches on by itself, keeps scanning, and stops only when the
    // NPK is changed or the login succeeds. The descriptor is compared only
    // with the employee_faces row owned by that NPK.
    // =================================================================
    function npkNilai() {
        return npkInput ? npkInput.value.trim().toUpperCase() : '';
    }

    // Chutex NPK form: 1-3 letters, optional dash, 4-6 digits (e.g. C-00827).
    function npkLengkap(npk) {
        return /^[A-Z]{1,3}-?\d{4,6}$/.test(npk);
    }

    var npkTimer = null;

    function jadwalkanMulai(jeda) {
        if (npkTimer) { window.clearTimeout(npkTimer); }
        npkTimer = window.setTimeout(function () {
            npkTimer = null;
            if (!npkLengkap(npkNilai())) { return; }
            mulaiScanNpk();
        }, jeda);
    }

    function mulaiScanNpk() {
        var npk = npkNilai();

        if (!npkLengkap(npk)) { return; }
        if (running && npkAktif === npk) { return; }

        if (running) { stopCamera(); }
        resetGates();

        npkAktif = npk;
        sudahMulai = true;
        failCount = 0;
        cooldownUntil = 0;

        if (shell) { shell.classList.remove('d-none'); }
        startCamera();
    }

    if (npkInput) {
        npkInput.addEventListener('input', function () {
            var bersih = npkInput.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
            if (bersih !== npkInput.value) { npkInput.value = bersih; }

            // Editing the NPK means a different identity: stop the old scan,
            // then restart automatically for the new NPK.
            if (running) {
                stopCamera();
                npkAktif = '';
                sudahMulai = false;
                failCount = 0;
                if (shell) { shell.classList.add('d-none'); }
                if (overlay) { overlay.innerHTML = 'Preparing camera...'; }
                setStatus('NPK changed. Keep typing — the camera restarts automatically.', 'info');
            }

            jadwalkanMulai(1200);
        });

        npkInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                if (npkLengkap(npkNilai())) {
                    if (npkTimer) { window.clearTimeout(npkTimer); npkTimer = null; }
                    mulaiScanNpk();
                }
            }
        });

        // Coming back after switching tabs: continue the scan if NPK is complete.
        npkInput.addEventListener('focus', function () {
            if (!running && npkLengkap(npkNilai())) { jadwalkanMulai(500); }
        });
    }

    // Clicking the camera area is not a button: it only retries a camera that
    // already started (e.g. after the browser permission was granted).
    if (shell) {
        shell.style.cursor = 'pointer';
        shell.addEventListener('click', function () {
            if (!running && sudahMulai && npkAktif !== '') {
                cooldownUntil = 0;
                resetGates();
                startCamera();
            }
        });
    }

    window.addEventListener('beforeunload', stopCamera);
})();
</script>
@endif
@endpush
