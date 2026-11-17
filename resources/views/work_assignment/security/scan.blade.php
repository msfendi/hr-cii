<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<style>
    body {
        font-family: 'Nunito', sans-serif;
        background: #eef2f8;
        min-height: 100vh;
        margin: 0;
    }

    .sg-top {
        background: #1b3a6b;
        color: #fff;
        padding: .7rem 1.1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        flex-wrap: wrap;
    }

    .sg-top .brand {
        display: flex;
        align-items: center;
        gap: .55rem;
        font-weight: 700;
        font-size: .95rem;
    }

    .sg-top .brand img { height: 26px; width: auto; }

    .sg-top .who {
        display: flex;
        align-items: center;
        gap: .7rem;
        font-size: .82rem;
    }

    .sg-top .who .badge {
        background: rgba(255, 255, 255, .16);
        border: 1px solid rgba(255, 255, 255, .3);
        border-radius: 999px;
        padding: .22rem .62rem;
        font-weight: 600;
    }

    .sg-wrap {
        max-width: 980px;
        margin: 1.1rem auto 2rem;
        padding: 0 1rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        align-items: start;
    }

    .sg-card {
        background: #fff;
        border-radius: .75rem;
        box-shadow: 0 2px 10px rgba(27, 58, 107, .1);
        padding: 1rem 1.1rem;
    }

    .sg-card h5 {
        font-size: .95rem;
        font-weight: 700;
        color: #1b3a6b;
        margin: 0 0 .15rem;
    }

    .sg-card .sub {
        font-size: .78rem;
        color: #71809a;
        margin-bottom: .85rem;
    }

    #sg-reader {
        width: 100%;
        border-radius: .55rem;
        overflow: hidden;
        background: #0e1a2b;
        min-height: 220px;
    }

    #sg-reader video { border-radius: .55rem; }

    .sg-or {
        text-align: center;
        font-size: .72rem;
        color: #9aa7bd;
        margin: .7rem 0 .55rem;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .sg-input-row { display: flex; gap: .5rem; }

    .sg-input-row input {
        flex: 1;
        border: 1px solid #cfd9e8;
        border-radius: .5rem;
        padding: .5rem .7rem;
        font-size: .88rem;
    }

    .sg-input-row input:focus { outline: 2px solid #bcd2f2; border-color: #7ba4dd; }

    .sg-btn {
        border: 0;
        border-radius: .5rem;
        padding: .5rem .9rem;
        font-size: .86rem;
        font-weight: 700;
        cursor: pointer;
        background: #1b3a6b;
        color: #fff;
        white-space: nowrap;
    }

    .sg-btn:hover { background: #14304f; }
    .sg-btn:disabled { background: #b9c4d4; cursor: not-allowed; }

    .sg-btn.ghost {
        background: #eef3fb;
        color: #1b3a6b;
        border: 1px solid #cfdcef;
    }

    .sg-btn.ok { background: #1d7a4c; }
    .sg-btn.ok:hover { background: #166039; }

    .sg-msg {
        border-radius: .5rem;
        padding: .6rem .8rem;
        font-size: .84rem;
        margin-top: .8rem;
        display: none;
    }

    .sg-msg.show { display: block; }
    .sg-msg.err { background: #fdecec; border: 1px solid #f5c2c2; color: #a4302c; }
    .sg-msg.good { background: #e8f7ee; border: 1px solid #b8e3c9; color: #17603a; }

    .sg-note {
        border-radius: .5rem;
        padding: .55rem .7rem;
        font-size: .79rem;
        line-height: 1.55;
        margin-bottom: .8rem;
        background: #fff8e6;
        border: 1px solid #f0dfae;
        color: #7a5f10;
    }

    .sg-note b { color: #6a5208; }
    .sg-note[hidden] { display: none; }

    .sg-empty {
        text-align: center;
        color: #8b98ad;
        font-size: .84rem;
        padding: 2rem .8rem;
        line-height: 1.6;
    }

    .sg-empty i { font-size: 1.7rem; display: block; margin-bottom: .5rem; color: #c2cddd; }

    .sg-rows { display: grid; gap: .1rem; }

    .sg-row {
        display: flex;
        justify-content: space-between;
        gap: .8rem;
        padding: .42rem 0;
        border-bottom: 1px dashed #e6ecf5;
        font-size: .855rem;
    }

    .sg-row:last-child { border-bottom: 0; }
    .sg-row .k { color: #71809a; flex: 0 0 42%; }
    .sg-row .v { font-weight: 600; color: #22344d; text-align: right; }

    .sg-sec {
        margin-top: .9rem;
        padding-top: .8rem;
        border-top: 2px solid #eef2f8;
    }

    .sg-sec h6 {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #71809a;
        font-weight: 700;
        margin: 0 0 .5rem;
    }

    .sg-gate {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .5rem .7rem;
        border-radius: .5rem;
        border: 1px solid #e3eaf5;
        margin-bottom: .45rem;
        font-size: .84rem;
    }

    .sg-gate.done { background: #f2f8f4; border-color: #cbe7d6; }
    .sg-gate.wait { background: #fbfaf3; border-color: #eee3bd; }

    .sg-gate .ic {
        width: 26px; height: 26px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; color: #fff; flex: 0 0 26px;
    }

    .sg-gate.done .ic { background: #1d7a4c; }
    .sg-gate.wait .ic { background: #c9a227; }

    .sg-gate .tx { flex: 1; }
    .sg-gate .tx b { display: block; font-size: .84rem; }
    .sg-gate .tx small { color: #71809a; font-size: .75rem; }

    .sg-tag {
        display: inline-block;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .03em;
        padding: .16rem .5rem;
        border-radius: 999px;
    }

    .sg-tag.ok { background: #dff2e6; color: #17603a; }
    .sg-tag.warn { background: #fdf3d8; color: #8a6d10; }
    .sg-tag.off { background: #f0f2f6; color: #6b7688; }

    .sg-actions { margin-top: 1rem; display: flex; gap: .55rem; flex-wrap: wrap; }

    .sg-full { grid-column: 1 / -1; }

    @media (max-width: 820px) {
        .sg-wrap { grid-template-columns: 1fr; }
        .sg-top { padding: .6rem .8rem; }
        .sg-top .who { font-size: .78rem; }
    }
</style>

<body>

<div class="sg-top">
    <div class="brand">
        <i class="fas fa-user-shield"></i>
        <span>Security Gate &mdash; Surat Ijin Dinas Luar</span>
    </div>
    <div class="who">
        <span class="badge"><i class="fas fa-id-badge mr-1"></i>{{ $securityName }} &middot; {{ $securityNpk }}</span>
        <a href="{{ route('work-assignment.security.logout') }}"
           class="btn btn-sm btn-outline-light rounded-pill">
            <i class="fas fa-sign-out-alt mr-1"></i>Keluar
        </a>
    </div>
</div>

<div class="sg-wrap">

    {{-- ── Kolom scan ── --}}
    <div class="sg-card">
        <h5>Scan QR Pengajuan</h5>
        <div class="sub">Arahkan kamera ke QR di halaman riwayat karyawan.</div>

        <div class="sg-note" id="sg-env-note" hidden></div>

        <div id="sg-reader"></div>

        <div class="sg-or">atau ketik kode manual</div>

        <div class="sg-input-row">
            <input type="text" id="sg-code" placeholder="WA:123:token atau 123" autocomplete="off"
                   inputmode="text">
            <button type="button" class="sg-btn" id="sg-search">
                <i class="fas fa-search mr-1"></i>Cari
            </button>
        </div>

        <div class="sg-msg" id="sg-scan-msg"></div>

        <div class="sg-actions">
            <button type="button" class="sg-btn ghost" id="sg-cam-toggle">
                <i class="fas fa-camera mr-1"></i>Nyalakan Kamera
            </button>
        </div>

        <div class="sg-sec">
            <h6>Petunjuk</h6>
            <div class="sg-row"><span class="k">Scan pertama</span><span class="v">Karyawan KELUAR</span></div>
            <div class="sg-row"><span class="k">Scan berikutnya</span><span class="v">Karyawan KEMBALI</span></div>
            <div class="sg-row"><span class="k">Jam kembali</span><span class="v">Terisi otomatis</span></div>
            <div class="sg-row"><span class="k">Scanner QR USB</span><span class="v">Langsung di kolom kode</span></div>
        </div>
    </div>

    {{-- ── Kolom hasil ── --}}
    <div class="sg-card" id="sg-result">
        <h5>Hasil Scan</h5>
        <div class="sub">Data pengajuan dan status gate muncul di sini.</div>

        <div class="sg-empty" id="sg-empty">
            <i class="fas fa-qrcode"></i>
            Belum ada pengajuan.<br>Scan QR atau ketik kode untuk memulai.
        </div>

        <div id="sg-body" style="display:none;"></div>
    </div>

</div>

@include('layout.footerscript')

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const endpoints = {
        lookup:  @json(route('work-assignment.security.lookup')),
        approve: @json(route('work-assignment.security.approve')),
    };

    const $code   = document.getElementById('sg-code');
    const $search = document.getElementById('sg-search');
    const $empty  = document.getElementById('sg-empty');
    const $body   = document.getElementById('sg-body');
    const $scanMsg = document.getElementById('sg-scan-msg');

    let current = null;
    let currentCode = '';
    let scanner = null;
    let scanning = false;

    function showMsg(kind, text) {
        $scanMsg.className = 'sg-msg show ' + kind;
        $scanMsg.textContent = text;
    }

    function hideMsg() {
        $scanMsg.className = 'sg-msg';
        $scanMsg.textContent = '';
    }

    function esc(v) {
        return String(v === null || v === undefined ? '-' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function gateRow(label, data, stageName) {
        if (data && data.status === 'approved') {
            return '<div class="sg-gate done">'
                + '<span class="ic"><i class="fas fa-check"></i></span>'
                + '<span class="tx"><b>' + label + ' &mdash; ' + esc(data.name || data.npk) + '</b>'
                + '<small>NPK ' + esc(data.npk) + ' &middot; ' + esc(data.at) + '</small></span>'
                + '<span class="sg-tag ok">Tercatat</span>'
                + '</div>';
        }

        return '<div class="sg-gate wait">'
            + '<span class="ic"><i class="fas fa-hourglass-half"></i></span>'
            + '<span class="tx"><b>' + label + ' &mdash; belum tercatat</b>'
            + '<small>Menunggu scan security</small></span>'
            + '<span class="sg-tag warn">Menunggu</span>'
            + '</div>';
    }

    function render(r) {
        current = r;

        const ready = r.approved_all;
        const stage = r.stage;

        let actionHtml = '';

        if (!ready) {
            actionHtml = '<div class="sg-actions">'
                + '<span class="sg-tag off">Belum disetujui semua level &mdash; belum bisa diproses</span>'
                + '</div>';
        } else if (stage === null) {
            actionHtml = '<div class="sg-actions">'
                + '<span class="sg-tag ok">Pengajuan selesai &mdash; keluar &amp; kembali sudah tercatat</span>'
                + '</div>';
        } else {
            const label = stage === 'out' ? 'Konfirmasi KELUAR' : 'Konfirmasi KEMBALI';
            const icon = stage === 'out' ? 'fa-sign-out-alt' : 'fa-sign-in-alt';
            actionHtml = '<div class="sg-actions">'
                + '<button type="button" class="sg-btn ok" id="sg-approve">'
                + '<i class="fas ' + icon + ' mr-1"></i>' + label + '</button>'
                + '</div>';
        }

        $body.innerHTML =
            '<div class="sg-rows">'
            + '<div class="sg-row"><span class="k">No. Pengajuan</span><span class="v">#' + esc(r.id) + '</span></div>'
            + '<div class="sg-row"><span class="k">Pemohon</span><span class="v">' + esc(r.nama) + '</span></div>'
            + '<div class="sg-row"><span class="k">NPK</span><span class="v">' + esc(r.npk) + '</span></div>'
            + '<div class="sg-row"><span class="k">Dinas Ke</span><span class="v">' + esc(r.dinas_ke) + '</span></div>'
            + '<div class="sg-row"><span class="k">Keperluan</span><span class="v">' + esc(r.keperluan) + '</span></div>'
            + '<div class="sg-row"><span class="k">Tanggal Dinas</span><span class="v">'
                + esc(r.start_date) + (r.end_date && r.end_date !== r.start_date ? ' &ndash; ' + esc(r.end_date) : '')
                + ' (' + esc(r.total_days) + ' hari)</span></div>'
            + '<div class="sg-row"><span class="k">Jam Keluar (rencana)</span><span class="v">' + esc(r.jam_keluar) + '</span></div>'
            + '<div class="sg-row"><span class="k">Rencana Kembali</span><span class="v">' + esc(r.rencana_kembali) + '</span></div>'
            + '<div class="sg-row"><span class="k">Jam Kembali (aktual)</span><span class="v">' + esc(r.jam_kembali) + '</span></div>'
            + '<div class="sg-row"><span class="k">Status Approval</span><span class="v">'
                + (ready ? '<span class="sg-tag ok">Disetujui semua level</span>'
                        : '<span class="sg-tag warn">Belum lengkap</span>') + '</span></div>'
            + '</div>'
            + '<div class="sg-sec">'
            + '<h6>Gate Check Security</h6>'
            + gateRow('Keluar', r.out)
            + gateRow('Kembali', r['in'])
            + '</div>'
            + actionHtml;

        $empty.style.display = 'none';
        $body.style.display = 'block';

        const btn = document.getElementById('sg-approve');
        if (btn) {
            btn.addEventListener('click', approve);
        }
    }

    async function lookup(code) {
        if (!code) {
            showMsg('err', 'Kode QR kosong.');
            return;
        }

        hideMsg();
        $search.disabled = true;

        try {
            const res = await fetch(endpoints.lookup, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code: code }),
            });

            const data = await res.json();

            if (!res.ok || !data.ok) {
                showMsg('err', data.message || 'Pengajuan tidak ditemukan.');
                return;
            }

            render(data.request);
            currentCode = code;
            showMsg('good', 'Pengajuan #' + data.request.id + ' ditemukan.');
        } catch (e) {
            showMsg('err', 'Gagal menghubungi server.');
        } finally {
            $search.disabled = false;
        }
    }

    async function approve() {
        if (!current) { return; }

        const btn = document.getElementById('sg-approve');
        if (btn) { btn.disabled = true; }

        try {
            const res = await fetch(endpoints.approve, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ code: currentCode }),
            });

            const data = await res.json();

            if (!res.ok || !data.ok) {
                showMsg('err', data.message || 'Gagal menyimpan.');
                if (btn) { btn.disabled = false; }
                return;
            }

            render(data.request);
            showMsg('good', data.message);
            $code.value = '';
        } catch (e) {
            showMsg('err', 'Gagal menyimpan.');
            if (btn) { btn.disabled = false; }
        }
    }

    $search.addEventListener('click', () => lookup($code.value.trim()));
    $code.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); lookup($code.value.trim()); }
    });

    // ── Kamera ──
    const $camToggle = document.getElementById('sg-cam-toggle');

    /**
     * Kenapa kamera bisa tidak tersedia:
     *  - 'insecure'  : halaman dibuka lewat http:// di IP LAN. Browser tidak
     *                  pernah menampilkan prompt izin dan menyembunyikan
     *                  navigator.mediaDevices — ini penyebab paling sering.
     *  - 'nomediadevices' : browser lama / WebView tanpa dukungan getUserMedia.
     *  - 'ok'
     */
    function cameraEnv() {
        if (!window.isSecureContext) {
            return 'insecure';
        }

        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            return 'nomediadevices';
        }

        return 'ok';
    }

    function explainCameraError(e) {
        const name = (e && (e.name || (e.cause && e.cause.name))) || '';

        switch (name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                return 'Izin kamera ditolak. Klik ikon kunci/kamera di address bar, '
                    + 'pilih "Izinkan", lalu muat ulang halaman.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'Tidak ada kamera terdeteksi di perangkat ini. Gunakan input manual.';
            case 'NotReadableError':
            case 'TrackStartError':
                return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.';
            case 'OverconstrainedError':
                return 'Kamera tidak mendukung mode yang diminta. Coba input manual.';
            default:
                return 'Kamera tidak bisa diakses'
                    + (e && e.message ? ': ' + e.message : '. Gunakan input manual.')
                    + ' — detail: ' + (name || 'tidak diketahui');
        }
    }

    async function startCamera() {
        const env = cameraEnv();

        if (env === 'insecure') {
            showMsg('err',
                'Kamera diblokir browser karena halaman ini dibuka lewat http:// '
                + '(' + location.host + '). Kamera hanya diizinkan di HTTPS atau localhost. '
                + 'Gunakan input manual di bawah, atau minta admin mengaktifkan HTTPS.');
            $code.focus();
            return;
        }

        if (env === 'nomediadevices') {
            showMsg('err',
                'Browser ini tidak menyediakan akses kamera (getUserMedia). '
                + 'Gunakan input manual atau scanner QR USB.');
            $code.focus();
            return;
        }

        if (typeof Html5Qrcode === 'undefined') {
            showMsg('err', 'Library scanner gagal dimuat. Gunakan input manual.');
            $code.focus();
            return;
        }

        try {
            scanner = new Html5Qrcode('sg-reader');
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 220, height: 220 } },
                (text) => {
                    if (scanning) { return; }
                    scanning = true;
                    $code.value = text;
                    lookup(text);
                    setTimeout(() => { scanning = false; }, 2500);
                },
                () => {}
            );

            $camToggle.innerHTML = '<i class="fas fa-camera-slash mr-1"></i>Matikan Kamera';
            showMsg('good', 'Kamera aktif. Arahkan ke QR pengajuan.');
        } catch (e) {
            scanner = null;
            showMsg('err', explainCameraError(e));
            $code.focus();
        }
    }

    async function stopCamera() {
        if (!scanner) { return; }

        try { await scanner.stop(); await scanner.clear(); } catch (e) {}
        scanner = null;
        $camToggle.innerHTML = '<i class="fas fa-camera mr-1"></i>Nyalakan Kamera';
        hideMsg();
    }

    $camToggle.addEventListener('click', () => {
        if (scanner) { stopCamera(); } else { startCamera(); }
    });

    // ── Kondisi awal halaman ──
    (function initEnvNotice() {
        const env = cameraEnv();
        const note = document.getElementById('sg-env-note');

        if (env === 'insecure') {
            note.hidden = false;
            note.innerHTML = '<b>Kamera tidak tersedia di alamat ini.</b><br>'
                + 'Halaman dibuka lewat <code>http://' + location.host + '</code>. Browser hanya '
                + 'mengizinkan kamera pada <b>HTTPS</b> atau <b>localhost</b>, dan pada http biasa '
                + 'prompt izin memang tidak pernah muncul.<br>'
                + 'Sementara ini gunakan <b>input manual</b> di bawah (bisa juga scanner QR USB — '
                + 'alat itu mengetik kode lalu menekan Enter otomatis).';

            $camToggle.innerHTML = '<i class="fas fa-camera-slash mr-1"></i>Kamera tidak tersedia';
            $camToggle.disabled = true;
        }
    })();

    // Fokus input manual: scanner QR USB bekerja seperti keyboard (ketik + Enter).
    $code.focus();
})();
</script>

</body>
</html>