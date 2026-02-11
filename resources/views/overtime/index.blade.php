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
                    <h1 class="h3 mb-0 text-gray-800">Overtime Management</h1>
                </div>
                
                <!-- Add Button in Card Header -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Overtime Data</h6>
                        <div>
                            <div class="d-flex align-items-center flex-wrap">
                                <form action="{{ route('overtime.downloadTemplate') }}" method="GET" class="form-inline mr-3 mb-2">
                                    {{-- <label for="department_filter" class="mr-2">Dept:</label>
                                    <div class="input-group input-group-sm mr-2">
                                        <select class="form-control" name="department" id="department_filter">
                                            <option value="">All Departments</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->BAGIAN }}">{{ $dept->BAGIAN }}</option>
                                            @endforeach
                                        </select>
                                    </div> --}}

                                    <label for="date" class="mr-2">Date:</label>
                                    <div class="input-group input-group-sm mr-2">
                                        <input type="date" class="form-control" name="date" id="date" value="{{ date('Y-m-d') }}" required>
                                    </div>

                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-excel"></i> Download Template
                                    </button>
                                </form>

                                <form action="{{ route('overtime.import') }}" method="POST" enctype="multipart/form-data" class="form-inline mb-2">
                                    @csrf
                                    <label for="file" class="mr-2">Upload File:</label>
                                    <div class="input-group input-group-sm">
                                        <input type="file" class="form-control" name="file" id="file" accept=".xlsx" required style="padding-bottom: 2rem;">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-file-excel"></i> Import Data
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>NPK</th>
                                        <th>NAMA</th>
                                        <th>BAGIAN</th>
                                        <th>TANGGAL</th>
                                        <th>JAM LEMBUR</th>
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
@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            processing: true,
            // serverSide: false, // Client-side processing
            ajax: {
                url: '{{ route("overtime.get-data") }}',
                type: 'GET'
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'NPK', name: 'NPK' },
                { data: 'NAMA_KARYAWAN', name: 'NAMA_KARYAWAN' },
                { data: 'BAGIAN', name: 'BAGIAN' },
                { data: 'OVERTIME_DATE', name: 'OVERTIME_DATE' },
                { data: 'JUMLAH_JAM_LEMBUR', name: 'JUMLAH_JAM_LEMBUR' },
            ],
            order: [[0, 'desc']]
        });

        $('#date, #department_filter').on('change', function() {
            table.draw();
        });
    });
</script>
</html>