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

                {{-- ===================== INFO ROLE PAYROLL ===================== --}}
                @if($noRoleAssigned)
                    <div class="alert alert-danger py-2 px-3 mb-3 mt-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Akun Anda belum terdaftar di <strong>role_payrolls</strong>, sehingga tidak ada periode payroll yang bisa diproses/dicek dari halaman ini.
                        Silakan hubungi Admin untuk pengaturan akses.
                    </div>
                @elseif($payrollRoleLabel && $payrollRoleLabel !== 'Semua (Tidak Difilter)')
                    <div class="alert alert-info py-2 px-3 mb-3 mt-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Hasil "Check Payroll" ditampilkan sesuai akses role payroll Anda: <strong>{{ $payrollRoleLabel }}</strong>
                    </div>
                @endif

            </div>
            @if(!$noRoleAssigned)
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
                     action="{{ route('payroll-process.processv2') }}"
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
                     <div id="validationButtons"
                        class="mt-4"
                        style="display:none">

                        <label class="font-weight-bold text-dark d-block mb-2">
                            <i class="fas fa-clipboard-check mr-1"></i>
                            Payroll Requirement Check
                        </label>

                        <div class="d-flex flex-wrap"
                            style="gap:8px;">

                            <button type="button"
                                id="btnApprovalStatus"
                                class="btn btn-outline-secondary btn-sm shadow-sm">
                                <i class="fas fa-check-circle mr-2"></i>
                                Approval Status
                                <span id="badgeApprovalStatus"
                                    class="badge badge-pill badge-light ml-1">-</span>
                            </button>

                            <button type="button"
                                id="btnInvalidContract"
                                class="btn btn-outline-danger btn-sm shadow-sm"
                                style="display:none">
                                <i class="fas fa-file-contract mr-2"></i>
                                Kontrak Tidak Valid
                                <span id="badgeInvalidContract"
                                    class="badge badge-pill badge-light ml-1">0</span>
                            </button>

                            <button type="button"
                                id="btnInvalidBank"
                                class="btn btn-outline-danger btn-sm shadow-sm"
                                style="display:none">
                                <i class="fas fa-university mr-2"></i>
                                Rekening Belum Ada
                                <span id="badgeInvalidBank"
                                    class="badge badge-pill badge-light ml-1">0</span>
                            </button>

                            <button type="button"
                                id="btnDuplicateBank"
                                class="btn btn-outline-danger btn-sm shadow-sm"
                                style="display:none">
                                <i class="fas fa-clone mr-2"></i>
                                Rekening Duplikat
                                <span id="badgeDuplicateBank"
                                    class="badge badge-pill badge-light ml-1">0</span>
                            </button>

                            <button type="button"
                                id="btnDataFreshness"
                                class="btn btn-outline-info btn-sm shadow-sm">
                                <i class="fas fa-database mr-2"></i>
                                Kelengkapan Data Absensi &amp; Insentif
                                <span id="badgeDataFreshness"
                                    class="badge badge-pill badge-light ml-1">-</span>
                            </button>

                        </div>

                        <div id="approvalLoading"
                            class="text-center p-3"
                            style="display:none">
                            <i class="fas fa-spinner fa-spin fa-2x"></i>
                        </div>

                     </div>
                     <br>
                    <div class="d-flex flex-wrap mt-4">

                    @canRoute('payroll-process.processv2')
                        <button id="btnProcess"
                            class="btn btn-success btn-sm shadow-sm"
                            disabled>
                            <i class="fas fa-cogs mr-2"></i>
                            Process Payroll
                        </button>
                        @endcanRoute

                    @canRoute('payroll-process.checkv2')
                        <button type="button"
                            id="btnCheckPayroll"
                            class="btn btn-outline-primary btn-sm ml-2 shadow-sm">

                            <i class="fas fa-search mr-2"></i>
                            Check Payroll

                        </button>

                        <button type="button"
                            id="btnMinusSalaryAlert"
                            class="btn btn-outline-danger btn-sm ml-2 shadow-sm"
                            style="display:none;">

                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Karyawan Gaji Minus
                            <span id="minusSalaryBadge" class="badge badge-danger ml-1">0</span>

                        </button>

                    @endcanRoute
                    </div>
                  </form>
               </div>
            </div>
            <div id="payroll-detail-container"
            style="display:none;"
            class="mt-4">
            @endif

