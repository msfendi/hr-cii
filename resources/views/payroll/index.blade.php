<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @include('layout.navbar')
            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Daftar Payroll Process</h1>
                    <div>
                    @canRoute('payroll-process.generate')
                    <a href="{{ route('payroll-process.generate') }}" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm"><i
                        class="fas fa-plus fa-sm text-white-50"></i> Generate Payroll</a>
                    @endcanRoute

                    @canRoute('payroll-periods.create')
                    <a href="{{ route('payroll-periods.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
                        class="fas fa-plus fa-sm text-white-50"></i> Create Payroll Period</a>
                    @endcanRoute
                    </div>
                </div>

                {{-- ===================== INFO ROLE PAYROLL ===================== --}}
                @if($noRoleAssigned)
                    <div class="alert alert-danger py-2 px-3 mb-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Akun Anda belum terdaftar di <strong>role_payrolls</strong>, sehingga daftar payroll di halaman ini kosong.
                        Silakan hubungi Admin untuk pengaturan akses.
                    </div>
                @elseif($payrollRoleLabel && $payrollRoleLabel !== 'Semua (Tidak Difilter)')
                    <div class="alert alert-info py-2 px-3 mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Total payroll dan jumlah karyawan pada tabel di bawah ini ditampilkan sesuai akses role payroll Anda:
                        <strong>{{ $payrollRoleLabel }}</strong>
                    </div>
                @endif

                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Data Payroll
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
                        @if ($message = Session::get('success'))
                        <div class="alert alert-success alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif

                        @if ($message = Session::get('error'))
                        <div class="alert alert-danger alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif

                        @if ($message = Session::get('warning'))
                        <div class="alert alert-warning alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif

                        @if ($message = Session::get('info'))
                        <div class="alert alert-info alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Period</th>
                                        <th>Process Date</th>
                                        <th>Total Payroll</th>
                                        <th>Employee Count</th>
                                        <th>Export Status</th>
                                        <th>Payroll File</th>
                                        <th>Bank Format</th>
                                        <th>Approval Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($periods as $period)
                                    @php
                                    $folder = strtoupper(str_replace(' ', '_', $period->period_name));
                                    @endphp
                                    <tr>
                                        <td>{{ $period->id }}</td>
                                        <td>{{ $period->period_name }}</td>
                                        <td>{{ $period->processed_at }}</td>
                                        <td>Rp {{ number_format($period->total_payroll,0,',','.') }}</td>
                                        <td>{{ $period->employee_count }}</td>
                                        
                                        <td class="text-center">
                                            @if(!$period->export_status)
                                            <span class="badge badge-secondary">
                                            Not Generated
                                            </span>
                                            @elseif($period->export_status == 'processing')
                                            <span class="badge badge-warning">
                                            <i class="fas fa-spinner fa-spin"></i> Processing
                                            </span>
                                            @elseif($period->export_status == 'finished')
                                            <span class="badge badge-primary">
                                            Finished
                                            </span>
                                            @elseif($period->export_status == 'approved')
                                            <span class="badge badge-success">
                                            Approved
                                            </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(($period->export_status != 'approved' && $period->export_status != 'finished') && $period->file_excel && $period->file_pdf && $period->file_peng)
                                                <span class="badge badge-warning">
                                                    <i class="fas fa-spinner fa-spin"></i> Finalizing Document Approved
                                                </span>
                                            @else
                                                @php
                                                    $roleFolder = \App\Services\PayrollRoleFilterService::folder(
                                                        \App\Services\PayrollRoleFilterService::getRole(auth()->user())
                                                    );
                                                @endphp


                                                {{-- DOWNLOAD EXCEL --}}
                                                @if(($period->export_status == 'finished' || $period->export_status == 'approved') && $period->file_excel)
                                                    <a class="btn btn-success btn-sm"
                                                    href="{{ Storage::url('payroll/'.$folder.'/'.$roleFolder.$period->file_excel) }}"
                                                    target="_blank">
                                                        <i class="fas fa-file-excel mr-1"></i> Excel
                                                    </a>
                                                @endif


                                                {{-- DOWNLOAD PDF --}}
                                                @if(($period->export_status == 'finished' || $period->export_status == 'approved') && $period->file_pdf)
                                                    <a class="btn btn-danger btn-sm"
                                                    href="{{ Storage::url('payroll/'.$folder.'/'.$roleFolder.$period->file_pdf) }}"
                                                    target="_blank">
                                                        <i class="fas fa-file-pdf mr-1"></i> PDF
                                                    </a>
                                                @endif  


                                                {{-- DOWNLOAD PDF PENGELUARAN --}}
                                                @if(($period->export_status == 'finished' || $period->export_status == 'approved') && $period->file_peng)
                                                    <a class="btn btn-secondary btn-sm"
                                                    href="{{ Storage::url('payroll/'.$folder.'/'.$roleFolder.$period->file_peng) }}"
                                                    target="_blank">
                                                        <i class="fas fa-file-pdf mr-1"></i> Pengeluaran
                                                    </a>
                                                @endif
                                            @endif
                                        </td>

                                        <td class="text-center">
                                            {{-- DOWNLOAD BANK (HANYA JIKA APPROVAL FINISH) --}}
                                            @if($period->approve_status == 'finish' && $period->export_status == 'approved' && (auth()->user()->hasRole('Accounting') || auth()->user()->hasRole('Admin')))
                                            
                                                <a class="btn btn-primary btn-sm"
                                                    href="{{ Storage::url('payroll/'.$folder.'/'.$period->file_bank_active) }}"
                                                target="_blank">
                                                    <i class="fas fa-university mr-1"></i> Active
                                                </a>
                                                
                                                <a class="btn btn-secondary btn-sm"
                                                    href="{{ Storage::url('payroll/'.$folder.'/'.$period->file_bank_resign) }}"
                                                target="_blank">
                                                    <i class="fas fa-university mr-1"></i> Resign
                                                </a>
                                            @endif
                                        </td>
                                        
                                        <td class="text-center">
                                            @if($period->approve_status == 'finish')
                                            <span class="badge badge-success">
                                            Approved
                                            </span>
                                            @elseif($period->approve_status == 'pending')
                                            <span class="badge badge-warning">
                                            <i class="fas fa-spinner fa-spin"></i> Waiting
                                            </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @canRoute('payroll-process.details')
                                            <button class="btn btn-info btn-circle btn-sm btn-detail"
                                                data-id="{{ $period->id }}"
                                                data-period="{{ $period->period_name }}">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            @endcanRoute

                                            
                                            @if($period->export_status == 'finished' || $period->export_status == 'approved')

                                            @canRoute('payroll-process.update-pph21')
                                            <button
                                                class="btn btn-warning btn-circle btn-sm btn-update-pph"
                                                data-id="{{ $period->id }}"
                                                title="Update PPH21"
                                            >
                                                <i class="fas fa-percent"></i>
                                            </button>
                                            @endcanRoute
                                            @canRoute('payroll-process.recreate-document')
                                            <button
                                                class="btn btn-secondary btn-circle btn-sm btn-recreate"
                                                data-id="{{ $period->id }}"
                                                title="Recreate Document"
                                            >
                                                <i class="fas fa-sync"></i>
                                            </button>
                                            @endcanRoute

                                            @endif

                                            @if(!$period->export_status)
                                            <!-- <a class="btn btn-warning btn-circle btn-sm"
                                                href="{{ route('payroll.export.export', $period->id) }}"
                                                title="Generate Export">
                                                    <i class="fas fa-database"></i>
                                            </a> -->
                                            @canRoute('payroll.export.export')
                                                <a class="btn btn-warning btn-circle btn-sm btn-export"
                                                    href="#"
                                                    data-url="{{ route('payroll.export.export', $period->id) }}"
                                                    title="Generate Export">
                                                    <i class="fas fa-database"></i>
                                                </a>
                                            @endcanRoute
                                            @endif
                                            @canRoute('payroll-process.destroy')
                                            <a class="btn btn-danger btn-circle btn-sm btn-delete-payroll"
                                                data-id="{{ $period->id }}"
                                                data-period="{{ $period->period_name }}"
                                                data-toggle="modal"
                                                data-target="#deleteModal">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            @endcanRoute
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Content Row -->

                <div id="payroll-detail-container" style="display:none;" class="mt-4">

                <div class="card shadow">
                    <div class="card-header">
                        <h6 id="detail-title" class="m-0 font-weight-bold text-primary">
                            Data Payroll Details
                        </h6>
                    </div>

                    <div class="card-body">
