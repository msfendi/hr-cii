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

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

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
                                <button type="button" class="btn btn-primary btn-sm mb-2 mr-2" data-toggle="modal" data-target="#importModal">
                                    <i class="fas fa-file-excel"></i> Import Data
                                </button>

                                <form id="actionForm" method="GET" class="form-inline mr-2 mb-2">
                                    <label for="date" class="mr-2">Date:</label>
                                    <div class="input-group input-group-sm mr-2">
                                        <input type="date" class="form-control" name="date" id="date" value="{{ date('Y-m-d') }}" required>
                                    </div>

                                    <div class="input-group input-group-sm mr-2">
                                        <select class="form-control" name="type" id="department_filter" required>
                                            <option value="" disabled selected>-- Pilih Tipe --</option>
                                            <option value="all">All</option>
                                            <option value="sewing">Sewing</option>
                                            <option value="non_sewing">Non-Sewing</option>
                                            <option value="staff">Staff</option>
                                        </select>
                                    </div>

                                    {{-- <button type="submit" formaction="{{ route('overtime.downloadTemplate') }}" class="btn btn-success btn-sm mr-2" title="Template berdasarkan tanggal hari (untuk insert harian)">
                                        <i class="fas fa-file-excel"></i> Template (Harian)
                                    </button> --}}

                                    <button type="submit" formaction="{{ route('overtime.export-template') }}" class="btn btn-success btn-sm mr-2" title="Template berdasarkan bulan kalender (1 - 31)">
                                        <i class="fas fa-file-excel"></i> Template (Kalender)
                                    </button>

                                    <button type="submit" formaction="{{ route('overtime.export') }}" class="btn btn-info btn-sm">
                                        <i class="fas fa-file-excel"></i> Export Calendar
                                    </button>
                                </form>

                                {{-- <button type="button" class="btn btn-danger btn-sm mb-2 mr-2" id="btnDeleteAll">
                                    <i class="fas fa-trash"></i> Delete All by Date
                                </button> --}}
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
                                        <th>ACTION</th>
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

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Import Overtime Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="importForm" action="{{ route('overtime.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="dept_group">Tipe / Departemen</label>
                        <select class="form-control" name="dept_group" id="dept_group" required>
                            <option value="" disabled selected>-- Pilih Tipe --</option>
                            <option value="sewing">Sewing</option>
                            <option value="non_sewing">Non-Sewing</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="month">Bulan Format (Tahun-Bulan)</label>
                        <input type="month" class="form-control" name="month" id="month" required value="{{ date('Y-m') }}">
                    </div>
                    <div class="form-group">
                        <label for="file">Upload File</label>
                        <input type="file" class="form-control" name="file" id="file" accept=".xlsx" required style="padding-bottom: 2rem;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-excel"></i> Import Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editOvertimeModal" tabindex="-1" role="dialog" aria-labelledby="editOvertimeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editOvertimeModalLabel">Edit Overtime Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_npk">NPK</label>
                        <input type="text" class="form-control" id="edit_npk" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_nama">Nama Karyawan</label>
                        <input type="text" class="form-control" id="edit_nama" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_bagian">Bagian</label>
                        <input type="text" class="form-control" id="edit_bagian" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_tanggal">Tanggal</label>
                        <input type="text" class="form-control" id="edit_tanggal" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_jam">Jam Lembur</label>
                        <input type="number" step="0.5" class="form-control" id="edit_jam" name="jumlah_jam_lembur">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<script>
    $(document).ready(function() {
        var table = $('#dataTable').DataTable({
            processing: true,
            // serverSide: false, // Client-side processing
            ajax: {
                url: '{{ route("overtime.get-data") }}',
                type: 'GET',
                data: function(d) {
                    d.date = $('#date').val();
                    d.department_filter = $('#department_filter').val();
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'NPK', name: 'NPK' },
                { data: 'NAMA_KARYAWAN', name: 'NAMA_KARYAWAN' },
                { data: 'BAGIAN', name: 'BAGIAN' },
                { data: 'OVERTIME_DATE', name: 'OVERTIME_DATE' },
                { data: 'JUMLAH_JAM_LEMBUR', name: 'JUMLAH_JAM_LEMBUR' },
                { 
                    data: 'action', 
                    name: 'action', 
                    orderable: false, 
                    searchable: false,
                    render: function(data, type, row) {
                        return '<button class="btn btn-sm btn-primary btn-edit mr-1" data-id="'+row.id+'" data-npk="'+row.NPK+'" data-nama="'+row.NAMA_KARYAWAN+'" data-bagian="'+row.BAGIAN+'" data-tanggal="'+row.OVERTIME_DATE+'" data-jam="'+row.JUMLAH_JAM_LEMBUR+'"><i class="fas fa-edit"></i> Edit</button>' +
                               '<button class="btn btn-sm btn-danger btn-delete" data-id="'+row.id+'"><i class="fas fa-trash"></i> Delete</button>';
                    }
                }
            ],
            order: [[0, 'desc']]
        });

        $('#date, #department_filter').on('change', function() {
            table.ajax.reload();
        });

        // Import Form Submit with AJAX & SWAL
        $('#importForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);

            Swal.fire({
                title: 'Importing Data...',
                text: 'Please wait while we process the file.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        table.ajax.reload(); // Reload table data
                        $('#file').val('');  // Clear the file input
                        $('#dept_group').val(''); // Clear the select input
                        $('#importModal').modal('hide'); // Hide modal
                    });
                },
                error: function(xhr) {
                    var errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during import.';
                    Swal.fire({
                        icon: 'error',
                        title: 'Import Failed',
                        text: errorMsg,
                        confirmButtonText: 'Try Again'
                    });
                }
            });
        });

        // Action Form Submit (Download Template or Export Calendar)
        $('#actionForm button[type="submit"]').on('click', function() {
            var actionUrl = $(this).attr('formaction');
            if (actionUrl && actionUrl.includes('downloadTemplate')) {
                Swal.fire({
                    title: 'Downloading Template...',
                    text: 'Preparing your Excel template, please wait.',
                    icon: 'info',
                    timer: 5000,
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            } else if (actionUrl && actionUrl.includes('export')) {
                Swal.fire({
                    title: 'Generating Export...',
                    text: 'Please wait while we prepare your calendar file.',
                    icon: 'info',
                    timer: 15000,
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
        });

        // Edit Button Click
        $('#dataTable tbody').on('click', '.btn-edit', function() {
            var data = $(this).data();
            $('#edit_id').val(data.id);
            $('#edit_npk').val(data.npk);
            $('#edit_nama').val(data.nama);
            $('#edit_bagian').val(data.bagian);
            $('#edit_tanggal').val(data.tanggal);
            $('#edit_jam').val(data.jam);
            $('#editOvertimeModal').modal('show');
        });

        // Delete Button Click
        $('#dataTable tbody').on('click', '.btn-delete', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = '{{ route("overtime.destroy", ":id") }}';
                    url = url.replace(':id', id);

                    $.ajax({
                        url: url,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            table.ajax.reload(null, false);
                            Swal.fire(
                                'Deleted!',
                                response.message,
                                'success'
                            );
                        },
                        error: function(xhr) {
                            Swal.fire(
                                'Error!',
                                'Failed to delete data.',
                                'error'
                            );
                        }
                    });
                }
            });
        });

        // Delete All Button Click
        $('#btnDeleteAll').on('click', function() {
            var date = $('#date').val();
            Swal.fire({
                title: 'Are you sure?',
                text: "Delete all overtime data for date: " + date + "?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete all!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route("overtime.destroyAll") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            date: date
                        },
                        success: function(response) {
                            table.ajax.reload(null, false);
                            Swal.fire(
                                'Deleted!',
                                response.message,
                                'success'
                            );
                        },
                        error: function(xhr) {
                            Swal.fire(
                                'Error!',
                                'Failed to delete data.',
                                'error'
                            );
                        }
                    });
                }
            });
        });

        // Update Form Submit
        $('#editForm').on('submit', function(e) {
            e.preventDefault();
            var id = $('#edit_id').val();
            var url = '{{ route("overtime.update", ":id") }}';
            url = url.replace(':id', id);

            $.ajax({
                url: url,
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('#editOvertimeModal').modal('hide');
                    table.ajax.reload(null, false);
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON.message || 'An error occurred'
                    });
                }
            });
        });
    });
</script>
</html>