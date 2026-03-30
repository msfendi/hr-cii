<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
   <body id="page-top">
      @include('sweetalert::alert')
      <div id="wrapper">
      @include('layout.sidebar')
      <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
         @include('layout.navbar')
         <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
               <h1 class="h3 mb-0 text-gray-800">Payroll Process</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Payroll Process
                  </h6>
               </div>
               <div class="card-body">
                  <form method="POST"
                     action="{{ route('payroll-process.process') }}"
                     id="payrollForm">
                     @csrf
                     <div class="form-group">
                        <label>Payroll Period :</label>
                        <select class="form-control"
                           id="period_id"
                           name="period_id"
                           required>
                           <option value="">Pilih Periode</option>
                           @foreach($periods as $period)
                           <option value="{{ $period->id }}">
                              {{ $period->name }}
                           </option>
                           @endforeach
                        </select>
                     </div>
                     {{-- ================= VALIDATION RESULT ================= --}}
                     <div id="approvalBox"
                        class="mt-4"
                        style="display:none">
                        <div class="card border-left-info shadow">
                           <div class="card-header">
                              <b>Approval Validation</b>
                           </div>
                           <div class="card-body">
                              <div id="approvalLoading"
                                 class="text-center p-3"
                                 style="display:none">
                                 <i class="fas fa-spinner fa-spin fa-2x"></i>
                              </div>
                              <table class="table table-sm table-bordered mb-0">
                                 <thead>
                                    <tr>
                                       <th>Payroll Component</th>
                                       <th>Status</th>
                                       <th>Approved At</th>
                                    </tr>
                                 </thead>
                                 <tbody id="approvalTable"></tbody>
                              </table>
                           </div>
                        </div>
                     </div>
                     <br>
                     <button id="btnProcess"
                        class="btn btn-primary"
                        disabled>
                     Process Payroll
                     </button>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="{{asset('vendor/jquery/select2.min.js')}}"></script>
      <script>
         $("#period_id").select2({
         allowClear:true,
         placeholder:'Pilih Periode Payroll'
         });
      </script>
      <script>
         /*
         ================================================
         LOAD APPROVAL WHEN PERIOD SELECTED
         ================================================
         */
         
         $('#period_id').on('change', function(){
         
         let periodId = $(this).val();
         
         $('#approvalBox').hide();
         $('#btnProcess').prop('disabled', true);
         
         if(!periodId) return;
         
         $('#approvalBox').show();
         $('#approvalLoading').show();
         $('#approvalTable').html('');
         
         $.get('/payroll-process/approval/'+periodId,function(res){
         
         let html='';
         let allFinish=true;
         
         if(res.length===0){
         html=`<tr>
         <td colspan="3" class="text-center text-danger">
         Approval belum tersedia
         </td>
         </tr>`;
         allFinish=false;
         }
         
         res.forEach(row=>{
         
         let badge='';
         let approved='-';
         
         if(row.status==='finish'){
         badge=`<span class="badge badge-success">Finish</span>`;
         approved=row.approved_at ?? '-';
         }else{
         badge=`<span class="badge badge-warning">Pending</span>`;
         allFinish=false;
         }
         
         html+=`
         <tr>
         <td>${row.payroll_component.toUpperCase()}</td>
         <td>${badge}</td>
         <td>${approved}</td>
         </tr>
         `;
         
         });
         
         $('#approvalTable').html(html);
         $('#approvalLoading').hide();
         
         /*
         ====================================
         ENABLE BUTTON ONLY IF ALL FINISH
         ====================================
         */
         
         if(allFinish){
         $('#btnProcess')
         .prop('disabled',false)
         .removeClass('btn-secondary')
         .addClass('btn-primary');
         }else{
         $('#btnProcess')
         .prop('disabled',true)
         .removeClass('btn-primary')
         .addClass('btn-secondary');
         }
         
         });
         
         });
         
         
         /*
         ================================================
         LOADING PROCESS PAYROLL
         ================================================
         */
         
         $('#payrollForm').on('submit', function(){
         
         Swal.fire({
         title:'Processing Payroll',
         text:'Mohon tunggu payroll sedang diproses...',
         allowOutsideClick:false,
         allowEscapeKey:false,
         didOpen:()=>{
         Swal.showLoading()
         }
         });
         
         });
         
      </script>
   </body>
</html>