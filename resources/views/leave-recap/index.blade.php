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
                        <h1 class="h3 mb-0 text-gray-800">Leave Recap / Pengajuan Cuti</h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div
                            class="card-header py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">Daftar Pengajuan & Approved Cuti
                            </h6>
                            <div class="d-flex flex-wrap align-items-center">
                                <select id="status_filter"
                                    class="form-control form-control-sm d-inline-block shadow-sm mr-2 mb-2 mb-md-0"
                                    style="width: 150px;">
                                    <option value="">Semua Status</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="partial">Parsial</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                </select>
                                <input type="month" id="month_year_filter"
                                    class="form-control form-control-sm d-inline-block shadow-sm mb-2 mb-md-0"
                                    value="{{ date('Y-m') }}" style="width: 150px;">
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="dataTable" width="100%"
                                    cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Karyawan</th>
                                            <th>Jenis Cuti</th>
                                            <th>Tgl Pengajuan</th>
                                            <th>Periode</th>
                                            <th>Jumlah Hari</th>
                                            <th>Alasan</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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
    </div>

    <!-- Modal Detail Pengajuan -->
    <div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-primary">Detail Pengajuan Cuti</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="detailContent">
                        <div class="text-center my-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Page level plugins -->
    <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css"
        rel="stylesheet" />
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/js/bootstrap-datepicker.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize Year Picker
            $('.yearpicker').datepicker({
                format: "yyyy",
                viewMode: "years",
                minViewMode: "years",
                autoclose: true
            });

            var table = $('#dataTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("leave-recap.get-data") }}',
                    data: function (d) {
                        d.month_year = $('#month_year_filter').val();
                        d.status = $('#status_filter').val();
                    }
                },
                pageLength: 15,
                order: [[3, 'desc']], // urut berdasarkan tanggal pengajuan desc
                columns: [
                    {
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    { data: 'karyawan', name: 'npk' },
                    { data: 'leave_type', name: 'leave_type_id' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'periode', name: 'start_date' },
                    { data: 'hari', name: 'total_days', orderable: false, searchable: false },
                    { data: 'reason', name: 'reason' },
                    { data: 'status_badge', name: 'status', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });

            $('#month_year_filter, #status_filter').on('change', function () {
                table.ajax.reload();
            });

            $('#dataTable').on('click', '.btn-detail', function () {
                var token = $(this).data('token');
                $('#detailContent').html('<div class="text-center my-3"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
                $('#modalDetail').modal('show');

                $.ajax({
                    url: '{{ url("leave-recap/detail") }}/' + token,
                    type: 'GET',
                    success: function (res) {
                        if (res.success) {
                            var data = res.data;
                            var html = '<table class="table table-sm table-bordered">';
                            html += '<tr><th width="30%">Nama</th><td>' + data.nama + ' (' + data.npk + ')</td></tr>';
                            html += '<tr><th>Departemen</th><td>' + data.dept + '</td></tr>';
                            html += '<tr><th>Jenis Cuti</th><td>' + data.leave_type + '</td></tr>';
                            html += '<tr><th>Periode</th><td>' + data.start_date + ' s/d ' + data.end_date + ' (' + data.total_days + ' hari)</td></tr>';
                            html += '<tr><th>Alasan</th><td>' + data.reason + '</td></tr>';
                            html += '</table>';

                            html += '<h6 class="mt-4 font-weight-bold">Status Persetujuan:</h6>';
                            html += '<table class="table table-sm text-center table-bordered">';
                            html += '<thead class="bg-light"><tr><th>Level</th><th>Approver</th><th>Status</th><th>Tanggal</th><th>Keterangan / Komentar</th></tr></thead>';
                            html += '<tbody>';
                            $.each(data.approvals, function (i, app) {
                                var statusBadge = '';
                                if (app.status == 'approved') statusBadge = '<span class="badge badge-success">Disetujui</span>';
                                else if (app.status == 'rejected') statusBadge = '<span class="badge badge-danger">Ditolak</span>';
                                else statusBadge = '<span class="badge badge-warning text-white">Menunggu</span>';

                                html += '<tr>';
                                html += '<td>Level ' + app.level + '</td>';
                                html += '<td>' + app.approver_name + '</td>';
                                html += '<td>' + statusBadge + '</td>';
                                html += '<td>' + app.date + '</td>';
                                html += '<td>' + app.comment || '-' + '</td>';
                                html += '</tr>';
                            });
                            html += '</tbody></table>';

                            $('#detailContent').html(html);
                        } else {
                            $('#detailContent').html('<div class="alert alert-danger">Gagal memuat detail data.</div>');
                        }
                    },
                    error: function () {
                        $('#detailContent').html('<div class="alert alert-danger">Terjadi kesalahan saat memuat data.</div>');
                    }
                });
            });
        });
    </script>
</body>

</html>