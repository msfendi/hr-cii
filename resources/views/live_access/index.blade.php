@extends('layouts.app')

@section('title', 'Live Access')

@push('styles')
<style>
    /* ===== Live Map (ala threat map) ===== */
    .lamap {
        position: relative;
        background: radial-gradient(1200px 620px at 52% 42%, #0a2136 0%, #04101c 72%);
        overflow: hidden;
    }
    #lamap-canvas {
        display: block;
        width: 100%;
        height: 460px;
    }
    .lamap-hud {
        position: absolute;
        top: 12px;
        left: 16px;
        color: #7dd3fc;
        font-size: .7rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        pointer-events: none;
        background: rgba(4, 16, 28, .72);
        border: 1px solid rgba(56, 189, 248, .18);
        border-radius: 8px;
        padding: 6px 12px;
    }
    .lamap-hud .hud-row { display: flex; gap: 18px; }
    .lamap-hud .hud-num {
        display: block;
        font-size: 1.35rem;
        font-weight: 700;
        color: #e0f2fe;
        line-height: 1.1;
    }
    .lamap-hud .hud-lbl { color: #94a3b8; }
    .lamap-hud .hud-hint {
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px dashed rgba(56, 189, 248, .2);
        font-size: .6rem;
        letter-spacing: 0;
        text-transform: none;
        color: #64748b;
    }
    /* Kontrol zoom peta (roda mouse juga bisa) */
    .lamap-zoom {
        position: absolute;
        right: 324px;
        bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        z-index: 3;
    }
    .lamap-zoom button {
        width: 32px;
        height: 32px;
        padding: 0;
        border: 1px solid rgba(56, 189, 248, .35);
        border-radius: 8px;
        background: rgba(4, 16, 28, .82);
        color: #7dd3fc;
        font-size: .8rem;
        line-height: 1;
        cursor: pointer;
    }
    .lamap-zoom button:hover { background: rgba(34, 211, 238, .18); color: #e0f2fe; }
    #lamap-canvas { cursor: grab; }
    #lamap-canvas.lamap-grabbing { cursor: grabbing; }
    /* Modal detail wilayah */
    .lamap-modal {
        background: #071a2b;
        border: 1px solid rgba(56, 189, 248, .32);
        color: #cbd5e1;
    }
    .lamap-modal .modal-header,
    .lamap-modal .modal-footer { border-color: rgba(56, 189, 248, .22); }
    .lamap-modal .modal-title { color: #e0f2fe; }
    .lamap-modal .close { color: #94a3b8; opacity: .9; text-shadow: none; }
    .lamap-modal .modal-body { max-height: 60vh; overflow-y: auto; }
    .lm-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
    .lm-chip {
        border: 1px solid rgba(56, 189, 248, .3);
        border-radius: 999px;
        padding: 2px 10px;
        font-size: .7rem;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: .03em;
    }
    .lm-chip b { color: #e0f2fe; font-size: .8rem; }
    .lm-chip.c-4xx { border-color: rgba(245, 158, 11, .5); }
    .lm-chip.c-5xx { border-color: rgba(239, 68, 68, .5); }
    .lm-line { font-size: .78rem; color: #94a3b8; margin-bottom: 8px; }
    .lm-sec {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #7dd3fc;
        margin: 10px 0 4px;
        border-top: 1px dashed rgba(56, 189, 248, .2);
        padding-top: 8px;
    }
    .lm-item { padding: 6px 0; border-bottom: 1px dashed rgba(56, 189, 248, .14); font-size: .78rem; }
    .lm-item:last-child { border-bottom: 0; }
    .lm-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; }
    .lm-row.meta { color: #64748b; font-size: .72rem; }
    .lm-row .ip { color: #e0f2fe; font-weight: 600; }
    .lm-badge {
        border: 1px solid rgba(34, 211, 238, .4);
        border-radius: 6px;
        padding: 0 6px;
        font-size: .62rem;
        color: #7dd3fc;
    }
    .lm-at { color: #64748b; font-size: .72rem; margin-left: auto; }
    .lm-empty { font-size: .8rem; color: #94a3b8; padding: 6px 0; }
    .lm-recent .lm-row { margin-bottom: 3px; }
    .lm-recent .lm-at { margin-left: 0; min-width: 62px; }
    .lm-path { color: #94a3b8; word-break: break-all; }
    .lamap-legend {
        position: absolute;
        left: 16px;
        bottom: 46px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: .7rem;
        color: #94a3b8;
        pointer-events: none;
    }
    .lamap-legend i { font-size: 8px; vertical-align: middle; }
    .lamap-foot {
        position: absolute;
        left: 16px;
        bottom: 10px;
        font-size: .7rem;
        color: #64748b;
        pointer-events: none;
    }
    .lamap-side {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 300px;
        max-height: calc(100% - 24px);
        overflow-y: auto;
        background: rgba(4, 16, 28, .86);
        border: 1px solid rgba(56, 189, 248, .25);
        border-radius: 8px;
        padding: 8px 10px;
    }
    .lamap-side-title {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #7dd3fc;
        margin-bottom: 6px;
    }
    .lamap-item {
        padding: 5px 0;
        border-bottom: 1px dashed rgba(56, 189, 248, .16);
        font-size: .7rem;
        color: #cbd5e1;
    }
    .lamap-item:last-child { border-bottom: 0; }
    .lamap-item .ip { color: #e0f2fe; font-weight: 600; }
    .lamap-item .meta { color: #64748b; }
    .lamap-item .line { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .lamap-item .trunc { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    @media (max-width: 991.98px) {
        #lamap-canvas { height: 340px; }
        .lamap-side { display: none; }
        .lamap-zoom { right: 12px; bottom: 56px; }
        .lamap-hud { padding: 5px 9px; }
        .lamap-hud .hud-row { gap: 13px; }
        .lamap-hud .hud-num { font-size: 1.1rem; }
        .lamap-hud .hud-lbl { font-size: .6rem; color: #a8bccf; }
        .lamap-hud .hud-hint { font-size: .55rem; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-globe-asia mr-2"></i>Live Access</h1>
        <span class="text-muted small" id="server-time">Memuat...</span>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="row" id="summary-cards">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Request (5 menit)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="sum-requests">0</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">IP Unik Aktif</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="sum-ips">0</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">User Aktif</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="sum-users">0</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Rata-rata Durasi</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="sum-duration">0 ms</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Live Map (ala threat map) --}}
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-globe mr-1"></i> Live Map Akses</h6>
                    <span class="badge badge-dark" id="lamap-badge">0 sumber</span>
                </div>
                <div class="card-body p-0">
                    <div class="lamap">
                        <canvas id="lamap-canvas" aria-label="Peta akses langsung"></canvas>

                        <div class="lamap-hud">
                            <div class="hud-row">
                                <div>
                                    <span class="hud-num" id="lamap-reqpm">0</span>
                                    <span class="hud-lbl">request/menit</span>
                                </div>
                                <div>
                                    <span class="hud-num" id="lamap-sources">0</span>
                                    <span class="hud-lbl">sumber aktif</span>
                                </div>
                                <div>
                                    <span class="hud-num" id="lamap-err">0</span>
                                    <span class="hud-lbl">error 4xx/5xx</span>
                                </div>
                            </div>
                            <div class="hud-hint">Klik negara untuk zoom + detail &middot; scroll = zoom &middot; geser peta = pindah</div>
                        </div>

                        <div class="lamap-zoom">
                            <button type="button" id="lamap-zoom-in" title="Perbesar"><i class="fas fa-plus"></i></button>
                            <button type="button" id="lamap-zoom-out" title="Perkecil"><i class="fas fa-minus"></i></button>
                            <button type="button" id="lamap-zoom-reset" title="Reset ke seluruh dunia"><i class="fas fa-compress"></i></button>
                        </div>

                        <div class="lamap-side">
                            <div class="lamap-side-title">Sumber teratas</div>
                            <div id="lamap-list"><div class="lamap-item meta">Memuat data...</div></div>
                        </div>

                        <div class="lamap-legend">
                            <span><i class="fas fa-circle" style="color:#22d3ee;"></i> publik</span>
                            <span><i class="fas fa-circle" style="color:#22c55e;"></i> jaringan lokal</span>
                            <span><i class="fas fa-circle" style="color:#f59e0b;"></i> ada 4xx</span>
                            <span><i class="fas fa-circle" style="color:#ef4444;"></i> ada 5xx</span>
                            <span><i class="fas fa-circle" style="color:#64748b;"></i> belum diketahui</span>
                        </div>

                        <div class="lamap-foot" id="lamap-foot">Origin: -</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail negara/region yang diklik di peta -->
    <div class="modal fade" id="lamapCountryModal" tabindex="-1" role="dialog" aria-labelledby="lamapCountryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content lamap-modal">
                <div class="modal-header py-2">
                    <div>
                        <h6 class="modal-title m-0" id="lamapCountryTitle">Wilayah</h6>
                        <div class="small text-muted" id="lamapCountrySub">-</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="lamapCountryBody"></div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-info" id="lamapCountryZoom">
                        <i class="fas fa-search-plus mr-1"></i>Zoom ke wilayah
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light" id="lamapCountryReset">
                        <i class="fas fa-undo mr-1"></i>Reset peta
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Peta akses per IP --}}
        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-network-wired mr-1"></i> Akses per IP (5 menit terakhir)</h6>
                    <span class="badge badge-primary" id="ip-count">0 IP</span>
                </div>
                <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                    <div id="ip-map" class="d-flex flex-wrap" style="gap: 10px;">
                        <div class="text-muted small w-100">Memuat data...</div>
                    </div>
                </div>
            </div>

            {{-- Peta akses per user --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-shield mr-1"></i> Akses per User (5 menit terakhir)</h6>
                    <span class="badge badge-info" id="user-count">0 user</span>
                </div>
                <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                    <div id="user-map" class="d-flex flex-wrap" style="gap: 10px;">
                        <div class="text-muted small w-100">Memuat data...</div>
                    </div>
                </div>
            </div>

            {{-- Tabel request terbaru --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-stream mr-1"></i> Request Terbaru</h6>
                </div>
                <div class="card-body table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm table-bordered" id="recent-table">
                        <thead>
                            <tr>
                                <th>Waktu</th><th>IP</th><th>User</th><th>NPK</th>
                                <th>Method</th><th>Path</th><th>Route</th><th>Status</th><th>ms</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Jobs / Queue --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks mr-1"></i> Jobs &amp; Queue</h6>
                    <span class="badge badge-info" id="jobs-badge">0 worker</span>
                </div>
                <div class="card-body" style="max-height: 260px; overflow-y: auto; font-size: 12.5px;">
                    <div id="jobs-panel" class="text-muted small">Memuat...</div>
                </div>
            </div>

            {{-- Services --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-server mr-1"></i> Services berjalan</h6>
                    <span class="badge badge-success" id="services-badge">0</span>
                </div>
                <div class="card-body" style="max-height: 260px; overflow-y: auto; font-size: 12.5px;">
                    <div id="services-panel" class="text-muted small">Memuat...</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const fmtTime = iso => {
        if (!iso) return '-';
        const d = new Date(iso);
        return isNaN(d) ? iso : d.toLocaleTimeString('id-ID');
    };
    const statusBadge = st => {
        if (!st) return '<span class="badge badge-secondary">-</span>';
        if (st < 300) return '<span class="badge badge-success">' + st + '</span>';
        if (st < 500) return '<span class="badge badge-warning">' + st + '</span>';
        return '<span class="badge badge-danger">' + st + '</span>';
    };
    const statusCounts = o =>
        '<span class="badge badge-success">2xx</span> ' + (o.status_2xx ?? 0) +
        ' · <span class="badge badge-warning">4xx</span> ' + (o.status_4xx ?? 0) +
        ' · <span class="badge badge-danger">5xx</span> ' + (o.status_5xx ?? 0);

    async function fetchJson(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return res.json();
    }

    async function refreshSummary() {
        try {
            const d = await fetchJson('{{ route("live-access.summary") }}');
            document.getElementById('sum-requests').textContent = d.total_requests;
            document.getElementById('sum-ips').textContent = d.unique_ips;
            document.getElementById('sum-users').textContent = d.active_users;
            document.getElementById('sum-duration').textContent = d.total_requests > 0 ? (d.avg_duration_ms + ' ms') : '—';
            document.getElementById('server-time').textContent = 'Server: ' + fmtTime(d.server_time) + ' WIB · window ' + d.window_minutes + ' menit';
        } catch (e) { /* abaikan */ }
    }

    async function refreshData() {
        try {
            const d = await fetchJson('{{ route("live-access.data") }}?minutes=5');
            document.getElementById('ip-count').textContent = d.by_ip.length + ' IP';

            const map = document.getElementById('ip-map');
            if (!d.by_ip.length) {
                map.innerHTML = '<div class="text-muted small w-100">Belum ada akses dalam 5 menit terakhir.</div>';
            } else {
                map.innerHTML = d.by_ip.map(ip => {
                    const intensity = Math.min(100, 15 + ip.requests * 10);
                    const routes = (ip.top_routes || []).map(r => esc(r.route) + ' ×' + r.count).join(', ');
                    const users = (ip.users || []).map(esc).join(', ') || (ip.npk || []).map(esc).join(', ') || '-';
                    return '' +
                    '<div class="ip-node" style="border:1px solid rgba(29,63,134,' + (intensity/100) + '); background: rgba(34,211,238,' + (intensity/300) + '); border-radius:10px; padding:8px 10px; min-width:150px;">' +
                        '<div class="font-weight-bold" style="color:#1d3f86;"><i class="fas fa-circle" style="font-size:8px; color:#22c55e;"></i> ' + esc(ip.ip) + '</div>' +
                        '<div class="small text-muted">' + ip.requests + ' request · ' + esc(users) + '</div>' +
                        '<div class="small" style="color:#475569;">' + esc(ip.last_path) + '</div>' +
                        (routes ? '<div class="small" style="color:#64748b;">top: ' + routes + '</div>' : '') +
                        '<div class="small">' + statusCounts(ip) + ' · ' + fmtTime(ip.last_at) + '</div>' +
                    '</div>';
                }).join('');
            }

            const ucount = document.getElementById('user-count');
            const umap = document.getElementById('user-map');
            const byUser = d.by_user || [];
            if (ucount) ucount.textContent = byUser.length + ' user';
            if (umap) {
                if (!byUser.length) {
                    umap.innerHTML = '<div class="text-muted small w-100">Belum ada akses user dalam 5 menit terakhir.</div>';
                } else {
                    umap.innerHTML = byUser.map(u => {
                        const intensity = Math.min(100, 15 + u.requests * 10);
                        const routes = (u.top_routes || []).map(r => esc(r.route) + ' ×' + r.count).join(', ');
                        const ips = (u.ips || []).map(esc).join(', ') || '-';
                        const name = u.user || u.npk || u.key || '(anonim)';
                        const npkTag = (u.user && u.npk) ? ' <span class="text-muted small">' + esc(u.npk) + '</span>' : '';
                        return '' +
                        '<div class="ip-node" style="border:1px solid rgba(29,63,134,' + (intensity/100) + '); background: rgba(34,211,238,' + (intensity/300) + '); border-radius:10px; padding:8px 10px; min-width:150px;">' +
                            '<div class="font-weight-bold" style="color:#1d3f86;"><i class="fas fa-circle" style="font-size:8px; color:#22c55e;"></i> ' + esc(name) + npkTag + '</div>' +
                            '<div class="small text-muted">' + u.requests + ' request · IP: ' + esc(ips) + '</div>' +
                            '<div class="small" style="color:#475569;">' + esc(u.last_path) + '</div>' +
                            (routes ? '<div class="small" style="color:#64748b;">top: ' + routes + '</div>' : '') +
                            '<div class="small">' + statusCounts(u) + ' · ' + fmtTime(u.last_at) + '</div>' +
                        '</div>';
                    }).join('');
                }
            }

            const tbody = document.querySelector('#recent-table tbody');
            tbody.innerHTML = d.recent.map(r =>
                '<tr>' +
                '<td>' + fmtTime(r.at) + '</td>' +
                '<td>' + esc(r.ip) + '</td>' +
                '<td>' + esc(r.user) + '</td>' +
                '<td>' + esc(r.npk) + '</td>' +
                '<td><span class="badge badge-light">' + esc(r.method) + '</span></td>' +
                '<td>' + esc(r.path) + '</td>' +
                '<td>' + esc(r.route) + '</td>' +
                '<td>' + statusBadge(r.status) + '</td>' +
                '<td>' + (r.duration_ms ?? '-') + '</td>' +
                '</tr>'
            ).join('') || '<tr><td colspan="9" class="text-center text-muted">Kosong</td></tr>';

            refreshMap(d);
        } catch (e) { /* abaikan */ }
    }

    async function refreshJobs() {
        try {
            const d = await fetchJson('{{ route("live-access.jobs") }}');
            document.getElementById('jobs-badge').textContent = d.queue_workers_count + ' worker · ' + d.pending_jobs + ' pending · ' + d.failed_jobs + ' failed';
            let html = '';
            if (d.queue_workers.length) {
                html += '<div class="mb-2"><b>Queue workers:</b><ul class="mb-1">' + d.queue_workers.map(w => '<li><code>' + esc(w) + '</code></li>').join('') + '</ul></div>';
            } else {
                html += '<div class="mb-2 text-muted">Tidak ada proses queue:work yang terdeteksi.</div>';
            }
            if (d.scheduled && d.scheduled.length) {
                html += '<div><b>Jadwal (schedule):</b><ul class="mb-0">' + d.scheduled.map(s =>
                    '<li><code>' + esc(s.command) + '</code> <span class="text-muted">(' + esc(s.expression) + ')</span>' +
                    (s.next_due ? ' <span class="text-muted">next: ' + fmtTime(s.next_due) + '</span>' : '') + '</li>'
                ).join('') + '</ul></div>';
            }
            if (d.failed_recent && d.failed_recent.length) {
                html += '<div class="mt-2 text-danger"><b>Failed jobs terbaru:</b><ul class="mb-0">' + d.failed_recent.map(f =>
                    '<li><code>' + esc(f.queue) + '</code> @ ' + esc(f.failed_at) + '</li>'
                ).join('') + '</ul></div>';
            }
            document.getElementById('jobs-panel').innerHTML = html;
        } catch (e) { /* abaikan */ }
    }

    async function refreshServices() {
        try {
            const d = await fetchJson('{{ route("live-access.services") }}');
            document.getElementById('services-badge').textContent = d.count;
            const panel = document.getElementById('services-panel');
            if (!d.services.length) {
                panel.innerHTML = '<span class="text-muted">Tidak ada service yang terdeteksi (atau systemctl tidak tersedia).</span>';
            } else {
                panel.innerHTML = '<ul class="mb-0 pl-3">' + d.services.map(s =>
                    '<li><span class="badge badge-' + (s.active === 'active' ? 'success' : 'secondary') + '">' + esc(s.active) + '</span> <code>' + esc(s.name) + '</code> <span class="text-muted">' + esc(s.description) + '</span></li>'
                ).join('') + '</ul>';
            }
        } catch (e) { /* abaikan */ }
    }

    // ===================== LIVE MAP (ala threat map) =====================
    // Data peta datang dari endpoint data() yang sama (key "map"), jadi tidak
    // menambah request baru. Gambar digambar di <canvas> supaya animasi garis
    // tetap ringan (tanpa library eksternal / tanpa CDN).
    const LAMAP = {
        canvas: document.getElementById('lamap-canvas'),
        ctx: null,
        land: null,
        paths: [],
        sources: [],
        recent: [],
        origin: null,
        counts: null,
        unknown: 0,
        windowMinutes: 5,
        w: 0,
        h: 0,
        dpr: 1,
        started: false,
        // Negara (poligon + nama) untuk klik/zoom + deteksi negara per sumber.
        countries: null,
        countryPaths: [],
        selected: null,
        hover: null,
        drag: null,
        // View peta: scale 1 = seluruh dunia. tx/ty = geser (px layar).
        view: { scale: 1, tx: 0, ty: 0 },
        viewDirty: true
    };
    const LAMAP_WORLD_URL = '{{ asset('vendor/geomap/world-countries.json') }}';
    const LAMAP_LAT_TOP = 78;
    const LAMAP_LAT_BOTTOM = -56;
    const LAMAP_ZOOM_MIN = 1;
    const LAMAP_ZOOM_MAX = 16;
    const LAMAP_ZOOM_CLICK_MAX = 9;  // batas zoom otomatis saat negara diklik

    function lamapProjWidth() {
        // Panel "Sumber teratas" menempel di kanan (lebar 300px). Peta diproyeksikan
        // hanya pada lebar tersisa supaya titik origin (Indonesia, di kanan peta
        // dunia) tidak tertutup panel.
        const panelVisible = window.matchMedia('(min-width: 992px)').matches;
        return Math.max(280, LAMAP.w - (panelVisible ? 320 : 0));
    }

    // Proyeksi dasar (tanpa zoom/geser): lng/lat -> koordinat peta "dunia penuh".
    function lamapProjectBase(lng, lat) {
        const x = ((Number(lng) + 180) / 360) * lamapProjWidth();
        const latClamped = Math.max(LAMAP_LAT_BOTTOM, Math.min(LAMAP_LAT_TOP, Number(lat)));
        const y = ((LAMAP_LAT_TOP - latClamped) / (LAMAP_LAT_TOP - LAMAP_LAT_BOTTOM)) * LAMAP.h;
        return [x, y];
    }

    // Proyeksi + view (zoom/geser) -> koordinat layar. Semua penggambaran memakai
    // fungsi ini, jadi zoom cukup mengubah state LAMAP.view.
    function lamapProject(lng, lat) {
        const p = lamapProjectBase(lng, lat);
        const v = LAMAP.view;
        return [p[0] * v.scale + v.tx, p[1] * v.scale + v.ty];
    }

    // Kebalikan lamapProject: titik layar -> lng/lat (untuk hit-test negara).
    function lamapScreenToGeo(sx, sy) {
        const v = LAMAP.view;
        const bx = (sx - v.tx) / v.scale;
        const by = (sy - v.ty) / v.scale;
        const pw = lamapProjWidth();
        return {
            lng: (bx / pw) * 360 - 180,
            lat: LAMAP_LAT_TOP - (by / LAMAP.h) * (LAMAP_LAT_TOP - LAMAP_LAT_BOTTOM)
        };
    }

    function lamapMarkViewDirty(build) {
        LAMAP.viewDirty = true;
        if (build) lamapRebuildScene();
    }

    // Grid + daratan digambar ulang hanya saat view berubah (dipakai oleh
    // lamapLoop/renderNow sebelum menggambar frame berikutnya).
    function lamapRebuildScene() {
        if (!LAMAP.viewDirty) return;
        const t0 = performance.now();
        lamapBuildPaths();
        const t1 = performance.now();
        lamapBuildBase();
        const t2 = performance.now();
        LAMAP.perf = { pathsMs: +(t1 - t0).toFixed(1), baseMs: +(t2 - t1).toFixed(1), totalMs: +(t2 - t0).toFixed(1) };
        LAMAP.viewDirty = false;
    }

    // Saat menyeret peta: geser latar yang sudah tergambar apa adanya (skala
    // tidak berubah, jadi hasilnya sama persis) dan lakukan pembangunan ulang
    // penuh paling sering 200 ms — seretan tetap mulus walau data 50 m besar.
    function lamapDragRebuild(dx, dy) {
        const now = (window.performance && performance.now) ? performance.now() : Date.now();
        if (!LAMAP.dragRebuiltAt || now - LAMAP.dragRebuiltAt > 200) {
            LAMAP.dragRebuiltAt = now;
            LAMAP.dragShift = null;
            lamapMarkViewDirty(true);
            return;
        }
        const sh = LAMAP.dragShift || [0, 0];
        LAMAP.dragShift = [sh[0] + dx, sh[1] + dy];
    }

    function lamapClampView() {
        const v = LAMAP.view;
        const pw = lamapProjWidth();
        v.scale = Math.max(LAMAP_ZOOM_MIN, Math.min(LAMAP_ZOOM_MAX, v.scale));
        if (v.scale <= 1.001) {
            v.scale = 1;
            v.tx = 0;
            v.ty = 0;
            return;
        }
        // Peta tidak boleh digeser keluar dari area gambar.
        v.tx = Math.max(pw - pw * v.scale, Math.min(0, v.tx));
        v.ty = Math.max(LAMAP.h - LAMAP.h * v.scale, Math.min(0, v.ty));
    }

    // Zoom dengan titik acuan (cursor) tetap di posisi yang sama.
    function lamapZoomAt(pt, factor) {
        const v = LAMAP.view;
        const before = [(pt[0] - v.tx) / v.scale, (pt[1] - v.ty) / v.scale];
        v.scale = Math.max(LAMAP_ZOOM_MIN, Math.min(LAMAP_ZOOM_MAX, v.scale * factor));
        v.tx = pt[0] - before[0] * v.scale;
        v.ty = pt[1] - before[1] * v.scale;
        lamapClampView();
        lamapMarkViewDirty(true);
    }

    // Bbox fokus: kalau negara menyeberang antimeridian (Rusia, Fiji, AS),
    // pakai poligon terbesar supaya zoom tidak "melompat" ke seluruh dunia.
    function lamapFocusBbox(feature) {
        const bb = feature.properties.bbox || [feature.properties.bbox];
        const box = Array.isArray(bb) ? bb : [bb];
        if (box[2] - box[0] <= 180) return box;
        let best = null;
        let bestArea = -1;
        (feature.geometry.coordinates || []).forEach(rings => {
            const outer = rings[0] || [];
            let minX = 180, minY = 90, maxX = -180, maxY = -90;
            outer.forEach(p => {
                minX = Math.min(minX, p[0]); maxX = Math.max(maxX, p[0]);
                minY = Math.min(minY, p[1]); maxY = Math.max(maxY, p[1]);
            });
            const area = (maxX - minX) * (maxY - minY);
            if (area > bestArea) { bestArea = area; best = [minX, minY, maxX, maxY]; }
        });
        return best || box;
    }

    function lamapZoomToFeature(feature) {
        const box = lamapFocusBbox(feature);
        const pw = lamapProjWidth();
        const tl = lamapProjectBase(box[0], box[3]);
        const br = lamapProjectBase(box[2], box[1]);
        const bw = Math.max(4, Math.abs(br[0] - tl[0]));
        const bh = Math.max(4, Math.abs(br[1] - tl[1]));
        const pad = 1.7; // beri ruang di sekeliling negara
        // Batas 9x: negara kecil (Singapura, Malta) tidak "terbang" ke zoom
        // maksimum sampai konteks sekitarnya hilang — pengguna tetap bisa
        // memperbesar lagi dengan roda mouse sampai 16x.
        const scale = Math.max(LAMAP_ZOOM_MIN, Math.min(LAMAP_ZOOM_CLICK_MAX,
            Math.min(pw / (bw * pad), LAMAP.h / (bh * pad))));
        const cx = (tl[0] + br[0]) / 2;
        const cy = (tl[1] + br[1]) / 2;
        const v = LAMAP.view;
        v.scale = scale;
        v.tx = pw / 2 - cx * scale;
        v.ty = LAMAP.h / 2 - cy * scale;
        lamapClampView();
        lamapMarkViewDirty(true);
    }

    function lamapResetView() {
        const v = LAMAP.view;
        lamapLog('resetView (dari scale ' + v.scale.toFixed(2) + ')');
        v.scale = 1;
        v.tx = 0;
        v.ty = 0;
        lamapMarkViewDirty(true);
    }

    // Titik di tepi peta jangan terpotong (termasuk cincin denyutnya):
    // tahan minimal 14px dari tepi. (Dipakai saat peta belum di-zoom.)
    function lamapClampPt(pt) {
        const maxX = lamapProjWidth() - 14;
        return [
            Math.max(14, Math.min(maxX, pt[0])),
            Math.max(14, Math.min(LAMAP.h - 14, pt[1]))
        ];
    }

    // Titik yang berada di luar area gambar (setelah zoom) tidak digambar —
    // kalau dijepit ke tepi, sumber dari negara lain akan tampak di pinggir peta.
    function lamapInView(pt) {
        return pt[0] >= -4 && pt[0] <= lamapProjWidth() + 4 && pt[1] >= -4 && pt[1] <= LAMAP.h + 4;
    }

    // ---- Deteksi negara (point-in-polygon di atas poligon Natural Earth) ----
    function lamapPointInRing(pt, ring) {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const xi = ring[i][0], yi = ring[i][1];
            const xj = ring[j][0], yj = ring[j][1];
            if (((yi > pt[1]) !== (yj > pt[1])) &&
                (pt[0] < (xj - xi) * (pt[1] - yi) / (yj - yi) + xi)) {
                inside = !inside;
            }
        }
        return inside;
    }

    function lamapCountryAt(lng, lat) {
        const feats = (LAMAP.countries && LAMAP.countries.features) || [];
        for (let i = 0; i < feats.length; i++) {
            const f = feats[i];
            const bb = f.properties.bbox || [];
            if (bb.length === 4 && (lng < bb[0] || lng > bb[2] || lat < bb[1] || lat > bb[3])) continue;
            const polys = (f.geometry && f.geometry.coordinates) || [];
            for (let p = 0; p < polys.length; p++) {
                const rings = polys[p];
                if (!rings.length || !lamapPointInRing([lng, lat], rings[0])) continue;
                let inHole = false;
                for (let r = 1; r < rings.length; r++) {
                    if (lamapPointInRing([lng, lat], rings[r])) { inHole = true; break; }
                }
                if (!inHole) return f;
            }
        }
        return null;
    }

    // Negara terdekat bila titik persis di garis pantai / sedikit di luar
    // poligon (peta 50 m menyederhanakan garis pantai sampai beberapa km, jadi
    // kota pantai seperti Surabaya bisa jatuh di luar poligon).
    function lamapSegDist2(px, py, ax, ay, bx, by) {
        const kx = Math.cos(py * Math.PI / 180);
        const x0 = (px - ax) * kx, y0 = py - ay;
        const x1 = (bx - ax) * kx, y1 = by - ay;
        const len2 = x1 * x1 + y1 * y1;
        let t = len2 > 0 ? (x0 * x1 + y0 * y1) / len2 : 0;
        t = Math.max(0, Math.min(1, t));
        const dx = x0 - t * x1, dy = y0 - t * y1;
        return dx * dx + dy * dy;
    }

    function lamapNearestCountry(lng, lat, maxDeg) {
        const limit = maxDeg * maxDeg;
        const feats = (LAMAP.countries && LAMAP.countries.features) || [];
        let best = null;
        let bestD = limit;
        for (let i = 0; i < feats.length; i++) {
            const f = feats[i];
            const b = f.properties.bbox;
            if (!b || b.length !== 4) continue;
            if (lng < b[0] - maxDeg || lng > b[2] + maxDeg || lat < b[1] - maxDeg || lat > b[3] + maxDeg) continue;
            const polys = (f.geometry && f.geometry.coordinates) || [];
            for (let p = 0; p < polys.length; p++) {
                const rings = polys[p];
                for (let r = 0; r < rings.length; r++) {
                    const ring = rings[r];
                    for (let k = 0, j = ring.length - 1; k < ring.length; j = k++) {
                        const d = lamapSegDist2(lng, lat, ring[j][0], ring[j][1], ring[k][0], ring[k][1]);
                        if (d < bestD) { bestD = d; best = f; }
                    }
                }
            }
        }
        return best;
    }

    function lamapCountryName(feature) {
        if (!feature) return '';
        const p = feature.properties || {};
        return p.name || p.iso || 'Wilayah';
    }

    // Negara untuk satu sumber: koordinat GeoIP kalau ada, kalau tidak (LAN,
    // loopback) pakai lokasi origin — server memang berada di negara itu.
    function lamapSourceCountry(src) {
        let lat = null;
        let lng = null;
        if (src.scope === 'public' && src.lat !== null && src.lng !== null) {
            lat = Number(src.lat); lng = Number(src.lng);
        } else if (LAMAP.origin) {
            lat = Number(LAMAP.origin.lat); lng = Number(LAMAP.origin.lng);
        }
        if (lat === null || lng === null || Number.isNaN(lat) || Number.isNaN(lng)) return null;
        // Toleransi 0,15 derajat (~17 km) untuk titik di garis pantai yang
        // tersederhanakan oleh peta 50 m.
        return lamapCountryAt(lng, lat) || lamapNearestCountry(lng, lat, 0.15);
    }


    function lamapBuildPaths() {
        LAMAP.paths = [];
        LAMAP.countryPaths = [];
        if (!LAMAP.land || !LAMAP.land.features) return;
        const feats = LAMAP.land.features;
        const pw = lamapProjWidth();
        const v = LAMAP.view;
        const s = v.scale, tx = v.tx, ty = v.ty;
        const kx = pw / 360;   // px per derajat bujur (proyeksi dasar)
        const ky = LAMAP.h / (LAMAP_LAT_TOP - LAMAP_LAT_BOTTOM);
        const latTop = LAMAP_LAT_TOP, latBot = LAMAP_LAT_BOTTOM;
        // Wilayah geografis yang terlihat: poligon di luar ini dilewati sama
        // sekali (file 50 m berisi ~100 ribu titik; tanpa ini setiap zoom/geser
        // harus memproyeksikan semuanya).
        const tl = lamapScreenToGeo(0, 0);
        const br = lamapScreenToGeo(pw, LAMAP.h);
        const minLng = tl.lng, maxLng = br.lng, maxLat = tl.lat, minLat = br.lat;
        const MIN_DIST = 2;    // px: titik lebih rapat dari ini tidak menambah bentuk
        let points = 0, drawn = 0, polys = 0, drawnPolys = 0;

        for (let fi = 0; fi < feats.length; fi++) {
            const feature = feats[fi];
            const geomPolys = (feature.geometry && feature.geometry.coordinates) || [];
            const polyBox = feature.properties.polyBbox || [];
            const path = new Path2D();
            for (let pi = 0; pi < geomPolys.length; pi++) {
                polys++;
                const b = polyBox[pi];
                if (b && (b[2] < minLng || b[0] > maxLng || b[3] < minLat || b[1] > maxLat)) continue;
                drawnPolys++;
                const rings = geomPolys[pi];
                for (let ri = 0; ri < rings.length; ri++) {
                    const ring = rings[ri];
                    const n = ring.length;
                    points += n;
                    let lastX = 0, lastY = 0, first = true;
                    for (let i = 0; i < n; i++) {
                        const pt = ring[i];
                        let latc = pt[1];
                        if (latc > latTop) latc = latTop;
                        else if (latc < latBot) latc = latBot;
                        const bx = (pt[0] + 180) * kx * s + tx;
                        const by = (latTop - latc) * ky * s + ty;
                        if (i > 0 && Math.abs(bx - lastX) + Math.abs(by - lastY) < MIN_DIST) continue;
                        if (first) { path.moveTo(bx, by); first = false; }
                        else path.lineTo(bx, by);
                        lastX = bx;
                        lastY = by;
                        drawn++;
                    }
                    path.closePath();
                }
            }
            // Path tetap didorong untuk semua fitur supaya indeksnya sejajar
            // dengan features (dipakai saat menyorot negara terpilih).
            LAMAP.paths.push(path);
            LAMAP.countryPaths.push(path);
        }
        LAMAP.pathStats = { points: points, drawn: drawn, polys: polys, drawnPolys: drawnPolys, minDist: MIN_DIST };
    }

    function lamapLanRadius() {
        return Math.max(58, Math.min(150, Math.min(LAMAP.w, LAMAP.h) * 0.17));
    }

    function lamapResize() {
        const canvas = LAMAP.canvas;
        if (!canvas) return;
        const rect = canvas.getBoundingClientRect();
        LAMAP.dpr = Math.min(2, window.devicePixelRatio || 1);
        LAMAP.w = Math.max(280, Math.round(rect.width));
        LAMAP.h = Math.max(240, Math.round(rect.height));
        canvas.width = Math.round(LAMAP.w * LAMAP.dpr);
        canvas.height = Math.round(LAMAP.h * LAMAP.dpr);
        LAMAP.ctx = canvas.getContext('2d');
        if (LAMAP.ctx) {
            LAMAP.ctx.setTransform(LAMAP.dpr, 0, 0, LAMAP.dpr, 0, 0);
        }
        lamapClampView();
        lamapLog('resize ' + LAMAP.w + 'x' + LAMAP.h + ' dpr=' + LAMAP.dpr + ' scale=' + LAMAP.view.scale.toFixed(2));
        LAMAP.viewDirty = true;
        lamapRebuildScene();
    }

    // Grid + daratan digambar SEKALI ke canvas offscreen, lalu tiap frame hanya
    // drawImage. Tanpa ini, fill/stroke 124 poligon setiap frame membuat
    // perangkat lemah (HP/tablet) tersendat. Digambar ulang hanya ketika view
    // (zoom/geser) atau negara terpilih berubah.
    function lamapBuildBase() {
        const base = document.createElement('canvas');
        base.width = Math.round(LAMAP.w * LAMAP.dpr);
        base.height = Math.round(LAMAP.h * LAMAP.dpr);
        const bctx = base.getContext('2d');
        if (!bctx) return;
        bctx.setTransform(LAMAP.dpr, 0, 0, LAMAP.dpr, 0, 0);

        bctx.lineWidth = 1;
        bctx.strokeStyle = 'rgba(56,189,248,0.07)';
        for (let lng = -180; lng <= 180; lng += 30) {
            const x = lamapProject(lng, 0)[0];
            bctx.beginPath(); bctx.moveTo(x, 0); bctx.lineTo(x, LAMAP.h); bctx.stroke();
        }
        for (let lat = LAMAP_LAT_BOTTOM + 4; lat < LAMAP_LAT_TOP; lat += 20) {
            const y = lamapProject(0, lat)[1];
            bctx.beginPath(); bctx.moveTo(0, y); bctx.lineTo(LAMAP.w, y); bctx.stroke();
        }

        if (LAMAP.paths.length) {
            bctx.fillStyle = 'rgba(13,42,63,0.95)';
            bctx.strokeStyle = 'rgba(56,189,248,0.30)';
            bctx.lineWidth = 1;
            const paths = LAMAP.paths;
            for (let i = 0; i < paths.length; i++) { bctx.fill(paths[i]); bctx.stroke(paths[i]); }
        }
        LAMAP.base = base;
    }

    // Path satu negara untuk sorotan (hover/terpilih). Dibangun sesuai view
    // sekarang dan di-cache, sehingga menyorot negara TIDAK memicu pembangunan
    // ulang latar (yang memakan ~200 ms dengan data 50 m).
    function lamapFeaturePath(feature) {
        const v = LAMAP.view;
        const key = (feature.properties.name || '?') + '|' + v.scale + '|' + v.tx + '|' + v.ty;
        if (LAMAP.hlKey === key && LAMAP.hlPath) return LAMAP.hlPath;
        const pw = lamapProjWidth();
        const kx = pw / 360;
        const ky = LAMAP.h / (LAMAP_LAT_TOP - LAMAP_LAT_BOTTOM);
        const path = new Path2D();
        const polys = (feature.geometry && feature.geometry.coordinates) || [];
        for (let pi = 0; pi < polys.length; pi++) {
            const rings = polys[pi];
            for (let ri = 0; ri < rings.length; ri++) {
                const ring = rings[ri];
                for (let i = 0; i < ring.length; i++) {
                    const pt = ring[i];
                    const bx = (pt[0] + 180) * kx * v.scale + v.tx;
                    const by = (LAMAP_LAT_TOP - pt[1]) * ky * v.scale + v.ty;
                    if (i === 0) path.moveTo(bx, by);
                    else path.lineTo(bx, by);
                }
                path.closePath();
            }
        }
        LAMAP.hlKey = key;
        LAMAP.hlPath = path;
        return path;
    }

    // Sprite cahaya partikel (pengganti ctx.shadowBlur yang mahal).
    function lamapGlow(color) {
        if (!LAMAP.glows) LAMAP.glows = {};
        if (LAMAP.glows[color]) return LAMAP.glows[color];
        const size = 22;
        const c = document.createElement('canvas');
        c.width = size; c.height = size;
        const g = c.getContext('2d');
        const grad = g.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
        grad.addColorStop(0, color);
        grad.addColorStop(0.35, color);
        grad.addColorStop(1, 'rgba(0,0,0,0)');
        g.fillStyle = grad;
        g.beginPath(); g.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2); g.fill();
        LAMAP.glows[color] = c;
        return c;
    }

    // Negara setiap sumber (dipakai modal detail per negara). Dipanggil ulang
    // setelah data negara termuat, karena refreshMap bisa lebih dulu jalan.
    function lamapTagSourceCountries() {
        (LAMAP.sources || []).forEach(s => { s._country = lamapSourceCountry(s); });
    }

    async function lamapLoadWorld() {
        if (LAMAP.land) return;
        try {
            const res = await fetch(LAMAP_WORLD_URL);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            LAMAP.land = data;        // 242 negara (nama + ISO + benua + bbox poligon)
            LAMAP.countries = data;   // dipakai untuk hit-test klik/zoom
            lamapMarkViewDirty(true);
            lamapTagSourceCountries();
        } catch (e) {
            LAMAP.land = null; // peta tetap jalan: hanya tanpa garis daratan
            LAMAP.countries = null;
        }
    }

    function lamapColor(src) {
        if ((src.status_5xx || 0) > 0) return '#ef4444';
        if ((src.status_4xx || 0) > 0) return '#f59e0b';
        if (src.scope === 'lan') return '#22c55e';
        return '#22d3ee';
    }

    function lamapControl(a, b) {
        const mx = (a[0] + b[0]) / 2;
        const my = (a[1] + b[1]) / 2;
        const dx = b[0] - a[0];
        const dy = b[1] - a[1];
        const len = Math.max(1, Math.hypot(dx, dy));
        const offset = Math.min(130, len * 0.32);
        let nx = -dy / len;
        let ny = dx / len;
        if (ny > 0) { nx = -nx; ny = -ny; } // busur melengkung ke atas
        return [mx + nx * offset, my + ny * offset];
    }

    function lamapPointOnQuad(a, c, b, t) {
        const mt = 1 - t;
        return [
            mt * mt * a[0] + 2 * mt * t * c[0] + t * t * b[0],
            mt * mt * a[1] + 2 * mt * t * c[1] + t * t * b[1]
        ];
    }

    function lamapRingPositions(originPt, count) {
        const out = [];
        const radius = lamapLanRadius();
        for (let i = 0; i < count; i++) {
            const angle = (-90 + (360 / Math.max(1, count)) * i) * Math.PI / 180;
            out.push([originPt[0] + Math.cos(angle) * radius, originPt[1] + Math.sin(angle) * radius]);
        }
        return out;
    }

    function lamapDraw(ts) {
        const ctx = LAMAP.ctx;
        if (!ctx) return;
        const t = ts / 1000;
        ctx.clearRect(0, 0, LAMAP.w, LAMAP.h);
        LAMAP.debugPoints = []; // diagnostik: koordinat titik sumber tiap frame

        // Grid + daratan: digambar ulang hanya kalau view/negara terpilih berubah.
        lamapRebuildScene();

        // Latar (grid + daratan) dari offscreen canvas — murah, sekali gambar.
        // Saat pengguna menyeret peta, latar digeser apa adanya (skala tidak
        // berubah, jadi hasilnya sama persis) dan pembangunan ulang penuh
        // dilakukan maksimal tiap 200 ms supaya seretan tetap mulus.
        if (LAMAP.base) {
            const sh = LAMAP.dragShift || [0, 0];
            ctx.drawImage(LAMAP.base, sh[0], sh[1], LAMAP.w, LAMAP.h);
        }

        // Sorotan negara (hover abu-abu, terpilih sian) — digambar di lapisan
        // ini, bukan di latar, supaya hover tidak memicu pembangunan ulang.
        if (LAMAP.hover && LAMAP.hover !== LAMAP.selected) {
            const hp = lamapFeaturePath(LAMAP.hover);
            ctx.fillStyle = 'rgba(148,163,184,0.10)';
            ctx.strokeStyle = 'rgba(148,163,184,0.55)';
            ctx.lineWidth = 1;
            ctx.fill(hp);
            ctx.stroke(hp);
        }
        if (LAMAP.selected) {
            const sp = lamapFeaturePath(LAMAP.selected);
            ctx.fillStyle = 'rgba(34,211,238,0.18)';
            ctx.strokeStyle = 'rgba(34,211,238,0.95)';
            ctx.lineWidth = 1.8;
            ctx.fill(sp);
            ctx.stroke(sp);
        }

        const originPt = LAMAP.origin ? lamapProject(LAMAP.origin.lng, LAMAP.origin.lat) : null;
        const sources = LAMAP.sources || [];
        const lanSources = sources.filter(s => s.scope === 'lan');
        const lanPos = originPt ? lamapRingPositions(originPt, lanSources.length) : [];

        // Cincin jaringan lokal + label
        if (originPt && lanSources.length) {
            const radius = lamapLanRadius();
            ctx.save();
            ctx.setLineDash([4, 7]);
            ctx.strokeStyle = 'rgba(34,197,94,0.35)';
            ctx.beginPath();
            ctx.arc(originPt[0], originPt[1], radius, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();
            ctx.fillStyle = 'rgba(148,163,184,0.8)';
            ctx.font = '10px system-ui, -apple-system, Segoe UI, sans-serif';
            ctx.fillText('jaringan lokal (LAN)', originPt[0] - 52, originPt[1] + radius + 14);
        }

        // Garis akses dari setiap sumber ke origin
        const endpointByIdx = [];
        sources.forEach((src, idx) => {
            if (!originPt) return;
            let from = null;
            if (src.scope === 'public' && src.lat !== null && src.lng !== null) {
                const raw = lamapProject(src.lng, src.lat);
                // Saat dunia penuh: titik tepi dijepit (perilaku lama). Setelah
                // di-zoom: sumber di luar bidang tampilan tidak digambar supaya
                // tidak muncul di pinggir peta seolah-olah dari negara lain.
                from = LAMAP.view.scale <= 1.001 ? lamapClampPt(raw) : (lamapInView(raw) ? raw : null);
            } else if (src.scope === 'lan') {
                const lanIdx = lanSources.indexOf(src);
                from = lanPos[lanIdx] || null;
            } else if (src.scope === 'loopback') {
                from = [originPt[0] + 16, originPt[1] + 16];
            }
            if (!from) return;
            LAMAP.debugPoints[idx] = [Math.round(from[0]), Math.round(from[1])];

            const color = lamapColor(src);
            const ctrl = lamapControl(from, originPt);
            const speed = 0.14 + Math.min(0.55, (src.requests || 1) * 0.03);
            const phase = (t * speed + idx * 0.23) % 1;

            // garis busur
            ctx.save();
            ctx.globalAlpha = src.scope === 'lan' ? 0.5 : 0.36;
            ctx.strokeStyle = color;
            ctx.lineWidth = src.scope === 'public' ? 1.4 : 1.1;
            ctx.beginPath();
            ctx.moveTo(from[0], from[1]);
            ctx.quadraticCurveTo(ctrl[0], ctrl[1], originPt[0], originPt[1]);
            ctx.stroke();
            ctx.restore();

            // titik sumber
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.arc(from[0], from[1], src.scope === 'public' ? 3.2 : 2.6, 0, Math.PI * 2);
            ctx.fill();
            ctx.save();
            ctx.globalAlpha = 0.28;
            ctx.beginPath();
            ctx.arc(from[0], from[1], 3.2 + 6 * (0.5 + 0.5 * Math.sin(t * 2 + idx)), 0, Math.PI * 2);
            ctx.strokeStyle = color;
            ctx.stroke();
            ctx.restore();

            // partikel berjalan di sepanjang busur
            const p = lamapPointOnQuad(from, ctrl, originPt, phase);
            ctx.drawImage(lamapGlow(color), p[0] - 7, p[1] - 7, 14, 14);
            ctx.fillStyle = '#e0f2fe';
            ctx.beginPath();
            ctx.arc(p[0], p[1], 2.1, 0, Math.PI * 2);
            ctx.fill();

            endpointByIdx.push({ color: color, at: phase });
        });

        // Origin: denyut + label
        if (originPt) {
            const pulse = 0.5 + 0.5 * Math.sin(t * 2.4);
            ctx.save();
            ctx.strokeStyle = 'rgba(34,211,238,' + (0.55 - 0.35 * pulse) + ')';
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.arc(originPt[0], originPt[1], 12 + 16 * pulse, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();

            ctx.drawImage(lamapGlow('#22d3ee'), originPt[0] - 11, originPt[1] - 11, 22, 22);
            ctx.fillStyle = '#22d3ee';
            ctx.beginPath();
            ctx.arc(originPt[0], originPt[1], 4.2, 0, Math.PI * 2);
            ctx.fill();

            // kilatan tiap partikel yang mencapai origin
            endpointByIdx.forEach(ep => {
                if (ep.at < 0.94) return;
                ctx.save();
                ctx.globalAlpha = (1 - ep.at) * 2.2;
                ctx.strokeStyle = ep.color;
                ctx.lineWidth = 1.4;
                ctx.beginPath();
                ctx.arc(originPt[0], originPt[1], 6 + (ep.at - 0.94) * 220, 0, Math.PI * 2);
                ctx.stroke();
                ctx.restore();
            });

            const label = LAMAP.origin.label || 'Lokasi server';
            ctx.font = '11px system-ui, -apple-system, Segoe UI, sans-serif';
            const tw = ctx.measureText(label).width;
            let lx = originPt[0] + 14;
            if (lx + tw + 10 > lamapProjWidth()) lx = originPt[0] - tw - 20;
            ctx.fillStyle = 'rgba(4,16,28,0.75)';
            ctx.fillRect(lx - 5, originPt[1] - 18, tw + 10, 17);
            ctx.fillStyle = '#e0f2fe';
            ctx.fillText(label, lx, originPt[1] - 6);
        }

        ctx.fillStyle = 'rgba(148,163,184,0.75)';
        ctx.font = '10px system-ui, -apple-system, Segoe UI, sans-serif';
        ctx.fillText('window ' + LAMAP.windowMinutes + ' menit', 16, LAMAP.h - 26);

        // Indikator wilayah terpilih + level zoom (kanan bawah area peta).
        if (LAMAP.selected || LAMAP.view.scale > 1.001) {
            const label = (LAMAP.selected ? lamapCountryName(LAMAP.selected) + ' · ' : '') +
                'zoom ' + LAMAP.view.scale.toFixed(1) + 'x';
            ctx.font = '600 11px ui-monospace, SFMono-Regular, Menlo, monospace';
            const tw = ctx.measureText(label).width;
            const bx = Math.max(12, lamapProjWidth() - tw - 44);
            const by = LAMAP.h - 34;
            ctx.fillStyle = 'rgba(4,16,28,0.85)';
            ctx.strokeStyle = 'rgba(34,211,238,0.55)';
            ctx.lineWidth = 1;
            ctx.fillRect(bx, by, tw + 18, 20);
            ctx.strokeRect(bx, by, tw + 18, 20);
            ctx.fillStyle = '#a5f3fc';
            ctx.fillText(label, bx + 9, by + 14);
        }
    }

    // ===================== Interaksi peta: zoom, geser, klik negara =====================
    // Zoom: roda mouse / pinch / tombol. Klik negara -> zoom ke negara itu +
    // modal detail. Semua data detail dihitung di klien dari payload yang sudah
    // ada (sources + recent), jadi tidak ada request tambahan.
    function lamapCanvasPos(ev) {
        const r = LAMAP.canvas.getBoundingClientRect();
        return [ev.clientX - r.left, ev.clientY - r.top];
    }

    function lamapTouchPos(touch) {
        const r = LAMAP.canvas.getBoundingClientRect();
        return [touch.clientX - r.left, touch.clientY - r.top];
    }

    function lamapInsideCanvas(p) {
        return p[0] >= 0 && p[0] <= LAMAP.canvas.getBoundingClientRect().width &&
            p[1] >= 0 && p[1] <= LAMAP.canvas.getBoundingClientRect().height;
    }

    function lamapClickAt(p) {
        if (!LAMAP.countries || !(LAMAP.countries.features || []).length) return null;
        const geo = lamapScreenToGeo(p[0], p[1]);
        // Toleransi kecil (~7 km) supaya klik di garis pantai/pulau kecil tetap
        // kena negaranya; klik di laut lepas tetap mengosongkan pilihan.
        const feature = lamapCountryAt(geo.lng, geo.lat) || lamapNearestCountry(geo.lng, geo.lat, 0.06);
        lamapSelectCountry(feature);
        return feature;
    }

    function lamapSelectCountry(feature) {
        LAMAP.selected = feature || null;
        lamapLog('select:' + (feature ? lamapCountryName(feature) : 'null') + ' scale=' + LAMAP.view.scale.toFixed(2));
        if (feature) {
            lamapZoomToFeature(feature);
            lamapOpenCountryModal(feature);
        } else {
            lamapCloseCountryModal();
        }
        lamapMarkViewDirty(true);
    }

    // Catatan kejadian peta (diagnostik; dipakai saat menguji di browser).
    function lamapLog(msg) {
        LAMAP.events = LAMAP.events || [];
        LAMAP.events.push(Math.round((window.performance ? performance.now() : Date.now())) + ' ' + msg);
        if (LAMAP.events.length > 40) LAMAP.events.shift();
    }

    function lamapBindInteraction() {
        const canvas = LAMAP.canvas;
        if (!canvas || LAMAP.bound) return;
        LAMAP.bound = true;

        canvas.addEventListener('wheel', ev => {
            ev.preventDefault();
            const factor = (ev.deltaY < 0 ? 1.24 : 1 / 1.24);
            lamapZoomAt(lamapCanvasPos(ev), factor);
        }, { passive: false });

        canvas.addEventListener('mousedown', ev => {
            if (ev.button !== 0) return;
            const p = lamapCanvasPos(ev);
            LAMAP.drag = { last: p, moved: 0 };
            canvas.classList.add('lamap-grabbing');
            ev.preventDefault();
        });

        window.addEventListener('mousemove', ev => {
            if (LAMAP.drag) {
                const p = lamapCanvasPos(ev);
                const dx = p[0] - LAMAP.drag.last[0];
                const dy = p[1] - LAMAP.drag.last[1];
                LAMAP.drag.last = p;
                LAMAP.drag.moved += Math.abs(dx) + Math.abs(dy);
                LAMAP.view.tx += dx;
                LAMAP.view.ty += dy;
                lamapClampView();
                lamapDragRebuild(dx, dy);
                return;
            }
            // hover: sorot negara di bawah kursor
            if (!LAMAP.countries || !lamapInsideCanvas(lamapCanvasPos(ev))) return;
            const geo = lamapScreenToGeo(lamapCanvasPos(ev)[0], lamapCanvasPos(ev)[1]);
            const feature = lamapCountryAt(geo.lng, geo.lat);
            if (feature !== LAMAP.hover) {
                LAMAP.hover = feature;
                canvas.style.cursor = feature ? 'pointer' : 'grab';
            }
        });

        window.addEventListener('mouseup', ev => {
            if (!LAMAP.drag) return;
            const moved = LAMAP.drag.moved;
            LAMAP.drag = null;
            LAMAP.dragShift = null;              // sinkronkan latar setelah seret
            lamapMarkViewDirty(true);
            canvas.classList.remove('lamap-grabbing');
            const p = lamapCanvasPos(ev);
            if (moved < 5 && lamapInsideCanvas(p)) lamapClickAt(p);
        });

        // Sentuh: 1 jari = geser, 2 jari = pinch zoom, ketuk = klik negara.
        let touch = null;
        const dist = t => Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
        const mid = t => [(t[0].clientX + t[1].clientX) / 2, (t[0].clientY + t[1].clientY) / 2];

        canvas.addEventListener('touchstart', ev => {
            if (ev.touches.length === 1) {
                touch = { kind: 'pan', last: lamapTouchPos(ev.touches[0]), moved: 0 };
            } else if (ev.touches.length === 2) {
                touch = { kind: 'pinch', d: dist(ev.touches), mid: mid(ev.touches) };
            }
        }, { passive: true });

        canvas.addEventListener('touchmove', ev => {
            if (!touch) return;
            ev.preventDefault();
            if (touch.kind === 'pan' && ev.touches.length === 1) {
                const p = lamapTouchPos(ev.touches[0]);
                const dx = p[0] - touch.last[0];
                const dy = p[1] - touch.last[1];
                touch.last = p;
                touch.moved += Math.abs(dx) + Math.abs(dy);
                LAMAP.view.tx += dx;
                LAMAP.view.ty += dy;
                lamapClampView();
                lamapDragRebuild(dx, dy);
            } else if (touch.kind === 'pinch' && ev.touches.length === 2) {
                const d = dist(ev.touches);
                const m = mid(ev.touches);
                const r = canvas.getBoundingClientRect();
                const factor = touch.d > 0 ? d / touch.d : 1;
                touch.d = d;
                touch.moved += 6;
                lamapZoomAt([m[0] - r.left, m[1] - r.top], factor);
            }
        }, { passive: false });

        canvas.addEventListener('touchend', ev => {
            if (!touch) return;
            const wasTap = touch.kind === 'pan' && touch.moved < 8;
            const last = touch.last;
            if (ev.touches.length === 0) touch = null;
            LAMAP.dragShift = null;
            lamapMarkViewDirty(true);
            if (wasTap && ev.changedTouches.length) {
                lamapClickAt(lamapTouchPos(ev.changedTouches[0]) || last);
            }
        });

        const btn = (id, fn) => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('click', ev => { ev.preventDefault(); fn(); });
        };
        btn('lamap-zoom-in', () => lamapZoomAt([lamapProjWidth() / 2, LAMAP.h / 2], 1.4));
        btn('lamap-zoom-out', () => lamapZoomAt([lamapProjWidth() / 2, LAMAP.h / 2], 1 / 1.4));
        btn('lamap-zoom-reset', () => { lamapSelectCountry(null); lamapResetView(); });
        btn('lamapCountryZoom', () => { if (LAMAP.selected) lamapZoomToFeature(LAMAP.selected); });
        btn('lamapCountryReset', () => {
            lamapSelectCountry(null);
            lamapResetView();
            const m = document.getElementById('lamapCountryModal');
            if (m && window.jQuery && jQuery.fn.modal) jQuery(m).modal('hide');
        });
    }

    function lamapStatusChips(list) {
        const req = list.reduce((a, s) => a + (s.requests || 0), 0);
        const e4 = list.reduce((a, s) => a + (s.status_4xx || 0), 0);
        const e5 = list.reduce((a, s) => a + (s.status_5xx || 0), 0);
        const ips = list.length;
        const users = new Set();
        list.forEach(s => (s.users || []).forEach(u => users.add(u)));
        list.forEach(s => (s.npk || []).forEach(u => users.add(u)));
        const chip = (cls, lbl, val) => '<span class="lm-chip ' + cls + '">' + lbl + ' <b>' + val + '</b></span>';
        return '<div class="lm-chips">' +
            chip('c-req', 'request', req) +
            chip('c-ip', 'IP', ips) +
            chip('c-user', 'user', users.size) +
            chip('c-4xx', '4xx', e4) +
            chip('c-5xx', '5xx', e5) +
            '</div>';
    }

    function lamapOpenCountryModal(feature) {
        const modal = document.getElementById('lamapCountryModal');
        const body = document.getElementById('lamapCountryBody');
        if (!modal || !body) return;
        const name = lamapCountryName(feature);
        const props = feature.properties || {};
        const inCountry = (LAMAP.sources || []).filter(s => s._country === feature);
        const ipSet = new Set(inCountry.map(s => s.ip));
        const recent = (LAMAP.recent || []).filter(r => ipSet.has(r.ip)).slice(0, 15);
        const minutes = Math.max(1, LAMAP.windowMinutes || 5);

        const t = document.getElementById('lamapCountryTitle');
        const sub = document.getElementById('lamapCountrySub');
        if (t) t.textContent = name + (props.iso ? ' (' + props.iso + ')' : '');
        if (sub) {
            sub.textContent = (props.continent || 'Wilayah') + ' · ' + inCountry.length + ' sumber · window ' +
                minutes + ' menit';
        }

        let html = lamapStatusChips(inCountry);
        const req = inCountry.reduce((a, s) => a + (s.requests || 0), 0);
        html += '<div class="lm-line">Laju: <b>' + (req / minutes).toFixed(1) + '</b> request/menit</div>';

        if (!inCountry.length) {
            html += '<div class="lm-empty">Tidak ada akses dari wilayah ini pada ' + minutes +
                ' menit terakhir. Akses LAN/loopback dihitung di negara lokasi server' +
                (LAMAP.origin ? ' (' + esc(LAMAP.origin.label || 'server') + ')' : '') + '.</div>';
        } else {
            html += '<div class="lm-sec">Sumber</div>' + inCountry.map(s => {
                const who = (s.users && s.users.length ? s.users.join(', ')
                    : (s.npk && s.npk.length ? s.npk.join(', ') : '-'));
                const route = (s.top_routes && s.top_routes.length) ? 
                    s.top_routes.map(r => esc(r.route) + ' ×' + r.count).join(', ') : esc(s.last_route || '-');
                return '<div class="lm-item">' +
                    '<div class="lm-row"><span class="ip">' + esc(s.ip) + '</span>' +
                    '<span class="lm-badge">' + (s.scope === 'public' ? 'publik' : (s.scope === 'lan' ? 'LAN' : 'server')) + '</span>' +
                    '<span class="lm-at">' + fmtTime(s.last_at) + '</span></div>' +
                    '<div class="lm-row meta">' + esc(s.label || '-') + ' · ' + (s.requests || 0) + ' req · ' + esc(who) + '</div>' +
                    '<div class="lm-row meta">' + route + '</div>' +
                    '<div class="lm-row">' + statusCounts(s) + '</div>' +
                    '</div>';
            }).join('');
            if (recent.length) {
                html += '<div class="lm-sec">Akses terbaru</div><div class="lm-recent">' + recent.map(r =>
                    '<div class="lm-row"><span class="lm-at">' + fmtTime(r.at) + '</span> ' +
                    '<span class="ip">' + esc(r.ip) + '</span> ' +
                    '<span class="status">' + statusBadge(r.status) + '</span>' +
                    '<span class="lm-path">' + esc((r.method || '') + ' ' + (r.path || '')) + '</span></div>'
                ).join('') + '</div>';
            }
        }
        body.innerHTML = html;

        const zoomBtn = document.getElementById('lamapCountryZoom');
        if (zoomBtn) zoomBtn.classList.remove('d-none');

        if (window.jQuery && jQuery.fn.modal) jQuery(modal).modal('show');
        else modal.classList.add('show');
    }

    function lamapCloseCountryModal() {
        const modal = document.getElementById('lamapCountryModal');
        if (!modal) return;
        if (window.jQuery && jQuery.fn.modal) {
            if (jQuery(modal).hasClass('show') || document.querySelector('.modal-backdrop')) {
                jQuery(modal).modal('hide');
            }
        } else {
            modal.classList.remove('show');
        }
    }

    function lamapLoop(ts) {
        // 1) Satu gambar gagal TIDAK boleh mematikan animasi.
        // 2) Tab tersembunyi tidak digambar (hemat CPU), loop tetap terjadwal.
        if (!document.hidden) {
            try {
                lamapDraw(ts || 0);
            } catch (e) {
                if (!LAMAP.drawError) {
                    LAMAP.drawError = String((e && e.message) || e);
                    console.warn('Live Map: gagal menggambar —', e);
                }
            }
        }
        window.requestAnimationFrame(lamapLoop);
    }

    async function initLiveMap() {
        if (!LAMAP.canvas || LAMAP.started) return;
        LAMAP.started = true;
        window.__lamap = LAMAP; // diagnostik: __lamap.drawError / __lamap.sources
        // Diagnostik/uji: gambar & hit-test paksa (loop rAF tidak jalan di tab
        // tersembunyi, jadi uji otomatis butuh jalur ini).
        LAMAP.renderNow = function (ts) {
            try { lamapRebuildScene(); lamapDraw(ts || 0); } catch (e) { LAMAP.drawError = String((e && e.message) || e); }
            return LAMAP.view;
        };
        LAMAP.geoAt = function (x, y) { return lamapScreenToGeo(x, y); };
        LAMAP.projectAt = function (lng, lat) { return lamapProject(lng, lat); };
        LAMAP.countryAtXY = function (x, y) { const f = lamapCountryAt(lamapScreenToGeo(x, y).lng, lamapScreenToGeo(x, y).lat); return f ? lamapCountryName(f) : null; };
        LAMAP.clickAt = function (x, y) { return lamapClickAt([x, y]); };
        lamapResize();
        window.addEventListener('resize', lamapResize);
        lamapBindInteraction();
        await lamapLoadWorld();
        window.requestAnimationFrame(lamapLoop);
    }

    function refreshMap(d) {
        const map = d && d.map ? d.map : null;
        const sources = (map && map.sources) || [];
        LAMAP.sources = sources;
        LAMAP.recent = (d && d.recent) || [];
        LAMAP.origin = (map && map.origin) || LAMAP.origin;
        LAMAP.counts = (map && map.counts) || null;
        LAMAP.unknown = (map && map.unknown_count) || 0;
        LAMAP.windowMinutes = d.window_minutes || LAMAP.windowMinutes;
        lamapTagSourceCountries();
        // Modal detail negara ikut diperbarui saat data refresh (peta live).
        if (LAMAP.selected) lamapOpenCountryModal(LAMAP.selected);

        const totalReq = sources.reduce((sum, s) => sum + (s.requests || 0), 0);
        const errors = sources.reduce((sum, s) => sum + (s.status_4xx || 0) + (s.status_5xx || 0), 0);
        const minutes = Math.max(1, LAMAP.windowMinutes);

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        setText('lamap-reqpm', (totalReq / minutes).toFixed(1));
        setText('lamap-sources', sources.length);
        setText('lamap-err', errors);
        setText('lamap-badge', sources.length + ' sumber' + (LAMAP.unknown ? ' · ' + LAMAP.unknown + ' belum diketahui' : ''));

        const foot = document.getElementById('lamap-foot');
        if (foot && LAMAP.origin) {
            foot.innerHTML = 'Origin: ' + esc(LAMAP.origin.label) +
                ' (' + Number(LAMAP.origin.lat).toFixed(3) + ', ' + Number(LAMAP.origin.lng).toFixed(3) + ')' +
                (LAMAP.unknown ? '<br>' + LAMAP.unknown + ' IP publik belum ada di cache GeoIP (tidak digambar)' : '');
        }

        const list = document.getElementById('lamap-list');
        if (!list) return;
        if (!sources.length) {
            list.innerHTML = '<div class="lamap-item meta">Belum ada akses pada window ini.</div>';
            return;
        }
        const scopeLabel = s => s.scope === 'public' ? 'publik' : (s.scope === 'lan' ? 'LAN' : 'server');
        list.innerHTML = sources.slice(0, 8).map(s => {
            const who = (s.users && s.users.length ? s.users.join(', ') : (s.npk && s.npk.length ? s.npk.join(', ') : '-'));
            const route = (s.top_routes && s.top_routes.length) ? s.top_routes[0].route : (s.last_route || '-');
            return '' +
                '<div class="lamap-item">' +
                    '<div class="line">' +
                        '<span><span class="ip">' + esc(s.ip) + '</span> ' +
                            '<span class="badge badge-' + (s.scope === 'public' ? 'info' : 'success') + '">' + scopeLabel(s) + '</span></span>' +
                        '<span class="meta">' + fmtTime(s.last_at) + '</span>' +
                    '</div>' +
                    '<div class="line meta"><span class="trunc" style="min-width:0">' + esc(s.label || '-') + ' · ' + (s.requests || 0) + ' req · ' + esc(who) + '</span></div>' +
                    '<div class="line"><span class="trunc meta" style="min-width:0">' + esc(route) + '</span>' + statusCounts(s) + '</div>' +
                '</div>';
        }).join('');
    }

    async function refreshAll() {
        await Promise.all([refreshSummary(), refreshData(), refreshJobs(), refreshServices()]);
    }

    initLiveMap();
    refreshAll();
    setInterval(refreshAll, 5000);
})();
</script>
@endpush
