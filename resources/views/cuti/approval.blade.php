<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">

<!-- Page Wrapper -->
@include('sweetalert::alert')
<div id="wrapper">
    @include('layout.sidebar-cuti')

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @include('layout.topbar-cuti')

            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Approval Permohonan Cuti</h1>
                </div>

                <!-- Card Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Permohonan</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Karyawan</th>
                                        <th>Jenis Cuti</th>
                                        <th>Periode</th>
                                        <th>Total Hari</th>
                                        <th>Alasan</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DataTable akan di-load via AJAX -->
                                </tbody>
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

<!-- Page level plugins -->
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {

    /* Initialize Server-Side DataTable */
    var dtable = $('#dataTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('pengajuan-cuti.approval') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'karyawan', name: 'nama'},
            {data: 'leave_type', name: 'leave_type'},
            {data: 'periode', name: 'periode', orderable: false, searchable: false},
            {data: 'hari', name: 'hari', orderable: false, searchable: false},
            {data: 'alasan', name: 'alasan', orderable: false, searchable: false},
            {data: 'status_badge', name: 'status_badge', orderable: false, searchable: false},
            {data: 'aksi', name: 'aksi', orderable: false, searchable: false}
        ],
        language: {
            search:       'Cari:',
            zeroRecords:  'Tidak ada permohonan cuti yang perlu diproses.',
        }
    });

    // Helper function for AJAX actions
    function handleAction(url, successMessage) {
        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function (response) {
                if (response.success) {
                    Swal.fire('Berhasil', response.message || successMessage, 'success')
                        .then(function () { dtable.ajax.reload(null, false); });
                } else {
                    Swal.fire('Gagal', response.message || 'Terjadi kesalahan.', 'error');
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan server.';
                Swal.fire('Error', msg, 'error');
            }
        });
    }

    // Approve button
    $(document).on('click', '.btn-approve', function () {
        var id   = $(this).data('id');
        var nama = $(this).data('nama');

        Swal.fire({
            title: 'Setujui Cuti?',
            text: 'Anda akan menyetujui permohonan cuti dari ' + nama,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#1cc88a',
            confirmButtonText: 'Ya, Setujui',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (result.isConfirmed) {
                handleAction('/pengajuan-cuti/approval/approve/' + id, 'Permohonan berhasil disetujui.');
            }
        });
    });

    // Reject button
    $(document).on('click', '.btn-reject', function () {
        var id   = $(this).data('id');
        var nama = $(this).data('nama');

        Swal.fire({
            title: 'Tolak Cuti?',
            text: 'Anda akan menolak permohonan cuti dari ' + nama,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            confirmButtonText: 'Ya, Tolak',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (result.isConfirmed) {
                handleAction('/pengajuan-cuti/approval/reject/' + id, 'Permohonan berhasil ditolak.');
            }
        });
    });

});
</script>
</body>
</html>
