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
                                    data-period="{{ $row->period_name }}">Details</button>
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
      <div class="card-body">
      <div class="card shadow mb-4" id="detail-card" style="display:none;">

    <div class="card-header py-3 d-flex justify-content-between align-items-center">

    <h6 class="m-0 font-weight-bold text-primary" id="detail-title">
        Data Payroll Details
    </h6>

    <div id="export-button-container"></div>

</div>

    <div class="card-body">

        <div class="table-responsive">

            <table class="table table-bordered table-sm" id="table-details">

                <thead>
                    <tr>
                        <th>Run ID</th>
                                    <th>NPK</th>
                                    <th>Name</th>
                                    <th>Dept</th>
                                    <th>TMK</th>
                                    <th>TKK</th>
                                    <th>Basic Salary</th>
                                    <th>Overtime</th>
                                    <th>Special OT</th>
                                    <th>Monthly Premi</th>
                                    <th>Long Service</th>
                                    <th>Allowance</th>
                                    <th>Sewing Insentif</th>
                                    <th>Pad Print Insentif</th>
                                    <th>Cutting Insentif</th>
                                    <th>Heat Insentif</th>
                                    <th>6S Insentif</th>
                                    <th>Adjusments</th>
                                    <th>BPJS Kes</th>
                                    <th>BPJS TK</th>
                                    <th>PPh21</th>
                                    <th>PPh21 Deduction</th>
                                    <th>Absence</th>
                                    <th>Late Deduction</th>
                                    <th>Work Leave Deduction</th>
                                    <th>Total Salary</th>
                                    <th>% Difference</th>
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

</div></div>
      @include('layout.footer')
      {{-- ========================= SCRIPT ========================= --}}
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
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

    if(
        number === null ||
        number === undefined ||
        number === '' ||
        number === false
    ){
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
        minimumFractionDigits:2
    }).format(number);
}
</script>
      {{-- DETAIL MODAL DATATABLE --}}
      <script>

const userRole = @json(optional(Auth::user()->roles->first())->name);

// console.log('USER ROLE =', userRole);

const canSeeSalary =
    userRole === 'Admin' ||
    userRole === 'Management' ||
    userRole === 'Audit' ||
    userRole === 'Payroll_STAFF' ||
    userRole === 'Payroll_NONSTAFF' ||
    userRole === 'Payroll_SEWING' ||
    userRole === 'Payroll_NONSEWING';

function salaryMask(value){

    if(canSeeSalary){
        return formatRupiah(value ?? 0);
    }

    return '****';
}

function componentColor(componentType){
    return componentType === 'deduction' ? '#dc3545' : '#212529';
}

function salaryMaskColored(amount, componentType){
    let masked = salaryMask(amount ?? 0);
    if(!canSeeSalary){
        return masked; // tetap '****' tanpa styling
    }
    return `<span style="color:${componentColor(componentType)}">${masked}</span>`;
}

</script>
<script>

let tableDetails = null;

