<!DOCTYPE html>
<html lang="id">
@include('layout.header')

@include('work_assignment._styles')

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
            <a href="{{ route('work-assignment.dashboard') }}" class="btn btn-outline-light">
                <i class="fas fa-list mr-1"></i> Pengajuan Saya
            </a>
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
                            <div class="emp-npk" id="f-npk">NPK {{ $employee['npk'] ?? '-' }}</div>
                        </div>

                        <div class="emp-chips">
                            <span class="emp-chip" id="f-jabatan"><b>Jabatan/Dept:</b> {{ $employee['jabatan'] ?? '-' }}</span>
                            <span class="emp-chip" id="f-bagian"><b>Bagian:</b> {{ $employee['bagian'] ?? '-' }}</span>
                            <span class="emp-chip" id="f-section"><b>Section:</b> {{ $employee['section'] ?? '-' }}</span>
                        </div>
                    </div>

                    <div class="wa-note info mt-2">
                        <span class="t"><i class="fas fa-user-check mr-1"></i> Data pemohon terisi otomatis</span>
                        Sesuai login NPK {{ $employee['npk'] ?? '-' }} &mdash; tidak bisa diubah di halaman ini.
                    </div>

                    <div id="form-msg" class="small mt-1"></div>
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
                                        @if (!empty($step['jabatan']) && $step['jabatan'] !== $step['name'])
                                            &middot; <em>{{ $step['jabatan'] }}</em>
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
    const MAX_DATES = @json($maxDates);

    const $msg       = document.getElementById('form-msg');
    const $dateInput = document.getElementById('f-date');
    const $addDate   = document.getElementById('btn-add-date');
    const $chips     = document.getElementById('date-chips');
    const $empty     = document.getElementById('date-empty');
    const $ringkasan = document.getElementById('ringkasan');

    let dates = [];

    function setMsg(text, type) {
        $msg.className = 'small mt-1 ' + (type === 'danger' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-muted'));
        $msg.textContent = text || '';
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
