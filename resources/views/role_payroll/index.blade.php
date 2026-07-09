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
                    <h1 class="h3 mb-0 text-gray-800">Manage Payroll Role</h1>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#modalRolePayroll" id="btnAddRolePayroll">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Assignment
                        </button>
                    </div>
                </div>

                <div class="alert alert-info">
                    Halaman ini mengatur user mana yang termasuk kategori
                    <strong>Payroll_STAFF</strong>, <strong>Payroll_NONSTAFF</strong>,
                    <strong>Payroll_SEWING</strong>, <strong>Payroll_NONSEWING</strong>,
                    atau <strong>Payroll_ALL</strong> (tidak difilter / semua karyawan muncul).
                    Ini menggantikan filter yang sebelumnya hardcode berdasarkan role Spatie.
                </div>

                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Assignment Payroll Role</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="table-role-payroll" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama User</th>
                                        <th>Email</th>
                                        <th>Payroll Role</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- End of Main Content -->
    </div>
    <!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

<!-- Modal Add/Edit -->
<div class="modal fade" id="modalRolePayroll" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="formRolePayroll">
                @csrf
                <input type="hidden" name="id" id="role_payroll_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRolePayrollTitle">Tambah Assignment Payroll Role</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>User</label>
                        <select name="user_id" id="select_user_id" class="form-control select2-user" style="width:100%" required>
                            <option value="">-- Pilih User --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payroll Role</label>
                        <select name="payroll_role" id="select_payroll_role" class="form-control" required>
                            <option value="">-- Pilih Payroll Role --</option>
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}">{{ $label }} ({{ $key }})</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">
                            Pilih <strong>Payroll_ALL</strong> jika user ini harus melihat semua karyawan tanpa difilter
                            (mis. untuk kasus Compliance yang butuh akses penuh).
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('layout.footer')
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {

    // Select2 di dalam modal butuh dropdownParent supaya z-index & posisi benar
    $('.select2-user').select2({
        dropdownParent: $('#modalRolePayroll')
    });

    let table = $('#table-role-payroll').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('role-payroll.index') }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'user_name', name: 'user.name' },
            { data: 'user_email', name: 'user.email' },
            { data: 'payroll_role', name: 'payroll_role' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });

    function resetForm() {
        $('#formRolePayroll')[0].reset();
        $('#role_payroll_id').val('');
        $('#select_user_id').val(null).trigger('change');
        $('#modalRolePayrollTitle').text('Tambah Assignment Payroll Role');
        $('#select_user_id').prop('disabled', false);
    }

    $('#btnAddRolePayroll').on('click', function () {
        resetForm();
    });

    // EDIT: ambil ulang daftar user (termasuk user yang sedang diedit) lalu buka modal
    $(document).on('click', '.btn-edit-role-payroll', function () {

        let id = $(this).data('id');
        let userId = $(this).data('user_id');
        let payrollRole = $(this).data('payroll_role');

        $.get("{{ url('role-payroll') }}/" + id + "/users-for-edit", function (res) {

            if (res.success) {
                let options = '<option value="">-- Pilih User --</option>';
                res.data.forEach(function (u) {
                    options += `<option value="${u.id}">${u.name} (${u.email})</option>`;
                });
                $('#select_user_id').html(options);
            }

            $('#role_payroll_id').val(id);
            $('#select_user_id').val(userId).trigger('change');
            $('#select_payroll_role').val(payrollRole);
            $('#modalRolePayrollTitle').text('Edit Assignment Payroll Role');
            $('#modalRolePayroll').modal('show');
        });
    });

    $('#formRolePayroll').on('submit', function (e) {
        e.preventDefault();

        let id = $('#role_payroll_id').val();
        let url = id
            ? "{{ url('role-payroll') }}/" + id
            : "{{ route('role-payroll.store') }}";

        $.ajax({
            url: url,
            method: 'POST',
            data: $(this).serialize() + (id ? '&_method=PUT' : ''),
            success: function (res) {
                $('#modalRolePayroll').modal('hide');
                Swal.fire('Berhasil', res.message, 'success');
                table.ajax.reload();
                resetForm();
            },
            error: function (xhr) {
                let msg = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Terjadi kesalahan, silakan coba lagi.';
                Swal.fire('Gagal', msg, 'error');
            }
        });
    });

    $(document).on('click', '.btn-delete-role-payroll', function () {

        let id = $(this).data('id');

        Swal.fire({
            title: 'Yakin hapus assignment ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "{{ url('role-payroll') }}/" + id,
                    method: 'POST',
                    data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
                    success: function (res) {
                        Swal.fire('Terhapus', res.message, 'success');
                        table.ajax.reload();
                    },
                    error: function (xhr) {
                        let msg = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Terjadi kesalahan, silakan coba lagi.';
                        Swal.fire('Gagal', msg, 'error');
                    }
                });
            }
        });
    });

    $('#modalRolePayroll').on('hidden.bs.modal', function () {
        resetForm();
    });
});
</script>
</html>
