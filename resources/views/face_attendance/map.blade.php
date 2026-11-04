<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<!-- Leaflet & Leaflet-Draw CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />

<body id="page-top">
@include('sweetalert::alert')

<div id="wrapper">
    {{-- Tampilkan sidebar HANYA jika user login --}}
    @if(auth()->check() || Session::has('face_attendance_npk'))
        @include('layout.sidebar')
    @endif

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @if(auth()->check() || Session::has('face_attendance_npk'))
                @include('layout.navbar')
            @endif

            <!-- Begin Page Content -->
            <div class="container-fluid py-3">

                <!-- Page Header SBAdmin 2 -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        <i class="fas fa-map-marked-alt text-primary mr-2"></i> Pengaturan Area Geo Map Presensi Wajah
                    </h1>
                </div>

                <div class="row">
                    <!-- Form Input / Edit Lokasi Kerja -->
                    <div class="col-lg-4 mb-4">
                        <div class="card shadow" id="cardForm">
                            <div class="card-header py-3 bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold" id="formTitle">
                                    <i class="fas fa-edit mr-1"></i> Form Lokasi Kerja
                                </h6>
                                <span id="badgeEditMode" class="badge badge-warning text-dark font-weight-bold d-none">Mode Edit</span>
                            </div>
                            <div class="card-body">
                                <form id="formLocation">
                                    @csrf
                                    <input type="hidden" id="loc_id" name="id">

                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-gray-700 small">Nama Lokasi / Pabrik</label>
                                        <input type="text" id="loc_name" name="name" class="form-control shadow-sm" placeholder="Contoh: Pabrik Chutex Area A" required>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold text-gray-700 small">Keterangan / Alamat</label>
                                        <textarea id="loc_desc" name="description" class="form-control shadow-sm" rows="2" placeholder="Keterangan singkat..."></textarea>
                                    </div>

                                    <div class="form-group mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold text-gray-700 small mb-0">Data Polygon GeoJSON</label>
                                            <span class="badge badge-light border text-muted font-weight-normal small">Auto-generated</span>
                                        </div>
                                        <textarea id="polygon_geojson" name="polygon_geojson" class="form-control bg-light font-monospace small" rows="4" readonly required placeholder="Gunakan tool draw polygon di peta atau klik Edit pada tabel untuk mengubah titik sudut..."></textarea>
                                    </div>

                                    <div class="form-group form-check mb-3">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label font-weight-bold text-gray-700 small" for="is_active">Aktifkan Area Ini</label>
                                    </div>

                                    <div class="d-flex">
                                        <button type="submit" id="btnSubmitLocation" class="btn btn-primary btn-block rounded-pill font-weight-bold shadow-sm">
                                            <i class="fas fa-save mr-1"></i> Simpan Lokasi Kerja
                                        </button>
                                        <button type="button" id="btnCancelEdit" class="btn btn-secondary rounded-pill ml-2 px-3 shadow-sm d-none" onclick="cancelEdit()">
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Peta Interaktif Leaflet -->
                    <div class="col-lg-8 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 bg-gradient-primary text-white d-flex align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold">
                                    <i class="fas fa-draw-polygon mr-1"></i> Peta Area Geofencing
                                </h6>
                                <span class="badge badge-light text-dark font-weight-normal small">Gunakan ikon polygon untuk gambar / ikon edit untuk ubah titik</span>
                            </div>
                            <div class="card-body p-0">
                                <!-- Cari Titik Koordinat -->
                                <div class="px-3 pt-3 pb-2 border-bottom bg-light">
                                    <label class="font-weight-bold text-gray-700 small mb-1 d-block">
                                        <i class="fas fa-crosshairs text-primary mr-1"></i>
                                        Cari Titik Koordinat (Latitude, Longitude)
                                    </label>
                                    <div class="form-row align-items-end">
                                        <div class="col-sm-5 mb-2 mb-sm-0">
                                            <input type="text" id="searchLat" class="form-control form-control-sm shadow-sm" placeholder="Latitude, mis. -7.010300866192923">
                                        </div>
                                        <div class="col-sm-5 mb-2 mb-sm-0">
                                            <input type="text" id="searchLng" class="form-control form-control-sm shadow-sm" placeholder="Longitude, mis. 107.74745258715632">
                                        </div>
                                        <div class="col-sm-2 mb-2 mb-sm-0">
                                            <button type="button" id="btnSearchCoord" class="btn btn-sm btn-primary btn-block rounded-pill shadow-sm">
                                                <i class="fas fa-search mr-1"></i> Cari
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-row mt-1">
                                        <div class="col-12">
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                Ambil koordinat dari Google Maps (klik kanan pada titik &rarr; salin koordinat), lalu tempel di atas. Peta akan langsung menuju titik tersebut, siap menggambar polygon.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div id="map" style="height: 520px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar Area Lokasi Tersimpan -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list mr-1"></i> Daftar Area Lokasi Presensi Terdaftar
                        </h6>
                        <span class="badge badge-primary">{{ count($locations) }} Lokasi</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 text-gray-800" id="tableLocations">
                                <thead class="bg-light font-weight-bold">
                                    <tr>
                                        <th class="px-4 py-3" style="width: 50px;">No</th>
                                        <th class="py-3">Nama Lokasi</th>
                                        <th class="py-3">Keterangan</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3 text-center" style="width: 180px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($locations as $index => $loc)
                                    <tr id="row-loc-{{ $loc->id }}">
                                        <td class="px-4">{{ $index + 1 }}</td>
                                        <td class="font-weight-bold text-primary">{{ $loc->name }}</td>
                                        <td class="small text-muted">{{ $loc->description ?: '-' }}</td>
                                        <td>
                                            @if($loc->is_active)
                                                <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Aktif</span>
                                            @else
                                                <span class="badge badge-secondary px-2 py-1">Non-Aktif</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-info rounded-circle px-2 mr-1 shadow-sm" onclick="focusLocationOnMap({{ $loc->id }})" title="Tampilkan Lokasi Ini di Peta" aria-label="Tampilkan Lokasi Ini di Peta">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-circle px-2 mr-1 shadow-sm" onclick="editLocation({{ $loc->id }})" title="Edit Lokasi &amp; Polygon" aria-label="Edit Lokasi">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            @if($loc->is_active)
                                                <button type="button" class="btn btn-sm btn-outline-warning rounded-circle px-2 mr-1 shadow-sm" onclick="toggleLocationStatus({{ $loc->id }}, 0, @js($loc->name))" title="Non-Aktifkan Lokasi Ini" aria-label="Non-Aktifkan Lokasi">
                                                    <i class="fas fa-toggle-on"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-success rounded-circle px-2 mr-1 shadow-sm" onclick="toggleLocationStatus({{ $loc->id }}, 1, @js($loc->name))" title="Aktifkan Lokasi Ini" aria-label="Aktifkan Lokasi">
                                                    <i class="fas fa-toggle-off"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle px-2 shadow-sm" onclick="deleteLocation({{ $loc->id }})" title="Hapus Lokasi" aria-label="Hapus Lokasi">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            Belum ada lokasi kerja yang didaftarkan.
                                        </td>
                                    </tr>
                                    @endforelse
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