<div id="dept-filter-container" style="display:none;">
    <select id="filterDept" class="form-control form-control-sm">
        <option value="">All Department</option>
    </select>
</div>
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
                                        <th>Absence Deduction</th>
                                        <th>Late Deduction</th>
                                        <th>Work Leave Deduction</th>
                                        <th>Total Salary</th>
                                        <th>Status</th>
                                        <th>Slip</th>
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
                                    </tr>
                                </tfoot>
                                </table>
                        </div>

                    </div>
                </div>

            </div>

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->
        
        <!-- Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="delete-title" class="modal-title" id="exampleModalLabel">Delete Record</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body"><p id="modal-text-payroll_comp"></p></div>
                    <div class="modal-footer">
                        <form id="delete-form" method="POST">
                        @csrf
                        @method('DELETE')

                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <button class="btn btn-danger" type="submit">Delete</button>

                        </form>
                    </div>
                </div>
            </div>
        </div>
<br>
@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function(){

    $('#dataTable').DataTable({
        order: [[0,'desc']], // pakai urutan ID dari Laravel
        pageLength: 10,
        responsive: true,
        autoWidth:false
    });

});
</script>

<!-- <script>

$(document).on('change','.input-pph21',function(){

    let id = $(this).data('id');
    let pph21 = $(this).val();

    $.ajax({
        url: "{{ route('payroll-process.update-pph21') }}",
        type: "POST",
        data: {
            _token: "{{ csrf_token() }}",
            id: id,
            pph21: pph21
        },

        success:function(res){

            if(res.success){

                Swal.fire({
                    icon:'success',
                    title:'Success',
                    text:'PPh21 updated successfully',
                    timer:1200,
                    showConfirmButton:false
                });

                tableDetails.ajax.reload(null,false);

            }else{

                Swal.fire({
                    icon:'error',
                    title:'Error',
                    text:res.message
                });

            }

        },

        error:function(xhr){

            Swal.fire({
                icon:'error',
                title:'Error',
                text:'Failed update PPh21'
            });

        }

    });

});

