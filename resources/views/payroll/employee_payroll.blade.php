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
                        <div class="tab-pane fade" id="facelogin">
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
      <script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
      <script src="{{ asset('js/face-recognition.js') }}"></script>
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

                        const data = await FaceRecognition.postJson(identifyUrl, {
                           descriptor: detected.descriptor
                        });

                        if (data.status) {
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
