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
                                <form action="{{ route('overtime.import') }}" method="POST" enctype="multipart/form-data" class="form-inline mr-2 mb-2">
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

                                <form action="{{ route('overtime.downloadTemplate') }}" method="GET" class="form-inline mr-2 mb-2">
                                    <label for="date" class="mr-2">Date:</label>
                                    <div class="input-group input-group-sm mr-2">
                                        <input type="date" class="form-control" name="date" id="date" value="{{ date('Y-m-d') }}" required>
                                    </div>

                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-file-excel"></i> Download Template
                                    </button>
                                </form>

                                {{-- export --}}
                                <form action="{{ route('overtime.export') }}" method="GET" class="form-inline mb-2 mr-2" id="exportForm">
                                    <input type="hidden" name="date" id="exportDate">
                                    <button type="submit" class="btn btn-info btn-sm">
                                        <i class="fas fa-file-excel"></i> Export Calendar
                                    </button>
                                </form>

                                <button type="button" class="btn btn-danger btn-sm mb-2 mr-2" id="btnDeleteAll">
                                    <i class="fas fa-trash"></i> Delete All by Date
                                </button>
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

        // Sync date ke export form sebelum submit
        $('#exportForm').on('submit', function() {
            $('#exportDate').val($('#date').val());
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