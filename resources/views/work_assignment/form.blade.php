<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<style>
    :root {
        --wa-primary: #4e73df;
        --wa-primary-dark: #224abe;
        --wa-soft: #f4f6fd;
        --wa-border: #e3e8f4;
        --wa-text: #2c3e50;
        --wa-muted: #6b7688;
    }

    body {
        font-family: 'Nunito', sans-serif;
        background: linear-gradient(135deg, #4e73df 0%, #224abe 55%, #1a3a8f 100%);
        min-height: 100vh;
    }

    .wa-shell {
        max-width: 1080px;
        margin: 0 auto;
        padding: 1.5rem 1rem 1.75rem;
    }

    /* ── Header ── */
    .wa-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .wa-head .brand {
        display: flex;
        align-items: center;
        gap: .75rem;
        color: #fff;
    }

    .wa-head .brand img {
        height: 40px;
        width: auto;
        filter: brightness(0) invert(1);
    }

    .wa-head .brand .t1 {
        font-weight: 800;
        font-size: .95rem;
        line-height: 1.2;
    }

    .wa-head .brand .t2 {
        font-size: .74rem;
        opacity: .85;
        line-height: 1.2;
    }

    .wa-head-actions {
        display: flex;
        gap: .5rem;
    }

    .wa-head-actions .btn {
        border-radius: 2rem;
        font-weight: 600;
        font-size: .82rem;
        padding: .45rem 1rem;
    }

    /* ── Kartu utama ── */
    .wa-card {
        background: #fff;
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 1rem 2.5rem rgba(15, 30, 80, .22);
        overflow: hidden;
    }

    .wa-card-head {
        background: var(--wa-primary);
        color: #fff;
        padding: 1.05rem 1.5rem;
    }

    .wa-card-head h4 {
        font-weight: 800;
        margin: 0;
        font-size: 1.02rem;
    }

    .wa-card-head .sub {
        font-size: .76rem;
        opacity: .88;
        margin-top: .12rem;
    }

    .wa-card-head .doc-ref {
        display: inline-block;
        background: rgba(255, 255, 255, .18);
        border-radius: 2rem;
        padding: .18rem .65rem;
        font-size: .68rem;
        font-weight: 600;
        letter-spacing: .04em;
        white-space: nowrap;
    }

    .wa-card-body {
        padding: 1.35rem 1.5rem 1.5rem;
    }

    /* ── Section ── */
    .wa-section {
        margin-bottom: 1.4rem;
    }

    .wa-section:last-of-type {
        margin-bottom: .5rem;
    }

    .wa-section-title {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--wa-primary);
        margin-bottom: .75rem;
    }

    .wa-section-title::after {
        content: '';
        flex: 1 1 auto;
        height: 1px;
        background: var(--wa-border);
    }

    .wa-section-title .step {
        width: 21px;
        height: 21px;
        border-radius: 50%;
        background: var(--wa-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .66rem;
        font-weight: 800;
        flex: 0 0 21px;
    }

    /* ── Card karyawan (COMPACT: 1 baris) ── */
    .emp-card {
        background: var(--wa-soft);
        border: 1px solid var(--wa-border);
        border-radius: .7rem;
        padding: .7rem .9rem;
        display: flex;
        align-items: center;
        gap: .9rem;
        flex-wrap: wrap;
    }

    .emp-avatar {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        border-radius: 50%;
        background: var(--wa-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: .95rem;
        text-transform: uppercase;
    }

    .emp-id {
        min-width: 0;
        flex: 0 1 auto;
    }

    .emp-name {
        font-weight: 800;
        font-size: .88rem;
        color: var(--wa-text);
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .emp-npk {
        font-size: .72rem;
        color: var(--wa-muted);
        line-height: 1.2;
    }

    /* chips: Jabatan / Bagian / Section */
    .emp-chips {
        display: flex;
        gap: .3rem;
        flex-wrap: wrap;
        flex: 1 1 auto;
        justify-content: flex-end;
    }

    .emp-chip {
        font-size: .68rem;
        font-weight: 700;
        background: #fff;
        border: 1px solid #cdd7ef;
        color: var(--wa-primary-dark);
        border-radius: 2rem;
        padding: .12rem .5rem;
        white-space: nowrap;
    }

    .emp-chip b {
        color: var(--wa-muted);
        font-weight: 600;
    }

    .emp-chip.empty {
        color: var(--wa-muted);
        border-style: dashed;
        border-color: #d8dfea;
        font-weight: 500;
        font-style: italic;
    }

    /* ── Input ── */
    .wa-label {
        font-size: .78rem;
        font-weight: 700;
        color: #3d4a5c;
        margin-bottom: .28rem;
        display: block;
    }

    .wa-label .req {
        color: #e74a3b;
        margin-left: .1rem;
    }

    .wa-label .hint {
        display: block;
        font-weight: 500;
        font-size: .71rem;
        color: var(--wa-muted);
        margin-top: .08rem;
    }

    .wa-input,
    .wa-textarea {
        border: 1px solid #d8dfee;
        border-radius: .55rem;
        padding: .55rem .8rem;
        font-size: .875rem;
        color: var(--wa-text);
        width: 100%;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
    }

    .wa-input:focus,
    .wa-textarea:focus {
        outline: 0;
        border-color: var(--wa-primary);
        box-shadow: 0 0 0 3px rgba(78, 115, 223, .14);
    }

    .wa-input[readonly].auto-field {
        background: #f8f9fc;
        border-style: dashed;
        border-color: #cdd5e5;
        color: var(--wa-muted);
    }

    .wa-input.is-invalid,
    .wa-textarea.is-invalid {
        border-color: #e74a3b;
    }

    .wa-textarea {
        resize: vertical;
        min-height: 72px;
    }

    .wa-row {
        display: flex;
        gap: .5rem;
        align-items: stretch;
    }

    .wa-row .wa-input {
        flex: 1 1 auto;
    }

    .wa-row .btn {
        border-radius: .55rem;
        padding: .55rem 1.05rem;
        font-size: .85rem;
        font-weight: 600;
        flex: 0 0 auto;
        background: var(--wa-primary);
        border-color: var(--wa-primary);
    }

    /* ── Chip tanggal ── */
    .date-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .55rem;
    }

    .date-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        background: #fff;
        border: 1px solid #cdd7ef;
        color: var(--wa-primary-dark);
        border-radius: 2rem;
        padding: .18rem .5rem .18rem .65rem;
        font-size: .74rem;
        font-weight: 700;
    }

    .date-chip .x {
        cursor: pointer;
        color: #98a4bb;
        font-size: .82rem;
        line-height: 1;
        border: 0;
        background: 0;
        padding: 0 .05rem;
    }

    .date-chip .x:hover {
        color: #e74a3b;
    }

    .date-empty {
        font-size: .75rem;
        color: var(--wa-muted);
        font-style: italic;
        margin-top: .5rem;
    }

    .auto-badge {
        display: inline-block;
        font-size: .62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #7a869a;
        background: #eef1f7;
        border-radius: 2rem;
        padding: .05rem .4rem;
        margin-left: .35rem;
    }

    /* ── Panel info ── */
    .wa-note {
        background: #fffaf0;
        border: 1px solid #fde68a;
        border-radius: .6rem;
        padding: .65rem .85rem;
        font-size: .77rem;
        color: #78350f;
    }

    .wa-note.info {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    .wa-note .t {
        font-weight: 800;
        display: block;
        margin-bottom: .12rem;
    }

    /* ── Alur approval ── */
    .flow {
        display: flex;
        align-items: stretch;
        flex-wrap: wrap;
    }

    .flow-step {
        flex: 1 1 200px;
        background: #fff;
        border: 1px solid var(--wa-border);
        border-radius: .7rem;
        padding: .7rem .85rem;
        margin-bottom: .5rem;
        min-height: 92px;
        display: flex;
        flex-direction: column;
    }

    .flow-step .lv {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 21px;
        height: 21px;
        border-radius: 50%;
        background: var(--wa-primary);
        color: #fff;
        font-size: .64rem;
        font-weight: 800;
        margin-right: .35rem;
        vertical-align: middle;
    }

    .flow-step .role {
        font-size: .64rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--wa-primary);
        vertical-align: middle;
    }

    .flow-step .who {
        font-weight: 800;
        font-size: .84rem;
        color: var(--wa-text);
        margin-top: .3rem;
    }

    .flow-step .npk {
        font-size: .71rem;
        color: var(--wa-muted);
        margin-top: auto;
        padding-top: .2rem;
    }

    .flow-arrow {
        align-self: center;
        color: #c3ccdd;
        font-size: .85rem;
        padding: 0 .55rem;
        margin-bottom: .5rem;
    }

    @media (max-width: 767px) {
        .flow-arrow {
            display: none;
        }
    }

    /* ── Footer aksi ── */
    .wa-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        border-top: 1px solid var(--wa-border);
        padding: 1.05rem 0 .2rem;
        margin-top: .2rem;
    }

    .wa-actions .btn {
        border-radius: 2rem;
        font-weight: 700;
        font-size: .85rem;
        padding: .6rem 1.5rem;
        background: var(--wa-primary);
        border-color: var(--wa-primary);
    }

    .wa-actions .btn:hover {
        background: var(--wa-primary-dark);
        border-color: var(--wa-primary-dark);
    }

    .wa-actions .summary {
        font-size: .76rem;
        color: var(--wa-muted);
    }

    .is-loading {
        opacity: .6;
        pointer-events: none;
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
            <a href="{{ route('work-assignment.logout') }}" class="btn btn-outline-light">
                <i class="fas fa-sign-out-alt mr-1"></i> Keluar
            </a>
        </div>
    </div>

    @include('sweetalert::alert')

    <form id="form-wa" method="POST" action="{{ route('work-assignment.submit') }}">
        @csrf

        <div class="wa-card">
            <div class="wa-card-head">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h4>Work Assignment Request</h4>
                        <div class="sub">Surat Ijin Dinas Luar &mdash; isi data dinas, sisanya otomatis dari data karyawan.</div>
                    </div>
                    <span class="doc-ref">FORM CII.FM.HR.02.08 &middot; REV 00</span>
                </div>
            </div>

            <div class="wa-card-body">

                {{-- ══ Data Pemohon (compact 1 baris) ══ --}}
                <div class="wa-section">
                    <div class="wa-section-title">
                        <span class="step"><i class="fas fa-user"></i></span>
                        Data Pemohon
                    </div>

                    <div class="emp-card">
                        <div class="emp-avatar" id="emp-avatar"><?php echo strtoupper(mb_substr($employee['nama'] ?? '?', 0, 1)); ?></div>

                        <div class="emp-id">
                            <div class="emp-name" id="f-nama">{{ $employee['nama'] ?? '-' }}</div>
                            <div class="emp-npk" id="f-npk">{{ $employee['npk'] ?? '-' }}</div>
                        </div>

                        <div class="emp-chips">
                            <span class="emp-chip" id="f-jabatan"><b>Jabatan/Dept:</b> {{ $employee['jabatan'] ?? '-' }}</span>
                            <span class="emp-chip" id="f-bagian"><b>Bagian:</b> {{ $employee['bagian'] ?? '-' }}</span>
                            <span class="emp-chip" id="f-section"><b>Section:</b> {{ $employee['section'] ?? '-' }}</span>
                        </div>
                    </div>

                    <div class="wa-row mt-2">
                        <input type="text" id="lookup-npk" class="wa-input"
                               placeholder="Masukkan NPK lalu klik Cari"
                               value="{{ $employee['npk'] ?? '' }}" autocomplete="off">
                        <button type="button" id="btn-lookup" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i> Cari
                        </button>
                    </div>
                    <div id="lookup-msg" class="small mt-1"></div>
                </div>

                {{-- ══ Detail Dinas ══ --}}
                <div class="wa-section">
                    <div class="wa-section-title">
                        <span class="step">1</span>
                        Detail Dinas
                    </div>

                    <div class="mb-3">
                        <label class="wa-label" for="f-date">
                            Tanggal Dinas <span class="req">*</span>
                            <span class="hint">Bisa lebih dari satu tanggal &mdash; klik Tambah Tanggal untuk menambah.</span>
                        </label>

                        <div class="wa-row">
                            <input type="date" id="f-date" class="wa-input"
                                   min="{{ $minDate }}" max="{{ $maxDate }}"
                                   value="{{ old('duty_dates.0', now()->format('Y-m-d')) }}">
                            <button type="button" id="btn-add-date" class="btn btn-primary">
                                <i class="fas fa-plus mr-1"></i> Tambah Tanggal
                            </button>
                        </div>

                        <div class="date-chips" id="date-chips"></div>
                        <div class="date-empty" id="date-empty">Belum ada tanggal dipilih.</div>

                        <small class="text-muted d-block mt-1">
                            Periode pengajuan:
                            {{ \Carbon\Carbon::parse($minDate)->format('d/m/Y') }}
                            s/d {{ \Carbon\Carbon::parse($maxDate)->format('d/m/Y') }}
                            &middot; maksimal {{ $maxDates }} tanggal
                        </small>
                        @error('duty_dates') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        @error('duty_dates.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="wa-label" for="f-dinas-ke">
                                Dinas Ke <span class="req">*</span>
                                <span class="hint">Tujuan / instansi yang dikunjungi</span>
                            </label>
                            <input type="text" id="f-dinas-ke" name="dinas_ke"
                                   class="wa-input @error('dinas_ke') is-invalid @enderror"
                                   maxlength="255" value="{{ old('dinas_ke') }}"
                                   placeholder="Contoh: Kantor Pusat Jakarta" required>
                            @error('dinas_ke') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-7 mb-3">
                            <label class="wa-label" for="f-keperluan">
                                Keperluan <span class="req">*</span>
                                <span class="hint">Uraian singkat keperluan dinas luar</span>
                            </label>
                            <textarea id="f-keperluan" name="keperluan" rows="2" maxlength="2000"
                                      class="wa-textarea @error('keperluan') is-invalid @enderror"
                                      placeholder="Contoh: Rapat dengan klien terkait perpanjangan kontrak"
                                      required>{{ old('keperluan') }}</textarea>
                            @error('keperluan') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- ══ Waktu ══ --}}
                <div class="wa-section">
                    <div class="wa-section-title">
                        <span class="step">2</span>
                        Waktu
                    </div>

                    <div class="row">
                        <div class="col-6 col-lg-4 mb-3">
                            <label class="wa-label" for="f-jam-keluar">
                                Jam Keluar <span class="req">*</span>
                                <span class="hint">WIB</span>
                            </label>
                            <input type="time" id="f-jam-keluar" name="jam_keluar"
                                   class="wa-input @error('jam_keluar') is-invalid @enderror"
                                   value="{{ old('jam_keluar') }}" required>
                            @error('jam_keluar') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-6 col-lg-4 mb-3">
                            <label class="wa-label" for="f-rencana-kembali">
                                Rencana Kembali <span class="req">*</span>
                                <span class="hint">WIB &middot; diisi pemohon</span>
                            </label>
                            <input type="time" id="f-rencana-kembali" name="rencana_kembali"
                                   class="wa-input @error('rencana_kembali') is-invalid @enderror"
                                   value="{{ old('rencana_kembali') }}" required>
                            @error('rencana_kembali') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-lg-4 mb-3">
                            <label class="wa-label" for="f-jam-kembali">
                                Jam Kembali
                                <span class="auto-badge">security</span>
                                <span class="hint">Diisi Security</span>
                            </label>
                            <input type="time" id="f-jam-kembali" class="wa-input auto-field" value="" readonly>
                            <small class="text-muted d-block mt-1" style="font-size:.7rem">
                                Setelah karyawan kembali dari dinas &amp; masuk area factory.
                            </small>
                        </div>
                    </div>

                    <div class="wa-note">
                        <span class="t"><i class="fas fa-info-circle mr-1"></i> Waktu dalam WIB</span>
                        Jam keluar &amp; rencana kembali diisi pemohon &mdash; diverifikasi Departemen.
                        Jam kembali diisi Security setelah karyawan benar-benar kembali (manual, atau check-in/out GPS karyawan).
                    </div>
                </div>

                {{-- ══ Alur Persetujuan ══ --}}
                <div class="wa-section mb-0">
                    <div class="wa-section-title">
                        <span class="step"><i class="fas fa-sitemap"></i></span>
                        Alur Persetujuan
                        <span class="badge badge-warning" style="font-size:.6rem">DRAFT</span>
                    </div>

                    @if (!empty($chain))
                        <div class="flow">
                            @foreach ($chain as $i => $step)
                                @if ($i > 0)
                                    <div class="flow-arrow"><i class="fas fa-chevron-right"></i></div>
                                @endif
                                <div class="flow-step">
                                    <div>
                                        <span class="lv">{{ $step['level'] }}</span>
                                        <span class="role">{{ $step['role_label'] }}</span>
                                    </div>
                                    <div class="who">{{ $step['name'] ?: '—' }}</div>
                                    <div class="npk">
                                        @if (!empty($step['npk']))
                                            {{ $step['npk'] }}
                                        @else
                                            <span class="text-warning">NPK belum diset</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="wa-note info mt-2">
                            <span class="t"><i class="fas fa-user-check mr-1"></i> Mekanisme belum final</span>
                            Urutan &amp; siapa yang berhak menyetujui masih menunggu konfirmasi.
                            Struktur data sudah mendukung snapshot chain + jawaban per level.
                        </div>
                    @else
                        <div class="wa-note">
                            <span class="t"><i class="fas fa-exclamation-triangle mr-1"></i> Alur persetujuan belum terdaftar</span>
                            Departemen ini belum punya grup di <code>approval_depts</code> / <code>approval_rules</code>.
                            Pengajuan tetap bisa dikirim, tetapi chain approval akan kosong.
                        </div>
                    @endif
                </div>

                {{-- ══ Aksi ══ --}}
                <div class="wa-actions">
                    <div class="summary" id="ringkasan">
                        <i class="fas fa-info-circle mr-1"></i>
                        Pastikan semua data sudah benar sebelum mengirim.
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-1"></i> Ajukan Work Assignment
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@include('layout.footerscript')

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const urlLookup = @json(route('work-assignment.lookup-npk'));
    const urlShift  = @json(route('work-assignment.lookup-shift'));
    const MAX_DATES = @json($maxDates);

    const $npk       = document.getElementById('lookup-npk');
    const $btn       = document.getElementById('btn-lookup');
    const $msg       = document.getElementById('lookup-msg');
    const $dateInput = document.getElementById('f-date');
    const $addDate   = document.getElementById('btn-add-date');
    const $chips     = document.getElementById('date-chips');
    const $empty     = document.getElementById('date-empty');
    const $ringkasan = document.getElementById('ringkasan');
    const $avatar    = document.getElementById('emp-avatar');

    let dates = [];

    function setMsg(text, type) {
        $msg.className = 'small mt-1 ' + (type === 'danger' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-muted'));
        $msg.textContent = text || '';
    }

    function setChip(id, label, value) {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<b>' + label + ':</b> ' + (value ? escapeHtml(value) : '-');
        el.classList.toggle('empty', !value);
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    // ── Chip tanggal ──
    function renderDates() {
        $chips.innerHTML = '';
        $empty.style.display = dates.length ? 'none' : 'block';

        dates.forEach(function (d, idx) {
            const chip = document.createElement('span');
            chip.className = 'date-chip';

            const label = new Date(d + 'T00:00:00')
                .toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

            chip.innerHTML = escapeHtml(label);

            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'x';
            x.innerHTML = '&times;';
            x.title = 'Hapus tanggal ini';
            x.addEventListener('click', function () {
                dates.splice(idx, 1);
                renderDates();
            });
            chip.appendChild(x);

            $chips.appendChild(chip);
        });

        updateRingkasan();
    }

    function addDate() {
        const val = $dateInput.value;
        if (!val) { setMsg('Pilih tanggal dulu.', 'danger'); return; }

        if (dates.indexOf(val) !== -1) { setMsg('Tanggal itu sudah ditambahkan.', 'danger'); return; }

        if (dates.length >= MAX_DATES) {
            setMsg('Maksimal ' + MAX_DATES + ' tanggal per pengajuan.', 'danger');
            return;
        }

        dates.push(val);
        dates.sort();
        renderDates();
        setMsg('');
    }

    function updateRingkasan() {
        let bagian = '<i class="fas fa-calendar-check mr-1"></i>';

        if (dates.length === 0) {
            bagian += 'Belum ada tanggal dipilih.';
        } else if (dates.length === 1) {
            bagian += dates[0];
        } else {
            bagian += dates.length + ' tanggal (' + dates[0] + ' s/d ' + dates[dates.length - 1] + ')';
        }

        const keluar  = document.getElementById('f-jam-keluar').value;
        const kembali = document.getElementById('f-rencana-kembali').value;
        if (keluar || kembali) {
            bagian += ' &middot; ' + (keluar || '--:--') + ' s/d ' + (kembali || '--:--') + ' WIB';
        }

        $ringkasan.innerHTML = bagian;
    }

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(body)
        });
        return res.json();
    }

    async function lookup() {
        const npk = ($npk.value || '').trim();
        if (!npk) { setMsg('NPK wajib diisi.', 'danger'); return; }

        $btn.classList.add('is-loading');
        setMsg('Mencari data karyawan…');

        try {
            const data = await post(urlLookup, { npk: npk, tanggal: dates[0] || null });
            if (!data.ok) { setMsg(data.message || 'NPK tidak ditemukan.', 'danger'); return; }

            const d = data.data;
            document.getElementById('f-nama').textContent = d.nama || '-';
            document.getElementById('f-npk').textContent  = d.npk || '-';
            setChip('f-jabatan', 'Jabatan/Dept', d.jabatan);
            setChip('f-bagian', 'Bagian', d.bagian);
            setChip('f-section', 'Section', d.section);

            if ($avatar) $avatar.textContent = (d.nama || '?').trim().charAt(0).toUpperCase();

            setMsg('Data karyawan ditemukan.', 'success');
        } catch (e) {
            setMsg('Gagal menghubungi server.', 'danger');
        } finally {
            $btn.classList.remove('is-loading');
        }
    }

    $btn.addEventListener('click', lookup);
    $npk.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); lookup(); }
    });
    $addDate.addEventListener('click', addDate);
    $dateInput.addEventListener('change', function () { addDate(); });
    document.getElementById('f-jam-keluar').addEventListener('change', updateRingkasan);
    document.getElementById('f-rencana-kembali').addEventListener('change', updateRingkasan);

    // ── Submit: kirim duty_dates sebagai array ke server ──
    const form = document.getElementById('form-wa');
    form.addEventListener('submit', function (e) {
        if (!dates.length) {
            e.preventDefault();
            setMsg('Pilih minimal satu tanggal dinas.', 'danger');
            $dateInput.focus();
            return;
        }

        // Sisipkan hidden input per tanggal agar Laravel dapat array
        dates.forEach(function (d) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'duty_dates[]';
            inp.value = d;
            form.appendChild(inp);
        });

        const wajib = [
            { el: document.getElementById('f-dinas-ke'),        label: 'Dinas Ke' },
            { el: document.getElementById('f-keperluan'),       label: 'Keperluan' },
            { el: document.getElementById('f-jam-keluar'),      label: 'Jam Keluar' },
            { el: document.getElementById('f-rencana-kembali'), label: 'Rencana Kembali' }
        ];

        const kosong = wajib.filter(f => !(f.el.value || '').trim());
        if (kosong.length) {
            e.preventDefault();
            setMsg('Lengkapi dulu: ' + kosong.map(f => f.label).join(', '), 'danger');
            kosong[0].el.focus();
            return;
        }

        const keluar  = document.getElementById('f-jam-keluar').value;
        const kembali = document.getElementById('f-rencana-kembali').value;
        if (keluar && kembali && kembali <= keluar) {
            e.preventDefault();
            setMsg('Rencana kembali harus setelah jam keluar.', 'danger');
            document.getElementById('f-rencana-kembali').focus();
            return;
        }

        setMsg('');
    });

    // Init: 1 tanggal default
    dates = [@json(now()->format('Y-m-d'))];
    renderDates();
    updateRingkasan();
})();
</script>

</body>
</html>
