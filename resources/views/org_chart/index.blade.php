@extends('layouts.app')

@section('title', 'Organization Chart')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1 text-gray-800"><i class="fas fa-sitemap mr-2"></i>Organization Chart</h1>
            <p class="text-muted mb-0" style="font-size:0.875rem;">
                Struktur organisasi disusun dari <strong>setting manual</strong> (ID_DEPT + level).
                Karyawan aktif (PKWT.TKK kosong) tidak digambar sebagai card &mdash;
                klik card unit untuk membuka <strong>daftar karyawan unit tersebut</strong>. Unit yang punya sub-departemen tetap punya card sendiri, jadi daftar tidak pernah bercampur antar-departemen.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <a href="{{ route('org-chart.settings') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fas fa-cog mr-1"></i> Setting Org Chart
            </a>
            <button id="btnReloadChart" class="btn btn-primary btn-sm rounded-pill px-3">
                <i class="fas fa-sync-alt mr-1"></i> Muat Ulang
            </button>
            <div class="btn-group btn-group-sm" role="group" aria-label="Zoom chart">
                <button type="button" class="btn btn-outline-secondary" id="zoomOut" title="Perkecil">&minus;</button>
                <button type="button" class="btn btn-outline-secondary" id="zoomReset" title="Reset zoom">
                    <span id="zoomLabel">100%</span>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="zoomIn" title="Perbesar">+</button>
            </div>
            <span class="badge badge-info" id="empCount">0 karyawan</span>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div id="chart-container" style="width:100%; min-height:600px; overflow:auto;">
                <div class="text-center text-muted py-5" id="chart-loading">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Memuat organization chart...
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal daftar karyawan per departemen (muncul saat card unit diklik) --}}
<div class="modal fade" id="deptEmpModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div class="mr-3">
                    <h6 class="modal-title font-weight-bold mb-0" id="deptEmpTitle">Daftar Karyawan</h6>
                    <div class="emp-total" id="deptEmpSub">-</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mr-2 d-none" id="deptEmpBack">
                    <i class="fas fa-arrow-left mr-1"></i> Daftar
                </button>
                <button type="button" class="close ml-auto" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="deptEmpList"></div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/orgchart/css/jquery.orgchart.min.css') }}">
