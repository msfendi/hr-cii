<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
   <style>
      #faceVideo {
         width: 100%;
         height: 100%;
         object-fit: cover;
      }

      /* Kamera pada halaman slip dibuat persegi (1:1), bukan persegi panjang */
      .camera-box {
         width: 100%;
         max-width: 360px;
         height: auto;
         aspect-ratio: 1 / 1;
         margin: 0 auto;
         background: #fff;
         border-radius: 20px;
         overflow: hidden;
         display: flex;
         align-items: center;
         justify-content: center;
      }
   </style>
   <body class="bg-gradient-primary">
      @include('sweetalert::alert')
      <div class="container container-center">
         <div class="text-center">
            <img src="{{ asset('img/chutex.svg') }}" style="width:150px;">
            <h1 class="h4 text-white"><b>PT. Chutex International Indonesia</b></h1>
            <h1 class="h1 text-white mb-4"><b>HRIS</b></h1>
         </div>
         <div class="card shadow-lg my-5">
            <div class="card-body">
               <ul class="nav nav-tabs">
                  <li class="nav-item">
                     <a class="nav-link active {{ !($qrEnabled ?? true) ? 'disabled text-muted' : '' }}" data-toggle="tab" href="#qrlogin">
                     <i class="fas fa-qrcode mr-1"></i> QR Code Login {{ !($qrEnabled ?? true) ? '(Nonaktif)' : '' }}
                     </a>
                  </li>
                  @if($faceEnabled ?? false)
                  <li class="nav-item">
                     <a class="nav-link" data-toggle="tab" href="#facelogin">
                     Face Recognition Login
                     </a>
                  </li>
                  @endif
                  <!-- <li class="nav-item">
                     <a class="nav-link" data-toggle="tab" href="#manual">
                     Manual Login
                     </a>
                  </li> -->
               </ul>
               <div class="tab-content mt-4">
                  {{-- ================= MANUAL LOGIN ================= --}}
                  <div class="tab-pane fade" id="manual">
                     <form id="manualLoginForm">
                        @csrf
                        <div class="form-group">
                           <label>NPK</label>
                           <input type="text" class="form-control" name="npk" required>
                        </div>
                        <div class="form-group">
                           <label>Password (Tanggal Lahir)</label>
                           <input type="password" class="form-control" name="password" placeholder="Contoh : 250513 (YYMMDD)" required>
                        </div>
                        <div class="form-group">
                           <label>Payroll Period</label>
                           <select class="form-control" name="run_id" id="run_id_manual">
                              <option disabled selected>-- Pilih Periode --</option>
                              @foreach($periods as $period)
                              <option value="{{$period->id}}">
                                 {{ date('F Y',strtotime($period->start_date)) }}
                              </option>
                              @endforeach
                           </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                        Show Payroll Slip
                        </button>
                     </form>
                  </div>
                  {{-- ================= QR LOGIN ================= --}}
                  <div class="tab-pane fade show active" id="qrlogin">
                     @if(!($qrEnabled ?? true))
                     <div class="alert alert-warning text-center m-3">
                        <i class="fas fa-ban mr-1"></i><strong>QR Login dinonaktifkan.</strong><br>
                        <small>Fitur QR Login sedang dinonaktifkan oleh admin.</small>
                     </div>
                     @endif
                     <div id="step1">
                        <div class="form-group">
                           <label>Pilih Periode</label>
                           <select class="form-control" id="run_id_qr">
                              <option disabled selected>-- Pilih Periode --</option>
                              @foreach($periods as $period)
                              <option value="{{$period->id}}">
                                 {{ date('F Y',strtotime($period->start_date)) }}
                              </option>
                              @endforeach
                           </select>
                        </div>
                        <button class="btn btn-success btn-block"
                           onclick="nextQR()">Next</button>
                     </div>
                     <div id="step2" style="display:none">
                        <div class="camera-box text-center">
                           <video id="video" style="width:100%"></video>
                        </div>
                     </div>
                     <div id="step3" style="display:none">
                        <form id="qrPasswordForm">
                           <div class="form-group">
                              <label>Password (Tanggal Lahir)</label>
                              <input type="password" id="qr_password"
                                 class="form-control" placeholder="Contoh : 250513 (YYMMDD)" required>
                           </div>
                           <button class="btn btn-primary btn-block">
                           Login Payroll
                           </button>
                        </form>
                     </div>
                  </div>

                  {{-- ================= FACE RECOGNITION LOGIN ================= --}}
                  @if($faceEnabled ?? false)
                  <div class="tab-pane fade" id="facelogin">
                     <div id="faceStep1">
                        <div class="form-group">
                           <label>Pilih Periode</label>
                           <select class="form-control" id="run_id_face">
                              <option disabled selected>-- Pilih Periode --</option>
                              @foreach($periods as $period)
                              <option value="{{$period->id}}">
                                 {{ date('F Y',strtotime($period->start_date)) }}
                              </option>
                              @endforeach
                           </select>
                        </div>
                        <button class="btn btn-success btn-block"
                           onclick="nextFace()">Next</button>
                     </div>
                     <div id="faceStep2" style="display:none">
                        <div class="camera-box text-center">
                           <video id="faceVideo" autoplay muted playsinline></video>
                        </div>
                        <div id="facePayrollStatus" class="text-center small text-muted mt-2">
                           Kamera akan aktif otomatis. Posisikan wajah di dalam frame.
                        </div>
                        <button type="button" id="facePayrollStart" class="btn btn-success btn-block mt-3 d-none">
                           <i class="fas fa-camera mr-1"></i> Coba Scan Lagi
                        </button>
                        <button type="button" id="facePayrollStop" class="btn btn-outline-secondary btn-block mt-2 d-none">
                           Berhenti
                        </button>
                     </div>
                     <div id="faceStep3" style="display:none">
                        <form id="facePasswordForm">
                           <div class="form-group">
                              <label>NPK</label>
                              <input type="text" id="face_npk" class="form-control" readonly>
                           </div>
                           <div class="form-group">
                              <label>Nama</label>
                              <input type="text" id="face_name" class="form-control" readonly>
                           </div>
                           <div class="form-group">
                              <label>Password (Tanggal Lahir)</label>
                              <input type="password" id="face_password"
                                 class="form-control" placeholder="Contoh : 250513 (YYMMDD)" required>
                           </div>
                           <button class="btn btn-primary btn-block">
                           Login Payroll
                           </button>
                        </form>
                     </div>
                  </div>
                  @endif
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

            function setStatus(message, type) {
               statusEl.textContent = message;
               statusEl.className = 'text-center small mt-2 ' + (type === 'error'
                  ? 'text-danger'
                  : (type === 'success' ? 'text-success' : 'text-muted'));
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

                  if (target === '#facelogin') {
                     // Kamera TIDAK dijalankan otomatis saat tab dibuka.
                     // Sesuai flow QR: user wajib memilih periode lebih dulu
                     // lalu menekan Next; nextFace() yang akan start kamera.
                     setStatus('Pilih periode terlebih dahulu, lalu klik Next.');
                  } else {
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
