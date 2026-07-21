<!DOCTYPE html>
<html lang="id">
@include('layout.header')
@include('sweetalert::alert')
<body id="page-top">

<div id="wrapper">

    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

            @include('layout.navbar')

            <div class="container-fluid" id="mon-app"
                 data-filters='@json($filters)'
                 data-endpoint="{{ route('monitoring.dashboard.data') }}"
                 data-sync-bom-url="{{ route('monitoring.sync.bom') }}"
                 data-sync-po-url="{{ route('monitoring.sync.po') }}"
                 data-calendar-url="{{ route('monitoring.dashboard.calendar') }}"
                 data-calendar-detail-url="{{ route('monitoring.dashboard.calendar.detail') }}">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1 text-gray-800">Material List Dashboard</h1>
                        <p class="mb-0 text-gray-600 small">Monitoring ORDER &middot; PO &middot; BOM &mdash; realtime dari database</p>
                    </div>
                    <div>
                        @canRoute('monitoring.sync.bom')
                            <button id="btn-sync-bom" type="button" class="btn btn-info btn-sm mr-2">
                                <i class="fas fa-sync-alt fa-sm"></i> Sync BOM
                            </button>
                        @endcanRoute

                        @canRoute('monitoring.sync.po')
                            <button id="btn-sync-po" type="button" class="btn btn-secondary btn-sm mr-2">
                                <i class="fas fa-sync-alt fa-sm"></i> Sync PO
                            </button>
                        @endcanRoute

                        @canRoute('monitoring.order.import.form')
                            <a href="{{ route('monitoring.order.import.form') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-file-upload fa-sm"></i> Upload Sheet ORDER
                            </a>
                        @endcanRoute
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="small font-weight-bold text-gray-600 text-uppercase mb-1">Buyer</label>
                                <select id="f-buyer" class="form-control form-control-sm select2-filter">
                                    <option value=""></option>
                                    @foreach($filterOptions['buyer'] as $v)
                                        <option value="{{ $v }}" @selected(($filters['buyer'] ?? null) === $v)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="small font-weight-bold text-gray-600 text-uppercase mb-1">Style</label>
                                <select id="f-style" class="form-control form-control-sm select2-filter">
                                    <option value=""></option>
                                    @foreach($filterOptions['style'] as $v)
                                        <option value="{{ $v }}" @selected(($filters['style'] ?? null) === $v)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="small font-weight-bold text-gray-600 text-uppercase mb-1">Uraian (CPO)</label>
                                <select id="f-uraian" class="form-control form-control-sm select2-filter">
                                    <option value=""></option>
                                    @foreach($filterOptions['uraian'] as $v)
                                        <option value="{{ $v }}" @selected(($filters['uraian'] ?? null) === $v)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex mon-filter-actions" style="gap:8px">
                                    <button id="f-apply" class="btn btn-primary btn-sm">
                                        <i class="fas fa-filter fa-sm"></i> Filter
                                    </button>
                                    <button id="f-reset" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-undo fa-sm"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards -->
                <div class="row">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Qty Order</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-qty-order">{{ number_format($summary['total_qty_order']) }}</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Style</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-total-style">{{ number_format($summary['total_style']) }}</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-tshirt fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 kpi-warn">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Item BOM Belum Diorder</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="kpi-belum-order">{{ number_format($summary['total_item_belum_order']) }}</div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar: Production Delivery -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Kalender Production Delivery</h6>
                        <div class="d-flex align-items-center" style="gap:10px">
                            <button id="cal-prev" type="button" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-left"></i></button>
                            <span id="cal-label" class="font-weight-bold text-gray-700" style="min-width:140px; text-align:center;"></span>
                            <button id="cal-next" type="button" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-7 mb-3 mb-lg-0">
                                <table class="table table-bordered table-sm mb-0" id="mon-calendar">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Min</th><th class="text-center">Sen</th>
                                            <th class="text-center">Sel</th><th class="text-center">Rab</th>
                                            <th class="text-center">Kam</th><th class="text-center">Jum</th>
                                            <th class="text-center">Sab</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                                <div class="small text-gray-600 mt-2 d-flex" style="gap:16px">
                                    <span><span class="badge bg-warning" style="width:12px;height:12px;display:inline-block;padding:0;"></span> Ada order</span>
                                    <span><span class="badge bg-danger" style="width:12px;height:12px;display:inline-block;padding:0;"></span> &le;7 hari lagi / sudah lewat</span>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div id="cal-detail-empty" class="text-muted small">
                                    Klik salah satu tanggal pada kalender untuk melihat detail order dengan
                                    <em>production delivery</em> pada tanggal tersebut.
                                </div>
                                <div id="cal-detail-wrap" class="d-none">
                                    <div class="small font-weight-bold text-gray-700 mb-2" id="cal-detail-title"></div>
                                    <div class="mon-table-box" style="max-height:340px;">
                                        <table class="table table-bordered table-sm mon-table mon-table-fixed w-100" id="table-cal-detail">
                                            <colgroup>
                                                <col style="width:30%">
                                                <col style="width:18%">
                                                <col style="width:18%">
                                                <col style="width:20%">
                                                <col style="width:14%">
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th>Uraian</th><th>Buyer</th><th>Style</th>
                                                    <th>Destination</th><th class="right">Qty Ord</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pivot 1: ORDER -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pivot ORDER &mdash; Qty Garment per Uraian / Buyer / Style</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-5">
                                <div class="chart-area">
                                    <canvas id="chart-order"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="mon-table-box">
                                    <table class="table table-bordered table-sm mon-table mon-table-fixed w-100" id="table-order">
                                        <colgroup>
                                            <col style="width:20%">
                                            <col style="width:15%">
                                            <col style="width:15%">
                                            <col style="width:17%">
                                            <col style="width:18%">
                                            <col style="width:15%">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Uraian</th><th>Buyer</th><th>Style</th><th>Destination</th>
                                                <th>Estimasi Shipment</th><th class="right">Qty Order</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pivot 2: MATERIAL PURCHASE -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pivot MATERIAL PURCHASE &mdash; Jenis PO: PO / Material Supply</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-5">
                                <div class="chart-area">
                                    <canvas id="chart-material"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="mon-table-box">
                                    <table class="table table-bordered table-sm mon-table mon-table-fixed w-100" id="table-material">
                                        <colgroup>
                                            <col style="width:3%">
                                            <col style="width:16%">
                                            <col style="width:10%">
                                            <col style="width:7%">
                                            <col style="width:7%">
                                            <col style="width:13%">
                                            <col style="width:13%">
                                            <col style="width:11%">
                                            <col style="width:11%">
                                            <col style="width:9%">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Item</th>
                                                <th>No. PO</th>
                                                <th>Satuan</th>
                                                <th>Valas</th>
                                                <th class="right">Harga Satuan</th>
                                                <th class="right">Harga Total</th>
                                                <th class="right">Jumlah Order</th>
                                                <th class="right">Jumlah Diterima</th>
                                                <th class="right">Sisa</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pivot 3: WORK ORDER -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pivot WORK ORDER &mdash; Item BOM yang Belum Diorder (ada di BOM, belum ada di PO)</h6>
                    </div>
                    <div class="card-body">
                        <div class="mon-table-box mon-table-box-full">
                            <table class="table table-bordered table-sm mon-table mon-table-fixed w-100" id="table-workorder">
                                <colgroup>
                                    <col style="width:14%">
                                    <col style="width:12%">
                                    <col style="width:23%">
                                    <col style="width:12%">
                                    <col style="width:12%">
                                    <col style="width:12%">
                                    <col style="width:8%">
                                    <col style="width:7%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Uraian</th><th>Barang Code</th><th>Nama Barang</th>
                                        <th>Departemen</th><th>Komponen</th><th>Barang Jadi</th>
                                        <th>Satuan</th>
                                        <th class="right">Cons/Gmt</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
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

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<!-- Bootstrap core JavaScript -->
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/js/sb-admin-2.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function(){
    const app = document.getElementById('mon-app');
    const endpoint = app.dataset.endpoint;
    const syncBomUrl = app.dataset.syncBomUrl;
    const syncPoUrl = app.dataset.syncPoUrl;
    const calendarUrl = app.dataset.calendarUrl;
    const calendarDetailUrl = app.dataset.calendarDetailUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const fUraian = document.getElementById('f-uraian');
    const fBuyer  = document.getElementById('f-buyer');
    const fStyle  = document.getElementById('f-style');
    const fApply  = document.getElementById('f-apply');
    const fReset  = document.getElementById('f-reset');
    const btnSyncBom = document.getElementById('btn-sync-bom');
    const btnSyncPo  = document.getElementById('btn-sync-po');

    let chartOrder, chartMaterial;
    let dtOrder, dtMaterial, dtWorkOrder;

    const chartAvailable = typeof Chart !== 'undefined';
    if (!chartAvailable) {
        console.warn('Chart.js tidak berhasil dimuat, grafik dilewati. Tabel tetap ditampilkan.');
    }

    // Select2 untuk 3 dropdown filter -- single select, bisa dikosongkan lagi lewat
    // tombol "clear" (allowClear) di pojok kanan select2.
    $('.select2-filter').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Semua',
        allowClear: true
    });

    /* =========================================================
       Cascading Select2: Buyer -> Style -> Uraian (CPO)
       Sumber data: kombinasi uraian/buyer/style yang benar-benar
       ada di mon_orders, diambil dari orderPivot yang sudah
       dikirim server (tanpa request tambahan).
       ========================================================= */
    const comboData = @json(
        $orderPivot->map(fn($r) => ['uraian' => $r->uraian, 'buyer' => $r->buyer, 'style' => $r->style])->unique()->values()
    );

    let cascadeSuppressed = false;

    function uniqueSorted(arr){
        return Array.from(new Set(arr.filter(v => v !== null && v !== undefined && v !== '')))
            .sort((a, b) => String(a).localeCompare(String(b)));
    }

    function filterCombos(opts){
        const buyer = opts.buyer;
        const style = opts.style;
        return comboData.filter(c => {
            if (buyer && c.buyer !== buyer) return false;
            if (style && c.style !== style) return false;
            return true;
        });
    }

    // Isi ulang <option> sebuah select2 single, sambil mempertahankan value
    // terpilih kalau masih valid terhadap daftar baru (kalau tidak valid lagi,
    // otomatis balik ke "Semua"). Trigger 'change' di sini SENGAJA memicu
    // handler cascading level berikutnya juga -- makanya dipagari dengan
    // cascadeSuppressed supaya tidak terjadi loop balik ke handler asal.
    function setSelect2Options($select, values, keepSelected){
        const selected = keepSelected ? ($select.val() || '') : '';
        const validSelected = values.includes(selected) ? selected : '';

        $select.empty();
        $select.append(new Option('', '', false, validSelected === ''));
        values.forEach(v => $select.append(new Option(v, v, false, v === validSelected)));

        cascadeSuppressed = true;
        $select.val(validSelected).trigger('change');
        cascadeSuppressed = false;
    }

    function updateStyleOptions(){
        const selectedBuyer = $(fBuyer).val() || '';
        const styles = uniqueSorted(filterCombos({ buyer: selectedBuyer }).map(c => c.style));
        setSelect2Options($(fStyle), styles, true);
    }

    function updateUraianOptions(){
        const selectedBuyer = $(fBuyer).val() || '';
        const selectedStyle = $(fStyle).val() || '';
        const uraians = uniqueSorted(filterCombos({ buyer: selectedBuyer, style: selectedStyle }).map(c => c.uraian));
        setSelect2Options($(fUraian), uraians, true);
    }

    $(fBuyer).on('change', function(){
        if (cascadeSuppressed) return;
        updateStyleOptions();
        updateUraianOptions();
    });

    $(fStyle).on('change', function(){
        if (cascadeSuppressed) return;
        updateUraianOptions();
    });

    updateStyleOptions();
    updateUraianOptions();

    const dtLanguage = {
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_ baris',
        info: 'Baris _START_-_END_ dari _TOTAL_',
        infoEmpty: 'Tidak ada data',
        infoFiltered: '(disaring dari _MAX_ total baris)',
        zeroRecords: 'Tidak ada data yang cocok',
        emptyTable: 'Tidak ada data',
        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
    };

    function initDataTable(tableId, options){
        return $(tableId).DataTable(Object.assign({
            language: dtLanguage,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            responsive: false,
            autoWidth: false
        }, options));
    }

    // ================================================================
    // Kolom tabel bisa di-resize manual (drag garis di kanan header).
    // Lebar disimpan dalam % (bukan px) di monColWidths supaya tetap
    // proporsional walau ukuran layar berubah, dan supaya gampang
    // disinkronkan ke tabel child (breakdown Pivot MATERIAL PURCHASE).
    // Semua tabel sumbernya <colgroup> yang sudah didefinisikan di blade;
    // state di sini hanya melacak perubahan HASIL drag oleh user.
    // ================================================================
    const monColWidths = {};

    function readColWidths(tableSelector){
        return Array.from(document.querySelectorAll(`${tableSelector} > colgroup > col`))
            .map(c => parseFloat(c.style.width) || 0);
    }

    function applyColWidths(tableSelector, widths){
        monColWidths[tableSelector] = widths;
        document.querySelectorAll(`${tableSelector} > colgroup > col`).forEach((col, i) => {
            if (widths[i] !== undefined) col.style.width = widths[i] + '%';
        });
    }

    // Kolom "toggle" (0) + "Item" (1) di parent #table-material digabung jadi
    // 1 kolom (colspan=2) di tabel child (renderMaterialDetail) -- jadi lebar
    // child = [w0+w1, w2, w3, ...].
    function materialWidthsToChildWidths(widths){
        return [widths[0] + widths[1], ...widths.slice(2)];
    }

    // Terapkan lebar terbaru #table-material ke semua tabel child (breakdown
    // per spesifikasi) yang SEDANG terbuka saat parent-nya di-resize.
    function syncOpenMaterialChildren(){
        const widths = monColWidths['#table-material'];
        if (!widths) return;
        const childWidths = materialWidthsToChildWidths(widths);
        document.querySelectorAll('#table-material .mon-detail-table').forEach(t => {
            t.querySelectorAll(':scope > colgroup > col').forEach((col, i) => {
                if (childWidths[i] !== undefined) col.style.width = childWidths[i] + '%';
            });
        });
    }

    // Pasang handle drag-resize di setiap header kolom (kecuali kolom paling
    // kanan, karena itu batas tepi tabel). Idempotent -- aman dipanggil ulang
    // tiap kali tabel di-render ulang (DataTables destroy()+create lagi saat
    // filter/reload), tidak akan pasang handle dobel.
    function initColumnResize(tableSelector, opts){
        opts = opts || {};
        const minPct = opts.minPercent || 4;
        const table = document.querySelector(tableSelector);
        if (!table) return;

        if (!monColWidths[tableSelector]) {
            monColWidths[tableSelector] = readColWidths(tableSelector);
        } else {
            applyColWidths(tableSelector, monColWidths[tableSelector]);
        }

        const ths = table.querySelectorAll('thead th');
        ths.forEach((th, i) => {
            if (i === ths.length - 1) return;
            th.classList.add('mon-resizable');
            if (th.querySelector('.mon-col-resizer')) return;

            const handle = document.createElement('div');
            handle.className = 'mon-col-resizer';
            th.appendChild(handle);

            // Drag-resize tidak boleh ikut memicu "sort kolom" bawaan DataTables
            // (yang listen ke event click di <th>) -- blok click berikutnya kalau
            // barusan selesai drag.
            let suppressNextClick = false;
            th.addEventListener('click', function(e){
                if (suppressNextClick) { e.stopPropagation(); e.preventDefault(); suppressNextClick = false; }
            }, true);

            handle.addEventListener('mousedown', function(e){
                e.preventDefault();
                e.stopPropagation();
                const tableWidth = table.getBoundingClientRect().width;
                const startX = e.pageX;
                const startWidths = (monColWidths[tableSelector] || readColWidths(tableSelector)).slice();
                handle.classList.add('resizing');
                document.body.classList.add('mon-col-resizing');

                function onMove(ev){
                    const deltaPct = ((ev.pageX - startX) / tableWidth) * 100;
                    const widths = startWidths.slice();
                    let a = widths[i] + deltaPct;
                    let b = widths[i + 1] - deltaPct;
                    if (a < minPct) { b -= (minPct - a); a = minPct; }
                    if (b < minPct) { a -= (minPct - b); b = minPct; }
                    widths[i] = Math.max(minPct, a);
                    widths[i + 1] = Math.max(minPct, b);
                    applyColWidths(tableSelector, widths);
                    if (opts.onResize) opts.onResize(widths);
                }
                function onUp(){
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    handle.classList.remove('resizing');
                    document.body.classList.remove('mon-col-resizing');
                    suppressNextClick = true;
                    if (opts.onResizeEnd) opts.onResizeEnd(monColWidths[tableSelector]);
                }
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }

    function currentFilters(){
        return {
            uraian: $(fUraian).val() || '',
            buyer:  $(fBuyer).val() || '',
            style:  $(fStyle).val() || ''
        };
    }

    function buildQueryParams(filters){
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, val]) => {
            if (Array.isArray(val)) {
                val.forEach(v => { if (v !== '' && v !== null) params.append(`${key}[]`, v); });
            } else if (val) {
                params.append(key, val);
            }
        });
        return params;
    }

    function fmt(n){ return Number(n || 0).toLocaleString('id-ID'); }

    // Format qty dengan desimal tetap (mis. jumlah_order/jumlah_diterima material yang decimal(18,4))
    function fmtQty(n, digits){
        digits = digits === undefined ? 2 : digits;
        return Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    }

    // Sisa yang nilainya 0 ditampilkan sebagai "-" (mengikuti tampilan pivot Excel aslinya)
    function fmtSisa(n, digits){
        digits = digits === undefined ? 2 : digits;
        const num = Number(n || 0);
        return Math.abs(num) < 0.00001 ? '-' : fmtQty(num, digits);
    }

    // Format nominal harga sesuai valas: IDR pakai "Rp", USD pakai "$", valas lain pakai kode-nya.
    // Kalau satu baris ternyata gabungan >1 valas (dipisah koma di data), jangan pasang simbol
    // mata uang yang menyesatkan -- tampilkan angka polos saja (kode valasnya tetap ada di kolom Valas).
    function fmtCurrency(n, valas){
        const num = Number(n || 0);
        const code = String(valas || '').toUpperCase().trim();

        if (!code || code.indexOf(',') !== -1) {
            return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (code === 'USD') {
            return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (code === 'IDR' || code === 'RP') {
            return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
        return code + ' ' + num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Format tanggal (production_delivery / buyer_delivery) dari berbagai kemungkinan
    // format yang dikembalikan driver SQL Server (mis. "2026-07-15" atau ISO datetime penuh).
    function fmtDate(d){
        if (!d) return '-';
        const iso = String(d).slice(0, 10);
        const parts = iso.split('-');
        if (parts.length !== 3) return String(d);
        const y = parts[0], m = parts[1], day = parts[2];
        return `${day}-${m}-${y}`;
    }

    function fillTable(tbodySelector, rows, rowRenderer){
        const tbody = document.querySelector(tbodySelector);
        tbody.innerHTML = rows.map(rowRenderer).join('');
    }

    function renderOrderPivot(rows){
        if (dtOrder) { dtOrder.destroy(); dtOrder = null; }

        fillTable('#table-order tbody', rows, r =>
            `<tr>
                <td>${r.uraian ?? ''}</td>
                <td>${r.buyer ?? ''}</td>
                <td>${r.style ?? ''}</td>
                <td>${r.destination ?? ''}</td>
                <td>${fmtDate(r.estimasi_shipment)}</td>
                <td class="right" data-order="${Number(r.qty_order) || 0}">${fmt(r.qty_order)}</td>
            </tr>`
        );

        dtOrder = initDataTable('#table-order', {
            columnDefs: [{ targets: 5, className: 'text-right' }]
        });
        initColumnResize('#table-order');

        if (!chartAvailable) return;

        const top = rows.slice().sort((a,b) => b.qty_order - a.qty_order).slice(0, 10);
        const labels = top.map(r => `${r.uraian} - ${r.style ?? ''}`);
        const data = top.map(r => Number(r.qty_order));

        if (chartOrder) chartOrder.destroy();
        chartOrder = new Chart(document.getElementById('chart-order'), {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Qty Order', data, backgroundColor: '#4e73df', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                scales: { x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 30 } } } }
        });
    }

    // Baris detail (breakdown per spesifikasi/valas/no PO) yang muncul saat parent row
    // di-expand. Kolom tabel ini SENGAJA dibuat identik lebarnya (lihat colgroup di bawah,
    // harus persis sama persennya dengan colgroup tabel induk #table-material) supaya lurus:
    // kolom "toggle" + "Item" di induk digabung jadi 1 kolom (colspan=2) di sini untuk
    // menampung label spesifikasi, sisanya 1:1 dengan kolom induk.
    function renderMaterialDetail(details){
        if (!details || !details.length) {
            return '<div class="text-muted small px-3 py-2">Tidak ada rincian spesifikasi.</div>';
        }
        const body = details.map(d => {
            const label = (d.spesifikasi && String(d.spesifikasi).trim()) ? d.spesifikasi : '(Tanpa Spesifikasi)';
            const rowClass = Number(d.sisa) > 0.00001 ? 'table-danger' : '';
            return `<tr class="${rowClass}">
                <td class="mon-item-cell" title="${escapeHtml(label)}">${label}</td>
                <td class="mon-po-cell" title="${escapeHtml(d.no_po ?? '')}">${d.no_po ?? '-'}</td>
                <td>${d.satuan_order ?? '-'}</td>
                <td>${d.valas ?? '-'}</td>
                <td class="right">${fmtCurrency(d.harga_satuan, d.valas)}</td>
                <td class="right">${fmtCurrency(d.harga_total, d.valas)}</td>
                <td class="right">${fmtQty(d.jumlah_order)}</td>
                <td class="right">${fmtQty(d.jumlah_diterima)}</td>
                <td class="right">${fmtSisa(d.sisa)}</td>
            </tr>`;
        }).join('');

        // Colgroup mengikuti lebar TERKINI #table-material (bisa berubah kalau user
        // sudah resize kolom parent) supaya child selalu lurus dengan parent, bukan
        // lebar default yang di-hardcode.
        const childWidths = materialWidthsToChildWidths(
            monColWidths['#table-material'] || readColWidths('#table-material')
        );
        const colgroupHtml = childWidths.map(w => `<col style="width:${w}%">`).join('');

        return `<table class="table table-sm table-borderless mb-0 mon-detail-table mon-table-fixed w-100">
            <colgroup>${colgroupHtml}</colgroup>
            <tbody>${body}</tbody>
        </table>`;
    }

    function renderMaterialPivot(rows){
        if (dtMaterial) { dtMaterial.destroy(); dtMaterial = null; }

        // Pakai DataTables "data"/"columns" (bukan HTML string manual) supaya setiap parent
        // row bisa punya child row (row().child()) untuk breakdown per spesifikasi tanpa
        // ikut dihitung sebagai baris tersendiri saat pagination/search.
        dtMaterial = $('#table-material').DataTable({
            language: dtLanguage,
            data: rows,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            responsive: false,
            autoWidth: false,
            // Lebar kolom TIDAK di-set di sini (autoWidth:false + width per-kolom di JS bisa
            // konflik dengan colgroup di HTML). Colgroup pada <table id="table-material"> di
            // blade adalah satu-satunya sumber lebar kolom, dan disamakan persis dengan
            // colgroup tabel detail (renderMaterialDetail) supaya parent & child selalu lurus.
            columns: [
                {
                    data: null, orderable: false, className: 'mon-toggle-cell text-center',
                    render: (data, type, row) => (row.details && row.details.length)
                        ? '<i class="fas fa-plus-square text-primary mon-toggle"></i>'
                        : ''
                },
                {
                    data: null, className: 'mon-item-cell',
                    render: (r, type) => {
                        const name = r.barang_name || r.barang_code || '';
                        return type === 'display' ? `<span title="${escapeHtml(name)}">${escapeHtml(name)}</span>` : name;
                    }
                },
                {
                    data: 'no_po', defaultContent: '-', className: 'mon-po-cell',
                    render: (v, type) => {
                        const val = v || '-';
                        return type === 'display' ? `<span title="${escapeHtml(val)}">${escapeHtml(val)}</span>` : val;
                    }
                },
                { data: 'satuan_order', defaultContent: '-' },
                { data: 'valas', defaultContent: '-' },
                { data: null, className: 'right', render: r => fmtCurrency(r.harga_satuan, r.valas) },
                { data: null, className: 'right', render: r => fmtCurrency(r.harga_total, r.valas) },
                { data: 'jumlah_order', className: 'right', render: v => fmtQty(v) },
                { data: 'jumlah_diterima', className: 'right', render: v => fmtQty(v) },
                { data: 'sisa', className: 'right', render: v => fmtSisa(v) },
            ],
            // Baris parent (per item) diberi warna merah kalau masih ada sisa (belum diterima penuh),
            // supaya konsisten dengan highlight di baris detail per-spesifikasi.
            createdRow: (row, data) => {
                if (Number(data.sisa) > 0.00001) {
                    row.classList.add('table-danger');
                }
            }
        });

        initColumnResize('#table-material', { onResize: syncOpenMaterialChildren });

        // Delegasikan klik ke elemen tabel (stabil lintas destroy/redraw), lalu bersihkan
        // binding lama dulu supaya tidak dobel setiap kali renderMaterialPivot dipanggil ulang.
        $('#table-material').off('click.monToggle').on('click.monToggle', 'td.mon-toggle-cell, td:nth-child(2)', function(e){
            if ($(e.target).is('.mon-col-resizer')) return; // klik di handle resize, bukan buat expand
            const tr = $(this).closest('tr');
            const row = dtMaterial.row(tr);
            const data = row.data();
            if (!data || !data.details || !data.details.length) return;

            const icon = tr.find('.mon-toggle');
            if (row.child.isShown()) {
                row.child.hide();
                icon.removeClass('fa-minus-square').addClass('fa-plus-square');
            } else {
                row.child(renderMaterialDetail(data.details)).show();
                icon.removeClass('fa-plus-square').addClass('fa-minus-square');
            }
        });

        if (!chartAvailable) return;

        const top = rows.slice(0, 10);
        const labels = top.map(r => r.barang_name ?? r.barang_code ?? '-');

        if (chartMaterial) chartMaterial.destroy();
        chartMaterial = new Chart(document.getElementById('chart-material'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Jumlah Order', data: top.map(r => Number(r.jumlah_order)), backgroundColor: '#4e73df', borderRadius: 4 },
                    { label: 'Jumlah Diterima', data: top.map(r => Number(r.jumlah_diterima)), backgroundColor: '#1cc88a', borderRadius: 4 },
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                plugins: { legend: { position: 'bottom' } } }
        });
    }

    function renderWorkOrderPivot(rows){
        if (dtWorkOrder) { dtWorkOrder.destroy(); dtWorkOrder = null; }

        fillTable('#table-workorder tbody', rows, r =>
            `<tr>
                <td>${r.uraian ?? ''}</td>
                <td>${r.barang_code ?? ''}</td>
                <td>${r.barang_name ?? ''}</td>
                <td>${r.departemen ?? ''}</td>
                <td>${r.komponen ?? ''}</td>
                <td>${r.barang_jadi ?? ''}</td>
                <td>${r.satuan_order ?? '-'}</td>
                <td class="right" data-order="${Number(r.total_cons) || 0}">${fmtQty(r.total_cons, 4)}</td>
            </tr>`
        );

        dtWorkOrder = initDataTable('#table-workorder', {
            pageLength: 15,
            lengthMenu: [15, 25, 50, 100],
            columnDefs: [{ targets: 7, className: 'text-right' }]
        });
        initColumnResize('#table-workorder');
    }

    function showLoading(){
        Swal.fire({
            title: 'Memuat data...',
            html: 'Menerapkan filter dan mengambil data terbaru.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
    }

    function hideLoading(){
        Swal.close();
    }

    function showErrorAlert(message){
        Swal.fire({
            icon: 'error',
            title: 'Gagal memuat data',
            text: message || 'Terjadi kesalahan saat mengambil data dari server.',
            confirmButtonColor: '#4e73df'
        });
    }

    function refresh(){
        showLoading();

        const params = buildQueryParams(currentFilters());
        fetch(`${endpoint}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(json => {
                document.getElementById('kpi-qty-order').textContent = fmt(json.summary.total_qty_order);
                document.getElementById('kpi-total-style').textContent = fmt(json.summary.total_style);
                document.getElementById('kpi-belum-order').textContent = fmt(json.summary.total_item_belum_order);

                renderOrderPivot(json.orderPivot);
                renderMaterialPivot(json.materialPivot);
                renderWorkOrderPivot(json.workOrderPivot);

                hideLoading();
            })
            .catch(err => {
                hideLoading();
                showErrorAlert(err.message);
            });
    }

    // Filter sekarang eksplisit lewat tombol, bukan langsung saat dropdown berubah
    fApply.addEventListener('click', refresh);

    fReset.addEventListener('click', () => {
        $('.select2-filter').val(null).trigger('change');
        refresh();
    });

    function escapeHtml(str){
        return String(str).replace(/[&<>"']/g, s => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[s]));
    }

    // Jalankan "php artisan monitoring:sync-bom/-po --year=<tahun berjalan>" lewat AJAX,
    // lalu refresh pivot supaya data yang baru saja disinkronkan langsung terlihat.
    function runSync(url, label, button){
        if (!url) {
            showErrorAlert(`URL endpoint ${label} belum tersedia (cek route monitoring.sync.*).`);
            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `<i class="fas fa-spinner fa-spin fa-sm"></i> ${label}...`;

        Swal.fire({
            title: `Menjalankan ${label}...`,
            html: 'Mengambil data terbaru dari SmartIT (SQL Server). Proses ini bisa memakan waktu beberapa menit, mohon tunggu.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ year: new Date().getFullYear() })
        })
            .then(r => r.json().then(json => ({ ok: r.ok, json })))
            .then(({ ok, json }) => {
                if (!ok || !json.success) {
                    throw new Error(json.message || json.output || `${label} gagal dijalankan.`);
                }

                Swal.fire({
                    icon: 'success',
                    title: `${label} selesai`,
                    html: json.output
                        ? `<pre style="text-align:left; max-height:240px; overflow:auto; font-size:.75rem; white-space:pre-wrap;">${escapeHtml(json.output)}</pre>`
                        : 'Data berhasil disinkronkan dari SmartIT.',
                    confirmButtonColor: '#4e73df'
                });

                refresh();
            })
            .catch(err => {
                Swal.fire({
                    icon: 'error',
                    title: `${label} gagal`,
                    html: `<pre style="text-align:left; max-height:240px; overflow:auto; font-size:.75rem; white-space:pre-wrap;">${escapeHtml(err.message)}</pre>`,
                    confirmButtonColor: '#4e73df'
                });
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
    }

    btnSyncBom.addEventListener('click', () => runSync(syncBomUrl, 'Sync BOM', btnSyncBom));
    btnSyncPo.addEventListener('click', () => runSync(syncPoUrl, 'Sync PO', btnSyncPo));

    /* =========================================================
       Kalender Production Delivery (mon_orders.production_delivery)
       ========================================================= */
    const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const today = new Date();

    // State kalender dikumpulkan di satu object supaya jelas apa yang berubah
    // saat navigasi bulan / pilih tanggal (dan gampang dibaca lintas fungsi).
    const calState = {
        year: today.getFullYear(),
        month: today.getMonth() + 1, // 1-12
        selectedDate: null,
        requestSeq: 0, // dipakai untuk menolak response fetch yang "telat" (out-of-order)
    };

    const calLabel       = document.getElementById('cal-label');
    const calBody        = document.querySelector('#mon-calendar tbody');
    const calPrev        = document.getElementById('cal-prev');
    const calNext        = document.getElementById('cal-next');
    const calDetailEmpty = document.getElementById('cal-detail-empty');
    const calDetailWrap  = document.getElementById('cal-detail-wrap');
    const calDetailTitle = document.getElementById('cal-detail-title');
    let dtCalDetail;

    function pad2(n){ return String(n).padStart(2, '0'); }

    function toIsoDate(y, m, d){ return `${y}-${pad2(m)}-${pad2(d)}`; }

    function buildCalendarQuery(extra){
        const params = buildQueryParams(currentFilters());
        Object.entries(extra || {}).forEach(([k, v]) => params.append(k, v));
        return params;
    }

    function setCalNavDisabled(disabled){
        calPrev.disabled = disabled;
        calNext.disabled = disabled;
    }

    function renderCalendarGrid(year, month, dayMap){
        calLabel.textContent = `${monthNames[month - 1]} ${year}`;

        const firstDow = new Date(year, month - 1, 1).getDay(); // 0=Min
        const daysInMonth = new Date(year, month, 0).getDate();

        let cells = [];
        for (let i = 0; i < firstDow; i++) cells.push(null);
        for (let d = 1; d <= daysInMonth; d++) cells.push(d);
        while (cells.length % 7 !== 0) cells.push(null);

        let html = '';
        for (let w = 0; w < cells.length / 7; w++) {
            html += '<tr>';
            for (let c = 0; c < 7; c++) {
                const d = cells[w * 7 + c];
                if (!d) { html += '<td class="bg-light"></td>'; continue; }

                const iso = toIsoDate(year, month, d);
                const info = dayMap[iso];
                const isToday = iso === toIsoDate(today.getFullYear(), today.getMonth() + 1, today.getDate());
                const isSelected = iso === calState.selectedDate;

                // Selisih hari dari hari ini ke tanggal production_delivery.
                // Negatif = sudah lewat (overdue), 0-7 = mendekati deadline (dianggap urgent juga).
                const diffDays = Math.floor((new Date(year, month - 1, d) - new Date(today.getFullYear(), today.getMonth(), today.getDate())) / 86400000);
                const isUrgent = !!info && diffDays <= 7;

                let cls = 'text-center';
                if (isUrgent) cls += ' bg-danger text-white';
                else if (info) cls += ' bg-warning';
                if (isToday) cls += ' font-weight-bold';

                const style = isSelected
                    ? 'cursor:pointer; vertical-align:middle; box-shadow: inset 0 0 0 3px #4e73df;'
                    : 'cursor:pointer; vertical-align:middle;';

                html += `<td class="${cls}" style="${style}" data-date="${iso}" title="${info ? `${info.jumlah_order} order` : ''}">
                    <div>${d}</div>
                    ${info ? `<span class="badge badge-pill ${isUrgent ? 'badge-light' : 'badge-secondary'}" style="font-size:.65rem;">${info.jumlah_order}</span>` : ''}
                </td>`;
            }
            html += '</tr>';
        }
        calBody.innerHTML = html;

        calBody.querySelectorAll('td[data-date]').forEach(td => {
            td.addEventListener('click', () => selectCalendarDate(td.dataset.date));
        });
    }

    // year/month yang dipakai untuk fetch selalu diambil eksplisit dari argumen
    // (bukan langsung baca calState di dalam .then()), supaya kalau user klik
    // Prev/Next berkali-kali dengan cepat, response yang datang belakangan tidak
    // menimpa tampilan dengan data bulan yang salah (dicek lewat requestSeq).
    function loadCalendarMonth(year, month){
        calState.year = year;
        calState.month = month;

        const seq = ++calState.requestSeq;
        setCalNavDisabled(true);

        const params = buildCalendarQuery({ year, month });
        fetch(`${calendarUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(json => {
                if (seq !== calState.requestSeq) return; // ada request lebih baru, abaikan response ini
                const dayMap = {};
                (json.days || []).forEach(row => { dayMap[row.tanggal] = row; });
                renderCalendarGrid(year, month, dayMap);
            })
            .catch(() => {
                if (seq !== calState.requestSeq) return;
                renderCalendarGrid(year, month, {});
            })
            .finally(() => {
                if (seq === calState.requestSeq) setCalNavDisabled(false);
            });
    }

    function selectCalendarDate(iso){
        calState.selectedDate = iso;
        loadCalendarMonth(calState.year, calState.month); // redraw ulang supaya highlight tanggal terpilih update

        const params = buildCalendarQuery({ date: iso });
        calDetailTitle.textContent = `Production Delivery: ${iso}`;
        calDetailEmpty.classList.add('d-none');
        calDetailWrap.classList.remove('d-none');

        if (dtCalDetail) { dtCalDetail.clear().draw(); }

        fetch(`${calendarDetailUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(json => renderCalendarDetailTable(json.rows || []))
            .catch(() => renderCalendarDetailTable([]));
    }

    function renderCalendarDetailTable(rows){
        if (dtCalDetail) { dtCalDetail.destroy(); dtCalDetail = null; }

        dtCalDetail = $('#table-cal-detail').DataTable({
            language: dtLanguage,
            data: rows,
            pageLength: 5,
            lengthMenu: [5, 10, 25, 50],
            order: [],
            responsive: false,
            autoWidth: false,
            columns: [
                { data: 'uraian', defaultContent: '' },
                { data: 'buyer', defaultContent: '' },
                { data: 'style', defaultContent: '' },
                { data: 'destination', defaultContent: '' },
                { data: 'qty_ord', className: 'right', render: v => fmt(v) },
            ]
        });
        initColumnResize('#table-cal-detail');
    }

    // Navigasi bulan: prev/next tidak pernah "terpaku" di bulan berjalan --
    // calState.year/month selalu diupdate lebih dulu lalu langsung fetch ulang.
    calPrev.addEventListener('click', () => {
        let { year, month } = calState;
        month--;
        if (month < 1) { month = 12; year--; }
        loadCalendarMonth(year, month);
    });
    calNext.addEventListener('click', () => {
        let { year, month } = calState;
        month++;
        if (month > 12) { month = 1; year++; }
        loadCalendarMonth(year, month);
    });

    if (calendarUrl) {
        loadCalendarMonth(calState.year, calState.month);
    }

    // Filter (tombol "Filter") juga me-refresh kalender bulan yang sedang aktif.
    fApply.addEventListener('click', () => {
        if (calendarUrl) loadCalendarMonth(calState.year, calState.month);
    });

    // render awal pakai data yang sudah dikirim server (hindari flash kosong & loading di initial load)
    renderOrderPivot(@json($orderPivot));
    renderMaterialPivot(@json($materialPivot));
    renderWorkOrderPivot(@json($workOrderPivot));
})();
</script>

    <style>
        .chart-area { position: relative; height: 320px; }
        .mon-table-box { max-height: 460px; overflow: auto; }
        .mon-table-box-full { max-height: 560px; }
        .mon-table td.right, .mon-table th.right { text-align: right; }
        .kpi-warn .h5 { color: #b45309; }

        /* Kunci table-layout supaya lebar kolom mengikuti persentase yang didefinisikan
           lewat <colgroup> (bukan menyesuaikan panjang konten) -- kunci utama supaya kolom
           tabel induk (Pivot MATERIAL PURCHASE) dan tabel detail expand-nya selalu lurus. */
        .mon-table-fixed {
            table-layout: fixed;
            border-collapse: collapse; /* sama dengan tabel detail, supaya lebar akhir kolom identik */
        }

        /* Tabel induk pakai table-bordered (border 1px di tiap sel). Tabel detail dibuat
           borderless secara visual, TAPI diberi border transparan dengan lebar yang SAMA
           supaya box-model (dan lebar kolom render akhirnya) tidak bergeser dibanding
           tabel induk -- ini penyebab utama kolom "Satuan"/"Valas" kelihatan tidak lurus
           sebelumnya (lebar sel berbeda beberapa px karena border tabel induk vs tabel
           detail yang tidak setara). */
        #table-material.mon-table-fixed > tbody > tr > td,
        #table-material.mon-table-fixed > thead > tr > th {
            border: 1px solid #e3e6f0;
        }
        .mon-detail-table.mon-table-fixed td {
            border: 1px solid transparent !important;
            border-top: 1px dashed #e3e6f0 !important;
        }

        /* Item & No. PO bisa berisi teks panjang -- dipotong dengan ellipsis (bukan
           melebarkan kolom) supaya tabel tidak berantakan; hover untuk lihat teks penuh. */
        .mon-item-cell, .mon-po-cell {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mon-item-cell span, .mon-po-cell span { display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; }

        /* Select2 - selaraskan tinggi & radius dengan input SB Admin 2 (versi single-select) */
        .select2-container .select2-selection--single {
            height: calc(1.5em + .5rem + 2px);
            padding: .25rem .5rem;
            border-radius: .35rem;
            border: 1px solid #d1d3e2;
        }
        .select2-container--bootstrap-5 .select2-selection__rendered { line-height: 1.6; font-size: .875rem; }
        .select2-container .select2-selection--single .select2-selection__arrow { height: calc(1.5em + .5rem); }
        .select2-container { width: 100% !important; }

        /* Tombol Filter & Reset - lebar sama persis, sejajar tingginya */
        .mon-filter-actions .btn { flex: 1 1 0; white-space: nowrap; }

        /* Pivot MATERIAL PURCHASE - expand/collapse */
        .mon-toggle { cursor: pointer; width: 14px; text-align: center; }
        .mon-detail-table { background: #f8f9fc; }
        .mon-detail-table td { border-top: 1px dashed #e3e6f0 !important; padding: .35rem .5rem; }
        .mon-detail-table td:first-child { padding-left: 2rem; color: #5a5c69; }
        tr.mon-parent-row { cursor: pointer; }
        tr.mon-parent-row:hover { background: #f8f9fc; }

        /* Kolom tabel bisa di-resize manual: garis tipis di kanan tiap header,
           kursor col-resize, highlight biru transparan saat hover/drag. */
        table.dataTable thead th.mon-resizable { position: relative; }
        .mon-col-resizer {
            position: absolute;
            top: 0;
            right: -3px;
            width: 7px;
            height: 100%;
            cursor: col-resize;
            user-select: none;
            z-index: 5;
        }
        .mon-col-resizer:hover, .mon-col-resizer.resizing { background: rgba(78,115,223,.35); }
        /* Saat drag berlangsung, matikan text-selection & override kursor di seluruh halaman
           supaya drag tetap mulus walau mouse sempat lewat di atas elemen lain. */
        body.mon-col-resizing { cursor: col-resize !important; user-select: none !important; }
        body.mon-col-resizing * { cursor: col-resize !important; }

        /* DataTables - selaraskan ukuran font & spacing dengan tema SB Admin 2 */
        .dataTables_wrapper { font-size: .8rem; }
        table.dataTable thead th { background: #f8f9fc; white-space: nowrap; }
        .dataTables_length select, .dataTables_filter input { font-size: .8rem; }
        .dt-buttons-hidden .dataTables_filter { display: none; }
    </style>

</body>
</html>