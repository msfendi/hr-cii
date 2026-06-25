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

                <div>
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        Payroll Processing Center
                    </h1>

                    <small class="text-muted">
                        Validate payroll approval, check payroll simulation and generate payroll process.
                    </small>
                </div>

            </div>
            <div class="card border-left-primary shadow-lg mb-4">
               <div class="card-header py-3 bg-white">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h5 class="m-0 font-weight-bold text-primary">
                                Payroll Processing
                            </h5>

                            <small class="text-muted">
                                Select payroll period and validate all required approvals.
                            </small>

                        </div>

                        <i class="fas fa-money-check-alt fa-2x text-primary"></i>

                    </div>

                </div>
               <div class="card-body">
                  <form method="POST"
                     action="{{ route('payroll-process.process') }}"
                     id="payrollForm">
                     @csrf
                     <div class="form-group">

                        <label class="font-weight-bold text-dark">
                            Payroll Period
                        </label>

                        <small class="d-block text-muted mb-2">
                            Select payroll period before checking approval or processing payroll.
                        </small>
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
                        <div class="card border-left-info shadow-sm">
                           <div class="card-header bg-info text-white">

                                <div class="d-flex justify-content-between">

                                    <span>
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Approval Validation
                                    </span>

                                    <span>
                                        Payroll Requirement Check
                                    </span>

                                </div>

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
                    <div class="d-flex flex-wrap mt-4">

                        <button id="btnProcess"
                            class="btn btn-success btn-sm shadow-sm"
                            disabled>
                            <i class="fas fa-cogs mr-2"></i>
                            Process Payroll
                        </button>

                        <button type="button"
                            id="btnCheckPayroll"
                            class="btn btn-outline-primary btn-sm ml-2 shadow-sm">

                            <i class="fas fa-search mr-2"></i>
                            Check Payroll

                        </button>

                    </div>
                  </form>
               </div>
            </div>
            

            <div id="payroll-detail-container"
            style="display:none;"
            class="mt-4">

            <div class="card shadow-lg border-left-success">

    <div class="card-header bg-white">

        <div class="d-flex justify-content-between align-items-center">

    <div>

        <h5 id="detail-title"
            class="m-0 font-weight-bold text-primary">

            Data Payroll Details

        </h5>

        <small class="text-muted">
            Payroll simulation result for selected payroll period.
        </small>

    </div>

    <div class="d-flex align-items-center">

        <div id="export-button-container"
             class="mr-3">
        </div>

        <i class="fas fa-file-invoice-dollar fa-2x text-success"></i>

    </div>

</div>

    </div>

    <div class="card-body">
<div id="dept-filter-container" style="display:none;">
    <select id="filterDept" class="form-control form-control-sm">
        <option value="">All Department</option>
    </select>