</script> -->

<script>

$(document).on('click','.btn-update-pph',function(){

    let id = $(this).data('id');

    Swal.fire({
        title:'Update PPH21?',
        text:'PPH21 akan diambil dari employee contract sesuai payroll period',
        icon:'warning',
        showCancelButton:true,
        confirmButtonText:'Yes Update'
    }).then((result)=>{

        if(!result.isConfirmed){
            return;
        }

        Swal.fire({
            title:'Processing...',
            text:'Updating PPH21 payroll',
            allowOutsideClick:false,
            didOpen:()=>{
                Swal.showLoading();
            }
        });

        $.ajax({

            url:'/payroll-process/update-pph-by-contract/' + id,
            type:'POST',

            data:{
                _token:"{{ csrf_token() }}"
            },

            success:function(res){

                if(res.success){

                    Swal.fire({
                        icon:'success',
                        title:'Success',
                        text:res.message
                    }).then(()=>{
                        location.reload();
                    });

                }else{

                    Swal.fire({
                        icon:'error',
                        title:'Error',
                        text:res.message
                    });

                }

            },

            error:function(){

                Swal.fire({
                    icon:'error',
                    title:'Error',
                    text:'Failed update PPH21'
                });

            }

        });

    });

});

</script>

<script>

$(document).on('click','.btn-recreate',function(){

    let id = $(this).data('id');

    Swal.fire({
        title:'Recreate Payroll Document?',
        text:'Document payroll akan dibuat ulang',
        icon:'warning',
        showCancelButton:true,
        confirmButtonText:'Yes Recreate'
    }).then((result)=>{

        if(!result.isConfirmed){
            return;
        }

        Swal.fire({
            title:'Processing...',
            text:'Please wait, recreating the document is in progress',
            allowOutsideClick:false,
            didOpen:()=>{
                Swal.showLoading();
            }
        });

        $.ajax({

            url:'/payroll-process/recreate-document/' + id,
            type:'POST',

            data:{
                _token:"{{ csrf_token() }}"
            },

            success:function(res){

                if(res.success){

                    Swal.fire({
                        icon:'success',
                        title:'Success',
                        text:res.message
                    }).then(()=>{
                        location.reload();
                    });

                }else{

                    Swal.fire({
                        icon:'error',
                        title:'Error',
                        text:res.message
                    });

                }

            },

            error:function(){

                Swal.fire({
                    icon:'error',
                    title:'Error',
                    text:'Failed recreate document'
                });

            }

        });

    });

});

