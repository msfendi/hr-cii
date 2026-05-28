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
                    <h1 class="h3 mb-0 text-gray-800">Parent Department Management</h1>
                    <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"
                        id="btnTambahParent" data-toggle="modal" data-target="#modalParent">
                        <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Parent Dept
                    </button>
                </div>

                <!-- DataTable Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Parent Department List</h6>
                        <div>
                            <a href="{{ route('parent-dept.export') }}" class="btn btn-warning btn-sm mr-2">
                                <i class="fas fa-file-export"></i> Export Data
                            </a>
                            <a href="{{ route('parent-dept.template') }}" class="btn btn-info btn-sm mr-2">
                                <i class="fas fa-file-download"></i> Template Excel
                            </a>
                            <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#importModalParent">
                                <i class="fas fa-file-import"></i> Import Excel
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="parentTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="10%">ID</th>
                                        <th>Nama Parent Dept</th>
                                        <th width="20%">Jumlah Dept Terhubung</th>
                                        <th width="15%">Aksi</th>
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

<!-- ===================== IMPORT MODAL ===================== -->
<div class="modal fade" id="importModalParent" tabindex="-1" role="dialog" aria-labelledby="importModalParentLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalParentLabel">Import Parent Dept Data</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('parent-dept.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="file">Choose Excel File</label>
                        <input type="file" class="form-control-file" id="file" name="file" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- ===================== END IMPORT MODAL ===================== -->

<!-- ===================== ADD / EDIT MODAL ===================== -->
<div class="modal fade" id="modalParent" tabindex="-1" role="dialog" aria-labelledby="modalParentLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalParentLabel">Tambah Parent Dept</h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formParent">
                @csrf
                <input type="hidden" id="parent_id" name="parent_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="parent_dept_name">Nama Parent Departemen <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="parent_dept_name" name="parent_dept_name"
                            placeholder="Contoh: HRD, PRODUKSI, FINANCE" required maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSimpanParent">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- ===================== END MODAL ===================== -->

<!-- ===================== DELETE CONFIRM MODAL ===================== -->
<div class="modal fade" id="modalDeleteParent" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Hapus Parent Dept</h5>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Apakah anda yakin ingin menghapus <strong id="deleteParentName"></strong>?</p>
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
    var table = $('#parentTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("parent-dept.get-data") }}',
        },
        columns: [
            {
                data: null,
                render: function (data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; },
                orderable: false,
                searchable: false
            },
            { data: 'id' },
            { data: 'parent_dept_name' },
            { 
                data: 'depts_count',
                render: function(data) {
                    return data > 0 ? `<span class="badge badge-info">${data} Depts</span>` : `<span class="badge badge-secondary">0 Depts</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data) {
                    return `
                        <button class="btn btn-warning btn-circle btn-sm btn-edit mr-1"
                            data-id="${data.id}"
                            data-name="${data.parent_dept_name}"
                            title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-circle btn-sm btn-delete"
                            data-id="${data.id}"
                            data-name="${data.parent_dept_name}"
                            title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>`;
                }
            }
        ]
    });

    // ── Reset modal saat buka utk tambah ───────────────────────────
    $('#btnTambahParent').on('click', function () {
        $('#modalParentLabel').text('Tambah Parent Dept');
        $('#formParent')[0].reset();
        $('#parent_id').val('');
    });

    // ── Edit ───────────────────────────────────────────────────────
    $('#parentTable').on('click', '.btn-edit', function () {
        $('#modalParentLabel').text('Edit Parent Dept');
        $('#parent_id').val($(this).data('id'));
        $('#parent_dept_name').val($(this).data('name'));
        $('#modalParent').modal('show');
    });

    // ── Store / Update ─────────────────────────────────────────────
    $('#formParent').on('submit', function (e) {
        e.preventDefault();

        var id  = $('#parent_id').val();
        var url = id
            ? '{{ url("/parent-dept/update") }}/' + id
            : '{{ route("parent-dept.store") }}';

        $.ajax({
            url: url,
            type: 'POST',
            data: $(this).serialize(),
            success: function (res) {
                if (res.status === 'success') {
                    $('#modalParent').modal('hide');
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

    $('#parentTable').on('click', '.btn-delete', function () {
        deleteId = $(this).data('id');
        $('#deleteParentName').text($(this).data('name'));
        $('#modalDeleteParent').modal('show');
    });

    $('#btnConfirmDelete').on('click', function () {
        if (!deleteId) return;

        $.ajax({
            url: '{{ url("/parent-dept/destroy") }}/' + deleteId,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                $('#modalDeleteParent').modal('hide');
                deleteId = null;
                if (res.status === 'success') {
                    table.ajax.reload(null, false);
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message });
                }
            },
            error: function (xhr) {
                $('#modalDeleteParent').modal('hide');
                deleteId = null;
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan.';
                Swal.fire({ icon: 'error', title: 'Gagal!', text: msg });
            }
        });
    });

});
</script>

</html>
