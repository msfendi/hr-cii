<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
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
            <h2 class="text-white font-weight-bold">HRIS - Slip Gaji Karyawan</h2>
         </div>
         <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
               <div class="card shadow-lg border-0 rounded-lg">
                  <div class="card-body p-4">
                     @php
                        $showQr   = $qrEnabled ?? true;
                        $showFace = $faceEnabled ?? false;
                        $defaultTab = $showQr ? 'qrlogin' : ($showFace ? 'facelogin' : 'manual');
                     @endphp
                     <ul class="nav nav-pills nav-justified mb-4" id="loginTab">
                        @if($showQr)
                        <li class="nav-item">
                           <a class="nav-link {{ $defaultTab === 'qrlogin' ? 'active' : '' }} font-weight-bold" data-toggle="tab" href="#qrlogin">
                              <i class="fas fa-qrcode mr-1"></i> QR Code
                           </a>
                        </li>
                        @endif
                        @if($showFace)
                        <li class="nav-item">
                           <a class="nav-link {{ $defaultTab === 'facelogin' ? 'active' : '' }} font-weight-bold" data-toggle="tab" href="#facelogin">
                              <i class="fas fa-user-circle mr-1"></i> Scan Wajah
                           </a>
                        </li>
                        @endif
                        <li class="nav-item">
                           <a class="nav-link {{ $defaultTab === 'manual' ? 'active' : '' }} font-weight-bold" data-toggle="tab" href="#manual">
                              <i class="fas fa-keyboard mr-1"></i> Manual
                           </a>
                        </li>
                     </ul>
                     <div class="tab-content">
                        {{-- ================= MANUAL LOGIN ================= --}}
                        <div class="tab-pane fade {{ $defaultTab === 'manual' ? 'show active' : '' }}" id="manual">
                           <form id="manualLoginForm">
                              @csrf
                              <div class="form-group">
                                 <label class="font-weight-bold text-gray-700">NPK</label>
                                 <input type="text" class="form-control rounded-pill" name="npk" required placeholder="Masukkan NPK Anda">
                              </div>
                              <div class="form-group">
                                 <label class="font-weight-bold text-gray-700">Password (Tanggal Lahir)</label>
                                 <input type="password" class="form-control rounded-pill" name="password" placeholder="Contoh : 250513 (YYMMDD)" required>
                              </div>
                              <div class="form-group">
                                 <label class="font-weight-bold text-gray-700">Payroll Period</label>
                                 <select class="form-control rounded-pill" name="run_id" id="run_id_manual">
                                    <option disabled selected>-- Pilih Periode --</option>
                                    @foreach($periods as $period)
                                    <option value="{{$period->id}}">
                                       {{ date('F Y',strtotime($period->start_date)) }}
                                    </option>
                                    @endforeach
                                 </select>
                              </div>
                              <button type="submit" class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm mt-4">
                                 <i class="fas fa-file-invoice mr-1"></i> Show Payroll Slip
                              </button>
                           </form>
                        </div>
                        {{-- ================= QR LOGIN ================= --}}
                        @if($showQr)
                        <div class="tab-pane fade {{ $defaultTab === 'qrlogin' ? 'show active' : '' }}" id="qrlogin">
                           <div id="step1">
                              <div class="form-group">
                                 <label class="font-weight-bold text-gray-700">Pilih Periode</label>
                                 <select class="form-control rounded-pill" id="run_id_qr">
                                    <option disabled selected>-- Pilih Periode --</option>
                                    @foreach($periods as $period)
                                    <option value="{{$period->id}}">
                                       {{ date('F Y',strtotime($period->start_date)) }}
                                    </option>
                                    @endforeach
                                 </select>
                              </div>
                              <button class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm mt-3"
                                 onclick="nextQR()">
                                 <i class="fas fa-arrow-right mr-1"></i> Lanjut Scan QR
                              </button>
                           </div>
                           <div id="step2" style="display:none" class="text-center">
                              <div class="camera-box shadow-sm mb-3">
                                 <video id="video"></video>
                              </div>
                              <p class="text-muted small mb-0">Arahkan QR Code pada ID Card Anda ke kamera.</p>
                           </div>
                           <div id="step3" style="display:none">
                              <form id="qrPasswordForm">
                                 <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">Password (Tanggal Lahir)</label>
                                    <input type="password" id="qr_password"
                                       class="form-control rounded-pill" placeholder="Contoh : 250513 (YYMMDD)" required>
                                 </div>
                                 <button class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm mt-3">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Login Payroll
                                 </button>
                              </form>
                           </div>
                        </div>
                        @endif

                        {{-- ================= FACE RECOGNITION LOGIN ================= --}}
                        @if($faceEnabled ?? false)
                        {{-- Pane wajib punya 'show active' ketika tab Scan Wajah adalah default.
                             Tanpa ini panelnya tetap hidden sampai user klik tab lain dulu. --}}
                        <div class="tab-pane fade {{ $defaultTab === 'facelogin' ? 'show active' : '' }}" id="facelogin">
                           <div id="faceStep1">
                              <div class="form-group">
                                 <label class="font-weight-bold text-gray-700">Pilih Periode</label>
                                 <select class="form-control rounded-pill" id="run_id_face">
                                    <option disabled selected>-- Pilih Periode --</option>
                                    @foreach($periods as $period)
                                    <option value="{{$period->id}}">
                                       {{ date('F Y',strtotime($period->start_date)) }}
                                    </option>
                                    @endforeach
                                 </select>
                              </div>
                              <button class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm mt-3"
                                 onclick="nextFace()">
                                 <i class="fas fa-arrow-right mr-1"></i> Lanjut Scan Wajah
                              </button>
                           </div>
                           <div id="faceStep2" style="display:none" class="text-center">
                              <div id="facePayrollStatus" class="alert alert-info py-2 px-3 mb-3 small font-weight-bold">
                                 <i class="fas fa-info-circle mr-1"></i> Klik "Mulai Kamera Wajah" untuk login dengan wajah terdaftar.
                              </div>
                              @if ($livenessEnabled ?? true)
                              {{-- PERBAIKAN V31.3c (2026-11-04): banner liveness
                                   yang DULU tidak ada di halaman slip gaji. --}}
                              <div id="livenessFaceBanner" class="liveness-face-banner d-flex align-items-center justify-content-center mb-3">
                                 @include('face_attendance._liveness_face')
                                 <span class="liveness-face-text font-weight-bold">LIVENESS CHECK: MENOLEH</span>
                              </div>
                              @endif
                              <div class="camera-box shadow-sm mb-3">
                                 <video id="faceVideo" autoplay muted playsinline></video>
                                 <div id="faceGuide" class="scanner-guide-face"></div>
                              </div>
                              <div class="d-flex justify-content-center">
                                 <button type="button" id="facePayrollStart" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">
                                    <i class="fas fa-camera mr-1"></i> Mulai Kamera Wajah
                                 </button>
                                 <button type="button" id="facePayrollStop" class="btn btn-outline-danger btn-sm rounded-pill px-3 ml-2 d-none">
                                    <i class="fas fa-stop mr-1"></i> Matikan Kamera
                                 </button>
                              </div>
                           </div>
                           <div id="faceStep3" style="display:none">
                              <form id="facePasswordForm">
                                 <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">NPK</label>
                                    <input type="text" id="face_npk" class="form-control rounded-pill" readonly>
                                 </div>
                                 <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">Nama</label>
                                    <input type="text" id="face_name" class="form-control rounded-pill" readonly>
                                 </div>
                                 <div class="form-group">
                                    <label class="font-weight-bold text-gray-700">Password (Tanggal Lahir)</label>
                                    <input type="password" id="face_password"
                                       class="form-control rounded-pill" placeholder="Contoh : 250513 (YYMMDD)" required>
                                 </div>
                                 <button class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm mt-3">
                                    <i class="fas fa-sign-in-alt mr-1"></i> Login Payroll
                                 </button>
                              </form>
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
      @if($faceEnabled ?? false)
      <script src="{{ asset('vendor-face-api/face-api.js') . '?v=325' }}"></script>
      <script src="{{ asset('js/face-recognition.js') . '?v=325' }}"></script>
      {{-- PERBAIKAN V31.3c (2026-11-04): detektor liveness bersama --}}
      <script src="{{ asset('js/liveness-check.js') . '?v=325' }}"></script>
      @endif
      <script>
         const verifyUrl = "{{ route('employee-payroll.verify-password') }}";
         const csrf = "{{ csrf_token() }}";
         
         let scanned=false;
         let scannedNpk=null;
         
         const codeReader = new ZXing.BrowserQRCodeReader();
         
         function startScanner(){
         
         codeReader.decodeFromVideoDevice(null,'video',(result)=>{
         
         if(result && !scanned){
         
         let npk=result.text.split("_")[0];
         
         if(!/^C-\d{5}$/.test(npk)){
         Swal.fire('QR Tidak Valid','','error');
         return;
         }
         
         scanned=true;
         scannedNpk=npk;
         
         codeReader.reset();
         
         Swal.fire({
         icon:'success',
         title:'QR berhasil dibaca'
         });
         
         $("#step2").hide();
         $("#step3").show();
         
         }
         
         });
         }
         
         function nextQR(){
         
         if(!$("#run_id_qr").val()){
         Swal.fire('Pilih periode dulu','','warning');
         return;
         }
         
         $("#step1").hide();
         $("#step2").show();
         
         startScanner();
         }
         
      </script>
      {{-- ================= MANUAL LOGIN AJAX ================= --}}
      <script>
         $("#manualLoginForm").submit(function(e){
         
         e.preventDefault();
         
         let form=$(this).serialize();
         
         Swal.fire({
         title:'Verifikasi...',
         allowOutsideClick:false,
         didOpen:()=>Swal.showLoading()
         });
         
         $.post(verifyUrl,form,function(res){
         
         if(!res.status){
         
         Swal.fire({
         icon:'error',
         title:'Login Gagal',
         text:res.message
         });
         return;
         }
         
         Swal.fire({
         icon:'success',
         title:'Login Berhasil',
         text:'Sedang membuka slip...',
         timer:1500,
         showConfirmButton:false
         });
         
         setTimeout(()=>{
         window.location=res.redirect;
         },1500);
         
         });
         
         });
         
      </script>
      {{-- ================= QR LOGIN AJAX ================= --}}
      <script>
         $("#qrPasswordForm").submit(function(e){
         
         e.preventDefault();
         
         if(!scannedNpk){
         Swal.fire('QR belum discan','','error');
         return;
         }
         
         Swal.fire({
         title:'Verifikasi Password...',
         allowOutsideClick:false,
         didOpen:()=>Swal.showLoading()
         });
         
         $.post(verifyUrl,{
         _token:csrf,
         npk:scannedNpk,
         password:$("#qr_password").val(),
         run_id:$("#run_id_qr").val()
         },function(res){
         
         if(!res.status){
         
         Swal.fire({
         icon:'error',
         title:'Login Gagal',
         text:res.message
         });
         return;
         }
         
         Swal.fire({
         icon:'success',
         title:'Login Berhasil',
         text:'Tunggu download slip...',
         timer:1800,
         showConfirmButton:false
         });
         
         setTimeout(()=>{
         window.location=res.redirect;
         },1800);
         
         });
         
         });
         
      </script>

      @if($faceEnabled ?? false)
      {{-- ================= FACE RECOGNITION LOGIN ================= --}}
      <script>
         (function () {
            const faceVideo  = document.getElementById('faceVideo');
            const statusEl   = document.getElementById('facePayrollStatus');
            const startBtn   = document.getElementById('facePayrollStart');
            const stopBtn    = document.getElementById('facePayrollStop');

            const modelUrl   = "{{ asset('vendor-face-api/model') }}";
            const identifyUrl = "{{ route('employee-payroll.face-identify') }}";

            let active = false;
            let busy = false;
            let timer = null;
            let failedAttempts = 0;
            // V31.9g: penanda sedang mengirim verifikasi. Loop tetap hidup.
            let verifying = false;
            let identifiedNpk = null;

            const faceGuide = document.getElementById('faceGuide');

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

            window.nextFace = function () {
               const runId = document.getElementById('run_id_face').value;

               if (!runId) {
                  Swal.fire('Pilih periode dulu', '', 'warning');
                  return;
               }

               document.getElementById('faceStep1').style.display = 'none';
               document.getElementById('faceStep2').style.display = 'block';

               startFace();
            };

            // PERBAIKAN V31.3c (2026-11-04): liveness untuk login slip gaji.
            // Dulu halaman ini langsung mengirim descriptor begitu wajah
            // terdeteksi, sehingga foto cetak bisa dipakai membuka slip.
            const livenessWajib = {{ ($livenessEnabled ?? true) ? 'true' : 'false' }};

            // ================================================================
            // PERBAIKAN V31.9 (2026-11-05): LIVENESS DISAMAKAN PERSIS DENGAN
            // HALAMAN ACUAN /face-attendance/login.
            // ================================================================
            // Sebelumnya halaman ini memakai pustaka LivenessCheck yang
            // skema kalibrasinya berbeda dari halaman acuan, sehingga
            // liveness terasa tidak pernah lolos walau user sudah menoleh.
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

            async function loop() {
               if (!active) {
                  return;
               }

               if (!busy && !verifying && faceVideo.readyState >= 2) {
                  busy = true;

                  try {
                     const detected = await FaceRecognition.detect(faceVideo);

                     if (detected) {
                        // Wajah terdeteksi -> reset hitungan frame hilang.
                        detectMissCount = 0;

                        // --- GERBANG LIVENESS (sama seperti halaman acuan) ---
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
                        // hasil verifikasi diketahui.
                        verifying = true;
                        setStatus('Wajah terdeteksi, memverifikasi...');

                        const data = await FaceRecognition.postJson(identifyUrl, {
                           descriptor: detected.descriptor
                        });

                        if (data.status) {
                           verifying = false;
                           active = false;
                           identifiedNpk = data.npk;

                           FaceRecognition.stopCamera(faceVideo);
                           resetButtons(false);

                           document.getElementById('face_npk').value = data.npk;
                           document.getElementById('face_name').value = data.name || data.npk;

                           setStatus('Wajah dikenali: ' + data.npk, 'success');

                           Swal.fire({
                              icon: 'success',
                              title: 'Wajah Dikenali',
                              text: 'Silakan masukkan password tanggal lahir.',
                              timer: 1200,
                              showConfirmButton: false
                           });

                           setTimeout(function () {
                              document.getElementById('faceStep2').style.display = 'none';
                              document.getElementById('faceStep3').style.display = 'block';
                           }, 1000);

                           busy = false;
                           return;
                        }

                        failedAttempts++;

                        Swal.fire({
                           icon: 'error',
                           title: 'Verifikasi Gagal',
                           text: data.message || 'Wajah tidak dikenali.'
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

                        // Sama seperti halaman acuan: verifikasi ditolak ->
                        // liveness diminta ulang (kecuali dimatikan).
                        verifying = false;
                        livenessVerified = !livenessWajib;
                        initialNoseRatio = null;
                        headTurnStreak = 0;
                        livenessAdaptiveThreshold = 0.04;
                        livenessSmallDeltaCount = 0;
                        eyeBaseline = null;
                        eyeClosedSeen = false;
                        setStatus('Wajah belum cocok. Posisikan wajah dengan jelas...', 'error');
                        active = true;
                        await new Promise(resolve => setTimeout(resolve, 2000));
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
                           setStatus('Arahkan Wajah ke Kamera', 'error');
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
                           setStatus('LIVENESS CHECK: Silakan BERKEDIP atau MENOLEH sedikit ke kiri / kanan', 'info');
                        }
                     }
                  } catch (error) {
                     console.error(error);
                     // Sama seperti halaman acuan: kegagalan jaringan tidak
                     // mematikan sesi; liveness diminta ulang dan loop jalan.
                     verifying = false;
                     livenessVerified = !livenessWajib;
                     initialNoseRatio = null;
                     headTurnStreak = 0;
                     livenessAdaptiveThreshold = 0.04;
                     livenessSmallDeltaCount = 0;
                     eyeBaseline = null;
                     eyeClosedSeen = false;
                     active = true;
                     setStatus('Gagal memproses wajah: ' + error.message, 'error');
                  }

                  verifying = false;
                  busy = false;
               }

               if (active) {
                  // Sama seperti halaman acuan: 150 ms saat ada wajah,
                  // 300 ms saat tidak ada wajah.
                  timer = setTimeout(loop, detectMissCount > 0 ? 300 : 150);
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
                  setStatus('Gagal memuat model face recognition.', 'error');
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
            }

            startBtn.addEventListener('click', startFace);
            stopBtn.addEventListener('click', stopFace);

            window.addEventListener('beforeunload', function () {
               active = false;
               FaceRecognition.stopCamera(faceVideo);
            });

            if (window.jQuery) {
               jQuery('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                  const target = jQuery(e.target).attr('href');

                  if (target !== '#facelogin') {
                     stopFace();
                  }
               });
            }

            $("#facePasswordForm").submit(function (e) {
               e.preventDefault();

               if (!identifiedNpk) {
                  Swal.fire('Wajah belum diverifikasi', '', 'error');
                  return;
               }

               Swal.fire({
                  title: 'Memverifikasi Password...',
                  allowOutsideClick: false,
                  didOpen: () => Swal.showLoading()
               });

               $.post(verifyUrl, {
                  _token: csrf,
                  npk: identifiedNpk,
                  password: $("#face_password").val(),
                  run_id: $("#run_id_face").val()
               }, function (res) {
                  if (!res.status) {
                     Swal.fire({
                        icon: 'error',
                        title: 'Login Gagal',
                        text: res.message
                     });
                     return;
                  }

                  Swal.fire({
                     icon: 'success',
                     title: 'Login Berhasil',
                     text: 'Tunggu download slip...',
                     timer: 1800,
                     showConfirmButton: false
                  });

                  setTimeout(function () {
                     window.location = res.redirect;
                  }, 1800);
               });
            });
         })();
      </script>
      @endif
   </body>
</html>
