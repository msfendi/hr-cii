<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Material List Dashboard - hris.chutex.id</title>

    <!-- SB Admin 2 (Bootstrap + Fonts) -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/css/sb-admin-2.min.css">

    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <!-- DataTables (Bootstrap 5 skin) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/css/dataTables.bootstrap5.min.css">

    <style>
        .chart-area { position: relative; height: 320px; }
        .mon-table-box { max-height: 460px; overflow: auto; }
        .mon-table-box-full { max-height: 560px; }
        .mon-table td.right, .mon-table th.right { text-align: right; }
        .kpi-warn .h5 { color: #b45309; }

        /* Select2 - selaraskan tinggi & radius dengan input SB Admin 2 */
        .select2-container .select2-selection--single {
            height: calc(1.5em + .5rem + 2px);
            padding: .25rem .5rem;
            border-radius: .35rem;
            border: 1px solid #d1d3e2;
        }
        .select2-container--bootstrap-5 .select2-selection__rendered { line-height: 1.6; font-size: .875rem; }
        .select2-container .select2-selection--single .select2-selection__arrow { height: calc(1.5em + .5rem); }
        .select2-container { width: 100% !important; }

        /* Select2 - versi multiple: samakan tampilan chip/tag & tinggi minimum dengan input SB Admin 2 */
        .select2-container--bootstrap-5 .select2-selection--multiple {
            min-height: calc(1.5em + .5rem + 2px);
            border-radius: .35rem;
            border: 1px solid #d1d3e2;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            font-size: .8rem;
        }
        /* Tombol X hapus per-item pada select2 multiple sudah bawaan bootstrap-5 theme;
           pastikan cursor pointer & sedikit lebih mudah di-klik di mobile */
        .select2-container--bootstrap-5 .select2-selection__choice__remove {
            cursor: pointer;
        }

        /* Tombol Filter & Reset - lebar sama persis, sejajar tingginya */
        .mon-filter-actions .btn { flex: 1 1 0; white-space: nowrap; }

        /* Pivot MATERIAL PURCHASE - expand/collapse */
        .mon-toggle { cursor: pointer; width: 14px; text-align: center; }
        .mon-detail-table { background: #f8f9fc; }
        .mon-detail-table td { border-top: 1px dashed #e3e6f0 !important; padding: .35rem .5rem; }
        .mon-detail-table td:first-child { padding-left: 2rem; color: #5a5c69; }
        tr.mon-parent-row { cursor: pointer; }
        tr.mon-parent-row:hover { background: #f8f9fc; }

        /* DataTables - selaraskan ukuran font & spacing dengan tema SB Admin 2 */
        .dataTables_wrapper { font-size: .8rem; }
        table.dataTable thead th { background: #f8f9fc; white-space: nowrap; }
        .dataTables_length select, .dataTables_filter input { font-size: .8rem; }
        .dt-buttons-hidden .dataTables_filter { display: none; }
    </style>
</head>
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
                 data-sync-po-url="{{ route('monitoring.sync.po') }}">

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
                                <label class="small font-weight-bold text-gray-600 text-uppercase mb-1">Uraian (CPO)</label>
                                <select id="f-uraian" class="form-control form-control-sm select2-filter" multiple>
                                    @foreach($filterOptions['uraian'] as $v)
                                        <option value="{{ $v }}" @selected(in_array($v, (array) ($filters['uraian'] ?? [])))>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="small font-weight-bold text-gray-600 text-uppercase mb-1">Buyer</label>
                                <select id="f-buyer" class="form-control form-control-sm select2-filter" multiple>
                                    @foreach($filterOptions['buyer'] as $v)
                                        <option value="{{ $v }}" @selected(in_array($v, (array) ($filters['buyer'] ?? [])))>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label class="small font-weight-bold text-gray-600 text-uppercase mb-1">Style</label>
                                <select id="f-style" class="form-control form-control-sm select2-filter" multiple>
                                    @foreach($filterOptions['style'] as $v)
                                        <option value="{{ $v }}" @selected(in_array($v, (array) ($filters['style'] ?? [])))>{{ $v }}</option>
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

                <!-- Pivot 1: ORDER -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pivot ORDER &mdash; Qty Order per Uraian / Buyer / Style</h6>
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
                                    <table class="table table-bordered table-sm mon-table w-100" id="table-order">
                                        <thead><tr><th>Uraian</th><th>Buyer</th><th>Style</th><th class="right">Qty Order</th></tr></thead>
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
                                    <table class="table table-bordered table-sm mon-table w-100" id="table-material">
                                        <thead><tr><th></th><th>Item</th><th class="right">Jumlah Order</th><th class="right">Jumlah Diterima</th><th class="right">Sisa</th></tr></thead>
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
                            <table class="table table-bordered table-sm mon-table w-100" id="table-workorder">
                                <thead>
                                    <tr>
                                        <th>Uraian</th><th>Barang Code</th><th>Nama Barang</th>
                                        <th>Departemen</th><th>Komponen</th><th>Barang Jadi</th>
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

    // Select2 untuk 3 dropdown filter -- multiple, dan tiap item bisa dihapus satu-satu (x)
    // maupun semua sekaligus lewat tombol "clear" (allowClear) di pojok kanan select2.
    $('.select2-filter').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Semua',
        allowClear: true,
        closeOnSelect: false
    });

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
            responsive: false
        }, options));
    }

    function currentFilters(){
        return {
            uraian: $(fUraian).val() || [],
            buyer:  $(fBuyer).val() || [],
            style:  $(fStyle).val() || []
        };
    }

    // uraian/buyer/style sekarang array (multi-select) -> kirim sebagai uraian[]=A&uraian[]=B dst,
    // supaya Laravel otomatis mem-parsingnya jadi array di $request->only(...).
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
                <td class="right" data-order="${Number(r.qty_order) || 0}">${fmt(r.qty_order)}</td>
            </tr>`
        );

        dtOrder = initDataTable('#table-order', {
            columnDefs: [{ targets: 3, className: 'text-right' }]
        });

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

    // Baris detail (breakdown per spesifikasi/warna) yang muncul saat parent row di-expand.
    function renderMaterialDetail(details){
        if (!details || !details.length) {
            return '<div class="text-muted small px-3 py-2">Tidak ada rincian spesifikasi.</div>';
        }
        const body = details.map(d => {
            const label = (d.spesifikasi && String(d.spesifikasi).trim()) ? d.spesifikasi : '(Tanpa Spesifikasi)';
            return `<tr>
                <td>${label}</td>
                <td class="right">${fmtQty(d.jumlah_order)}</td>
                <td class="right">${fmtQty(d.jumlah_diterima)}</td>
                <td class="right">${fmtSisa(d.sisa)}</td>
            </tr>`;
        }).join('');

        return `<table class="table table-sm table-borderless mb-0 mon-detail-table w-100">
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
            columns: [
                {
                    data: null, orderable: false, className: 'mon-toggle-cell text-center',
                    render: (data, type, row) => (row.details && row.details.length)
                        ? '<i class="fas fa-plus-square text-primary mon-toggle"></i>'
                        : ''
                },
                { data: null, render: r => r.barang_name || r.barang_code || '' },
                { data: 'jumlah_order', className: 'right', render: v => fmtQty(v) },
                { data: 'jumlah_diterima', className: 'right', render: v => fmtQty(v) },
                { data: 'sisa', className: 'right', render: v => fmtSisa(v) },
            ]
        });

        // Delegasikan klik ke elemen tabel (stabil lintas destroy/redraw), lalu bersihkan
        // binding lama dulu supaya tidak dobel setiap kali renderMaterialPivot dipanggil ulang.
        $('#table-material').off('click.monToggle').on('click.monToggle', 'td.mon-toggle-cell, td:nth-child(2)', function(){
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
                <td class="right" data-order="${Number(r.total_cons) || 0}">${fmtQty(r.total_cons, 4)}</td>
            </tr>`
        );

        dtWorkOrder = initDataTable('#table-workorder', {
            pageLength: 15,
            lengthMenu: [15, 25, 50, 100],
            columnDefs: [{ targets: 6, className: 'text-right' }]
        });
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

    // render awal pakai data yang sudah dikirim server (hindari flash kosong & loading di initial load)
    renderOrderPivot(@json($orderPivot));
    renderMaterialPivot(@json($materialPivot));
    renderWorkOrderPivot(@json($workOrderPivot));
})();
</script>

</body>
</html>