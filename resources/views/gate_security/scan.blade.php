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

    .gs-top {
        background: #1b3a6b;
        color: #fff;
        padding: .7rem 1.1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        flex-wrap: wrap;
    }

    .gs-top .brand {
        display: flex;
        align-items: center;
        gap: .55rem;
        font-weight: 700;
        font-size: .95rem;
    }

    .gs-top .who {
        display: flex;
        align-items: center;
        gap: .7rem;
        font-size: .82rem;
    }

    .gs-top .who .badge {
        background: rgba(255, 255, 255, .16);
        border: 1px solid rgba(255, 255, 255, .3);
        border-radius: 999px;
        padding: .22rem .62rem;
        font-weight: 600;
    }

    .gs-wrap {
        max-width: 980px;
        margin: 1.1rem auto 2rem;
        padding: 0 1rem;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        align-items: start;
    }

    .gs-card {
        background: #fff;
        border-radius: .75rem;
        box-shadow: 0 2px 10px rgba(27, 58, 107, .1);
        padding: 1rem 1.1rem;
    }

    .gs-card h5 {
        font-size: .95rem;
        font-weight: 700;
        color: #1b3a6b;
        margin: 0 0 .15rem;
    }

    .gs-card .sub {
        font-size: .78rem;
        color: #71809a;
        margin-bottom: .85rem;
    }

    #gs-reader {
        width: 100%;
        border-radius: .55rem;
        overflow: hidden;
        background: #0e1a2b;
        min-height: 220px;
    }

    #gs-reader video { border-radius: .55rem; }

    .gs-or {
        text-align: center;
        font-size: .72rem;
        color: #9aa7bd;
        margin: .7rem 0 .55rem;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .gs-input-row { display: flex; gap: .5rem; }

    .gs-input-row input {
        flex: 1;
        border: 1px solid #cfd9e8;
        border-radius: .5rem;
        padding: .5rem .7rem;
        font-size: .88rem;
    }

    .gs-input-row input:focus { outline: 2px solid #bcd2f2; border-color: #7ba4dd; }

    .gs-btn {
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

    .gs-btn:hover { background: #14304f; }
    .gs-btn:disabled { background: #b9c4d4; cursor: not-allowed; }

    .gs-btn.ghost {
        background: #eef3fb;
        color: #1b3a6b;
        border: 1px solid #cfdcef;
    }

    .gs-btn.ok { background: #1d7a4c; }
    .gs-btn.ok:hover { background: #166039; }

    .gs-msg {
        border-radius: .5rem;
        padding: .6rem .8rem;
        font-size: .84rem;
        margin-top: .8rem;
        display: none;
    }

    .gs-msg.show { display: block; }
    .gs-msg.err { background: #fdecec; border: 1px solid #f5c2c2; color: #a4302c; }
    .gs-msg.good { background: #e8f7ee; border: 1px solid #b8e3c9; color: #17603a; }

    .gs-note {
        border-radius: .5rem;
        padding: .55rem .7rem;
        font-size: .79rem;
        line-height: 1.55;
        margin-bottom: .8rem;
        background: #fff8e6;
        border: 1px solid #f0dfae;
        color: #7a5f10;
    }

    .gs-note b { color: #6a5208; }
    .gs-note[hidden] { display: none; }

    .gs-empty {
        text-align: center;
        color: #8b98ad;
        font-size: .84rem;
        padding: 2rem .8rem;
        line-height: 1.6;
    }

    .gs-empty i { font-size: 1.7rem; display: block; margin-bottom: .5rem; color: #c2cddd; }

    .gs-rows { display: grid; gap: .1rem; }

    .gs-row {
        display: flex;
        justify-content: space-between;
        gap: .8rem;
        padding: .42rem 0;
        border-bottom: 1px dashed #e6ecf5;
        font-size: .855rem;
    }

    .gs-row:last-child { border-bottom: 0; }
    .gs-row .k { color: #71809a; flex: 0 0 42%; }
    .gs-row .v { font-weight: 600; color: #22344d; text-align: right; }

    .gs-sec {
        margin-top: .9rem;
        padding-top: .8rem;
        border-top: 2px solid #eef2f8;
    }

    .gs-sec h6 {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #71809a;
        font-weight: 700;
        margin: 0 0 .5rem;
    }

    .gs-gate {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .5rem .7rem;
        border-radius: .5rem;
        border: 1px solid #e3eaf5;
        margin-bottom: .45rem;
        font-size: .84rem;
    }

    .gs-gate.done { background: #f2f8f4; border-color: #cbe7d6; }
    .gs-gate.wait { background: #fbfaf3; border-color: #eee3bd; }

    .gs-gate .ic {
        width: 26px; height: 26px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .72rem; color: #fff; flex: 0 0 26px;
    }

    .gs-gate.done .ic { background: #1d7a4c; }
    .gs-gate.wait .ic { background: #c9a227; }

    .gs-gate .tx { flex: 1; }
    .gs-gate .tx b { display: block; font-size: .84rem; }
    .gs-gate .tx small { color: #71809a; font-size: .75rem; }

    .gs-tag {
        display: inline-block;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .03em;
        padding: .16rem .5rem;
        border-radius: 999px;
    }

    .gs-tag.ok { background: #dff2e6; color: #17603a; }
    .gs-tag.warn { background: #fdf3d8; color: #8a6d10; }
    .gs-tag.off { background: #f0f2f6; color: #6b7688; }

    .gs-actions { margin-top: 1rem; display: flex; gap: .55rem; flex-wrap: wrap; }

    @media (max-width: 820px) {
        .gs-wrap { grid-template-columns: 1fr; }
        .gs-top { padding: .6rem .8rem; }
        .gs-top .who { font-size: .78rem; }
    }
</style>

<body>

<div class="gs-top">
    <div class="brand">
        <i class="fas fa-user-shield"></i>
        <span>Security Gate &mdash; Pemeriksaan Dokumen</span>
    </div>
    <div class="who">
        <span class="badge"><i class="fas fa-id-badge mr-1"></i>{{ $officerName }} &middot; {{ $officerNpk }}</span>
        <a href="{{ route('gate-security.logout') }}"
           class="btn btn-sm btn-outline-light rounded-pill">
            <i class="fas fa-sign-out-alt mr-1"></i>Keluar
        </a>
    </div>
</div>

<div class="gs-wrap">

    {{-- ── Kolom scan ── --}}
    <div class="gs-card">
        <h5>Scan QR Dokumen</h5>
        <div class="sub">Arahkan kamera ke QR pada halaman riwayat pengajuan.</div>

        <div class="gs-note" id="gs-env-note" hidden></div>

        <div id="gs-reader"></div>

        <div class="gs-or">atau ketik kode manual</div>

        <div class="gs-input-row">
            <input type="text" id="gs-code" placeholder="GATE:izin-dinas:10009:token" autocomplete="off">
            <button type="button" class="gs-btn" id="gs-search">
                <i class="fas fa-search mr-1"></i>Cari
            </button>
        </div>

        <div class="gs-msg" id="gs-scan-msg"></div>

        <div class="gs-actions">
            <button type="button" class="gs-btn ghost" id="gs-cam-toggle">
                <i class="fas fa-camera mr-1"></i>Nyalakan Kamera
            </button>
        </div>

        <div class="gs-sec">
            <h6>Petunjuk</h6>
            <div class="gs-row"><span class="k">Scan pertama</span><span class="v">Karyawan KELUAR</span></div>
            <div class="gs-row"><span class="k">Scan berikutnya</span><span class="v">Karyawan KEMBALI</span></div>
            <div class="gs-row"><span class="k">Jam kembali</span><span class="v">Terisi otomatis</span></div>
            <div class="gs-row"><span class="k">Scanner QR USB</span><span class="v">Langsung di kolom kode</span></div>
        </div>
    </div>

    {{-- ── Kolom hasil ── --}}
    <div class="gs-card" id="gs-result">
        <h5>Hasil Scan</h5>
        <div class="sub">Data dokumen dan status gate muncul di sini.</div>

        <div class="gs-empty" id="gs-empty">
            <i class="fas fa-qrcode"></i>
            Belum ada dokumen.<br>Scan QR atau ketik kode untuk memulai.
        </div>

        <div id="gs-body" style="display:none;"></div>
    </div>

</div>

@include('layout.footerscript')

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const endpoints = {
        lookup:  @json(route('gate-security.lookup')),
        approve: @json(route('gate-security.approve')),
    };

    const $code    = document.getElementById('gs-code');
    const $search  = document.getElementById('gs-search');
    const $empty   = document.getElementById('gs-empty');
    const $body    = document.getElementById('gs-body');
    const $scanMsg = document.getElementById('gs-scan-msg');

    let current = null;
    let currentCode = '';
    let scanner = null;
    let scanning = false;

    function showMsg(kind, text) {
        $scanMsg.className = 'gs-msg show ' + kind;
        $scanMsg.textContent = text;
    }

    function hideMsg() {
        $scanMsg.className = 'gs-msg';
        $scanMsg.textContent = '';
    }

    function esc(v) {
        return String(v === null || v === undefined ? '-' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Baris ringkasan dirender dari data dokumen — view tidak tahu kolomnya.
    function rows(list) {
        return (list || []).map(function (r) {
            return '<div class="gs-row"><span class="k">' + esc(r.label)
                + '</span><span class="v">' + esc(r.value) + '</span></div>';
        }).join('');
    }

    // Dua baris gate (keluar & kembali) — labelnya sama untuk semua dokumen.
    function gateRows(gates) {
        return gateRow('Keluar', gates && gates.out)
            + gateRow('Kembali', gates && gates['in']);
    }

    function gateRow(label, data) {
        if (data && data.status === 'approved') {
            return '<div class="gs-gate done">'
                + '<span class="ic"><i class="fas fa-check"></i></span>'
                + '<span class="tx"><b>' + label + ' &mdash; ' + esc(data.name || data.npk) + '</b>'
                + '<small>NPK ' + esc(data.npk) + ' &middot; ' + esc(data.at) + '</small></span>'
                + '<span class="gs-tag ok">Tercatat</span>'
                + '</div>';
        }

        return '<div class="gs-gate wait">'
            + '<span class="ic"><i class="fas fa-hourglass-half"></i></span>'
            + '<span class="tx"><b>' + label + ' &mdash; belum tercatat</b>'
            + '<small>Menunggu scan security</small></span>'
            + '<span class="gs-tag warn">Menunggu</span>'
            + '</div>';
    }

    function render(doc) {
        current = doc;

        let actionHtml = '';

        if (!doc.ready) {
            actionHtml = '<div class="gs-actions">'
                + '<span class="gs-tag off">Belum disetujui semua level &mdash; belum bisa diproses</span>'
                + '</div>';
        } else if (doc.action) {
            actionHtml = '<div class="gs-actions">'
                + '<button type="button" class="gs-btn ok" id="gs-approve">'
                + '<i class="fas ' + esc(doc.action.icon) + ' mr-1"></i>' + esc(doc.action.label) + '</button>'
                + '</div>';
        } else {
            actionHtml = '<div class="gs-actions">'
                + '<span class="gs-tag ok">Selesai &mdash; keluar &amp; kembali sudah tercatat</span>'
                + '</div>';
        }

        $body.innerHTML =
            '<div class="gs-rows">'
            + '<div class="gs-row"><span class="k">Jenis Dokumen</span><span class="v">' + esc(doc.doc_label) + '</span></div>'
            + '<div class="gs-row"><span class="k">No. Pengajuan</span><span class="v">#' + esc(doc.id) + '</span></div>'
            + rows(doc.summary)
            + '</div>'
            + '<div class="gs-sec">'
            + '<h6>Gate Check</h6>'
            + gateRows(doc.gates)
            + '</div>'
            + actionHtml;

        $empty.style.display = 'none';
        $body.style.display = 'block';

        const btn = document.getElementById('gs-approve');
        if (btn) { btn.addEventListener('click', approve); }
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
                showMsg('err', data.message || 'Dokumen tidak ditemukan.');
                return;
            }

            render(data.document);
            currentCode = data.document.code;
            showMsg('good', data.document.doc_label + ' #' + data.document.id + ' ditemukan.');
        } catch (e) {
            showMsg('err', 'Gagal menghubungi server.');
        } finally {
            $search.disabled = false;
        }
    }

    async function approve() {
        if (!current || !currentCode) { return; }

        const btn = document.getElementById('gs-approve');
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

            if (data.document) { render(data.document); }
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
    const $camToggle = document.getElementById('gs-cam-toggle');

    function cameraEnv() {
        if (!window.isSecureContext) { return 'insecure'; }
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
            scanner = new Html5Qrcode('gs-reader');
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
            showMsg('good', 'Kamera aktif. Arahkan ke QR dokumen.');
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
        const note = document.getElementById('gs-env-note');

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
