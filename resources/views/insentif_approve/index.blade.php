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
               <h1 class="h3 mb-0 text-gray-800">Insentif Approval</h1>
            </div>
            {{-- ===============================
            TABLE APPROVAL
            =============================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Data Approval Payroll
                  </h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>ID</th>
                              <th>Period</th>
                              <th>Payroll Component</th>
                              <th>Status</th>
                              <th>Progress</th>
                              <th>Details</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($data as $row)
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>{{ $row->period_name }}</td>
                              <td>{{ strtoupper($row->payroll_component) }}</td>
                              <td>
                                 @if($row->status=='finish')
                                 <span class="badge badge-success">Finish</span>
                                 @else
                                 <span class="badge badge-warning">Pending</span>
                                 @endif
                              </td>
                              {{-- ================= PROGRESS ================= --}}
                              <td>
                                 @foreach($row->progress as $p)
                                 @php
                                 $users=$p['users'];
                                 $statusList=
                                 $p['status']=='approve'
                                 ? array_fill(0,count($users),'approve')
                                 : (json_decode($p['status'],true)
                                 ?? array_fill(0,count($users),'waiting'));
                                 @endphp
                                 <div class="mb-2 p-2 border rounded bg-light">
                                    @foreach($users as $idx=>$user)
                                    @php
                                    $beforeApproved=true;
                                    for($i=0;$i<$idx;$i++){
                                    if($statusList[$i]!=='approve') $beforeApproved=false;
                                    }
                                    @endphp
                                    <div class="d-flex justify-content-between">
                                       <span>
                                       <b>{{ $user['npk'] }}</b> - {{ $user['name'] }}
                                       </span>
                                       @if($statusList[$idx]=='approve')
                                       <span class="badge badge-success">✔ Approved</span>
                                       @elseif(!$beforeApproved)
                                       <span class="badge badge-secondary">Waiting Previous</span>
                                       @else
                                       <span class="badge badge-warning">Waiting</span>
                                       @endif
                                    </div>
                                    @endforeach
                                 </div>
                                 @endforeach
                              </td>
                              <td>
                                 <button class="btn btn-info btn-sm btn-detail"
                                    data-id="{{ $row->id }}"
                                    data-period="{{ $row->period_name }}"
                                    data-component="{{ $row->payroll_component }}">
                                 <i class="fas fa-search"></i>
                                 </button>
                              </td>
                              <td class="text-center">
                                 @php
                                 $progress=collect($row->progress);
                                 $currentIndex=$progress->search(fn($i)=>$i['status']!=='approve');
                                 $canApprove=false;
                                 if($currentIndex!==false){
                                 $current=$progress[$currentIndex];
                                 $npkList=is_array($current['npk'])
                                 ? $current['npk']
                                 : json_decode($current['npk'],true);
                                 $statusList=$current['status']=='pending'
                                 ? array_fill(0,count($npkList),'waiting')
                                 : (json_decode($current['status'],true)
                                 ?? array_fill(0,count($npkList),'waiting'));
                                 foreach($npkList as $idx=>$npk){
                                 $beforeApproved=true;
                                 for($i=0;$i<$idx;$i++){
                                 if($statusList[$i]!=='approve')
                                 $beforeApproved=false;
                                 }
                                 if(
                                 $npk==auth()->user()->npk &&
                                 $statusList[$idx]!='approve' &&
                                 $beforeApproved
                                 ){
                                 $canApprove=true;
                                 }
                                 }
                                 }
                                 @endphp
                                 @if($canApprove)
                                 <button class="btn btn-success btn-sm btn-approve"
                                    data-id="{{ $row->id }}">
                                 <i class="fas fa-check"></i>
                                 </button>
                                 @elseif($row->status=='finish')
                                 <span class="badge badge-success">Done</span>
                                 @else
                                 <span class="badge badge-secondary">Waiting</span>
                                 @endif
                              </td>
                           </tr>
                           @endforeach
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
            {{-- =====================================================
            DETAIL SECTION
            ===================================================== --}}
            <div id="insentif-detail-container" style="display:none" class="mt-4">
               <div class="card shadow">
                  <div class="card-header">
                     <h6 id="detail-title" class="m-0 font-weight-bold text-primary">
                        Insentif Detail
                     </h6>
                  </div>
                  <div class="card-body">
                     <div id="loadingDetail" class="text-center p-4" style="display:none;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                     </div>
                     <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="detailTable">
                           <thead>
                              <tr id="detailHead"></tr>
                           </thead>
                           <tbody></tbody>
                           <tfoot id="detailFooter" style="display:none;">
                              <tr>
                                 <th colspan="2" class="text-right font-weight-bold">
                                    TOTAL INSENTIF
                                 </th>
                                 <th id="totalInsentif" class="text-right font-weight-bold text-success"></th>
                              </tr>
                           </tfoot>
                        </table>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
      <script>
         /* ======================================
         SHOW DETAIL
         ====================================== */
         
         let detailTable=null;
         
         $('.btn-detail').on('click',function(){
         
         let id=$(this).data('id');
         let period=$(this).data('period');
         let component=$(this).data('component');
         
         $('#insentif-detail-container').show();
         $('#detail-title').text('Insentif Detail - '+period);
         
         if($.fn.DataTable.isDataTable('#detailTable')){
         $('#detailTable').DataTable().clear().destroy();
         }
         
         detailTable=null;
         
         $('#detailHead').html('');
         $('#detailTable tbody').html('');
         $('#detailFooter').hide();
         
         $('#loadingDetail').show();
         
         $('html,body').animate({
         scrollTop:$('#insentif-detail-container').offset().top-80
         },400);
         
         
         $.get('/insentif-approve/'+id+'/detail',function(res){
         
         let head='';
         let rows='';
         let total=0;
         
         /*
         ===============================
         DATA KOSONG
         ===============================
         */
         if(!res || res.length===0){
         
         head=`
         <th>NPK</th>
         <th>Name</th>
         <th>Insentif</th>
         `;
         
         rows=`
         <tr>
         <td colspan="3"
         class="text-center text-muted font-weight-bold">
         Tidak ada Insentif pada periode bulan ini
         </td>
         </tr>
         `;
         
         $('#detailHead').html(head);
         $('#detailTable tbody').html(rows);
         
         $('#loadingDetail').hide();
         return;
         }
         
         /*
         ===============================
         SEWING
         ===============================
         */
         if(component==='sewing_insentif'){
         
         head=`
         <th>NPK</th>
         <th>Name</th>
         <th class="text-right">Sewing Insentif</th>
         `;
         
         res.forEach(r=>{
         let val=parseFloat(r.sewing_insentif)||0;
         total+=val;
         
         rows+=`
         <tr>
         <td>${r.npk}</td>
         <td>${r.name}</td>
         <td class="text-right">${val.toLocaleString()}</td>
         </tr>`;
         });
         }
         
         /*
         ===============================
         PAD
         ===============================
         */
         else if(component==='pad_insentif'){
         
         head=`
         <th>NPK</th>
         <th>Name</th>
         <th class="text-right">Pad Insentif</th>
         `;
         
         res.forEach(r=>{
         let val=parseFloat(r.pad_insentif)||0;
         total+=val;
         
         rows+=`
         <tr>
         <td>${r.npk}</td>
         <td>${r.name}</td>
         <td class="text-right">${val.toLocaleString()}</td>
         </tr>`;
         });
         }
         
         /*
         ===============================
         CUTTING
         ===============================
         */
         else if(component==='cutting_insentif'){
         
         head=`
         <th>NPK</th>
         <th>Name</th>
         <th class="text-right">Cutting Insentif</th>
         `;
         
         res.forEach(r=>{
         let val=parseFloat(r.cutting_insentif)||0;
         total+=val;
         
         rows+=`
         <tr>
         <td>${r.npk}</td>
         <td>${r.name}</td>
         <td class="text-right">${val.toLocaleString()}</td>
         </tr>`;
         });
         }
         
         /*
         ===============================
         RENDER TABLE
         ===============================
         */
         
         $('#detailHead').html(head);
         $('#detailTable tbody').html(rows);
         
         /*
         ===============================
         SHOW TOTAL (ONLY IF DATA ADA)
         ===============================
         */
         
         if(total>0){
         $('#totalInsentif').text(total.toLocaleString());
         $('#detailFooter').show();
         }
         
         $('#loadingDetail').hide();
         
         /*
         ===============================
         INIT DATATABLE
         ===============================
         */
         
         detailTable=$('#detailTable').DataTable({
         pageLength:25,
         ordering:true,
         responsive:true,
         autoWidth:false,
         destroy:true
         });
         
         });
         
         });
         
         
         /* ======================================
         APPROVE
         ====================================== */
         
         $('.btn-approve').click(function(){
         
         let btn=$(this);
         btn.prop('disabled',true);
         
         let id=$(this).data('id');
         
         Swal.fire({
         title:'Approve Insentif?',
         icon:'question',
         showCancelButton:true
         }).then((result)=>{
         
         if(result.isConfirmed){
         
         $.ajax({
         url:'/insentif-approve/'+id+'/approve',
         type:'POST',
         data:{
         _token:'{{ csrf_token() }}',
         npk:'{{ auth()->user()->npk }}'
         },
         success:function(res){
         
         Swal.fire({
         icon:'success',
         title:res.message,
         timer:1200,
         showConfirmButton:false
         });
         
         setTimeout(()=>location.reload(),1200);
         },
         error:function(err){
         
         Swal.fire({
         icon:'error',
         title:err.responseJSON.message,
         timer:2000,
         showConfirmButton:false
         });
         }
         });
         }
         });
         });
         
      </script>
   </body>
</html>