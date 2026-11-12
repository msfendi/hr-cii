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
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .8rem 1rem;
        flex-wrap: wrap;
    }

    .wa-item-id {
        font-weight: 800;
        font-size: .87rem;
        color: var(--wa-primary-dark);
        white-space: nowrap;
    }

    .wa-item-meta {
        font-size: .76rem;
        color: var(--wa-muted);
        display: flex;
        gap: .9rem;
        flex-wrap: wrap;
        flex: 1 1 auto;
    }

    .wa-item-meta i {
        margin-right: .2rem;
        opacity: .7;
    }

    .wa-badge {
        font-size: .68rem;
        font-weight: 800;
        border-radius: 2rem;
        padding: .2rem .65rem;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .wa-badge.warning { background: #fff4d6; color: #8a6100; }
    .wa-badge.success { background: #dcfce7; color: #146c43; }
    .wa-badge.danger  { background: #fde2e1; color: #a12622; }
    .wa-badge.secondary { background: #eef1f7; color: #6b7688; }

    /* Progress bar */
    .wa-progress {
        height: 4px;
        background: #edf0f7;
        border-radius: 2rem;
        overflow: hidden;
    }

    .wa-progress > span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #4e73df, #6ea8fe);
        border-radius: 2rem;
        transition: width .25s;
    }

    .wa-progress.done > span { background: linear-gradient(90deg, #16a34a, #4ade80); }
    .wa-progress.fail > span { background: linear-gradient(90deg, #dc2626, #f87171); }

    /* ── Timeline approval ── */
    .tl {
        padding: .3rem 1rem 1rem;
        border-top: 1px solid #f0f3f9;
    }

    .tl-step {
        display: flex;
        gap: .7rem;
        padding: .55rem 0;
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
        gap: .7rem 1.2rem;
        padding: .7rem 1rem;
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
</style>

<body>

<div class="wa-shell">

    {{-- ── Header ── --}}
    <div class="wa-head">
        <div class="brand">
            <img src="{{ asset('img/chutex.svg') }}" alt="Chutex">
            <div>
                <div class="t1">PT. Chutex International Indonesia</div>
                <div class="t2">Work Assignment Request &middot; CII.FM.HR.02.08</div>
            </div>
        </div>
        <div class="wa-head-actions">
            <div class="wa-head-user">
                <b>{{ $employee['nama'] ?? '-' }}</b><br>
                <span>NPK {{ $employee['npk'] ?? '-' }}</span>
            </div>
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
                <span class="doc-ref">FORM CII.FM.HR.02.08 &middot; REV 00</span>
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

                            <div class="wa-item">
                                <div class="wa-item-head">
                                    <span class="wa-item-id">#{{ $req->id }}</span>

                                    <div class="wa-item-meta">
                                        <span><i class="fas fa-calendar-day"></i>
                                            {{ $req->start_date ? $req->start_date->format('d/m/Y') : '-' }}
                                            @if ($req->total_days > 1)
                                                &ndash; {{ $req->end_date ? $req->end_date->format('d/m/Y') : '-' }}
                                                ({{ $req->total_days }} hari)
                                            @endif
                                        </span>
                                        <span><i class="fas fa-map-marker-alt"></i> {{ $req->dinas_ke ?: '-' }}</span>
                                        <span><i class="fas fa-clock"></i>
                                            {{ $req->jam_keluar ? substr($req->jam_keluar, 0, 5) : '--:--' }}
                                            &ndash;
                                            {{ $req->rencana_kembali ? substr($req->rencana_kembali, 0, 5) : '--:--' }}
                                        </span>
                                    </div>

                                    <span class="wa-badge {{ $progress['tone'] }}">{{ $progress['label'] }}</span>
                                </div>

                                {{-- Progress bar --}}
                                @if ($progress['total'] > 0)
                                    @php $pct = round($progress['done'] / $progress['total'] * 100); @endphp
                                    <div class="wa-progress {{ $progress['status'] === 'approved' ? 'done' : ($progress['status'] === 'rejected' ? 'fail' : '') }}"
                                         style="margin: 0 1rem .55rem;">
                                        <span style="width: {{ $pct }}%"></span>
                                    </div>
                                @endif

                                {{-- Detail isi surat --}}
                                <div class="wa-detail">
                                    <div>
                                        <div class="k">Keperluan</div>
                                        <div class="v">{{ \Illuminate\Support\Str::limit($req->keperluan, 90) }}</div>
                                    </div>
                                    <div>
                                        <div class="k">Diajukan</div>
                                        <div class="v">{{ $req->created_at ? $req->created_at->format('d/m/Y H:i') : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="k">Progress Approval</div>
                                        <div class="v">{{ $progress['done'] }} dari {{ $progress['total'] }} level</div>
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

                                {{-- Timeline approval --}}
                                @if (!empty($timeline))
                                    <div class="tl">
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
                                            <div class="tl-step">
                                                <div class="tl-dot {{ $step['decision'] }}">
                                                    <i class="fas {{ $icon }}"></i>
                                                </div>
                                                <div class="tl-body">
                                                    <div class="tl-title">
                                                        <span class="role">L{{ $step['level'] }} &middot; {{ $step['role_label'] }}</span>
                                                        {{ $step['name'] ?: '—' }}
                                                    </div>
                                                    <div class="tl-sub">
                                                        {{ $step['npk'] ?: 'NPK belum diset' }}
                                                        @if (!empty($step['jabatan']) && $step['jabatan'] !== $step['name'])
                                                            &middot; <span class="jab">{{ $step['jabatan'] }}</span>
                                                        @endif
                                                    </div>
                                                    @if (!empty($step['reason']))
                                                        <div class="tl-reason">
                                                            <b>Alasan:</b> {{ $step['reason'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="tl-status {{ $step['decision'] }}">{{ $label }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="tl">
                                        <div class="tl-sub">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Belum ada alur persetujuan terdaftar untuk departemen ini.
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('layout.footerscript')

</body>
</html>