$('.btn-detail').click(function(){

    let id     = $(this).data('id');
    let period = $(this).data('period');

    $('#detail-title').text(
        'Data Payroll Details ('+period+')'
    );

    $('#detail-card').show();

$('html, body').animate({

    scrollTop: $('#detail-card').offset().top - 20

}, 500);

    let url = '/payroll-process/details/' + id;

    if(tableDetails){

    tableDetails.destroy();

    $('#table-details tbody').empty();

}

    tableDetails = $('#table-details').DataTable({

        processing:true,
        responsive:true,
        dom: 'Bfrtip',

buttons: [
    {
        extend: 'excelHtml5',

        text: '<i class="fas fa-file-excel"></i> Export Excel',

        className: 'btn btn-success btn-sm',

        title: function(){

            return $('#detail-title').text();
        },

        exportOptions: {
            orthogonal: 'export',

            columns: [
                1,2,3,4,5,6,7,8,9,10,
                11,12,13,14,15,16,17,
                18,19,20,21,22,23,24,25,26
            ],

            format: {

                body: function(data,row,column){

                    if(typeof data === 'string'){

                        return data
                            .replace(/<[^>]*>/g,'')
                            .replace(/Rp/g,'')
                            .replace(/\./g,'')
                            .trim();
                    }

                    return data;
                }
            }
        }
    }
],

        ajax:{
        url:url,
        dataSrc:function(json){

            // console.log('RAW RESPONSE:', json);

            let rows = Array.isArray(json)
                ? json
                : (json.data ?? []);

            // console.log('ROWS FINAL:', rows.length);

            return rows;
        }
    },

        columns:[

            {
                data:null,
                render:function(data,type,row,meta){
                    return meta.row + 1;
                }
            },

            {
                data:'employee_npk'
            },

            {
                data:'employee_name'
            },

            {
                data:'dept',
                defaultContent:'-'
            },
            
            {
                data:'tmk'
            },
            
            {
                data:'tkk'
            },

            {
                data:'components.basic_salary.amount',
                defaultContent:0,
                render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return salaryMaskColored(data ?? 0, row.components?.basic_salary?.type);
                }
            },

            {
                data:'components.overtime_pay.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){ return data ?? 0; }

                    let overtimeData =
                        encodeURIComponent(
                            JSON.stringify(
                                row.overtime_details || []
                            )
                        );

                    return `
                        <a href="javascript:void(0)"
                        class="btn-overtime-detail"
                        data-overtime="${overtimeData}">
                            ${salaryMaskColored(data ?? 0, row.components?.overtime_pay?.type)}
                        </a>
                    `;
                }
            },

            {
                data:'components.special_overtime_pay.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    let specialOvertimeData =
                        encodeURIComponent(
                            JSON.stringify(
                                row.overtime_details || []
                            )
                        );

                    return `
                        <a href="javascript:void(0)"
                        class="btn-special-overtime-detail"
                        data-special-overtime="${specialOvertimeData}">
                            ${salaryMaskColored(data ?? 0, row.components?.special_overtime_pay?.type)}
                        </a>
                    `;
                }
            },

            {
                data:'components.monthly_premi.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.monthly_premi?.type);
                }
            },

            {
                data:'components.long_service_allowance.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.long_service_allowance?.type);
                }
            },

            {
                data:'components.allowance.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.allowance?.type);
                }
            },

            {
                data:'components.sewing_insentif.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.sewing_insentif?.type);
                }
            },

            {
                data:'components.pad_insentif.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.pad_insentif?.type);
                }
            },

            {
                data:'components.cutting_insentif.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.cutting_insentif?.type);
                }
            },

            {
                data:'components.heat_insentif.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.heat_insentif?.type);
                }
            },

            {
                data:'components.sixs_insentif.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.sixs_insentif?.type);
                }
            },

            {
                data:'components.adjusment.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    let adjusmentData =
                        encodeURIComponent(
                            JSON.stringify(
                                row.payroll_adjustment_details || []
                            )
                        );

                    return `
                        <a href="javascript:void(0)"
                        class="btn-adjusment-detail"
                        data-adjusment="${adjusmentData}">
                            ${salaryMaskColored(data ?? 0, row.components?.adjusment?.type)}
                        </a>
                    `;
                }
            },

            {
                data:'components.bpjs_kesehatan.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.bpjs_kesehatan?.type);
                }
            },

            {
                data:'components.bpjs_ketenagakerjaan.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.bpjs_ketenagakerjaan?.type);
                }
            },

            {
                data:'components.pph_21.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.pph_21?.type);
                }
            },

            {
                data:'components.pph_21_deduction.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMaskColored(data ?? 0, row.components?.pph_21_deduction?.type);
                }
            },

            {
                data:'components.absence_deduction.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    let absenceData =
                        encodeURIComponent(
                            JSON.stringify(
                                row.overtime_details || []
                            )
                        );

                    return `
                        <a href="javascript:void(0)"
                        class="btn-absence-detail"
                        data-absence="${absenceData}">
                            ${salaryMaskColored(data ?? 0, row.components?.absence_deduction?.type)}
                        </a>
                    `;
                }
            },

            {
                data:'components.late_deduction.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    let lateData =
                        encodeURIComponent(
                            JSON.stringify(
                                row.late_details || []
                            )
                        );

                    return `
                        <a href="javascript:void(0)"
                        class="btn-late-detail"
                        data-late="${lateData}">
                            ${salaryMaskColored(data ?? 0, row.components?.late_deduction?.type)}
                        </a>
                    `;
                }
            },
            {
                data:'components.work_leave_deduction.amount',
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    let ijinData =
                        encodeURIComponent(
                            JSON.stringify(
                                row.ijin_details || []
                            )
                        );

                    return `
                        <a href="javascript:void(0)"
                        class="btn-ijin-detail"
                        data-ijin="${ijinData}">
                            ${salaryMaskColored(data ?? 0, row.components?.work_leave_deduction?.type)}
                        </a>
                    `;
                }
            },

            {
                data:'total_salary',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data: null,
                orderable: true,
                searchable: false,
                render: function(data, type, row){

                    const basic = Number(row.components?.basic_salary || 0);
                    const allowance = Number(row.components?.allowance || 0);
                    const totalSalary = Number(row.total_salary || 0);

                    const baseSalary = basic + allowance;

                    let percentage = 0;

                    if(baseSalary > 0){
                        percentage = ((totalSalary - baseSalary) / baseSalary) * 100;
                    }

                    if(type === 'export'){
                        // Excel membutuhkan 0.185 agar tampil 18.5%
                        return percentage / 100;
                    }

                    if(type !== 'display'){
                        return percentage;
                    }

                    let badge = 'success';

                    if(percentage > 30){
                        badge = 'danger';
                    }else if(percentage > 20){
                        badge = 'warning';
                    }

                    return `
                        <span class="badge badge-${badge}">
                            ${percentage.toFixed(2)}%
                        </span>
                    `;
                }
            },

            {
                data: 'tkk',
                defaultContent: null,
                render: function (data, type, row) {

                    const tmk = row.tmk ? new Date(row.tmk) : null;
                    const periodStart = row.period_start ? new Date(row.period_start) : null;

                    const isTMKInPeriod =
                        tmk &&
                        periodStart &&
                        tmk.getFullYear() === periodStart.getFullYear() &&
                        tmk.getMonth() === periodStart.getMonth();

                    // 1. BARU (TMK di periode berjalan)
                    if (isTMKInPeriod) {
                        return `
                            <span class="badge badge-primary">
                                Baru
                            </span>
                        `;
                    }

                    // 2. MANGKIR
                    if ((row.keterangan || '').toLowerCase() === 'ma') {
                        return `
                            <span class="badge badge-danger">
                                Mangkir
                            </span>
                        `;
                    }

                    // 3. RESIGN
                    if (data !== null && data !== '') {
                        return `
                            <span class="badge badge-warning">
                                Resign
                            </span>
                        `;
                    }

                    // 4. ACTIVE
                    return `
                        <span class="badge badge-success">
                            Active
                        </span>
                    `;
                }
            }
        ],

        createdRow: function (row, data) {

            const ket = (data.keterangan || '').toString().trim().toLowerCase();

            $(row).removeClass('table-warning table-danger');

            if (data.tkk !== null && data.tkk !== '') {

                if (ket === 'ma') {
                    $(row).addClass('table-danger');
                } else {
                    $(row).addClass('table-warning');
                }
            }
        },
        initComplete:function(){

            let api = this.api();

            let deptColumn = api.column(3);

            if($('#filterDept').hasClass('select2-hidden-accessible')){
                $('#filterDept').select2('destroy');
            }

            $('#filterDept').empty().append(
                '<option value="">All Department</option>'
            );

            let depts = [];

            deptColumn.data().each(function(value){

                if(value){
                    depts.push(value);
                }

            });

            depts = [...new Set(depts)].sort();

            depts.forEach(function(dept){

                $('#filterDept').append(
                    `<option value="${dept}">${dept}</option>`
                );

            });

            $('#filterDept').select2({
                placeholder:'Department',
                allowClear:true,
                width:'220px'
            });

            /*
            ============================
            PINDAH KE KANAN SEARCH
            ============================
            */

            $('#table-details_filter').addClass(
            'd-flex align-items-center justify-content-end'
        );

        if(!$('#dept-filter-wrapper').length){

            $('#table-details_filter').prepend(`
                <div id="dept-filter-wrapper"
                    class="mr-2"
                    style="width:220px;">
                </div>
            `);

        }

        $('#filterDept').appendTo('#dept-filter-wrapper');

        if($('#filterDept').hasClass('select2-hidden-accessible')){
            $('#filterDept').select2('destroy');
        }

        $('#filterDept').select2({
            placeholder:'Department',
            allowClear:true,
            width:'100%'
        });

        },

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

                    i = i.replace(/\./g,'')
                        .replace(',', '.');

                    let num = parseFloat(i);

                    return isNaN(num)
                        ? 0
                        : num;
                }

                return 0;
            }

            /*
            ==========================================
            KOLOM CURRENCY
            ==========================================
            */

            let currencyCols = [
                6,7,8,
                9,10,11,12,
                13,14,15,16,
                17,18,19,20,21,22,23,24,25
            ];

            // let ijinTotal = api
            //     .column(23, { search: 'applied' })
            //     .data()
            //     .reduce(function(a, b) {
            //         return intVal(a) + intVal(b);
            //     }, 0);

            // $(api.column(23).footer())
            //     .html(ijinTotal + ' Menit');

            currencyCols.forEach(function(colIndex){

                let total = api
                    .column(colIndex,{search:'applied'})
                    .data()
                    .reduce(function(a,b){

                        return intVal(a)
                            + intVal(b);

                    },0);

                $(api.column(colIndex).footer())
                    .html(formatRupiah(total));

            });

        }

    });
    tableDetails.buttons()
    .container()
    .appendTo('#export-button-container');

});

