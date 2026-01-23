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
                    <h1 class="h3 mb-0 text-gray-800">Attendance List</h1>
                    <div>
                        <a class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#importModal"><i
                            class="fas fa-upload fa-sm text-white-50"></i> Upload Data</a>
                        <a class="d-none d-sm-inline-block btn btn-sm btn-danger shadow-sm" data-toggle="modal" data-target="#deleteModal"><i
                            class="fas fa-trash fa-sm text-white-50"></i> Delete All Data</a>
                        </form>
                    </div>
                </div>
                
                <!-- DataTales Example -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Attendance Data</h6>
                        <form method="GET" id="form-void">
                                <select name="void" id="void" class="form-control" onchange="document.getElementById('form-void').submit()" style="width: 300px;">
                                    <option disabled selected hidden>Select Status</option>
                                    <option value="false" {{ app('request')->input('void') == 'false'  ? 'selected' : ''}}>Active</option>
                                    <option value="true" {{ app('request')->input('void') == 'true'  ? 'selected' : ''}}>Void</option>
                                </select>
                        </form>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('attendance.export') }}" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-xl-3 col-md-6">
                                    <div>
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <label>From Date :</label>
                                        <input class="date form-control" type="date" id="fromdate" name="fromdate" value="">
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div>
                                        <label>To Date :</label>
                                        <input class="date form-control" type="date" id="todate" name="todate" value="">
                                    </div>
                                </div>
                                <div class="col-xl-2 col-md-6">
                                    <div>
                                        <label>Department</label>
                                        <select class="department" id="department[]" name="department[]" multiple="multiple">
                                            {{-- <option></option> --}}
                                            @foreach($employeeGroupChutex as $dept)
                                                <option value="{{ $dept->KODE_BAGIAN }}">{{ $dept->SUBDIVISI }}</option>
                                            @endforeach
                                        </select>
                                        <input id="select-all" type="checkbox" >Select All
                                    </div>
                                </div>

                                <div class="col-xl-2 w-full col-md-6">
                                    <div>
                                        <label>Holiday Date</label>
                                        <input class="form-control" type="text" id="holiday_date" name="holiday_date">
                                    </div>
                                </div>
                                <div class="col-xl-2 w-full col-md-6 mt-2">
                                    <br>
                                    <div class="row">
                                        <button id='filter-data' type="button" class="btn btn-md btn-primary mr-2">Filter</button>
                                        <button id='export-excel' type="button" class="btn btn-md btn-warning">Export Excel</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>NPK</th>
                                        <th>Nama Karyawan</th>
                                        <th>Tanggal</th>
                                        <th>Subdivisi</th>
                                        <th>Jam Pagi</th>
                                        <th>Jam Siang</th>
                                        <th>Jam Malam</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

        <!-- Modal -->
        <div class="modal fade" id="voidModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="attendance" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="void-title" class="modal-title" id="exampleModalLabel">Void Record</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body"><p id="modal-text-record-void"></p></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <a id="btn-confirm-void" href=""><button class="btn btn-danger" type="button">Confirm</button></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="restoreModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="attendance" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="restore-title" class="modal-title" id="exampleModalLabel">Restore Record</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body"><p id="modal-text-record-restore"></p></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <a id="btn-confirm-restore" href=""><button class="btn btn-success" type="button">Confirm</button></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="modal-title" class="modal-title" id="exampleModalLabel">Import Attendance Data</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <form action="{{ route('attendance.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>PILIH FILE</label>
                                    <input type="file" name="file" accept=".xls,.xlsx">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                <button type="submit" id="submit-import" class="btn btn-success">Import</button>
                            </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="modal-title" class="modal-title" id="exampleModalLabel">Hapus Semua Data Kehadiran</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <form action="{{ route('attendance.deleteAll') }}" method="POST">
                        @csrf
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                                <button type="submit" class="btn btn-danger">Hapus</button>
                            </div>
                    </form>
                </div>
            </div>
        </div>