</div>
        <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped table-sm"
                            id="table-details"
                            width="100%">

                            <thead class="thead-light">
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
                                    <th>Total Salary</th>
                                    <th>Total Ijin</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody></tbody>

                            <tfoot>
                                <tr style="
                                        font-weight:bold;
                                        background:#eaf4ff;
                                        color:#003366;
                                    ">
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
      <script src="{{asset('vendor/jquery/select2.min.js')}}"></script>
      <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">

    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
      <script>
         $("#period_id").select2({
         allowClear:true,
         placeholder:'Pilih Periode Payroll'
         });
         $('#filterDept').select2({
    placeholder: 'All Department',
    allowClear: true,
    width: '100%'
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

        /*
        =========================================
        APPROVAL DATA
        =========================================
        */

        let approvals = res.approval ?? [];
        let invalidContracts = res.invalid_contracts ?? [];
        let invalidBankAccounts = res.invalid_bank_accounts ?? [];

        /*
        =========================================
        APPROVAL EMPTY
        =========================================
        */

        if(approvals.length===0){

            html=`<tr>
                    <td colspan="3" class="text-center text-danger">
                        Approval belum tersedia
                    </td>
                  </tr>`;

            allFinish=false;
        }

        /*
        =========================================
        LOOP APPROVAL
        =========================================
        */

        approvals.forEach(row=>{

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

        /*
        =========================================
        INVALID CONTRACT CHECK
        =========================================
        */

        if(invalidContracts.length > 0){

            allFinish = false;

            let employeeList = '';

            invalidContracts.forEach(emp=>{

                let empName = emp.NAMA_KARYAWAN ?? 'Kontrak tidak valid';

                employeeList += `
                    <li>
                        ${emp.NPK} - ${empName}
                    </li>
                `;
            });

            html += `
                <tr>
                    <td colspan="3">

                        <div class="alert alert-danger mb-0">

                            <b>Kontrak Karyawan Tidak Valid :</b>
                            <br>
                            <b>Total Karyawan : ${invalidContracts.length}</b>

                            <br><br>

                            <div style="
                                max-height:250px;
                                overflow-y:auto;
                                border:1px solid #ddd;
                                padding:10px;
                                background:#fff;
                            ">

                                <ul style="margin-bottom:0;padding-left:20px">
                                    ${employeeList}
                                </ul>

                            </div>

                        </div>

                    </td>
                </tr>
            `;
        }

        /*
        =========================================
        INVALID BANK ACCOUNT CHECK
        =========================================
        */

        if(invalidBankAccounts.length > 0){

            allFinish = false;

            let employeeListBankAccount = '';

            invalidBankAccounts.forEach(emp=>{

                let empNameBankAccount = emp.bank_account ?? 'Nomor Rekening Belum Ada';

                employeeListBankAccount += `
                    <li>
                        ${emp.NPK} - ${emp.NAMA} - ${empNameBankAccount}
                    </li>
                `;
            });

            html += `
                <tr>
                    <td colspan="3">

                        <div class="alert alert-danger mb-0">

                            <b>Nomor Rekening Karyawan Belum Ada :</b>
                            <br>
                            <b>Total Karyawan : ${invalidBankAccounts.length}</b>

                            <br><br>

                            <div style="
                                max-height:250px;
                                overflow-y:auto;
                                border:1px solid #ddd;
                                padding:10px;
                                background:#fff;
                            ">

                                <ul style="margin-bottom:0;padding-left:20px">
                                    ${employeeListBankAccount}
                                </ul>

                            </div>

                        </div>

                    </td>
                </tr>
            `;
        }

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
</script>
      <script>

$(document).on('click','#btnProcess',function(e){

    e.preventDefault();

    let periodId = $('#period_id').val();

    if(!periodId){
        Swal.fire({
            icon:'warning',
            title:'Periode belum dipilih'
        });
        return;
    }

    /*
    ==========================================
    ROUTES
    ==========================================
    */

    let url = "{{ route('payroll-process.process') }}";

    let progressUrlTemplate =
        "{{ route('payroll.process.progress', ':period_id') }}";

    let progressUrl =
        progressUrlTemplate.replace(':period_id', periodId);

    /*
    ==========================================
    CONFIRM
    ==========================================
    */

    Swal.fire({
        title: "Generate Payroll?",
        text: "The payroll process will begin. This may take a few minutes depending on the amount of data. Do you want to proceed?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, generate!"
    }).then((result)=>{

        if(!result.isConfirmed) return;

        Swal.fire({
            title: "Payroll is being processed!",
            html: `
                <div class="w-100">

                    <div id="progress-status"
                        style="font-weight:600;margin-bottom:10px">
                        Initializing...
                    </div>

                    <div class="progress" style="height:25px;">
                        <div id="progress-bar"
                            class="progress-bar progress-bar-striped progress-bar-animated"
                            style="width:0%">
                            0%
                        </div>
                    </div>

                </div>
            `,
            allowOutsideClick:false,
            showConfirmButton:false,
            didOpen: ()=>{

                Swal.showLoading();

                /*
                ==========================================
                START PROCESS (FIXED)
                ==========================================
                */

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        period_id: periodId,
                        refresh: 1
                    },
                    error:function(xhr){
                        console.log('Start process error',xhr.responseText);
                    }
                });

                /*
                ==========================================
                POLLING PROGRESS
                ==========================================
                */

                let interval = setInterval(function(){

                    $.ajax({
                        url: progressUrl,
                        type:'GET',
                        success:function(res){

                            let progress = res.progress ?? 0;
                            let status   = res.status ?? 'Processing';

                            $('#progress-bar')
                                .css('width',progress+'%')
                                .text(progress+'%');

                            $('#progress-status')
                                .text(status);

                            if(progress >= 100){

                                clearInterval(interval);

                                Swal.fire({
                                    icon:'success',
                                    title:'Payroll Finished',
                                    text:'Payroll Successfully Calculated!'
                                }).then(()=>{
                                    window.location.href = "{{ route('payroll-process.index') }}";
                                });

                            }
                        },
                        error:function(xhr){
                            console.log('Polling error',xhr.status);
                        }
                    });

                },2000);

            }
        });

    });

});

</script>
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
        minimumFractionDigits:0
    }).format(number);
}
</script>
<script>
    let tableDetails = null;

