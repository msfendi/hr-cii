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
                            >

                            <i class="fas fa-cogs mr-2"></i>
                            Process Payroll
                            disabled
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

            <i class="fas fa-file-invoice-dollar fa-2x text-success"></i>

        </div>

    </div>

    <div class="card-body">

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
                                    <th>Adjusments</th>
                                    <th>BPJS Kes</th>
                                    <th>BPJS TK</th>
                                    <th>PPh21</th>
                                    <th>PPh21 Deduction</th>
                                    <th>Absence</th>
                                    <th>Late</th>
                                    <th>Total Salary</th>
                                    <th>Absence Days</th>
                                    <th>Late Minutes</th>
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

                let empNameBankAccount = emp.NAMA_KARYAWAN ?? 'Nomor Rekening Belum Ada';

                employeeListBankAccount += `
                    <li>
                        ${emp.NPK} - ${empNameBankAccount}
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

    $('#detail-title').text(
        'Payroll Check Result'
    );

    $('#payroll-detail-container').show();

    if(tableDetails){
        tableDetails.destroy();
    }

    tableDetails = $('#table-details').DataTable({

        processing:true,
        responsive:true,

        ajax:{
        url:url,
        dataSrc:function(json){

            let role = userRole;

            let rows = json.data ?? [];

            if(role === 'Payroll_STAFF'){

                rows = rows.filter(x =>
                    Number(x.IS_STAFF) === 1
                );

            }else if(role === 'Payroll_SEWING'){

                rows = rows.filter(x =>
                    Number(x.IS_STAFF) === 0 &&
                    Number(x.IS_SEWING) === 0
                );

            }else if(role === 'Payroll_NONSEWING'){

                rows = rows.filter(x =>
                    Number(x.IS_STAFF) === 0 &&
                    Number(x.IS_SEWING) === 1
                );

            }

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
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.special_overtime_pay',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
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
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
                }
            },

            {
                data:'components.late_deduction',
                defaultContent:0,
                render:function(data,type){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    return salaryMask(data ?? 0);
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
                data:'absence_days',
                defaultContent:0
            },

            {
                data:'components.late_minutes',
                defaultContent:0
            },

            {
                data:'tkk',
                defaultContent:null,
                render:function(data){

                    if(data === null || data === ''){

                        return `
                            <span class="badge badge-success">
                                Active
                            </span>
                        `;
                    }

                    return `
                        <span class="badge badge-danger">
                            Resign
                        </span>
                    `;
                }
            }
        ],

        createdRow:function(row,data){

            if(
                data.tkk !== null &&
                data.tkk !== ''
            ){

                $(row).addClass('table-danger');
            }

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
                17,18,19,20,21
            ];

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

            /*
            ==========================================
            ABSENCE DAYS
            ==========================================
            */

            let absenceTotal = api
                .column(22,{search:'applied'})
                .data()
                .reduce(function(a,b){

                    return intVal(a)
                        + intVal(b);

                },0);

            $(api.column(22).footer())
                .html(absenceTotal.toLocaleString('id-ID'));

            /*
            ==========================================
            LATE MINUTES
            ==========================================
            */

            let lateTotal = api
                .column(23,{search:'applied'})
                .data()
                .reduce(function(a,b){

                    return intVal(a)
                        + intVal(b);

                },0);

            $(api.column(23).footer())
                .html(lateTotal.toLocaleString('id-ID'));

            $(api.column(24).footer())
                .html('-');

        }

    });

});
</script>
<script>

const userRole = @json(optional(Auth::user()->roles->first())->name);

console.log('USER ROLE =', userRole);

const canSeeSalary =
    userRole === 'Admin' ||
    userRole === 'Payroll_STAFF' ||
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

</style>
   </body>
</html>