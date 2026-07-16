<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
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
                     <a class="nav-link active" data-toggle="tab" href="#qrlogin">
                     QR Code Login
                     </a>
                  </li>
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
               </div>
            </div>
         </div>
      </div>
      @include('layout.footerscript')
      <script src="https://unpkg.com/@zxing/library@latest"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
   </body>
</html>