$(document).on('change', '#filterDept', function(){

    let value = $(this).val();

    if(value){

        tableDetails
            .column(3)
            .search('^' + $.fn.dataTable.util.escapeRegex(value) + '$', true, false)
            .draw();

    }else{

        tableDetails
            .column(3)
            .search('')
            .draw();

    }

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
<div class="modal fade"
     id="adjusmentDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header text-white">

                <h5 class="modal-title">
                    <i class="fas fa-user-clock mr-2"></i>
                    Adjusment Details
                </h5>

                <button type="button"
                        class="close text-white"
                        data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body">

                <table id="table-adjusment-detail"
                       class="table table-bordered table-striped table-hover w-100">

                    <thead class="thead-light">

                        <tr>
                            <th>NPK</th>
                            <th>NAMA KARYAWAN</th>
                            <th>DEPARTEMENT</th>
                            <th>Adjusment</th>
                            <th>Keterangan</th>
                        </tr>

                    </thead>

                    <tfoot>

                        <tr style="
                            font-weight:bold;
                            background:#fff0f0">

                            <th colspan="3"
                                class="text-right">

                                TOTAL ADJUSMENT

                            </th>

                            <th id="total-adjusment">
                                0
                            </th>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>
      <div class="modal fade"
     id="lateDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header text-white">

                <h5 class="modal-title">
                    <i class="fas fa-user-clock mr-2"></i>
                    Late Attendance Details
                </h5>

                <button type="button"
                        class="close text-white"
                        data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body">

                <table id="table-late-detail"
                       class="table table-bordered table-striped table-hover w-100">

                    <thead class="thead-light">

                        <tr>
                            <th>NPK</th>
                            <th>NAMA KARYAWAN</th>
                            <th>DEPARTEMENT</th>
                            <th>Date</th>
                            <th>Work Start</th>
                            <th>Work End</th>
                            <th>First Scan</th>
                            <th>Late Minute</th>
                        </tr>

                    </thead>

                    <tfoot>

                        <tr style="
                            font-weight:bold;
                            background:#fff0f0">

                            <th colspan="7"
                                class="text-right">

                                TOTAL LATE MINUTES

                            </th>

                            <th id="total-late-minute">
                                0
                            </th>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>
<div class="modal fade"
     id="overtimeDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title">
                    <i class="fas fa-clock mr-2"></i>
                    Overtime Details
                </h5>

                <button type="button"
                        class="close text-white"
                        data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body">

                <table id="table-overtime-detail"
                       class="table table-bordered table-striped table-hover w-100">

                    <thead class="thead-light">

                        <tr>
                            <th>NPK</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Normal OT Hours</th>
                        </tr>

                    </thead>

                    <tfoot>

                        <tr style="font-weight:bold;background:#eef7ff">
                            <th colspan="4" class="text-right">
                                TOTAL
                            </th>

                            <th id="total-normal-ot">
                                0
                            </th>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>
<div class="modal fade"
     id="absenceDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-danger text-white">

                <h5 class="modal-title">
                    <i class="fas fa-user-times mr-2"></i>
                    Absence Deduction Details
                </h5>

                <button type="button"
                        class="close text-white"
                        data-dismiss="modal">
                    <span>&times;</span>
                </button>

            </div>

            <div class="modal-body">

                <table id="table-absence-detail"
                       class="table table-bordered table-striped table-hover w-100">

                    <thead>
                        <tr>
                            <th>NPK</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Absence Days</th>
                        </tr>
                    </thead>
                    <tfoot>
                    <tr style="
                        font-weight:bold;
                        background:#fff0f0;
                    ">
                        <th colspan="5" class="text-right">
                            TOTAL ABSENCE DAYS
                        </th>

                        <th id="total-absence-days">
                            0
                        </th>
                    </tr>
                </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>
<div class="modal fade"
     id="ijinDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-info text-white">

                <h5 class="modal-title">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    Ijin Details
                </h5>

                <button type="button"
                        class="close text-white"
                        data-dismiss="modal">
                    <span>&times;</span>
                </button>

            </div>

            <div class="modal-body">

                <table id="table-ijin-detail"
                       class="table table-bordered table-striped table-hover w-100">

                    <thead>
                        <tr>
                            <th>NPK</th>
                            <th>Nama Karyawan</th>
                            <th>Dept</th>
                            <th>Date</th>
                            <th>Jam Keluar</th>
                            <th>Jam Kembali</th>
                            <th>Reason</th>
                            <th>Menit</th>
                        </tr>
                    </thead>

                </table>

            </div>

        </div>

    </div>

</div>
<div class="modal fade"
     id="specialOvertimeDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-success text-white">

                <h5 class="modal-title">
                    <i class="fas fa-business-time mr-2"></i>
                    Special Overtime Details
                </h5>

                <button type="button"
                        class="close text-white"
                        data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body">

                <table id="table-special-overtime-detail"
                       class="table table-bordered table-striped table-hover w-100">

                    <thead class="thead-light">

                        <tr>
                            <th>NPK</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Special OT Hours</th>
                        </tr>

                    </thead>

                    <tfoot>

                        <tr style="font-weight:bold;background:#eef7ff">

                            <th colspan="4"
                                class="text-right">

                                TOTAL

                            </th>

                            <th id="total-special-ot">
                                0
                            </th>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

</div>
<script>
    let adjusmentDetailTable = null;

$(document).on(
    'click',
    '.btn-adjusment-detail',
    function(){

        let details = JSON.parse(
            decodeURIComponent(
                $(this).data('adjusment')
            )
        );

        let totalAdjusment = 0;

        details.forEach(function(row){

            totalAdjusment += Number(
                row.adjusment || 0
            );

        });

        $('#total-adjusment')
            .html(
                totalAdjusment.toLocaleString('id-ID')
            );

        if(adjusmentDetailTable){

            adjusmentDetailTable.destroy();

            $('#table-adjusment-detail tbody')
                .remove();
        }

        $('#table-adjusment-detail')
            .append('<tbody></tbody>');

        adjusmentDetailTable =
            $('#table-adjusment-detail')
            .DataTable({

                data: details,

                pageLength: 10,

                responsive: true,

                ordering: true,

                searching: true,

                order:[[0,'asc']],

                createdRow:function(row,data){

                    if(
                        Number(data.adjusment) > 0
                    ){
                        $(row).addClass(
                            'adjusment-row'
                        );
                    }

                },

                columns:[
                    {
                        data:'npk'
                    },
                    {
                        data:'NAMA_KARYAWAN'
                    },
                    {
                        data:'DEPARTEMENT'
                    },
                    {
                        data:'adjusment'
                    },
                    {
                        data:'keterangan'
                    },

                ]

            });

        $('#adjusmentDetailModal')
            .modal('show');

    }
);
</script>
<script>
    let ijinDetailTable = null;

$(document).on('click','.btn-ijin-detail',function(){

    let details = JSON.parse(
        decodeURIComponent($(this).data('ijin'))
    );

    if(ijinDetailTable){
        ijinDetailTable.destroy();
        $('#table-ijin-detail tbody').remove();
    }

    $('#table-ijin-detail').append('<tbody></tbody>');

    ijinDetailTable = $('#table-ijin-detail').DataTable({
        data: details,
        columns: [
            { data:'npk' },
            { data:'NAMA_KARYAWAN' },
            { data:'DEPARTEMENT' },
            { data:'tanggal' },
            { data:'jam_keluar' },
            { data:'jam_kembali' },
            { data:'reason' },
            { data:'ijin_minutes' }
        ]
    });

    $('#ijinDetailModal').modal('show');
});
</script>
<script>
let absenceDetailTable = null;

$(document).on(
    'click',
    '.btn-absence-detail',
    function(){

        let details = JSON.parse(
            decodeURIComponent(
                $(this).data('absence')
            )
        );

        details = details.filter(row =>
            ['MA','P1','H','BR','OUT','SD','CT'].includes(
                row.absence_status
            )
        );

        let totalAbsence = 0;

        details.forEach(function(row){

            totalAbsence += Number(
                row.absence_days || 0
            );

        });

$('#total-absence-days')
    .html(
        totalAbsence.toLocaleString('id-ID')
    );

        if(absenceDetailTable){

            absenceDetailTable.destroy();

            $('#table-absence-detail tbody')
                .remove();
        }

        $('#table-absence-detail')
            .append('<tbody></tbody>');

        absenceDetailTable =
            $('#table-absence-detail')
            .DataTable({

                data: details,

                pageLength:10,
                responsive:true,

                columns:[

                    {
                        data:'NPK'
                    },

                    {
                        data:'NAMA_KARYAWAN'
                    },

                    {
                        data:'DEPARTEMENT'
                    },

                    {
                        data:'OVERTIME_DATE'
                    },

                    {
                        data: 'absence_status',
                        className: 'text-center',
                        render: function(data) {

                            if (data === 'MA' || data === 'P1' || data === 'OUT') {
                                return `
                                    <span class="badge badge-danger">
                                        ${data}
                                    </span>
                                `;
                            }

                            return `
                                <span class="badge badge-warning">
                                    ${data ?? '-'}
                                </span>
                            `;
                        }
                    },

                    {
                        data:'absence_days',
                        className:'text-center'
                    }

                ]

            });

        $('#absenceDetailModal').modal('show');

    }
);
</script>
<script>
    let lateDetailTable = null;

$(document).on(
    'click',
    '.btn-late-detail',
    function(){

        let details = JSON.parse(
            decodeURIComponent(
                $(this).data('late')
            )
        );

        let totalLate = 0;

        details.forEach(function(row){

            totalLate += Number(
                row.late_minute || 0
            );

        });

        $('#total-late-minute')
            .html(
                totalLate.toLocaleString('id-ID')
            );

        if(lateDetailTable){

            lateDetailTable.destroy();

            $('#table-late-detail tbody')
                .remove();
        }

        $('#table-late-detail')
            .append('<tbody></tbody>');

        lateDetailTable =
            $('#table-late-detail')
            .DataTable({

                data: details,

                pageLength: 10,

                responsive: true,

                ordering: true,

                searching: true,

                order:[[0,'asc']],

                createdRow:function(row,data){

                    if(
                        Number(data.late_minute) > 0
                    ){
                        $(row).addClass(
                            'late-row'
                        );
                    }

                },

                columns:[
                    {
                        data:'NPK'
                    },
                    {
                        data:'NAMA_KARYAWAN'
                    },
                    {
                        data:'DEPARTEMENT'
                    },
                    {
                        data:'scan_day'
                    },

                    {
                        data:'work_start',
                        render:function(data){

                            return data
                                ? data.substring(0,5)
                                : '-';
                        }
                    },

                    {
                        data:'work_end',
                        render:function(data){

                            return data
                                ? data.substring(0,5)
                                : '-';
                        }
                    },

                    {
                        data:'first_scan',
                        render:function(data){

                            if(
                                data === null ||
                                data === ''
                            ){
                                return `
                                    <span class="badge badge-secondary">
                                        No Scan
                                    </span>
                                `;
                            }

                            return data;
                        }
                    },

                    {
                        data:null,
                        className:'text-center',
                        render:function(data,type,row){

                            if(
                                row.first_scan === null ||
                                row.first_scan === ''
                            ){
                                return `
                                    <span class="badge badge-warning">
                                        No Scan
                                    </span>
                                `;
                            }

                            let minute = Number(row.late_minute || 0);
                            let actualminute = Number(row.late_actual || 0);

                            if(minute > 0){
                                if(minute == actualminute) {
                                    return `
                                    <span class="badge badge-danger">
                                        ${minute} Min
                                    </span>
                                `;
                                } else {
                                return `
                                    <span class="badge badge-danger">
                                        ${minute}(${actualminute}) Min
                                    </span>
                                `;
                                }
                            }

                            return `
                                <span class="badge badge-success">
                                    On Time
                                </span>
                            `;
                        }
                    }

                ]

            });

        $('#lateDetailModal')
            .modal('show');

    }
);
</script>
<script>
    let specialOvertimeDetailTable = null;
    $(document).on(
    'click',
    '.btn-special-overtime-detail',
    function(){

        let details = JSON.parse(
            decodeURIComponent(
                $(this).data('special-overtime')
            )
        );

        let totalSpecial = 0;

        details.forEach(function(row){

            totalSpecial += Number(
                row.special_overtime_hours || 0
            );

        });

        $('#total-special-ot')
            .html(totalSpecial);

        if(specialOvertimeDetailTable){

            specialOvertimeDetailTable.destroy();

            $('#table-special-overtime-detail tbody')
                .remove();
        }

        $('#table-special-overtime-detail')
            .append('<tbody></tbody>');

        specialOvertimeDetailTable =
            $('#table-special-overtime-detail')
            .DataTable({

                data: details,

                pageLength: 10,

                responsive: true,

                ordering: true,

                searching: true,

                columns:[

                    {
                        data:'NPK'
                    },

                    {
                        data:'NAMA_KARYAWAN'
                    },

                    {
                        data:'DEPARTEMENT'
                    },

                    {
                        data:'OVERTIME_DATE'
                    },

                    {
                        data:'special_overtime_hours',
                        className:'text-center'
                    }

                ]

            });

        $('#specialOvertimeDetailModal')
            .modal('show');

    }
);
</script>
<script>
    let overtimeDetailTable = null;
    $(document).on(
    'click',
    '.btn-overtime-detail',
    function(){

        let details = JSON.parse(
            decodeURIComponent(
                $(this).data('overtime')
            )
        );

        let totalNormal = 0;

        details.forEach(function(row){

            totalNormal += Number(
                row.overtime_hours || 0
            );

        });

        $('#total-normal-ot')
            .html(totalNormal);

        if(overtimeDetailTable){

            overtimeDetailTable.destroy();

            $('#table-overtime-detail tbody')
                .remove();
        }

        $('#table-overtime-detail').append(
            '<tbody></tbody>'
        );

        overtimeDetailTable =
            $('#table-overtime-detail').DataTable({

                data: details,

                pageLength: 10,

                responsive: true,

                ordering: true,

                searching: true,

                columns: [

                    {
                        data:'NPK'
                    },

                    {
                        data:'NAMA_KARYAWAN'
                    },

                    {
                        data:'DEPARTEMENT'
                    },

                    {
                        data:'OVERTIME_DATE'
                    },

                    {
                        data:'overtime_hours',
                        className:'text-center'
                    }

                ]

            });

        $('#overtimeDetailModal')
            .modal('show');

    }
);
</script>

   <style>
    
    .btn-adjusment-detail{
    text-decoration:none;
    transition:.2s;
    font-weight:600;
}

.btn-adjusment-detail:hover{
    text-decoration:none;
    color:#dc3545 !important;
}
    #export-button-container .dt-buttons{
    margin-bottom:0;
}

#export-button-container .btn{
    margin-left:.5rem;
}
    .btn-ijin-detail{
    text-decoration:none;
    transition:.2s;
    font-weight:600;
}

