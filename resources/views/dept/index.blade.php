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
                    <h1 class="h3 mb-0 text-gray-800">Departement List</h1>
                    <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"
                        id="btnTambahDept" data-toggle="modal" data-target="#modalDept">
                        <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Departemen
                    </button>
                </div>

                <!-- DataTable Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Departement List</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="deptTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>ID</th>
                                        <th>Departemen</th>
                                        <th>Is Sewing</th>
                                        <th>Section</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- End of Main Content -->

@include('layout.footer')

</div>
<!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

<!-- ===================== ADD / EDIT MODAL ===================== -->
<div class="modal fade" id="modalDept" tabindex="-1" role="dialog" aria-labelledby="modalDeptLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDeptLabel">Tambah Departemen</h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formDept">
                @csrf
                <input type="hidden" id="dept_id" name="dept_id">
                <div class="modal-body">

                    <div class="form-group">
                        <label for="departement">Nama Departemen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="departement" name="departement"
                            placeholder="Nama Departemen" required>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_sewing" name="is_sewing" value="1">
                            <label class="custom-control-label" for="is_sewing">Is Sewing</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="section">Section</label>
                        <input type="text" class="form-control" id="section" name="section"
                            placeholder="CHUTEX" value="CHUTEX">
                        <small class="form-text text-muted">Default: CHUTEX</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSimpanDept">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- ===================== END MODAL ===================== -->

<!-- ===================== DELETE CONFIRM MODAL ===================== -->
<div class="modal fade" id="modalDeleteDept" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Hapus Departemen</h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Apakah anda yakin ingin menghapus departemen <strong id="deleteDeptName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                    <i class="fas fa-trash mr-1"></i> Hapus
                </button>
            </div>
        </div>
    </div>
</div>
<!-- ===================== END DELETE MODAL ===================== -->

</body>

<!-- DataTables -->
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {

    // ── DataTable ──────────────────────────────────────────────────
    var table = $('#deptTable').DataTable({
        processing: true,
        ajax: {
            url: '{{ route("dept.get-data") }}',
            dataSrc: 'data'
        },
        columns: [
            {
                data: null,
                render: function (data, type, row, meta) { return meta.row + 1; },
                orderable: false,
                searchable: false
            },
            { data: 'ID_DEPT' },
            { data: 'DEPARTEMENT' },
            {
                data: 'IS_SEWING',
                render: function (val) {
                    return val == 0
                        ? '<span class="badge badge-success">Ya</span>'
                        : '<span class="badge badge-secondary">Tidak</span>';
                }
            },
            { data: 'SECTION' },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data) {
                    return `
                        <button class="btn btn-warning btn-circle btn-sm btn-edit mr-1"
                            data-id="${data.ID_DEPT}"
                            data-departement="${data.DEPARTEMENT}"
                            data-issewing="${data.IS_SEWING}"
                            data-section="${data.SECTION}"
                            title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-circle btn-sm btn-delete"
                            data-id="${data.ID_DEPT}"
                            data-name="${data.DEPARTEMENT}"
                            title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>`;
                }
            }
        ]
    });

    // ── Reset modal saat buka utk tambah ───────────────────────────
    $('#btnTambahDept').on('click', function () {
        $('#modalDeptLabel').text('Tambah Departemen');
        $('#formDept')[0].reset();
        $('#dept_id').val('');
        $('#section').val('CHUTEX');
        $('#is_sewing').prop('checked', false);
    });

    // ── Edit ───────────────────────────────────────────────────────
    $('#deptTable').on('click', '.btn-edit', function () {
        $('#modalDeptLabel').text('Edit Departemen');
        $('#dept_id').val($(this).data('id'));
        $('#departement').val($(this).data('departement'));
        $('#is_sewing').prop('checked', $(this).data('issewing') == 0);
        $('#section').val($(this).data('section') || 'CHUTEX');
        $('#modalDept').modal('show');
    });

    // ── Store / Update ─────────────────────────────────────────────
    $('#formDept').on('submit', function (e) {
        e.preventDefault();

        var id  = $('#dept_id').val();
        var url = id
            ? '{{ url("/dept/update") }}/' + id
            : '{{ route("dept.store") }}';

        $.ajax({
            url: url,
            type: 'POST',
            data: $(this).serialize(),
            success: function (res) {
                if (res.status === 'success') {
                    $('#modalDept').modal('hide');
                    table.ajax.reload(null, false);
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                Swal.fire({ icon: 'error', title: 'Gagal!', text: msg });
            }
        });
    });

    // ── Delete confirm ─────────────────────────────────────────────
    var deleteId = null;

    $('#deptTable').on('click', '.btn-delete', function () {
        deleteId = $(this).data('id');
        $('#deleteDeptName').text($(this).data('name'));
        $('#modalDeleteDept').modal('show');
    });

    $('#btnConfirmDelete').on('click', function () {
        if (!deleteId) return;

        $.ajax({
            url: '{{ url("/dept/destroy") }}/' + deleteId,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                $('#modalDeleteDept').modal('hide');
                deleteId = null;
                if (res.status === 'success') {
                    table.ajax.reload(null, false);
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                }
            },
            error: function (xhr) {
                $('#modalDeleteDept').modal('hide');
                deleteId = null;
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                Swal.fire({ icon: 'error', title: 'Gagal!', text: msg });
            }
        });
    });

});
</script>

</html>