@if(!$noRoleAssigned)
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
                                    <th>Night Shift Compensation</th>
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
                                    <th>Action</th>
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
@endif

         </div>
      </div>
      <br>
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
HELPER : FORMAT TANGGAL INDONESIA
================================================
*/

function formatDateID(dateStr){

    if(!dateStr) return '-';

    let d = new Date(dateStr);

    return d.toLocaleString('id-ID', {
        day:'2-digit',
        month:'short',
        year:'numeric',
        hour:'2-digit',
        minute:'2-digit'
    });
}

/*
================================================
HELPER : WARNA BADGE BERDASARKAN SELISIH HARI
================================================
*/

function freshnessBadgeClass(daysDiff){

    if(daysDiff === null || daysDiff === undefined) return 'badge-secondary';
    if(daysDiff <= 1) return 'badge-success';
    if(daysDiff <= 3) return 'badge-warning';

    return 'badge-danger';
}

/*
================================================
RENDER : APPROVAL STATUS MODAL
================================================
*/

function renderApprovalModal(approvals){

    let html = '';

    if(approvals.length === 0){

        html = `<tr>
                    <td colspan="3" class="text-center text-danger">
                        Approval belum tersedia
                    </td>
                </tr>`;

        $('#approvalStatusTableBody').html(html);
        return;
    }

    approvals.forEach(row=>{

        let badge = '';

        if(row.status === 'finish'){
            badge = `<span class="badge badge-success">Finish</span>`;
        }else{
            badge = `<span class="badge badge-warning">Pending</span>`;
        }

        let progressHtml = '-';

        if(row.progress && row.progress.length > 0){

            progressHtml = row.progress.map(p=>{

                let statusBadge = '';
                let timeHtml = '';

                if(p.status === 'approve'){

                    statusBadge = `<span class="badge badge-success">Approve</span>`;

                    if(p.approved_at){
                        timeHtml = `<small class="badge badge-primary white">${formatDateID(p.approved_at)}</small>`;
                    }

                }else{
                    statusBadge = `<span class="badge badge-warning">Waiting</span>`;
                }

                return `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="mr-2">${p.npk} - ${p.nama}</span>
                        <span class="d-flex align-items-center">
                            ${statusBadge}
                            ${timeHtml}
                        </span>
                    </div>
                `;

            }).join('');
        }

        html += `
            <tr>
                <td>${row.payroll_component.toUpperCase()}</td>
                <td>${badge}</td>
                <td style="min-width:220px">${progressHtml}</td>
            </tr>
        `;
    });

    $('#approvalStatusTableBody').html(html);
}

/*
================================================
RENDER : KONTRAK TIDAK VALID MODAL
================================================
*/

function renderInvalidContractModal(invalidContracts){

    let html = '';

    invalidContracts.forEach(emp=>{

        let empName = emp.NAMA_KARYAWAN ?? 'Kontrak tidak valid';

        html += `<li>${emp.NPK} - ${empName}</li>`;
    });

    $('#invalidContractList').html(html || '<li class="text-muted">Tidak ada data</li>');
    $('#invalidContractCount').text(invalidContracts.length);
}

/*
================================================
RENDER : REKENING BELUM ADA MODAL
================================================
*/

function renderInvalidBankModal(invalidBankAccounts){

    let html = '';

    invalidBankAccounts.forEach(emp=>{

        let empNameBankAccount = emp.bank_account ?? 'Nomor Rekening Belum Ada';

        html += `<li>${emp.NPK} - ${emp.NAMA} - ${empNameBankAccount}</li>`;
    });

    $('#invalidBankList').html(html || '<li class="text-muted">Tidak ada data</li>');
    $('#invalidBankCount').text(invalidBankAccounts.length);
}

/*
================================================
RENDER : REKENING DUPLIKAT MODAL
================================================
*/

