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
               <h1 class="h3 mb-0 text-gray-800">Payroll Approval</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3 d-flex justify-content-between align-items-center">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Data Payroll Approval
                    </h6>

                    <form method="GET" id="filterForm">
                        <select name="status"
                                class="form-control form-control-sm"
                                onchange="document.getElementById('filterForm').submit()">

                            <option value="all" {{ $filter=='all' ? 'selected':'' }}>
                                All
                            </option>

                            <option value="open" {{ $filter=='open' ? 'selected':'' }}>
                                Open
                            </option>

                            <option value="closed" {{ $filter=='closed' ? 'selected':'' }}>
                                Closed
                            </option>

                        </select>
                    </form>

                    </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>ID</th>
                              <th>Payroll Run</th>
                              <th>Payroll Period</th>
                              <th>Export File</th>
                              <th>Export Status</th>
                              <th>Progress</th>
                              <th>Approval Status</th>
                              <th>Details</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($data as $row)
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>{{ $row->payroll_run_id }}</td>
                              <td>{{ $row->period_name }}</td>
                              <td class="text-center">
                                 @if($row->is_exported && $row->export_status != 'approved' && $row->export_status != 'finished')
                                 <span class="badge badge-warning">
                                 <i class="fas fa-spinner fa-spin"></i>
                                 Finalizing Document Approved
                                 </span>
                                 @else
                                 @if($row->is_exported && $row->file_excel)
                                 <a class="btn btn-success btn-sm"
                                    href="{{ asset('storage/'.$row->file_excel) }}" target="_blank">
                                 <i class="fas fa-file-excel mr-1"></i> Excel
                                 </a>
                                 @endif
                                 @if($row->is_exported && $row->file_pdf)
                                 <a class="btn btn-danger btn-sm"
                                    href="{{ asset('storage/'.$row->file_pdf) }}" target="_blank">
                                 <i class="fas fa-file-pdf mr-1"></i> PDF
                                 </a>
                                 @endif
                                 @if($row->is_exported && $row->file_peng)
                                 <a class="btn btn-secondary btn-sm"
                                    href="{{ asset('storage/'.$row->file_peng) }}" target="_blank">
                                 <i class="fas fa-file-pdf mr-1"></i> Pengeluaran
                                 </a>
                                 @endif
                                 @endif
                              </td>
                              <td>
                                 @if($row->is_exported)
                                 <span class="badge badge-success">Sudah Export</span>
                                 @else
                                 <span class="badge badge-secondary">Belum Export</span>
                                 @endif
                              </td>
                              {{-- ================= PROGRESS ================= --}}
                              <td>
                                 @foreach($row->progress as $levelIndex => $p)
                                 @php
                                 $users = $p['users'];
                                 if ($p['status'] === 'approve') {
                                 $statusList = array_fill(0, count($users), 'approve');
                                 } else {
                                 $decodedStatus = json_decode($p['status'], true);
                                 $statusList = is_array($decodedStatus)
                                 ? $decodedStatus
                                 : array_fill(0, count($users), 'waiting');
                                 }
                                 @endphp
                                 <div class="mb-2 p-2 border rounded bg-light">
                                    @foreach($users as $idx => $user)
                                    @php
                                    $beforeApproved = true;
                                    for ($i = 0; $i < $idx; $i++) {
                                    if ($statusList[$i] !== 'approve') {
                                    $beforeApproved = false;
                                    }
                                    }
                                    @endphp
                                    <div class="d-flex justify-content-between">
                                       <span>
                                       <b>{{ $user['npk'] }}</b> - {{ $user['name'] }}
                                       </span>
                                       @if($statusList[$idx]=='approve')
                                       <span class="badge badge-success">Approved</span>
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
                                 @if($row->status=='finish')
                                 <span class="badge badge-success">Finish</span>
                                 @else
                                 <span class="badge badge-warning">Pending</span>
                                 @endif
                              </td>
                              <td>
                                 {{-- DETAIL BUTTON (NEW) --}}
                                 <button
                                    class="btn btn-info btn-sm btn-detail"
                                    data-id="{{ $row->payroll_run_id }}"
                                    data-period="{{ $row->period_name }}"
                                    data-toggle="modal"
                                    data-target="#payrollDetailModal">
                                 <i class="fas fa-eye"></i>
                                 </button>
                              </td>
                              {{-- ================= ACTION ================= --}}
                              <td class="text-center">
                                 @php
                                 $progress = collect($row->progress);
                                 $currentIndex = $progress->search(function ($item) {
                                 return $item['status'] !== 'approve';
                                 });
                                 $canApprove=false;
                                 if($currentIndex!==false){
                                 $current=$progress[$currentIndex];
                                 $npkList=is_array($current['npk'])
                                 ? $current['npk']
                                 : json_decode($current['npk'],true);
                                 if(!is_array($npkList)) $npkList=[];
                                 if($current['status']==='pending'){
                                 $statusList=array_fill(0,count($npkList),'waiting');
                                 }else{
                                 $decodedStatus=json_decode($current['status'],true);
                                 $statusList=is_array($decodedStatus)
                                 ? $decodedStatus
                                 : array_fill(0,count($npkList),'waiting');
                                 }
                                 foreach($npkList as $idx=>$npk){
                                 $beforeApproved=true;
                                 for($i=0;$i<$idx;$i++){
                                 if($statusList[$i]!=='approve'){
                                 $beforeApproved=false;
                                 }
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
                                 @if(!$row->is_exported)
                                 <span class="badge badge-secondary">Waiting for Export</span>
                                 @else
                                 @if($canApprove)
                                 <button class="btn btn-success btn-sm btn-approve"
                                    data-id="{{ $row->id }}">
                                 <i class="fas fa-check"></i> Approve
                                 </button>
                                 @elseif($row->status=='finish')
                                 <span class="badge badge-success">Done</span>
                                 @else
                                 <span class="badge badge-secondary">Waiting</span>
                                 @endif
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
      {{-- ========================= MODAL DETAIL ========================= --}}
      <div class="modal fade" id="payrollDetailModal" tabindex="-1">
         <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 id="detail-title" class="modal-title">
                     Data Payroll Details
                  </h5>
                  <button type="button" class="close" data-dismiss="modal">
                  <span>&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="table-details">
                        <thead>
                           <tr>
                              <th>Run ID</th>
                              <th>NPK</th>
                              <th>Name</th>
                              <th>Dept</th>
                              <th>Basic Salary</th>
                              <th>Overtime</th>
                              <th>Special OT</th>
                              <th>Monthly Premi</th>
                              <th>Long Service</th>
                              <th>Allowance</th>
                              <th>Sewing</th>
                              <th>Pad</th>
                              <th>Cutting</th>
                              <th>Heat</th>
                              <th>Adjust</th>
                              <th>BPJS Kes</th>
                              <th>BPJS TK</th>
                              <th>PPh21</th>
                              <th>PPh21 Deduction</th>
                              <th>Absence</th>
                              <th>Late</th>
                              <th>Total Salary</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody></tbody>
                            <tfoot>
                                <tr style="font-weight:bold;background:#f8f9fc">
                                    <th colspan="3" class="text-right">TOTAL</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>
      {{-- ========================= SCRIPT ========================= --}}
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
         $('#dataTable').DataTable({
         order:[[0,'desc']],
         pageLength:10,
         responsive:true,
         autoWidth:false
         });
      </script>
      {{-- FORMAT RUPIAH --}}
      <script>
         function formatRupiah(number){
         if(number===null||number===undefined||number===''){number=0;}
         if(typeof number==='string'){
         number=number.replace(/[^0-9\-]/g,'');
         }
         number=Number(number);
         if(isNaN(number)){number=0;}
         return new Intl.NumberFormat('id-ID',{
         style:'currency',
         currency:'IDR',
         minimumFractionDigits:0
         }).format(number);
         }
      </script>
      {{-- DETAIL MODAL DATATABLE --}}
<script>

let tableDetails = null;

$('.btn-detail').click(function(){

    let id     = $(this).data('id');
    let period = $(this).data('period');

    $('#detail-title').text(
        'Data Payroll Details ('+period+')'
    );

    let url = '/payroll-process/details/' + id;

    if(tableDetails){
        tableDetails.destroy();
    }

    tableDetails = $('#table-details').DataTable({

        processing:true,
        responsive:true,
        ajax:url,

        createdRow:function(row,data){

            // highlight TKK
            if(data.tkk !== null && data.tkk !== '' && data.tkk !== 0){
                $(row).addClass('table-danger');
            }
        },

        columns:[
            {data:'run_id'},
            {data:'employee_npk'},
            {data:'employee_name'},
            {data:'dept'},
            { data:'basic_salary',defaultContent:0,render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'overtime_pay', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'special_overtime_pay', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'monthly_premi', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'long_service_allowance', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'allowance', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },

            { data:'sewing_insentif', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'pad_insentif', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'cutting_insentif', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'heat_insentif', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },

            { data:'adjusment', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },

            { data:'bpjs_kesehatan', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'bpjs_ketenagakerjaan', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },

            { data:'pph_21', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'pph_21_deduction', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'absence_deduction', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            { data:'late_deduction', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },

            { data:'total_salary', defaultContent:0, render:function(data,type){
                    return type === 'display'
                        ? formatRupiah(data ?? 0)
                        : data ?? 0;
                }
            },
            {
                data: 'employment_status',
                render: function(data, type){

                    if(type !== 'display') return data;

                    return `
                        <span class="badge ${
                            data === 'Resign'
                                ? 'bg-danger text-white'
                                : 'bg-success text-white'
                        }">
                            ${data}
                        </span>
                    `;
                }
            },
        ],

        /* ===============================
           ✅ AUTO TOTAL FOOTER
        =============================== */
        footerCallback:function(row,data,start,end,display){

            let api = this.api();

            function intVal(i){

                if(i === null || i === undefined || i === '') return 0;

                if(typeof i === 'number') return i;

                if(typeof i === 'string'){
                    i = i.replace(/[Rp\s]/g,'');
                    i = i.replace(/\./g,'').replace(',', '.');
                    let num = parseFloat(i);
                    return isNaN(num) ? 0 : num;
                }

                return 0;
            }

            // kolom numeric sesuai table kamu
            let cols = [
                4,5,6,7,8,
                9,10,11,12,13,
                14,15,16,17,18,
                19,20,21
            ];

            cols.forEach(function(colIndex){

                let total = api
                    .column(colIndex,{search:'applied'})
                    .data()
                    .reduce(function(a,b){
                        return intVal(a)+intVal(b);
                    },0);

                $(api.column(colIndex).footer())
                    .html(formatRupiah(total));

            });

        }

    });

});

</script>
      {{-- APPROVE SCRIPT (ORIGINAL) --}}
      <script>
         $('.btn-approve').click(function(){
         
         let id=$(this).data('id');
         
         Swal.fire({
         title:'Approve?',
         text:"Anda yakin ingin approve?",
         icon:'question',
         showCancelButton:true,
         confirmButtonText:'Yes'
         }).then((result)=>{
         
         if(result.isConfirmed){
         
         Swal.fire({
         title:"Finalizing Payroll Approval...",
         text:"Mohon tunggu...",
         allowOutsideClick:false,
         showConfirmButton:false,
         timer:3000,
         didOpen:()=>{
         
         $.ajax({
         url:'/payroll-approve/'+id+'/approve',
         type:'POST',
         data:{
         _token:'{{ csrf_token() }}',
         npk:'{{ auth()->user()->npk }}'
         },
         success:function(res){
         Swal.fire('Success',res.message,'success');
         setTimeout(()=>location.reload(),1000);
         },
         error:function(err){
         Swal.fire('Error',err.responseJSON.message,'error');
         }
         });
         
         }
         });
         
         }
         });
         });
      </script>
   </body>
</html>