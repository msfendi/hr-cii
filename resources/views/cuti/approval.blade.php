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
                                        <option value="">Semua Status</option>
                                        <option value="pending">Diproses</option>
                                        <option value="approved">Disetujui</option>
                                        <option value="rejected">Ditolak</option>
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
                                <table class="table table-bordered table-sm table-hover" id="dataTable" width="100%"
                                    cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>Karyawan</th>
                                            <th>Tgl Pengajuan</th>
                                            <th>Jenis Cuti</th>
                                            <th>Periode</th>
                                            <th class="text-center" style="width:90px;">Total Hari</th>
                                            <th>Alasan</th>
                                            <th class="text-center" style="width:120px;">Status</th>
                                            <th class="text-center">Aksi</th>
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

    <style>
        .avatar-circle {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
    </style>

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
                ajax: {
                    url: "{{ route('pengajuan-cuti.approval') }}",
                    data: function (d) {
                        d.start_date = $('#filterStart').val();
                        d.end_date = $('#filterEnd').val();
                        d.status = $('#filterStatus').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'karyawan', name: 'nama' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'leave_type', name: 'leave_type' },
                    { data: 'periode', name: 'periode', orderable: false, searchable: false },
                    { data: 'hari', name: 'hari', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'alasan', name: 'alasan', orderable: false, searchable: false },
                    { data: 'status_badge', name: 'status_badge', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'aksi', name: 'aksi', orderable: false, searchable: false, className: 'text-center' }
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
                $('#filterStart, #filterEnd, #filterStatus').val('');
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
            function statusMeta(status) {
                if (status === 'approved') return { header: 'bg-success text-white', icon: 'fa-check-circle', close: 'text-white' };
                if (status === 'rejected') return { header: 'bg-danger text-white', icon: 'fa-times-circle', close: 'text-white' };
                return { header: 'bg-warning text-dark', icon: 'fa-hourglass-half', close: '' };
            }

            // Detail button — buka modal, isi dari data yang sudah ada di DataTable (tanpa request baru)
            $(document).on('click', '.btn-detail', function () {
                var tr = $(this).closest('tr');
                var row = dtable.row(tr).data();
                var meta = statusMeta(row.status);

                $('#detailModalHeader').removeClass('bg-success bg-danger bg-warning text-white text-dark').addClass(meta.header);
                $('#detailModalIcon').attr('class', 'fas mr-1 ' + meta.icon);
                $('#detailModalHeader .close').removeClass('text-white').addClass(meta.close);

                $('#detailNama').text(row.nama);
                $('#detailNpkDept').text(row.npk + ' · ' + row.dept);
                $('#detailStatusBadge').html(row.status_badge);
                $('#detailJenis').text(row.leave_type);
                $('#detailHari').text(row.hari);
                $('#detailPeriode').text(row.periode);
                $('#detailDiajukan').text(row.created_at);
                $('#detailAlasan').text(row.alasan || '-');

                if (row.status === 'rejected' && row.comment) {
                    $('#detailKomentar').text(row.comment);
                    $('#detailKomentarWrap').show();
                } else {
                    $('#detailKomentarWrap').hide();
                }

                var footer = '';
                if (row.status === 'pending') {
                    footer += '<button type="button" class="btn btn-success btn-approve" data-id="' + row.id + '" data-nama="' + row.nama + '"><i class="fas fa-check"></i> Setujui</button> ';
                    footer += '<button type="button" class="btn btn-danger btn-reject" data-id="' + row.id + '" data-nama="' + row.nama + '"><i class="fas fa-times"></i> Tolak</button> ';
                } else if (row.can_update) {
                    footer += '<button type="button" class="btn btn-outline-warning btn-ubah" data-id="' + row.id + '" data-nama="' + row.nama + '" data-status="' + row.status + '"><i class="fas fa-edit"></i> Ubah Keputusan</button> ';
                }
                footer += '<button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>';
                $('#detailModalFooter').html(footer);

                $('#detailModal').modal('show');
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
                        handleAction('/pengajuan-cuti/approval/approve/' + id, {}, 'Permohonan berhasil disetujui.');
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
                        handleAction('/pengajuan-cuti/approval/reject/' + id, { comment: result.value }, 'Permohonan berhasil ditolak.');
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
                        handleAction('/pengajuan-cuti/approval/update/' + id, result.value, 'Keputusan berhasil diubah.');
                    }
                });
            });

        });
    </script>
</body>

</html>