function renderDuplicateBankModal(duplicateBankAccounts){

    let grouped = {};

    duplicateBankAccounts.forEach(emp=>{

        let key = emp.bank_account ?? '-';

        if(!grouped[key]){
            grouped[key] = [];
        }

        grouped[key].push(emp);
    });

    let groupedHtml = '';

    Object.keys(grouped).forEach(bankAcc=>{

        groupedHtml += `
            <li class="mb-2">
                <b>No. Rek: ${bankAcc}</b>
                <ul style="padding-left:20px">
        `;

        grouped[bankAcc].forEach(emp=>{
            groupedHtml += `<li>${emp.NPK} - ${emp.NAMA_KARYAWAN ?? '-'}</li>`;
        });

        groupedHtml += `</ul></li>`;
    });

    $('#duplicateBankList').html(groupedHtml || '<li class="text-muted">Tidak ada data</li>');
    $('#duplicateBankCount').text(Object.keys(grouped).length);

    return Object.keys(grouped).length;
}

/*
================================================
RENDER : KELENGKAPAN DATA (ATT_LOG / OVERTIME / INSENTIF) MODAL
================================================
*/

function renderDataFreshnessModal(res){

    let rows = [
        {
            label: res.att_log.label,
            last_date: res.att_log.last_date,
            days_diff: res.att_log.days_diff
        },
        {
            label: res.overtimes.label,
            last_date: res.overtimes.last_date,
            days_diff: res.overtimes.days_diff
        }
    ];

    Object.keys(res.insentif.detail).forEach(key=>{

        let d = res.insentif.detail[key];

        rows.push({
            label: 'Insentif - ' + d.label,
            last_date: d.last_date,
            days_diff: d.days_diff
        });
    });

    let html = '';

    rows.forEach(r=>{

        let cls = freshnessBadgeClass(r.days_diff);

        let diffText = (r.days_diff === null || r.days_diff === undefined)
            ? 'Tidak ada data'
            : (r.days_diff < 0
                ? 'Updated'
                : (r.days_diff === 0 ? 'Hari ini' : r.days_diff + ' hari lalu'));

        html += `
            <tr>
                <td>${r.label}</td>
                <td>${formatDateID(r.last_date)}</td>
                <td><span class="badge ${cls}">${diffText}</span></td>
            </tr>
        `;
    });

    $('#dataFreshnessTableBody').html(html);
    $('#dataFreshnessToday').text(formatDateID(res.today));

    /*
    ================================================
    BADGE DI TOMBOL -> AMBIL YANG PALING LAMA TIDAK UPDATE
    ================================================
    */

    let worst = rows.reduce(function(max, r){
        if(r.days_diff === null || r.days_diff === undefined) return max;
        return r.days_diff > max ? r.days_diff : max;
    }, 0);

    let worstCls = freshnessBadgeClass(worst);

    $('#badgeDataFreshness')
        .removeClass('badge-success badge-warning badge-danger badge-secondary badge-light')
        .addClass(worstCls)
        .text(worst === 0 ? 'Up to date' : worst + 'h lalu');
}

/*
================================================
LOAD APPROVAL + DATA FRESHNESS WHEN PERIOD SELECTED
================================================
*/