.btn-ijin-detail:hover{
    text-decoration:none;
    color:#dc3545 !important;
}
.dept-filter-wrapper{
    min-width:220px;
}

.dept-filter-wrapper .select2-container{
    width:220px !important;
}

.dept-filter-wrapper .select2-selection--single{
    height:31px !important;
    border-radius:6px !important;
}

.dept-filter-wrapper .select2-selection__rendered{
    line-height:29px !important;
    font-size:12px;
}

.dept-filter-wrapper .select2-selection__arrow{
    height:29px !important;
}

#table-details_filter label{
    margin-bottom:0;
}
    #filterDept + .select2-container .select2-selection--single{
    height:34px;
    border-radius:8px;
    border:1px solid #d1d3e2;
}

#filterDept + .select2-container .select2-selection__rendered{
    line-height:32px;
    font-size:13px;
}

#filterDept + .select2-container .select2-selection__arrow{
    height:32px;
}

.select2-dropdown{
    border-radius:8px;
    overflow:hidden;
}

.select2-results__option{
    font-size:13px;
}
    .btn-late-detail{
    text-decoration:none;
    transition:.2s;
    font-weight:600;
}

.btn-late-detail:hover{
    text-decoration:none;
    color:#dc3545 !important;
}

#lateDetailModal .modal-dialog{
    max-width:95%;
}

