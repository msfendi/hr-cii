@extends('layouts.app')

@section('title', 'Live Access')

@push('styles')
<style>
    /* ===== Live Map (ala threat map) — LIGHT MODE =====
       Palet terang: seluruh peta, HUD, panel kanan, dan modal memakai latar
       putih/terang dengan teks gelap. Variabel di .lamap dipakai bersama supaya
       warna konsisten dan mudah diubah lagi di satu tempat. */
    .lamap {
        --lm-bg: #f6faff;
        --lm-ocean: #eef5fc;
        --lm-line: #dbe4ee;
        --lm-line-soft: #eaf0f7;
        --lm-text: #1e293b;
        --lm-muted: #64748b;
        --lm-accent: #1d3f86;
        --lm-accent-2: #0891b2;
        --lm-dock-w: 420px;
        --lm-dock-h: 520px;
        position: relative;
        background: linear-gradient(180deg, #fbfdff 0%, var(--lm-ocean) 100%);
        border: 1px solid var(--lm-line);
        border-radius: .35rem;
        overflow: hidden;
    }
    #lamap-canvas {
        display: block;
        width: 100%;
        height: 660px;
    }
    /* ===== HUD di pojok kiri-atas peta: statistik ringkas ===== */
    .lamap-panel {
        position: absolute;
        top: 12px;
        left: 16px;
        z-index: 4;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        pointer-events: none;
    }
    .lamap-hud {
        max-width: 100%;
        color: var(--lm-accent);
        font-size: .68rem;
        letter-spacing: .04em;
        text-transform: uppercase;
        pointer-events: auto;
        background: rgba(255, 255, 255, .94);
        border: 1px solid var(--lm-line);
        border-radius: 8px;
        padding: 8px 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .07);
    }
    .lamap-hud .hud-row { display: flex; flex-wrap: wrap; gap: 6px 16px; align-items: baseline; }
    .lamap-hud .hud-num {
        display: block;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--lm-text);
        line-height: 1.15;
    }
    .lamap-hud .hud-lbl { color: var(--lm-muted); font-size: .58rem; }
    .lamap-hud .hud-sub {
        display: flex;
        flex-wrap: wrap;
        gap: 3px 10px;
        align-items: baseline;
        margin-top: 5px;
        padding-top: 5px;
        border-top: 1px dashed var(--lm-line);
        font-size: .62rem;
        letter-spacing: 0;
        text-transform: none;
        color: var(--lm-muted);
    }
    .lamap-hud .hud-hint { color: var(--lm-muted); }
    .lamap-hud .hud-server { color: var(--lm-accent); font-weight: 600; font-size: .72rem; }

    /* ===== Tombol zoom di pojok kanan BAWAH peta (bukan kiri-atas) ===== */
    .lamap-zoom {
        position: absolute;
        bottom: 12px;
        right: 16px;
        display: flex;
        flex-direction: row;
        gap: 6px;
        pointer-events: auto;
        z-index: 4;
    }
    .lamap-zoom button {
        width: 32px;
        height: 32px;
        padding: 0;
        border: 1px solid var(--lm-line);
        border-radius: 8px;
        background: rgba(255, 255, 255, .96);
        color: var(--lm-accent);
        font-size: .8rem;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
    }
    .lamap-zoom button:hover { background: #eaf1fa; color: var(--lm-accent); }

    /* Modal detail wilayah */
    .lamap-modal {
        background: #ffffff;
        border: 1px solid var(--lm-line);
        color: #334155;
    }
    .lamap-modal .modal-header,
    .lamap-modal .modal-footer { border-color: var(--lm-line-soft); }
    .lamap-modal .modal-title { color: var(--lm-text); }
    .lamap-modal .close { color: var(--lm-muted); opacity: .9; text-shadow: none; }
    /* ---- Kerapian modal detail wilayah ---- */
    #lamapCountryModal .modal-dialog { max-width: 820px; }
    #lamapCountryModal .modal-header {
        padding: 14px 18px;
        align-items: flex-start;
        border-bottom: 1px solid var(--lm-line-soft);
    }
    #lamapCountryModal .modal-title { font-size: 1rem; color: var(--lm-accent); font-weight: 600; }
    #lamapCountryModal .modal-header .close { margin: 0; padding: 0 2px; line-height: 1; font-size: 1.5rem; }
    /* Tinggi modal diatur sendiri (bukan modal-dialog-centered): dengan konten
       panjang mekanisme centering Bootstrap mendorong header ke luar layar.
       Di sini dialog diikat 1.5rem dari atas dan kontennya dibatasi tinggi viewport. */
    #lamapCountryModal .lamap-dlg { max-width: 820px; margin: 1.5rem auto; }
    #lamapCountryModal .modal-content {
        max-height: calc(100vh - 3rem);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    #lamapCountryModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        max-height: none;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 16px 18px 18px;
    }
    #lamapCountryModal .modal-footer {
        gap: 8px;
        padding: 12px 18px;
        border-top: 1px solid var(--lm-line-soft);
    }
    #lamapCountryModal .modal-footer .btn { margin: 0; }

    /* Baris metrik: kartu angka seragam, bukan pil-pil campur. */
    .lm-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }
    .lm-stat {
        border: 1px solid var(--lm-line);
        border-radius: 8px;
        background: #f8fafc;
        padding: 8px 10px;
        min-width: 0;
    }
    .lm-stat .lm-stat-v {
        display: block;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--lm-text);
        line-height: 1.2;
    }
    .lm-stat .lm-stat-l {
        display: block;
        font-size: .58rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--lm-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lm-stat.c-req .lm-stat-v { color: var(--lm-accent); }
    .lm-stat.c-ip .lm-stat-v, .lm-stat.c-user .lm-stat-v { color: #0f766e; }
    .lm-stat.c-4xx { background: #fffbeb; border-color: rgba(217, 119, 6, .35); }
    .lm-stat.c-4xx .lm-stat-v { color: #b45309; }
    .lm-stat.c-5xx { background: #fef2f2; border-color: rgba(220, 38, 38, .35); }
    .lm-stat.c-5xx .lm-stat-v { color: #b91c1c; }

    .lm-line {
        font-size: .76rem;
        color: var(--lm-muted);
        background: #f8fafc;
        border-radius: 6px;
        padding: 6px 10px;
        margin-bottom: 5px;
    }
    .lm-line b { color: var(--lm-accent); }
    .lm-sec {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--lm-accent);
        margin: 16px 0 8px;
        border-top: 1px dashed var(--lm-line);
        padding-top: 12px;
    }
    .lm-stats + .lm-line + .lm-sec { margin-top: 12px; }
    /* Daftar akses terbaru di modal: kolom sejajar, path boleh terpotong rapi. */
    #lamapCountryBody .lm-recent .lm-row {
        display: grid;
        grid-template-columns: 60px 120px 50px minmax(0, 1fr);
        gap: 3px 12px;
        align-items: baseline;
        padding: 5px 2px;
        border-bottom: 1px solid var(--lm-line-soft);
        margin: 0;
    }
    #lamapCountryBody .lm-recent .lm-row:last-child { border-bottom: 0; }
    #lamapCountryBody .lm-recent .lm-path {
        word-break: break-all;
        color: var(--lm-text);
        font-size: .74rem;
    }
    #lamapCountryBody .lm-item { padding: 10px 0; }
    #lamapCountryBody .lm-item + .lm-item { border-top: 1px solid var(--lm-line-soft); }
    #lamapCountryBody .lm-row.meta { font-size: .72rem; }
    @media (max-width: 575.98px) {
        .lm-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        #lamapCountryBody .lm-recent .lm-row { grid-template-columns: 54px minmax(0, 1fr) 46px; }
        #lamapCountryBody .lm-recent .lm-row .ip { display: none; }
    }
    .lm-item { padding: 8px 0; border-bottom: 1px dashed var(--lm-line-soft); font-size: .8rem; }
    .lm-item:last-child { border-bottom: 0; }
    .lm-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 10px; }
    .lm-row.meta { color: var(--lm-muted); font-size: .74rem; }
    .lm-row .ip { color: var(--lm-accent); font-weight: 600; }
    .lm-badge {
        border: 1px solid var(--lm-line);
        border-radius: 6px;
        padding: 0 7px;
        font-size: .64rem;
        color: var(--lm-accent-2);
        background: #f8fafc;
    }
    .lm-at { color: var(--lm-muted); font-size: .74rem; margin-left: auto; }
    .lm-empty { font-size: .82rem; color: var(--lm-muted); padding: 8px 0; }
    .lm-recent .lm-row { margin-bottom: 4px; }
    .lm-recent .lm-at { margin-left: 0; min-width: 64px; }
    .lm-path { color: var(--lm-muted); word-break: break-all; }
    .lm-recent-head {
        display: grid;
        grid-template-columns: 60px 120px 50px minmax(0, 1fr);
        gap: 3px 12px;
        align-items: baseline;
        padding: 6px 2px 4px;
        border-bottom: 2px solid var(--lm-line);
        margin-bottom: 4px;
        font-size: .62rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--lm-accent);
        font-weight: 700;
    }
    .lm-recent-head .status-col { text-align: center; }
    @media (max-width: 575.98px) {
        .lm-recent-head { grid-template-columns: 54px minmax(0, 1fr) 46px; }
        .lm-recent-head .ip { display: none; }
    }
    /* ===== Dock HUD: panel data menempel di dalam peta =====
       Akses per IP, Akses per User, Request Terbaru, Jobs & Queue, dan Services
       berjalan dipindah ke sini sebagai tab, jadi seluruh halaman cuma punya satu
       widget besar (peta) dan tidak ada kartu-kartu terpisah di bawahnya. */
    .lamap-dock {
        position: absolute;
        top: 12px;
        right: 12px;
        width: var(--lm-dock-w);
        max-height: calc(100% - 24px);
        display: flex;
        flex-direction: column;
        background: rgba(255, 255, 255, .95);
        border: 1px solid var(--lm-line);
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
        overflow: hidden;
        z-index: 4;
    }
    /* Baris tab bisa digeser mendatar; tombol tutup dipin di luar area scroll
       supaya tetap terjangkau di layar kecil walau tab meluber. */
    .lamap-dock-head {
        display: flex;
        align-items: flex-start;
        border-bottom: 1px solid var(--lm-line);
        flex: 0 0 auto;
    }
    .lamap-tabs {
        display: flex;
        align-items: stretch;
        gap: 2px;
        padding: 6px 6px 0;
        overflow-x: auto;
        flex: 1 1 auto;
        min-width: 0;
    }
    .lamap-tab {
        flex: 0 0 auto;
        border: 1px solid transparent;
        border-bottom: 0;
        background: transparent;
        color: var(--lm-muted);
        font-size: .66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding: 5px 7px;
        border-radius: 6px 6px 0 0;
        cursor: pointer;
        white-space: nowrap;
    }
    .lamap-tab:hover { color: var(--lm-accent); background: #eef4fc; }
    .lamap-tab.is-active {
        color: var(--lm-accent);
        background: #fff;
        border-color: var(--lm-line);
        box-shadow: inset 0 -2px 0 var(--lm-accent-2);
    }
    .lamap-dock-close {
        display: none;
        flex: 0 0 auto;
        align-self: center;
        margin: 0 6px 0 2px;
        border: 1px solid var(--lm-line);
        background: #fff;
        color: var(--lm-muted);
        border-radius: 6px;
        width: 22px;
        height: 22px;
        padding: 0;
        cursor: pointer;
    }
    .lamap-panes { flex: 1 1 auto; min-height: 0; }
    .lamap-pane {
        display: none;
        padding: 8px 10px;
        max-height: var(--lm-dock-h);
        overflow-y: auto;
    }
    .lamap-pane.is-active { display: block; }
    .lamap-pane-title {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--lm-accent);
        margin-bottom: 6px;
    }
    .lm-nodes { display: flex; flex-direction: column; gap: 8px; }
    .lm-nodes .ip-node { min-width: 0 !important; }
    .lm-nodes .ip-node .small { word-break: break-word; }
    .lm-plain { font-size: .72rem; color: #334155; }
    .lm-plain ul { margin-bottom: .35rem; padding-left: 1.1rem; }
    .lm-plain code { font-size: .68rem; color: var(--lm-accent); }
    /* Tombol pembuka dock di layar kecil (dock disembunyikan CSS). */
    .lamap-dock-toggle {
        position: absolute;
        top: 12px;
        right: 12px;
        display: none;
        align-items: center;
        gap: 5px;
        z-index: 5;
        border: 1px solid var(--lm-line);
        border-radius: 8px;
        background: rgba(255, 255, 255, .96);
        color: var(--lm-accent);
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding: 5px 9px;
        cursor: pointer;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
    }
    .lamap-dock-toggle:hover { background: #eaf1fa; }
    .lamap-item {
        padding: 5px 0;
        border-bottom: 1px dashed var(--lm-line-soft);
        font-size: .7rem;
        color: #334155;
    }
    .lamap-item:last-child { border-bottom: 0; }
    .lamap-item .ip { color: var(--lm-accent); font-weight: 600; }
    .lamap-item .meta { color: var(--lm-muted); }
    .lamap-item .line { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .lamap-item .trunc { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    /* Layar lebar: peta makin tinggi, dock makin lebar. */
    @media (min-width: 1400px) {
        #lamap-canvas { height: 780px; }
        .lamap { --lm-dock-w: 460px; --lm-dock-h: 620px; }
    }
    @media (max-width: 991.98px) {
        #lamap-canvas { height: 420px; }
        .lamap { --lm-dock-w: 100%; --lm-dock-h: 300px; }
        /* Dock jadi panel penuh di atas peta; dibuka lewat tombol "Panel". */
        .lamap-dock {
            top: 8px;
            right: 8px;
            left: 8px;
            bottom: 8px;
            width: auto;
            max-height: none;
            display: none;
        }
        /* Saat dipakai sebagai panel penuh, latar dibuat opak supaya peta di
           belakang tidak menembus dan isi panel tetap enak dibaca. */
        .lamap.is-dock-open .lamap-dock { display: flex; background: #fff; }
        .lamap-dock-toggle { display: inline-flex; }
        .lamap.is-dock-open .lamap-dock-toggle { display: none; }
        .lamap-dock-close { display: block; }
        /* Rapatkan tab supaya keenamnya muat di lebar layar kecil. */
        .lamap-tabs { gap: 1px; padding: 6px 4px 0; }
        .lamap-tab { font-size: .56rem; letter-spacing: -.01em; padding: 4px; }
        .lamap-dock-close { margin: 0 5px 0 0; }
        /* Kelompok kontrol tetap di kiri-atas; di layar kecil statistik
           opsional disembunyikan supaya peta tidak tertutup. */
        .lamap-panel { top: 8px; left: 8px; right: 10px; gap: 6px; }
        .lamap-hud { padding: 5px 9px; }
        .lamap-hud .hud-row { gap: 4px 12px; }
        .lamap-hud .hud-num { font-size: .95rem; }
        .lamap-hud .hud-lbl { font-size: .54rem; }
        .lamap-hud .hud-sub { font-size: .58rem; }
        .lamap-panel .hud-opt { display: none; }
        .lamap-panel .hud-hint { display: none; }
        .lamap-zoom button { width: 30px; height: 30px; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-globe-asia mr-2"></i>Live Access</h1>
    </div>

    <div class="row">
        {{-- Live Map (ala threat map) --}}
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-globe mr-1"></i> Live Map Akses</h6>
                    <span class="badge badge-light border" id="lamap-badge" style="color:#1d3f86;">0 sumber</span>
                </div>
                <div class="card-body p-0">
                    <div class="lamap">
                        <canvas id="lamap-canvas" aria-label="Peta akses langsung"></canvas>

                        {{-- Kelompok kiri-atas: HUD statistik + tombol zoom menempel
                             jadi satu, jadi tidak ada kontrol di pojok kanan bawah. --}}
                        <div class="lamap-panel">
                            <div class="lamap-hud">
                                <div class="hud-row">
                                    <div>
                                        <span class="hud-num" id="lamap-reqpm">0</span>
                                        <span class="hud-lbl">request/menit</span>
                                    </div>
                                    <div>
                                        <span class="hud-num" id="sum-requests">0</span>
                                        <span class="hud-lbl">total request</span>
                                    </div>
                                    <div class="hud-opt">
                                        <span class="hud-num" id="sum-ips">0</span>
                                        <span class="hud-lbl">IP unik</span>
                                    </div>
                                    <div>
                                        <span class="hud-num" id="sum-users">0</span>
                                        <span class="hud-lbl">user aktif</span>
                                    </div>
                                    <div>
                                        <span class="hud-num" id="sum-duration">0 ms</span>
                                        <span class="hud-lbl">Rata-rata Durasi</span>
                                    </div>
                                    <div class="hud-opt">
                                        <span class="hud-num" id="lamap-sources">0</span>
                                        <span class="hud-lbl">sumber</span>
                                    </div>
                                    <div class="hud-opt">
                                        <span class="hud-num" id="lamap-err">0</span>
                                        <span class="hud-lbl">error</span>
                                    </div>
                                </div>
                                <div class="hud-sub">
                                    <span class="hud-server" id="server-time">Memuat...</span>
                                    <span class="hud-hint">Klik negara untuk zoom + detail · scroll = zoom · geser peta = pindah</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol zoom di pojok kanan bawah peta -->
                        <div class="lamap-zoom">
                            <button type="button" id="lamap-zoom-in" title="Perbesar"><i class="fas fa-plus"></i></button>
                            <button type="button" id="lamap-zoom-out" title="Perkecil"><i class="fas fa-minus"></i></button>
                            <button type="button" id="lamap-zoom-reset" title="Reset ke seluruh dunia"><i class="fas fa-compress"></i></button>
                        </div>

                        <button type="button" class="lamap-dock-toggle" id="lamap-dock-toggle" title="Buka panel data">
                            <i class="fas fa-list"></i> Panel
                        </button>

                        {{-- Dock HUD: panel data (IP, User, Request, Jobs, Services) --}}
                        <div class="lamap-dock" id="lamap-dock">
                            <div class="lamap-dock-head">
                                <div class="lamap-tabs" role="tablist" aria-label="Panel Live Map">
                                    <button type="button" class="lamap-tab is-active" data-lm-tab="sumber" role="tab" aria-selected="true">Sumber</button>
                                    <button type="button" class="lamap-tab" data-lm-tab="ip" role="tab" aria-selected="false">IP</button>
                                    <button type="button" class="lamap-tab" data-lm-tab="user" role="tab" aria-selected="false">User</button>
                                    <button type="button" class="lamap-tab" data-lm-tab="request" role="tab" aria-selected="false">Request</button>
                                    <button type="button" class="lamap-tab" data-lm-tab="jobs" role="tab" aria-selected="false">Jobs</button>
                                    <button type="button" class="lamap-tab" data-lm-tab="services" role="tab" aria-selected="false">Services</button>
                                </div>
                                <button type="button" class="lamap-dock-close" id="lamap-dock-close" title="Tutup panel"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="lamap-panes">
                                <div class="lamap-pane is-active" data-lm-pane="sumber" role="tabpanel">
                                    <div class="lamap-pane-title"><span>Sumber teratas</span></div>
                                    <div id="lamap-list"><div class="lamap-item meta">Memuat data...</div></div>
                                </div>
                                <div class="lamap-pane" data-lm-pane="ip" role="tabpanel">
                                    <div class="lamap-pane-title"><span>Akses per IP (5 menit)</span><span class="lm-badge" id="ip-count">0 IP</span></div>
                                    <div id="ip-map" class="lm-nodes"><div class="lm-empty">Memuat data...</div></div>
                                </div>
                                <div class="lamap-pane" data-lm-pane="user" role="tabpanel">
                                    <div class="lamap-pane-title"><span>Akses per User (5 menit)</span><span class="lm-badge" id="user-count">0 user</span></div>
                                    <div id="user-map" class="lm-nodes"><div class="lm-empty">Memuat data...</div></div>
                                </div>
                                <div class="lamap-pane" data-lm-pane="request" role="tabpanel">
                                    <div class="lamap-pane-title"><span>Request Terbaru</span><span class="lm-badge" id="recent-count">0 req</span></div>
                                    <div id="recent-list" class="lm-recent"><div class="lm-empty">Memuat data...</div></div>
                                </div>
                                <div class="lamap-pane" data-lm-pane="jobs" role="tabpanel">
                                    <div class="lamap-pane-title"><span>Jobs &amp; Queue</span><span class="lm-badge" id="jobs-badge">0 worker</span></div>
                                    <div id="jobs-panel" class="lm-plain">Memuat...</div>
                                </div>
                                <div class="lamap-pane" data-lm-pane="services" role="tabpanel">
                                    <div class="lamap-pane-title"><span>Services berjalan</span><span class="lm-badge" id="services-badge">0</span></div>
                                    <div id="services-panel" class="lm-plain">Memuat...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail negara/region yang diklik di peta -->
    <div class="modal fade" id="lamapCountryModal" tabindex="-1" role="dialog" aria-labelledby="lamapCountryTitle" aria-hidden="true">
        <div class="modal-dialog lamap-dlg modal-lg" role="document">
            <div class="modal-content lamap-modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title m-0" id="lamapCountryTitle">Wilayah</h5>
                        <div class="text-muted small" id="lamapCountrySub">-</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="lamapCountryBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="lamapCountryZoom">
                        <i class="fas fa-search-plus mr-1"></i>Zoom ke wilayah
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="lamapCountryReset">
                        <i class="fas fa-undo mr-1"></i>Reset peta
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Catatan: Akses per IP, Akses per User, Request Terbaru, Jobs & Queue, dan
         Services berjalan TIDAK lagi berupa kartu terpisah di bawah peta. Seluruh data
         sudah dipindah ke dalam HUD Live Map Akses (dock + tab di dalam peta). --}}
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
                        '<div class="font-weight-bold" style="color:#1d3f86;"><i class="fas fa-circle" style="font-size:8px; color:#16a34a;"></i> ' + esc(ip.ip) + '</div>' +
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
                            '<div class="font-weight-bold" style="color:#1d3f86;"><i class="fas fa-circle" style="font-size:8px; color:#16a34a;"></i> ' + esc(name) + npkTag + '</div>' +
                            '<div class="small text-muted">' + u.requests + ' request · IP: ' + esc(ips) + '</div>' +
                            '<div class="small" style="color:#475569;">' + esc(u.last_path) + '</div>' +
                            (routes ? '<div class="small" style="color:#64748b;">top: ' + routes + '</div>' : '') +
                            '<div class="small">' + statusCounts(u) + ' · ' + fmtTime(u.last_at) + '</div>' +
                        '</div>';
                    }).join('');
                }
            }

            // Request terbaru: daftar ringkas (bukan tabel lebar) supaya muat di dock peta.
            const rlist = document.getElementById('recent-list');
            const recent = d.recent || [];
            const rcount = document.getElementById('recent-count');
            if (rcount) rcount.textContent = recent.length + ' req';
            if (rlist) {
                rlist.innerHTML = recent.length ? recent.map(r =>
                    '<div class="lm-item">' +
                        '<div class="lm-row">' +
                            '<span class="lm-at">' + fmtTime(r.at) + '</span>' +
                            statusBadge(r.status) +
                            '<span class="lm-badge">' + esc(r.method) + '</span>' +
                            '<span class="ip">' + esc(r.ip) + '</span>' +
                            '<span class="text-muted" style="font-size:.66rem;">' + (r.duration_ms ?? '-') + ' ms</span>' +
                        '</div>' +
                        '<div class="lm-row meta lm-path">' + esc(r.path) + '</div>' +
                        '<div class="lm-row meta">' + (esc(r.user) || '(anonim)') +
                            (r.npk ? ' &middot; ' + esc(r.npk) : '') +
                            ' &middot; ' + (esc(r.route) || '-') + '</div>' +
                    '</div>'
                ).join('') : '<div class="lm-empty">Belum ada request.</div>';
            }

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

    // ===================== DOCK HUD: panel data di dalam peta =====================
    // Tab: Sumber / IP / User / Request / Jobs / Services. Semua wadah data
    // (#lamap-list, #ip-map, #user-map, #recent-list, #jobs-panel, #services-panel)
    // ada di dalam dock, jadi fungsi pengisi datanya tidak perlu diubah.
    (function lamapInitDock() {
        const dock = document.getElementById('lamap-dock');
        if (!dock) return;
        const wrap = document.querySelector('.lamap');
        const tabs = Array.prototype.slice.call(dock.querySelectorAll('.lamap-tab'));
        const panes = Array.prototype.slice.call(dock.querySelectorAll('.lamap-pane'));
        const activate = key => {
            tabs.forEach(t => {
                const on = t.getAttribute('data-lm-tab') === key;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panes.forEach(p => p.classList.toggle('is-active', p.getAttribute('data-lm-pane') === key));
        };
        tabs.forEach(tab => tab.addEventListener('click', ev => {
            ev.preventDefault();
            activate(tab.getAttribute('data-lm-tab'));
        }));
        const toggle = document.getElementById('lamap-dock-toggle');
        const close = document.getElementById('lamap-dock-close');
        // Di layar kecil dock disembunyikan CSS; tombol "Panel" yang membukanya.
        if (toggle) toggle.addEventListener('click', ev => { ev.preventDefault(); wrap.classList.add('is-dock-open'); });
        if (close) close.addEventListener('click', ev => { ev.preventDefault(); wrap.classList.remove('is-dock-open'); });

        window.__lamapDock = {
            tabs: () => tabs.map(t => t.getAttribute('data-lm-tab')),
            active: () => {
                const t = dock.querySelector('.lamap-tab.is-active');
                return t ? t.getAttribute('data-lm-tab') : null;
            },
            open: () => wrap.classList.contains('is-dock-open'),
            visible: () => {
                const cs = getComputedStyle(dock);
                return cs.display !== 'none' && cs.visibility !== 'hidden' && dock.getBoundingClientRect().width > 0;
            },
            box: () => { const r = dock.getBoundingClientRect(); return { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) }; },
            paneCount: key => {
                const pane = dock.querySelector('.lamap-pane[data-lm-pane="' + key + '"]');
                if (!pane) return -1;
                return pane.querySelectorAll('.lamap-item, .ip-node, .lm-item, li, tr').length;
            },
            paneText: key => {
                const pane = dock.querySelector('.lamap-pane[data-lm-pane="' + key + '"]');
                return pane ? pane.innerText.replace(/\s+/g, ' ').trim() : '';
            }
        };
    })();

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
        // Peta diproyeksikan full width canvas. Dock HUD adalah overlay absolut
        // di kanan (CSS), bukan pengurangan area proyeksi. Ini memastikan
        // konversi koordinat click (screen <-> geo) konsisten.
        return LAMAP.w;
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
        // Graticule versi terang (abu tipis di atas laut muda).
        bctx.strokeStyle = 'rgba(148,163,184,0.28)';
        for (let lng = -180; lng <= 180; lng += 30) {
            const x = lamapProject(lng, 0)[0];
            bctx.beginPath(); bctx.moveTo(x, 0); bctx.lineTo(x, LAMAP.h); bctx.stroke();
        }
        for (let lat = LAMAP_LAT_BOTTOM + 4; lat < LAMAP_LAT_TOP; lat += 20) {
            const y = lamapProject(0, lat)[1];
            bctx.beginPath(); bctx.moveTo(0, y); bctx.lineTo(LAMAP.w, y); bctx.stroke();
        }

        if (LAMAP.paths.length) {
            // Daratan putih + garis pantai abu-biru (light mode).
            bctx.fillStyle = 'rgba(255,255,255,0.98)';
            bctx.strokeStyle = 'rgba(148,163,184,0.85)';
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
        // Warna titik/busur disesuaikan untuk latar terang (kontras lebih tinggi).
        if ((src.status_5xx || 0) > 0) return '#dc2626';
        if ((src.status_4xx || 0) > 0) return '#d97706';
        if (src.scope === 'lan') return '#16a34a';
        return '#0891b2';
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
            ctx.fillStyle = 'rgba(148,163,184,0.22)';
            ctx.strokeStyle = 'rgba(100,116,139,0.75)';
            ctx.lineWidth = 1;
            ctx.fill(hp);
            ctx.stroke(hp);
        }
        if (LAMAP.selected) {
            const sp = lamapFeaturePath(LAMAP.selected);
            ctx.fillStyle = 'rgba(8,145,178,0.20)';
            ctx.strokeStyle = 'rgba(8,145,178,0.95)';
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
            ctx.strokeStyle = 'rgba(22,163,74,0.45)';
            ctx.beginPath();
            ctx.arc(originPt[0], originPt[1], radius, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();
            ctx.fillStyle = 'rgba(71,85,105,0.9)';
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
            ctx.globalAlpha = src.scope === 'lan' ? 0.7 : 0.55;
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
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.arc(p[0], p[1], 2.1, 0, Math.PI * 2);
            ctx.fill();

            endpointByIdx.push({ color: color, at: phase });
        });

        // Origin: denyut + label
        if (originPt) {
            const pulse = 0.5 + 0.5 * Math.sin(t * 2.4);
            ctx.save();
            ctx.strokeStyle = 'rgba(8,145,178,' + (0.6 - 0.35 * pulse) + ')';
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.arc(originPt[0], originPt[1], 12 + 16 * pulse, 0, Math.PI * 2);
            ctx.stroke();
            ctx.restore();

            ctx.drawImage(lamapGlow('#0891b2'), originPt[0] - 11, originPt[1] - 11, 22, 22);
            ctx.fillStyle = '#0891b2';
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
            ctx.fillStyle = 'rgba(255,255,255,0.94)';
            ctx.fillRect(lx - 5, originPt[1] - 18, tw + 10, 17);
            ctx.fillStyle = '#1e293b';
            ctx.fillText(label, lx, originPt[1] - 6);
        }

        ctx.fillStyle = 'rgba(100,116,139,0.9)';
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
            ctx.fillStyle = 'rgba(255,255,255,0.96)';
            ctx.strokeStyle = 'rgba(29,63,134,0.45)';
            ctx.lineWidth = 1;
            ctx.fillRect(bx, by, tw + 18, 20);
            ctx.strokeRect(bx, by, tw + 18, 20);
            ctx.fillStyle = '#1d3f86';
            ctx.fillText(label, bx + 9, by + 14);
        }
    }

    // ===================== Interaksi peta: zoom, geser, klik negara =====================
    // Zoom: roda mouse / pinch / tombol. Klik negara -> zoom ke negara itu +
    // modal detail. Semua data detail dihitung di klien dari payload yang sudah
    // ada (sources + recent), jadi tidak ada request tambahan.
    function lamapCanvasPos(ev) {
        const r = LAMAP.canvas.getBoundingClientRect();
        const cssX = ev.clientX - r.left;
        const cssY = ev.clientY - r.top;
        // Konversi koordinat CSS ke buffer canvas (LAMAP.w x LAMAP.h)
        // karena canvas bisa di-stretch oleh CSS (width: 100%).
        const scaleX = LAMAP.w / r.width;
        const scaleY = LAMAP.h / r.height;
        return [cssX * scaleX, cssY * scaleY];
    }

    function lamapTouchPos(touch) {
        const r = LAMAP.canvas.getBoundingClientRect();
        const cssX = touch.clientX - r.left;
        const cssY = touch.clientY - r.top;
        const scaleX = LAMAP.w / r.width;
        const scaleY = LAMAP.h / r.height;
        return [cssX * scaleX, cssY * scaleY];
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
        const prev = LAMAP.selected;
        LAMAP.selected = feature || null;
        lamapLog('select:' + (feature ? lamapCountryName(feature) : 'null') + ' scale=' + LAMAP.view.scale.toFixed(2));
        if (feature) {
            // Klik pertama -> zoom ke negara. Klik negara yang sama saat sudah
            // di-zoom tidak menambah zoom lagi (menghindari zoom beruntun).
            if (feature !== prev || LAMAP.view.scale <= 1.001) lamapZoomToFeature(feature);
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
        const chip = (cls, val, lbl) => '<div class="lm-stat ' + cls + '">' +
            '<span class="lm-stat-v">' + val + '</span>' +
            '<span class="lm-stat-l">' + lbl + '</span></div>';
        return '<div class="lm-stats">' +
            chip('c-req', req, 'request') +
            chip('c-ip', ips, 'IP') +
            chip('c-user', users.size, 'user') +
            chip('c-4xx', e4, '4xx') +
            chip('c-5xx', e5, '5xx') +
            '</div>';
    }

    // Isi modal dibangun ulang TANPA menampilkan modal. Dipakai oleh klik user
    // (lamapOpenCountryModal) dan oleh refresh data (refreshMap): saat modal
    // sedang terbuka, HANYA isinya yang disegarkan.
    function lamapRenderCountryModal(feature) {
            const modal = document.getElementById('lamapCountryModal');
            const body = document.getElementById('lamapCountryBody');
            if (!modal || !body) return;
            const name = lamapCountryName(feature);
            const props = feature.properties || {};
            const inCountry = (LAMAP.sources || []).filter(s => s._country === feature);
            const ipSet = new Set(inCountry.map(s => s.ip));
            const recent = (LAMAP.recent || []).filter(r => ipSet.has(r.ip)).slice(0, 20);
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
                html += '<div class="lm-sec">Sumber Akses</div>' + inCountry.map(s => {
                    const whoList = (s.users && s.users.length ? s.users
                        : (s.npk && s.npk.length ? s.npk : []));
                    const whoFull = whoList.join(', ');
                    // Daftar nama panjang bikin baris berdesakan: tampilkan 4 nama
                    // pertama + jumlah sisanya (nama lengkap ada di tooltip).
                    const who = whoList.length
                        ? whoList.slice(0, 4).join(', ') + (whoList.length > 4 ? ' +' + (whoList.length - 4) + ' lainnya' : '')
                        : '-';
                    const route = (s.top_routes && s.top_routes.length) ?
                        s.top_routes.map(r => esc(r.route) + ' ×' + r.count).join(', ') : esc(s.last_route || '-');
                    const scopeLabel = s.scope === 'public' ? 'publik' : (s.scope === 'lan' ? 'LAN' : 'server');
                    const scopeColor = s.scope === 'public' ? '#0891b2' : (s.scope === 'lan' ? '#16a34a' : '#64748b');
                    return '<div class="lm-item">' +
                        '<div class="lm-row"><span class="ip">' + esc(s.ip) + '</span>' +
                        '<span class="lm-badge" style="background:' + scopeColor + '15; border-color:' + scopeColor + '40; color:' + scopeColor + ';">' + scopeLabel + '</span>' +
                        '<span class="lm-at">' + fmtTime(s.last_at) + '</span></div>' +
                        '<div class="lm-row meta">' + esc(s.label || '-') + ' · ' + (s.requests || 0) + ' req · ' +
                            '<span title="' + esc(whoFull) + '">' + esc(who) + '</span></div>' +
                        '<div class="lm-row meta">' + route + '</div>' +
                        '<div class="lm-row">' + statusCounts(s) + '</div>' +
                        '</div>';
                }).join('');
                if (recent.length) {
                    html += '<div class="lm-sec">Akses Terbaru (' + recent.length + ')</div><div class="lm-recent"><div class="lm-recent-head">' +
                        '<span class="lm-at">Waktu</span>' +
                        '<span class="ip">IP</span>' +
                        '<span class="status-col">Status</span>' +
                        '<span class="lm-path">Method · Path</span>' +
                        '</div>' + recent.map(r =>
                        '<div class="lm-row">' +
                        '<span class="lm-at">' + fmtTime(r.at) + '</span> ' +
                        '<span class="ip">' + esc(r.ip) + '</span> ' +
                        '<span class="status">' + statusBadge(r.status) + '</span>' +
                        '<span class="lm-path">' + esc((r.method || '') + ' ' + (r.path || '')) + '</span>' +
                        '</div>'
                    ).join('') + '</div>';
                }
            }
            body.innerHTML = html;

            const zoomBtn = document.getElementById('lamapCountryZoom');
            if (zoomBtn) zoomBtn.classList.remove('d-none');
        }

    // Tampilkan modal detail wilayah (dipicu klik user). Kalau modal SUDAH
    // terbuka, isinya saja yang sudah diperbarui di atas — modal('show') tidak
    // dipanggil lagi supaya modal tidak muncul berulang/berkedip.
    function lamapOpenCountryModal(feature) {
        const modal = document.getElementById('lamapCountryModal');
        if (!modal) return;
        lamapRenderCountryModal(feature);
        if (LAMAP.modalOpen) return;
        if (window.jQuery && jQuery.fn.modal) {
            if (!LAMAP.modalBound) {
                LAMAP.modalBound = true;
                // Status modal diambil dari event Bootstrap supaya tutup lewat
                // tombol X / backdrop / ESC ikut terdeteksi.
                jQuery(modal).on('shown.bs.modal', () => { LAMAP.modalOpen = true; });
                jQuery(modal).on('hidden.bs.modal', () => { LAMAP.modalOpen = false; });
            }
            jQuery(modal).modal('show');
            LAMAP.modalOpen = true;
        } else {
            modal.classList.add('show');
            LAMAP.modalOpen = true;
        }
    }

    function lamapCloseCountryModal() {
        const modal = document.getElementById('lamapCountryModal');
        if (!modal) return;
        LAMAP.modalOpen = false;
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
        LAMAP.geoAt = function (x, y) { const r = LAMAP.canvas.getBoundingClientRect(); const scaleX = LAMAP.w / r.width; const scaleY = LAMAP.h / r.height; return lamapScreenToGeo((x - r.left) * scaleX, (y - r.top) * scaleY); };
        LAMAP.projectAt = function (lng, lat) { return lamapProject(lng, lat); };
        LAMAP.countryAtXY = function (x, y) { const r = LAMAP.canvas.getBoundingClientRect(); const scaleX = LAMAP.w / r.width; const scaleY = LAMAP.h / r.height; const f = lamapCountryAt(lamapScreenToGeo((x - r.left) * scaleX, (y - r.top) * scaleY).lng, lamapScreenToGeo((x - r.left) * scaleX, (y - r.top) * scaleY).lat); return f ? lamapCountryName(f) : null; };
        LAMAP.clickAt = function (x, y) { const r = LAMAP.canvas.getBoundingClientRect(); const scaleX = LAMAP.w / r.width; const scaleY = LAMAP.h / r.height; return lamapClickAt([(x - r.left) * scaleX, (y - r.top) * scaleY]); };
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
        // Modal detail negara: HANYA isinya yang disegarkan, dan hanya kalau
        // modalnya sedang terbuka. Sebelumnya di sini modal dipaksa show() tiap
        // refresh (5 detik) sehingga muncul sendiri walau sudah ditutup user.
        if (LAMAP.selected && LAMAP.modalOpen) lamapRenderCountryModal(LAMAP.selected);

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
