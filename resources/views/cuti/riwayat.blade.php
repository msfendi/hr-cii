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
                    <h1 class="h3 mb-0 text-gray-800">Riwayat Pengajuan Cuti</h1>
                </div>

                <!-- Card Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Pengajuan</h6>
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
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DataTable will load contents here -->
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

<!-- Modal Detail -->
<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="mdTitle">Detail Pengajuan</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" width="40%">Nama</td><td id="mdNama">-</td></tr>
                    <tr><td class="text-muted">NPK</td><td id="mdNpk">-</td></tr>
                    <tr><td class="text-muted">Departemen</td><td id="mdDept">-</td></tr>
                    <tr><td class="text-muted">Jenis Cuti</td><td id="mdLeave">-</td></tr>
                    <tr><td class="text-muted">Tanggal Mulai</td><td id="mdStart">-</td></tr>
                    <tr><td class="text-muted">Tanggal Selesai</td><td id="mdEnd">-</td></tr>
                    <tr><td class="text-muted">Jumlah Hari</td><td id="mdDays">-</td></tr>
                    <tr><td class="text-muted">Alasan</td><td id="mdReason">-</td></tr>
                    <tr><td class="text-muted">Approver</td><td id="mdApprover">-</td></tr>
                    <tr><td class="text-muted">Status</td><td id="mdStatus">-</td></tr>
                    <tr><td class="text-muted">Tanggal Diajukan</td><td id="mdCreated">-</td></tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Page level plugins -->
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

<!-- Data rows handling code replaced by server side fetching -->
<script>
$(document).ready(function () {

    /* Initialize Server-Side DataTable */
    var dtable = $('#dataTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('pengajuan-cuti.riwayat') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'karyawan', name: 'nama'}, // search will fallback to row nama
            {data: 'leave_type', name: 'leave_type'},
            {data: 'periode', name: 'periode', orderable: false, searchable: false},
            {data: 'hari', name: 'hari', orderable: false, searchable: false},
            {data: 'status_badge', name: 'status_badge', orderable: false, searchable: false},
            {data: 'aksi', name: 'aksi', orderable: false, searchable: false}
        ],
        language: {
            search:       'Cari:',
            zeroRecords:  'Tidak ada data pengajuan.',
        }
    });

    // Open modal on Detail click
    // Note: since it's AJAX, we access the row data embedded manually during column rendering
    $(document).on('click', '.btn-detail', function () {
        var info = $(this).data('info');
        if (!info) return;
        
        // Handle if the data is stringified JSON
        if (typeof info === 'string') {
            try { info = JSON.parse(info); } catch(e) {}
        }

        $('#mdNama').text(info.nama || '-');
        $('#mdNpk').text(info.npk || '-');
        $('#mdDept').text(info.dept || '-');
        $('#mdLeave').text(info.leave_type || '-');
        $('#mdStart').text(info.start_date || '-');
        $('#mdEnd').text(info.end_date || '-');
        $('#mdDays').text((info.total_days || 0) + ' hari');
        $('#mdReason').text(info.reason || '-');
        $('#mdApprover').text((info.approver_name || '-') + ' (Level ' + (info.approval_level||0) + ')');
        $('#mdCreated').text(info.created_at || '-');

        // Status badge
        var statusMap = {
            approved: '<span class="badge badge-success">Disetujui</span>',
            rejected: '<span class="badge badge-danger">Ditolak</span>',
            partial:  '<span class="badge badge-warning text-white">Parsial</span>',
            pending:  '<span class="badge badge-secondary">Menunggu</span>',
        };
        $('#mdStatus').html(statusMap[info.overall_status] || statusMap['pending']);

        $('#modalDetail').modal('show');
    });

});
</script>
</body>
</html>