#lateDetailModal .modal-content{
    border-radius:15px;
    overflow:hidden;
}

#lateDetailModal .modal-header{
    background:linear-gradient(
        135deg,
        #dc3545,
        #b02a37
    );
}

#table-late-detail{
    font-size:13px;
}

#table-late-detail tbody tr:hover{
    background:#fff5f5;
}

.late-row{
    background:#ffe5e5 !important;
    color:#a30000;
    font-weight:bold;
}

    .btn-overtime-detail{
    text-decoration:none;
    transition:.2s;
}

.btn-overtime-detail:hover{
    color:#0056b3 !important;
    text-decoration:none;
}

#overtimeDetailModal .modal-content{
    border-radius:15px;
    overflow:hidden;
}

#overtimeDetailModal .modal-header{
    background:linear-gradient(
        135deg,
        #4e73df,
        #224abe
    );
}

#overtimeDetailModal .modal-dialog,
#specialOvertimeDetailModal .modal-dialog{
    max-width:90%;
}

#table-overtime-detail,
#table-special-overtime-detail{
    font-size:13px;
}

#table-overtime-detail tbody tr:hover,
#table-special-overtime-detail tbody tr:hover{
    background:#f5f9ff;
}

#table-overtime-detail_wrapper .dataTables_filter,
#table-special-overtime-detail_wrapper .dataTables_filter{
    margin-bottom:10px;
}