</script>

<script>
function formatRupiah(number){

    /*
    =====================================================
    SAFE NUMBER CONVERSION (ANTI RpNaN)
    =====================================================
    */

    if(
        number === null ||
        number === undefined ||
        number === '' ||
        number === false
    ){
        number = 0;
    }

    // jika string currency / text
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

function componentColor(componentType){
    return componentType === 'deduction' ? '#dc3545' : '#212529';
}

function formatRupiahColored(amount, componentType){
    let masked = formatRupiah(amount ?? 0);
    return `<span style="color:${componentColor(componentType)}">${masked}</span>`;
}
</script>
<script>

$(document).on('click','.btn-export',function(e){

    e.preventDefault();

    let url = $(this).data('url');
    let run_id = url.split('/').pop();

    // route progress laravel (AMAN PREFIX)
    let progressUrlTemplate = "{{ route('payroll.export.progress', ':id') }}";
    let progressUrl = progressUrlTemplate.replace(':id', run_id);

    Swal.fire({
        title: "Generate Export?",
        text: "The payroll export process will begin. This may take a few minutes depending on the amount of data. Do you want to proceed?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, generate!"
    }).then((result)=>{

        if(!result.isConfirmed) return;

        Swal.fire({
            title: "Export is being processed!",
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
                 |--------------------------------------------------------------------------
                 | START EXPORT (LOGIC TETAP)
                 |--------------------------------------------------------------------------
                 */

                $.get(url + '?refresh=1');


                /*
                 |--------------------------------------------------------------------------
                 | POLLING PROGRESS
                 |--------------------------------------------------------------------------
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
                                    title:'Export Finished',
                                    text:'Payroll Document Successfully Created!'
                                }).then(()=>{
                                    location.reload();
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
   const params = new URLSearchParams(window.location.search);
   
   if(params.get('refresh') == 1){
   
       setTimeout(function(){
   
           window.stop(); // stop loading
   
           window.location.href = window.location.pathname; // refresh halaman utama
   
       },1000);
   
   }
   
</script>
<script>
    
    $('.btn-delete-payroll').on('click', function () {

        let id = $(this).data('id');
        let period = $(this).data('period');

        let url = "/payroll-process/delete/" + id;

        $('#delete-form').attr('action', url);

        $("#modal-text-payroll_comp").text(
            'Apakah anda yakin ingin menghapus payroll periode ' + period + '?'
        );

    });


    let tableDetails = null;

    $('.btn-detail').on('click', function () {

    let id = $(this).data('id');
    let period = $(this).data('period');
    let url = '/payroll-process/details/' + id;

    // ubah title
    $('#detail-title').text('Data Payroll Details (' + period + ')');

    if ($('#payroll-detail-container').is(':visible') && $('#payroll-detail-container').data('id') == id) {
        $('#payroll-detail-container').hide();
        return;
    }

    $('#payroll-detail-container').show().data('id', id);

    if (tableDetails) {
        tableDetails.destroy();
    }

    let slipUrl = "{{ route('employee-payroll.view-slip', ['run_id' => ':run_id', 'npk' => ':npk']) }}";

    tableDetails = $('#table-details').DataTable({
        processing: true,
        responsive: true,
        ajax: url,

        createdRow: function (row, data) {

            const ket = (data.employment_status || '').toString();

            $(row).removeClass('table-warning table-danger');

            if (data.tkk !== null && data.tkk !== '') {

                if (ket === 'Mangkir') {
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

        columns: [
            { data: 'run_id' },
            { data: 'employee_npk' },
            { data: 'employee_name' },
            { data: 'dept' },

            { data:'components.basic_salary.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.basic_salary?.type);
                }
            },
            { data:'components.overtime_pay.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.overtime_pay?.type);
                }
            },
            { data:'components.special_overtime_pay.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.special_overtime_pay?.type);
                }
            },
            { data:'components.monthly_premi.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.monthly_premi?.type);
                }
            },
            { data:'components.long_service_allowance.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.long_service_allowance?.type);
                }
            },
            { data:'components.allowance.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.allowance?.type);
                }
            },

            { data:'components.sewing_insentif.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.sewing_insentif?.type);
                }
            },
            { data:'components.pad_insentif.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.pad_insentif?.type);
                }
            },
            { data:'components.cutting_insentif.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.cutting_insentif?.type);
                }
            },
            { data:'components.heat_insentif.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.heat_insentif?.type);
                }
            },
            { data:'components.sixs_insentif.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.sixs_insentif?.type);
                }
            },

            { data:'components.adjusment.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.adjusment?.type);
                }
            },

            { data:'components.bpjs_kesehatan.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.bpjs_kesehatan?.type);
                }
            },
            { data:'components.bpjs_ketenagakerjaan.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.bpjs_ketenagakerjaan?.type);
                }
            },
            { data:'components.pph_21.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.pph_21?.type);
                }
            },
            { data:'components.pph_21_deduction.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.pph_21_deduction?.type);
                }
            },
            { data:'components.absence_deduction.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.absence_deduction?.type);
                }
            },
            { data:'components.late_deduction.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.late_deduction?.type);
                }
            },

            { data:'components.work_leave_deduction.amount', defaultContent:0, render:function(data,type,row){
                    if(type !== 'display'){ return data ?? 0; }
                    return formatRupiahColored(data ?? 0, row.components?.work_leave_deduction?.type);
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
                defaultContent: '',
                render: function (data) {

                    switch ((data || '').toLowerCase()) {
                        case 'baru':
                            return `<span class="badge badge-primary">Baru</span>`;

                        case 'mangkir':
                            return `<span class="badge badge-danger">Mangkir</span>`;

                        case 'resign':
                            return `<span class="badge badge-warning">Resign</span>`;

                        default:
                            return `<span class="badge badge-success">Active</span>`;
                    }
                }
            },

            {
                data:null,
                orderable:false,
                searchable:false,
                render:function(data,type,row){

                    let viewUrl =
                        "/employee-payroll/show/"
                        + row.run_id + "/" + row.employee_npk;

                    let viewUrlAudit =
                        "/employee-payroll/show-audit/"
                        + row.run_id + "/" + row.employee_npk;

                    return `
                        <a href="${viewUrl}" 
                        class="btn btn-primary btn-circle btn-sm d-flex align-items-center justify-content-center mb-1"
                        title="View Slip">
                            <i class="fa fa-eye"></i>
                        </a>
                        <a href="${viewUrlAudit}" 
                        class="btn btn-warning btn-circle btn-sm d-flex align-items-center justify-content-center"
                        title="View Slip Audit">
                            <i class="fa fa-clipboard-check"></i>
                        </a>
                    `;
                }
            }
        ],

        /*
        =====================================================
        AUTO TOTAL RECALCULATE (SEARCH / FILTER / PAGING)
        =====================================================
        */
        footerCallback:function(row,data,start,end,display){

            let api = this.api();

            function intVal(i){

            if(i === null || i === undefined || i === '') return 0;

            if(typeof i === 'number') return i;

            if(typeof i === 'string'){

                // hapus Rp dan spasi
                i = i.replace(/[Rp\s]/g,'');

                // ubah format indonesia
                // 1.500.000,50 → 1500000.50
                i = i.replace(/\./g,'').replace(',', '.');

                let num = parseFloat(i);

                return isNaN(num) ? 0 : num;
            }

            return 0;
        }

            // kolom numeric (index sesuai table)
            let cols = [
                4,5,6,7,8,
                9,10,11,12,
                13,
                14,15,
                16,17,18,19,
                20,21,22,23
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
<style>
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

</style>
</html>