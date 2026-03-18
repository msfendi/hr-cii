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
                    <a href="{{ route('payroll-process.generate') }}" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm"><i
                        class="fas fa-plus fa-sm text-white-50"></i> Generate Payroll</a>
                    <a href="{{ route('payroll-periods.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
                        class="fas fa-plus fa-sm text-white-50"></i> Create Payroll Period</a>
                    </div>
                </div>
                
                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Data Payroll Process</h6>
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
                                        <th>Downloads</th>
                                        <th>Approval Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($periods as $period)
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
                                            <span class="badge badge-success">
                                            Finished
                                            </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            {{-- DOWNLOAD EXCEL --}}
                                            @if($period->export_status == 'finished' && $period->file_excel)
                                                <a class="btn btn-success btn-circle btn-sm"
                                                    href="{{ asset('storage/'.$period->file_excel) }}"
                                                    title="Download Excel"
                                                    target="_blank">
                                                    <i class="fas fa-file-excel"></i>
                                                </a>
                                            @endif

                                            {{-- DOWNLOAD PDF --}}
                                            @if($period->export_status == 'finished' && $period->file_pdf)
                                                <a class="btn btn-danger btn-circle btn-sm"
                                                    href="{{ asset('storage/'.$period->file_pdf) }}"
                                                    title="Download PDF"
                                                    target="_blank">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            @endif

                                            {{-- DOWNLOAD BANK (HANYA JIKA APPROVAL FINISH) --}}
                                            @if($period->approve_status == 'finish' && $period->export_status == 'finished')
                                                <a class="btn btn-primary btn-circle btn-sm"
                                                    href="{{ asset('storage/'.$period->file_bank) }}"
                                                    title="Download Bank">
                                                    <i class="fas fa-university"></i>
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

                                            <button class="btn btn-info btn-circle btn-sm btn-detail"
                                                data-id="{{ $period->id }}"
                                                data-period="{{ $period->period_name }}">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            @if(!$period->export_status)
                                                <a class="btn btn-warning btn-circle btn-sm btn-export"
                                                    href="#"
                                                    data-url="{{ route('payroll.export.export', $period->id) }}"
                                                    title="Generate Export">
                                                    <i class="fas fa-database"></i>
                                                </a>
                                            @endif
                                            <a class="btn btn-danger btn-circle btn-sm btn-delete-payroll"
                                            data-id="{{ $period->id }}"
                                            data-period="{{ $period->period_name }}"
                                            data-toggle="modal"
                                            data-target="#deleteModal">
                                            <i class="fas fa-trash"></i>
                                            </a>
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

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="table-details">
                                <thead>
                                    <tr>
                                        <th>Run ID</th>
                                        <th>NPK</th>
                                        <th>Name</th>

                                        <th>Basic Salary</th>
                                        <th>Overtime</th>
                                        <th>Special OT</th>
                                        <th>Monthly Premi</th>
                                        <th>Long Service</th>
                                        <th>Allowance</th>
                                        
                                        <th>Sewing Insentif</th>
                                        <th>Pad Print Insentif</th>
                                        <th>Cutting Insentif</th>

                                        <th>BPJS Kes</th>
                                        <th>BPJS TK</th>

                                        <th>PPh21</th>
                                        <th>PPh21 Deduction</th>
                                        <th>Absence</th>

                                        <th>Total Salary</th>
                                        <th>Slip</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
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

                        <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <button class="btn btn-danger" type="submit">Delete</button>
                        </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>

@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>

<script>

    function formatRupiah(number){
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(number);
    }
   $(document).on('click','.btn-export',function(e){
   
       e.preventDefault();
   
       let url = $(this).data('url');
   
       Swal.fire({
           title: "Generate Export?",
           text: "Proses export payroll akan dimulai.",
           icon: "warning",
           showCancelButton: true,
           confirmButtonColor: "#3085d6",
           cancelButtonColor: "#d33",
           confirmButtonText: "Yes, generate!"
       }).then((result) => {
   
           if (result.isConfirmed) {
   
               Swal.fire({
                   title: "Export sedang diproses...",
                   text: "Mohon tunggu...",
                   allowOutsideClick: false,
                   didOpen: () => {
                       Swal.showLoading()
   
                       setTimeout(function(){
   
                           window.location.href = url + '?refresh=1';
   
                       },500)
   
                   }
               });
   
           }
   
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

    let slipUrl = "{{ route('view-slip', ['run_id' => ':run_id', 'npk' => ':npk']) }}";

    tableDetails = $('#table-details').DataTable({
        processing: true,
        responsive: true,
        ajax: url,
        columns: [
            { data: 'run_id' },
            { data: 'employee_npk' },
            { data: 'employee_name' },

            { 
                data: 'basic_salary',
                render: data => formatRupiah(data)
            },
            { 
                data: 'overtime_pay',
                render: data => formatRupiah(data)
            },
            { 
                data: 'special_overtime_pay',
                render: data => formatRupiah(data)
            },
            { 
                data: 'monthly_premi',
                render: data => formatRupiah(data)
            },
            { 
                data: 'long_service_allowance',
                render: data => formatRupiah(data)
            },
            { 
                data: 'allowance',
                render: data => formatRupiah(data)
            },
            { 
                data: 'sewing_insentif',
                render: data => formatRupiah(data)
            },
            { 
                data: 'pad_insentif',
                render: data => formatRupiah(data)
            },
            { 
                data: 'cutting_insentif',
                render: data => formatRupiah(data)
            },
            { 
                data: 'bpjs_kesehatan',
                render: data => formatRupiah(data)
            },
            { 
                data: 'bpjs_ketenagakerjaan',
                render: data => formatRupiah(data)
            },
            { 
                data: 'pph_21',
                render: data => formatRupiah(data)
            },
            { 
                data: 'pph_21_deduction',
                render: data => formatRupiah(data)
            },
            { 
                data: 'absence_deduction',
                render: data => formatRupiah(data)
            },
            { 
                data: 'total_salary',
                render: data => formatRupiah(data)
            },

            {
            data: null,
            orderable: false,
            searchable: false,
            render: function(data, type, row){

                let pdfUrl = slipUrl
                    .replace(':run_id', row.run_id)
                    .replace(':npk', row.employee_npk);

                let viewUrl = "/employee-payroll/show/" + row.run_id + "/" + row.employee_npk;

                return `
                     <a href="${viewUrl}" 
                     class="btn btn-primary btn-circle btn-sm d-flex align-items-center justify-content-center"
                     title="View Slip">
                         <i class="fa fa-eye"></i>
                     </a>
                 `;

                // return `
                //     <a href="${pdfUrl}" 
                //     class="btn btn-success btn-circle btn-sm d-flex align-items-center justify-content-center"
                //     title="Download Slip">
                //         <i class="fa fa-file-pdf"></i>
                //     </a>

                //     <a href="${viewUrl}" 
                //     class="btn btn-primary btn-circle btn-sm d-flex align-items-center justify-content-center"
                //     title="View Slip">
                //         <i class="fa fa-eye"></i>
                //     </a>
                // `;
            }
        }
        ]
    });

});
</script>
</html>