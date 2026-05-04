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
               <h1 class="h3 mb-0 text-gray-800">
                  Insentif Approval
               </h1>
            </div>
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
                                    if($statusList[$i]!=='approve')
                                    $beforeApproved=false;
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
                                 <button
                                    class="btn btn-info btn-sm btn-detail"
                                    data-period="{{ $row->period_id }}"
                                    data-period-name="{{ $row->period_name }}"
                                    data-component="{{ $row->payroll_component }}">
                                 <i class="fas fa-search"></i>
                                 </button>
                              </td>
                              <td class="text-center">
                                 @if($row->status=='finish')
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
         </div>
      </div>
      @include('layout.footer')
      <!-- ================= MODAL DETAIL ================= -->
      <div class="modal fade" id="detailModal" tabindex="-1">
         <div class="modal-dialog modal-xl">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="detail-title">
                     Insentif Detail
                  </h5>
                  <button type="button" class="close" data-dismiss="modal">
                  <span>&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm"
                        id="insentifTable"
                        width="100%">
                        <thead>
                           <tr>
                              <th>NPK</th>
                              <th>Name</th>
                              <th>
                                 Department
                                 <br>
                                 <input type="text"
                                    id="searchDept"
                                    class="form-control form-control-sm mt-1"
                                    placeholder="Search Dept">
                              </th>
                              <th>Insentif</th>
                           </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                           <tr style="background:#f8f9fc;font-weight:bold">
                              <th colspan="3" class="text-right">TOTAL</th>
                              <th></th>
                           </tr>
                        </tfoot>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script>
         let insentifTable;
         
         function formatRupiah(number){
         
         number = Number(number) || 0;
         
         return new Intl.NumberFormat('id-ID',{
         style:'currency',
         currency:'IDR',
         minimumFractionDigits:0
         }).format(number);
         
         }
         
         $(document).ready(function(){
         
         $('#dataTable').DataTable({
         order:[[0,'desc']],
         pageLength:10,
         responsive:true,
         autoWidth:false
         });
         
         
         insentifTable = $('#insentifTable').DataTable({
         
         processing:true,
         searching:true,
         paging:true,
         info:false,
         autoWidth:true,
         data:[],
         
         language:{
         processing:
         '<div class="text-center p-3">'+
         '<i class="fas fa-spinner fa-spin fa-2x"></i>'+
         '<div>Loading data...</div>'+
         '</div>'
         },
         
         columns:[
         {data:'npk'},
         {data:'name'},
         {data:'dept'},
         {
         data:null,
         render:function(row){
         
         let value =
         row.sewing_insentif ??
         row.pad_insentif ??
         row.cutting_insentif ??
         row.heat_insentif ??
         0;
         
         return formatRupiah(value);
         }
         }
         ],
         
         footerCallback:function(){
         
         let api=this.api();
         
         let total = api
         .rows({search:'applied'})
         .data()
         .reduce(function(sum,row){
         
         let val =
         row.sewing_insentif ??
         row.pad_insentif ??
         row.cutting_insentif ??
         row.heat_insentif ??
         0;
         
         return sum + Number(val);
         
         },0);
         
         $(api.column(3).footer())
         .html(formatRupiah(total));
         
         }
         
         });
         
         
         /* ✅ SEARCH DEPT */
         
         $('#searchDept').on('keyup change', function () {

            insentifTable
                  .column(2) // kolom Department
                  .search(this.value)
                  .draw();

         });
         
         });
         
         
         $(document).on('click','.btn-detail',function(){
         
         let period=$(this).data('period');
         let periodName=$(this).data('period-name');
         let component=$(this).data('component');
         
         $('#detailModal').modal('show');
         
         $('#detail-title')
         .text('Insentif Detail - '+periodName);
         
         let url='';
         
         if(component==='sewing_insentif'){
         url='/line-insentif-master/'+period+'/check';
         }
         else if(component==='pad_insentif'){
         url='/pad-insentif-master/'+period+'/check';
         }
         else if(component==='cutting_insentif'){
         url='/cutting-insentif-master/'+period+'/check';
         }
         else if(component==='heat_insentif'){
         url='/heat-insentif-master/'+period+'/check';
         }
         
         $('#insentifTable_processing').show();
         
         $.ajax({
         
         url:url,
         type:'GET',
         dataType:'json',
         
         success:function(res){
         
         insentifTable.clear();
         insentifTable.rows.add(res.data);
         insentifTable.draw();
         
         $('#insentifTable_processing').hide();
         
         },
         
         error:function(xhr){
         
         console.log(xhr.responseText);
         $('#insentifTable_processing').hide();
         
         }
         
         });
         
         });
         
      </script>
   </body>
</html>