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
                                        <th>Komentar</th>
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
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="mdTitle">Detail Pengajuan</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted" width="30%">Nama</td><td id="mdNama">-</td></tr>
                    <tr><td class="text-muted">NPK</td><td id="mdNpk">-</td></tr>
                    <tr><td class="text-muted">Departemen</td><td id="mdDept">-</td></tr>
                    <tr><td class="text-muted">Jenis Cuti</td><td id="mdLeave">-</td></tr>
                    <tr><td class="text-muted">Tanggal Mulai</td><td id="mdStart">-</td></tr>
                    <tr><td class="text-muted">Tanggal Selesai</td><td id="mdEnd">-</td></tr>
                    <tr><td class="text-muted">Jumlah Hari</td><td id="mdDays">-</td></tr>
                    <tr><td class="text-muted">Alasan</td><td id="mdReason">-</td></tr>
                    <tr><td class="text-muted">Komentar</td><td id="mdComment">-</td></tr>
                    <tr><td class="text-muted">Approver</td><td id="mdApprover">-</td></tr>
                    <tr><td class="text-muted">Status</td><td id="mdStatus">-</td></tr>
                    <tr><td class="text-muted">Tanggal Diajukan</td><td id="mdCreated">-</td></tr>
                    <tr><td class="text-muted align-top">Lampiran Dokumen</td><td id="mdAttach">-</td></tr>
                </table>
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
            {
                data: 'leave_type', 
                name: 'leave_type',
                render: function (data, type, row) {
                    var badge = '';
                    if (row.attach_files && row.attach_files.length > 0) {
                        var count = row.attach_files.length;
                        badge = ' <span class="badge badge-light border text-primary" title="' + count + ' file dilampirkan"><i class="fas fa-paperclip"></i> ' + count + '</span>';
                    }
                    return (data || '-') + badge;
                }
            },
            {data: 'periode', name: 'periode', orderable: false, searchable: false},
            {data: 'hari', name: 'hari', orderable: false, searchable: false},
            {data: 'status_badge', name: 'status_badge', orderable: false, searchable: false},
            {
                data: 'comment', 
                name: 'comment', 
                orderable: false, 
                searchable: false,
                render: function(data, type, row) {
                    return data ? '<span class="text-muted small">' + data + '</span>' : '-';
                }
            },
            {data: 'aksi', name: 'aksi', orderable: false, searchable: false}
        ],
        language: {
            search:       'Cari:',
            zeroRecords:  'Tidak ada data pengajuan.',
        }
    });

    // Open modal on Detail click
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
        $('#mdComment').text(info.comment || '-');
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

        // Render lampiran dokumen
        if (info.attach_files && info.attach_files.length > 0) {
            var attachHtml = '<div class="d-flex flex-wrap" style="gap: 8px;">';
            $.each(info.attach_files, function(i, file) {
                if (file.is_image) {
                    attachHtml += `
                    <div class="border rounded p-2 bg-white shadow-sm d-flex align-items-center" style="max-width: 260px; min-width: 200px;">
                        <img src="${file.url}" class="rounded mr-2 img-preview-thumb" data-url="${file.url}" data-name="${file.name}" style="width: 48px; height: 48px; object-fit: cover; cursor: zoom-in;" title="Klik untuk perbesar">
                        <div class="overflow-hidden mr-2" style="flex:1;">
                            <div class="font-weight-bold small text-truncate" title="${file.name}">${file.name}</div>
                            <span class="badge badge-success" style="font-size:0.65rem;">GAMBAR</span>
                        </div>
                        <a href="${file.url}" target="_blank" class="btn btn-sm btn-outline-primary p-1" title="Buka di Tab Baru"><i class="fas fa-external-link-alt fa-xs"></i></a>
                    </div>`;
                } else if (file.is_pdf) {
                    attachHtml += `
                    <div class="border rounded p-2 bg-white shadow-sm d-flex align-items-center" style="max-width: 260px; min-width: 200px;">
                        <div class="rounded mr-2 d-flex align-items-center justify-content-center bg-light" style="width: 48px; height: 48px; flex-shrink: 0;">
                            <i class="far fa-file-pdf text-danger fa-2x"></i>
                        </div>
                        <div class="overflow-hidden mr-2" style="flex:1;">
                            <div class="font-weight-bold small text-truncate" title="${file.name}">${file.name}</div>
                            <span class="badge badge-danger" style="font-size:0.65rem;">PDF</span>
                        </div>
                        <a href="${file.url}" target="_blank" class="btn btn-sm btn-outline-danger p-1" title="Buka File"><i class="fas fa-external-link-alt fa-xs"></i></a>
                    </div>`;
                } else {
                    var badgeColor = file.is_word ? 'badge-primary' : 'badge-secondary';
                    var iconColor = file.is_word ? 'fa-file-word text-primary' : 'fa-file-alt text-secondary';
                    attachHtml += `
                    <div class="border rounded p-2 bg-white shadow-sm d-flex align-items-center" style="max-width: 260px; min-width: 200px;">
                        <div class="rounded mr-2 d-flex align-items-center justify-content-center bg-light" style="width: 48px; height: 48px; flex-shrink: 0;">
                            <i class="far ${iconColor} fa-2x"></i>
                        </div>
                        <div class="overflow-hidden mr-2" style="flex:1;">
                            <div class="font-weight-bold small text-truncate" title="${file.name}">${file.name}</div>
                            <span class="badge ${badgeColor}" style="font-size:0.65rem;">${(file.ext || 'FILE').toUpperCase()}</span>
                        </div>
                        <a href="${file.url}" target="_blank" class="btn btn-sm btn-outline-primary p-1" title="Download / Buka"><i class="fas fa-download fa-xs"></i></a>
                    </div>`;
                }
            });
            attachHtml += '</div>';
            $('#mdAttach').html(attachHtml);
        } else {
            $('#mdAttach').html('<span class="text-muted font-italic">Tidak ada dokumen dilampirkan</span>');
        }

        $('#modalDetail').modal('show');
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
