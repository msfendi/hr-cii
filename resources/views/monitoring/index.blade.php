<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Server Monitoring - hris.chutex.id</title>

    <!-- SB Admin 2 (Bootstrap + Fonts) -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/css/sb-admin-2.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <style>
    /* ... style lama tetap ... */
    .ssl-ok   { color: #1cc88a; }
    .ssl-warn { color: #f6c23e; }
    .ssl-danger { color: #e74a3b; }
        .chart-area { position: relative; height: 260px; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-live { background: #1cc88a; box-shadow: 0 0 0 3px rgba(28,200,138,.25); animation: pulse 1.5s infinite; }
        .status-down { background: #e74a3b; box-shadow: 0 0 0 3px rgba(231,74,59,.25); }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(28,200,138,.5);} 70% { box-shadow: 0 0 0 8px rgba(28,200,138,0);} 100% { box-shadow: 0 0 0 0 rgba(28,200,138,0);} }
        .progress-sm { height: 6px; }
        .error-banner {
            display: none;
            align-items: center;
            gap: 10px;
            background: #fdeceb;
            border: 1px solid #f1b6b0;
            color: #7a1f16;
            padding: 10px 16px;
            border-radius: .35rem;
            margin-bottom: 1rem;
        }
        .error-banner.show { display: flex; }
        .error-banner .retry-btn { margin-left: auto; white-space: nowrap; }

        /* --- GA4 Realtime overview style (tema biru, mengikuti warna utama dashboard) --- */
        .ga4-rt-card { border-radius: .5rem; }
        .ga4-rt-number { font-size: 2.2rem; font-weight: 700; line-height: 1; color: #3a3b45; }
        .ga4-rt-number-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #858796; font-weight: 700; }
        .ga4-rt-minibar-wrap { display: flex; align-items: flex-end; gap: 2px; height: 90px; }
        .ga4-rt-minibar { flex: 1 1 0; background: #a6c0f5; border-radius: 2px 2px 0 0; min-height: 2px; transition: height .3s ease; }
        .ga4-rt-minibar.is-now { background: #4e73df; }
        .ga4-rt-axis { display: flex; justify-content: space-between; font-size: .65rem; color: #b7b9cc; margin-top: 4px; }
        .ga4-rt-list-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #eaecf4; font-size: .85rem; }
        .ga4-rt-list-row:last-child { border-bottom: none; }
        .ga4-rt-list-name { color: #3a3b45; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%; }
        .ga4-rt-list-value { font-weight: 700; color: #3a3b45; }
        .ga4-rt-bar-track { background: #eaecf4; height: 5px; border-radius: 3px; margin-top: 3px; overflow: hidden; }
        .ga4-rt-bar-fill { background: #4e73df; height: 100%; border-radius: 3px; }
        .ga4-rt-panel-title { font-size: .8rem; font-weight: 700; color: #4e4f5a; text-transform: uppercase; letter-spacing: .03em; }
        .ga4-rt-rank { color: #b7b9cc; font-weight: 700; margin-right: 6px; }
        .ga4-rt-empty { text-align: center; color: #b7b9cc; padding: 30px 0; font-size: .85rem; }

        /* --- GA4 geographic map --- */
        #ga4Map { height: 320px; border-radius: .35rem; z-index: 1; }
        .ga4-map-legend { max-height: 320px; overflow-y: auto; }
    </style>
</head>
<body id="page-top">

<div id="wrapper">

    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>
                <div class="ml-auto d-flex align-items-center mr-3">
                    <span class="status-dot" id="statusDot"></span>
                    <span class="text-gray-600 small" id="statusText">Menghubungkan ke node_exporter...</span>
                </div>
            </nav>
            <!-- End Topbar -->

            <div class="container-fluid">

                <h1 class="h3 mb-4 text-gray-800">Monitoring Server &mdash; hris.chutex.id</h1>

                <!-- Error banner: muncul kalau request ke Laravel gagal -->
                <div class="error-banner" id="errorBanner">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="errorBannerText">Gagal memuat data.</span>
                    <button class="btn btn-sm btn-outline-danger retry-btn" id="retryBtn">
                        <i class="fas fa-redo"></i> Coba Lagi
                    </button>
                </div>

                <!-- Cards Ringkasan -->
                <div class="row">

                    <div class="col-xl-2 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">CPU Usage</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="cpuPercent">0</span>%</div>
                                        <div class="progress progress-sm mt-2">
                                            <div id="cpuBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-microchip fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">RAM Usage</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="ramPercent">0</span>%
                                            <small class="text-gray-500" id="ramDetail">(0/0 GB)</small>
                                        </div>
                                        <div class="progress progress-sm mt-2">
                                            <div id="ramBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-memory fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-6 mb-4">
                        <div class="card border-left-dark shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Storage</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="diskPercent">0</span>%
                                            <small class="text-gray-500" id="diskDetail">(0/0 GB)</small>
                                        </div>
                                        <div class="progress progress-sm mt-2">
                                            <div id="diskBar" class="progress-bar bg-dark" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">Sisa: <span id="diskLeft">0</span> GB</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-hdd fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Network Traffic</div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                                            &#8595; <span id="rxMbps">0</span> Mbps<br>
                                            &#8593; <span id="txMbps">0</span> Mbps
                                        </div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-network-wired fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            SSL Certificate
                                            <span class="status-dot" id="sslDot" style="width:8px;height:8px;"></span>
                                        </div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800" id="sslDomain">-</div>
                                        <div class="text-xs text-gray-600 mt-1">
                                            Issuer: <span id="sslIssuer">-</span><br>
                                            Berlaku s/d: <span id="sslExpiry">-</span>
                                            &middot; <span id="sslDaysLeft">-</span>
                                        </div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-lock fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-2 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Visitor Aktif (GA4)
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <span id="ga4ActiveNow">-</span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1" id="ga4Today">
                                            Hari ini: - users
                                        </div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- End Cards Ringkasan -->

                <!-- Charts -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">CPU Usage (%) - Live</h6></div>
                            <div class="card-body"><div class="chart-area"><canvas id="cpuChart"></canvas></div></div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success">RAM Usage (%) - Live</h6></div>
                            <div class="card-body"><div class="chart-area"><canvas id="ramChart"></canvas></div></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-info">Network Traffic (Mbps) - Live</h6></div>
                            <div class="card-body"><div class="chart-area"><canvas id="netChart"></canvas></div></div>
                        </div>
                    </div>
                </div>
                <!-- End Charts -->

                <!-- ============================================================ -->
                <!-- GA4 Realtime Overview (dipisah dari card Active users by city) -->
                <!-- ============================================================ -->
                <div class="row">
                    <!-- Card 1: Realtime overview (angka + bar per menit) -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow ga4-rt-card h-100">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-circle text-success" style="font-size:.5rem; vertical-align:middle;"></i>
                                    &nbsp;Realtime overview
                                </h6>
                                <div class="small text-gray-600">
                                    Sesi hari ini: <span id="ga4Sessions">-</span> &middot;
                                    Pageviews hari ini: <span id="ga4PageViews">-</span> &middot;
                                    Bounce: <span id="ga4Bounce">-</span>%
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="ga4Unavailable" class="text-center text-muted py-5" style="display:none;">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                                    GA4 belum tersedia: <span id="ga4ErrorText"></span>
                                </div>

                                <div id="ga4RealtimeWrap" style="display:none;">
                                    <div class="d-flex" style="gap: 40px;">
                                        <div>
                                            <div class="ga4-rt-number-label">Active users in last 30 minutes</div>
                                            <div class="ga4-rt-number" id="ga4Active30">0</div>
                                        </div>
                                        <div>
                                            <div class="ga4-rt-number-label">Active users in last 5 minutes</div>
                                            <div class="ga4-rt-number" id="ga4Active5">0</div>
                                        </div>
                                    </div>

                                    <div class="ga4-rt-number-label mt-4 mb-2">Active users per minute</div>
                                    <div class="ga4-rt-minibar-wrap" id="ga4MinuteBars"></div>
                                    <div class="ga4-rt-axis">
                                        <span>-30 min</span>
                                        <span>-15 min</span>
                                        <span>-1 min</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Active users by city (peta + legend), terpisah dari card Realtime overview -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow ga4-rt-card h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Active users by city</h6>
                            </div>
                            <div class="card-body">
                                <div id="ga4MapUnavailable" class="text-center text-muted py-5" style="display:none;">
                                    <i class="fas fa-map-marked-alt fa-2x mb-2"></i><br>
                                    GA4 belum tersedia: <span id="ga4MapErrorText"></span>
                                </div>

                                <div id="ga4MapWrap" style="display:none;">
                                    <div class="ga4-rt-panel-title mb-2">Active users by city</div>
                                    <div id="ga4Map"></div>
                                    <div class="ga4-map-legend mt-2" id="ga4ByCity"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" id="ga4BreakdownRow" style="display:none;">
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Active users by First user source</h6></div>
                            <div class="card-body"><div id="ga4BySource"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Active users by Audience</h6></div>
                            <div class="card-body"><div id="ga4ByAudience"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Views by Page title and screen name</h6></div>
                            <div class="card-body"><div id="ga4ByPageViews"></div></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Event count by Event name</h6></div>
                            <div class="card-body"><div id="ga4ByEvent"></div></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Trend Visitor (7 Hari) - GA4</h6></div>
                            <div class="card-body"><div class="chart-area"><canvas id="ga4Chart"></canvas></div></div>
                        </div>
                    </div>
                </div>
                <!-- End GA4 Realtime Overview -->

                <!-- Tabel Detail Storage -->
                <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-dark">Detail Storage per Partisi/Mountpoint</h6></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Mountpoint</th>
                                                <th>Device</th>
                                                <th>Filesystem</th>
                                                <th>Total (GB)</th>
                                                <th>Terpakai (GB)</th>
                                                <th>Sisa (GB)</th>
                                                <th style="width: 180px;">Penggunaan</th>
                                            </tr>
                                        </thead>
                                        <tbody id="diskTable">
                                            <tr><td colspan="7" class="text-center text-muted">Memuat data...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End Tabel Detail Storage -->

            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/js/sb-admin-2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
const ga4Chart = makeLineChart(document.getElementById('ga4Chart'), 'Total Users', '#4e73df');
const STATS_URL = "{{ route('monitoring.stats') }}";
const POLL_MS = 5000; // interval polling live, ubah sesuai kebutuhan
const MAX_POINTS = 20;

let pollTimer = null;

function nowLabel() {
    return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function renderSsl(ssl) {
    const dot = document.getElementById('sslDot');
    const domainEl = document.getElementById('sslDomain');
    const issuerEl = document.getElementById('sslIssuer');
    const expiryEl = document.getElementById('sslExpiry');
    const daysEl = document.getElementById('sslDaysLeft');

    if (!ssl) {
        dot.className = 'status-dot status-down';
        domainEl.textContent = '-';
        issuerEl.textContent = '-';
        expiryEl.textContent = '-';
        daysEl.textContent = '-';
        return;
    }

    domainEl.textContent = ssl.host || '-';

    if (!ssl.valid) {
        dot.className = 'status-dot status-down';
        issuerEl.textContent = '-';
        expiryEl.textContent = '-';
        daysEl.innerHTML = `<span class="ssl-danger">${escapeHtml(ssl.error || 'Gagal cek sertifikat')}</span>`;
        return;
    }

    issuerEl.textContent = ssl.issuer || '-';
    expiryEl.textContent = ssl.valid_to || '-';

    const days = ssl.days_left;
    let cls = 'ssl-ok';
    let label = `${days} hari lagi`;

    if (ssl.expired || days < 0) {
        cls = 'ssl-danger';
        label = 'Kadaluarsa';
        dot.className = 'status-dot status-down';
    } else if (days <= 7) {
        cls = 'ssl-danger';
        dot.className = 'status-dot status-live';
    } else if (days <= 30) {
        cls = 'ssl-warn';
        dot.className = 'status-dot status-live';
    } else {
        dot.className = 'status-dot status-live';
    }

    daysEl.innerHTML = `<span class="${cls} font-weight-bold">${label}</span>`;
}

function makeLineChart(ctx, label, color) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: label,
                data: [],
                borderColor: color,
                backgroundColor: color + '22',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });
}

const cpuChart = makeLineChart(document.getElementById('cpuChart'), 'CPU %', '#4e73df');
const ramChart = makeLineChart(document.getElementById('ramChart'), 'RAM %', '#1cc88a');
const netChart = new Chart(document.getElementById('netChart'), {
    type: 'line',
    data: {
        labels: [],
        datasets: [
            { label: 'Download (Mbps)', data: [], borderColor: '#36b9cc', backgroundColor: '#36b9cc22', borderWidth: 2, tension: 0.3, fill: true, pointRadius: 0 },
            { label: 'Upload (Mbps)',   data: [], borderColor: '#f6c23e', backgroundColor: '#f6c23e22', borderWidth: 2, tension: 0.3, fill: true, pointRadius: 0 },
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { display: true, position: 'bottom' } }
    }
});

/* ---------- Peta geografis GA4 (Leaflet + OpenStreetMap) ----------
   Zoom HANYA lewat tombol +/- (zoomControl), scroll wheel & interaksi
   lain yang bisa memicu zoom sengaja dimatikan semua. */
const ga4Map = L.map('ga4Map', {
    zoomControl: true,          // tombol +/- tetap aktif
    scrollWheelZoom: false,     // zoom via scroll mouse dimatikan
    doubleClickZoom: false,     // zoom via double click dimatikan
    touchZoom: false,           // zoom via pinch/touch dimatikan
    boxZoom: false              // zoom via drag-select dimatikan
}).setView([-2.5, 118], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(ga4Map);
let ga4MapMarkers = [];
let ga4MapSizedOnce = false;

function renderGa4Map(byCity) {
    // Leaflet butuh invalidateSize() setelah container-nya pertama kali terlihat (sebelumnya display:none)
    if (!ga4MapSizedOnce) {
        setTimeout(() => ga4Map.invalidateSize(), 50);
        ga4MapSizedOnce = true;
    }

    ga4MapMarkers.forEach(m => ga4Map.removeLayer(m));
    ga4MapMarkers = [];

    if (!byCity || byCity.length === 0) return;

    const plottable = byCity.filter(c => c.lat != null && c.lng != null);
    if (plottable.length === 0) return;

    const max = Math.max(1, ...plottable.map(c => c.value));
    plottable.forEach(c => {
        const radius = 6 + (c.value / max) * 16;
        const marker = L.circleMarker([c.lat, c.lng], {
            radius: radius,
            fillColor: '#4e73df',
            color: '#224abe',
            weight: 1,
            fillOpacity: 0.6
        }).bindPopup(`<strong>${escapeHtml(c.city)}${c.country ? ', ' + escapeHtml(c.country) : ''}</strong><br>${c.value} active users`);
        marker.addTo(ga4Map);
        ga4MapMarkers.push(marker);
    });
}

function pushPoint(chart, label, values) {
    chart.data.labels.push(label);
    chart.data.datasets.forEach((ds, i) => ds.data.push(values[i]));
    if (chart.data.labels.length > MAX_POINTS) {
        chart.data.labels.shift();
        chart.data.datasets.forEach(ds => ds.data.shift());
    }
    chart.update();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

/* ---------- GA4 Realtime overview helpers ---------- */

function renderMinuteBars(perMinute) {
    const wrap = document.getElementById('ga4MinuteBars');
    if (!perMinute || perMinute.length === 0) {
        wrap.innerHTML = '';
        return;
    }
    const max = Math.max(1, ...perMinute.map(p => p.value));
    wrap.innerHTML = perMinute.map((p, idx) => {
        const heightPct = Math.max(3, Math.round((p.value / max) * 100));
        const isNow = idx === perMinute.length - 1;
        return `<div class="ga4-rt-minibar${isNow ? ' is-now' : ''}" style="height:${heightPct}%" title="${escapeHtml(p.label)}: ${p.value}"></div>`;
    }).join('');
}

function renderRankedList(containerId, items, opts = {}) {
    const el = document.getElementById(containerId);
    if (!items || items.length === 0) {
        el.innerHTML = '<div class="ga4-rt-empty">No data available</div>';
        return;
    }
    const max = Math.max(1, ...items.map(i => i.value));
    const limited = items.slice(0, opts.limit || 6);
    el.innerHTML = limited.map((item, idx) => `
        <div class="ga4-rt-list-row" style="display:block;">
            <div class="d-flex justify-content-between align-items-center">
                <span class="ga4-rt-list-name"><span class="ga4-rt-rank">#${idx + 1}</span>${escapeHtml(item.name)}</span>
                <span class="ga4-rt-list-value">${item.value}</span>
            </div>
            <div class="ga4-rt-bar-track"><div class="ga4-rt-bar-fill" style="width:${Math.round((item.value / max) * 100)}%"></div></div>
        </div>
    `).join('');
}

function renderByCity(byCity) {
    const el = document.getElementById('ga4ByCity');
    if (!byCity || byCity.length === 0) {
        el.innerHTML = '<div class="ga4-rt-empty">No data available</div>';
        return;
    }
    const max = Math.max(1, ...byCity.map(c => c.value));
    el.innerHTML = byCity.map((c, idx) => {
        const label = c.city && c.city !== '(not set)' ? `${c.city}${c.country ? ', ' + c.country : ''}` : (c.country || '(not set)');
        return `
        <div class="ga4-rt-list-row" style="display:block;">
            <div class="d-flex justify-content-between align-items-center">
                <span class="ga4-rt-list-name"><span class="ga4-rt-rank">#${idx + 1}</span>${escapeHtml(label)}</span>
                <span class="ga4-rt-list-value">${c.value}</span>
            </div>
            <div class="ga4-rt-bar-track"><div class="ga4-rt-bar-fill" style="width:${Math.round((c.value / max) * 100)}%"></div></div>
        </div>`;
    }).join('');
}

function renderGa4(ga4) {
    const unavailableEl = document.getElementById('ga4Unavailable');
    const realtimeWrap = document.getElementById('ga4RealtimeWrap');
    const mapUnavailableEl = document.getElementById('ga4MapUnavailable');
    const mapWrap = document.getElementById('ga4MapWrap');
    const breakdownRow = document.getElementById('ga4BreakdownRow');

    if (!ga4 || !ga4.available) {
        document.getElementById('ga4ActiveNow').textContent = '-';
        document.getElementById('ga4Today').textContent = 'GA4 tidak tersedia';

        const errMsg = (ga4 && ga4.error) ? ga4.error : 'tidak diketahui';
        document.getElementById('ga4ErrorText').textContent = errMsg;
        document.getElementById('ga4MapErrorText').textContent = errMsg;

        unavailableEl.style.display = 'block';
        realtimeWrap.style.display = 'none';
        mapUnavailableEl.style.display = 'block';
        mapWrap.style.display = 'none';
        breakdownRow.style.display = 'none';
        return;
    }

    unavailableEl.style.display = 'none';
    realtimeWrap.style.display = 'block';
    mapUnavailableEl.style.display = 'none';
    mapWrap.style.display = 'block';
    breakdownRow.style.display = 'flex';

    document.getElementById('ga4ActiveNow').textContent = ga4.active_now;
    document.getElementById('ga4Today').textContent = `Hari ini: ${ga4.today.total_users} users`;
    document.getElementById('ga4Sessions').textContent = ga4.today.sessions;
    document.getElementById('ga4PageViews').textContent = ga4.today.page_views;
    document.getElementById('ga4Bounce').textContent = ga4.today.bounce_rate;

    // Angka besar: active users last 30 min / last 5 min
    document.getElementById('ga4Active30').textContent = ga4.active_last_30 ?? 0;
    document.getElementById('ga4Active5').textContent = ga4.active_last_5 ?? 0;

    // Bar chart active users per minute
    renderMinuteBars(ga4.per_minute);

    // Breakdown panels
    renderByCity(ga4.by_city);
    renderGa4Map(ga4.by_city);
    renderRankedList('ga4BySource', ga4.by_source, { limit: 6 });
    renderRankedList('ga4ByAudience', ga4.by_audience, { limit: 6 });
    renderRankedList('ga4ByPageViews', ga4.by_page_views, { limit: 6 });
    renderRankedList('ga4ByEvent', ga4.by_event, { limit: 6 });

    // Trend chart - replace seluruh dataset tiap polling (bukan push incremental)
    ga4Chart.data.labels = ga4.trend.map(t => t.date);
    ga4Chart.data.datasets[0].data = ga4.trend.map(t => t.users);
    ga4Chart.update();
}

function renderDiskTable(filesystems) {
    const tbody = document.getElementById('diskTable');
    if (!filesystems || filesystems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Data storage tidak tersedia</td></tr>';
        return;
    }
    tbody.innerHTML = filesystems.map(fs => {
        const barColor = fs.percent >= 90 ? 'bg-danger' : (fs.percent >= 75 ? 'bg-warning' : 'bg-dark');
        return `
        <tr>
            <td><code>${escapeHtml(fs.mountpoint)}</code></td>
            <td>${escapeHtml(fs.device)}</td>
            <td>${escapeHtml(fs.fstype)}</td>
            <td>${fs.total_gb}</td>
            <td>${fs.used_gb}</td>
            <td>${fs.avail_gb}</td>
            <td>
                <div class="progress progress-sm">
                    <div class="progress-bar ${barColor}" role="progressbar" style="width:${fs.percent}%"></div>
                </div>
                <small class="text-gray-600">${fs.percent}%</small>
            </td>
        </tr>`;
    }).join('');
}

function showErrorBanner(message) {
    document.getElementById('errorBannerText').textContent = message;
    document.getElementById('errorBanner').classList.add('show');
}

function hideErrorBanner() {
    document.getElementById('errorBanner').classList.remove('show');
}

function setDisconnectedStatus(message) {
    const dot = document.getElementById('statusDot');
    const statusText = document.getElementById('statusText');
    dot.className = 'status-dot status-down';
    statusText.innerHTML = `<span class="text-danger font-weight-bold">${escapeHtml(message)}</span>`;
}

async function fetchStats() {
    try {
        const res = await fetch(STATS_URL, { headers: { 'Accept': 'application/json' } });

        // Kalau Laravel error (500, 404, dsb) biasanya body-nya HTML, bukan JSON.
        // Tangkap ini secara eksplisit supaya pesan errornya jelas, bukan generik.
        const contentType = res.headers.get('content-type') || '';
        if (!res.ok) {
            let detail = `HTTP ${res.status} ${res.statusText}`;
            if (contentType.includes('application/json')) {
                try {
                    const errJson = await res.json();
                    detail = errJson.message || detail;
                } catch (_) { /* biarkan detail default */ }
            }
            throw new Error(detail);
        }
        if (!contentType.includes('application/json')) {
            throw new Error('Server tidak mengembalikan JSON (kemungkinan error/redirect di Laravel)');
        }

        const data = await res.json();
        const label = nowLabel();

        // CPU
        document.getElementById('cpuPercent').textContent = data.cpu.percent;
        document.getElementById('cpuBar').style.width = data.cpu.percent + '%';
        pushPoint(cpuChart, label, [data.cpu.percent]);

        // RAM
        document.getElementById('ramPercent').textContent = data.ram.percent;
        document.getElementById('ramBar').style.width = data.ram.percent + '%';
        document.getElementById('ramDetail').textContent = `(${data.ram.used_gb}/${data.ram.total_gb} GB)`;
        pushPoint(ramChart, label, [data.ram.percent]);

        // Network
        document.getElementById('rxMbps').textContent = data.network.rx_mbps;
        document.getElementById('txMbps').textContent = data.network.tx_mbps;
        pushPoint(netChart, label, [data.network.rx_mbps, data.network.tx_mbps]);

        // Storage
        if (data.disk && data.disk.main) {
            const m = data.disk.main;
            document.getElementById('diskPercent').textContent = m.percent;
            document.getElementById('diskBar').style.width = m.percent + '%';
            document.getElementById('diskDetail').textContent = `(${m.used_gb}/${m.total_gb} GB)`;
            document.getElementById('diskLeft').textContent = m.avail_gb;
        }
        renderDiskTable(data.disk ? data.disk.filesystems : []);
        renderSsl(data.ssl);
        renderGa4(data.ga4);

        // Status koneksi node_exporter (bukan error Laravel, tapi node_exporter yang unreachable)
        const dot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        if (data.exporter_ok) {
            dot.className = 'status-dot status-live';
            statusText.innerHTML = `Live dari HRIS Chutex Server &middot; server time: <strong>${data.server_time}</strong>`;
        } else {
            dot.className = 'status-dot status-down';
            statusText.innerHTML = `<span class="text-danger font-weight-bold">Tidak bisa konek ke node_exporter (${escapeHtml(data.exporter_url)})</span>`;
        }

        hideErrorBanner();
    } catch (e) {
        console.error('Gagal mengambil data monitoring:', e);
        setDisconnectedStatus('Gagal memuat data dari server Laravel');
        showErrorBanner(e.message || 'Gagal memuat data dari server Laravel');
    }
}

document.getElementById('retryBtn').addEventListener('click', () => {
    hideErrorBanner();
    fetchStats();
});

function startPolling() {
    fetchStats();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(fetchStats, POLL_MS);
}

startPolling();
</script>

</body>
</html>