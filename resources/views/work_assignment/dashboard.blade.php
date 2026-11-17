<!DOCTYPE html>
<html lang="id">
@include('layout.header')
@include('work_assignment._styles')

<style>
    /* ── Dashboard ── */
    .dash-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        background: var(--wa-soft);
        border: 1px solid var(--wa-border);
        border-radius: .8rem;
        padding: .95rem 1.1rem;
        margin-bottom: 1.35rem;
    }

    .dash-hero .emp-card {
        background: transparent;
        border: 0;
        padding: 0;
        flex: 1 1 auto;
    }

    .dash-hero .btn-new {
        border-radius: 2rem;
        font-weight: 700;
        font-size: .88rem;
        padding: .62rem 1.5rem;
        background: var(--wa-primary);
        border: 1px solid var(--wa-primary);
        color: #fff;
        box-shadow: 0 .25rem .75rem rgba(78, 115, 223, .35);
        white-space: nowrap;
        opacity: 1;
    }

    .dash-hero .btn-new:hover,
    .dash-hero .btn-new:focus,
    .dash-hero .btn-new:active {
        background: var(--wa-primary-dark);
        border-color: var(--wa-primary-dark);
        color: #fff;
        opacity: 1;
    }

    .dash-hero .btn-new i {
        color: #fff;
    }

    /* ── Mobile ── */
    @media (max-width: 640px) {
        .dash-hero {
            gap: .6rem;
            padding: .7rem .75rem;
            margin-bottom: .9rem;
        }

        .dash-hero .emp-card {
            gap: .5rem;
        }

        .dash-hero .emp-avatar {
            width: 2.1rem;
            height: 2.1rem;
            font-size: .85rem;
        }

        .dash-hero .emp-name {
            font-size: .82rem;
        }

        .dash-hero .emp-npk {
            font-size: .66rem;
        }

        .dash-hero .emp-chip {
            font-size: .62rem;
            padding: .14rem .4rem;
        }

        .dash-hero .btn-new {
            font-size: .76rem;
            padding: .45rem 1rem;
            width: 100%;
        }

        .wa-list {
            gap: .7rem;
        }

        .wa-item {
            border-radius: .55rem;
        }

        .wa-item-head {
            padding: .7rem .75rem;
        }
    }

    /* ── List pengajuan ── */
    .wa-list {
        display: flex;
        flex-direction: column;
        gap: .7rem;
    }

    .wa-item {
        border: 1px solid var(--wa-border);
        border-radius: .75rem;
        background: #fff;
        overflow: hidden;
        transition: box-shadow .15s, border-color .15s;
    }

    .wa-item:hover {
        box-shadow: 0 .3rem 1rem rgba(15, 30, 80, .09);
        border-color: #cdd7ef;
    }

    .wa-item-head {
        display: grid;
        grid-template-columns: 74px 150px minmax(130px, 1fr) 100px 108px 106px auto;
        align-items: center;
        gap: .7rem;
        padding: .8rem .9rem;
    }

    .wa-item-meta {
        display: flex;
        flex-direction: column;
        gap: .05rem;
        min-width: 0;
    }

    .wa-item-id {
        font-weight: 800;
        font-size: .87rem;
        color: var(--wa-primary-dark);
        white-space: nowrap;
    }

    .wa-item-ident {
        display: flex;
        flex-direction: column;
        gap: .05rem;
        flex: 0 0 auto;
        grid-column: 1;
    }

    .wa-item-id-label,
    .wa-item-status .ml {
        font-size: .62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--wa-muted);
    }

    .wa-item-status {
        display: flex;
        flex-direction: column;
        gap: .2rem;
        align-items: flex-start;
        flex: 0 0 auto;
        grid-column: 6;
        justify-self: end;
    }

    .wa-item-meta {
        display: flex;
        flex-direction: column;
        gap: .2rem;
        min-width: 0;
    }

    .wa-item-meta.meta-c1 { grid-column: 2; }
    .wa-item-meta.meta-c2 { grid-column: 3; }
    .wa-item-meta.meta-c3 { grid-column: 4; }
    .wa-item-meta.meta-c4 { grid-column: 5; }

    .wa-item-meta .ml .short {
        display: none;
    }

    .wa-item-meta .ml {
        font-size: .56rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .01em;
        color: var(--wa-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.15;
    }

    .wa-item-meta .mv {
        font-size: .8rem;
        color: var(--wa-text);
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.25;
    }

    .wa-item-meta .mv i {
        margin-right: .2rem;
        opacity: .65;
    }

    .wa-item-meta .meta-date {
        font-weight: 800;
        font-size: .84rem;
        color: var(--wa-text);
    }

    .wa-item-meta .meta-date .cnt {
        font-weight: 600;
        font-size: .72rem;
        color: var(--wa-muted);
        margin-left: .15rem;
    }

    .wa-item-meta .meta-date i {
        color: var(--wa-primary);
        opacity: 1;
    }

    .wa-badge {
        font-size: .58rem;
        font-weight: 800;
        border-radius: 2rem;
        padding: .2rem .5rem;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: .02em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .25rem;
        min-width: 82px;
    }

    .wa-badge.warning { background: #fff4d6; color: #8a6100; }
    .wa-badge.success { background: #dcfce7; color: #146c43; }
    .wa-badge.danger  { background: #fde2e1; color: #a12622; }
    .wa-badge.secondary { background: #eef1f7; color: #6b7688; }

    /* ── Aksi per baris (QR + Detail) ── */
    .wa-head-actions {
        grid-column: 7;
        justify-self: end;
        margin-left: -.5rem;
        display: inline-flex;
        align-items: center;
        gap: .32rem;
    }

    .wa-toggle {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        border: 1px solid var(--wa-border);
        background: #fff;
        color: var(--wa-primary);
        font-size: .74rem;
        font-weight: 700;
        padding: .28rem .6rem;
        border-radius: 2rem;
        cursor: pointer;
        transition: background .12s, border-color .12s;
    }

    .wa-qr-btn {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        border: 1px solid #cfdcef;
        background: #f3f7fe;
        color: #1b3a6b;
        font-size: .74rem;
        font-weight: 700;
        padding: .28rem .6rem;
        border-radius: 2rem;
        cursor: pointer;
        transition: background .12s, border-color .12s;
    }

    .wa-qr-btn:hover {
        background: #e6effc;
        border-color: #a9c3e6;
    }

    .wa-qr-btn i { font-size: .72rem; }

    /* ── Modal QR ── */
    .wa-qr-modal[hidden] { display: none; }

    .wa-qr-modal {
        position: fixed;
        inset: 0;
        z-index: 1080;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.1rem;
    }

    .wa-qr-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(14, 26, 43, .62);
    }

    .wa-qr-dialog {
        position: relative;
        background: #fff;
        border-radius: .9rem;
        box-shadow: 0 1.2rem 3rem rgba(10, 22, 40, .32);
        width: 100%;
        max-width: 360px;
        padding: 1.2rem 1.2rem 1.1rem;
        text-align: center;
    }

    .wa-qr-x {
        position: absolute;
        top: .45rem;
        right: .6rem;
        border: 0;
        background: transparent;
        font-size: 1.5rem;
        line-height: 1;
        color: #8b98ad;
        cursor: pointer;
        padding: .1rem .3rem;
    }

    .wa-qr-x:hover { color: #22344d; }

    .wa-qr-head h6 {
        font-size: .95rem;
        font-weight: 700;
        color: #1b3a6b;
        margin: 0 0 .2rem;
    }

    .wa-qr-head .wa-qr-sub {
        font-size: .76rem;
        color: #71809a;
        line-height: 1.5;
        margin-bottom: .9rem;
    }

    .wa-qr-img {
        width: 240px;
        height: 240px;
        max-width: 100%;
        margin: 0 auto .7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e3eaf5;
        border-radius: .6rem;
        background: #fff;
        overflow: hidden;
    }

    .wa-qr-img img { width: 100%; height: 100%; display: block; }

    .wa-qr-load { font-size: .8rem; color: #8b98ad; }

    .wa-qr-id {
        font-size: 1rem;
        font-weight: 800;
        color: #22344d;
        letter-spacing: .02em;
    }

    .wa-qr-stage {
        font-size: .76rem;
        color: #71809a;
        margin-top: .25rem;
        line-height: 1.5;
    }

    @media (max-width: 420px) {
        .wa-qr-img { width: 200px; height: 200px; }
        .wa-qr-dialog { padding: 1rem; }
    }

    .wa-toggle:hover {
        background: #f2f6ff;
        border-color: #c6d2e8;
    }

    .wa-toggle i {
        font-size: .62rem;
        transition: transform .18s ease;
    }

    .wa-toggle[aria-expanded="true"] i {
        transform: rotate(180deg);
    }

    .wa-collapse[hidden] {
        display: none;
    }

    /* Layar sempit: meta turun ke baris sendiri, tetap sejajar. */
    @media (max-width: 992px) {
        .wa-item-head {
            grid-template-columns: 1fr 1fr;
            gap: .7rem .9rem;
        }

        .wa-item-ident { grid-column: 1; }
        .wa-item-meta.meta-c1 { grid-column: 2; grid-row: 1; }
        .wa-item-meta.meta-c2 { grid-column: 1; grid-row: 2; }
        .wa-item-meta.meta-c3 { grid-column: 2; grid-row: 2; }
        .wa-item-meta.meta-c4 { grid-column: 1; grid-row: 3; }
        .wa-item-status { grid-column: 2; grid-row: 3; }
        .wa-head-actions { grid-column: 1 / -1; grid-row: 4; justify-self: start; margin-left: 0; }
    }

    /* ── Timeline approval ── */
    .tl {
        padding: .45rem 1rem 1.1rem;
        border-top: 1px solid #f0f3f9;
    }

    .tl-head {
        display: flex;
        align-items: baseline;
        gap: .5rem;
        flex-wrap: wrap;
        padding: .7rem 0 .45rem;
    }

    .tl-head-title {
        font-size: .66rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--wa-muted);
    }

    .tl-head-hint {
        font-size: .7rem;
        color: var(--wa-muted);
        font-style: italic;
    }

    .tl-step {
        display: flex;
        gap: .7rem;
        padding: .65rem 0;
        position: relative;
    }

    .tl-step:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 30px;
        bottom: -4px;
        width: 2px;
        background: #e6eaf3;
    }

    .tl-dot {
        width: 22px;
        height: 22px;
        flex: 0 0 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .62rem;
        font-weight: 800;
        color: #fff;
        background: #cbd3e1;
        z-index: 1;
    }

    .tl-dot.approved { background: #16a34a; }
    .tl-dot.rejected { background: #dc2626; }
    .tl-dot.waiting { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, .18); }
    .tl-dot.upcoming { background: #d5dbe7; }

    .tl-body {
        flex: 1 1 auto;
        min-width: 0;
        padding-top: .05rem;
    }

    .tl-title {
        font-size: .82rem;
        font-weight: 800;
        color: var(--wa-text);
        line-height: 1.25;
    }

    .tl-title .nm {
        font-weight: 800;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .tl-title .role {
        font-size: .62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--wa-primary);
        margin-right: .3rem;
    }

    .tl-sub {
        font-size: .71rem;
        color: var(--wa-muted);
        margin-top: .1rem;
    }

    .tl-sub .jab {
        font-style: italic;
    }

    .tl-status {
        font-size: .67rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .03em;
        white-space: nowrap;
    }

    .tl-status.approved { color: #16a34a; }
    .tl-status.rejected { color: #dc2626; }
    .tl-status.waiting { color: #d97706; }
    .tl-status.upcoming { color: #9aa5b8; }
    .tl-status.skipped { color: #9aa5b8; }

    .tl-reason {
        margin-top: .3rem;
        font-size: .72rem;
        background: #fdeceb;
        border-left: 3px solid #dc2626;
        border-radius: .25rem;
        padding: .3rem .5rem;
        color: #93211c;
    }

    /* ── Empty state ── */
    .wa-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        color: var(--wa-muted);
    }

    .wa-empty .ico {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--wa-soft);
        border: 1px solid var(--wa-border);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto .8rem;
        font-size: 1.4rem;
        color: var(--wa-primary);
    }

    .wa-empty .t {
        font-weight: 800;
        color: var(--wa-text);
        font-size: .92rem;
        margin-bottom: .2rem;
    }

    .wa-empty .d {
        font-size: .8rem;
    }

    /* ── Detail row ── */
    .wa-detail {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: .8rem 1.3rem;
        padding: .85rem 1rem;
        background: #fbfcfe;
        border-top: 1px solid #f0f3f9;
    }

    .wa-detail .k {
        font-size: .66rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--wa-muted);
        font-weight: 800;
    }

    .wa-detail .v {
        font-size: .82rem;
        color: var(--wa-text);
        font-weight: 600;
        margin-top: .1rem;
    }

    /* Mobile: dua kolom, teks boleh wrap. */
    @media (max-width: 640px) {
        .wa-item-head {
            position: relative;
            grid-template-columns: 1fr 1fr auto;
            gap: .65rem .8rem;
            align-items: start;
            padding: .95rem .9rem;
        }

        .wa-item-ident { grid-column: 1; grid-row: 1; }
        .wa-item-meta.meta-c1 { grid-column: 2; grid-row: 1; }
        .wa-item-meta.meta-c2 { grid-column: 1 / -1; grid-row: 2; }
        .wa-item-meta.meta-c3 { grid-column: 1; grid-row: 3; }
        .wa-item-meta.meta-c4 { grid-column: 2; grid-row: 3; }
        .wa-item-status { grid-column: 3; grid-row: 3; justify-self: start; }

        /* Tombol di pojok kanan atas baris 1, kolom 3 */
        .wa-head-actions {
            grid-column: 3;
            grid-row: 1;
            justify-self: end;
            align-self: start;
            margin-left: 0;
            gap: .24rem;
        }

        .wa-toggle,
        .wa-qr-btn {
            padding: .2rem .42rem;
            margin-left: 0;
        }

        .wa-item-meta .mv {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        /* Badge status & tombol lebih kecil */
        .wa-item-meta {
            gap: .24rem;
        }

        .wa-item-meta .ml {
            font-size: .56rem;
        }

        .wa-item-meta .ml .long {
            display: none;
        }

        .wa-item-meta .ml .short {
            display: inline;
        }

        .wa-item-meta .mv,
        .wa-item-meta .meta-date {
            font-size: .7rem;
        }

        .wa-item-status {
            gap: .24rem;
        }

        .wa-badge {
            font-size: .52rem;
            min-width: 70px;
            padding: .14rem .38rem;
            letter-spacing: .01em;
        }

        .wa-toggle {
            font-size: .68rem;
            padding: .24rem .5rem;
            margin-left: 0;
        }

        /* Tombol jadi ikon ringkas supaya tak makan kolom */
        .wa-toggle .tx,
        .wa-qr-btn .tx {
            display: none;
        }

        .wa-toggle i,
        .wa-qr-btn i {
            font-size: .85rem;
        }

        .wa-item-id {
            font-size: .8rem;
        }

        .wa-item-id-label {
            font-size: .58rem;
        }

        /* Detail di dalam accordion lebih rapat */
        .wa-detail {
            gap: .7rem 1rem;
            padding: 1rem .9rem;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        }

        .wa-detail .k {
            font-size: .56rem;
            letter-spacing: .02em;
            line-height: 1.2;
        }

        .wa-detail .v {
            font-size: .7rem;
            line-height: 1.3;
            margin-top: .08rem;
        }

        .tl {
            padding: .55rem .9rem 1rem;
        }

        .tl-head {
            padding: .7rem 0 .45rem;
            gap: .4rem;
        }

        .tl-head-title { font-size: .56rem; }
        .tl-head-hint { font-size: .6rem; }

        .tl-step { gap: .55rem; padding: .65rem 0; }

        /* Nama approver dapat baris sendiri di mobile */
        .tl-body {
            display: flex;
            flex-direction: column;
            gap: .12rem;
            padding-top: 0;
        }

        .tl-title {
            display: flex;
            flex-direction: column;
            gap: .05rem;
            font-size: .72rem;
            line-height: 1.25;
        }

        .tl-title .nm {
            font-size: .74rem;
        }

        .tl-title .role {
            font-size: .52rem;
            margin-right: 0;
        }

        .tl-sub {
            font-size: .62rem;
            margin-top: 0;
        }

        .tl-dot {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            font-size: .54rem;
        }

        .tl-title { font-size: .72rem; }
        .tl-title .role { font-size: .54rem; }
        .tl-sub { font-size: .62rem; margin-top: .05rem; }
        .tl-status { font-size: .58rem; }
        .tl-reason { font-size: .62rem; margin-top: .2rem; padding: .25rem .4rem; }
    }

</style>

<body>

<div class="wa-shell">

    {{-- ── Header ── --}}
    <div class="wa-head">
        <div class="brand">
            <img src="{{ asset('img/chutex.svg') }}" alt="Chutex">
            <div>
                <div class="t1">PT. Chutex International Indonesia</div>
                <div class="t2">Work Assignment Request</div>
            </div>
        </div>
        <div class="wa-head-actions">
            <div class="wa-head-user">
                <b>{{ $employee['nama'] ?? '-' }}</b><br>
                <span>NPK {{ $employee['npk'] ?? '-' }}</span>
            </div>
            <button type="button" id="btn-refresh" class="btn btn-outline-light" title="Refresh status approval">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
            <a href="{{ route('work-assignment.logout') }}" class="btn btn-outline-light">
                <i class="fas fa-sign-out-alt mr-1"></i> Keluar
            </a>
        </div>
    </div>

    @include('sweetalert::alert')

    <div class="wa-card">
        <div class="wa-card-head">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4>Pengajuan Dinas Luar Saya</h4>
                    <div class="sub">Pantau status persetujuan setiap pengajuan Anda.</div>
                </div>
                </div>
        </div>

        <div class="wa-card-body">

            {{-- ── Hero: data diri + tombol pengajuan ── --}}
            <div class="dash-hero">
                <div class="emp-card">
                    <div class="emp-avatar"><?php echo strtoupper(mb_substr($employee['nama'] ?? '?', 0, 1)); ?></div>
                    <div class="emp-id">
                        <div class="emp-name">{{ $employee['nama'] ?? '-' }}</div>
                        <div class="emp-npk">NPK {{ $employee['npk'] ?? '-' }}</div>
                    </div>
                    <div class="emp-chips">
                        <span class="emp-chip"><b>Jabatan/Dept:</b> {{ $employee['jabatan'] ?? '-' }}</span>
                        <span class="emp-chip"><b>Bagian:</b> {{ $employee['bagian'] ?? '-' }}</span>
                        <span class="emp-chip"><b>Section:</b> {{ $employee['section'] ?? '-' }}</span>
                    </div>
                </div>

                <a href="{{ route('work-assignment.form') }}" class="btn btn-new">
                    <i class="fas fa-plus-circle mr-1"></i> Buat Pengajuan
                </a>
            </div>

            {{-- ── Daftar pengajuan ── --}}
            <div class="wa-section mb-0">
                <div class="wa-section-title">
                    <span class="step"><i class="fas fa-list"></i></span>
                    Riwayat Pengajuan
                    <span class="badge badge-secondary" style="font-size:.6rem">{{ $requests->count() }}</span>
                </div>

                @if ($requests->isEmpty())
                    <div class="wa-empty">
                        <div class="ico"><i class="fas fa-file-alt"></i></div>
                        <div class="t">Belum ada pengajuan</div>
                        <div class="d">Klik tombol <b>Buat Pengajuan</b> di atas untuk mengajukan surat ijin dinas luar.</div>
                    </div>
                @else
                    <div class="wa-list">
                        @foreach ($requests as $req)
                            @php
                                $progress = $req->progress();
                                $timeline = $req->timeline();
                            @endphp

                            <div class="wa-item" data-wa-item="{{ $req->id }}">
                                <div class="wa-item-head">
                                    <div class="wa-item-ident">
                                        <span class="wa-item-id">#{{ $req->id }}</span>
                                        <span class="wa-item-id-label">No. Pengajuan</span>
                                    </div>

                                    <div class="wa-item-meta meta-c1">
                                        <span class="ml">Tanggal Dinas</span>
                                        <span class="mv meta-date" title="{{ $req->start_date ? $req->start_date->format('d-m-Y') : '-' }}{{ $req->total_days > 1 ? ',' . ($req->end_date ? $req->end_date->format('d-m-Y') : '-') . ' (' . $req->total_days . ' hari)' : '' }}">
                                            <i class="fas fa-calendar-day"></i>
                                            {{ $req->start_date ? $req->start_date->format('d-m-Y') : '-' }}
                                        </span>
                                        @if ($req->total_days > 1)
                                            <span class="mv meta-date">
                                                <i class="fas fa-calendar-day"></i>
                                                {{ $req->end_date ? $req->end_date->format('d-m-Y') : '-' }}
                                                <span class="cnt">({{ $req->total_days }} hari)</span>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="wa-item-meta meta-c2">
                                        <span class="ml">Tujuan</span>
                                        <span class="mv" title="{{ $req->dinas_ke ?: '-' }}"><i class="fas fa-map-marker-alt"></i> {{ $req->dinas_ke ?: '-' }}</span>
                                    </div>
                                    <div class="wa-item-meta meta-c3">
                                        <span class="ml">Jam Keluar</span>
                                        <span class="mv"><i class="fas fa-clock"></i>
                                            {{ $req->jam_keluar ? substr($req->jam_keluar, 0, 5) : '--:--' }}
                                        </span>
                                    </div>
                                    <div class="wa-item-meta meta-c4">
                                        <span class="ml" title="Jam Rencana Kembali"><span class="long">Jam Rencana Kembali</span><span class="short">Rencana Kembali</span></span>
                                        <span class="mv"><i class="fas fa-undo-alt"></i>
                                            {{ $req->rencana_kembali ? substr($req->rencana_kembali, 0, 5) : '--:--' }}
                                        </span>
                                    </div>

                                    <div class="wa-item-status">
                                        <span class="ml">Status</span>
                                        <span class="wa-badge {{ $progress['tone'] }}" data-wa-badge>
                                            <span data-wa-label>{{ $progress['label'] }}</span>
                                        </span>
                                    </div>

                                    <div class="wa-head-actions">
                                        <button type="button" class="wa-qr-btn" data-wa-qr="{{ $req->id }}"
                                                aria-label="Tampilkan QR pengajuan">
                                            <i class="fas fa-qrcode" aria-hidden="true"></i>
                                            <span class="tx">QR</span>
                                        </button>

                                        <button type="button" class="wa-toggle" data-wa-toggle aria-expanded="false"
                                                aria-label="Lihat detail pengajuan">
                                            <span class="tx">Detail</span>
                                            <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- Isi detail (disembunyikan sampai diklik) --}}
                                <div class="wa-collapse" data-wa-collapse hidden>

                                {{-- Detail isi surat --}}
                                <div class="wa-detail">
                                    <div>
                                        <div class="k">Keperluan</div>
                                        <div class="v">{{ \Illuminate\Support\Str::limit($req->keperluan, 90) }}</div>
                                    </div>
                                    <div>
                                        <div class="k">Tanggal Pengajuan</div>
                                        <div class="v">{{ $req->created_at ? $req->created_at->format('d-m-Y H:i') : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="k">Level Approval</div>
                                        <div class="v" data-wa-progress-text>
                                            @if ($progress['total'] > 0)
                                                Level {{ $req->current_level }} dari {{ $progress['total'] }}
                                                &middot; {{ $progress['done'] }} selesai
                                            @else
                                                Belum ada alur persetujuan
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <div class="k">Jam Kembali</div>
                                        <div class="v">
                                            @if ($req->jam_kembali)
                                                {{ substr($req->jam_kembali, 0, 5) }}
                                            @else
                                                <span class="text-muted">— (diisi Security)</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Lampiran --}}
                                @if (!empty($req->attach_files))
                                    <div class="wa-detail" style="grid-template-columns: 1fr;">
                                        <div>
                                            <div class="k">Lampiran</div>
                                            <div class="v">
                                                @foreach ($req->attach_files as $att)
                                                    @php
                                                        $attPath = \App\Services\WorkAssignment\WorkAssignmentAttachment::pathOf($att);
                                                        $attName = is_array($att) ? ($att['original_name'] ?? basename((string) $attPath)) : basename((string) $attPath);
                                                        $attExt  = strtolower(pathinfo((string) $attPath, PATHINFO_EXTENSION));
                                                        $attIcon = match ($attExt) {
                                                            'pdf' => 'fa-file-pdf',
                                                            'jpg', 'jpeg', 'png', 'webp' => 'fa-file-image',
                                                            'doc', 'docx' => 'fa-file-word',
                                                            'xls', 'xlsx' => 'fa-file-excel',
                                                            default => 'fa-file',
                                                        };
                                                    @endphp
                                                    <a href="{{ route('work-assignment.attachment.download', ['id' => $req->id, 'index' => $loop->index]) }}"
                                                       class="badge badge-light border text-primary mr-1 mb-1"
                                                       style="font-size:.7rem;padding:.25rem .5rem;">
                                                        <i class="fas {{ $attIcon }} mr-1"></i>{{ $attName }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Timeline approval --}}
                                @if (!empty($timeline))
                                    <div class="tl">
                                        <div class="tl-head">
                                            <span class="tl-head-title">Alur Persetujuan</span>
                                            <span class="tl-head-hint">Nama approver &amp; hasil keputusan tiap level</span>
                                        </div>
                                        <div data-wa-timeline>
                                        @foreach ($timeline as $step)
                                            @php
                                                $icon = match ($step['decision']) {
                                                    'approved' => 'fa-check',
                                                    'rejected' => 'fa-times',
                                                    'waiting'  => 'fa-clock',
                                                    'skipped'  => 'fa-minus',
                                                    default    => 'fa-circle',
                                                };
                                                $label = match ($step['decision']) {
                                                    'approved' => 'Disetujui',
                                                    'rejected' => 'Ditolak',
                                                    'waiting'  => 'Menunggu',
                                                    'skipped'  => 'Dilewati',
                                                    default    => 'Belum Aktif',
                                                };
                                            @endphp
                                            <div class="tl-step" data-level="{{ $step['level'] }}">
                                                <div class="tl-dot {{ $step['decision'] }}" data-tl-dot>
                                                    <i class="fas {{ $icon }}" data-tl-icon></i>
                                                </div>
                                                <div class="tl-body">
                                                    <div class="tl-title">
                                                        <span class="role">L{{ $step['level'] }} &middot; {{ $step['role_label'] }}</span>
                                                        <span class="nm">{{ $step['name'] ?: '—' }}</span>
                                                    </div>
                                                    <div class="tl-sub">
                                                        {{ $step['npk'] ?: 'NPK belum diset' }}
                                                        @if (!empty($step['jabatan']) && $step['jabatan'] !== $step['name'])
                                                            &middot; <span class="jab">{{ $step['jabatan'] }}</span>
                                                        @endif
                                                        <span data-tl-time>@if (!empty($step['decided_at'])) &middot; {{ $step['decided_at'] }} @endif</span>
                                                    </div>
                                                    <div class="tl-reason" data-tl-reason @if (empty($step['reason'])) style="display:none" @endif>
                                                        @if (!empty($step['reason']))
                                                            <b>Alasan:</b> {{ $step['reason'] }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="tl-status {{ $step['decision'] }}" data-tl-status>{{ $label }}</div>
                                            </div>
                                        @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="tl">
                                        <div class="tl-sub">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Belum ada alur persetujuan terdaftar untuk departemen ini.
                                        </div>
                                    </div>
                                @endif
                                </div>{{-- /.wa-collapse --}}
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal QR pengajuan — discan security di gate keluar & kembali --}}
<div class="wa-qr-modal" id="wa-qr-modal" hidden>
    <div class="wa-qr-backdrop" data-wa-qr-close></div>

    <div class="wa-qr-dialog" role="dialog" aria-modal="true" aria-labelledby="wa-qr-title">
        <button type="button" class="wa-qr-x" data-wa-qr-close aria-label="Tutup">&times;</button>

        <div class="wa-qr-head">
            <h6 id="wa-qr-title">QR Pengajuan</h6>
            <div class="wa-qr-sub">
                Tunjukkan QR ini ke petugas security saat keluar dan saat kembali.
            </div>
        </div>

        <div class="wa-qr-img" id="wa-qr-img">
            <span class="wa-qr-load">Memuat&hellip;</span>
        </div>

        <div class="wa-qr-id">#<span id="wa-qr-id">-</span></div>
        <div class="wa-qr-stage" id="wa-qr-stage"></div>
    </div>
</div>

@include('layout.footerscript')

<script>
(function () {
    const urlProgress = @json(route('work-assignment.progress'));

    const ICONS = {
        approved: 'fa-check',
        rejected: 'fa-times',
        waiting: 'fa-clock',
        skipped: 'fa-minus'
    };

    const LABELS = {
        approved: 'Disetujui',
        rejected: 'Ditolak',
        waiting: 'Menunggu',
        skipped: 'Dilewati'
    };

    const btn = document.getElementById('btn-refresh');
    let timer = null;
    let busy = false;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    /** Update satu item pengajuan dengan data terbaru dari server. */
    function applyItem(item) {
        const el = document.querySelector('[data-wa-item="' + item.id + '"]');
        if (!el) return;

        // Badge status
        const badge = el.querySelector('[data-wa-badge]');
        if (badge) {
            badge.className = 'wa-badge ' + item.tone;

            const label = badge.querySelector('[data-wa-label]');
            if (label) label.textContent = item.label;
        }

        // Teks level approval (di dalam detail)
        const txt = el.querySelector('[data-wa-progress-text]');
        if (txt) {
            txt.textContent = item.total > 0
                ? 'Level ' + item.current_level + ' dari ' + item.total + ' · ' + item.done + ' selesai'
                : 'Belum ada alur persetujuan';
        }

        // Timeline per level
        const tl = el.querySelector('[data-wa-timeline]');
        if (tl && item.timeline) {
            item.timeline.forEach(function (step) {
                const row = tl.querySelector('.tl-step[data-level="' + step.level + '"]');
                if (!row) return;

                const dot = row.querySelector('[data-tl-dot]');
                if (dot) {
                    dot.className = 'tl-dot ' + step.decision;
                    const icon = dot.querySelector('[data-tl-icon]');
                    if (icon) icon.className = 'fas ' + (ICONS[step.decision] || 'fa-circle');
                }

                const status = row.querySelector('[data-tl-status]');
                if (status) {
                    status.className = 'tl-status ' + step.decision;
                    status.textContent = LABELS[step.decision] || 'Belum Aktif';
                }

                const time = row.querySelector('[data-tl-time]');
                if (time) time.innerHTML = step.decided_at ? ' &middot; ' + escapeHtml(step.decided_at) : '';

                const reason = row.querySelector('[data-tl-reason]');
                if (reason) {
                    if (step.reason) {
                        reason.style.display = '';
                        reason.innerHTML = '<b>Alasan:</b> ' + escapeHtml(step.reason);
                    } else {
                        reason.style.display = 'none';
                        reason.innerHTML = '';
                    }
                }
            });
        }
    }

    async function refresh(silent) {
        if (busy) return;
        busy = true;

        if (!silent && btn) btn.classList.add('is-loading');

        try {
            const res = await fetch(urlProgress, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });

            if (!res.ok) return;

            const data = await res.json();

            if (data.ok && Array.isArray(data.items)) {
                data.items.forEach(applyItem);
            }
        } catch (e) {
            // diamkan saja — refresh berikutnya akan mencoba lagi
        } finally {
            busy = false;
            if (btn) btn.classList.remove('is-loading');
        }
    }

    if (btn) {
        btn.addEventListener('click', function () { refresh(false); });
    }

    // ── Toggle detail per pengajuan ──
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-wa-toggle]');
        if (!toggle) return;

        var item = toggle.closest('[data-wa-item]');
        if (!item) return;

        var panel = item.querySelector('[data-wa-collapse]');
        if (!panel) return;

        var open = toggle.getAttribute('aria-expanded') === 'true';

        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        panel.hidden = open;

        var tx = toggle.querySelector('.tx');
        if (tx) tx.textContent = open ? 'Detail' : 'Sembunyikan';
    });

    // Auto-refresh tiap 30 detik — status approval berubah tanpa reload halaman.
    timer = setInterval(function () {
        if (document.hidden) return;
        refresh(true);
    }, 30000);

    // Refresh sekali saat halaman dibuka (status mungkin berubah sejak load).
    setTimeout(function () { refresh(true); }, 2000);
})();
</script>

<script>
// ── Modal QR pengajuan (untuk scan security) ──
(function () {
    'use strict';

    var modal  = document.getElementById('wa-qr-modal');
    var imgBox = document.getElementById('wa-qr-img');
    var idBox  = document.getElementById('wa-qr-id');
    var stageBox = document.getElementById('wa-qr-stage');

    if (!modal) { return; }

    var urlTemplate = @json(route('work-assignment.qr', ['id' => '__ID__']));
    var lastFocus = null;

    function stageText(stage, status) {
        if (status !== 'approved') {
            return 'Belum disetujui semua level — security belum bisa memproses.';
        }

        if (stage === 'out') { return 'Menunggu scan security: karyawan KELUAR.'; }
        if (stage === 'in')  { return 'Sudah keluar. Menunggu scan security saat KEMBALI.'; }

        return 'Selesai — keluar dan kembali sudah tercatat.';
    }

    function open(id, trigger) {
        lastFocus = trigger || null;

        imgBox.innerHTML = '<span class="wa-qr-load">Memuat&hellip;</span>';
        idBox.textContent = id;
        stageBox.textContent = '';
        modal.hidden = false;
        document.body.style.overflow = 'hidden';

        fetch(urlTemplate.replace('__ID__', id), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    imgBox.innerHTML = '<span class="wa-qr-load">' + (data.message || 'Gagal memuat QR.') + '</span>';
                    return;
                }

                var img = document.createElement('img');
                img.alt = 'QR pengajuan #' + data.id;
                img.src = data.image;
                imgBox.innerHTML = '';
                imgBox.appendChild(img);

                stageBox.textContent = stageText(data.stage, data.status);
            })
            .catch(function () {
                imgBox.innerHTML = '<span class="wa-qr-load">Gagal memuat QR.</span>';
            });
    }

    function close() {
        modal.hidden = true;
        document.body.style.overflow = '';

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-wa-qr]');

        if (btn) {
            e.preventDefault();
            open(btn.getAttribute('data-wa-qr'), btn);
            return;
        }

        if (e.target.closest('[data-wa-qr-close]')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { close(); }
    });
})();
</script>

</body>
</html>
