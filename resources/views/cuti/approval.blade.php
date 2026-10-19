<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">

    <!-- Page Wrapper -->
    @include('sweetalert::alert')
    <div id="wrapper">
        @if (!empty($isPortal))
            @include('layout.sidebar-cuti')
        @else
            @include('layout.sidebar')
        @endif

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                @if (!empty($isPortal))
                    @include('layout.topbar-cuti')
                @else
                    @include('layout.navbar')
                @endif

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

                            <!-- Filters -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="filterStart">Dari Tanggal</label>
                                    <input type="date" class="form-control form-control-sm" id="filterStart">
                                </div>
                                <div class="col-md-3">
                                    <label for="filterEnd">Sampai Tanggal</label>
                                    <input type="date" class="form-control form-control-sm" id="filterEnd">
                                </div>
                                <div class="col-md-3">
                                    <label for="filterStatus">Status</label>
                                    <select class="form-control form-control-sm" id="filterStatus">
                                        <option value="pending" selected>Menunggu Approval</option>
                                        <option value="approved">Disetujui</option>
                                        <option value="rejected">Ditolak</option>
                                        <option value="all">Semua Status</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="button" id="btnFilter" class="btn btn-primary btn-sm mr-2 w-100">
                                        <i class="fas fa-filter fa-sm"></i> Filter
                                    </button>
                                    <button type="button" id="btnResetFilter" class="btn btn-secondary btn-sm w-100">
                                        <i class="fas fa-undo fa-sm"></i> Reset
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="dataTable" width="100%"
                                    cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:35px;">#</th>
                                            <th style="width:150px;">Karyawan</th>
                                            <th style="width:100px;">Tgl Pengajuan</th>
                                            <th style="width:110px;">Jenis Cuti</th>
                                            <th class="text-center" style="width:110px;">Periode</th>
                                            <th class="text-center" style="width:90px;">Sisa Cuti</th>
                                            <th class="text-center" style="width:80px;">Total Hari</th>
                                            <th style="width:120px;">Alasan</th>
                                            <th class="text-center" style="width:250px; min-width:230px;">Approval
                                                Progress</th>
                                            <th class="text-center" style="width:110px;">Decision</th>
                                            <th class="text-center" style="width:90px;">Aksi</th>
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

    <!-- Modal Detail Pengajuan Cuti -->
    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" id="detailModalHeader">
                    <h5 class="modal-title" id="detailModalLabel">
                        <i class="fas mr-1" id="detailModalIcon"></i> Detail Pengajuan Cuti
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="mb-0" id="detailNama">-</h5>
                            <div class="text-muted small" id="detailNpkDept">-</div>
                        </div>
                        <div id="detailStatusBadge"></div>
                    </div>
                    <hr class="mt-0">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Jenis Cuti</div>
                            <div class="font-weight-bold" id="detailJenis">-</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Total Hari</div>
                            <div class="font-weight-bold" id="detailHari">-</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Periode Cuti</div>
                            <div class="font-weight-bold" id="detailPeriode">-</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="text-muted small">Diajukan Pada</div>
                            <div class="font-weight-bold" id="detailDiajukan">-</div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="text-muted small">Alasan Pengajuan</div>
                            <div id="detailAlasan">-</div>
                        </div>
                        <div class="col-md-12 mb-3" id="detailApproversWrap">
                            <div class="text-muted small mb-1">Status Tiap Approver</div>
                            <div id="detailApproversContent" class="p-2 border rounded bg-light"></div>
                        </div>
                        <div class="col-md-12 mb-3" id="detailAttachWrap">
                            <div class="text-muted small mb-1">Lampiran Dokumen</div>
                            <div id="detailAttachContent"></div>
                        </div>
                        <div class="col-md-12" id="detailKomentarWrap" style="display:none;">
                            <div class="text-muted small">Komentar Penolakan</div>
                            <div class="text-danger" id="detailKomentar">-</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" id="detailModalFooter">
                    <!-- diisi dinamis lewat JS: Setujui/Tolak, Ubah Keputusan, atau cuma Tutup -->
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
                <div class="modal-body p-2 text-center bg-dark"
                    style="min-height: 250px; display:flex; align-items:center; justify-content:center;">
                    <img id="imageZoomSrc" src=""
                        style="max-width: 100%; max-height: 75vh; border-radius: 4px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);"
                        alt="Zoom">
                </div>
            </div>
        </div>
    </div>

    <style>
        #dataTable {
            font-size: 0.94rem;
            color: #2e384d;
        }

        #dataTable thead th {
            font-size: 0.92rem;
            font-weight: 700;
            background-color: #f8f9fc;
            color: #4e73df;
            vertical-align: middle;
            padding: 10px 8px;
        }

        #dataTable tbody td {
            font-size: 0.93rem;
            vertical-align: middle;
            padding: 10px 8px;
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.95rem;
        }
    </style>

    <!-- Page level plugins -->
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {

            var ajaxUrl = "{{ $ajaxUrl ?? route('pengajuan-cuti.approval') }}";
            var actionBaseUrl = "{{ $actionBaseUrl ?? url('pengajuan-cuti/approval') }}";

            /* Initialize Server-Side DataTable */
            var dtable = $('#dataTable').DataTable({
                processing: true,
                serverSide: true,
                autoWidth: false,
                ajax: {
                    url: ajaxUrl,
                    data: function (d) {
                        d.start_date = $('#filterStart').val();
                        d.end_date = $('#filterEnd').val();
                        d.status = $('#filterStatus').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '35px', className: 'text-center' },
                    { data: 'karyawan', name: 'nama', width: '150px' },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        width: '100px',
                        render: function (data) {
                            return '<span style="font-size:0.9rem; font-weight:500;">' + (data || '-') + '</span>';
                        }
                    },
                    {
                        data: 'leave_type',
                        name: 'leave_type',
                        width: '110px',
                        render: function (data, type, row) {
                            var badge = '';
                            if (row.is_negative_leave) {
                                badge += '<br><span class="badge badge-warning text-dark font-weight-bold mt-1" style="font-size:0.75rem;"><i class="fas fa-exclamation-triangle fa-xs mr-1"></i>Hutang Cuti</span>';
                            }
                            if (row.attach_files && row.attach_files.length > 0) {
                                var count = row.attach_files.length;
                                badge += '<br><span class="badge badge-light border text-primary mt-1" style="font-size:0.8rem;" title="' + count + ' file dilampirkan"><i class="fas fa-paperclip"></i> ' + count + ' berkas</span>';
                            }
                            return '<span class="font-weight-bold" style="font-size:0.94rem;">' + (data || '-') + '</span>' + badge;
                        }
                    },
                    { data: 'periode', name: 'periode', orderable: false, searchable: false, width: '110px', className: 'text-center' },
                    { data: 'sisa_cuti', name: 'sisa_cuti', orderable: false, searchable: false, width: '90px', className: 'text-center' },
                    { data: 'hari', name: 'hari', orderable: false, searchable: false, width: '80px', className: 'text-center' },
                    { data: 'alasan', name: 'alasan', orderable: false, searchable: false, width: '120px' },
                    { data: 'status_approver', name: 'status_approver', orderable: false, searchable: false, width: '250px' },
                    { data: 'status_utama', name: 'status_utama', orderable: false, searchable: false, width: '110px', className: 'text-center' },
                    { data: 'aksi', name: 'aksi', orderable: false, searchable: false, width: '90px', className: 'text-center' }
                ],
                language: {
                    search: 'Cari:',
                    zeroRecords: 'Tidak ada permohonan cuti yang perlu diproses.',
                }
            });

            // Filter tanggal
            $('#btnFilter').on('click', function () {
                dtable.ajax.reload();
            });
            $('#btnResetFilter').on('click', function () {
                $('#filterStart, #filterEnd').val('');
                $('#filterStatus').val('pending');
                dtable.ajax.reload();
            });

            // Helper function for AJAX actions
            function handleAction(url, postData, successMessage) {
                var dataToSend = $.extend({ _token: '{{ csrf_token() }}' }, postData);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: dataToSend,
                    success: function (response) {
                        if (response.success) {
                            $('#detailModal').modal('hide');
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

            // Warna header modal & footer aksi menyesuaikan status permohonan
            function statusMeta(status, isWaitingPrev) {
                if (status === 'approved') return { header: 'bg-success text-white', icon: 'fa-check-circle', close: 'text-white' };
                if (status === 'rejected') return { header: 'bg-danger text-white', icon: 'fa-times-circle', close: 'text-white' };
                if (isWaitingPrev) return { header: 'bg-secondary text-white', icon: 'fa-clock', close: 'text-white' };
                return { header: 'bg-warning text-dark', icon: 'fa-hourglass-half', close: '' };
            }

            // Detail button — buka modal, isi dari data yang sudah ada di DataTable (tanpa request baru)
            $(document).on('click', '.btn-detail', function () {
                var tr = $(this).closest('tr');
                var row = dtable.row(tr).data();
                var meta = statusMeta(row.status, row.is_waiting_previous);

                $('#detailModalHeader').removeClass('bg-success bg-danger bg-warning bg-secondary text-white text-dark').addClass(meta.header);
                $('#detailModalIcon').attr('class', 'fas mr-1 ' + meta.icon);
                $('#detailModalHeader .close').removeClass('text-white').addClass(meta.close);

                $('#detailNama').text(row.nama);
                $('#detailNpkDept').text(row.npk + ' · ' + row.dept);
                $('#detailStatusBadge').html(row.status_utama);
                $('#detailJenis').text(row.leave_type);
                $('#detailHari').text(row.total_days ? (row.total_days + ' hari') : '-');
                var startText = row.start_date_formatted || row.start_date || '-';
                var endText = row.end_date_formatted || row.end_date || '-';
                $('#detailPeriode').text(startText + ' s/d ' + endText);
                $('#detailDiajukan').text(row.created_at || '-');
                $('#detailAlasan').text(row.reason || '-');
                $('#detailApproversContent').html(row.status_approver || '<span class="text-muted">-</span>');

                // Render lampiran dokumen
                if (row.attach_files && row.attach_files.length > 0) {
                    var attachHtml = '<div class="d-flex flex-wrap" style="gap: 8px;">';
                    $.each(row.attach_files, function (i, file) {
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
                    $('#detailAttachContent').html(attachHtml);
                } else {
                    $('#detailAttachContent').html('<span class="text-muted font-italic">Tidak ada dokumen dilampirkan</span>');
                }

                if (row.status === 'rejected' && row.comment) {
                    $('#detailKomentar').text(row.comment);
                    $('#detailKomentarWrap').show();
                } else {
                    $('#detailKomentarWrap').hide();
                }

                var footer = '';
                if (row.is_waiting_previous) {
                    footer += '<span class="text-muted small mr-auto"><i class="fas fa-info-circle text-info mr-1"></i>Permohonan ini masih menunggu persetujuan pada Level sebelumnya.</span> ';
                } else if (row.status === 'pending') {
                    footer += '<button type="button" class="btn btn-success btn-approve" data-id="' + row.id + '" data-nama="' + row.nama + '"><i class="fas fa-check"></i> Setujui</button> ';
                    footer += '<button type="button" class="btn btn-danger btn-reject" data-id="' + row.id + '" data-nama="' + row.nama + '"><i class="fas fa-times"></i> Tolak</button> ';
                } else if (row.can_update) {
                    footer += '<button type="button" class="btn btn-outline-warning btn-ubah" data-id="' + row.id + '" data-nama="' + row.nama + '" data-status="' + row.status + '"><i class="fas fa-edit"></i> Ubah Keputusan</button> ';
                }
                footer += '<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>';
                $('#detailModalFooter').html(footer);

                $('#detailModal').modal('show');
            });

            // Zoom foto preview
            $(document).on('click', '.img-preview-thumb', function () {
                var url = $(this).data('url');
                var name = $(this).data('name') || 'Preview Foto';
                $('#imageZoomSrc').attr('src', url);
                $('#imageZoomTitle').html('<i class="far fa-file-image text-primary mr-1"></i> ' + name);
                $('#imageZoomModal').modal('show');
            });

            // Approve button (dari kolom Aksi ATAU dari footer modal Detail)
            $(document).on('click', '.btn-approve', function () {
                var id = $(this).data('id');
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
                        handleAction(actionBaseUrl + '/approve/' + id, {}, 'Permohonan berhasil disetujui.');
                    }
                });
            });

            // Reject button (dari kolom Aksi ATAU dari footer modal Detail)
            $(document).on('click', '.btn-reject', function () {
                var id = $(this).data('id');
                var nama = $(this).data('nama');

                Swal.fire({
                    title: 'Tolak Cuti?',
                    html: 'Anda akan menolak permohonan cuti dari <b>' + nama + '</b>.<br><br>Silakan masukkan alasan/komentar penolakan:',
                    input: 'textarea',
                    inputPlaceholder: 'Tulis komentar di sini...',
                    inputAttributes: {
                        'aria-label': 'Tulis komentar penolakan di sini'
                    },
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    confirmButtonText: 'Ya, Tolak',
                    cancelButtonText: 'Batal',
                    inputValidator: (value) => {
                        if (!value.trim()) {
                            return 'Komentar/Alasan penolakan wajib diisi!';
                        }
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        handleAction(actionBaseUrl + '/reject/' + id, { comment: result.value }, 'Permohonan berhasil ditolak.');
                    }
                });
            });

            // Ubah Keputusan button — cuma muncul di footer modal Detail, untuk permohonan
            // yang sudah diproses tapi belum lanjut ke level approval berikutnya
            $(document).on('click', '.btn-ubah', function () {
                var id = $(this).data('id');
                var nama = $(this).data('nama');
                var currentStatus = $(this).data('status');
                var jenis = $(this).data('jenis');
                var mulai = $(this).data('mulai');
                var selesai = $(this).data('selesai');
                var hari = $(this).data('hari');
                var komentar = $(this).data('komentar') || '';
                var statusLabel = currentStatus === 'approved' ? 'Disetujui' : 'Ditolak';

                var infoHtml = '';
                if (jenis && mulai && selesai && hari) {
                    infoHtml = '<div class="alert alert-info text-left p-2 mb-3" style="font-size: 13px;">' +
                        '<strong>Jenis Cuti:</strong> ' + jenis + '<br>' +
                        '<strong>Periode:</strong> ' + mulai + ' s/d ' + selesai + ' (' + hari + ' hari)' +
                        '</div>';
                }

                Swal.fire({
                    title: 'Ubah Keputusan',
                    html:
                        '<p class="text-left" style="font-size: 15px;">Ubah keputusan untuk permohonan cuti dari <b>' + nama + '</b> (status saat ini: <b>' + statusLabel + '</b>).</p>' +
                        infoHtml +
                        '<div class="form-group text-left">' +
                        '<label for="swalNewStatus" style="font-size: 14px; font-weight: bold;">Status Baru</label>' +
                        '<select id="swalNewStatus" class="form-control">' +
                        '<option value="approved"' + (currentStatus === 'approved' ? ' selected' : '') + '>Setujui</option>' +
                        '<option value="rejected"' + (currentStatus === 'rejected' ? ' selected' : '') + '>Tolak</option>' +
                        '</select>' +
                        '</div>' +
                        '<div class="form-group text-left mb-0">' +
                        '<label for="swalComment" style="font-size: 14px; font-weight: bold;">Komentar/Alasan</label>' +
                        '<textarea id="swalComment" class="form-control" rows="3" placeholder="Komentar/alasan perubahan (opsional)"></textarea>' +
                        '</div>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f6c23e',
                    confirmButtonText: 'Simpan Perubahan',
                    cancelButtonText: 'Batal',
                    didOpen: () => {
                        if (komentar) {
                            $('#swalComment').val(komentar);
                        }
                    },
                    preConfirm: () => {
                        return {
                            new_status: document.getElementById('swalNewStatus').value,
                            comment: document.getElementById('swalComment').value
                        };
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        handleAction(actionBaseUrl + '/update/' + id, result.value, 'Keputusan berhasil diubah.');
                    }
                });
            });

        });
    </script>
</body>

</html>