$(document).on('click','#btnCheckPayroll',function(){

    let periodId = $('#period_id').val();

    if(!periodId){
        return;
    }

    let url = '/payroll-process/check/' + periodId;

    let periodName = $('#period_id option:selected').text();

    $('#detail-title').text(
        'Payroll Check Result - ' + periodName
    );

    $('#payroll-detail-container').show();

    if(tableDetails){
        tableDetails.destroy();
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

            columns: [
                1,2,3,4,5,6,7,8,9,10,
                11,12,13,14,15,16,17,
                18,19,20,21,22,23,24
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
                data:'components.basic_salary',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.overtime_pay',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

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
                            ${salaryMask(data ?? 0)}
                        </a>
                    `;
                }
            },

            {
                data:'components.special_overtime_pay',
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
                            ${salaryMask(data ?? 0)}
                        </a>
                    `;
                }
            },

            {
                data:'components.monthly_premi',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.long_service_allowance',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.allowance',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.sewing_insentif',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.pad_insentif',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.cutting_insentif',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.heat_insentif',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.sixs_insentif',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.adjusment',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.bpjs_kesehatan',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.bpjs_ketenagakerjaan',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.pph_21',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.pph_21_deduction',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.absence_deduction',
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
                            ${salaryMask(data ?? 0)}
                        </a>
                    `;
                }
            },

            {
                data:'components.late_deduction',
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
                            ${salaryMask(data ?? 0)}
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
                data:'total_ijin',
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
                            ${data ?? 0} Menit
                        </a>
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

        createdRow:function(row,data){

            if(
                data.tkk !== null &&
                data.tkk !== '' &&
                (data.keterangan || '').toLowerCase() !== 'MA'
            ){

                $(row).addClass('table-warning');

            } else if(
                data.tkk !== null &&
                data.tkk !== '' &&
                (data.keterangan || '').toLowerCase() === 'MA'
            ){

                $(row).addClass('table-danger');
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
                4,5,6,7,8,
                9,10,11,12,
                13,14,15,16,
                17,18,19,20,21,22
            ];

            let ijinTotal = api
                .column(23, { search: 'applied' })
                .data()
                .reduce(function(a, b) {
                    return intVal(a) + intVal(b);
                }, 0);

            $(api.column(23).footer())
                .html(ijinTotal + ' Menit');

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
<script>

const userRole = @json(optional(Auth::user()->roles->first())->name);

// console.log('USER ROLE =', userRole);

const canSeeSalary =
    userRole === 'Admin' ||
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

</script>
<style>
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
            ['MA','P1','H','BR','OUT','SD'].includes(
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

                            if(minute > 0){
                                return `
                                    <span class="badge badge-danger">
                                        ${minute} Min
                                    </span>
                                `;
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
   </body>
</html>