$('#period_id').on('change', function(){

    let periodId = $(this).val();

    $('#validationButtons').hide();
    $('#btnInvalidContract').hide();
    $('#btnInvalidBank').hide();
    $('#btnDuplicateBank').hide();
    $('#btnProcess').prop('disabled', true);

    if(!periodId) return;

    $('#validationButtons').show();
    $('#approvalLoading').show();

    /*
    =========================================
    APPROVAL STATUS + KONTRAK / REKENING
    =========================================
    */

    $.get('/payroll-process/approval/'+periodId, function(res){

        let approvals = res.approval ?? [];
        let invalidContracts = res.invalid_contracts ?? [];
        let invalidBankAccounts = res.invalid_bank_accounts ?? [];
        let duplicateBankAccounts = res.duplicate_bank_accounts ?? [];

        let allFinish = approvals.length > 0;
        let finishedCount = 0;

        approvals.forEach(row=>{
            if(row.status === 'finish'){
                finishedCount++;
            }else{
                allFinish = false;
            }
        });

        renderApprovalModal(approvals);

        $('#badgeApprovalStatus')
            .removeClass('badge-success badge-warning badge-secondary badge-light')
            .addClass(approvals.length === 0 ? 'badge-secondary' : (allFinish ? 'badge-success' : 'badge-warning'))
            .text(approvals.length === 0 ? 'Belum tersedia' : finishedCount + '/' + approvals.length + ' Selesai');

        renderInvalidContractModal(invalidContracts);

        if(invalidContracts.length > 0){
            allFinish = false;
            $('#badgeInvalidContract').text(invalidContracts.length);
            $('#btnInvalidContract').show();
        }else{
            $('#btnInvalidContract').hide();
        }

        renderInvalidBankModal(invalidBankAccounts);

        if(invalidBankAccounts.length > 0){
            allFinish = false;
            $('#badgeInvalidBank').text(invalidBankAccounts.length);
            $('#btnInvalidBank').show();
        }else{
            $('#btnInvalidBank').hide();
        }

        let duplicateGroupCount = renderDuplicateBankModal(duplicateBankAccounts);

        if(duplicateGroupCount > 0){
            allFinish = false;
            $('#badgeDuplicateBank').text(duplicateGroupCount);
            $('#btnDuplicateBank').show();
        }else{
            $('#btnDuplicateBank').hide();
        }

        $('#approvalLoading').hide();

        /*
        ====================================
        ENABLE BUTTON ONLY IF ALL FINISH
        ====================================
        */

        if(allFinish){

            $('#btnProcess')
                .prop('disabled', false)
                .removeClass('btn-secondary')
                .addClass('btn-primary');

        }else{

            $('#btnProcess')
                .prop('disabled', true)
                .removeClass('btn-primary')
                .addClass('btn-secondary');
        }
    });

    /*
    =========================================
    KELENGKAPAN DATA ATT_LOG / OVERTIME / INSENTIF
    =========================================
    */

    $('#badgeDataFreshness')
        .removeClass('badge-success badge-warning badge-danger badge-secondary')
        .addClass('badge-light')
        .text('...');

    $.get('/payroll-process/data-freshness/'+periodId, function(res){
        renderDataFreshnessModal(res);
    });

});

/*
================================================
TOMBOL -> BUKA MODAL MASING-MASING
================================================
*/

$(document).on('click', '#btnApprovalStatus', function(){
    $('#modalApprovalStatus').modal('show');
});

$(document).on('click', '#btnInvalidContract', function(){
    $('#modalInvalidContract').modal('show');
});

$(document).on('click', '#btnInvalidBank', function(){
    $('#modalInvalidBank').modal('show');
});

$(document).on('click', '#btnDuplicateBank', function(){
    $('#modalDuplicateBank').modal('show');
});

$(document).on('click', '#btnDataFreshness', function(){
    $('#modalDataFreshness').modal('show');
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

    let url = "{{ route('payroll-process.processv2') }}";

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
        minimumFractionDigits:2
    }).format(number);
}
</script>
<script>
    let tableDetails = null;
    let minusSalaryList = [];

function renderMinusSalaryModal(){

    let tbody = $('#minusSalaryTableBody');

    tbody.empty();

    if(minusSalaryList.length === 0){

        tbody.append(`
            <tr>
                <td colspan="4" class="text-center text-muted">
                    Tidak ada karyawan dengan Total Salary minus.
                </td>
            </tr>
        `);

        $('#minusSalaryCount').text(0);

        return;
    }

    minusSalaryList.forEach(function(item){

        tbody.append(`
            <tr>
                <td>${item.npk ?? '-'}</td>
                <td>${item.nama ?? '-'}</td>
                <td>${item.bagian ?? '-'}</td>
                <td class="text-right text-danger font-weight-bold">
                    ${formatRupiah(item.total_salary)}
                </td>
            </tr>
        `);
    });

    $('#minusSalaryCount').text(minusSalaryList.length);
}

$(document).on('click','#btnMinusSalaryAlert',function(){
    renderMinusSalaryModal();
    $('#minusSalaryModal').modal('show');
});

// ⭐ View Slip Live — ambil data payroll langsung dari hasil job V2
// (belum disimpan), difilter berdasarkan NPK baris yang tombolnya
// diklik, lalu ditampilkan sebagai halaman HTML biasa (bukan PDF,
// bukan ter-password) di tab baru.
$(document).on('click', '.btnViewSlipLive', function(){

    let npk = $(this).data('npk');
    let periodId = $('#period_id').val();

    if(!periodId || !npk){
        return;
    }

    let url = '/payroll-process/slip-live/' + periodId + '/' + npk + '?version=v2';

    window.open(url, '_blank');
});

