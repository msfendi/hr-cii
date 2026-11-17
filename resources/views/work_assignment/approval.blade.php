<!DOCTYPE html>
<html lang="en">
@include('layout.header')
@include('work_assignment._approver_badges')
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
                            <h1 class="h3 mb-0 text-gray-800">Approval Work Assignment</h1>
                            <p class="mb-0 mt-1 text-muted" style="font-size:.82rem;">
                                Surat Ijin Dinas Luar
                                &middot; Anda: <strong>{{ $approver['nama'] ?? $approverNpk }}</strong>
                                <span class="text-muted">(NPK {{ $approverNpk }})</span>
                            </p>
                        </div>
                    </div>

                    <!-- Card Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">Daftar Pengajuan Dinas Luar</h6>
                            <select id="filterStatus" class="form-control form-control-sm d-inline-block shadow-sm"
                                style="width: 210px;">
                                <option value="actionable" selected>Perlu Tindakan Saya</option>
                                <option value="waiting_previous">Menunggu Level Sebelumnya</option>
                                <option value="decided">Sudah Saya Putuskan</option>
                                <option value="pending">Status: Menunggu</option>
                                <option value="approved">Status: Disetujui</option>
                                <option value="rejected">Status: Ditolak</option>
                                <option value="all">Semua</option>
                            </select>
                        </div>
                        <div class="card-body">

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0"
                                    style="min-width:1105px;">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:32px;">#</th>
                                            <th style="width:145px;">Karyawan</th>
                                            <th style="width:88px;">Tgl Pengajuan</th>
                                            <th style="width:135px;">Hari / Tgl Dinas</th>
                                            <th style="width:115px;">Dinas Ke</th>
                                            <th class="text-center" style="width:58px;">Hari</th>
                                            <th style="width:92px;">Jam</th>
                                            <th style="width:320px; min-width:300px;">Approval Progress</th>
                                            <th class="text-center" style="width:120px;">Aksi</th>
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
    </div>

    <!-- Modal Detail Pengajuan -->
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
                <div class="modal-footer" id="detailModalFooter">
                    <!-- diisi dinamis lewat JS -->
                </div>
            </div>
        </div>
    </div>

    <style>
        #dataTable {
            font-size: 0.9rem;
            color: #2e384d;
            table-layout: fixed;
        }

        #dataTable thead th {
            font-size: 0.84rem;
            font-weight: 700;
            background-color: #f8f9fc;
            color: #4e73df;
            vertical-align: middle;
            padding: 8px 6px;
            word-break: normal;
        }

        #dataTable tbody td {
            font-size: 0.88rem;
            vertical-align: middle;
            padding: 8px 6px;
        }

        /* Mobile: padding atas-bawah dilegarkan, font dikecilkan. */
        @media (max-width: 768px) {
            #dataTable {
                font-size: 0.82rem;
            }

            #dataTable thead th {
                font-size: 0.74rem;
                padding: 10px 6px;
            }

            #dataTable tbody td {
                font-size: 0.8rem;
                padding: 12px 7px;
            }

            .wa-approver {
                padding: .5rem 0;
            }

            .wa-approver .nm {
                font-size: .82rem;
                line-height: 1.3;
            }

            .wa-approver .npk {
                font-size: .68rem;
            }

            .wa-approver .st {
                font-size: .7rem;
                padding: 4px 7px;
            }
        }

        .wa-steps { margin-top: .15rem; }
        .wa-step { display: none; }

        .wa-progress-track { height: 4px; background: #eef0f6; border-radius: 2px; }

        .wa-tl {
            padding: .25rem 0 .1rem;
        }

        .wa-tl-step {
            display: flex;
            gap: .65rem;
            padding: .45rem 0;
            position: relative;
        }

        .wa-tl-step:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 28px;
            bottom: -4px;
            width: 2px;
            background: #e6eaf3;
        }

        .wa-tl-dot {
            width: 22px;
            height: 22px;
            flex: 0 0 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .6rem;
            color: #fff;
            background: #cbd3e1;
            z-index: 1;
        }

        .wa-tl-dot.approved {
            background: #16a34a;
        }

        .wa-tl-dot.rejected {
            background: #dc2626;
        }

        .wa-tl-dot.waiting {
            background: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .18);
        }

        .wa-tl-dot.upcoming {
            background: #d5dbe7;
        }

        .wa-tl-dot.mine {
            outline: 2px solid #4e73df;
            outline-offset: 2px;
        }

        .wa-tl-title {
            font-size: .84rem;
            font-weight: 800;
            color: #2e384d;
            line-height: 1.25;
        }

        .wa-tl-role {
            font-size: .62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #4e73df;
            margin-right: .3rem;
        }

        .wa-tl-sub {
            font-size: .72rem;
            color: #6b7688;
            margin-top: .05rem;
        }

        .wa-tl-status {
            font-size: .66rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            white-space: nowrap;
        }

        .wa-tl-status.approved {
            color: #16a34a;
        }

        .wa-tl-status.rejected {
            color: #dc2626;
        }

        .wa-tl-status.waiting {
            color: #d97706;
        }

        .wa-tl-status.upcoming {
            color: #9aa5b8;
        }

        .wa-tl-reason {
            margin-top: .25rem;
            font-size: .73rem;
            background: #fdeceb;
            border-left: 3px solid #dc2626;
            border-radius: .25rem;
            padding: .3rem .5rem;
            color: #93211c;
        }

        .wa-date-chip {
            display: inline-block;
            background: #f4f6fd;
            border: 1px solid #cdd7ef;
            border-radius: 2rem;
            padding: .1rem .5rem;
            font-size: .7rem;
            font-weight: 700;
            color: #224abe;
            margin: 0 .15rem .2rem 0;
        }
    </style>

    <!-- Page level plugins -->
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {
            var getDataUrl = @json(route('work-assignment.approval.get-data'));
            var detailBase = @json(url('work-assignment/approval/detail'));
            var approveBase = @json(url('work-assignment/approval/approve'));
            var rejectBase = @json(url('work-assignment/approval/reject'));
            var updateBase = @json(url('work-assignment/approval/update'));
            var attachmentBase = @json(url('work-assignment/manage/attachment'));
            var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            var dtable = $('#dataTable').DataTable({
                processing: true,
                serverSide: false,
                autoWidth: false,
                ajax: {
                    url: getDataUrl,
                    type: 'GET',
                    data: function (d) {
                        d.status = $('#filterStatus').val();
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
                        render: function (data, type, row) {
                            if (type !== 'display') return data;
                            return '<strong>' + escapeHtml(data) + '</strong><br>'
                                + '<small class="text-muted">' + escapeHtml(row.npk) + ' &middot; '
                                + escapeHtml(row.jabatan || '-') + '</small>';
                        }
                    },
                    {
                        data: 'created_at',
                        render: function (d) {
                            if (!d) return '-';
                            var p = d.split(' ');
                            var q = p[0].split('-');
                            return q[2] + '-' + q[1] + '-' + q[0] + '<br><small class="text-muted">'
                                + (p[1] || '') + '</small>';
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
                    {
                        data: 'total_days',
                        className: 'text-center',
                        render: function (d) { return d + ' hr'; }
                    },
                    {
                        data: 'jam_keluar',
                        render: function (d, t, row) {
                            var a = d || '--:--';
                            var b = row.rencana_kembali || '--:--';
                            return a + '<br><small class="text-muted">&rarr; ' + b + '</small>';
                        }
                    },
                    {
                        data: 'steps',
                        orderable: false,
                        searchable: false,
                        render: function (d, t, row) {
                            // Nama + status per level; tanpa progress bar.
                            return waApproverBadges(d);
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (d, t, row) {
                            var btn = '<button class="btn btn-sm btn-info btn-detail" data-id="' + row.id + '">'
                                + '<i class="fas fa-eye"></i> Detail</button> ';
                            if (row.is_actionable) {
                                btn += '<button class="btn btn-sm btn-success btn-approve" data-id="' + row.id + '" '
                                    + 'data-nama="' + escapeHtml(row.nama) + '" data-level="' + row.my_level + '">'
                                    + '<i class="fas fa-check"></i></button> ';
                                btn += '<button class="btn btn-sm btn-danger btn-reject" data-id="' + row.id + '" '
                                    + 'data-nama="' + escapeHtml(row.nama) + '" data-level="' + row.my_level + '">'
                                    + '<i class="fas fa-times"></i></button>';
                            } else if (row.is_waiting_prev) {
                                btn += '<span class="badge badge-light border" title="Menunggu level sebelumnya disetujui">'
                                    + '<i class="fas fa-hourglass-half"></i></span>';
                            }
                            return btn;
                        }
                    }
                ]
            });

            $('#filterStatus').on('change', function () {
                dtable.ajax.reload(null, true);
            });

            function escapeHtml(s) {
                var d = document.createElement('div');
                d.textContent = s == null ? '' : s;
                return d.innerHTML;
            }

            // ── Modal detail ──
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
                        (d.duty_dates || []).forEach(function (x) {
                            html += '<span class="wa-date-chip">' + escapeHtml(x) + '</span>';
                        });
                        html += ' (' + d.total_days + ' hari)</td></tr>';
                        html += '<tr><th>Periode</th><td>' + escapeHtml(d.start_date) + ' s/d ' + escapeHtml(d.end_date) + '</td></tr>';
                        html += '<tr><th>Dinas Ke</th><td>' + escapeHtml(d.dinas_ke || '-') + '</td></tr>';
                        html += '<tr><th>Keperluan</th><td>' + escapeHtml(d.keperluan || '-') + '</td></tr>';
                        html += '<tr><th>Jam Keluar / Rencana Kembali</th><td>'
                            + escapeHtml(d.jam_keluar || '--:--') + ' &ndash; ' + escapeHtml(d.rencana_kembali || '--:--') + ' WIB</td></tr>';
                        html += '<tr><th>Jam Kembali</th><td>'
                            + (d.jam_kembali ? escapeHtml(d.jam_kembali) + ' WIB' : '<span class="text-muted">- (diisi Security)</span>') + '</td></tr>';
                        html += '<tr><th>Diajukan</th><td>' + escapeHtml(d.created_at || '-') + '</td></tr>';
                        html += '</table>';

                        html += '<h6 class="mt-4 font-weight-bold">Lampiran:</h6>';
                        html += waRenderAttachments(d.attachments, {
                            downloadBase: attachmentBase + '/' + d.id,
                            deletable: false
                        });

                        html += '<h6 class="mt-4 font-weight-bold">Alur Persetujuan:</h6>';
                        html += '<div class="wa-tl">';
                        (d.timeline || []).forEach(function (s) {
                            var icon = { approved: 'fa-check', rejected: 'fa-times', waiting: 'fa-clock', skipped: 'fa-minus' }[s.decision] || 'fa-circle';
                            var label = { approved: 'Disetujui', rejected: 'Ditolak', waiting: 'Menunggu', skipped: 'Dilewati' }[s.decision] || 'Belum Aktif';
                            var mineCls = s.is_mine ? ' mine' : '';
                            html += '<div class="wa-tl-step">';
                            html += '<div class="wa-tl-dot ' + s.decision + mineCls + '"><i class="fas ' + icon + '"></i></div>';
                            html += '<div style="flex:1 1 auto;min-width:0">';
                            html += '<div class="wa-tl-title"><span class="wa-tl-role">L' + s.level + ' &middot; ' + escapeHtml(s.role_label) + '</span>'
                                + escapeHtml(s.name || '-') + (s.is_mine ? ' <span class="badge badge-primary">Anda</span>' : '') + '</div>';
                            html += '<div class="wa-tl-sub">' + escapeHtml(s.npk || '-');
                            if (s.jabatan && s.jabatan !== s.name) html += ' &middot; <em>' + escapeHtml(s.jabatan) + '</em>';
                            if (s.decided_at) html += ' &middot; ' + escapeHtml(s.decided_at);
                            html += '</div>';
                            if (s.reason) html += '<div class="wa-tl-reason"><b>Alasan:</b> ' + escapeHtml(s.reason) + '</div>';
                            html += '</div>';
                            html += '<div class="wa-tl-status ' + s.decision + '">' + label + '</div>';
                            html += '</div>';
                        });
                        html += '</div>';

                        $('#detailContent').html(html);

                        var footer = '';
                        if (d.is_actionable) {
                            footer += '<button type="button" class="btn btn-success btn-approve" data-id="' + d.id + '" '
                                + 'data-nama="' + escapeHtml(d.nama) + '" data-level="' + d.my_level + '">'
                                + '<i class="fas fa-check mr-1"></i> Setujui</button> ';
                            footer += '<button type="button" class="btn btn-danger btn-reject" data-id="' + d.id + '" '
                                + 'data-nama="' + escapeHtml(d.nama) + '" data-level="' + d.my_level + '">'
                                + '<i class="fas fa-times mr-1"></i> Tolak</button> ';
                        } else if (d.my_decision && (d.my_decision === 'approved' || d.my_decision === 'rejected')) {
                            footer += '<button type="button" class="btn btn-outline-warning btn-ubah" data-id="' + d.id + '" '
                                + 'data-status="' + d.my_decision + '">'
                                + '<i class="fas fa-edit mr-1"></i> Ubah Keputusan</button> ';
                        } else {
                            footer += '<span class="text-muted small mr-auto"><i class="fas fa-info-circle mr-1"></i>'
                                + 'Menunggu persetujuan level sebelumnya.</span> ';
                        }
                        footer += '<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>';
                        $('#detailModalFooter').html(footer);
                    },
                    error: function () {
                        $('#detailContent').html('<div class="alert alert-danger">Terjadi kesalahan saat memuat data.</div>');
                    }
                });
            });

            // ── Setujui ──
            $(document).on('click', '.btn-approve', function () {
                var id = $(this).data('id');
                var nama = $(this).data('nama');
                var level = $(this).data('level');

                Swal.fire({
                    title: 'Setujui pengajuan?',
                    html: 'Anda akan menyetujui pengajuan Work Assignment dari <b>' + escapeHtml(nama) + '</b>'
                        + ' pada level <b>' + level + '</b>.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#1cc88a',
                    confirmButtonText: 'Ya, Setujui',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    input: 'text',
                    inputPlaceholder: 'Catatan (opsional)',
                    inputAttributes: { 'aria-label': 'Catatan' }
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: approveBase + '/' + id,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF },
                        data: { comment: result.value || '' },
                        success: function (res) {
                            if (res.success !== false) {
                                Swal.fire({ icon: 'success', title: 'Disetujui', text: res.message, timer: 2200, showConfirmButton: false });
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

            // ── Tolak ──
            $(document).on('click', '.btn-reject', function () {
                var id = $(this).data('id');
                var nama = $(this).data('nama');
                var level = $(this).data('level');

                Swal.fire({
                    title: 'Tolak pengajuan?',
                    html: 'Pengajuan Work Assignment dari <b>' + escapeHtml(nama) + '</b> pada level <b>' + level + '</b> '
                        + 'akan DITOLAK. Rantai persetujuan berhenti di level ini.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    confirmButtonText: 'Ya, Tolak',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    input: 'textarea',
                    inputPlaceholder: 'Alasan penolakan (WAJIB diisi)',
                    inputAttributes: { 'aria-label': 'Alasan' },
                    inputValidator: function (v) {
                        if (!v || !v.trim()) return 'Alasan penolakan wajib diisi.';
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: rejectBase + '/' + id,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF },
                        data: { reason: result.value },
                        success: function (res) {
                            if (res.success !== false) {
                                Swal.fire({ icon: 'success', title: 'Ditolak', text: res.message, timer: 2200, showConfirmButton: false });
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

            // ── Ubah keputusan ──
            $(document).on('click', '.btn-ubah', function () {
                var id = $(this).data('id');
                var status = $(this).data('status');

                Swal.fire({
                    title: 'Ubah keputusan?',
                    text: 'Keputusan saat ini: ' + (status === 'approved' ? 'Disetujui' : 'Ditolak')
                        + '. Pilih status baru (alasan wajib bila menjadi Ditolak).',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#f6c23e',
                    confirmButtonText: 'Terapkan',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    html: '<select id="swal-new-status" class="swal2-select" style="margin-top:.6rem">'
                        + '<option value="approved">Disetujui</option>'
                        + '<option value="rejected">Ditolak</option></select>'
                        + '<textarea id="swal-reason" class="swal2-textarea" placeholder="Alasan (wajib bila Ditolak)" '
                        + 'style="margin-top:.6rem;width:100%;min-height:80px"></textarea>',
                    preConfirm: function () {
                        var ns = document.getElementById('swal-new-status').value;
                        var rs = document.getElementById('swal-reason').value;
                        if (ns === 'rejected' && (!rs || !rs.trim())) {
                            Swal.showValidationMessage('Alasan wajib diisi bila menjadi Ditolak.');
                            return false;
                        }
                        return { new_status: ns, reason: rs };
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: updateBase + '/' + id,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF },
                        data: result.value,
                        success: function (res) {
                            if (res.success !== false) {
                                Swal.fire({ icon: 'success', title: 'Diperbarui', text: res.message, timer: 2200, showConfirmButton: false });
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
