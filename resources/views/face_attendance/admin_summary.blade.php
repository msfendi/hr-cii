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
    .emp-row-detail {
        background-color: #f8f9fc;
    }
    .emp-row-detail table {
        margin-bottom: 0;
    }
    .emp-row-detail table th {
        background-color: #e3e6f0 !important;
        color: #4a4a68 !important;
    }
    .table-toggle-icon {
        transition: transform .2s ease;
    }
    .table-toggle-icon.rotated {
        transform: rotate(90deg);
    }
    .stat-card {
        border-left: 4px solid;
    }
    .stat-card.border-primary { border-left-color: #4e73df !important; }
    .stat-card.border-success { border-left-color: #1cc88a !important; }
    .stat-card.border-info { border-left-color: #36b9cc !important; }
    .stat-card.border-warning { border-left-color: #f6c23e !important; }
</style>

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    @if(auth()->check())
        @include('layout.sidebar')
    @endif

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            @if(auth()->check())
                @include('layout.navbar')
            @endif

            <div class="container-fluid py-3">

                <!-- Header -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-users-cog text-primary mr-2"></i> Rekap Presensi Semua Karyawan
                    </h1>
                    <div class="mt-2 mt-sm-0">
                        <a href="{{ route('face.attendance.admin.export', ['month' => $month]) }}"
                           class="btn btn-sm btn-success shadow-sm rounded-pill px-3">
                            <i class="fas fa-file-excel mr-1"></i> Export CSV
                        </a>
                        <a href="{{ route('face.attendance.settings.index') }}"
                           class="btn btn-sm btn-outline-primary shadow-sm rounded-pill px-3">
                            <i class="fas fa-cog mr-1"></i> Pengaturan
                        </a>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card shadow mb-4 border-left-primary">
                    <div class="card-body">
                        <form method="GET" action="{{ route('face.attendance.admin.index') }}" class="row align-items-end">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterMonth">Pilih Bulan:</label>
                                <input type="month" name="month" id="filterMonth" value="{{ $month }}" 
                                       class="form-control form-control-sm rounded-pill" 
                                       onchange="this.form.submit()" 
                                       style="height: 31px;">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterDepartment">Departemen:</label>
                                <select name="department_id" id="filterDepartment" class="form-control form-control-sm rounded-pill" style="height: 31px;">
                                    <option value="">Semua Departemen</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->ID_DEPT }}" {{ (string)$department === (string)$d->ID_DEPT ? 'selected' : '' }}>
                                            {{ $d->DEPARTEMENT }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="font-weight-bold text-gray-700 small d-block mb-2" for="filterSearch">Cari (Nama / NPK / Barcode):</label>
                                {{-- PERBAIKAN (2026-11-03): input teks biasa -> Select2.
                                     Data diambil dari API /face-attendance/admin/employees
                                     (union BIODATA + BIODATA_KELUAR) dengan
                                     pencarian di server, jadi tidak memuat ribuan
                                     karyawan ke browser.
                                     Value awal memakai NPK yang sedang difilter;
                                     labelnya dibuat server agar tampilan
                                     "-- NPK (nama)" --}}
                                <select name="q" id="filterSearch" class="form-control form-control-sm rounded-pill" style="height: 31px; width: 100%;">
                                    @if($search !== '')
                                        <option value="{{ $search }}" selected>
                                            {{ $searchSelectedLabel ?? $search }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-2 mb-3 mb-md-0">
                                <label class="small d-block mb-2" style="visibility:hidden;">Filter</label>
                                <button type="submit" class="btn btn-sm btn-primary rounded-pill btn-block" style="height: 31px;">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </button>
                            </div>
                        </form>
                        <div id="rekapLoadingBanner" class="alert alert-info border-left-info shadow-sm mt-3 mb-0" role="status">
                            <div class="d-flex align-items-center">
                                <span class="spinner-border spinner-border-sm text-info mr-2" role="status" aria-hidden="true"></span>
                                <span>
                                    <strong>Memuat data rekap...</strong>
                                    <span class="d-block small text-gray-700">
                                        Halaman ditampilkan lebih dulu, data presensi diambil di belakang.
                                        Filter bulan / departemen / pencarian akan langsung memuat ulang data.
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div id="rekapErrorBanner" class="alert alert-danger border-left-danger shadow-sm mt-3 mb-0 d-none" role="alert"></div>
                    </div>
                </div>

                {{-- Kontainer data rekap: diisi via AJAX (lazy load) --}}
                <div id="rekapDataContainer">

                @include('face_attendance.partials.admin_summary_data')
                </div>{{-- /#rekapDataContainer --}}
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Presensi (Wajah + Fingerprint + Lokasi) -->
<div class="modal fade" id="modalFaceDetail" tabindex="-1" role="dialog" aria-labelledby="modalFaceDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="modalFaceDetailLabel">
                    <i class="fas fa-map-marker-alt mr-2"></i> Detail Presensi
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
                        {{-- PERBAIKAN (2026-11-03): kolom koordinat di bawah
                             peta DIHAPUS atas permintaan user. Peta sudah
                             menunjukkan posisi secara visual.
                             Elemen #detailCoords sengaja dibiarkan
                             (disembunyikan) supaya script yang mengisi
                             nilainya tidak error. --}}
                        <span id="detailCoords" class="d-none" aria-hidden="true">-</span>

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
                            <i class="fas fa-list-ul text-primary mr-1"></i> Riwayat Scan (Wajah + Fingerprint)
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

<!-- jQuery (wajib sebelum DataTables) -->
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    // PERBAIKAN (2026-11-03): DataTable TIDAK lagi diinisialisasi di
    // $(document).ready(). Sebelumnya ada dua inisialisasi:
    //   1. $(document).ready -> $('#tableRekapAdmin').DataTable({...})
    //   2. initRekapTable()  -> dipanggil setelah AJAX memuat fragment
    // Keduanya berlomba: yang pertama membungkus tabel lama, yang kedua
    // mengganti isinya. Akibatnya DataTable tetap ter-attach ke tabel
    // LAMA yang sudah dibuang -> "tampilan rusak", baris tidak muncul,
    // pagination/search tidak jalan.
    //
    // Sekarang: hanya initRekapTable() yang boleh membuat DataTable,
    // dan SELALU destroys instance lama dulu kalau ada.
    //
    // PERBAIKAN (2026-11-03 #2): initRekapTable() DIDEFINISIKAN di blok
    // <script> KEDUA (setelah blok ini). Function declaration tidak
    // menyeberang batas blok <script>, jadi memanggilnya dari sini
    // memunculkan:
    //     Uncaught ReferenceError: initRekapTable is not defined
    // Pemanggilan awal dipindah ke blok kedua, tepat setelah fungsinya
    // dideklarasikan. Lihat "Inisialisasi awal setelah fungsi didefinisikan".

    // ===== Toggle baris detail per karyawan =====
    $(document).on('click', '.btn-toggle-emp', function() {
        const $btn = $(this);
        const target = $btn.data('target');
        const $row = $(target);
        const $icon = $btn.find('.table-toggle-icon');

        $row.toggleClass('d-none');
        $icon.toggleClass('rotated');
    });

    // ===== Detail Lokasi & Wajah Presensi (Face + Fingerprint) =====
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
                container.innerHTML = '<div class="text-muted small py-4">' +
                    '<i class="fas fa-image fa-2x mb-2 d-block text-secondary"></i>' +
                    'Tidak ada gambar wajah tersimpan.</div>';
            } else {
                withImage.forEach(function(d, idx) {
                    const wrap = document.createElement('div');
                    wrap.className = 'mb-3';
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

            // Daftar seluruh scan (wajah + fingerprint) -> tabel rapi.
            // PERBAIKAN (2026-11-03): baris koordinat mentah (lat, lng)
            // TIDAK ditampilkan. Peta di atas sudah menunjukkan posisi.
            // Akurasi GPS tetap tampil sebagai indikator kualitas sinyal.
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

                // PERBAIKAN (2026-11-03): kolom Lokasi tidak lagi menampilkan
                // koordinat mentah (mis. "-7.629019, 110.827461"). Dipakai
                // nama area kerja: location_resolved (dari server) atau
                // location_name, lalu fallback ke polygon yang memuat titik.
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
            // SEMUA lokasi (aktif & non-aktif). Sebelumnya hanya `is_active`
            // yang dikirim server, sehingga kalau sebuah area sedang
            // terkunci (CTPAT lock) polygon-nya hilang dari minimap.
            // Warna dibedakan: aktif = biru, non-aktif = abu-abu.
            // Polygon yang memuat titik absensi diberi warna hijau + label.
            const matchedLoc = findLocationByCoords(locations, lat, lng);
            if (locations && locations.length > 0) {
                detailPolyGroup = L.layerGroup().addTo(detailMap);

                locations.forEach(function(loc) {
                    if (!loc.coordinates || loc.coordinates.length < 3) return;
                    const latLngs = loc.coordinates.map(function(c) {
                        return [c[1], c[0]]; // GeoJSON = [lng, lat]
                    });

                    const isActive = loc.is_active !== false; // default dianggap aktif
                    const isMatched = matchedLoc && matchedLoc.name === loc.name;
                    const color = isMatched ? '#1cc88a' : (isActive ? '#4e73df' : '#858796');
                    const fillOpacity = isMatched ? 0.25 : (isActive ? 0.15 : 0.08);

                    const layer = L.polygon(latLngs, {
                        color: color,
                        weight: isMatched ? 3 : 2,
                        fillColor: color,
                        fillOpacity: fillOpacity
                    });

                    const statusTxt = isActive ? 'Aktif' : 'Non-aktif';
                    layer.bindPopup(
                        '<strong>' + (loc.name || 'Lokasi Kerja') + '</strong><br>' +
                        (loc.description ? loc.description + '<br>' : '') +
                        'Status: ' + statusTxt
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
            // koordinat mentah (lat, lng). Diganti nama lokasi + akurasi,
            // sesuai permintaan user supaya tidak ada angka GPS mentah.
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

        $(document).on('click', '[data-npk]', function() {
            const $btn = $(this);
            const npk = $btn.attr('data-npk');
            // PERBAIKAN (2026-11-03): nama karyawan diambil dari atribut
            // sebagai nilai awal. Sumber kebenaran sebenarnya adalah
            // response API (employee.name) yang dipakai di applyData(),
            // karena atribut bisa kosong / ter-escape.
            const empName = $btn.attr('data-emp-name') || '-';
            const date = $btn.attr('data-date') || '';
            const dateLabel = $btn.attr('data-date-label') || '-';

            document.getElementById('detailEmployeeName').textContent = empName;
            document.getElementById('detailEmployeeNpk').textContent = npk || '-';
            document.getElementById('detailEmployeeDept').textContent = '-';
            document.getElementById('detailDateLabel').textContent = dateLabel;
            document.getElementById('detailLocationName').textContent = '-';
            document.getElementById('detailLocationSub').textContent = '-';
            document.getElementById('detailCoords').textContent = '-';

            if (!npk || !date) {
                renderFaceImages([]);
                destroyMap();
                return;
            }

            fetch("{{ url('/face-attendance/admin/detail') }}/" + encodeURIComponent(npk) + "/" + encodeURIComponent(date), {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.success) {
                    applyData(data);
                } else {
                    renderFaceImages([]);
                    destroyMap();
                }
            })
            .catch(function() {
                renderFaceImages([]);
                destroyMap();
            });
        });

        function applyData(data) {
            const scans = data.scans || [];
            const faceDetails = data.face_details || [];
            const employee = data.employee || {};

            // PERBAIKAN (2026-11-03): isi identitas karyawan dari API.
            // Sebelumnya modal menampilkan "KARYAWAN: -" karena nama hanya
            // diambil dari atribut tombol dan tidak pernah di-refresh dari
            // response server. Sekarang API yang jadi sumber utama, atribut
            // tombol hanya fallback.
            const nameEl = document.getElementById('detailEmployeeName');
            nameEl.textContent = employee.name || '-';
            document.getElementById('detailEmployeeNpk').textContent = employee.npk || '-';
            document.getElementById('detailEmployeeDept').textContent = employee.dept && employee.dept !== '-' ? employee.dept : '-';

            // Gabungkan scan + detail wajah (face_details sudah punya scan_time & action_type)
            const merged = [];
            faceDetails.forEach(function(f) {
                merged.push(Object.assign({}, f, {
                    mode_label: 'Wajah',
                    action_type: f.action_type || 'Absensi Wajah'
                }));
            });
            scans.forEach(function(s) {
                if (s.mode_label !== 'Wajah') {
                    merged.push(s);
                }
            });
            merged.sort(function(a, b) {
                return String(a.scan_time || '').localeCompare(String(b.scan_time || ''));
            });

            const first = faceDetails.length > 0 ? faceDetails[0] : null;

            if (first && first.latitude !== null && first.longitude !== null) {
                const lat = parseFloat(first.latitude);
                const lng = parseFloat(first.longitude);

                // PERBAIKAN (2026-11-03): nama lokasi kerja.
                //  - first.location_name bisa berupa koordinat mentah (fallback
                //    dari FaceAttendanceLog::locationName()) -> tidak informatif.
                //  - API sudah mengirim location_resolved (hasil pencocokan
                //    point-in-polygon ke nama area kerja).
                //  - Kalau keduanya kosong, cari polygon yang memuat koordinat
                //    ini di sisi frontend sebagai fallback terakhir.
                const resolved = first.location_resolved || '';
                const rawName = first.location_name || '';
                const isCoordText = /^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(rawName.trim());
                const finalName = resolved
                    || (rawName && !isCoordText ? rawName : '')
                    || findLocationByCoords(workLocations, lat, lng)
                    || '-';

                document.getElementById('detailLocationName').textContent = finalName;

                // Baris kedua: akurasi GPS + penanda polygon aktif/tidak.
                const acc = parseFloat(first.accuracy);
                const accText = (!isNaN(acc) && acc > 0) ? 'Akurasi GPS ±' + Math.round(acc) + ' m' : 'Akurasi GPS tidak tersedia';
                const matched = findLocationByCoords(workLocations, lat, lng);
                const statusText = matched
                    ? (matched.is_active ? 'Polygon aktif' : 'Polygon non-aktif')
                    : 'Di luar polygon lokasi kerja';
                document.getElementById('detailLocationSub').textContent = accText + ' · ' + statusText;

                // PERBAIKAN (2026-11-03): #detailCoords TIDAK lagi diisi
                // koordinat mentah. Elemennya tetap ada (d-none) supaya
                // tidak error, tapi isinya cuma penanda. Koordinat tidak
                // perlu disimpan di DOM karena peta sudah menunjuk lokasi.
                document.getElementById('detailCoords').textContent = 'tersembunyi';
                initMap(lat, lng, parseFloat(first.accuracy), workLocations);
            } else {
                document.getElementById('detailLocationName').textContent = '-';
                document.getElementById('detailLocationSub').textContent = 'Tidak ada data lokasi pada scan ini';
                document.getElementById('detailCoords').textContent = '-';
                destroyMap();
            }

            renderFaceImages(merged);
        }

        // PERBAIKAN (2026-11-03): helper untuk mencari polygon lokasi kerja
        // yang memuat koordinat tertentu (point-in-polygon versi client).
        // Dipakai sebagai fallback penamaan lokasi & penanda status polygon.
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


<script>
/**
 * Lazy load data rekap presensi.
 * Halaman + filter tampil instan; data berat diambil via AJAX setelah render.
 * Mencegah request timeout / 504 saat query att_log besar.
 */
(function () {
    var container = document.getElementById('rekapDataContainer');
    if (!container) return;

    var loading = document.getElementById('rekapLoadingBanner');
    var errBox  = document.getElementById('rekapErrorBanner');

    function showLoading() { if (loading) loading.classList.remove('d-none'); }
    function hideLoading() { if (loading) loading.classList.add('d-none'); }

    function showError(msg) {
        if (!errBox) return;
        errBox.textContent = msg;
        errBox.classList.remove('d-none');
    }
    function hideError() {
        if (!errBox) return;
        errBox.classList.add('d-none');
        errBox.textContent = '';
    }

    // ================================================================
    // PERBAIKAN (2026-11-03): Select2 untuk filter Karyawan + Departemen
    // ================================================================
    // "Cari (Nama / NPK / Barcode)" -> Select2 dengan AJAX ke
    //   /face-attendance/admin/employees  (union BIODATA + BIODATA_KELUAR)
    // "Departemen" -> Select2 dengan AJAX ke
    //   /face-attendance/admin/departments
    //
    // Kedua-duanya searching di SERVER, jadi browser tidak pernah memuat
    // seluruh master karyawan (ratusan/ribuan baris).
    // ================================================================
    function initSelect2() {
        if (typeof window.jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $ = jQuery;

        // --- Karyawan (union BIODATA + BIODATA_KELUAR) ---
        var $emp = $('#filterSearch');
        if ($emp.length && !$emp.hasClass('select2-hidden-accessible')) {
            $emp.select2({
                placeholder: 'Ketik nama / NPK / barcode...',
                allowClear: true,
                width: '100%',
                minimumInputLength: 2,
                delay: 300,
                ajax: {
                    url: '{{ route('face.attendance.admin.employees') }}',
                    type: 'GET',
                    dataType: 'json',
                    delay: 300,
                    cache: true,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            limit: 25
                        };
                    },
                    processResults: function (data) {
                        return { results: data || [] };
                    }
                },
                templateResult: function (item) {
                    if (!item.id) {
                        return item.text || 'Karyawan tidak ditemukan';
                    }
                    var badge = item.sumber === 'Keluar'
                        ? ' <span class="badge badge-secondary badge-sm">Keluar</span>'
                        : '';
                    return $('<div>').append(
                        $('<div>').append(
                            $('<span>').text(item.name || item.npk),
                            $('<span>').html(' &middot; ' + (item.npk || '') + badge))
                    );
                }
            });
        }

        // --- Departemen ---
        var $dept = $('#filterDepartment');
        if ($dept.length && !$dept.hasClass('select2-hidden-accessible')) {
            $dept.select2({
                placeholder: 'Semua Departemen',
                allowClear: true,
                width: '100%',
                delay: 250,
                ajax: {
                    url: '{{ route('face.attendance.admin.departments') }}',
                    type: 'GET',
                    dataType: 'json',
                    delay: 250,
                    cache: true,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        return { results: data || [] };
                    }
                }
            });
        }
    }

    function buildUrl() {
        var month = document.getElementById('filterMonth');
        var dept  = document.getElementById('filterDepartment');
        var q     = document.getElementById('filterSearch');
        var params = new URLSearchParams(window.location.search);
        if (month && month.value) params.set('month', month.value);
        // PERBAIKAN: nilai filter selalu dikirim (meski kosong) supaya
        // filter yang dikosongkan benar-benar ter-reset, bukan diam-diam
        // memakai filter lama dari URL.
        params.set('department_id', dept ? (dept.value || '') : '');
        params.set('q', q ? (q.value || '') : '');
        params.set('only_data', '1');
        return '{{ route('face.attendance.admin.index') }}?' + params.toString();
    }

    var inflight = null;

    function loadData() {
        showLoading();
        hideError();
        container.style.opacity = '0.45';

        if (inflight && inflight.abort) inflight.abort();
        inflight = new AbortController();

        fetch(buildUrl(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: inflight.signal
        })
        .then(function (r) {
            if (!r.ok) throw new Error('Server membalas ' + r.status + ' ' + r.statusText);
            return r.text();
        })
        .then(function (html) {
            container.innerHTML = html;
            container.style.opacity = '1';
            hideLoading();
            initRekapTable();
            document.dispatchEvent(new CustomEvent('rekap:loaded'));
        })
        .catch(function (e) {
            if (e.name === 'AbortError') return;
            container.style.opacity = '1';
            hideLoading();
            showError('Gagal memuat data rekap: ' + e.message + '. Periksa koneksi database lalu tekan Filter untuk mencoba lagi.');
        });
    }

    // Inisialisasi DataTables untuk konten yang baru dimuat
    //
    // PERBAIKAN (2026-11-03):
    //  - Selalu DESTROY instance lama sebelum membuat yang baru. Kalau tidak,
    //    DataTable tetap memegang wrapper dari tabel LAMA (yang sudah
    //    dibuang AJAX), sehingga tampilan rusak dan baris tidak muncul.
    //  - Opsi "Semua" (-1) dihapus dari lengthMenu. Dengan ribuan
    //    baris, merender semua sekaligus membuat browser hang.
    function initRekapTable() {
        if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
        var $t = jQuery('#tableRekapAdmin');
        if (!$t.length) return;

        // Buang instance lama (termasuk wrapper & event listener-nya).
        try {
            if (jQuery.fn.DataTable.isDataTable($t[0])) {
                $t.DataTable().destroy();
            }
        } catch (e) {
            console.warn('[AdminSummary] Gagal destroy DataTable lama:', e);
        }
        // Hapus wrapper sisa kalau destroy tidak sempurna.
        try {
            var dt = $t.closest('.dataTables_wrapper');
            if (dt.length) {
                $t.unwrap();
                dt.remove();
            }
        } catch (e) {
            console.warn('[AdminSummary] Gagal bersihkan wrapper DataTable:', e);
        }

        $t.DataTable({
            responsive: false,
            pageLength: 25,
            lengthMenu: [[25, 50, 100], [25, 50, 100]],
            order: [],
            columnDefs: [{ orderable: false, targets: -1 }],
            language: {
                search: 'Cari cepat:',
                lengthMenu: 'Tampilkan _MENU_ baris',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ karyawan',
                infoEmpty: 'Tidak ada data',
                zeroRecords: 'Tidak ada data yang cocok',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
            }
        });
    }

    // Inisialisasi awal setelah fungsi didefinisikan.
    // Dipanggil di blok <script> yang sama dengan definisinya supaya tidak
    // terkena "initRekapTable is not defined".
    // DOMContentLoaded dipakai karena blok ini berada di akhir body:
    // kalau dokumen sudah siap, jalankan langsung.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRekapTable);
    } else {
        initRekapTable();
    }

    // Form filter: jangan reload halaman penuh, cukup muat ulang data
    var form = document.querySelector('form[action="{{ route('face.attendance.admin.index') }}"]');
    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var params = new URLSearchParams(window.location.search);
            var month = document.getElementById('filterMonth');
            var dept  = document.getElementById('filterDepartment');
            var q     = document.getElementById('filterSearch');
            if (month && month.value) params.set('month', month.value);
            // PERBAIKAN: selalu set (meski kosong) supaya filter yang
            // dikosongkan benar-benar ter-reset.
            params.set('department_id', dept ? (dept.value || '') : '');
            params.set('q', q ? (q.value || '') : '');
            window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
            loadData();
        });
    }

    // ================================================================
    // PERBAIKAN (2026-11-03): Select2 + auto reload saat filter berubah.
    // ================================================================
    // Dulu Select2 tidak ada sama sekali dan Departemen memakai
    // onchange="this.form.submit()" (submit form penuh). Sekarang:
    //   - Select2 diinisialisasi untuk Karyawan & Departemen.
    //   - Perubahan ANYA filter (bulan / dept / karyawan) langsung
    //     memuat ulang data via AJAX, tanpa reload halaman penuh.
    // ================================================================
    function bindFilterAutoReload() {
        var $ = jQuery;

        // Bulan
        var $month = $('#filterMonth');
        if ($month.length) {
            $month.on('change', function () {
                var params = new URLSearchParams(window.location.search);
                params.set('month', this.value);
                var dept = document.getElementById('filterDepartment');
                var q = document.getElementById('filterSearch');
                params.set('department_id', dept ? (dept.value || '') : '');
                params.set('q', q ? (q.value || '') : '');
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                loadData();
            });
        }

        // Departemen (Select2)
        var $dept = $('#filterDepartment');
        if ($dept.length) {
            $dept.on('change', function () {
                var params = new URLSearchParams(window.location.search);
                var month = document.getElementById('filterMonth');
                var q = document.getElementById('filterSearch');
                if (month && month.value) params.set('month', month.value);
                params.set('department_id', this.value || '');
                params.set('q', q ? (q.value || '') : '');
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                loadData();
            });
        }

        // Karyawan (Select2)
        var $emp = $('#filterSearch');
        if ($emp.length) {
            $emp.on('change', function () {
                var params = new URLSearchParams(window.location.search);
                var month = document.getElementById('filterMonth');
                var dept = document.getElementById('filterDepartment');
                if (month && month.value) params.set('month', month.value);
                params.set('department_id', dept ? (dept.value || '') : '');
                params.set('q', this.value || '');
                window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                loadData();
            });
        }
    }

    function boot() {
        initSelect2();
        bindFilterAutoReload();
        loadData();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>

</body>
</html>
