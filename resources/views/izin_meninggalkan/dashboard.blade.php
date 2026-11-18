<!DOCTYPE html>
<html lang="id">
@include('layout.header')
@include('work_assignment._styles')

<style>
    .im-list { padding: .9rem 1.1rem 1.2rem; }

    .im-item {
        border: 1px solid var(--wa-border);
        border-radius: .7rem;
        background: #fff;
        margin-bottom: .65rem;
        overflow: hidden;
    }

    .im-item-head {
        display: grid;
        grid-template-columns: 92px repeat(4, minmax(110px, 1fr)) auto;
        gap: .7rem;
        align-items: center;
        padding: .8rem .9rem;
    }

    .im-meta .k {
        display: block;
        font-size: .6rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #8b98ad;
        font-weight: 700;
        margin-bottom: .12rem;
    }

    .im-meta .v {
        font-size: .82rem;
        font-weight: 700;
        color: #22344d;
    }

    .im-id { font-size: .92rem; font-weight: 800; color: var(--wa-primary); }

    .im-badge {
        display: inline-block;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .02em;
        padding: .2rem .55rem;
        border-radius: 999px;
    }

    .im-badge.success { background: #dcfce7; color: #146c43; }
    .im-badge.warning { background: #fdf3d8; color: #8a6d10; }
    .im-badge.danger  { background: #fde2e1; color: #a12622; }
    .im-badge.secondary { background: #eef1f7; color: #6b7688; }

    .im-toggle {
        border: 1px solid var(--wa-border);
        background: #fff;
        color: var(--wa-primary);
        font-size: .72rem;
        font-weight: 700;
        padding: .26rem .6rem;
        border-radius: 2rem;
        cursor: pointer;
        white-space: nowrap;
    }

    .im-detail {
        border-top: 1px dashed var(--wa-border);
        padding: .8rem .9rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: .7rem 1.2rem;
        background: #fbfcfe;
    }

    .im-detail[hidden] { display: none; }

    .im-detail .k {
        font-size: .62rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #8b98ad;
        font-weight: 700;
        margin-bottom: .12rem;
    }

    .im-detail .v { font-size: .84rem; color: #22344d; }

    .im-tl { grid-column: 1 / -1; }

    .im-step {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .35rem 0;
        border-bottom: 1px dashed #e6ecf5;
        font-size: .8rem;
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

    .im-step .who { flex: 1; }
    .im-step .who b { display: block; font-size: .8rem; }
    .im-step .who small { color: #71809a; font-size: .72rem; }

    .im-empty {
        text-align: center;
        color: #8b98ad;
        font-size: .86rem;
        padding: 2.2rem 1rem;
    }

    .im-empty i { font-size: 1.9rem; display: block; margin-bottom: .5rem; color: #c2cddd; }

    @media (max-width: 860px) {
        .im-item-head {
            grid-template-columns: 1fr 1fr;
            gap: .6rem .9rem;
        }
        .im-item-head .im-actions { grid-column: 1 / -1; }
    }
</style>

<body>

<div class="wa-shell py-3">

    @include('sweetalert::alert')

    <div class="wa-card">

        <div class="wa-head">
            <div class="wa-head-user">
                <div class="emp-avatar">{{ strtoupper(substr($employee['nama'] ?? '?', 0, 1)) }}</div>
                <div>
                    <div class="emp-name">{{ $employee['nama'] }}</div>
                    <div class="emp-npk">{{ $employee['npk'] }}</div>
                </div>
            </div>
            <div class="wa-head-actions">
                <a href="{{ route('izin-meninggalkan.form') }}" class="btn btn-sm btn-light rounded-pill font-weight-bold">
                    <i class="fas fa-plus mr-1"></i>Buat Pengajuan
                </a>
                <a href="{{ route('izin-meninggalkan.logout') }}" class="btn btn-sm btn-outline-light rounded-pill">
                    <i class="fas fa-sign-out-alt mr-1"></i>Keluar
                </a>
            </div>
        </div>

        <div style="padding:1rem 1.1rem .2rem;">
            <h4 style="font-size:1rem;font-weight:800;color:#1b3a6b;margin:0 0 .15rem;">
                Pengajuan Izin Meninggalkan Tugas Saya
            </h4>
            <div style="font-size:.78rem;color:#71809a;">
                Pantau status persetujuan setiap pengajuan Anda.
            </div>

            <div class="emp-chips" style="margin-top:.8rem;">
                <span class="emp-chip">Bagian: <strong>{{ $employee['bagian'] ?: '-' }}</strong></span>
                <span class="emp-chip">Departemen: <strong>{{ $employee['departemen'] ?: '-' }}</strong></span>
                <span class="emp-chip">Section: <strong>{{ $employee['section'] ?: '-' }}</strong></span>
            </div>
        </div>

        <div class="im-list">

            <div style="font-size:.72rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#8b98ad;margin:.6rem 0 .55rem;">
                Riwayat Pengajuan ({{ $requests->count() }})
            </div>

            @forelse ($requests as $req)
                @php
                    $progress = $req->progress();
                    $timeline = $req->timeline();
                @endphp

                <div class="im-item">
                    <div class="im-item-head">
                        <div>
                            <div class="im-id">#{{ $req->id }}</div>
                            <div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.04em;color:#8b98ad;font-weight:700;">
                                No. Pengajuan
                            </div>
                        </div>

                        <div class="im-meta">
                            <span class="k">Tanggal</span>
                            <span class="v">{{ $req->tanggal?->format('d-m-Y') ?? '-' }}</span>
                        </div>

                        <div class="im-meta">
                            <span class="k">Jam Keluar</span>
                            <span class="v">{{ $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-' }}</span>
                        </div>

                        <div class="im-meta">
                            <span class="k">Rencana Kembali</span>
                            <span class="v">{{ $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : '-' }}</span>
                        </div>

                        <div class="im-meta">
                            <span class="k">Jam Kembali</span>
                            <span class="v">{{ $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : '-' }}</span>
                        </div>

                        <div class="im-actions" style="display:flex;align-items:center;gap:.45rem;">
                            <span class="im-badge {{ $progress['tone'] }}">{{ $progress['label'] }}</span>
                            <button type="button" class="im-toggle" data-im-toggle aria-expanded="false">
                                Detail <i class="fas fa-chevron-down" style="font-size:.6rem;"></i>
                            </button>
                        </div>
                    </div>

                    <div class="im-detail" data-im-detail hidden>
                        <div>
                            <div class="k">Alasan</div>
                            <div class="v">{{ $req->alasan ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="k">Tanggal Pengajuan</div>
                            <div class="v">{{ $req->created_at?->format('d-m-Y H:i') ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="k">Level Approval</div>
                            <div class="v">{{ $progress['done'] }} dari {{ $progress['total'] }} level disetujui</div>
                        </div>

                        <div class="im-tl">
                            <div class="k" style="margin-bottom:.35rem;">Alur Persetujuan</div>

                            @foreach ($timeline as $step)
                                <div class="im-step {{ $step['decision'] }}">
                                    <span class="ic">
                                        @switch($step['decision'])
                                            @case('approved') <i class="fas fa-check"></i> @break
                                            @case('rejected') <i class="fas fa-times"></i> @break
                                            @case('waiting')  <i class="fas fa-hourglass-half"></i> @break
                                            @default <i class="fas fa-minus"></i>
                                        @endswitch
                                    </span>
                                    <span class="who">
                                        <b>{{ $step['name'] }}</b>
                                        <small>
                                            Level {{ $step['level'] }} &middot; {{ $step['role_label'] }}
                                            @if ($step['decision'] === 'approved' && $step['decided_at'])
                                                &middot; {{ $step['decided_at'] }}
                                            @elseif ($step['decision'] === 'rejected')
                                                &middot; ditolak{{ $step['reason'] ? ': ' . $step['reason'] : '' }}
                                            @elseif ($step['decision'] === 'waiting')
                                                &middot; menunggu
                                            @elseif ($step['decision'] === 'upcoming')
                                                &middot; belum giliran
                                            @else
                                                &middot; dilewati
                                            @endif
                                        </small>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="im-empty">
                    <i class="fas fa-inbox"></i>
                    Belum ada pengajuan.<br>Klik <strong>Buat Pengajuan</strong> untuk memulai.
                </div>
            @endforelse
        </div>
    </div>
</div>

@include('layout.footerscript')

<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-im-toggle]');
    if (!btn) { return; }

    var item   = btn.closest('.im-item');
    var detail = item.querySelector('[data-im-detail]');
    var open   = btn.getAttribute('aria-expanded') === 'true';

    btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    detail.hidden = open;
});
</script>

</body>
</html>