#table-overtime-detail tfoot,
#table-special-overtime-detail tfoot{
    background:#eef7ff;
}

#overtimeDetailModal table tbody tr:hover{
    background:#f8fbff;
}

#overtimeDetailModal .table-primary{
    font-size:14px;
}

    .btn-special-overtime-detail{
    text-decoration:none;
    transition:.2s;
}

.btn-special-overtime-detail:hover{
    color:#0056b3 !important;
    text-decoration:none;
}

#specialOvertimeDetailModal .modal-content{
    border-radius:15px;
    overflow:hidden;
}

#specialOvertimeDetailModal .modal-header{
    background:linear-gradient(
        135deg,
        #4e73df,
        #224abe
    );
}

#specialOvertimeDetailModal table tbody tr:hover{
    background:#f8fbff;
}

#specialOvertimeDetailModal .table-primary{
    font-size:14px;
}
#table-details tbody tr.table-danger td{
    background-color:#f8d7da !important;
    color:#721c24 !important;
}

.card {
    border-radius: 12px;
}

.card-header {
    border-top-left-radius: 12px !important;
    border-top-right-radius: 12px !important;
}

.table th {
    white-space: nowrap;
    vertical-align: middle;
}

.table td {
    vertical-align: middle;
}

.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
}

.btn {
    border-radius: 8px;
}

#approvalBox .alert {
    border-radius: 10px;
}

#table-details tbody tr:hover {
    background-color: #f5f9ff !important;
}

.btn-absence-detail{
    text-decoration:none;
    transition:.2s;
    font-weight:600;
}

.btn-absence-detail:hover{
    text-decoration:none;
    color:#dc3545 !important;
}

#absenceDetailModal .modal-dialog{
    max-width:90%;
}

#absenceDetailModal .modal-content{
    border-radius:15px;
    overflow:hidden;
}

#absenceDetailModal .modal-header{
    background:linear-gradient(
        135deg,
        #dc3545,
        #b02a37
    );
}

</style>
   </body>
</html>