<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<style>
    #imrTable { width: 100%; }
    #imrTable thead th {
        font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
        color: #71809a; border-top: 0;
    }
    #imrTable tbody td { font-size: .85rem; vertical-align: middle; }

    .im-badge {
        display: inline-block; font-size: .66rem; font-weight: 800;
        padding: .18rem .5rem; border-radius: 999px; white-space: nowrap;
    }
    .im-badge.success { background: #dcfce7; color: #146c43; }
    .im-badge.warning { background: #fdf3d8; color: #8a6d10; }
    .im-badge.danger  { background: #fde2e1; color: #a12622; }
    .im-badge.secondary { background: #eef1f7; color: #6b7688; }

    .im-step {
        display: flex; align-items: center; gap: .5rem;
        padding: .35rem 0; border-bottom: 1px dashed #e6ecf5; font-size: .82rem;
    }
    .im-step:last-child { border-bottom: 0; }
    .im-step .ic {
        width: 20px; height: 20px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .58rem; color: #fff; flex: 0 0 20px;
    }
    .im-step.approved .ic { background: #1d7a4c; }
    .im-step.rejected .ic { background: #b02a37; }
    .im-step.waiting  .ic { background: #c9a227; }
    .im-step.upcoming .ic,
    .im-step.skipped  .ic { background: #c2cddd; }
    .im-step .who b { display: block; font-size: .82rem; }
    .im-step .who small { color: #71809a; font-size: .73rem; }

    .im-kv {
        display: flex; justify-content: space-between; gap: .8rem;
        padding: .32rem 0; border-bottom: 1px dashed #e6ecf5; font-size: .85rem;
    }
    .im-kv:last-child { border-bottom: 0; }
    .im-kv .k { color: #71809a; }
    .im-kv .v { font-weight: 600; color: #22344d; text-align: right; }
</style>

<body id="page-top">

@include('sweetalert::alert')

<div id="wrapper">
    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @include('layout.navbar')

            <div class="container-fluid">

                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">Rekap Izin Meninggalkan Tugas</h1>
                        <p class="mb-0 mt-1 text-muted" style="font-size:.82rem;">
                            Semua pengajuan Surat Ijin Meninggalkan Tugas / Pekerjaan (CII.FM.HR.02.06)
                        </p>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary mb-2 mb-md-0">Daftar Semua Pengajuan</h6>
                        <select id="filterStatus" class="form-control form-control-sm d-inline-block shadow-sm"
                                style="width:auto;">
                            <option value="all" selected>Semua</option>
                            <option value="pending">Menunggu</option>
                            <option value="approved">Disetujui</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="imrTable" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Pemohon</th>
                                        <th>Tanggal</th>
                                        <th>Jam Keluar</th>
                                        <th>Rencana Kembali</th>
                                        <th>Jam Kembali</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        @include('layout.footer')
    </div>
</div>

{{-- Modal detail --}}
<div class="modal fade" id="imrModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Pengajuan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="imrModalBody"></div>
            <div class="modal-footer" id="imrModalFooter">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@include('layout.footerscript')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

<script>
(function () {
    'use strict';

    var urls = {
        data:   @json(route('izin-meninggalkan.recap.get-data')),
        detail: @json(url('/izin-meninggalkan/recap/detail')),
        cancel: @json(url('/izin-meninggalkan/recap/delete')),
    };

    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function esc(v) {
        return String(v === null || v === undefined ? '-' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    var table = $('#imrTable').DataTable({
        processing: true,
        serverSide: false,
        order: [[0, 'desc']],
        ajax: {
            url: urls.data,
            data: function (d) { d.status = $('#filterStatus').val(); },
        },
        columns: [
            { data: 'id', render: function (d) { return '#' + esc(d); } },
            {
                data: null,
                render: function (d) {
                    return '<strong>' + esc(d.nama) + '</strong><br><small class="text-muted">' + esc(d.npk) + '</small>';
                },
            },
            { data: 'tanggal', render: esc },
            { data: 'jam_keluar', render: esc },
            { data: 'rencana_kembali', render: esc },
            {
                data: 'jam_kembali',
                render: function (d) { return d ? esc(d) : '<span class="text-muted">belum kembali</span>'; },
            },
            {
                data: null,
                render: function (d) {
                    return '<span class="im-badge ' + esc(d.status_tone) + '">' + esc(d.status) + '</span>'
                        + '<br><small class="text-muted">' + esc(d.done) + '/' + esc(d.total) + ' level</small>';
                },
            },
            {
                data: null,
                orderable: false,
                render: function (d) {
                    var html = '<button class="btn btn-sm btn-outline-primary" data-imr-detail="' + esc(d.id) + '">Detail</button>';

                    if (d.status !== 'Dibatalkan') {
                        html += ' <button class="btn btn-sm btn-outline-danger" data-imr-cancel="' + esc(d.id) + '">Batalkan</button>';
                    }

                    return html;
                },
            },
        ],
        language: {
            emptyTable: 'Belum ada pengajuan.',
            zeroRecords: 'Tidak ada data.',
            processing: 'Memuat…',
            search: 'Cari:',
            lengthMenu: 'Tampil _MENU_',
            info: '_START_–_END_ dari _TOTAL_',
            infoEmpty: '0 data',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
        },
    });

    $('#filterStatus').on('change', function () { table.ajax.reload(); });

    // ── Detail ──
    $(document).on('click', '[data-imr-detail]', function () {
        var id = $(this).data('imr-detail');

        $.get(urls.detail + '/' + id)
            .done(function (res) {
                if (!res.success) {
                    Swal.fire('Gagal', res.message || 'Tidak bisa memuat detail.', 'error');
                    return;
                }

                var d = res.data;

                var rows = [
                    ['Pemohon', d.nama + ' (' + d.npk + ')'],
                    ['Tanggal', d.tanggal],
                    ['Jam keluar', d.jam_keluar],
                    ['Rencana kembali', d.rencana_kembali || '-'],
                    ['Jam kembali', d.jam_kembali || 'belum kembali'],
                    ['Alasan', d.alasan || '-'],
                    ['Status', d.status],
                    ['Diajukan', d.created_at || '-'],
                    ['ID absensi', d.legacy_ijin_id ? ('ijin #' + d.legacy_ijin_id) : 'belum tercatat'],
                ];

                var html = '';

                rows.forEach(function (r) {
                    html += '<div class="im-kv"><span class="k">' + esc(r[0]) + '</span><span class="v">' + esc(r[1]) + '</span></div>';
                });

                html += '<h6 class="font-weight-bold text-primary mt-3 mb-2">Alur Persetujuan</h6>';

                (d.steps || []).forEach(function (s) {
                    var icon = s.decision === 'approved' ? 'fa-check'
                             : s.decision === 'rejected' ? 'fa-times'
                             : s.decision === 'waiting'  ? 'fa-hourglass-half' : 'fa-minus';

                    var note = 'Level ' + s.level + ' · ' + (s.role_label || '');

                    if (s.decision === 'approved' && s.decided_at) { note += ' · ' + s.decided_at; }
                    if (s.decision === 'rejected') { note += ' · ditolak' + (s.reason ? ': ' + s.reason : ''); }
                    if (s.decision === 'waiting') { note += ' · menunggu'; }
                    if (s.decision === 'upcoming') { note += ' · belum giliran'; }

                    html += '<div class="im-step ' + esc(s.decision) + '">'
                        + '<span class="ic"><i class="fas ' + icon + '"></i></span>'
                        + '<span class="who"><b>' + esc(s.name) + ' <small>(' + esc(s.npk) + ')</small></b>'
                        + '<small>' + esc(note) + '</small></span></div>';
                });

                $('#imrModalBody').html(html);
                $('#imrModal').modal('show');
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Tidak bisa memuat detail.';
                Swal.fire('Gagal', msg, 'error');
            });
    });

    // ── Batalkan (void) ──
    $(document).on('click', '[data-imr-cancel]', function () {
        var id = $(this).data('imr-cancel');

        Swal.fire({
            title: 'Batalkan pengajuan #' + id + '?',
            input: 'textarea',
            inputLabel: 'Alasan pembatalan (opsional)',
            showCancelButton: true,
            confirmButtonText: 'Batalkan pengajuan',
            cancelButtonText: 'Tutup',
            confirmButtonColor: '#e74a3b',
        }).then(function (res) {
            if (!res.isConfirmed) { return; }

            $.post(urls.cancel + '/' + id, { _token: token, reason: res.value || '' })
                .done(function (r) {
                    Swal.fire(r.success ? 'Berhasil' : 'Gagal', r.message || '', r.success ? 'success' : 'error');
                    if (r.success) {
                        $('#imrModal').modal('hide');
                        table.ajax.reload(null, false);
                    }
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal membatalkan.';
                    Swal.fire('Gagal', msg, 'error');
                });
        });
    });
})();
</script>

</body>
</html>