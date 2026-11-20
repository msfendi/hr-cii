<!DOCTYPE html>
<html lang="en">
@include('layout.header')
@include('work_assignment._attachments')

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
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">Manage Work Assignment</h1>
                            <p class="mb-0 mt-1 text-muted" style="font-size:.82rem;">
                                Semua pengajuan Surat Ijin Dinas Luar
                            </p>
                        </div>
                    </div>

                    <!-- Card Table -->
                    <div class="card shadow mb-4">
                        <div
                            class="card-header py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">
                                Daftar Pengajuan Dinas Luar
                            </h6>
                            <div class="d-flex flex-wrap align-items-center">
                                <input type="text" id="keyword_filter"
                                    class="form-control form-control-sm d-inline-block shadow-sm mr-2 mb-2 mb-md-0"
                                    placeholder="Cari nama / NPK / dinas…" style="width: 200px;">
                                <select id="status_filter"
                                    class="form-control form-control-sm d-inline-block shadow-sm mr-2 mb-2 mb-md-0"
                                    style="width: 165px;">
                                    <option value="">Semua Status</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="approved">Disetujui</option>
                                    <option value="rejected">Ditolak</option>
                                    <option value="cancelled">Dibatalkan</option>
                                </select>
                                <input type="month" id="month_year_filter"
                                    class="form-control form-control-sm d-inline-block shadow-sm mb-2 mb-md-0"
                                    value="{{ date('Y-m') }}" style="width: 150px;">
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0"
                                    style="min-width:1039px;">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:32px;">#</th>
                                            <th style="width:140px;">Karyawan</th>
                                            <th style="width:85px;">Tgl Pengajuan</th>
                                            <th style="width:130px;">Hari / Tgl Dinas</th>
                                            <th style="width:110px;">Dinas Ke</th>
                                            <th class="text-center" style="width:52px;">Hari</th>
                                            <th style="width:85px;">Jam</th>
                                            <th style="width:170px;">Alasan</th>
                                            <th class="text-center" style="width:130px;">Status</th>
                                            <th class="text-center" style="width:125px;">Aksi</th>
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

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold text-primary">Detail Pengajuan Dinas Luar</h5>
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
                <div class="modal-footer" id="detailModalFooter"></div>
            </div>
        </div>
    </div>

    <style>
        #dataTable { font-size: .88rem; color: #2e384d; table-layout: fixed; }
        #dataTable thead th { font-size: .84rem; font-weight: 700; background-color: #f8f9fc; color: #4e73df; vertical-align: middle; padding: 8px 6px; }
        #dataTable tbody td { font-size: 0.86rem; vertical-align: middle; padding: 8px 6px; }

        /* Mobile: padding atas-bawah dilegarkan, font dikecilkan. */
        @media (max-width: 768px) {
            #dataTable { font-size: 0.82rem; }
            #dataTable thead th { font-size: 0.74rem; padding: 10px 6px; }
            #dataTable tbody td { font-size: 0.8rem; padding: 12px 7px; }
            .wa-tl-step { padding: .6rem 0; }
            .wa-tl-title { font-size: .8rem; }
        }

        .wa-date-chip { display: inline-block; background: #f4f6fd; border: 1px solid #cdd7ef; border-radius: 2rem; padding: .1rem .5rem; font-size: .7rem; font-weight: 700; color: #224abe; margin: 0 .15rem .2rem 0; }

        .wa-tl-step { display: flex; gap: .65rem; padding: .45rem 0; position: relative; }
        .wa-tl-step:not(:last-child)::before { content: ''; position: absolute; left: 10px; top: 28px; bottom: -4px; width: 2px; background: #e6eaf3; }
        .wa-tl-dot { width: 22px; height: 22px; flex: 0 0 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .6rem; color: #fff; background: #cbd3e1; z-index: 1; }
        .wa-tl-dot.approved { background: #16a34a; }
        .wa-tl-dot.rejected { background: #dc2626; }
        .wa-tl-dot.waiting { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, .18); }
        .wa-tl-dot.upcoming { background: #d5dbe7; }
        .wa-tl-title { font-size: .84rem; font-weight: 800; color: #2e384d; line-height: 1.25; }
        .wa-tl-role { font-size: .62rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #4e73df; margin-right: .3rem; }
        .wa-tl-sub { font-size: .72rem; color: #6b7688; margin-top: .05rem; }
        .wa-tl-status { font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
        .wa-tl-status.approved { color: #16a34a; }
        .wa-tl-status.rejected { color: #dc2626; }
        .wa-tl-status.waiting { color: #d97706; }
        .wa-tl-status.upcoming { color: #9aa5b8; }
        .wa-tl-reason { margin-top: .25rem; font-size: .73rem; background: #fdeceb; border-left: 3px solid #dc2626; border-radius: .25rem; padding: .3rem .5rem; color: #93211c; }
    </style>

    <!-- Page level plugins -->
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {
            var getDataUrl = @json(route('work-assignment.manage.get-data'));
            var detailBase = @json(url('work-assignment/manage/detail'));
            var deleteBase = @json(url('work-assignment/manage/delete'));
            var pdfBase = @json(url('work-assignment/manage/pdf'));
            var attachmentBase = @json(url('work-assignment/manage/attachment'));
            var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            function escapeHtml(s) {
                var d = document.createElement('div');
                d.textContent = s == null ? '' : s;
                return d.innerHTML;
            }

            var dtable = $('#dataTable').DataTable({
                processing: true,
                serverSide: false,
                autoWidth: false,
                ajax: {
                    url: getDataUrl,
                    type: 'GET',
                    data: function (d) {
                        d.month_year = $('#month_year_filter').val();
                        d.status = $('#status_filter').val();
                        d.keyword = $('#keyword_filter').val();
                    },
                    dataSrc: 'data'
                },
                pageLength: 15,
                order: [],
                columns: [
                    {
                        data: 'rowno',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'nama',
                        render: function (d, t, row) {
                            if (t !== 'display') return d;
                            return '<strong>' + escapeHtml(d) + '</strong><br><small class="text-muted">'
                                + escapeHtml(row.npk) + (row.dept !== '-' ? ' &middot; ' + escapeHtml(row.dept) : '') + '</small>';
                        }
                    },
                    {
                        data: 'created_at',
                        render: function (d) {
                            if (!d) return '-';
                            var p = d.split(' ');
                            var q = p[0].split('-');
                            return q[2] + '-' + q[1] + '-' + q[0] + '<br><small class="text-muted">' + (p[1] || '') + '</small>';
                        }
                    },
                    {
                        data: 'duty_dates',
                        render: function (d) {
                            if (!d || !d.length) return '-';
                            var out = '';
                            d.forEach(function (x) {
                                var p = x.split('-');
                                out += '<span class="wa-date-chip">' + p[2] + '-' + p[1] + '-' + p[0] + '</span>';
                            });
                            return out;
                        }
                    },
                    { data: 'dinas_ke', render: function (d) { return escapeHtml(d || '-'); } },
                    { data: 'total_days', className: 'text-center', render: function (d) { return d + ' hr'; } },
                    {
                        data: 'jam_keluar',
                        render: function (d, t, row) {
                            var a = d || '--:--';
                            var b = row.rencana_kembali || '--:--';
                            return a + '<br><small class="text-muted">&rarr; ' + b + '</small>';
                        }
                    },
                    {
                        data: 'keperluan',
                        render: function (d) {
                            var t = escapeHtml(d || '-');
                            return t.length > 40 ? t.substring(0, 40) + '…' : t;
                        }
                    },
                    {
                        data: 'status',
                        className: 'text-center',
                        render: function (d, t, row) {
                            var map = {
                                approved: '<span class="badge badge-success px-2 py-1" style="font-size:.82rem;"><i class="fas fa-check mr-1"></i>Disetujui</span>',
                                rejected: '<span class="badge badge-danger px-2 py-1" style="font-size:.82rem;"><i class="fas fa-times mr-1"></i>Ditolak</span>',
                                pending: '<span class="badge badge-warning text-white px-2 py-1" style="font-size:.82rem;"><i class="fas fa-hourglass-half mr-1"></i>Menunggu</span>',
                                cancelled: '<span class="badge badge-secondary px-2 py-1" style="font-size:.82rem;"><i class="fas fa-ban mr-1"></i>Dibatalkan</span>'
                            };
                            return (map[d] || '<span class="badge badge-secondary">-</span>')
                                + '<br><small class="text-muted">Level ' + row.current_level + ' dari ' + row.total + '</small>';
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (d, t, row) {
                            return '<button class="btn btn-sm btn-info btn-detail" data-id="' + row.id + '" title="Detail">'
                                + '<i class="fas fa-eye"></i></button> '
                                + '<a class="btn btn-sm btn-secondary btn-pdf" href="' + pdfBase + '/' + row.id + '" target="_blank" title="Surat PDF">'
                                + '<i class="fas fa-file-pdf"></i></a> '
                                + '<button class="btn btn-sm btn-danger btn-delete" data-id="' + row.id + '" '
                                + 'data-nama="' + escapeHtml(row.nama) + '" data-status="' + row.status + '" title="Hapus">'
                                + '<i class="fas fa-trash"></i></button>';
                        }
                    }
                ]
            });

            $('#month_year_filter, #status_filter').on('change', function () { dtable.ajax.reload(null, true); });

            var keywordTimer;
            $('#keyword_filter').on('input', function () {
                clearTimeout(keywordTimer);
                keywordTimer = setTimeout(function () { dtable.ajax.reload(null, true); }, 350);
            });

            // ── Detail ──
            $(document).on('click', '.btn-detail', function () {
                var id = $(this).data('id');
                $('#detailContent').html('<div class="text-center my-3"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
                $('#detailModal').modal('show');

                $.ajax({
                    url: detailBase + '/' + id,
                    type: 'GET',
                    success: function (res) {
                        if (!res.success) {
                            $('#detailContent').html('<div class="alert alert-danger">' + escapeHtml(res.message) + '</div>');
                            $('#detailModalFooter').html('<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>');
                            return;
                        }

                        var d = res.data;
                        var html = '<table class="table table-sm table-bordered">';
                        html += '<tr><th width="28%">Nama</th><td><strong>' + escapeHtml(d.nama) + '</strong> (' + escapeHtml(d.npk) + ')</td></tr>';
                        html += '<tr><th>Jabatan / Departemen</th><td>' + escapeHtml(d.jabatan) + ' &middot; ' + escapeHtml(d.departemen) + '</td></tr>';
                        html += '<tr><th>Bagian / Section</th><td>' + escapeHtml(d.bagian) + ' &middot; ' + escapeHtml(d.section) + '</td></tr>';
                        html += '<tr><th>Hari / Tgl Dinas</th><td>';
                        (d.duty_dates || []).forEach(function (x) { html += '<span class="wa-date-chip">' + escapeHtml(x) + '</span>'; });
                        html += ' (' + d.total_days + ' hari)</td></tr>';
                        html += '<tr><th>Dinas Ke</th><td>' + escapeHtml(d.dinas_ke || '-') + '</td></tr>';
                        html += '<tr><th>Keperluan</th><td>' + escapeHtml(d.keperluan || '-') + '</td></tr>';
                        html += '<tr><th>Jam Keluar / Rencana Kembali</th><td>' + escapeHtml(d.jam_keluar || '--:--') + ' &ndash; ' + escapeHtml(d.rencana_kembali || '--:--') + ' WIB</td></tr>';
                        html += '<tr><th>Jam Kembali</th><td>' + (d.jam_kembali ? escapeHtml(d.jam_kembali) + ' WIB' : '<span class="text-muted">- (diisi Security)</span>') + '</td></tr>';
                        html += '<tr><th>Diajukan</th><td>' + escapeHtml(d.created_at || '-') + '</td></tr>';
                        html += '</table>';

                        html += '<h6 class="mt-4 font-weight-bold">Lampiran:</h6>';
                        html += waRenderAttachments(d.attachments, {
                            downloadBase: attachmentBase + '/' + d.id,
                            deletable: true,
                            requestId: d.id
                        });

                        html += '<h6 class="mt-4 font-weight-bold">Alur Persetujuan:</h6><div>';
                        (d.timeline || []).forEach(function (s) {
                            var icon = { approved: 'fa-check', rejected: 'fa-times', waiting: 'fa-clock', skipped: 'fa-minus' }[s.decision] || 'fa-circle';
                            var label = { approved: 'Disetujui', rejected: 'Ditolak', waiting: 'Menunggu', skipped: 'Dilewati' }[s.decision] || 'Belum Aktif';
                            html += '<div class="wa-tl-step"><div class="wa-tl-dot ' + s.decision + '"><i class="fas ' + icon + '"></i></div>';
                            html += '<div style="flex:1 1 auto;min-width:0"><div class="wa-tl-title"><span class="wa-tl-role">L' + s.level + ' &middot; ' + escapeHtml(s.role_label) + '</span>' + escapeHtml(s.name || '-') + '</div>';
                            html += '<div class="wa-tl-sub">' + escapeHtml(s.npk || '-');
                            if (s.jabatan && s.jabatan !== s.name) html += ' &middot; <em>' + escapeHtml(s.jabatan) + '</em>';
                            if (s.decided_at) html += ' &middot; ' + escapeHtml(s.decided_at);
                            html += '</div>';
                            if (s.reason) html += '<div class="wa-tl-reason"><b>Alasan:</b> ' + escapeHtml(s.reason) + '</div>';
                            html += '</div><div class="wa-tl-status ' + s.decision + '">' + label + '</div></div>';
                        });
                        html += '</div>';

                        $('#detailContent').html(html);

                        $('#detailModalFooter').html(
                            '<a class="btn btn-secondary btn-sm" href="' + pdfBase + '/' + d.id + '" target="_blank">'
                            + '<i class="fas fa-file-pdf mr-1"></i> Lihat Surat PDF</a> '
                            + '<button type="button" class="btn btn-danger btn-sm btn-delete" data-id="' + d.id + '" '
                            + 'data-nama="' + escapeHtml(d.nama) + '" data-status="' + d.status + '">'
                            + '<i class="fas fa-trash mr-1"></i> Hapus</button> '
                            + '<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>'
                        );
                    },
                    error: function () {
                        $('#detailContent').html('<div class="alert alert-danger">Terjadi kesalahan saat memuat data.</div>');
                    }
                });
            });

            // ── Hapus lampiran ──
            $(document).on('click', '.btn-att-delete', function () {
                var id = $(this).data('request-id');
                var index = $(this).data('index');
                var name = $(this).data('name');

                Swal.fire({
                    title: 'Hapus lampiran?',
                    html: 'File <b>' + escapeHtml(name) + '</b> akan dihapus permanen dari pengajuan ini.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: attachmentBase + '/' + id + '/' + index,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF },
                        success: function (res) {
                            if (res.success !== false) {
                                Swal.fire({ icon: 'success', title: 'Dihapus', text: res.message, timer: 1800, showConfirmButton: false });
                                // Muat ulang detail agar daftar lampiran sinkron.
                                $('.btn-detail[data-id="' + id + '"]').first().trigger('click');
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal', text: res.message });
                            }
                        },
                        error: function (xhr) {
                            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Terjadi kesalahan.';
                            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
                        }
                    });
                });
            });

            // ── Hapus ──
            $(document).on('click', '.btn-delete', function () {
                var id = $(this).data('id');
                var nama = $(this).data('nama');
                var status = $(this).data('status');

                var extra = '';
                if (status === 'approved' || status === 'rejected') {
                    extra = '<div class="alert alert-warning text-left mt-3 mb-0" style="font-size:.85rem;">'
                        + 'Pengajuan ini <b>sudah diputuskan</b>. Pengajuan akan <b>DIBATALKAN</b> '
                        + '(bukan dihapus) supaya jejak persetujuannya tetap tersimpan.</div>';
                } else {
                    extra = '<div class="alert alert-info text-left mt-3 mb-0" style="font-size:.85rem;">'
                        + 'Pengajuan ini <b>belum selesai</b> dan akan <b>dihapus permanen</b> '
                        + 'beserta seluruh level persetujuannya.</div>';
                }

                Swal.fire({
                    title: 'Hapus pengajuan ini?',
                    html: '<div class="text-left">Pengajuan Work Assignment dari <b>' + escapeHtml(nama) + '</b> '
                        + 'akan diproses.</div>' + extra,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    input: 'text',
                    inputPlaceholder: 'Alasan (opsional, dicatat di log)',
                    inputAttributes: { 'aria-label': 'Alasan' }
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: deleteBase + '/' + id,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF },
                        data: { reason: result.value || '' },
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'Terhapus', text: res.message, timer: 2200, showConfirmButton: false });
                                $('#detailModal').modal('hide');
                                dtable.ajax.reload(null, false);
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal', text: res.message });
                            }
                        },
                        error: function (xhr) {
                            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Terjadi kesalahan.';
                            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>
