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
                        <h1 class="h3 mb-0 text-gray-800">Master Posisi Recruitment</h1>
                    </div>

                    <div class="card shadow mb-2">
                        <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-list mr-1"></i> Daftar Posisi
                            </h6>
                            <button class="btn btn-sm btn-primary" id="btnAdd">
                                <i class="fas fa-plus mr-1"></i> Tambah Posisi
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-striped" id="dataTable" width="100%"
                                    cellspacing="0">
                                    <thead class="bg-primary text-white">
                                        <tr>
                                            <th class="text-center" style="width:50px;">No</th>
                                            <th>Posisi</th>
                                            <th>Department</th>
                                            <th class="text-center" style="width:100px;">Status</th>
                                            <th class="text-center" style="width:150px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            @include('layout.footer')
        </div>
    </div>

    <!-- Modal Form -->
    <div class="modal fade" id="formModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="positionForm">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" id="positionId">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalTitle">Tambah Posisi</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Posisi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="position" name="position" required>
                        </div>
                        <div class="form-group">
                            <label>Department <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dept" name="dept" required>
                        </div>
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="is_aktif" name="is_aktif" required>
                                <option value="true">Aktif</option>
                                <option value="false">Tidak Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="btnSave">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function () {
        var table = $('#dataTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("recruitment-position.get-data") }}',
            order: [], // Disable default sorting on the first column (DT_RowIndex) to prevent "order by invalid" error
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', className: 'text-center', orderable: false, searchable: false },
                { data: 'position', name: 'position' },
                { data: 'dept', name: 'dept' },
                { data: 'is_aktif_badge', name: 'is_aktif_badge', className: 'text-center', orderable: false, searchable: false },
                { data: 'action', name: 'action', className: 'text-center', orderable: false, searchable: false }
            ]
        });

        // Open Add Modal
        $('#btnAdd').on('click', function () {
            $('#positionForm')[0].reset();
            $('#formMethod').val('POST');
            $('#positionId').val('');
            $('#modalTitle').text('Tambah Posisi');
            $('#formModal').modal('show');
        });

        // Open Edit Modal
        $('body').on('click', '.btn-edit', function () {
            var id = $(this).data('id');
            var position = $(this).data('position');
            var dept = $(this).data('dept');
            var is_aktif = $(this).data('is_aktif');

            $('#positionForm')[0].reset();
            $('#formMethod').val('PUT');
            $('#positionId').val(id);
            $('#position').val(position);
            $('#dept').val(dept);
            $('#is_aktif').val(is_aktif);

            $('#modalTitle').text('Edit Posisi');
            $('#formModal').modal('show');
        });

        // Save Data
        $('#positionForm').on('submit', function (e) {
            e.preventDefault();
            var id = $('#positionId').val();
            var method = $('#formMethod').val();
            var url = method === 'POST' ? '{{ route("recruitment-position.store") }}' : '/recruitment-position/update/' + id;

            var formData = $(this).serialize();
            $('#btnSave').prop('disabled', true).text('Menyimpan...');

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                success: function (res) {
                    if (res.status === 'success') {
                        $('#formModal').modal('hide');
                        Swal.fire('Berhasil!', res.message, 'success');
                        table.ajax.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function (xhr) {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                    Swal.fire('Error', msg, 'error');
                },
                complete: function () {
                    $('#btnSave').prop('disabled', false).text('Simpan');
                }
            });
        });

        // Delete Data
        $('body').on('click', '.btn-delete', function () {
            var id = $(this).data('id');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/recruitment-position/destroy/' + id,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (res) {
                            if (res.status === 'success') {
                                Swal.fire('Terhapus!', res.message, 'success');
                                table.ajax.reload();
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        },
                        error: function (xhr) {
                            var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                            Swal.fire('Error', msg, 'error');
                        }
                    });
                }
            });
        });
    });
</script>

</html>