<!-- Leaflet & SweetAlert2 JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let existingLocations = @json($locations);
    let map = L.map('map').setView([-6.2088, 106.8456], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    let drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    let drawControl = new L.Control.Draw({
        edit: {
            featureGroup: drawnItems
        },
        draw: {
            polygon: true,
            polyline: false,
            rectangle: true,
            circle: false,
            circlemarker: false,
            marker: false
        }
    });
    map.addControl(drawControl);

    // Event saat selesai membuat polygon baru
    map.on(L.Draw.Event.CREATED, function (e) {
        let layer = e.layer;
        drawnItems.clearLayers();
        drawnItems.addLayer(layer);

        let geojson = layer.toGeoJSON();
        document.getElementById('polygon_geojson').value = JSON.stringify(geojson);
    });

    // Event saat polygon di drawnItems diedit titik sudutnya
    map.on(L.Draw.Event.EDITED, function (e) {
        let layers = e.layers;
        layers.eachLayer(function (layer) {
            let geojson = layer.toGeoJSON();
            document.getElementById('polygon_geojson').value = JSON.stringify(geojson);
        });
    });

    // Layer grup untuk lokasi yang sudah tersimpan
    let staticLayers = L.featureGroup().addTo(map);

    function renderStaticLocations() {
        staticLayers.clearLayers();
        let bounds = [];
        if (existingLocations && existingLocations.length > 0) {
            existingLocations.forEach(loc => {
                if (loc.polygon_geojson) {
                    try {
                        let geo = JSON.parse(loc.polygon_geojson);
                        let polyLayer = L.geoJSON(geo, {
                            style: {
                                color: loc.is_active ? '#4e73df' : '#858796',
                                weight: 2,
                                fillOpacity: 0.2
                            }
                        }).bindPopup('<b>' + loc.name + '</b><br>' + (loc.description || '') + '<br><button class="btn btn-sm btn-primary mt-2 rounded-pill" onclick="editLocation(' + loc.id + ')"><i class="fas fa-edit mr-1"></i> Edit Polygon</button>');
                        staticLayers.addLayer(polyLayer);
                        bounds.push(polyLayer.getBounds());
                    } catch(err) {}
                }
            });
            if (bounds.length > 0 && drawnItems.getLayers().length === 0) {
                let group = new L.featureGroup(bounds.map(b => L.rectangle(b)));
                map.fitBounds(group.getBounds());
            }
        }
    }

    renderStaticLocations();

    // ============================================================
    // CARI TITIK KOORDINAT
    // Admin mengambil lat/lng dari Google Maps, lalu peta langsung
    // bergerak ke titik tersebut supaya polygon mudah digambar
    // di area yang jauh dari titik awal.
    // ============================================================
    let searchMarker = null;
    let focusedLocationLayer = null;

    function parseCoord(value) {
        if (value === null || value === undefined) return NaN;
        // Terima format desimal biasa.
        let v = String(value).trim().replace(/,/g, '.');
        // Terima juga "lat, lng" yang ditempel di satu kolom.
        if (v.indexOf(',') !== -1 || v.indexOf(' ') !== -1) {
            let parts = v.split(/[,;\s]+/).filter(function (p) { return p !== ''; });
            if (parts.length >= 2) {
                return parseFloat(parts[0]);
            }
        }
        return parseFloat(v);
    }

    function gotoCoordinate(lat, lng) {
        if (!isFinite(lat) || !isFinite(lng)) {
            Swal.fire({
                icon: 'error',
                title: 'Koordinat tidak valid',
                text: 'Latitude dan Longitude harus berupa angka.\nContoh: -7.010300866192923 dan 107.74745258715632',
                confirmButtonColor: '#e74a3b'
            });
            return;
        }

        if (lat < -90 || lat > 90) {
            Swal.fire({ icon: 'error', title: 'Latitude di luar rentang', text: 'Latitude harus antara -90 dan 90.', confirmButtonColor: '#e74a3b' });
            return;
        }
        if (lng < -180 || lng > 180) {
            Swal.fire({ icon: 'error', title: 'Longitude di luar rentang', text: 'Longitude harus antara -180 dan 180.', confirmButtonColor: '#e74a3b' });
            return;
        }

        // Pindah ke titik koordinat dengan zoom dekat.
        map.flyTo([lat, lng], 17, { duration: 1.2 });

        // Tandai titik yang dicari agar admin jelas posisinya.
        if (searchMarker) {
            map.removeLayer(searchMarker);
        }
        searchMarker = L.marker([lat, lng], {
            draggable: true,
            icon: L.divIcon({
                className: '',
                html: '<div style="background:#e74a3b;color:#fff;border-radius:50%;width:18px;height:18px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>',
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            })
        }).addTo(map);

        searchMarker.bindPopup('<b>Titik koordinat</b><br>Lat: ' + lat + '<br>Lng: ' + lng).openPopup();

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Peta dipindahkan',
            text: 'Lat ' + lat + ' / Lng ' + lng + ' - siap menggambar polygon.',
            showConfirmButton: false,
            timer: 3500
        });
    }

    function searchCoordinate() {
        let lat = parseCoord(document.getElementById('searchLat').value);
        let lng = parseCoord(document.getElementById('searchLng').value);
        gotoCoordinate(lat, lng);
    }

    document.getElementById('btnSearchCoord').addEventListener('click', searchCoordinate);

    // Enter pada kolom koordinat = klik tombol Cari.
    ['searchLat', 'searchLng'].forEach(function (id) {
        document.getElementById(id).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchCoordinate();
            }
        });
    });

    // ============================================================
    // FOKUS PETA KE SATU LOKASI (tombol point / map-marker)
    // Menampilkan polygon lokasi tersebut di tengah peta, seperti
    // saat menekan tombol Edit, TAPI tanpa mengaktifkan mode edit.
    // ============================================================
    function focusLocationOnMap(id) {
        let loc = existingLocations.find(l => l.id == id);
        if (!loc) {
            fetch("{{ url('/face-attendance/location') }}/" + id)
                .then(res => res.json())
                .then(data => {
                    if (data.success) focusLocationLayer(data.data);
                })
                .catch(() => {});
            return;
        }
        focusLocationLayer(loc);
    }

    function focusLocationLayer(loc) {
        if (!loc || !loc.polygon_geojson) {
            Swal.fire({
                icon: 'warning',
                title: 'Polygon Tidak Tersedia',
                text: 'Lokasi "' + (loc.name || '-') + '" belum memiliki polygon.'
            });
            return;
        }

        try {
            let geo = JSON.parse(loc.polygon_geojson);
            let focusLayer = L.geoJSON(geo);
            let bounds = focusLayer.getBounds();

            if (bounds && bounds.isValid()) {
                // Samakan tampilan dengan tombol Edit: polygon terisi di tengah peta.
                map.fitBounds(bounds, { padding: [40, 40] });
            }

            // Tandai polygon yang sedang difokuskan supaya mudah dikenali.
            if (focusedLocationLayer) {
                map.removeLayer(focusedLocationLayer);
            }
            focusedLocationLayer = L.geoJSON(geo, {
                style: {
                    color: '#e74a3b',
                    weight: 3,
                    fillOpacity: 0.35
                }
            }).addTo(map);

            if (focusedLocationLayer.bindPopup) {
                focusedLocationLayer.bindPopup(
                    '<b>' + (loc.name || '-') + '</b>' +
                    (loc.description ? '<br>' + loc.description : '') +
                    '<br><span class="text-muted" style="font-size:11px">Lokasi yang sedang difokuskan</span>'
                ).openPopup();
            }

            // Gulir halaman ke peta supaya hasilnya langsung terlihat.
            let mapEl = document.getElementById('map');
            if (mapEl && mapEl.scrollIntoView) {
                mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            // invalidateSize supaya ukuran peta benar setelah scroll/animasinya.
            setTimeout(() => { if (map && map.invalidateSize) map.invalidateSize(); }, 350);
        } catch (e) {
            console.error('Error focus location:', e);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menampilkan Lokasi',
                text: 'Polygon lokasi tidak dapat diproses.'
            });
        }
    }

    // Fungsi Edit Lokasi & Polygon
    function editLocation(id) {
        let loc = existingLocations.find(l => l.id == id);
        if (!loc) {
            fetch("{{ url('/face-attendance/location') }}/" + id)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        applyEditLocation(data.data);
                    }
                });
            return;
        }
        applyEditLocation(loc);
    }

    function applyEditLocation(loc) {
        document.getElementById('loc_id').value = loc.id;
        document.getElementById('loc_name').value = loc.name;
        document.getElementById('loc_desc').value = loc.description || '';
        document.getElementById('polygon_geojson').value = loc.polygon_geojson;
        document.getElementById('is_active').checked = (loc.is_active == 1);

        document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit mr-1"></i> Edit Lokasi: ' + loc.name;
        document.getElementById('badgeEditMode').classList.remove('d-none');
        document.getElementById('btnSubmitLocation').innerHTML = '<i class="fas fa-save mr-1"></i> Update Lokasi Kerja';
        document.getElementById('btnCancelEdit').classList.remove('d-none');

        // Masukkan polygon ke drawnItems agar langsung bisa diedit titiknya di peta
        drawnItems.clearLayers();
        if (loc.polygon_geojson) {
            try {
                let geo = JSON.parse(loc.polygon_geojson);
                let editLayer = L.geoJSON(geo);
                editLayer.eachLayer(layer => {
                    drawnItems.addLayer(layer);
                    if (layer.editing) {
                        layer.editing.enable(); // Aktifkan handle titik sudut polygon langsung
                    }
                });
                map.fitBounds(drawnItems.getBounds());
            } catch(e) {
                console.error('Error parsing polygon:', e);
            }
        }

        // Scroll halus ke form
        document.getElementById('cardForm').scrollIntoView({ behavior: 'smooth', block: 'start' });

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: 'Mode Edit Polygon Aktif',
            text: 'Tarik titik sudut polygon di peta untuk mengubah bentuk area.',
            showConfirmButton: false,
            timer: 3500
        });
    }

    function cancelEdit() {
        document.getElementById('loc_id').value = '';
        document.getElementById('formLocation').reset();
        document.getElementById('polygon_geojson').value = '';
        document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit mr-1"></i> Form Lokasi Kerja';
        document.getElementById('badgeEditMode').classList.add('d-none');
        document.getElementById('btnSubmitLocation').innerHTML = '<i class="fas fa-save mr-1"></i> Simpan Lokasi Kerja';
        document.getElementById('btnCancelEdit').classList.add('d-none');

        drawnItems.clearLayers();
        renderStaticLocations();
    }

    // Submit form simpan / update lokasi
    document.getElementById('formLocation').addEventListener('submit', function(e) {
        e.preventDefault();

        let id = document.getElementById('loc_id').value;
        let name = document.getElementById('loc_name').value;
        let desc = document.getElementById('loc_desc').value;
        let geo = document.getElementById('polygon_geojson').value;
        let active = document.getElementById('is_active').checked ? 1 : 0;

        if (!geo) {
            Swal.fire('Perhatian', 'Silakan gambar atau tentukan polygon area kerja pada peta terlebih dahulu.', 'warning');
            return;
        }

        fetch("{{ route('face.attendance.location.store') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                id: id || null,
                name: name,
                description: desc,
                polygon_geojson: geo,
                is_active: active
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Berhasil', data.message, 'success').then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Gagal', data.message || 'Gagal menyimpan lokasi', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', 'Terjadi kesalahan sistem saat menyimpan lokasi.', 'error');
        });
    });

    // Ambil token CSRF dari form yang sudah ada di halaman.
    // Lebih andal daripada menyalin token lewat Blade, karena token
    // selalu sinkron dengan session yang sedang dipakai browser.
    function getCsrfToken() {
        let el = document.querySelector('input[name="_token"]');
        if (el && el.value) {
            return el.value;
        }
        let meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    // ============================================================
    // AKSI AKTIF / NON-AKTIF LOKASI (dari tabel)
    // ============================================================
    function toggleLocationStatus(id, newStatus, name) {
        let isEnable = (newStatus === 1 || newStatus === '1' || newStatus === true);
        let title = isEnable ? 'Aktifkan Lokasi?' : 'Non-Aktifkan Lokasi?';
        let text = isEnable
            ? 'Lokasi "' + name + '" akan diaktifkan dan dapat dipakai untuk presensi wajah.'
            : 'Lokasi "' + name + '" akan dinonaktifkan dan tidak dapat dipakai untuk presensi wajah.';

        Swal.fire({
            title: title,
            text: text,
            icon: isEnable ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: isEnable ? '#1cc88a' : '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: isEnable ? 'Ya, Aktifkan' : 'Ya, Non-Aktifkan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            fetch("{{ url('/face-attendance/location') }}/" + id + "/toggle-status", {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": getCsrfToken()
                },
                body: JSON.stringify({ is_active: isEnable ? 1 : 0 })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', data.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Gagal', data.message || 'Gagal mengubah status lokasi.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Terjadi kesalahan sistem saat mengubah status lokasi.', 'error');
            });
        });
    }

    function deleteLocation(id) {
        Swal.fire({
            title: 'Hapus Lokasi?',
            text: 'Area lokasi kerja ini tidak dapat digunakan lagi setelah dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            cancelButtonColor: '#858796',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch("{{ url('/face-attendance/location') }}/" + id, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Terhapus', data.message, 'success').then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                });
            }
        });
    }
</script>
</body>
</html>
