<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    :root{
        --dp-primary: #4e73df;
        --dp-primary-dark: #2e59d9;
        --dp-gold: #f6c343;
    }

    body{ background-color: #f4f6fb; }

    /* ---------- KPI cards ---------- */
    .kpi-card{
        position: relative;
        overflow: hidden;
        border-radius: .75rem;
        color: #fff;
        padding: 1.1rem 1.3rem;
        min-height: 100px;
    }
    .kpi-card .kpi-icon{
        position: absolute;
        right: -.5rem;
        bottom: -.75rem;
        font-size: 4rem;
        opacity: .18;
    }
    .kpi-card .kpi-label{
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        opacity: .9;
        font-weight: 700;
    }
    .kpi-card .kpi-value{
        font-size: 1.55rem;
        font-weight: 700;
        margin-top: .2rem;
    }
    .kpi-scanned  { background: linear-gradient(135deg, #4e73df 0%, #2e59d9 100%); }
    .kpi-available{ background: linear-gradient(135deg, #1cc88a 0%, #13a06d 100%); }
    .kpi-won      { background: linear-gradient(135deg, #2e59d9 0%, #1c3fa8 100%); }
    .kpi-void     { background: linear-gradient(135deg, #858796 0%, #60616b 100%); }

    /* ---------- Settings card ---------- */
    .quick-amount .btn{
        min-width: 48px;
    }
    .btn-draw{
        background: linear-gradient(135deg, var(--dp-primary) 0%, var(--dp-primary-dark) 100%);
        border: none;
        color:#fff;
        font-weight:700;
        letter-spacing:.03em;
        padding: .75rem 1rem;
        box-shadow: 0 .25rem .75rem rgba(78,115,223,.4);
    }
    .btn-draw:hover{ color:#fff; opacity:.95; }
    .btn-draw:disabled{ opacity:.6; }

    /* ---------- Stage ---------- */
    .stage-card{
        background: radial-gradient(circle at top, #1b2a4a 0%, #0f1830 70%);
        border-radius: .75rem;
        min-height: 320px;
        display:flex;
        flex-direction: column;
        align-items:center;
        justify-content:center;
        position: relative;
        overflow:hidden;
        padding: 2rem 1rem;
    }
    .stage-card::before{
        content:'';
        position:absolute;
        inset:0;
        background-image: radial-gradient(circle, rgba(255,255,255,.08) 1px, transparent 1px);
        background-size: 22px 22px;
        opacity:.5;
    }
    .stage-placeholder{
        color: rgba(255,255,255,.55);
        text-align:center;
        position:relative;
        z-index:1;
    }

    /* grid pemenang - mendukung banyak kartu tampil bersamaan */
    .stage-slot-grid{
        display:flex;
        flex-wrap:wrap;
        gap: 16px;
        justify-content:center;
        align-items:flex-start;
        width:100%;
        position:relative;
        z-index:1;
    }
    .slot-card {
        width: 210px;
        padding: 18px;
        border-radius: 14px;
        background: linear-gradient(160deg, #ffffff 0%, #f3f0ff 100%);
        box-shadow: 0 10px 30px rgba(0,0,0,.35);
        text-align:center;
        position:relative;
    }
    .slot-photo {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--dp-gold);
        box-shadow: 0 2px 10px rgba(0,0,0,.25);
    }
    .slot-badge-npk{
        display:inline-block;
        background: var(--dp-primary);
        color:#fff;
        font-weight:700;
        font-size:1rem;
        padding:.3rem .8rem;
        border-radius: 2rem;
        margin-top: .6rem;
    }
    .slot-name{ font-size: .95rem; }
    .slot-department{ font-size: .78rem; }

    .slot-shuffling .slot-photo { animation: slot-spin .15s linear infinite; }
    .slot-shuffling .slot-badge-npk,
    .slot-shuffling .slot-name,
    .slot-shuffling .slot-department { animation: slot-blur .15s linear infinite; }
    @keyframes slot-spin{
        0% { transform: scale(1) rotate(0deg); }
        50% { transform: scale(1.05) rotate(3deg); }
        100% { transform: scale(1) rotate(0deg); }
    }
    @keyframes slot-blur{
        0%, 100% { opacity: 1; }
        50% { opacity: 0.35; }
    }
    .winner-reveal{ animation: winner-pop .5s ease; }
    @keyframes winner-pop{
        0% { transform: scale(0.6); opacity: 0; }
        70% { transform: scale(1.08); opacity: 1; }
        100% { transform: scale(1); }
    }
    .progress-round{
        color: rgba(255,255,255,.75);
        font-size:.8rem;
        margin-top: 1rem;
        position:relative;
        z-index:1;
    }

    /* ---------- Round result mini list ---------- */
    #round-result-list .round-result-item{
        display:flex;
        align-items:center;
        gap:.6rem;
        padding:.5rem .75rem;
        border-radius:.5rem;
        background:#f8f7ff;
        border:1px solid #eee5ff;
        margin-bottom:.4rem;
    }
    #round-result-list img{
        width:34px; height:34px; border-radius:50%; object-fit:cover;
    }

    /* ---------- Table ---------- */
    .status-pill{
        font-size:.72rem;
        padding:.3rem .6rem;
        border-radius: 1rem;
        font-weight:700;
    }
    .status-menang{ background:#e3f9f0; color:#13a06d; }
    .status-hangus{ background:#fdecef; color:#c0293c; }
    .npk-photo-thumb{ width:36px; height:36px; border-radius:50%; object-fit:cover; }

    /* ---------- Voided list ---------- */
    .void-chip{
        display:inline-flex;
        align-items:center;
        gap:.35rem;
        background:#fdecef;
        color:#c0293c;
        font-weight:600;
        font-size:.78rem;
        padding:.3rem .65rem;
        border-radius: 1rem;
        margin:.15rem;
    }

    /* NPK & departemen langsung di sebelah foto, dipotong dengan ellipsis biar tidak melebar */
    #round-result-list .round-result-item .flex-grow-1{ min-width:0; }
    #round-result-list .round-result-item .font-weight-bold,
    #round-result-list .round-result-item .text-muted{
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }
    #btn-toggle-fullscreen{
        position:fixed;
        top:1rem;
        right:1rem;
        z-index:9999;
        opacity:.85;
    }
    #btn-toggle-fullscreen:hover{ opacity:1; }

    /* ============================================================
       LAYAR UNDIAN — layar proyektor KIRI (animasi kocok & reveal)
       ============================================================ */
    body.layar-undian-mode{
        background: radial-gradient(circle at 50% 15%, #26356b 0%, #101a38 55%, #05070f 100%);
        margin:0; padding:0; min-height:100vh; overflow:hidden;
    }
    body.layar-undian-mode::before{
        content:'';
        position:fixed; inset:0;
        background-image: radial-gradient(circle, rgba(255,255,255,.07) 1.5px, transparent 1.5px);
        background-size: 30px 30px;
        pointer-events:none;
        animation: undian-bg-drift 18s linear infinite;
    }
    @keyframes undian-bg-drift{
        0%{ background-position: 0 0; }
        100%{ background-position: 300px 300px; }
    }
    .layar-undian-wrap{
        min-height:100vh;
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        padding: 2.5rem 3rem;
        position:relative;
        z-index:1;
    }
    .layar-undian-header{ text-align:center; margin-bottom: 2.25rem; }
    .layar-undian-header .title-badge{
        display:inline-flex; align-items:center; gap:.5rem;
        background: linear-gradient(135deg, var(--dp-gold), #ffe08a);
        color:#3a2a00;
        font-weight:800;
        letter-spacing:.1em;
        text-transform:uppercase;
        padding:.45rem 1.4rem;
        border-radius: 2rem;
        font-size: .85rem;
        box-shadow: 0 .5rem 1.5rem rgba(246,195,67,.35);
    }
    .layar-undian-header h1{
        color:#fff;
        font-weight:800;
        font-size: 2.6rem;
        margin: .85rem 0 0;
        text-shadow: 0 4px 28px rgba(78,115,223,.65);
    }
    .layar-undian-header .event-name{
        color: rgba(255,255,255,.6);
        font-size: 1.05rem;
        margin-top:.35rem;
        letter-spacing:.03em;
    }
    .layar-undian-stage-grid{
        display:grid !important;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 1.75rem;
        width:100%;
        max-width: 1500px;
        justify-items:stretch;
    }
    .layar-undian-progress{
        margin-top:2.25rem;
        color: rgba(255,255,255,.85);
        font-size: 1.2rem;
        font-weight:600;
        letter-spacing:.04em;
    }
    body.layar-undian-mode .stage-placeholder{ color: rgba(255,255,255,.55); }
    body.layar-undian-mode .slot-card{
        width:100%;
        padding: 1.75rem 1.25rem;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0,0,0,.5), 0 0 0 2px rgba(246,195,67,.15);
    }
    body.layar-undian-mode .slot-photo{ width:120px; height:120px; border-width:5px; }
    body.layar-undian-mode .slot-badge-npk{ font-size:1.25rem; padding:.4rem 1rem; }
    body.layar-undian-mode .slot-name{ font-size:1.05rem; margin-top:.5rem !important; }
    body.layar-undian-mode .slot-department{ font-size:.9rem; }
    body.layar-undian-mode .winner-reveal{ animation: winner-pop-big .6s ease; }
    @keyframes winner-pop-big{
        0% { transform: scale(0.5) rotate(-4deg); opacity: 0; }
        60% { transform: scale(1.1) rotate(2deg); opacity: 1; }
        100% { transform: scale(1) rotate(0); }
    }

    /* ============================================================
       LAYAR PEMENANG — layar proyektor KANAN (leaderboard)
       ============================================================ */
    body.layar-pemenang-mode{
        background: linear-gradient(160deg, #fffaf0 0%, #fdf3dc 100%);
        margin:0; padding:0; min-height:100vh;
    }
    .layar-pemenang-wrap{
        min-height:100vh;
        display:flex; flex-direction:column;
        padding: 2rem 3rem;
    }
    .layar-pemenang-header{
        display:flex; align-items:center; justify-content:space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 3px solid var(--dp-gold);
        flex-shrink:0;
    }
    .layar-pemenang-header h1{
        font-weight:800; color:#2e2205; margin:0;
        font-size: 2.1rem;
        display:flex; align-items:center; gap:.6rem;
    }
    .layar-pemenang-header .event-name{ color:#8a7440; font-size:1rem; margin-top:.2rem; }
    .layar-pemenang-counter{
        background: linear-gradient(135deg, var(--dp-primary), var(--dp-primary-dark));
        color:#fff;
        border-radius: 1rem;
        padding: .8rem 1.75rem;
        text-align:center;
        box-shadow: 0 .5rem 1.5rem rgba(78,115,223,.35);
    }
    .layar-pemenang-counter .num{ font-size:2rem; font-weight:800; line-height:1; }
    .layar-pemenang-counter .lbl{ font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; opacity:.9; }
    .layar-pemenang-grid{
        flex:1;
        overflow-y:auto;
        display:grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
        align-content:start;
        padding-right:.5rem;
    }
    .pemenang-card{
        background:#fff;
        border-radius: 16px;
        padding: 1rem 1.2rem;
        display:flex; align-items:center; gap:1rem;
        box-shadow: 0 .2rem .8rem rgba(0,0,0,.06);
        border: 1px solid #f1e6c8;
        position:relative;
    }
    .pemenang-card.is-void{
        opacity:.85;
        background: #fdecef;
        border-color: #f3b6c0;
    }
    .pemenang-card.is-void .npk{ color:#c0293c; }
    .pemenang-card.is-void .photo{ border-color:#e6798a; filter: grayscale(.35); }
    .pemenang-card.is-void .void-tag{
        background:#c0293c;
        color:#fff;
    }
    .pemenang-card.is-new{ animation: pemenang-highlight 2.6s ease; }
    @keyframes pemenang-highlight{
        0%{ box-shadow: 0 0 0 5px rgba(246,195,67,.9), 0 .2rem .8rem rgba(0,0,0,.06); background:#fff8e1; transform: scale(1.02); }
        100%{ box-shadow: 0 .2rem .8rem rgba(0,0,0,.06); background:#fff; transform: scale(1); }
    }
    .pemenang-card .photo{ width:60px; height:60px; border-radius:50%; object-fit:cover; border:3px solid var(--dp-gold); flex-shrink:0; }
    .pemenang-card .info{ min-width:0; flex:1; }
    .pemenang-card .npk{ font-weight:800; color: var(--dp-primary-dark); font-size:1rem; }
    .pemenang-card .npk .trophy-icon{ color: var(--dp-gold); margin-left:.3rem; }
    .pemenang-card .name{ font-size:.92rem; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pemenang-card .dept{ font-size:.78rem; color:#8a8a8a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pemenang-card .batch{ font-size:.68rem; color:#b8952f; font-weight:700; text-transform:uppercase; margin-top:.2rem; }
    .pemenang-card .void-tag{
        position:absolute; top:.6rem; right:.7rem;
        background:#fdecef; color:#c0293c; font-size:.66rem; font-weight:700;
        padding:.2rem .55rem; border-radius: .6rem;
    }
    .layar-pemenang-empty{ color:#a08a56; text-align:center; margin-top:4rem; font-size:1.1rem; }
</style>
<body id="page-top">
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">

                @include('layout.navbar')

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-gift text-primary mr-2"></i>Undian Doorprize
                        </h1>
                        @if($event)
                            <span class="badge badge-primary p-2">
                                <i class="fas fa-calendar-check mr-1"></i> Event Aktif: {{ $event->nama_event }}
                            </span>
                        @endif
                    </div>

                    @if(!$event)
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Tidak ada event doorprize yang sedang aktif. Aktifkan salah satu event terlebih dahulu
                            sebelum melakukan scan atau undian.
                        </div>
                    @endif

                    {{-- ===================== KPI CARDS ===================== --}}
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="kpi-card kpi-scanned shadow h-100">
                                <div class="kpi-label">Total Discan</div>
                                <div class="kpi-value" id="kpi-scanned">{{ $totalScanned }}</div>
                                <i class="fas fa-qrcode kpi-icon"></i>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="kpi-card kpi-available shadow h-100">
                                <div class="kpi-label">Belum Menang</div>
                                <div class="kpi-value" id="kpi-available">{{ $totalAvailable }}</div>
                                <i class="fas fa-users kpi-icon"></i>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="kpi-card kpi-won shadow h-100">
                                <div class="kpi-label">Sudah Menang</div>
                                <div class="kpi-value" id="kpi-won">{{ $totalWon }}</div>
                                <i class="fas fa-trophy kpi-icon"></i>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="kpi-card kpi-void shadow h-100">
                                <div class="kpi-label">Hangus</div>
                                <div class="kpi-value" id="kpi-void">{{ $totalVoid }}</div>
                                <i class="fas fa-ban kpi-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        {{-- ===================== SETTINGS ===================== --}}
                        <div class="col-lg-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-sliders-h mr-1"></i> Pengaturan Undian
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-gray-600">Nama Sesi / Batch</label>
                                        <input type="text" id="batch-label" class="form-control" placeholder="Contoh: Undian Grand Prize">
                                    </div>

                                    <div class="form-group">
                                        <label class="small font-weight-bold text-gray-600">Jumlah Pemenang</label>
                                        <input type="number" id="draw-amount" class="form-control" value="5" min="1" max="100">
                                        <div class="quick-amount mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-amount-btn" data-amount="3">3</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-amount-btn" data-amount="5">5</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-amount-btn" data-amount="10">10</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-amount-btn" data-amount="15">15</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm quick-amount-btn" data-amount="20">20</button>
                                        </div>
                                        <small class="text-muted d-block mt-1">Bebas isi manual juga, sesuai kebutuhan.</small>
                                    </div>

                                    <button id="btn-draw" class="btn btn-draw btn-block">
                                        <i class="fas fa-dice"></i> MULAI UNDIAN
                                    </button>
                                </div>
                            </div>

                            {{-- ===================== DAFTAR NPK HANGUS ===================== --}}
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-danger">
                                        <i class="fas fa-ban mr-1"></i> Daftar NPK Hangus
                                    </h6>
                                </div>
                                <div class="card-body" id="void-list-wrapper">
                                    <div id="void-list">
                                        @forelse ($winners->where('is_void', true) as $w)
                                            <span class="void-chip" data-void-npk="{{ $w['npk'] }}">
                                                <i class="fas fa-times-circle"></i> {{ $w['npk'] }}
                                            </span>
                                        @empty
                                            <p class="text-muted small mb-0" id="void-list-empty">Belum ada NPK yang dihanguskan.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ===================== PANGGUNG UNDIAN ===================== --}}
                        <div class="col-lg-8">
                            <div class="card shadow mb-4" id="panggung-undian-card">
                                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-star mr-1"></i> Panggung Undian
                                    </h6>
                                    <div class="btn-group">
                                        <button type="button" id="btn-open-layar-undian" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-dice mr-1"></i> Layar Undian
                                        </button>
                                        <button type="button" id="btn-open-layar-pemenang" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-trophy mr-1"></i> Layar Pemenang
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="stage-card">
                                        <div id="stage-placeholder" class="stage-placeholder">
                                            <i class="fas fa-gift fa-3x mb-2"></i>
                                            <p class="mb-0">Atur jumlah pemenang lalu klik "Mulai Undian"</p>
                                        </div>

                                        <div id="stage-slot" class="d-none w-100">
                                            <div id="stage-slot-grid" class="stage-slot-grid"></div>
                                            <p class="progress-round text-center" id="progress-round"></p>
                                        </div>
                                    </div>

                                    {{-- Hasil ronde undian saat ini, lengkap dengan tombol hanguskan langsung --}}
                                    <div id="round-result" class="mt-4 d-none">
                                        <h6 class="font-weight-bold text-gray-700 mb-2">
                                            <i class="fas fa-flag-checkered mr-1"></i> Pemenang Ronde Ini
                                        </h6>
                                        <div id="round-result-list"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== TABEL SEMUA PEMENANG ===================== --}}
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-list mr-1"></i> Daftar Semua Pemenang
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="winners-table" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Foto</th>
                                                    <th>NPK</th>
                                                    <th>Nama</th>
                                                    <th>Departemen</th>
                                                    <th>Batch</th>
                                                    <th>Waktu Menang</th>
                                                    <th>Status</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            @foreach ($winners as $w)
                                                <tr data-winner-id="{{ $w['id'] }}">
                                                    <td><img src="{{ $w['photo'] }}" class="npk-photo-thumb"></td>
                                                    <td>{{ $w['npk'] }}</td>
                                                    <td>{{ $w['name'] }}</td>
                                                    <td>{{ $w['department'] }}</td>
                                                    <td>{{ $w['batch_label'] }}</td>
                                                    <td>{{ $w['won_at'] }}</td>
                                                    <td>
                                                        @if ($w['is_void'])
                                                            <span class="status-pill status-hangus">Hangus</span>
                                                        @else
                                                            <span class="status-pill status-menang">Menang</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if (!$w['is_void'])
                                                            <button type="button" class="btn btn-outline-danger btn-sm btn-void-winner"
                                                                data-id="{{ $w['id'] }}" data-npk="{{ $w['npk'] }}">
                                                                <i class="fas fa-ban"></i> Hanguskan
                                                            </button>
                                                        @else
                                                            <span class="text-muted small">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            @include('layout.footer')
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <script id="winners-seed-data" type="application/json">{!! json_encode($winners) !!}</script>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <script>
    $(function () {
        const params = new URLSearchParams(window.location.search);
        // 'undian' = layar KIRI (animasi kocok & reveal), 'pemenang' = layar KANAN (daftar pemenang)
        const stageMode = params.get('layar');
        const isDrawStage = stageMode === 'undian';
        const isWinnersStage = stageMode === 'pemenang';
        const isStageOnly = isDrawStage || isWinnersStage;
        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : null;

        // Channel untuk sinkronisasi tab utama <-> layar undian <-> layar pemenang
        const bc = ('BroadcastChannel' in window) ? new BroadcastChannel('doorprize-undian-stage') : null;

        const defaultPhoto = "{{ asset('storage/img/profile/default.jpg') }}";

        // Seed data seluruh pemenang (dipakai untuk pool acak & papan pemenang, tanpa tergantung tabel DOM)
        const winnersSeedEl = document.getElementById('winners-seed-data');
        let winnersSeed = [];
        try { winnersSeed = winnersSeedEl ? JSON.parse(winnersSeedEl.textContent || '[]') : []; } catch (e) { winnersSeed = []; }

        // Kumpulan NPK dummy untuk efek acak visual sebelum hasil asli muncul.
        let dummyPool = [];
        function buildDummyPool() {
            dummyPool = [];
            if (winnersSeed.length) {
                winnersSeed.forEach(w => { if (w.npk) dummyPool.push(w.npk); });
            } else {
                $('#winners-table tbody tr').each(function () {
                    const npk = $(this).find('td').eq(1).text().trim();
                    if (npk) dummyPool.push(npk);
                });
            }
            if (dummyPool.length === 0) {
                for (let i = 1; i <= 20; i++) {
                    dummyPool.push('C-' + String(i).padStart(5, '0'));
                }
            }
        }

        // Batasi panjang nama karyawan. Jika terlalu panjang, kata terakhir
        // (biasanya nama belakang) disingkat jadi huruf depannya saja.
        // Contoh: "TRI HANDAYANI SETIAWAN" (maxLen 20) -> "TRI HANDAYANI S."
        function truncateEmployeeName(name, maxLen = 22) {
            if (!name) return '';
            name = name.trim();
            if (name.length <= maxLen) return name;

            const parts = name.split(/\s+/);
            if (parts.length <= 1) {
                return name.substring(0, Math.max(1, maxLen - 1)) + '…';
            }

            const last = parts.pop();
            const initial = last.charAt(0).toUpperCase() + '.';
            let truncated = parts.join(' ') + ' ' + initial;

            // jaga-jaga kalau nama depan+tengah saja masih kepanjangan
            if (truncated.length > maxLen) {
                truncated = truncated.substring(0, Math.max(1, maxLen - 1)) + '…';
            }
            return truncated;
        }

        function fireConfetti() {
            if (typeof confetti !== 'function') return;
            confetti({
                particleCount: 160,
                spread: 100,
                origin: { y: 0.5 },
                colors: ['#4e73df', '#2e59d9', '#f6c343', '#1cc88a'],
            });
        }

        // Membangun kartu-kartu kosong sejumlah pemenang yang akan diundi, tampil serentak.
        function buildStageGrid(amount) {
            const $grid = $('#stage-slot-grid').empty();
            for (let i = 0; i < amount; i++) {
                $grid.append(`
                    <div class="slot-card slot-shuffling" data-slot-index="${i}">
                        <img class="slot-photo" src="${defaultPhoto}">
                        <div class="slot-info">
                            <div class="slot-badge-npk">C-00000</div>
                            <p class="slot-name text-gray-800 font-weight-bold mt-2 mb-0">-</p>
                            <p class="slot-department text-muted small">-</p>
                        </div>
                    </div>
                `);
            }
            $('#stage-placeholder').addClass('d-none');
            $('#stage-slot').removeClass('d-none');
        }

        function shuffleGridOnce() {
            $('#stage-slot-grid .slot-card').each(function () {
                const random = dummyPool[Math.floor(Math.random() * dummyPool.length)];
                $(this).find('.slot-badge-npk').text(random);
                $(this).find('.slot-name').text('Mengacak...');
                $(this).find('.slot-department').text('...');
            });
        }

        // Mulai animasi acak untuk N kartu sekaligus. Return interval id.
        function startStageShuffle(amount) {
            buildDummyPool();
            buildStageGrid(amount);
            if (isStageOnly) {
                $('#round-result-list').html('<p class="text-muted small mb-0" id="round-result-empty">Mengundi...</p>');
            } else {
                $('#round-result').addClass('d-none');
                $('#round-result-list').empty();
            }
            $('#progress-round').text(`Mengundi ${amount} pemenang...`);
            return setInterval(shuffleGridOnce, 80);
        }

        // Ungkap semua pemenang secara bersamaan (bukan satu-satu).
        function revealAllWinners(winners) {
            const $cards = $('#stage-slot-grid .slot-card');
            winners.forEach((winner, i) => {
                const $card = $cards.eq(i);
                $card.removeClass('slot-shuffling').addClass('winner-reveal');
                $card.find('.slot-photo').attr('src', winner.photo);
                $card.find('.slot-badge-npk').text(winner.npk);
                $card.find('.slot-name').text(truncateEmployeeName(winner.name, 24)).attr('title', winner.name);
                $card.find('.slot-department').text(winner.department);
            });
            fireConfetti();
            setTimeout(() => $cards.removeClass('winner-reveal'), 500);
        }

        function appendRoundResultItem(winner) {
            $('#round-result').removeClass('d-none');
            $('#round-result-empty').remove();
            const displayName = truncateEmployeeName(winner.name, isStageOnly ? 16 : 30);
            $('#round-result-list').append(`
                <div class="round-result-item" data-round-winner-id="${winner.id}">
                    <img src="${winner.photo}">
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="font-weight-bold" title="${winner.name}">${winner.npk} - ${displayName}</div>
                        <div class="text-muted small">${winner.department}</div>
                    </div>
                    ${isStageOnly ? '' : `<button type="button" class="btn btn-outline-danger btn-sm btn-void-winner" data-id="${winner.id}" data-npk="${winner.npk}"><i class="fas fa-ban"></i> Hanguskan</button>`}
                </div>
            `);
        }

        function finishStageReveal(shuffleIntervalId, winners) {
            clearInterval(shuffleIntervalId);
            revealAllWinners(winners);
            $('#progress-round').text('Undian selesai!');
            winners.forEach(w => appendRoundResultItem(w));
        }

        // =====================================================================
        // MODE LAYAR TERPISAH (dibuka di jendela/proyektor lain)
        // ?layar=undian   -> layar KIRI: animasi kocok & reveal pemenang
        // ?layar=pemenang -> layar KANAN: papan daftar pemenang (live)
        // =====================================================================
        if (isStageOnly) {
            buildDummyPool();

            $(document).on('click', '#btn-toggle-fullscreen', function () {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(() => {});
                    $(this).html('<i class="fas fa-compress mr-1"></i> Keluar Fullscreen');
                } else {
                    document.exitFullscreen();
                    $(this).html('<i class="fas fa-expand mr-1"></i> Fullscreen');
                }
            });

            // ---------------- LAYAR UNDIAN (KIRI) ----------------
            if (isDrawStage) {
                $('body').addClass('layar-undian-mode');
                $('body').empty().append(`
                    <div class="layar-undian-wrap">
                        <div class="layar-undian-header">
                            <span class="title-badge"><i class="fas fa-gift mr-1"></i> Doorprize</span>
                            <h1>Panggung Undian</h1>
                            @if($event)
                                <div class="event-name">{{ $event->name }}</div>
                            @endif
                        </div>

                        <div id="stage-placeholder" class="stage-placeholder text-center">
                            <i class="fas fa-dice fa-4x mb-3" style="color: rgba(255,255,255,.35)"></i>
                            <p class="mb-0" style="color: rgba(255,255,255,.6); font-size:1.1rem;">
                                Menunggu undian dimulai dari layar admin...
                            </p>
                        </div>

                        <div id="stage-slot" class="d-none w-100">
                            <div id="stage-slot-grid" class="layar-undian-stage-grid"></div>
                            <p class="layar-undian-progress text-center" id="progress-round"></p>
                        </div>
                    </div>
                    <button id="btn-toggle-fullscreen" class="btn btn-sm btn-light shadow">
                        <i class="fas fa-expand mr-1"></i> Fullscreen
                    </button>
                `);

                let stageShuffleInterval = null;

                if (bc) {
                    bc.onmessage = (ev) => {
                        const msg = ev.data || {};
                        if (msg.type === 'draw-start') {
                            stageShuffleInterval = startStageShuffle(msg.amount);
                        } else if (msg.type === 'draw-reveal') {
                            finishStageReveal(stageShuffleInterval, msg.winners);
                        }
                    };
                } else {
                    $('#stage-placeholder').html(
                        '<p class="mb-0" style="color: rgba(255,255,255,.6);">Browser ini tidak mendukung sinkronisasi otomatis (BroadcastChannel). Gunakan browser modern seperti Chrome/Edge terbaru.</p>'
                    );
                }
            }

            // ---------------- LAYAR PEMENANG (KANAN) ----------------
            if (isWinnersStage) {
                $('body').addClass('layar-pemenang-mode');

                let pemenangList = Array.isArray(winnersSeed) ? [...winnersSeed] : [];

                function countWon() {
                    return pemenangList.filter(w => !w.is_void).length;
                }

                function renderPemenangCard(w, isNewCard) {
                    const displayName = truncateEmployeeName(w.name, 26);
                    return `
                        <div class="pemenang-card ${w.is_void ? 'is-void' : ''} ${isNewCard ? 'is-new' : ''}" data-winner-id="${w.id}">
                            ${w.is_void ? '<span class="void-tag">HANGUS</span>' : ''}
                            <img class="photo" src="${w.photo}">
                            <div class="info">
                                <div class="npk">${w.npk}<i class="fas fa-trophy trophy-icon"></i></div>
                                <div class="name" title="${w.name}">${displayName}</div>
                                <div class="dept" title="${w.department || ''}">${w.department || '-'}</div>
                                ${w.batch_label ? `<div class="batch">${w.batch_label}</div>` : ''}
                            </div>
                        </div>
                    `;
                }

                function renderPemenangGrid(newIds) {
                    newIds = newIds || [];
                    const sorted = [...pemenangList].reverse(); // terbaru tampil paling atas
                    const html = sorted.map(w => renderPemenangCard(w, newIds.includes(w.id))).join('');
                    $('#layar-pemenang-grid').html(html || '<p class="layar-pemenang-empty"><i class="fas fa-hourglass-half mr-1"></i> Belum ada pemenang. Menunggu undian dimulai...</p>');
                    $('#layar-pemenang-total .num').text(countWon());
                }

                $('body').empty().append(`
                    <div class="layar-pemenang-wrap">
                        <div class="layar-pemenang-header">
                            <div>
                                <h1><i class="fas fa-trophy" style="color: var(--dp-gold);"></i> Daftar Pemenang</h1>
                                @if($event)
                                    <div class="event-name">{{ $event->name }}</div>
                                @endif
                            </div>
                            <div id="layar-pemenang-total" class="layar-pemenang-counter">
                                <div class="num">0</div>
                                <div class="lbl">Total Pemenang</div>
                            </div>
                        </div>
                        <div id="layar-pemenang-grid" class="layar-pemenang-grid"></div>
                    </div>
                    <button id="btn-toggle-fullscreen" class="btn btn-sm btn-light shadow">
                        <i class="fas fa-expand mr-1"></i> Fullscreen
                    </button>
                `);

                renderPemenangGrid();

                if (bc) {
                    bc.onmessage = (ev) => {
                        const msg = ev.data || {};
                        if (msg.type === 'draw-reveal') {
                            const newIds = [];
                            (msg.winners || []).forEach(w => {
                                pemenangList.push(w);
                                newIds.push(w.id);
                            });
                            renderPemenangGrid(newIds);
                        } else if (msg.type === 'void-winner') {
                            const item = pemenangList.find(w => String(w.id) === String(msg.id));
                            if (item) item.is_void = true;
                            renderPemenangGrid();
                        }
                    };
                } else {
                    $('#layar-pemenang-grid').html('<p class="layar-pemenang-empty">Browser ini tidak mendukung sinkronisasi otomatis (BroadcastChannel). Gunakan browser modern seperti Chrome/Edge terbaru.</p>');
                }
            }

            return; // layar terpisah tidak butuh tabel, KPI, atau tombol undian admin
        }

        // =====================================================================
        // MODE UTAMA (halaman admin lengkap)
        // =====================================================================
        const winnersTable = $('#winners-table').DataTable({
            order: [[5, 'desc']],
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ - _END_ dari _TOTAL_ pemenang',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_ total data)',
                zeroRecords: 'Tidak ada pemenang yang cocok',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
            }
        });

        buildDummyPool();

        function appendWinnerRow(winner, batchLabel) {
            const statusHtml = winner.is_void
                ? '<span class="status-pill status-hangus">Hangus</span>'
                : '<span class="status-pill status-menang">Menang</span>';
            const actionHtml = winner.is_void
                ? '<span class="text-muted small">-</span>'
                : `<button type="button" class="btn btn-outline-danger btn-sm btn-void-winner" data-id="${winner.id}" data-npk="${winner.npk}"><i class="fas fa-ban"></i> Hanguskan</button>`;

            const row = winnersTable.row.add([
                `<img src="${winner.photo}" class="npk-photo-thumb">`,
                winner.npk,
                winner.name,
                winner.department,
                batchLabel || '-',
                winner.won_at || new Date().toLocaleString(),
                statusHtml,
                actionHtml,
            ]).draw().node();

            $(row).attr('data-winner-id', winner.id);
        }

        function updateKpi({ available, won, voidCount }) {
            if (available !== undefined) $('#kpi-available').text(available);
            if (won !== undefined) $('#kpi-won').text(won);
            if (voidCount !== undefined) $('#kpi-void').text(voidCount);
        }

        function markVoidedInUi(winnerId, npk) {
            const $row = $(`#winners-table tbody tr[data-winner-id="${winnerId}"]`);
            if ($row.length) {
                $row.find('td').eq(6).html('<span class="status-pill status-hangus">Hangus</span>');
                $row.find('td').eq(7).html('<span class="text-muted small">-</span>');
            }

            $(`.round-result-item[data-round-winner-id="${winnerId}"] .btn-void-winner`)
                .replaceWith('<span class="text-muted small">Sudah dihanguskan</span>');

            $('#void-list-empty').remove();
            $('#void-list').append(`
                <span class="void-chip" data-void-npk="${npk}">
                    <i class="fas fa-times-circle"></i> ${npk}
                </span>
            `);
        }

        function voidWinner(id, npk) {
            Swal.fire({
                icon: 'warning',
                title: 'Hanguskan Pemenang?',
                html: `NPK <b>${npk}</b> akan dihanguskan dan tidak bisa menang lagi di undian berikutnya.`,
                input: 'text',
                inputPlaceholder: 'Alasan (opsional)',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hanguskan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#e74a3b',
            }).then((result) => {
                if (!result.isConfirmed) return;

                fetch(`{{ url('doorprize/winners') }}/${id}/void`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ reason: result.value || null })
                })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        Swal.fire('Gagal', data.message || 'Terjadi kesalahan.', 'error');
                        return;
                    }
                    markVoidedInUi(id, npk);
                    updateKpi({ won: data.total_won, voidCount: data.total_void });
                    if (bc) bc.postMessage({ type: 'void-winner', id: id, npk: npk });
                    Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message, timer: 1500, showConfirmButton: false });
                })
                .catch(() => Swal.fire('Gagal', 'Terjadi kesalahan, silakan coba lagi.', 'error'));
            });
        }

        $(document).on('click', '.btn-void-winner', function () {
            voidWinner($(this).data('id'), $(this).data('npk'));
        });

        $('.quick-amount-btn').on('click', function () {
            $('#draw-amount').val($(this).data('amount'));
            $('.quick-amount-btn').removeClass('btn-primary').addClass('btn-outline-primary');
            $(this).removeClass('btn-outline-primary').addClass('btn-primary');
        });

        // Buka Layar Undian & Layar Pemenang di jendela terpisah, siap fullscreen untuk 2 proyektor.
        // Nama jendela dibuat tetap agar klik berikutnya fokus ke jendela yang sama (tidak buka duplikat).
        function openLayarWindow(layar) {
            const url = new URL(window.location.href);
            url.searchParams.set('layar', layar);
            window.open(url.toString(), 'doorprize_layar_' + layar, 'noopener');
        }
        $('#btn-open-layar-undian').on('click', function () {
            openLayarWindow('undian');
        });
        $('#btn-open-layar-pemenang').on('click', function () {
            openLayarWindow('pemenang');
        });

        $('#btn-draw').on('click', function () {
            const amount = parseInt($('#draw-amount').val(), 10);
            const batchLabel = $('#batch-label').val();
            const totalAvailable = parseInt($('#kpi-available').text(), 10);

            if (!amount || amount < 1) {
                Swal.fire('Oops', 'Jumlah pemenang minimal 1.', 'warning');
                return;
            }
            if (amount > totalAvailable) {
                Swal.fire('Oops', `Jumlah undian (${amount}) melebihi peserta tersisa (${totalAvailable}).`, 'warning');
                return;
            }

            $('#btn-draw').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengundi...');

            // Mulai animasi acak untuk SEMUA kartu sekaligus (bukan satu per satu).
            const shuffleStartedAt = Date.now();
            const shuffleInterval = startStageShuffle(amount);

            // Beritahu tab panggung (jika terbuka) agar mulai animasi acak yang sama.
            if (bc) bc.postMessage({ type: 'draw-start', amount });

            fetch("{{ route('doorprize.draw.run') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ amount: amount, batch_label: batchLabel })
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    clearInterval(shuffleInterval);
                    $('#btn-draw').prop('disabled', false).html('<i class="fas fa-dice"></i> MULAI UNDIAN');
                    $('#stage-slot').addClass('d-none');
                    $('#stage-placeholder').removeClass('d-none');
                    Swal.fire('Gagal', data.message || 'Terjadi kesalahan.', 'error');
                    return;
                }

                // Pastikan animasi acak minimal terlihat sebentar (~700ms) walau server sudah balas duluan.
                const minShuffleTime = 700;
                const elapsed = Date.now() - shuffleStartedAt;
                const wait = Math.max(0, minShuffleTime - elapsed);

                setTimeout(() => {
                    finishStageReveal(shuffleInterval, data.winners);

                    // Sisipkan batch_label ke tiap pemenang agar layar Daftar Pemenang bisa menampilkannya.
                    const winnersForBroadcast = (data.winners || []).map(w => ({ ...w, batch_label: w.batch_label || batchLabel || null }));

                    // Beritahu layar undian & layar pemenang agar mengungkap hasil yang sama, bersamaan.
                    if (bc) bc.postMessage({ type: 'draw-reveal', winners: winnersForBroadcast, batchLabel });

                    data.winners.forEach(w => appendWinnerRow(w, batchLabel));
                    updateKpi({ available: data.remaining, won: data.total_won });
                    $('#btn-draw').prop('disabled', false).html('<i class="fas fa-dice"></i> MULAI UNDIAN');

                    Swal.fire('Selesai', data.message, 'success');
                }, wait);
            })
            .catch(() => {
                clearInterval(shuffleInterval);
                $('#btn-draw').prop('disabled', false).html('<i class="fas fa-dice"></i> MULAI UNDIAN');
                $('#stage-slot').addClass('d-none');
                $('#stage-placeholder').removeClass('d-none');
                Swal.fire('Gagal', 'Terjadi kesalahan, silakan coba lagi.', 'error');
            });
        });
    });
    </script>
</body>

</html>