<style>
    /* ============================================================
       TAMPILAN ORG CHART (redesign 2026-11-11)
       Masalah sebelumnya: node mepet (padding 2px, tinggi 20px, title
       130px), jarak antar node nol, dan garis merah default plugin.
       Catatan geometri: connector plugin memakai offset tetap
       (::before top:-11px, ::after bottom:-11px, margin-bottom 20px).
       Jadi jarak VERTIKAL antar level tidak diubah (kalau diubah,
       garis penghubung putus). Ruang napas ditambah lewat: padding
       node, lebar/t tinggi node, dan padding .hierarchy untuk
       gap antar sibling (connector memakai % dari box, jadi aman).
       ============================================================ */

    /* Kartu chart */
    #chart-container {
        padding: 28px 24px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: auto;
    }
    #chart-container .orgchart { background: transparent; padding: 8px 4px 4px; }

    /* Jarak antar sibling: padding pada .hierarchy (bukan margin),
       supaya garis horizontal penghubung tetap menyambung. */
    #chart-container .orgchart .nodes > .hierarchy { padding: 0 14px; }

    /* Node induk HARUS center di atas anak-anaknya. Plugin 3.1.1 hanya
       memberi text-align:center pada .node (isi kotak), bukan pada
       .hierarchy, sehingga .node (inline-block) menempel kiri dan garis
       vertikal induk tidak bertemu garis horizontal anak. */
    #chart-container .orgchart .hierarchy { text-align: center; }

    /* Kotak node */
    #chart-container .orgchart .node {
        border-radius: 10px;
        padding: 0;
        margin-bottom: 30px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .10), 0 1px 2px rgba(15, 23, 42, .06);
        transition: box-shadow .15s ease, transform .15s ease;
    }
    #chart-container .orgchart .node:hover {
        background-color: transparent;
        box-shadow: 0 6px 16px rgba(15, 23, 42, .16);
        transform: translateY(-1px);
    }

    /* Header node: lebar lebih lega + teks boleh 2 baris */
    #chart-container .orgchart .node .title {
        box-sizing: border-box;
        width: 200px;
        height: auto;
        min-height: 42px;
        padding: 10px 12px;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.35;
        letter-spacing: .01em;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #1d3f86;
        border-radius: 9px 9px 0 0;
    }

    /* Sub-label node */
    #chart-container .orgchart .node .content {
        box-sizing: border-box;
        width: 200px;
        height: auto;
        min-height: 34px;
        padding: 8px 12px;
        font-size: 0.75rem;
        line-height: 1.35;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
        background: #fff;
        color: #334155;
        border: 1px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 9px 9px;
    }
    #chart-container .orgchart .node .content .emp-npk { color: #64748b; font-size: 0.6875rem; }

    /* Warna per level (brand) */
    #chart-container .orgchart .node[data-type="company"] .title { background: #16307a; }
    #chart-container .orgchart .node[data-type="department"] .title { background: #3a6fa8; }

    /* Card unit (Company/Department) bisa diklik -> modal daftar karyawan.
       Tidak ada lagi card karyawan di bagan; jumlah karyawan ditulis di card. */
    #chart-container .orgchart .node[data-type="company"],
    #chart-container .orgchart .node[data-type="department"] { cursor: pointer; }
    #chart-container .orgchart .node[data-emp-count]:not([data-emp-count="0"]) .content .emp-npk {
        color: #0f172a;
        font-weight: 700;
    }
    #chart-container .orgchart .node[data-emp-count]:not([data-emp-count="0"]) .content .emp-npk i {
        margin-right: 5px;
        color: #0e7490;
    }

    /* Modal daftar karyawan per departemen */
    #deptEmpModal .modal-dialog {
        max-width: 620px;
        margin-left: auto;
        margin-right: auto;
    }
    #deptEmpModal .modal-body { max-height: 62vh; overflow-y: auto; }
    #deptEmpModal .emp-sec {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #64748b;
        margin: 4px 0 8px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    #deptEmpModal .emp-sec:not(:first-child) { margin-top: 18px; }
    #deptEmpModal .emp-sec .badge { font-size: .68rem; }
    #deptEmpModal .emp-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 6px;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
    }
    #deptEmpModal .emp-row:hover { background: #f8fafc; }
    #deptEmpModal .emp-thumb-wrap {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border-radius: 50%;
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    #deptEmpModal .emp-thumb-wrap img { width: 100%; height: 100%; object-fit: cover; }
    #deptEmpModal .emp-thumb-icon { color: #94a3b8; }
    #deptEmpModal .emp-info { flex: 1 1 auto; min-width: 0; }
    #deptEmpModal .emp-name {
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #deptEmpModal .emp-meta { font-size: .78rem; color: #64748b; }
    #deptEmpModal .emp-total { font-size: .78rem; color: #64748b; }

    /* Ikon penanda "punya anak" (i.symbol, oci-leader) di-prepend plugin
       ke DALAM .title, dan default CSS-nya float:left + margin-top:12px.
       Karena .title kita flex-center, ikon itu bertumpuk dengan teks judul.
       Fungsinya hanya penanda (bukan tombol buka/tutup), jadi disembunyikan. */
    #chart-container .orgchart .node .title .symbol { display: none !important; }

    /* Tampilan foto karyawan (di dalam modal daftar): ukuran tetap & center */
    #deptEmpList .emp-photo-wrap { text-align: center; }
    #deptEmpList #deptEmpPhotoImg {
        display: block;
        margin: 0 auto;
        max-width: 100%;
        max-height: 340px;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    #deptEmpList .emp-photo-npk { font-size: .8rem; color: #64748b; }

    /* Garis penghubung: ganti merah default plugin jadi slate halus */
    #chart-container .orgchart .hierarchy::before,
    #chart-container .orgchart > ul > li > ul li > .node::before,
    #chart-container .orgchart .node:not(:only-child)::after {
        border-color: #cbd5e1;
        background-color: #cbd5e1;
    }
    #chart-container .orgchart .hierarchy::before { border-top-width: 2px; }

    /* Jarak antar LEVEL dinaikkan 20px -> 30px. Offset stub plugin ikut
       digeser (11px -> 16px, tinggi 9px -> 14px) supaya garis tetap
       menyambung: stub induk 2..16px, garis horizontal 14px di atas anak,
       stub anak 14..28px (anak mulai tepat 30px). */
    #chart-container .orgchart .node:not(:only-child)::after {
        bottom: -16px;
        height: 14px;
    }
    #chart-container .orgchart .hierarchy::before { top: -16px; }
    #chart-container .orgchart > ul > li > ul li > .node::before {
        top: -16px;
        height: 14px;
    }
