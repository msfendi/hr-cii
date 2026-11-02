<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    div.dataTables_wrapper div.dataTables_filter input {
        border-radius: 20px;
        padding: 5px 15px;
        border: 1px solid #d1d3e2;
    }
    .table-rekap th {
        background-color: #4e73df;
        color: white;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        border: none;
    }
    #miniMapDetail {
        height: 320px;
        width: 100%;
        border-radius: 8px;
        z-index: 500;
    }
    .face-capture-preview {
        max-width: 100%;
        border-radius: 8px;
        border: 3px solid #e3e6f0;
        box-shadow: 0 4px 14px rgba(0,0,0,.12);
    }
    .modal-xl .modal-body {
        max-height: 78vh;
        overflow-y: auto;
    }
</style>

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    @if(Session::has('face_attendance_npk') || auth()->check())
        @include('layout.sidebar')
    @endif

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @if(Session::has('face_attendance_npk') || auth()->check())
                @include('layout.navbar')
            @endif

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-history text-primary mr-2"></i> Rekapitulasi Presensi Karyawan
                    </h1>
                    <div class="mt-2 mt-sm-0">
                        <a href="{{ route('face.attendance.index') }}" class="btn btn-sm btn-primary shadow-sm rounded-pill px-3">
                            <i class="fas fa-camera fa-sm text-white-50 mr-1"></i> Kamera Presensi
                        </a>
                    </div>
                </div>

                <!-- Card Profil Ringkas -->
                <div class="card shadow mb-4 border-left-primary">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8 mb-3 mb-md-0">
                                <h5 class="font-weight-bold text-gray-900 mb-1">{{ $biodata->NAMA_KARYAWAN ?? 'Karyawan' }}</h5>
                                <span class="badge badge-light border text-muted mr-2">NPK: {{ $biodata->NPK ?? '-' }}</span>
                                <span class="badge badge-info">{{ $biodata->DEPT ?? '-' }}</span>
                            </div>
                            <div class="col-md-4 text-md-right">
                                <form method="GET" action="{{ route('face.attendance.rekap') }}" class="form-inline justify-content-md-end">
                                    <label class="mr-2 font-weight-bold text-gray-700 small">Pilih Bulan:</label>
                                    <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm rounded-pill mr-2" onchange="this.form.submit()">
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                                        <i class="fas fa-filter mr-1"></i> Filter
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Table Rekapitulasi Presensi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar-alt mr-1"></i> Log Presensi Bulan {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }}
                        </h6>
                        <span class="badge badge-primary px-3 py-1 font-weight-bold">
                            Total: {{ count($groupedLogs) }} Hari Kerja
                        </span>
                    </div>
                    <div class="card-body">

                        @if(empty($groupedLogs))
                            {{-- Empty state DI LUAR tabel.
                                 Baris "colspan" di dalam tbody membuat DataTables menghitung
                                 1 sel <td> (colspan diabaikan) sehingga muncul warning
                                 "Incorrect column count". --}}
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x mb-3 d-block text-secondary"></i>
                                <p class="mb-0 text-muted">Belum ada data presensi untuk bulan ini.</p>
                            </div>
                        @endif

                        <!-- Tampilan Desktop (DataTables) -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-bordered table-hover table-rekap" id="tableRekapDesktop" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="3%" class="text-center">#</th>
                                        <th width="14%">Tanggal & Hari</th>
                                        <th width="14%" class="text-center">Jam Masuk (Clock In)</th>
                                        <th width="14%" class="text-center">Jam Pulang (Clock Out)</th>
                                        <th width="14%" class="text-center">Jadwal Shift</th>
                                        <th width="13%" class="text-center">Mode Presensi</th>
                                        <th width="12%" class="text-center">Status</th>
                                        <th width="10%" class="text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $no = 1; @endphp
                                    @forelse($groupedLogs as $log)
                                    <tr>
                                        <td class="text-center font-weight-bold">{{ $no++ }}</td>
                                        <td>
                                            <span class="font-weight-bold text-gray-900 d-block">{{ $log['formatted_date'] }}</span>
                                            <small class="text-muted">{{ $log['day_name'] }}</small>
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_in'])
                                                <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                                                    <i class="fas fa-sign-in-alt mr-1"></i> {{ $log['clock_in'] }}
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">Belum Melakukan Scan</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_out'])
                                                <span class="badge badge-warning px-3 py-2 font-weight-bold text-dark" style="font-size: 0.85rem;">
                                                    <i class="fas fa-sign-out-alt mr-1"></i> {{ $log['clock_out'] }}
                                                </span>
                                            @else
                                                <span class="badge badge-light border text-muted px-2 py-1">Belum Melakukan Scan</span>
                                            @endif
                                        </td>
                                        {{-- PERBAIKAN (2026-11-03): kolom jadwal shift
                                             per tanggal. Menampilkan nama shift +
                                             jam kerja. Kalau karyawan tidak punya
                                             shift khusus tanggal itu, tampilkan
                                             "Normal" (jadwal default). --}}
                                        <td class="text-center">
                                            @if(!empty($log['shift_custom']))
                                                <span class="badge badge-primary px-2 py-1 font-weight-bold" style="font-size: 0.78rem;">
                                                    <i class="fas fa-calendar-alt mr-1"></i> {{ $log['shift_name'] }}
                                                </span>
                                                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                                    {{ substr($log['shift_work_start'], 0, 5) }} - {{ substr($log['shift_work_end'], 0, 5) }}
                                                </div>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1" style="font-size: 0.78rem;">
                                                    <i class="fas fa-calendar-alt mr-1"></i> Normal
                                                </span>
                                                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                                                    {{ substr($log['shift_work_start'], 0, 5) }} - {{ substr($log['shift_work_end'], 0, 5) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @foreach($log['modes'] as $mode)
                                                <span class="badge badge-{{ $mode == 'Wajah' ? 'primary' : 'info' }} px-2 py-1 mr-1">
                                                    <i class="fas {{ $mode == 'Wajah' ? 'fa-user-check' : 'fa-fingerprint' }} mr-1"></i> {{ $mode }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <td class="text-center">
                                            @if($log['clock_in'] && $log['clock_out'])
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> Lengkap</span>
                                            @elseif($log['clock_in'])
                                                <span class="badge badge-info px-2 py-1"><i class="fas fa-sign-in-alt mr-1"></i> Clock In Saja</span>
                                            @else
                                                <span class="badge badge-warning px-2 py-1"><i class="fas fa-sign-out-alt mr-1"></i> Clock Out Saja</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(!empty($log['face_details']))
                                                <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-sm px-3"
                                                        data-toggle="modal" data-target="#modalFaceDetail"
                                                        data-face-details="{{ json_encode($log['face_details'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                                                        data-date="{{ $log['date'] }}"
                                                        data-date-label="{{ $log['formatted_date'] }} ({{ $log['day_name'] }})">
                                                    <i class="fas fa-map-marker-alt mr-1"></i> Lihat
                                                </button>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                        {{-- Kosong: pesan empty-state sudah ditampilkan di atas tabel.
                                             Sengaja TIDAK ada <td> di sini supaya DataTables tidak
                                             menghitung jumlah sel tidak sesuai jumlah kolom. --}}
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Tampilan Mobile (Card List) -->
                        <div class="d-block d-md-none">
                            @forelse($groupedLogs as $log)
                            <div class="card mb-3 border-left-{{ ($log['clock_in'] && $log['clock_out']) ? 'success' : 'info' }} shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-gray-900">{{ $log['formatted_date'] }} ({{ $log['day_name'] }})</span>
                                        <span class="badge badge-{{ ($log['clock_in'] && $log['clock_out']) ? 'success' : 'info' }}">
                                            {{ ($log['clock_in'] && $log['clock_out']) ? 'Lengkap' : 'Partial' }}
                                        </span>
                                    </div>
                                    <div class="row text-center border-top border-bottom py-2 my-2">
                                        <div class="col-6 border-right">
                                            <small class="text-muted d-block">Jam Masuk</small>
                                            <strong class="text-success">{{ $log['clock_in'] ?? '-' }}</strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Jam Pulang</small>
                                            <strong class="text-warning">{{ $log['clock_out'] ?? '-' }}</strong>
                                        </div>
                                    </div>
                                    {{-- PERBAIKAN (2026-11-03): tampilkan jadwal shift
                                         juga di tampilan mobile, supaya konsisten
                                         dengan tabel desktop. --}}
                                    <div class="d-flex justify-content-between align-items-center small py-1 mb-1">
                                        <span class="text-muted">
                                            <i class="fas fa-calendar-alt text-primary mr-1"></i> Jadwal Shift
                                        </span>
                                        <span>
                                            <span class="badge badge-{{ !empty($log['shift_custom']) ? 'primary' : 'secondary' }} px-2 py-1">
                                                {{ $log['shift_name'] }}
                                            </span>
                                            <span class="text-muted ml-1">
                                                {{ substr($log['shift_work_start'], 0, 5) }} - {{ substr($log['shift_work_end'], 0, 5) }}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small text-muted">
                                        <span>Mode: {{ implode(', ', $log['modes']) }}</span>
                                        <span>Total Scan: {{ $log['total_scans'] }}x</span>
                                    </div>
                                    @if(!empty($log['face_details']))
                                    <div class="mt-2 pt-2 border-top">
                                        <button type="button" class="btn btn-sm btn-primary btn-block rounded-pill shadow-sm"
                                                data-toggle="modal" data-target="#modalFaceDetail"
                                                data-face-details="{{ json_encode($log['face_details'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                                                data-date="{{ $log['date'] }}"
                                                data-date-label="{{ $log['formatted_date'] }} ({{ $log['day_name'] }})">
                                            <i class="fas fa-map-marker-alt mr-1"></i> Lihat Lokasi & Wajah
                                        </button>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <div class="text-center py-4 text-muted">
                                Belum ada data presensi.
                            </div>
                            @endforelse
                        </div>

                    </div>
                </div>

            </div>
        </div>

        @include('layout.footer')

<!-- Modal Detail Lokasi & Wajah -->
<div class="modal fade" id="modalFaceDetail" tabindex="-1" aria-labelledby="modalFaceDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="modalFaceDetailLabel">
                    <i class="fas fa-map-marker-alt mr-2"></i> Detail Presensi Wajah
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{-- Ringkasan presensi: kartu rapi di atas, mudah dibaca --}}
                {{-- PERBAIKAN (2026-11-03): ditambah NPK + Departemen karena
                     modal ini sebelumnya menampilkan "KARYAWAN: -" (nama
                     kosong). Field baru diambil dari API /face-attendance/
                     admin/detail/{npk}/{tanggal} -> employee.{name,npk,dept}. --}}
                <div class="card border-left-primary shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="row text-center text-md-left">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Karyawan</div>
                                <div class="h6 mb-1 font-weight-bold text-gray-800" id="detailEmployeeName">-</div>
                                <div class="small text-gray-500">
                                    NPK: <span id="detailEmployeeNpk" class="font-weight-bold">-</span>
                                    <span class="mx-1 text-gray-300">|</span>
                                    <span id="detailEmployeeDept">-</span>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Tanggal</div>
                                <div class="h6 mb-0 font-weight-bold text-gray-800" id="detailDateLabel">-</div>
                            </div>
                            <div class="col-md-5">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Lokasi Kerja</div>
                                <div class="h6 mb-1 font-weight-bold text-gray-800" id="detailLocationName">-</div>
                                <div class="small text-gray-500" id="detailLocationSub">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Kolom kiri: Mini Map + Wajah -->
                    <div class="col-lg-5 mb-4 mb-lg-0">
                        <h6 class="font-weight-bold text-gray-800 mb-2">
                            <i class="fas fa-map text-primary mr-1"></i> Minimap Lokasi Absensi
                        </h6>
                        <div id="miniMapDetail"></div>
                        {{-- PERBAIKAN (2026-11-03): baris "Koordinat: lat, lng"
                             di bawah peta DIHAPUS atas permintaan user. Peta
                             sudah menunjukkan posisi secara visual, jadi
                             angka koordinat tidak perlu ditampilkan lagi.
                             Elemen #detailCoords sengaja dibiarkan ada
                             ( disembunyikan ) supaya script yang mengisi
                             nilainya tidak error. --}}
                        <div class="d-none" aria-hidden="true">
                            <span id="detailCoords" class="d-none" aria-hidden="true">-</span>
                        </div>

                        <h6 class="font-weight-bold text-gray-800 mb-2 mt-3">
                            <i class="fas fa-user-check text-primary mr-1"></i> Wajah Tercapture
                        </h6>
                        <div id="faceImageContainer" class="text-center mb-3">
                            <div id="faceImageEmpty" class="text-muted small py-4">
                                <i class="fas fa-image fa-2x mb-2 d-block text-secondary"></i>
                                Tidak ada gambar wajah tersimpan.
                            </div>
                        </div>
                    </div>

                    <!-- Kolom kanan: tabel riwayat scan -->
                    <div class="col-lg-7">
                        <h6 class="font-weight-bold text-gray-800 mb-2">
                            <i class="fas fa-list-ul text-primary mr-1"></i> Riwayat Scan Hari Ini
                        </h6>
                        <div id="faceDetailList" class="small"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    $(document).ready(function() {
        // Inisialisasi hanya bila tabel punya baris data. Baris kosong dengan
        // colspan membuat DataTables menghitung jumlah sel tidak sesuai kolom
        // dan memunculkan warning "Incorrect column count".
        var $rekapTable = $('#tableRekapDesktop');
        if ($rekapTable.length === 0) return;

        var rekapRowCount = $rekapTable.find('tbody tr').length;
        if (rekapRowCount === 0) {
            $rekapTable.addClass('d-none');
            return;
        }

        $rekapTable.DataTable({
            "order": [[0, "asc"]],
            "pageLength": 25,
            "language": {
                "search": "Cari Presensi:",
                "lengthMenu": "Tampilkan _MENU_ data",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ hari",
                "infoEmpty": "Menampilkan 0 data",
                "zeroRecords": "Tidak ada data presensi yang sesuai",
                "paginate": {
                    "first": "Awal",
                    "last": "Akhir",
                    "next": "Berikutnya",
                    "previous": "Sebelumnya"
                }
            }
        });
    });

    // ===== Detail Lokasi & Wajah Presensi (Face Attendance) =====
    (function() {
        let detailMap = null;
        let detailMarker = null;
        let detailCircle = null;
        let detailPolyGroup = null;

        // Polygon lokasi kerja aktif (dari server) untuk digambar di minimap
        const workLocations = @json($workLocationsForMap ?? [])

        function destroyMap() {
            if (detailMap) {
                detailMap.remove();
                detailMap = null;
            }
            detailMarker = null;
            detailCircle = null;
            detailPolyGroup = null;
        }

        function renderFaceImages(details) {
            const container = document.getElementById('faceImageContainer');
            const listContainer = document.getElementById('faceDetailList');
            container.innerHTML = '';
            listContainer.innerHTML = '';

            const withImage = details.filter(function(d) { return d.face_image; });

            if (withImage.length === 0) {
                container.innerHTML = '<div id="faceImageEmpty" class="text-muted small py-4">' +
                    '<i class="fas fa-image fa-2x mb-2 d-block text-secondary"></i>' +
                    'Tidak ada gambar wajah tersimpan.</div>';
            } else {
                withImage.forEach(function(d, idx) {
                    const wrap = document.createElement('div');
                    wrap.className = idx === 0 ? 'mb-3' : 'mb-3';
                    const src = d.face_image.indexOf('data:') === 0
                        ? d.face_image
                        : 'data:image/jpeg;base64,' + d.face_image;
                    wrap.innerHTML =
                        '<img src="' + src + '" alt="Wajah ' + (idx + 1) + '" class="face-capture-preview" style="max-width:260px;">' +
                        '<div class="small text-muted mt-1"><i class="fas fa-clock mr-1"></i>' +
                        (d.action_type || 'Absensi') + ' - ' + (d.scan_time || '-') + '</div>';
                    container.appendChild(wrap);
                });
            }

            // Daftar seluruh scan -> disusun sebagai tabel rapi & mudah dibaca.
            // PERBAIKAN (2026-11-03): koordinat mentah (lat, lng) TIDAK
            // ditampilkan. Peta di atas sudah menunjukkan posisi. Akurasi
            // GPS tetap tampil sebagai indikator kualitas sinyal.
            if (details.length === 0) {
                listContainer.innerHTML =
                    '<div class="text-muted small text-center py-3">' +
                    '<i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>' +
                    'Belum ada riwayat scan pada hari ini.</div>';
                return;
            }

            var rowsHtml = '';
            details.forEach(function(d, i) {
                var isFace = d.mode_label === 'Wajah';

                // PERBAIKAN (2026-11-03): kolom Lokasi menampilkan nama area
                // kerja, bukan koordinat mentah.
                var rawLoc = d.location_name || '';
                var isCoordText = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(rawLoc.trim());
                var locResolved = d.location_resolved || '';
                var locByCoords = '';
                if (!locResolved && d.latitude !== null && d.latitude !== undefined) {
                    var ml = findLocationByCoords(workLocations, parseFloat(d.latitude), parseFloat(d.longitude));
                    if (ml) locByCoords = ml.name || '';
                }
                var loc = locResolved
                    || (rawLoc && !isCoordText ? rawLoc : '')
                    || locByCoords
                    || '-';

                var acc = (d.accuracy !== null && d.accuracy !== undefined && d.accuracy !== '')
                    ? Math.round(parseFloat(d.accuracy)) + ' m'
                    : '-';
                var modeBadge = isFace
                    ? '<span class="badge badge-primary"><i class="fas fa-user-check mr-1"></i>Wajah</span>'
                    : '<span class="badge badge-info"><i class="fas fa-fingerprint mr-1"></i>Fingerprint</span>';
                var accBadge = (acc === '-')
                    ? '<span class="text-muted">-</span>'
                    : '<span class="badge badge-light border text-dark">' + acc + '</span>';

                rowsHtml +=
                    '<tr>' +
                    '<td class="text-center text-muted align-middle">' + (i + 1) + '</td>' +
                    '<td class="align-middle"><i class="far fa-clock text-muted mr-1"></i>' +
                        '<span class="font-weight-bold">' + (d.scan_time || '-') + '</span></td>' +
                    '<td class="align-middle">' + modeBadge + '</td>' +
                    '<td class="align-middle">' + (d.action_type || '-') + '</td>' +
                    '<td class="align-middle">' + loc + '</td>' +
                    '<td class="text-center align-middle">' + accBadge + '</td>' +
                    '</tr>';
            });

            listContainer.innerHTML =
                '<div class="table-responsive">' +
                '<table class="table table-sm table-hover table-bordered mb-0 align-middle">' +
                '<thead class="thead-light">' +
                '<tr>' +
                '<th class="text-center" style="width:40px;">#</th>' +
                '<th style="width:90px;">Jam</th>' +
                '<th style="width:110px;">Metode</th>' +
                '<th>Keterangan</th>' +
                '<th>Lokasi</th>' +
                '<th class="text-center" style="width:85px;">Akurasi</th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>' + rowsHtml + '</tbody>' +
                '</table>' +
                '</div>';
        }

        function initMap(lat, lng, accuracy, locations) {
            destroyMap();

            const el = document.getElementById('miniMapDetail');
            detailMap = L.map(el, {
                zoomControl: true,
                attributionControl: false
            }).setView([lat, lng], 17);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(detailMap);

            // PERBAIKAN (2026-11-03): polygon lokasi kerja digambar untuk
            // SEMUA lokasi (aktif & non-aktif), dengan warna berbeda.
            // Polygon yang memuat titik absensi diberi warna hijau.
            const matchedLoc = findLocationByCoords(locations, lat, lng);
            if (locations && locations.length > 0) {
                detailPolyGroup = L.layerGroup().addTo(detailMap);

                locations.forEach(function(loc) {
                    if (!loc.coordinates || loc.coordinates.length < 3) return;
                    const latLngs = loc.coordinates.map(function(c) {
                        return [c[1], c[0]]; // GeoJSON = [lng, lat]
                    });

                    const isActive = loc.is_active !== false;
                    const isMatched = matchedLoc && matchedLoc.name === loc.name;
                    const color = isMatched ? '#1cc88a' : (isActive ? '#4e73df' : '#858796');
                    const fillOpacity = isMatched ? 0.25 : (isActive ? 0.15 : 0.08);

                    const layer = L.polygon(latLngs, {
                        color: color,
                        weight: isMatched ? 3 : 2,
                        fillColor: color,
                        fillOpacity: fillOpacity
                    });

                    layer.bindPopup(
                        '<strong>' + (loc.name || 'Lokasi Kerja') + '</strong><br>' +
                        (loc.description ? loc.description + '<br>' : '') +
                        'Status: ' + (isActive ? 'Aktif' : 'Non-aktif')
                    );
                    layer.addTo(detailPolyGroup);
                });
            }

            // Marker posisi absensi
            const markerIcon = L.divIcon({
                className: 'custom-face-marker',
                html: '<div style="background:#e74a3b;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.35);font-size:14px;">&#128100;</div>',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            });

            detailMarker = L.marker([lat, lng], { icon: markerIcon }).addTo(detailMap);
            // PERBAIKAN (2026-11-03): popup marker tidak lagi menampilkan
            // koordinat mentah (lat, lng), diganti nama lokasi + akurasi.
            const popupName = (matchedLoc && matchedLoc.name) || 'Posisi Absensi';
            const accText = (accuracy && accuracy > 0) ? 'Akurasi ±' + Math.round(accuracy) + ' m' : '';
            detailMarker.bindPopup(
                '<strong>' + popupName + '</strong><br>' +
                (accText ? accText : 'Titik lokasi absensi')
            );

            // Circle radius akurasi GPS
            if (accuracy && accuracy > 0) {
                detailCircle = L.circle([lat, lng], {
                    radius: accuracy,
                    color: '#e74a3b',
                    weight: 1,
                    fillColor: '#e74a3b',
                    fillOpacity: 0.12
                }).addTo(detailMap);
            }

            setTimeout(function() {
                if (detailMap) detailMap.invalidateSize();
            }, 200);
        }

        $(document).on('click', '[data-face-details]', function() {
            const $btn = $(this);
            const raw = $btn.attr('data-face-details');
            const dateLabel = $btn.attr('data-date-label') || '-';
            const date = $btn.attr('data-date') || '';
            let details = [];
            try {
                details = raw ? JSON.parse(raw) : [];
            } catch (e) {
                details = [];
            }

            document.getElementById('detailDateLabel').textContent = dateLabel;

            // Refresh dari server (penting agar gambar/coordinat terbaru & tidak reliant cache HTML)
            if (date) {
                fetch("{{ url('/face-attendance/detail') }}/" + encodeURIComponent(date), {
                    headers: { 'Accept': 'application/json' }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.success && Array.isArray(data.details)) {
                        applyFaceDetails(data.details, dateLabel);
                    } else {
                        applyFaceDetails(details, dateLabel);
                    }
                })
                .catch(function() {
                    applyFaceDetails(details, dateLabel);
                });
            } else {
                applyFaceDetails(details, dateLabel);
            }
        });

        function applyFaceDetails(details, dateLabel) {
            document.getElementById('detailDateLabel').textContent = dateLabel;

            if (!details || !details.length) {
                document.getElementById('detailLocationName').textContent = '-';
                document.getElementById('detailLocationSub').textContent = '-';
                document.getElementById('detailCoords').textContent = '-';
                renderFaceImages(details || []);
                destroyMap();
                return;
            }

            const first = details[0];
            const lat = parseFloat(first.latitude);
            const lng = parseFloat(first.longitude);

            // PERBAIKAN (2026-11-03): nama lokasi kerja.
            //  - first.location_name bisa berupa koordinat mentah (fallback
            //    dari FaceAttendanceLog::locationName()).
            //  - location_resolved = hasil pencocokan point-in-polygon.
            const resolved = first.location_resolved || '';
            const rawName = first.location_name || '';
            const isCoordText = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(rawName.trim());
            const matched = (!isNaN(lat) && !isNaN(lng)) ? findLocationByCoords(workLocations, lat, lng) : null;
            const finalName = resolved
                || (rawName && !isCoordText ? rawName : '')
                || (matched ? matched.name : '')
                || '-';

            document.getElementById('detailLocationName').textContent = finalName;

            const acc = parseFloat(first.accuracy);
            const accText = (!isNaN(acc) && acc > 0) ? 'Akurasi GPS ±' + Math.round(acc) + ' m' : 'Akurasi GPS tidak tersedia';
            const statusText = matched
                ? (matched.is_active !== false ? 'Polygon aktif' : 'Polygon non-aktif')
                : 'Di luar polygon lokasi kerja';
            document.getElementById('detailLocationSub').textContent = accText + ' · ' + statusText;

            // PERBAIKAN (2026-11-03): #detailCoords tidak lagi diisi
            // koordinat mentah, hanya penanda (elemen tetap d-none).
            document.getElementById('detailCoords').textContent = 'tersembunyi';

            renderFaceImages(details);

            if (!isNaN(lat) && !isNaN(lng)) {
                initMap(lat, lng, parseFloat(first.accuracy), workLocations);
            } else {
                destroyMap();
            }
        }

        // PERBAIKAN (2026-11-03): helper point-in-polygon versi client untuk
        // mencari polygon lokasi kerja yang memuat koordinat absensi.
        function findLocationByCoords(locations, lat, lng) {
            if (!locations || !locations.length) return null;
            for (let i = 0; i < locations.length; i++) {
                const coords = locations[i].coordinates;
                if (!coords || coords.length < 3) continue;
                let inside = false;
                const n = coords.length;
                let j = n - 1;
                for (let k = 0; k < n; k++) {
                    const lngI = parseFloat(coords[k][0]);
                    const latI = parseFloat(coords[k][1]);
                    const lngJ = parseFloat(coords[j][0]);
                    const latJ = parseFloat(coords[j][1]);
                    const intersect = ((latI > lat) !== (latJ > lat)) &&
                        (lng < (lngJ - lngI) * (lat - latI) / ((latJ - latI) || 0.0000001) + lngI);
                    if (intersect) inside = !inside;
                    j = k;
                }
                if (inside) return locations[i];
            }
            return null;
        }

        // Bersihkan peta saat modal ditutup
        $('#modalFaceDetail').on('hidden.bs.modal', function() {
            destroyMap();
        });
    })();
</script>

</body>
</html>