$(document).on('click','#btnCheckPayroll',function(){

    let periodId = $('#period_id').val();

    if(!periodId){
        return;
    }

    let url = '/payroll-process/checkv2/' + periodId;

    let periodName = $('#period_id option:selected').text();

    $('#detail-title').text(
        'Payroll Check Result - ' + periodName
    );

    $('#payroll-detail-container').show();

    if(tableDetails){
        tableDetails.destroy();
    }

    $('#btnMinusSalaryAlert').hide();

    $('#table-details').off('xhr.dt').on('xhr.dt', function(e, settings, json){

        let rows = Array.isArray(json) ? json : (json.data ?? []);

        minusSalaryList = rows
            .filter(function(row){
                return Number(row.total_salary || 0) < 0;
            })
            .map(function(row){
                return {
                    npk: row.employee_npk,
                    nama: row.employee_name,
                    bagian: row.dept,
                    total_salary: row.total_salary
                };
            });

        renderMinusSalaryModal();

        if(minusSalaryList.length > 0){

            $('#minusSalaryBadge').text(minusSalaryList.length);
            $('#btnMinusSalaryAlert').show();

            $('#minusSalaryModal').modal('show');

        }else{

            $('#btnMinusSalaryAlert').hide();
        }
    });

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
                11,12,13,14,15,16,17,18,
                19,20,21,22,23,24,25,26,27,28
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
                data:'components.night_shift_compensation.amount',
                defaultContent:0,
                render:function(data,type,row){

                    if(type !== 'display'){
                        return data ?? 0;
                    }

                    let nightShiftData =
                        encodeURIComponent(
                            JSON.stringify(
                                row.night_shift_details || []
                            )
                        );

                    return `
                        <a href="javascript:void(0)"
                        class="btn-night-shift-detail"
                        data-night-shift="${nightShiftData}">
                            ${salaryMaskColored(data ?? 0, row.components?.night_shift_compensation?.type)}
                        </a>
                    `;
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

                    const ket = (row.keterangan || '').trim().toLowerCase();

                    const tmk = row.tmk ? new Date(row.tmk) : null;
                    const tkk = row.tkk ? new Date(row.tkk) : null;
                    const periodStart = row.period_start ? new Date(row.period_start) : null;
                    const periodEnd = row.period_end ? new Date(row.period_end) : null;

                    // Hilangkan pengaruh jam/timezone
                    if (tmk) tmk.setHours(0, 0, 0, 0);
                    if (tkk) tkk.setHours(0, 0, 0, 0);
                    if (periodStart) periodStart.setHours(0, 0, 0, 0);
                    if (periodEnd) periodEnd.setHours(23, 59, 59, 999);

                    const isTMKInPeriod =
                        tmk &&
                        periodStart &&
                        tmk.getFullYear() === periodStart.getFullYear() &&
                        tmk.getMonth() === periodStart.getMonth();

                    const isTKKInPeriod =
                        tkk &&
                        periodStart &&
                        periodEnd &&
                        tkk >= periodStart &&
                        tkk <= periodEnd;

                    // BARU
                    if (isTMKInPeriod && tkk === null) {
                        return `<span class="badge badge-primary">Baru</span>`;
                    }

                    // TKK berada dalam periode payroll
                    if (isTKKInPeriod) {

                        if (ket === 'ma') {
                            return `<span class="badge badge-danger">Mangkir</span>`;
                        }

                        return `<span class="badge badge-warning">Resign</span>`;
                    }

                    // Selain itu Active
                    return `<span class="badge badge-success">Active</span>`;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row){

                    if(type !== 'display'){
                        return '';
                    }

                    return `
                        <button type="button"
                            class="btn btn-info btn-sm btnViewSlipLive"
                            data-npk="${row.employee_npk}"
                            title="Lihat slip gaji live untuk NPK ini">
                            <i class="fas fa-eye"></i> Slip Live
                        </button>
                    `;
                }
            }
        ],

        createdRow: function (row, data) {

            const ket = (data.keterangan || '').trim().toLowerCase();

            $(row).removeClass('table-warning table-danger');

            if (!data.tkk || !data.period_start || !data.period_end) {
                return;
            }

            const tkk = new Date(data.tkk);
            const periodStart = new Date(data.period_start);
            const periodEnd = new Date(data.period_end);

            // Normalisasi jam
            tkk.setHours(0, 0, 0, 0);
            periodStart.setHours(0, 0, 0, 0);
            periodEnd.setHours(23, 59, 59, 999);

            if (tkk >= periodStart && tkk <= periodEnd) {
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

    // Ambil data mentah untuk baris yang sedang aktif (sesuai filter/search)
    let rowsData = api.rows({search:'applied'}).data().toArray();

    // Mapping index kolom -> field komponen (harus sinkron dgn definisi kolom di atas)
    let currencyFields = [
        {index:6,  field:'basic_salary'},
        {index:7,  field:'overtime_pay'},
        {index:8,  field:'special_overtime_pay'},
        {index:9,  field:'monthly_premi'},
        {index:10, field:'long_service_allowance'},
        {index:11, field:'allowance'},
        {index:12, field:'sewing_insentif'},
        {index:13, field:'pad_insentif'},
        {index:14, field:'cutting_insentif'},
        {index:15, field:'heat_insentif'},
        {index:16, field:'sixs_insentif'},
        {index:17, field:'night_shift_compensation'},
        {index:18, field:'adjusment'},
        {index:19, field:'bpjs_kesehatan'},
        {index:20, field:'bpjs_ketenagakerjaan'},
        {index:21, field:'pph_21'},
        {index:22, field:'pph_21_deduction'},
        {index:23, field:'absence_deduction'},
        {index:24, field:'late_deduction'},
        {index:25, field:'work_leave_deduction'}
    ];

    currencyFields.forEach(function(cfg){

        let total = 0;
        let isDeduction = false;

        rowsData.forEach(function(rowData){

            let comp = rowData.components
                ? rowData.components[cfg.field]
                : null;

            if(!comp) return;

            let amount = Number(comp.amount || 0);

            if(comp.type === 'deduction'){
                isDeduction = true;
                if(amount > 0){
                    amount = -amount;
                }
            }

            total += amount;
        });

        let color = isDeduction ? '#dc3545' : '#003366';

        $(api.column(cfg.index).footer())
            .html(
                `<span style="color:${color}">${formatRupiah(total)}</span>`
            );

    });

    // TOTAL SALARY (kolom 26) tetap net, tanpa dibalik tandanya
    let totalSalarySum = rowsData.reduce(function(sum, rowData){
        return sum + Number(rowData.total_salary || 0);
    }, 0);

    $(api.column(26).footer())
        .html(formatRupiah(totalSalarySum));

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

const canSeeSalary = @json(
    \App\Services\PayrollRoleFilterService::canSeeSalary(
        \App\Services\PayrollRoleFilterService::getRole(Auth::user())
    )
);

// console.log('CAN SEE SALARY =', canSeeSalary);

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

    let value = Number(amount ?? 0);

    // Jika deduction, jadikan minus (kecuali sudah negatif)
    if(componentType === 'deduction' && value > 0){
        value = -value;
    }

    let masked = salaryMask(value);

    if(!canSeeSalary){
        return masked; // tetap '****' tanpa styling
    }

    return `<span style="color:${componentColor(componentType)}">${masked}</span>`;
}

function formatIndoDate(dateStr){

    if(!dateStr){
        return '-';
    }

    let d = new Date(dateStr);

    if(isNaN(d.getTime())){
        return dateStr;
    }

    const bulan = [
        'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober',
        'November','Desember'
    ];

    return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}`;
}

function formatTimeOnly(timeStr){

    if(!timeStr){
        return '-';
    }

    let str = timeStr.toString().trim();

    // Handle datetime format e.g. "2026-07-01 07:37:45.0000000"
    if(str.includes(' ')){
        str = str.split(' ')[1];
    }

    // Handle date-time with 'T' separator e.g. "2026-07-01T07:37:45.0000000"
    if(str.includes('T')){
        str = str.split('T')[1];
    }

    return str.substring(0,5);
}

function formatTimeRange(startStr,endStr){

    if(!startStr && !endStr){
        return '-';
    }

    return `${formatTimeOnly(startStr)} - ${formatTimeOnly(endStr)}`;
}

</script>
<style>
    .btn-adjusment-detail{
    color:#4e73df !important;
    text-decoration:none;
    transition:.2s;
    font-weight:600;
}

.btn-adjusment-detail:hover{
    text-decoration:none;
    color:#dc3545 !important;
}
    .btn-ijin-detail{
    color:#4e73df !important;
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
    color:#4e73df !important;
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
    color:#4e73df !important;
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
    color:#4e73df !important;
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

    .btn-night-shift-detail{
    color:#4e73df !important;
    text-decoration:none;
    transition:.2s;
    font-weight:600;
}

.btn-night-shift-detail:hover{
    color:#0056b3 !important;
    text-decoration:none;
}

#nightShiftDetailModal .modal-dialog{
    max-width:90%;
}

#nightShiftDetailModal .modal-content{
    border-radius:15px;
    overflow:hidden;
}

#nightShiftDetailModal .modal-header{
    background:linear-gradient(
        135deg,
        #4e73df,
        #224abe
    );
}

#table-night-shift-detail{
    font-size:13px;
}

#table-night-shift-detail tbody tr:hover{
    background:#f5f9ff;
}

#table-night-shift-detail_wrapper .dataTables_filter{
    margin-bottom:10px;
}

#table-night-shift-detail tfoot{
    background:#eef7ff;
}

#nightShiftDetailModal table tbody tr:hover{
    background:#f8fbff;
}

#nightShiftDetailModal .table-primary{
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

#validationButtons .btn {
    border-radius: 20px;
}

#validationButtons .btn .badge {
    font-size: 85%;
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
<!-- ================= MODAL : APPROVAL STATUS ================= -->
<div class="modal fade"
     id="modalApprovalStatus"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle mr-2"></i>
                    Approval Validation
                </h5>
                <button type="button"
                        class="close text-white"
                        data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Payroll Component</th>
                                <th>Status</th>
                                <th>Approval Progress</th>
                            </tr>
                        </thead>
                        <tbody id="approvalStatusTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary btn-sm"
                        data-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL : KONTRAK TIDAK VALID ================= -->
<div class="modal fade"
     id="modalInvalidContract"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-contract mr-2"></i>
                    Kontrak Karyawan Tidak Valid
                    (<span id="invalidContractCount">0</span>)
                </h5>
                <button type="button"
                        class="close text-white"
                        data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div style="max-height:400px; overflow-y:auto;">
                    <ul id="invalidContractList" style="padding-left:20px; margin-bottom:0;"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary btn-sm"
                        data-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL : REKENING BELUM ADA ================= -->
<div class="modal fade"
     id="modalInvalidBank"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-university mr-2"></i>
                    Nomor Rekening Karyawan Belum Ada
                    (<span id="invalidBankCount">0</span>)
                </h5>
                <button type="button"
                        class="close text-white"
                        data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div style="max-height:400px; overflow-y:auto;">
                    <ul id="invalidBankList" style="padding-left:20px; margin-bottom:0;"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary btn-sm"
                        data-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL : REKENING DUPLIKAT ================= -->
<div class="modal fade"
     id="modalDuplicateBank"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-clone mr-2"></i>
                    Nomor Rekening Bank Duplikat
                    (<span id="duplicateBankCount">0</span>)
                </h5>
                <button type="button"
                        class="close text-white"
                        data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <small class="text-muted d-block mb-2">
                    Nomor rekening di bawah ini digunakan oleh lebih dari satu karyawan dengan nama berbeda.
                </small>
                <div style="max-height:400px; overflow-y:auto;">
                    <ul id="duplicateBankList" style="padding-left:20px; margin-bottom:0;"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary btn-sm"
                        data-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL : KELENGKAPAN DATA (ATT_LOG / OVERTIME / INSENTIF) ================= -->
<div class="modal fade"
     id="modalDataFreshness"
     tabindex="-1"
     role="dialog"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-database mr-2"></i>
                    Kelengkapan Data Absensi &amp; Insentif
                </h5>
                <button type="button"
                        class="close text-white"
                        data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <small class="text-muted d-block mb-3">
                    Tanggal data terakhir masuk, dibandingkan dengan hari ini
                    (<span id="dataFreshnessToday">-</span>).
                </small>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Sumber Data</th>
                                <th>Tanggal Terakhir</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="dataFreshnessTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary btn-sm"
                        data-dismiss="modal">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade"
     id="minusSalaryModal"
     tabindex="-1"
     role="dialog"
     aria-labelledby="minusSalaryModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"
                    id="minusSalaryModalLabel">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Karyawan dengan Total Salary Minus
                    (<span id="minusSalaryCount">0</span>)
                </h5>
                <button type="button"
                        class="close text-white"
                        data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>NPK</th>
                                <th>Nama</th>
                                <th>Bagian</th>
                                <th class="text-right">Total Salary</th>
                            </tr>
                        </thead>
                        <tbody id="minusSalaryTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary btn-sm"
                        data-dismiss="modal">
                    Tutup
                </button>
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
                            <th>Check In / Check Out</th>
                            <th>Shift (Work Start / Work End)</th>
                            <th>Normal OT Hours</th>
                        </tr>

                    </thead>

                    <tfoot>

                        <tr style="font-weight:bold;background:#eef7ff">
                            <th colspan="6" class="text-right">
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
                            <th>Check In / Check Out</th>
                            <th>Shift (Work Start / Work End)</th>
                            <th>Special OT Hours</th>
                        </tr>

                    </thead>

                    <tfoot>

                        <tr style="font-weight:bold;background:#eef7ff">

                            <th colspan="6"
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
<div class="modal fade"
     id="nightShiftDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title">
                    <i class="fas fa-moon mr-2"></i>
                    Night Shift Compensation Details
                </h5>

                <button type="button"
                        class="close text-white"
                        data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body">

                <table id="table-night-shift-detail"
                       class="table table-bordered table-striped table-hover w-100">

                    <thead class="thead-light">

                        <tr>
                            <th>NPK</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Shift Date</th>
                            <th>Shift Name</th>
                            <th>Work Start</th>
                            <th>Work End</th>
                        </tr>

                    </thead>

                    <tfoot>

                        <tr style="font-weight:bold;background:#eef7ff">

                            <th colspan="6"
                                class="text-right">

                                TOTAL NIGHT SHIFT

                            </th>

                            <th id="total-night-shift">
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
            {
                data:'tanggal',
                render:function(data){
                    return formatIndoDate(data);
                }
            },
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
                        data:'OVERTIME_DATE',
                        render:function(data){
                            return formatIndoDate(data);
                        }
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
                        data:'scan_day',
                        render:function(data){
                            return formatIndoDate(data);
                        }
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

        details = details.filter(row =>
            Number(row.special_overtime_hours || 0) > 0
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
                        data:'OVERTIME_DATE',
                        render:function(data){
                            return formatIndoDate(data);
                        }
                    },

                    {
                        data:null,
                        className:'text-center',
                        render:function(data,type,row){
                            return formatTimeRange(
                                row.check_in,
                                row.check_out
                            );
                        }
                    },

                    {
                        data:null,
                        className:'text-center',
                        render:function(data,type,row){
                            return formatTimeRange(
                                row.work_start,
                                row.work_end
                            );
                        }
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

        details = details.filter(row =>
            Number(row.overtime_hours || 0) > 0
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
                        data:'OVERTIME_DATE',
                        render:function(data){
                            return formatIndoDate(data);
                        }
                    },

                    {
                        data:null,
                        className:'text-center',
                        render:function(data,type,row){
                            return formatTimeRange(
                                row.check_in,
                                row.check_out
                            );
                        }
                    },

                    {
                        data:null,
                        className:'text-center',
                        render:function(data,type,row){
                            return formatTimeRange(
                                row.work_start,
                                row.work_end
                            );
                        }
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
<script>
    let nightShiftDetailTable = null;
    $(document).on(
    'click',
    '.btn-night-shift-detail',
    function(){

        let details = JSON.parse(
            decodeURIComponent(
                $(this).data('night-shift')
            )
        );

        $('#total-night-shift')
            .html(details.length);

        if(nightShiftDetailTable){

            nightShiftDetailTable.destroy();

            $('#table-night-shift-detail tbody')
                .remove();
        }

        $('#table-night-shift-detail').append(
            '<tbody></tbody>'
        );

        nightShiftDetailTable =
            $('#table-night-shift-detail').DataTable({

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
                        data:'shift_date',
                        render:function(data){
                            return formatIndoDate(data);
                        }
                    },

                    {
                        data:'shift_name'
                    },

                    {
                        data:'work_start',
                        className:'text-center'
                    },

                    {
                        data:'work_end',
                        className:'text-center'
                    }

                ]

            });

        $('#nightShiftDetailModal')
            .modal('show');

    }
);
</script>
   </body>
</html>