</style>
@endpush

@push('scripts')
{{-- jQuery sudah dimuat layout (footerscript) sebelum @stack('scripts'). --}}
<script src="{{ asset('vendor/orgchart/js/jquery.orgchart.min.js') }}"></script>
<script>
(function () {
    const DATA_URL = '{{ route("org-chart.data") }}';
    const PHOTO_BASE = '{{ url("/org-chart/photo") }}';
    const SETTINGS_URL = '{{ route("org-chart.settings") }}';
    const CARD_SEL = '.node[data-type="company"], .node[data-type="department"]';
    let lastRes = null;       // payload terakhir: dipakai modal daftar karyawan
    let listView = null;      // tampilan daftar (untuk tombol "Kembali" dari foto)
    const photoCache = {};    // npk -> respons endpoint foto (hindari fetch ulang)
    const $container = $('#chart-container');
    let chart = null;
    let chartScale = 1;

    function applyScale() {
        const $target = $('#chart-container .orgchart-inner');
        if (!$target.length) {
            $('#zoomLabel').text(Math.round(chartScale * 100) + '%');
            return;
        }

        // CSS `zoom` mengubah ukuran layout -> container overflow:auto tetap bisa
        // di-scroll. Kalau browser tidak mendukung, fallback ke transform.
        if (typeof document.body.style.zoom !== 'undefined') {
            $target.css('zoom', chartScale).css('transform', 'none');
        } else {
            $target.css({
                transform: 'scale(' + chartScale + ')',
                transformOrigin: 'top center',
                transition: 'transform .15s ease'
            });
        }

        $('#zoomLabel').text(Math.round(chartScale * 100) + '%');
    }

    function setScale(s) {
        chartScale = Math.min(2, Math.max(0.4, s));
        applyScale();
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function nodeTemplate(node) {
        const title = esc(node.name || '');
        // Bagan hanya berisi unit (Company/Department). Jumlah karyawan aktif
        // ditulis di card sebagai penanda bahwa card bisa diklik (daftar
        // karyawan muncul di modal, bukan sebagai card terpisah).
        const count = parseInt(node.emp_count, 10) || 0;
        let sub = '<div class="emp-npk">' + esc(node.title || '') + (node.code ? ' · ' + esc(node.code) : '');
        if (count > 0) {
            sub += ' · <i class="fas fa-user-friends"></i>' + count + ' orang';
        }
        sub += '</div>';
        return '<div class="title">' + title + '</div><div class="content">' + sub + '</div>';
    }

    function loadChart() {
        $container.html('<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Memuat organization chart...</div>');

        fetch(DATA_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    $container.html('<div class="text-center text-danger py-5">' + esc(res.message || 'Gagal memuat data.') + '</div>');
                    return;
                }

                if (!res.has_settings || !res.nodes || !res.nodes.length) {
                    $container.html(
                        '<div class="text-center text-muted py-5">' +
                        '<i class="fas fa-sitemap fa-2x mb-2 d-block"></i>' +
                        'Belum ada node organization chart.<br>' +
                        '<a class="btn btn-primary btn-sm rounded-pill px-3 mt-2" href="' + SETTINGS_URL + '">' +
                        '<i class="fas fa-cog mr-1"></i> Buka Setting Org Chart</a>' +
                        '</div>'
                    );
                    $('#empCount').text('0 karyawan');
                    return;
                }

                const tree = res.tree;
                lastRes = res;
                if (!tree) {
                    $container.html('<div class="text-center text-muted py-5">Struktur chart tidak terbentuk. Periksa parent node di Setting Org Chart.</div>');
                    $('#empCount').text((res.total_employees ?? 0) + ' karyawan');
                    return;
                }

                $container.empty();

                // IMPORTANT: jangan pakai pan/zoom bawaan plugin 3.1.1.
                // Handler wheel-nya (setChartScale) membaca $chart.data('options')
                // yang bisa undefined -> "Cannot read properties of undefined
                // (reading 'zoomoutLimit')" dan chart berhenti dirender.
                // Kita render polos (pan:false, zoom:false) lalu sediakan
                // kontrol zoom sendiri (transform CSS) di bawah.
                const $inner = $('<div class="orgchart-inner"></div>').appendTo($container);

                try {
                    chart = $inner.orgchart({
                        data: tree,
                        nodeTemplate: nodeTemplate,
                        pan: false,
                        zoom: false,
                        direction: 't2b',
                        toggleSiblingsResp: true,
                    });
                } catch (e) {
                    $container.html('<div class="text-center text-danger py-5">Gagal merender chart: ' + esc(e.message) + '</div>');
                    return;
                }

                // Tandai tipe node + jumlah karyawan via atribut data
                // (dipakai pewarnaan CSS dan penanda card bisa diklik).
                $container.find('.node').each(function () {
                    const id = String($(this).attr('id'));
                    const node = res.nodes.find(n => String(n.id) === id);
                    if (node) {
                        $(this).attr('data-type', node.type);
                        $(this).attr('data-emp-count', parseInt(node.emp_count, 10) || 0);
                    }
                });

                applyScale();

                $('#empCount').text((res.total_employees ?? 0) + ' karyawan');

                // Klik card unit (Company/Department) -> modal daftar karyawan.
                // Karyawan tidak lagi digambar sebagai card di bagan.
                $container.off('click', CARD_SEL).on('click', CARD_SEL, function (e) {
                    e.stopPropagation();
                    openDeptEmployees(String($(this).attr('id')));
                });
            })
            .catch(err => {
                $container.html('<div class="text-center text-danger py-5">Gagal memuat data: ' + esc(err.message) + '</div>');
            });
    }

    // --- Modal daftar karyawan per departemen --------------------------------
    // Karyawan dibaca dari `res.employees` (key = id node unit). Modal HANYA
    // menampilkan karyawan unit yang diklik (tanpa sub-departemen) supaya daftar
    // tidak bercampur antar-departemen — ini permintaan user: "daftar karyawan
    // yang muncul hanya karyawan di dept tersebut saja". Jumlah karyawan
    // sub-departemen hanya disebut sebagai keterangan di subjudul.
    function findTreeNode(node, id) {
        if (!node) return null;
        if (String(node.id) === id) return node;
        const kids = node.children || [];
        for (let i = 0; i < kids.length; i++) {
            const hit = findTreeNode(kids[i], id);
            if (hit) return hit;
        }
        return null;
    }

    // Jumlah karyawan seluruh subtree (dipakai hanya sebagai keterangan di
    // subjudul, bukan isi daftar). Dihitung unik per NPK supaya orang yang
    // tercatat di dua departemen tidak dihitung dua kali.
    function countSubtreeEmployees(node) {
        const map = (lastRes && lastRes.employees) ? lastRes.employees : {};
        const seen = {};
        let n = 0;
        (function walk(nd) {
            (map[nd.id] || []).forEach(function (e) {
                const k = String(e.npk || '');
                if (k && !seen[k]) { seen[k] = 1; n++; }
            });
            (nd.children || []).forEach(walk);
        })(node);
        return n;
    }

    function collectSections(node, out) {
        out = out || [];
        const map = (lastRes && lastRes.employees) ? lastRes.employees : {};
        const list = map[node.id] || [];
        // Hanya karyawan unit yang diklik (tanpa sub-departemen). Jangan
        // menelusuri anak node: itu membuat departemen lain ikut tampil
        // (mis. klik 2140 tetapi muncul 83/109/2095/2138).
        if (list.length) {
            out.push({ id: node.id, name: node.name, code: node.code, list: list });
        }
        return out;
    }

    function empRowHtml(emp) {
        return '<div class="emp-row" data-npk="' + esc(emp.npk) + '">' +
            '<div class="emp-thumb-wrap">' +
                '<i class="fas fa-user emp-thumb-icon"></i>' +
                '<img class="emp-thumb" alt="" style="display:none;">' +
            '</div>' +
            '<div class="emp-info">' +
                '<div class="emp-name">' + esc(emp.nama || emp.npk) + '</div>' +
                '<div class="emp-meta">' + esc(emp.npk) + (emp.dept_code ? ' · ' + esc(emp.dept_code) : '') + '</div>' +
            '</div>' +
            '<span class="text-muted small"><i class="fas fa-expand"></i></span>' +
        '</div>';
    }

    function fetchPhoto(npk) {
        if (photoCache[npk]) return Promise.resolve(photoCache[npk]);
        return fetch(PHOTO_BASE + '/' + encodeURIComponent(npk), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (p) { photoCache[npk] = p || {}; return photoCache[npk]; })
            .catch(function () { photoCache[npk] = {}; return photoCache[npk]; });
    }

    function loadThumb($row, npk) {
        const $wrap = $row.find('.emp-thumb-wrap');
        fetchPhoto(npk).then(function (p) {
            if (!p || !p.success || !p.photo) return;
            $wrap.find('.emp-thumb').attr('src', p.photo).on('load', function () {
                $(this).show();
                $wrap.find('.emp-thumb-icon').hide();
            });
        });
    }

    function openDeptEmployees(nodeId) {
        if (!lastRes || !lastRes.tree) return;
        const node = findTreeNode(lastRes.tree, nodeId);
        if (!node) return;

        const sections = collectSections(node);
        let total = 0;
        sections.forEach(function (s) { total += s.list.length; });

        const subCount = countSubtreeEmployees(node);
        const kids = (node.children || []).length;
        const title = node.name || 'Daftar Karyawan';
        // Judul menyebut jumlah karyawan UNIT INI saja — sama dengan angka pada
        // card — supaya tidak ada lagi teks "termasuk sub-departemen" padahal
        // daftarnya hanya unit yang diklik.
        const sub = (node.title || '') + (node.code ? ' · ' + node.code : '') + ' · ' +
            total + ' karyawan aktif' +
            (kids ? ' · unit ini punya ' + kids + ' sub-departemen (' + subCount +
                ' karyawan, dibuka lewat card masing-masing)' : '');

        let body;
        if (!total) {
            body = '<div class="text-center text-muted py-4">' +
                '<i class="fas fa-user-slash fa-2x mb-2 d-block"></i>' +
                'Belum ada karyawan aktif di unit ini.</div>';
        } else {
            body = '';
            sections.forEach(function (sec) {
                body += '<div class="emp-sec">' + esc(sec.name) +
                        (sec.code ? ' <span class="text-muted">· ' + esc(sec.code) + '</span>' : '') +
                        ' <span class="badge badge-light border">' + sec.list.length + ' orang</span></div>';
                sec.list.forEach(function (emp) { body += empRowHtml(emp); });
            });
        }

        listView = { title: title, sub: sub, html: body };
        renderListView();

        $('#deptEmpModal').modal('show');
    }

    function renderListView() {
        if (!listView) return;
        $('#deptEmpTitle').text(listView.title);
        $('#deptEmpSub').text(listView.sub);
        $('#deptEmpBack').addClass('d-none');
        $('#deptEmpList').html(listView.html);
        $('#deptEmpList .emp-row').each(function () {
            loadThumb($(this), String($(this).attr('data-npk')));
        });
    }

    // Tampilan foto DIBUKA DI MODAL YANG SAMA (bukan modal kedua): dua modal
    // bertumpuk membuat Bootstrap 4 menggantung di status transisi
    // (_isTransitioning tetap true, backdrop menumpuk, tombol tutup tidak
    // bereaksi lagi).
    function showPhotoView(npk, nama) {
        $('#deptEmpTitle').text(nama || npk);
        $('#deptEmpSub').text('NPK ' + npk);
        $('#deptEmpBack').removeClass('d-none');
        $('#deptEmpList').html(
            '<div class="emp-photo-wrap">' +
                '<img id="deptEmpPhotoImg" class="rounded border" alt="" style="display:none;">' +
                '<div id="deptEmpPhotoEmpty" class="text-muted py-4">Tidak ada foto (PKWT.file_pas_foto kosong/tidak ditemukan).</div>' +
                '<div class="emp-photo-npk mt-2">NPK: ' + esc(npk) + '</div>' +
            '</div>'
        );
        fetchPhoto(npk).then(function (p) {
            if (!p || !p.success || !p.photo) return;
            const img = $('#deptEmpPhotoImg');
            img.attr('src', p.photo).one('load', function () {
                $(this).show();
                $('#deptEmpPhotoEmpty').hide();
            });
        });
    }

    // Kembali dari foto ke daftar (thumbnail langsung tampil dari cache).
    $(document).off('click', '#deptEmpBack').on('click', '#deptEmpBack', function () {
        renderListView();
    });

    $(document).off('click', '#deptEmpList .emp-row').on('click', '#deptEmpList .emp-row', function () {
        showPhotoView(String($(this).attr('data-npk')), $.trim($(this).find('.emp-name').text()));
    });

    $('#btnReloadChart').on('click', loadChart);
    $('#zoomIn').on('click', () => setScale(chartScale + 0.1));
    $('#zoomOut').on('click', () => setScale(chartScale - 0.1));
    $('#zoomReset').on('click', () => setScale(1));
    loadChart();
})();
</script>
@endpush
