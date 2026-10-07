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

    <!-- Modal Zoom Foto Lampiran -->
    <div class="modal fade" id="imageZoomModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1070;">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header py-2 px-3 bg-light d-flex align-items-center justify-content-between">
                    <h6 class="modal-title font-weight-bold text-gray-800 text-truncate mr-2" id="imageZoomTitle">
                        <i class="far fa-file-image text-primary mr-1"></i> Preview Foto
                    </h6>
                    <button type="button" class="close ml-auto" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-2 text-center bg-dark" style="min-height: 250px; display:flex; align-items:center; justify-content:center;">
                    <img id="imageZoomSrc" src="" style="max-width: 100%; max-height: 75vh; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);" alt="Zoom">
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
                    { 
                        data: 'leave_type', 
                        name: 'leave_type_id',
                        render: function (data, type, row) {
                            var badge = row.has_attach ? ' <span class="badge badge-light border text-primary" title="Ada Lampiran Dokumen"><i class="fas fa-paperclip"></i></span>' : '';
                            return (data || '-') + badge;
                        }
                    },
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

                            // Render lampiran dokumen
                            html += '<tr><th>Lampiran Dokumen</th><td>';
                            if (data.attach_files && data.attach_files.length > 0) {
                                html += '<div class="d-flex flex-wrap" style="gap: 8px;">';
                                $.each(data.attach_files, function (i, file) {
                                    if (file.is_image) {
                                        html += '<div class="border rounded p-2 bg-white shadow-sm d-flex align-items-center" style="max-width: 260px; min-width: 200px;">' +
                                            '<img src="' + file.url + '" class="rounded mr-2 img-preview-thumb" data-url="' + file.url + '" data-name="' + file.name + '" style="width: 48px; height: 48px; object-fit: cover; cursor: zoom-in;" title="Klik untuk perbesar">' +
                                            '<div class="overflow-hidden mr-2" style="flex:1;">' +
                                                '<div class="font-weight-bold small text-truncate" title="' + file.name + '">' + file.name + '</div>' +
                                                '<span class="badge badge-success" style="font-size:0.65rem;">GAMBAR</span>' +
                                            '</div>' +
                                            '<a href="' + file.url + '" target="_blank" class="btn btn-sm btn-outline-primary p-1" title="Buka di Tab Baru"><i class="fas fa-external-link-alt fa-xs"></i></a>' +
                                        '</div>';
                                    } else if (file.is_pdf) {
                                        html += '<div class="border rounded p-2 bg-white shadow-sm d-flex align-items-center" style="max-width: 260px; min-width: 200px;">' +
                                            '<div class="rounded mr-2 d-flex align-items-center justify-content-center bg-light" style="width: 48px; height: 48px; flex-shrink: 0;">' +
                                                '<i class="far fa-file-pdf text-danger fa-2x"></i>' +
                                            '</div>' +
                                            '<div class="overflow-hidden mr-2" style="flex:1;">' +
                                                '<div class="font-weight-bold small text-truncate" title="' + file.name + '">' + file.name + '</div>' +
                                                '<span class="badge badge-danger" style="font-size:0.65rem;">PDF</span>' +
                                            '</div>' +
                                            '<a href="' + file.url + '" target="_blank" class="btn btn-sm btn-outline-danger p-1" title="Buka File"><i class="fas fa-external-link-alt fa-xs"></i></a>' +
                                        '</div>';
                                    } else {
                                        var badgeColor = file.is_word ? 'badge-primary' : 'badge-secondary';
                                        var iconColor = file.is_word ? 'fa-file-word text-primary' : 'fa-file-alt text-secondary';
                                        html += '<div class="border rounded p-2 bg-white shadow-sm d-flex align-items-center" style="max-width: 260px; min-width: 200px;">' +
                                            '<div class="rounded mr-2 d-flex align-items-center justify-content-center bg-light" style="width: 48px; height: 48px; flex-shrink: 0;">' +
                                                '<i class="far ' + iconColor + ' fa-2x"></i>' +
                                            '</div>' +
                                            '<div class="overflow-hidden mr-2" style="flex:1;">' +
                                                '<div class="font-weight-bold small text-truncate" title="' + file.name + '">' + file.name + '</div>' +
                                                '<span class="badge ' + badgeColor + '" style="font-size:0.65rem;">' + (file.ext || 'FILE').toUpperCase() + '</span>' +
                                            '</div>' +
                                            '<a href="' + file.url + '" target="_blank" class="btn btn-sm btn-outline-primary p-1" title="Download / Buka"><i class="fas fa-download fa-xs"></i></a>' +
                                        '</div>';
                                    }
                                });
                                html += '</div>';
                            } else {
                                html += '<span class="text-muted font-italic">Tidak ada dokumen dilampirkan</span>';
                            }
                            html += '</td></tr>';

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
                                html += '<td>' + (app.comment || '-') + '</td>';
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

            // Zoom foto preview
            $(document).on('click', '.img-preview-thumb', function () {
                var url = $(this).data('url');
                var name = $(this).data('name') || 'Preview Foto';
                $('#imageZoomSrc').attr('src', url);
                $('#imageZoomTitle').html('<i class="far fa-file-image text-primary mr-1"></i> ' + name);
                $('#imageZoomModal').modal('show');
            });
        });
    </script>
</body>

</html>