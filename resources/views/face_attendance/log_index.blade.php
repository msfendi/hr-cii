@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h3 mb-1 text-gray-800">Log Face Attendance</h1>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Daftar transaksi presensi wajah per scan (DataTables server-side).
                Gunakan filter tanggal untuk melihat data pada periode tertentu.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('face.attendance.admin.index') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-users-cog mr-1"></i> Rekap Semua Karyawan
            </a>
            <a href="{{ route('face-recognition.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Wajah
            </a>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card border-left-success shadow-sm h-100"><div class="card-body py-2"><div class="text-xs font-weight-bold text-success text-uppercase">Clock In</div><div class="h5 mb-0 font-weight-bold" id="statIn">{{ $totalIn ?? 0 }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-primary shadow-sm h-100"><div class="card-body py-2"><div class="text-xs font-weight-bold text-primary text-uppercase">Clock Out</div><div class="h5 mb-0 font-weight-bold" id="statOut">{{ $totalOut ?? 0 }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-warning shadow-sm h-100"><div class="card-body py-2"><div class="text-xs font-weight-bold text-warning text-uppercase">Wajah</div><div class="h5 mb-0 font-weight-bold" id="statWajah">{{ $totalWajah ?? 0 }}</div></div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow-sm h-100"><div class="card-body py-2"><div class="text-xs font-weight-bold text-info text-uppercase">Fingerprint</div><div class="h5 mb-0 font-weight-bold" id="statFinger">{{ $totalFinger ?? 0 }}</div></div></div>
        </div>
    </div>

    <div class="card shadow mb-4 border-left-primary">
        <div class="card-body">
            <form class="row align-items-end" id="filterForm">
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="font-weight-bold text-gray-700 small d-block mb-2" for="dateFrom">Dari Tanggal</label>
                    <input type="date" id="dateFrom" value="{{ $dateFrom ?? '' }}" class="form-control form-control-sm rounded-pill" style="height: 31px;">
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="font-weight-bold text-gray-700 small d-block mb-2" for="dateTo">Sampai Tanggal</label>
                    <input type="date" id="dateTo" value="{{ $dateTo ?? '' }}" class="form-control form-control-sm rounded-pill" style="height: 31px;">
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterMode">Mode</label>
                    <select id="filterMode" class="form-control form-control-sm rounded-pill" style="height: 31px;">
                        <option value="">Semua Mode</option>
                        <option value="wajah" {{ ($mode ?? '') === 'wajah' ? 'selected' : '' }}>Wajah</option>
                        <option value="fingerprint" {{ ($mode ?? '') === 'fingerprint' ? 'selected' : '' }}>Fingerprint</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterStatus">Status</label>
                    <select id="filterStatus" class="form-control form-control-sm rounded-pill" style="height: 31px;">
                        <option value="">Semua Status</option>
                        <option value="success" {{ ($status ?? '') === 'success' ? 'selected' : '' }}>Berhasil</option>
                        <option value="failed" {{ ($status ?? '') === 'failed' ? 'selected' : '' }}>Gagal</option>
                        <option value="ditolak" {{ ($status ?? '') === 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterDepartment">Departemen</label>
                    <select id="filterDepartment" class="form-control form-control-sm rounded-pill" style="height: 31px;">
                        <option value="">Semua Departemen</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->ID_DEPT }}" {{ (string)($department ?? '') === (string)$d->ID_DEPT ? 'selected' : '' }}>{{ $d->DEPARTEMENT }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterSearch">Cari (Nama / NPK / Barcode)</label>
                    <input type="text" id="filterSearch" value="{{ $search ?? '' }}" class="form-control form-control-sm rounded-pill" style="height: 31px;" placeholder="cth: Dimas / C-00827">
                </div>
                <div class="col-md-12 mt-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4"><i class="fas fa-filter mr-1"></i> Tampilkan</button>
                    <button type="button" id="btnResetFilter" class="btn btn-outline-secondary btn-sm rounded-pill px-4"><i class="fas fa-times mr-1"></i> Reset</button>
                    <a href="#" id="btnExportExcel" class="btn btn-success btn-sm rounded-pill px-4"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
                </div>
            </form>
            <div id="logErrorBanner" class="alert alert-danger border-left-danger shadow-sm mt-3 mb-0 {{ !empty($logError) ? '' : 'd-none' }}" role="alert">{{ $logError ?? '' }}</div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Data Log</h6>
            <span class="badge badge-primary" id="totalBadge">{{ $pagination['total'] ?? 0 }} transaksi</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="dataTableLog" class="table table-bordered table-hover w-100">
                    <thead class="thead-primary">
                        <tr>
                            <th style="min-width:50px">No</th>
                            <th style="min-width:90px">Tanggal</th>
                            <th style="min-width:70px">Waktu</th>
                            <th style="min-width:160px">Karyawan</th>
                            <th style="min-width:110px">NPK / Barcode</th>
                            <th style="min-width:120px">Departemen</th>
                            <th style="min-width:90px">Mode</th>
                            <th style="min-width:90px">Aksi</th>
                            <th style="min-width:90px">Verifymode</th>
                            <th style="min-width:110px">Status GPS</th>
                            <th style="min-width:100px">Foto</th>
                            <th style="min-width:90px">Confidence</th>
                            <th style="min-width:100px">Status</th>
                            <th style="min-width:130px">IP Address</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFaceLog" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-header bg-primary"><h5 class="modal-title text-white">Foto Wajah</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body text-center"><img id="modalFaceLogImg" src="" alt="Foto wajah" class="img-fluid rounded shadow border d-block mx-auto" style="max-height:380px;"><div class="mt-2 font-weight-bold" id="modalFaceLogName">-</div><div class="small text-muted" id="modalFaceLogNpk">-</div></div></div></div></div>
@endsection

@push('scripts')
{{-- PERBAIKAN (2026-11-09): DataTables dimuat SETELAH jQuery.
     layouts/app memuat jQuery synchron di layout.footerscript
     (sebelum @stack('scripts')), sehingga $ dan $.fn.DataTable
     tersedia di sini. Urutan: jQuery -> DataTables core -> bootstrap4 skin. --}}
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(function () {
    var EXPORT_URL = "{{ route('face.attendance.admin.log.export') }}";
    var DATA_URL = "{{ route('face.attendance.admin.log.data') }}";

    function currentFilters() {
        return {
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            mode: $('#filterMode').val(),
            status: $('#filterStatus').val(),
            department_id: $('#filterDepartment').val(),
            q: $('#filterSearch').val()
        };
    }

    function updateExportHref() {
        var f = currentFilters();
        var params = [];
        Object.keys(f).forEach(function (k) {
            if (f[k] !== '' && f[k] !== null) params.push(encodeURIComponent(k) + '=' + encodeURIComponent(f[k]));
        });
        $('#btnExportExcel').attr('href', EXPORT_URL + (params.length ? '?' + params.join('&') : ''));
    }

    // Guard anti-reinitialise (datatables.net/tn/3): bila script ini
    // dimuat dua kali (mis. cache browser lama), sudah ada instance ->
    // jangan buat baru, tapi JANGAN hangus filter.
    var $logTable = $('#dataTableLog');
    if ($logTable.length && $.fn.DataTable.isDataTable($logTable[0])) {
        var existing = $logTable.DataTable();
        existing.ajax.reload();
        $('#filterForm').on('submit', function (e) { e.preventDefault(); updateExportHref(); existing.ajax.reload(); });
        $('#btnResetFilter').on('click', function () {
            $('#dateFrom, #dateTo').val('');
            $('#filterMode, #filterStatus, #filterDepartment').val('');
            $('#filterSearch').val('');
            updateExportHref();
            existing.ajax.reload();
        });
        return;
    }

    var table = $logTable.DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        order: [[1, 'desc'], [2, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        ajax: {
            url: DATA_URL,
            type: 'GET',
            data: function (d) {
                var f = currentFilters();
                d.date_from = f.date_from;
                d.date_to = f.date_to;
                d.mode = f.mode;
                d.status = f.status;
                d.department_id = f.department_id;
                d.q = f.q;
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center', width: '50px' },
            { data: 'tanggal', name: 'tanggal', width: '95px' },
            { data: 'waktu', name: 'waktu', width: '80px' },
            { data: 'karyawan_html', name: 'karyawan', orderable: false, searchable: false },
            { data: 'npk_barcode', name: 'npk_barcode', orderable: false, searchable: false },
            { data: 'departemen', name: 'departemen', orderable: false, searchable: false },
            { data: 'mode_html', name: 'mode_label', orderable: false, searchable: false },
            { data: 'action_label', name: 'action_label', orderable: false, searchable: false },
            { data: 'verifymode', name: 'verifymode', orderable: false, searchable: false, className: 'text-center' },
            { data: 'gps_html', name: 'gps', orderable: false, searchable: false },
            { data: 'foto_html', name: 'foto', orderable: false, searchable: false, className: 'text-center' },
            { data: 'confidence_html', name: 'confidence', orderable: false, searchable: false, className: 'text-center' },
            { data: 'status_html', name: 'status', orderable: false, searchable: false },
            { data: 'ip_address', name: 'ip_address', orderable: false, searchable: false }
        ],
        language: {
            search: 'Cari cepat:',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ scan',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Tidak ada data yang cocok',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
            processing: 'Memuat log...'
        },
        drawCallback: function (settings) {
            $('#totalBadge').text(settings.json.recordsTotal + ' transaksi');
            // Lazy-load foto wajah: decode base64 hanya saat thumbnail masuk viewport
            // (sama seperti perilaku partial lama, dipertahankan agar halaman tetap ringan).
            window.__faceThumbObserver && window.__faceThumbObserver.disconnect();
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) {
                        var img = en.target;
                        img.setAttribute('src', img.getAttribute('data-src'));
                        io.unobserve(img);
                    }
                });
            }, { rootMargin: '200px' });
            window.__faceThumbObserver = io;
            document.querySelectorAll('.face-thumb[data-src]').forEach(function (img) {
                if (!img.getAttribute('src')) io.observe(img);
            });
        }
    });

    // Preview foto wajah (delegasi: baris di-render ulang DataTables tiap draw)
    $(document).on('click', '.btn-preview-face', function () {
        var d = $(this).attr('data-img');
        if (d && d.indexOf('data:') !== 0) d = 'data:image/jpeg;base64,' + d;
        $('#modalFaceLogImg').attr('src', d || '');
        $('#modalFaceLogName').text($(this).attr('data-name') || '-');
        $('#modalFaceLogNpk').text('NPK: ' + ($(this).attr('data-npk') || '-'));
        $('#modalFaceLog').modal('show');
    });

    $('#filterForm').on('submit', function (e) {
        e.preventDefault();
        updateExportHref();
        table.ajax.reload();
    });

    $('#btnResetFilter').on('click', function () {
        $('#dateFrom, #dateTo').val('');
        $('#filterMode, #filterStatus, #filterDepartment').val('');
        $('#filterSearch').val('');
        updateExportHref();
        table.ajax.reload();
    });

    updateExportHref();
});
</script>
@endpush