@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script> --}}
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<script>
    var tableAttendance = $('#dataTable').DataTable({
    destroy: true,
    responsive: true,
    ajax: '{{ route("attendance.showAttendance") }}',
    columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            { data: 'NPK', name: 'NPK', orderable: false },
            { data: 'NAMA_KARYAWAN', name: 'NAMA_KARYAWAN', orderable: false },
            { data: 'TANGGAL', name: 'TANGGAL', orderable: false },
            { data: 'SUBDIVISI', name: 'SUBDIVISI', orderable: false },
            { data: 'JAM_PAGI', name: 'JAM_PAGI', orderable: false },
            { data: 'JAM_SIANG', name: 'JAM_SIANG', orderable: false },
            { data: 'JAM_MALAM', name: 'JAM_MALAM', orderable: false },
            { data: 'STATUS', name: 'STATUS', orderable: false },
        ],
    });
    // setInterval( function () {
    //     tableCanteen.ajax.reload();
    // }, 1000);
</script>

<script type="text/javascript">
    $('.btn-delete-record').on('click', function () {
            $('#btn-confirm').attr('href', $(this).data('delete-link'));
            $("#modal-text-record").text('Apakah anda yakin ingin menghapus Inventory QR ' + $(this).data('delete-name') + '?');
    });
    $('.btn-void-record').on('click', function () {
            $('#btn-confirm-void').attr('href', $(this).data('void-link'));
            $("#modal-text-record-void").text('Apakah anda yakin ingin menghapus Inventory QR ' + $(this).data('void-name') + '?');
    });
    $('.btn-restore-record').on('click', function () {
            $('#btn-confirm-restore').attr('href', $(this).data('restore-link'));
            $("#modal-text-record-restore").text('Apakah anda yakin ingin mengembalikan Inventory QR ' + $(this).data('restore-name') + '?');
    });

    $(document).ready(function() {
        $('.department').select2();
        $('#holiday_date').datepicker({
            multidate: true
        });

        $("#select-all").click(function(){
        if($("#select-all").is(':checked')){
            $(".department > option").prop("selected", "selected");
            $(".department").trigger("change");
        } else {
            $(".department > option").removeAttr("selected");
            $(".department").trigger("change");
        }
    });
    });

    $('#filter-data').on('click', function () {
        var fromdate = $('#fromdate').val();
        var todate = $('#todate').val();
        var department = $('#department\\[\\]').val(); 
        var holiday_date = $('#holiday_date').val();
        console.log(holiday_date);

        if(fromdate && todate && department) {
            $.ajax({
            url: "{{ route('attendance.export') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                fromdate: fromdate,
                todate: todate,
                department: department,
                holiday_date: holiday_date
            },
            success: function(response) {
                console.log(response);
                
                var params = $.param({
                fromdate: fromdate,
                todate: todate,
                department: department,
                days: response.days // department is array, so Laravel will receive as department[]
            });
                window.open('/attendance/report?' + params, '_blank');

            },
            error: function(xhr, status, error) {
                console.log('errorrr');
                
                console.error("Error:", error);
            }
        });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'All fields are required',
                showConfirmButton: false,
                timer: 1500
            });
        }
    });

    $('#export-excel').on('click', function () {
        var fromdate = $('#fromdate').val();
        var todate = $('#todate').val();
        var department = $('#department\\[\\]').val(); 
        var holiday_date = $('#holiday_date').val();
        console.log(holiday_date);

        var dates = holiday_date.split(',');
        var days = dates.map(function(dateStr) {
            var parts = dateStr.trim().split('/');
            return parts[1];
        });

        console.log(days);
        

        var params = $.param({
            fromdate: fromdate,
            todate: todate,
            department: department,
            days: days
        });

        window.open('/attendance/export_view?' + params, '_blank');
    });

    $("#submit-import").click(function() {
        $(this).hide();
        Swal.fire({
            title: "Process",
            html: "Importing Data Attendance.. Please Wait!!",
            timerProgressBar: true,
            didOpen: () => {
                Swal.showLoading();
            },
        })
    });
</script>
</html>