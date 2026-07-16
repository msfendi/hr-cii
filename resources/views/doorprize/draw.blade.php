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

    /* ---------- Stage-only fullscreen popup mode ---------- */
    body.stage-only-mode{
        background: #0b1226;
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }
    body.stage-only-mode #panggung-undian-card{
        box-shadow:none;
        margin:0;
        border-radius:0;
        min-height:100vh;
        display:flex;
        flex-direction:column;
    }
    body.stage-only-mode #panggung-undian-card .card-header{
        background:transparent;
        border-bottom:1px solid rgba(255,255,255,.08);
    }
    body.stage-only-mode #panggung-undian-card .card-header h6{
        color:#fff;
        font-size:1.4rem;
    }
    /* body-card jadi dua kolom pasti: panggung (3 bagian) | daftar pemenang (1 bagian) */
    body.stage-only-mode #panggung-undian-card .card-body{
        flex:1;
        display:grid;
        grid-template-columns: 3fr 1fr;
        align-items:stretch;
        gap:1rem;
        min-height:0;
        overflow:hidden;
    }
    body.stage-only-mode .stage-card{
        display:flex;
        flex-direction:column;
        align-items:stretch;
        justify-content:flex-start;
        min-height: 0;
        height: 100%;
        border-radius: .5rem;
        overflow-y:auto;
    }
    body.stage-only-mode #stage-slot{
        display:flex;
        flex-direction:column;
        width:100%;
        flex:1;
    }
    body.stage-only-mode #stage-placeholder{
        margin:auto;
    }

    /* kartu pemenang jadi horizontal: foto di kiri, NPK/nama/dept di kanan */
    body.stage-only-mode .slot-card{
        display:flex;
        align-items:center;
        text-align:left;
        gap: 16px;
        width: 100%;
        padding: 16px 20px;
    }
    body.stage-only-mode .slot-info{
        display:flex;
        flex-direction:column;
        align-items:flex-start;
        gap:.35rem;
        min-width:0;
    }
    body.stage-only-mode .slot-photo{ width: 90px; height: 90px; flex-shrink:0; }
    body.stage-only-mode .slot-badge-npk{ margin-top:0; font-size: 1.05rem; }
    body.stage-only-mode .slot-name{ margin-top:0 !important; font-size: 1rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
    body.stage-only-mode .slot-department{ font-size: .82rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; }

    /* grid multi-kolom yang menyesuaikan lebar area panggung (2/3/4 kolom otomatis) */
    body.stage-only-mode .stage-slot-grid{
        display:grid !important;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        justify-content: stretch;
        align-content: start;
        gap: 14px;
        max-width: none;
        margin: 0;
        width:100%;
        flex: none;
    }

    /* panel Pemenang Ronde Ini jadi kolom kanan yang sempit & bisa discroll (grid item, bukan flex item) */
    body.stage-only-mode #round-result{
        max-width: none;
        margin:0 !important;
        width:100%;
        min-width: 0;
        height:100%;
        display:flex;
        flex-direction:column;
        overflow-y:auto;
        border-left: 1px solid rgba(255,255,255,.08);
        padding-left: 1rem;
    }
    body.stage-only-mode #round-result h6{ color:#fff; flex-shrink:0; }
    body.stage-only-mode #round-result-list{
        overflow-y:auto;
        flex:1;
        min-height:0;
    }
    body.stage-only-mode #round-result-list .round-result-item{
        flex-direction:row;
        align-items:center;
        gap:.55rem;
        padding:.4rem .55rem;
    }
    body.stage-only-mode #round-result-list .round-result-item img{
        width:32px; height:32px;
    }
    body.stage-only-mode #round-result-empty{ color: rgba(255,255,255,.5); }

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
                    </div>

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
                                    <button type="button" id="btn-open-stage-window" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt mr-1"></i> Buka Fullscreen
                                    </button>
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

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <script>
    $(function () {
        const params = new URLSearchParams(window.location.search);
        const isStageOnly = params.get('panggung') === '1';
        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : null;

        // Channel untuk sinkronisasi tab utama <-> tab panggung (fullscreen)
        const bc = ('BroadcastChannel' in window) ? new BroadcastChannel('doorprize-undian-stage') : null;

        const defaultPhoto = "{{ asset('storage/img/profile/default.jpg') }}";

        // Kumpulan NPK dummy untuk efek acak visual sebelum hasil asli muncul.
        let dummyPool = [];
        function buildDummyPool() {
            dummyPool = [];
            $('#winners-table tbody tr').each(function () {
                const npk = $(this).find('td').eq(1).text().trim();
                if (npk) dummyPool.push(npk);
            });
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
        // MODE PANGGUNG SAJA (dibuka di tab/jendela terpisah via ?panggung=1)
        // =====================================================================
        if (isStageOnly) {
            $('body').addClass('stage-only-mode');

            // Buang semua elemen layout lain, sisakan hanya kartu Panggung Undian, full screen.
            const $stageCard = $('#panggung-undian-card').detach();
            $('body').empty().append($stageCard);
            $('body').append(
                '<button id="btn-toggle-fullscreen" class="btn btn-sm btn-light shadow">' +
                '<i class="fas fa-expand mr-1"></i> Fullscreen</button>'
            );

            $(document).on('click', '#btn-toggle-fullscreen', function () {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(() => {});
                    $(this).html('<i class="fas fa-compress mr-1"></i> Keluar Fullscreen');
                } else {
                    document.exitFullscreen();
                    $(this).html('<i class="fas fa-expand mr-1"></i> Fullscreen');
                }
            });

            buildDummyPool();

            // Tampilkan panel daftar pemenang sejak awal (kosong) supaya layout 3:1 sudah terbentuk
            $('#round-result').removeClass('d-none');
            $('#round-result-list').html('<p class="text-muted small mb-0" id="round-result-empty">Menunggu hasil undian...</p>');

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
                    '<p class="mb-0">Browser ini tidak mendukung sinkronisasi otomatis (BroadcastChannel). Gunakan browser modern seperti Chrome/Edge terbaru.</p>'
                );
            }

            return; // mode panggung tidak butuh tabel, KPI, atau tombol undian
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

        // Buka Panggung Undian di tab/jendela baru, siap fullscreen (mis. untuk layar proyektor).
        $('#btn-open-stage-window').on('click', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('panggung', '1');
            window.open(url.toString(), '_blank', 'noopener');
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

                    // Beritahu tab panggung agar mengungkap hasil yang sama, bersamaan.
                    if (bc) bc.postMessage({ type: 'draw-reveal', winners: data.winners, batchLabel });

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