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
               <h1 class="h3 mb-0 text-gray-800">Compensation Approval</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3 d-flex justify-content-between align-items-center">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Data Compensations Approval
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
                              <th>Compensation Period</th>
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
                           @php
                           $folder = \Carbon\Carbon::parse($row->cutoff_date)->translatedFormat('F_Y');
                           @endphp
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>{{ \Carbon\Carbon::parse($row->cutoff_date)->translatedFormat('F Y') }}</td>
                              <td class="text-center">
                                 @if($row->status != 'finished')
                                 <span class="badge badge-warning">
                                 <i class="fas fa-spinner fa-spin"></i>
                                 Finalizing Document Approved
                                 </span>
                                 @else
                                    @if($row->status == 'finished' && $row->file_pdf)
                                    <a class="btn btn-danger btn-sm"
                                       href="{{ Storage::url('compensations/' . $folder . '/' .$row->file_pdf) }}" target="_blank">
                                    <i class="fas fa-file-pdf mr-1"></i> PDF
                                    </a>
                                    @endif
                                 @endif
                              </td>
                              <td>
                                 @if($row->status == 'finished')
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
                                 @if($row->approval_status=='finish')
                                 <span class="badge badge-success">Finish</span>
                                 @else
                                 <span class="badge badge-warning">Pending</span>
                                 @endif
                              </td>
                              <td>
                                 {{-- DETAIL BUTTON (NEW) --}}
                                 <button
                                    class="btn btn-info btn-sm btn-detail"
                                    data-id="{{ $row->cutoff_date }}"
                                    data-period="{{ \Carbon\Carbon::parse($row->cutoff_date)->translatedFormat('F_Y') }}"
                                    data-toggle="modal"
                                    data-target="#compensationDetailModal">
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
                                 @if(!$row->status == 'finished')
                                 <span class="badge badge-secondary">Waiting for Export</span>
                                 @else
                                 @if($canApprove)
                                 <button class="btn btn-success btn-sm btn-approve"
                                    data-id="{{ $row->id }}">
                                 <i class="fas fa-check"></i> Approve
                                 </button>
                                 @elseif($row->approval_status=='finish')
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
      <div class="modal fade" id="compensationDetailModal" tabindex="-1">
         <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                  <div class="modal-header">
                     <h5 id="detail-title" class="modal-title">
                        Data Compensation Details
                     </h5>

                     <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                     </button>
                  </div>

                  <div class="modal-body">

                     <div class="table-responsive">

                        <table class="table table-bordered table-sm" id="table-details">

                              <thead class="thead-dark">
                                 <tr>
                                    <th>ID</th>
                                    <th>NPK</th>
                                    <th>Department</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Active</th>
                                 </tr>
                              </thead>

                              <tbody></tbody>

                              <tfoot>
                                 <tr style="font-weight:bold;background:#f8f9fc">
                                    <th colspan="3" class="text-right">
                                          TOTAL
                                    </th>

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

function formatRupiah(number){

    if(number === null || number === undefined || number === ''){
        number = 0;
    }

    if(typeof number === 'string'){
        number = number.replace(/[^0-9\-]/g,'');
    }

    number = Number(number);

    if(isNaN(number)){
        number = 0;
    }

    return new Intl.NumberFormat('id-ID',{
        style:'currency',
        currency:'IDR',
        minimumFractionDigits:0
    }).format(number);
}

$('.btn-detail').click(function(){

    let id     = $(this).data('id');
    let period = $(this).data('period');

    $('#detail-title').text(
        'Data Compensation Details ('+period+')'
    );

    /*
    |--------------------------------------------------------------------------
    | ROUTE SESUAI CONTROLLER
    |--------------------------------------------------------------------------
    | public function details($date)
    |--------------------------------------------------------------------------
    */

    let url = '/compensation/details/' + id;

    /*
    |--------------------------------------------------------------------------
    | DESTROY OLD DATATABLE
    |--------------------------------------------------------------------------
    */

    if(tableDetails){
        tableDetails.destroy();
        $('#table-details tbody').empty();
    }

    /*
    |--------------------------------------------------------------------------
    | INIT DATATABLE
    |--------------------------------------------------------------------------
    */

    tableDetails = $('#table-details').DataTable({

        processing:true,
        responsive:true,
        autoWidth:false,
        destroy:true,

        ajax:{
    url:url,
    type:'GET',
    dataSrc:function(json){

        console.log(json);

        return json.data;
    },
    error:function(xhr){

        console.log(xhr.responseText);

        Swal.fire(
            'Error',
            'Failed load detail data',
            'error'
        );
    }
},

        columns:[

            {
                data:'id',
                defaultContent:'-'
            },

            {
                data:'npk',
                defaultContent:'-'
            },

            {
                data:'dept',
                defaultContent:'-'
            },

            {
                data:'amount',
                defaultContent:0,
                render:function(data){
                    return formatRupiah(data ?? 0);
                }
            },

            {
                data:'status',
                defaultContent:'-',
                render:function(data){

                    if(data == 'approved'){
                        return `<span class="badge badge-success">
                                    Approved
                                </span>`;
                    }

                    if(data == 'pending'){
                        return `<span class="badge badge-warning">
                                    Pending
                                </span>`;
                    }

                    if(data == 'reject'){
                        return `<span class="badge badge-danger">
                                    Reject
                                </span>`;
                    }

                    return `<span class="badge badge-secondary">
                                ${data ?? '-'}
                            </span>`;
                }
            },

            {
                data:'is_active',
                render:function(data){

                    if(data == 1){
                        return `<span class="badge badge-success">
                                    Active
                                </span>`;
                    }

                    return `<span class="badge badge-secondary">
                                Non Active
                            </span>`;
                }
            }

        ],

        /*
        |--------------------------------------------------------------------------
        | FOOTER TOTAL
        |--------------------------------------------------------------------------
        */

        footerCallback:function(row,data,start,end,display){

            let api = this.api();

            function intVal(i){

                if(i === null || i === undefined || i === ''){
                    return 0;
                }

                if(typeof i === 'number'){
                    return i;
                }

                if(typeof i === 'string'){

                    i = i.replace(/[Rp\s]/g,'');
                    i = i.replace(/\./g,'');
                    i = i.replace(',', '.');

                    let num = parseFloat(i);

                    return isNaN(num) ? 0 : num;
                }

                return 0;
            }

            /*
            |--------------------------------------------------------------------------
            | TOTAL AMOUNT COLUMN
            |--------------------------------------------------------------------------
            | amount = column index 3
            |--------------------------------------------------------------------------
            */

            let total = api
                .column(3,{search:'applied'})
                .data()
                .reduce(function(a,b){

                    return intVal(a) + intVal(b);

                },0);

            $(api.column(3).footer())
                .html(formatRupiah(total));

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
         title:"Finalizing Compensation Approval...",
         text:"Mohon tunggu...",
         allowOutsideClick:false,
         showConfirmButton:false,
         timer:3000,
         didOpen:()=>{
         
         $.ajax({
         url:'/compensation-approve/'+id+'/approve',
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