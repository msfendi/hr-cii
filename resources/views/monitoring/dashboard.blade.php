<!DOCTYPE html>
<html lang="id">
@include('layout.header')
<body id="page-top" class="rekon-page">

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
                 data-sync-rekon-url="{{ route('monitoring.rekonsiliasi.sync') }}"
                 data-sync-prodline-url="{{ route('monitoring.rekonsiliasi.sync-prod-line') }}"
                 data-sync-shipment-url="{{ route('monitoring.rekonsiliasi.sync-shipment') }}"
                 data-sync-workorder-url="{{ route('monitoring.rekonsiliasi.sync-work-order') }}"
                 data-sync-ms-barang-url="{{ route('monitoring.rekonsiliasi.sync-ms-barang') }}"
                 data-sync-ms-negara-url="{{ route('monitoring.rekonsiliasi.sync-ms-negara') }}"
                 data-sync-ms-supplier-url="{{ route('monitoring.rekonsiliasi.sync-ms-supplier') }}">

                {{-- ================= HEADER BAR (mirip DASHBOARD RECONCILIATION) ================= --}}
                <div class="rekon-hero shadow mb-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between">
                        <div class="rekon-hero-title">
                            <i class="fas fa-boxes mr-2"></i> MATERIAL LIST DASHBOARD
                        </div>
                        <div class="d-flex align-items-center flex-wrap" style="gap:16px">
                            <div class="rekon-search input-group input-group-sm">
                                {{-- Keempat select di bawah ini BOLAK-BALIK saling menyaring: opsi
                                     masing-masing sudah di-cascade dari server berdasarkan filter lain
                                     yang aktif (lihat MonitoringDashboardService::cascadedFilterOptions()),
                                     dan akan di-refresh lagi setiap kali salah satu select berubah
                                     (lihat applyCascadedFilterOptions() di script bawah). Urutan:
                                     Brand, Style, OCF, Uraian (CPO). --}}
                                <select id="f-brand" class="form-control select2-filter" style="min-width:150px" data-placeholder="Cari Brand...">
                                    <option value=""></option>
                                    @foreach($brandOptions as $b)
                                        <option value="{{ $b }}" @selected(($filters['brand'] ?? null) === $b)>{{ $b }}</option>
                                    @endforeach
                                </select>
                                <select id="f-style" class="form-control select2-filter" style="min-width:150px" data-placeholder="Cari Style...">
                                    <option value=""></option>
                                    @foreach($styleOptions as $s)
                                        <option value="{{ $s }}" @selected(($filters['style'] ?? null) === $s)>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <select id="f-ocf" class="form-control select2-filter" style="min-width:180px" data-placeholder="Cari OCF...">
                                    <option value=""></option>
                                    @foreach($ocfOptions as $o)
                                        <option value="{{ $o }}" @selected(($filters['ocf'] ?? null) === $o)>{{ $o }}</option>
                                    @endforeach
                                </select>
                                <select id="f-uraian" class="form-control select2-filter" style="min-width:220px" data-placeholder="Cari Uraian (CPO)...">
                                    <option value=""></option>
                                    @foreach($uraianOptions as $v)
                                        <option value="{{ $v }}" @selected(($filters['uraian'] ?? null) === $v)>{{ $v }}</option>
                                    @endforeach
                                </select>
                                {{-- Tombol reset EKSPLISIT: tidak bergantung pada ikon "x" bawaan
                                     select2 (klik "x" terbukti tidak selalu benar-benar mengosongkan
                                     value <select> di DOM setelah option-nya di-rebuild lewat
                                     populateSelect()). Tombol ini memaksa keempat select ke null lewat
                                     API resmi select2 (.val(null).trigger('change')) lalu memanggil
                                     refresh() langsung, supaya clear filter selalu deterministik. Sama
                                     seperti #btn-reset-filter di rekonsiliasi_blade.php. --}}
                                <button type="button" id="btn-reset-filter" class="btn btn-outline-light btn-sm" title="Reset semua filter">
                                    <i class="fas fa-times"></i> Reset Filter
                                </button>
                            </div>
                            <div class="text-white small">
                                <div class="text-uppercase" style="opacity:.75">Last Updated</div>
                                <div class="font-weight-bold" id="mon-last-updated">--</div>
                            </div>
                            <div class="d-flex" style="gap:6px">
                                @php $canSyncAny = false; @endphp
                                @canRoute('monitoring.sync.bom')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.sync.po')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-prod-line')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-shipment')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-work-order')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-ms-barang')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-ms-negara')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @canRoute('monitoring.rekonsiliasi.sync-ms-supplier')
                                    @php $canSyncAny = true; @endphp
                                @endcanRoute
                                @if($canSyncAny)
                                    <button id="btn-sync-all" type="button" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-sync-alt fa-sm"></i> Sync All Data
                                    </button>
                                @endif
                                @canRoute('monitoring.order.import.form')
                                    <a href="{{ route('monitoring.order.import.form') }}" class="btn btn-outline-light btn-sm">
                                        <i class="fas fa-file-upload fa-sm"></i> Upload Sheet ORDER
                                    </a>
                                @endcanRoute
                            </div>
                        </div>
                    </div>
                    <div class="rekon-hero-sub mt-2">
                        BRAND : <span id="hdr-brand">-</span>
                        &nbsp;|&nbsp; STYLE <span id="hdr-style">-</span>
                        &nbsp;|&nbsp; URAIAN (CPO) <span id="hdr-uraian">-</span>
                        &nbsp;|&nbsp; OCF <span id="hdr-ocf">-</span>
                    </div>
                </div>

                {{-- ================= EMPTY STATE: tampil sebelum ada filter dipilih ================= --}}
                <div id="mon-empty-notice" class="card shadow mb-4" style="display:none">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-filter fa-2x mb-3 d-block"></i>
                        <div class="font-weight-bold mb-1">Belum ada filter yang dipilih</div>
                        <div class="small">
                            Pilih salah satu atau kombinasi dari <strong>Brand</strong>, <strong>Style</strong>, atau
                            <strong>Uraian (CPO)</strong> pada kolom filter di atas untuk menampilkan data
                            Material List Dashboard (ORDER &middot; PO &middot; BOM). Data tidak dimuat otomatis
                            saat halaman dibuka.
                        </div>
                    </div>
                </div>

                {{-- ================= WIDGET DATA: disembunyikan sampai filter dipilih ================= --}}
                <div id="mon-widgets" style="display:none">

                {{-- ================= Pivot 1: ORDER ================= --}}
                <div class="card shadow mb-4">
                    <div class="card-header py-3 mon-card-header-dark">
                        <h6 class="m-0 font-weight-bold">Pivot ORDER &mdash; Qty Garment per Uraian / Brand / Style</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm w-100" id="table-order">
                                <thead>
                                    <tr>
                                        <th>Uraian</th><th>Destination</th>
                                        <th>Estimasi Shipment</th><th class="right">Qty Order(pcs)</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <th id="foot-total-cpo">Total CPO: 0</th>
                                        <th id="foot-total-negara">Total Negara: 0</th>
                                        <th></th>
                                        <th class="text-right" id="foot-total-qty">0</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ================= Pivot 2: MATERIAL PURCHASE ================= --}}
                <div class="card shadow mb-4">
                    <div class="card-header py-3 mon-card-header-dark d-flex flex-wrap align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold">Pivot MATERIAL PURCHASE &mdash; Jenis PO: PO / Material Supply</h6>
                        <div class="d-flex align-items-center mt-2 mt-md-0">
                            <span class="mon-sort-label">Urutan</span>
                            <select id="f-material-sort" class="form-control form-control-sm">
                                <option value="color" selected>Sort by Color (Belum Diterima Dulu)</option>
                                <option value="default">Urutan Default (Kode Barang)</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm w-100" id="table-material">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Kode Barang</th>
                                        <th>Item</th>
                                        <th class="mon-col-nopo">No. PO</th>
                                        <th>Tgl Pengiriman</th>
                                        <th>Satuan</th>
                                        <th>Valas</th>
                                        <th class="right">Harga Satuan</th>
                                        <th class="right">Harga Total</th>
                                        <th class="right">Jumlah Order</th>
                                        <th class="right">Jumlah Diterima</th>
                                        <th class="right">% Diterima</th>
                                        <th class="right">Sisa</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ================= Pivot 3: WORK ORDER ================= --}}
                <div class="card shadow mb-4">
                    <div class="card-header py-3 mon-card-header-dark">
                        <h6 class="m-0 font-weight-bold">Pivot WORK ORDER &mdash; Item BOM yang Belum Diorder (ada di BOM, belum ada di PO)</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm w-100" id="table-workorder">
                                <thead>
                                    <tr>
                                        <th>Uraian</th><th>Barang Code</th><th>Nama Barang</th>
                                        <th>Departemen</th><th>Komponen</th><th>Barang Jadi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                </div>
                <!-- /#mon-widgets -->

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

<!-- DataTables -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- CSS Tambahan untuk sticky header + perbaikan border alignment -->
<style>
    /* Warna header tabel (DataTables): gelap dengan teks putih, konsisten
       dengan warna header card (lihat .mon-card-header-dark di bawah). */
    .mon-scroll-box table thead th {
        position: sticky;
        top: 0;
        background: #1b3a5c;
        color: #fff;
        z-index: 10;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
    }
    .mon-scroll-box .dataTables_scrollHeadInner table thead th {
        position: sticky;
        top: 0;
        background: #1b3a5c;
        color: #fff;
        z-index: 10;
    }
    .mon-scroll-box table {
        border-collapse: separate;
        border-spacing: 0;
    }
    .mon-scroll-box table thead th {
        border-bottom: 2px solid #0f2438;
    }
    /* Ikon sort bawaan DataTables (panah atas/bawah) dibuat terang supaya
       tetap terlihat di atas header gelap. */
    .mon-scroll-box table thead th.sorting:after,
    .mon-scroll-box table thead th.sorting_asc:after,
    .mon-scroll-box table thead th.sorting_desc:after {
        opacity: .6;
    }

    /* Font data di semua DataTables dibuat hitam pekat, bukan abu-abu semi
       transparan seperti default Bootstrap/SB-Admin2. */
    .mon-scroll-box table.dataTable tbody td,
    .child-detail-table tbody td {
        color: #1a1a1a !important;
    }

    /* PERBAIKAN CHILD ROW MATERIAL PURCHASE */
    .child-detail-table td {
        padding: 0.25rem 0.5rem !important;
        border: 1px solid #e3e6f0 !important;
        vertical-align: middle;
    }
    .dataTables_child .child-row td {
        padding: 0 !important;
        /* Border kiri/kanan pada <td> pembungkus child dihapus supaya lebar
           yang tersedia untuk tabel child persis sama dengan lebar konten
           kolom parent (colgroup dihitung dari lebar <td> parent) -- kalau
           border ini tetap ada, tabel child jadi sedikit lebih sempit dan
           kolomnya "geser" dibanding parent, terutama makin terasa di
           kolom-kolom sebelah kanan. */
        border-left: none !important;
        border-right: none !important;
    }
    .child-detail-table {
        table-layout: fixed;
    }
    .right {
        text-align: right;
    }
    .table-responsive table.dataTable {
        width: 100% !important;
    }

    .mon-col-nopo {
        min-width: 180px;
    }

    /* Header card gelap (Pivot ORDER / MATERIAL PURCHASE / WORK ORDER),
       senada dengan warna header tabel di atas. */
    .mon-card-header-dark {
        background: #1b3a5c;
        border-bottom: 0;
    }
    .mon-card-header-dark h6 {
        color: #fff !important;
        text-transform: uppercase;
        letter-spacing: .5px;
    }
    .mon-card-header-dark .mon-sort-label {
        color: rgba(255,255,255,.75);
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-right: 6px;
    }
    #f-material-sort {
        min-width: 230px;
    }

    .rekon-hero {
        background: linear-gradient(135deg, #0b3d5c, #123f60);
        color: #fff;
        border-radius: .5rem;
        padding: 16px 22px;
    }
    .rekon-hero-title {
        font-size: 1.25rem;
        font-weight: 800;
        letter-spacing: .5px;
    }
    .rekon-hero-sub {
        font-size: .8rem;
        opacity: .9;
    }

    .rekon-search {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
    }
    .rekon-search.input-group {
        flex-wrap: nowrap;
        width: auto;
    }
    .rekon-search .select2-container {
        flex: 1 1 auto;
        width: 1% !important;
        min-width: 150px;
    }
    .rekon-search .select2-container .select2-selection--single {
        height: calc(1.5em + .5rem + 2px);
        border-radius: 0;
        display: flex;
        align-items: center;
    }
    .rekon-search .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 1.2;
    }
    .rekon-search .select2-container .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + .5rem);
    }

    .rekon-kpi .card-body {
        padding: 1rem .75rem;
    }

    /* =========================================================
       IMPOR CSS SELECT2 (tanpa tema tambahan)
       ========================================================= */
    @import url('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');

    .select2-container .select2-selection--single {
        height: calc(1.5em + .5rem + 2px);
        padding: 0.25rem 0.5rem;
        border-radius: .35rem;
        border: 1px solid #d1d3e2;
        display: flex;
        align-items: center;
    }
    .select2-container .select2-selection__rendered {
        padding: 0 !important;
        line-height: 1.6;
        font-size: .875rem;
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .select2-container .select2-selection__arrow {
        height: 100% !important;
        top: 0 !important;
        right: 0 !important;
        transform: none !important;
        display: flex;
        align-items: center;
        padding-right: 6px;
    }
    .select2-dropdown {
        border: 1px solid #d1d3e2;
        border-radius: .35rem;
        z-index: 9999;
    }
    .select2-results__option {
        padding: 0.25rem 0.75rem !important;
        font-size: .875rem;
    }
    .select2-results__option--highlighted {
        background-color: #4e73df !important;
        color: #fff !important;
    }
    .select2-search--dropdown .select2-search__field {
        padding: .25rem .5rem;
        font-size: .875rem;
        border: 1px solid #d1d3e2;
        border-radius: .35rem;
    }

    .mon-toggle {
        cursor: pointer;
    }

    /* =========================================================
       PERBAIKAN ALIGNMENT BORDER THEAD/TBODY/TFOOT
       DataTables dengan scrollY + scrollX sering membuat
       border tidak lurus. Solusi: force border-collapse
       dan gunakan width 100% + table-layout fixed.
       ========================================================= */
    .mon-scroll-box .dataTable {
        border-collapse: collapse !important;
        table-layout: fixed !important;
        width: 100% !important;
    }
    .mon-scroll-box .dataTable thead th,
    .mon-scroll-box .dataTable tbody td,
    .mon-scroll-box .dataTable tfoot th {
        border: 1px solid #d1d3e2 !important;
    }
    .mon-scroll-box .dataTables_scrollHead,
    .mon-scroll-box .dataTables_scrollBody,
    .mon-scroll-box .dataTables_scrollFoot {
        width: 100% !important;
    }
    .mon-scroll-box .dataTables_scrollHeadInner,
    .mon-scroll-box .dataTables_scrollFootInner {
        width: 100% !important;
    }
    .mon-scroll-box .dataTables_scrollHeadInner table,
    .mon-scroll-box .dataTables_scrollFootInner table {
        margin: 0 !important;
        width: 100% !important;
        table-layout: fixed !important;
    }
    .mon-scroll-box .dataTables_scrollBody table {
        margin: 0 !important;
        width: 100% !important;
        table-layout: fixed !important;
    }
    /* Hilangkan padding kosong di dalam scroll wrapper agar
       tidak mempengaruhi perhitungan lebar kolom */
    .mon-scroll-box .dataTables_scrollBody {
        padding: 0 !important;
    }
    /* Pastikan footer tidak melorot */
    .mon-scroll-box .dataTables_scrollFoot {
        border-top: 2px solid #1b3a5c;
    }
</style>

<script>
(function(){
    // PENANDA VERSI SEMENTARA untuk memastikan file ini yang benar-benar
    // dimuat browser (bukan versi cache lama). Kalau baris ini TIDAK muncul
    // di console setelah hard refresh, berarti server masih menyajikan file
    // blade yang lama -- cek cache Blade (`php artisan view:clear`), cache
    // OPcache PHP, atau CDN/reverse-proxy di depan aplikasi.

    const app = document.getElementById('mon-app');
    const endpoint = app.dataset.endpoint;
    const syncBomUrl = app.dataset.syncBomUrl;
    const syncPoUrl = app.dataset.syncPoUrl;
    const syncRekonUrl = app.dataset.syncRekonUrl;
    const syncProdlineUrl = app.dataset.syncProdlineUrl;
    const syncShipmentUrl = app.dataset.syncShipmentUrl;
    const syncWorkOrderUrl = app.dataset.syncWorkorderUrl;
    const syncMsBarangUrl = app.dataset.syncMsBarangUrl;
    const syncMsNegaraUrl = app.dataset.syncMsNegaraUrl;
    const syncMsSupplierUrl = app.dataset.syncMsSupplierUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const fBrand  = document.getElementById('f-brand');
    const fStyle  = document.getElementById('f-style');
    const fUraian = document.getElementById('f-uraian');
    const fOcf    = document.getElementById('f-ocf');
    const fMaterialSort = document.getElementById('f-material-sort');
    const emptyNotice = document.getElementById('mon-empty-notice');
    const widgets = document.getElementById('mon-widgets');
    const btnSyncAll = document.getElementById('btn-sync-all');

    let dtOrder, dtMaterial, dtWorkOrder;
    let lastMaterialRows = [];

    // ================================================================
    // SELECT2 – dipasang pada semua select filter di dalam .rekon-search
    // ================================================================
    $('.select2-filter').each(function () {
        $(this).select2({ width: '100%', placeholder: $(this).data('placeholder') || 'Semua', allowClear: true });
    });

    /* =========================================================
       Cascading Select2 DUA ARAH: Brand / Style / OCF / Uraian (CPO)
       Keempat select ini SUDAH dipopulasi server-side saat halaman
       pertama kali dirender (lihat @@foreach di HTML di atas, dari
       MonitoringDashboardService::cascadedFilterOptions()). Untuk update
       selanjutnya (tiap kali salah satu select berubah), opsi keempatnya
       di-refresh lagi dari response endpoint data() lewat
       applyCascadedFilterOptions() -- sama seperti pola di
       rekonsiliasi_blade.php.
       ========================================================= */
    function populateSelect($el, values) {
        const current = $el.val();
        $el.empty().append('<option value=""></option>');
        values.forEach(v => $el.append(new Option(v, v, false, false)));
        $el.val(values.includes(current) ? current : '').trigger('change');
    }

    /**
     * Update KEEMPAT dropdown filter (Brand/Style/OCF/Uraian) sekaligus dari
     * hasil cascade server (lihat MonitoringDashboardService::
     * cascadedFilterOptions(), dikirim controller di key brandOptions/
     * styleOptions/ocfOptions/uraianOptions pada tiap response endpoint
     * data()). Ini yang membuat filter BOLAK-BALIK: pilih OCF -> Brand/
     * Style/Uraian ikut menyaring; pilih Style -> Brand/OCF/Uraian ikut
     * menyaring; dst -- pilihan yang masih valid tetap dipertahankan, yang
     * sudah tidak valid otomatis ke-clear karena tidak ada lagi di daftar
     * opsi baru.
     */
    function applyCascadedFilterOptions(json) {
        if (Array.isArray(json.brandOptions)) populateSelect($(fBrand), json.brandOptions);
        if (Array.isArray(json.styleOptions)) populateSelect($(fStyle), json.styleOptions);
        if (Array.isArray(json.ocfOptions)) populateSelect($(fOcf), json.ocfOptions);
        if (Array.isArray(json.uraianOptions)) populateSelect($(fUraian), json.uraianOptions);
    }

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

    function currentFilters(){
        return {
            uraian: fUraian.value || '',
            brand:  fBrand.value || '',
            style:  fStyle.value || '',
            ocf:    fOcf.value || ''
        };
    }

    function buildQueryParams(filters){
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, val]) => {
            if (val !== '' && val !== null && val !== undefined) params.append(key, val);
        });
        return params;
    }

    function fmt(n){ return Number(n || 0).toLocaleString('id-ID'); }

    function fmtQty(n, digits){
        digits = digits === undefined ? 2 : digits;
        return Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    }

    function fmtSisa(n, digits){
        digits = digits === undefined ? 2 : digits;
        const num = Number(n || 0);
        return Math.abs(num) < 0.00001 ? '-' : fmtQty(num, digits);
    }

    function fmtCurrency(n, valas){
        const num = Number(n || 0);
        const code = String(valas || '').toUpperCase().trim();

        if (!code || code.indexOf(',') !== -1) {
            return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (code === 'USD') {
            return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (code === 'YEN') {
            return '¥' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (code === 'IDR' || code === 'RP') {
            return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
        return code + ' ' + num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const bulanNama = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    function formatTanggalIndonesia(iso){
        if (!iso) return '-';
        const parts = String(iso).slice(0,10).split('-');
        if (parts.length !== 3) return String(iso);
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        const d = parseInt(parts[2], 10);
        if (isNaN(y) || isNaN(m) || isNaN(d)) return String(iso);
        return `${d} ${bulanNama[m-1]} ${y}`;
    }

    function fmtDate(d){
        if (!d) return '-';
        const iso = String(d).slice(0, 10);
        return formatTanggalIndonesia(iso);
    }

    function escapeHtml(str){
        return String(str).replace(/[&<>"']/g, s => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[s]));
    }

    function fillTable(tbodySelector, rows, rowRenderer){
        const tbody = document.querySelector(tbodySelector);
        tbody.innerHTML = rows.map(rowRenderer).join('');
    }

    // ================================================================
    // Fungsi bantu untuk menyesuaikan alignment DataTables
    // ================================================================
    function adjustDataTableColumns(dtInstance) {
        if (dtInstance && typeof dtInstance.columns === 'function') {
            dtInstance.columns.adjust().draw();
        }
    }

    // ================================================================
    // Pivot ORDER
    // ================================================================
    function renderOrderPivot(rows){
        if (dtOrder) { dtOrder.destroy(); dtOrder = null; }
        $('#table-order tbody').empty();

        dtOrder = $('#table-order').DataTable({
            language: dtLanguage,
            data: rows,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[2, 'asc']],
            autoWidth: false,
            width: '100%',
            scrollY: '360px',
            scrollX: true,           // PERBAIKAN: scrollX agar kolom konsisten
            scrollCollapse: true,
            fixedHeader: true,
            columns: [
                { data: 'uraian', defaultContent: '' },
                { data: 'destination', defaultContent: '' },
                {
                    data: 'estimasi_shipment',
                    render: (v, type) => {
                        if (type === 'display') return fmtDate(v);
                        return v ? String(v).slice(0, 10) : '';
                    }
                },
                {
                    data: 'qty_order', className: 'right',
                    render: (v, type) => type === 'display' ? fmt(v) : (Number(v) || 0)
                },
            ],
            footerCallback: function(){
                const api = this.api();
                const cpoSet = new Set();
                const negaraSet = new Set();
                let sumQty = 0;
                api.rows({ search: 'applied' }).data().each(function(r){
                    if (r.uraian) cpoSet.add(r.uraian);
                    if (r.destination) negaraSet.add(r.destination);
                    sumQty += Number(r.qty_order || 0);
                });
                document.getElementById('foot-total-cpo').textContent = `Total CPO: ${fmt(cpoSet.size)}`;
                document.getElementById('foot-total-negara').textContent = `Total Negara: ${fmt(negaraSet.size)}`;
                document.getElementById('foot-total-qty').textContent = fmt(sumQty);
            },
        });
        $('#table-order').closest('.table-responsive').addClass('mon-scroll-box');

        // PERBAIKAN: sesuaikan kolom setelah inisialisasi dan setiap draw
        adjustDataTableColumns(dtOrder);
        dtOrder.on('draw', function() {
            adjustDataTableColumns(this);
        });
    }

    // ================================================================
    // DETAIL CHILD UNTUK MATERIAL PURCHASE
    // ================================================================
    function buildChildColgroup(parentTr){
        const widths = [];
        parentTr.find('> td').each(function(){
            widths.push(this.getBoundingClientRect().width);
        });
        if (!widths.length) return '';
        return `<colgroup>${widths.map(w => `<col style="width:${w}px">`).join('')}</colgroup>`;
    }

    function sortDetailsByColor(details){
        return [...details].sort((a, b) => {
            const aRed = Number(a.sisa) > 0.00001 ? 1 : 0;
            const bRed = Number(b.sisa) > 0.00001 ? 1 : 0;
            return bRed - aRed;
        });
    }

    function renderMaterialDetail(details, colgroupHtml){
        if (!details || !details.length) {
            return '<div class="text-muted small px-3 py-2">Tidak ada rincian spesifikasi.</div>';
        }
        const body = details.map(d => {
            const label = (d.spesifikasi && String(d.spesifikasi).trim()) ? d.spesifikasi : '(Tanpa Spesifikasi)';
            const rowClass = Number(d.sisa) > 0.00001 ? 'table-danger' : '';
            const order = Number(d.jumlah_order) || 0;
            const diterima = Number(d.jumlah_diterima) || 0;
            const pct = order > 0 ? (diterima / order * 100) : 0;
            return `<tr class="${rowClass}">
                <td colspan="3" class="px-2 py-1">${escapeHtml(label)}</td>
                <td class="px-2 py-1">${d.no_po ?? '-'}</td>
                <td class="px-2 py-1">${fmtDate(d.tgl_pengiriman)}</td>
                <td class="px-2 py-1">${d.satuan_order ?? '-'}</td>
                <td class="px-2 py-1">${d.valas ?? '-'}</td>
                <td class="px-2 py-1 text-right">${fmtCurrency(d.harga_satuan, d.valas)}</td>
                <td class="px-2 py-1 text-right">${fmtCurrency(d.harga_total, d.valas)}</td>
                <td class="px-2 py-1 text-right">${fmtQty(d.jumlah_order)}</td>
                <td class="px-2 py-1 text-right">${fmtQty(d.jumlah_diterima)}</td>
                <td class="px-2 py-1 text-right">${fmtQty(pct)}%</td>
                <td class="px-2 py-1 text-right">${fmtSisa(d.sisa)}</td>
            </tr>`;
        }).join('');

        return `<table class="table table-sm table-bordered mb-0 w-100 child-detail-table" style="background:#f8f9fc; border-collapse:collapse; margin:0;">
            ${colgroupHtml || ''}
            <tbody>${body}</tbody>
        </table>`;
    }

    function resyncOpenMaterialChildren(){
        if (!dtMaterial) return;
        dtMaterial.rows().every(function(){
            const row = this;
            if (!row.child || !row.child.isShown()) return;
            const tr = $(row.node());
            const colgroupHtml = buildChildColgroup(tr);
            $(row.child()).find('colgroup').replaceWith(colgroupHtml);
        });
    }

    function rerenderOpenMaterialChildren(){
        if (!dtMaterial) return;
        dtMaterial.rows().every(function(){
            const row = this;
            if (!row.child || !row.child.isShown()) return;
            const tr = $(row.node());
            const data = row.data();
            if (!data || !data.details) return;
            row.child(renderMaterialDetail(sortDetailsByColor(data.details), buildChildColgroup(tr))).show();
        });
    }

    let materialResizeTimer = null;
    $(window).on('resize', function(){
        clearTimeout(materialResizeTimer);
        materialResizeTimer = setTimeout(resyncOpenMaterialChildren, 150);
    });

    function pctDiterima(row){
        const order = Number(row.jumlah_order) || 0;
        const diterima = Number(row.jumlah_diterima) || 0;
        return order > 0 ? (diterima / order * 100) : 0;
    }

    function currentMaterialOrder(){
        return (fMaterialSort && fMaterialSort.value === 'default') ? [[1, 'asc']] : [[12, 'desc']];
    }

    // ================================================================
    // Pivot MATERIAL PURCHASE
    // ================================================================
    function renderMaterialPivot(rows){
        lastMaterialRows = rows;
        if (dtMaterial) { dtMaterial.destroy(); dtMaterial = null; }

        dtMaterial = $('#table-material').DataTable({
            language: dtLanguage,
            data: rows,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: currentMaterialOrder(),
            autoWidth: false,
            width: '100%',
            scrollY: '420px',
            scrollX: true,           // PERBAIKAN: scrollX agar kolom konsisten
            scrollCollapse: true,
            fixedHeader: true,
            columns: [
                {
                    data: null, orderable: false, className: 'text-center',
                    render: (data, type, row) => (row.details && row.details.length)
                        ? '<i class="fas fa-plus-square text-primary mon-toggle"></i>'
                        : ''
                },
                { data: 'barang_code', defaultContent: '-' },
                {
                    data: null,
                    render: (r, type) => {
                        const name = r.barang_name || r.barang_code || '';
                        return type === 'display' ? `<span title="${escapeHtml(name)}">${escapeHtml(name)}</span>` : name;
                    }
                },
                {
                    data: 'no_po', defaultContent: '-', width: '180px',
                    render: (v, type) => {
                        const val = v || '-';
                        return type === 'display' ? `<span title="${escapeHtml(val)}">${escapeHtml(val)}</span>` : val;
                    }
                },
                { data: null, defaultContent: '-', className: 'text-center', render: () => '-' },
                { data: 'satuan_order', defaultContent: '-' },
                { data: 'valas', defaultContent: '-' },
                { data: null, className: 'right', render: r => fmtCurrency(r.harga_satuan, r.valas) },
                { data: null, className: 'right', render: r => fmtCurrency(r.harga_total, r.valas) },
                { data: 'jumlah_order', className: 'right', render: v => fmtQty(v) },
                { data: 'jumlah_diterima', className: 'right', render: v => fmtQty(v) },
                { data: null, className: 'right', render: r => fmtQty(pctDiterima(r)) + '%' },
                { data: 'sisa', className: 'right', render: v => fmtSisa(v) },
            ],
            createdRow: (row, data) => {
                if (Number(data.sisa) > 0.00001) {
                    row.classList.add('table-danger');
                }
            }
        });
        $('#table-material').closest('.table-responsive').addClass('mon-scroll-box');

        // PERBAIKAN: sesuaikan kolom setelah inisialisasi
        adjustDataTableColumns(dtMaterial);
        dtMaterial.on('draw.dt', function() {
            adjustDataTableColumns(this);
            resyncOpenMaterialChildren();
        });

        // Delegasikan klik ke elemen tabel
        $('#table-material').off('click.monToggle').on('click.monToggle', 'td:first-child, td:nth-child(3)', function(){
            const tr = $(this).closest('tr');
            const row = dtMaterial.row(tr);
            const data = row.data();
            if (!data || !data.details || !data.details.length) return;

            const icon = tr.find('.mon-toggle');
            if (row.child.isShown()) {
                row.child.hide();
                icon.removeClass('fa-minus-square').addClass('fa-plus-square');
            } else {
                row.child(renderMaterialDetail(sortDetailsByColor(data.details), buildChildColgroup(tr))).show();
                icon.removeClass('fa-plus-square').addClass('fa-minus-square');
            }
        });
    }

    // ================================================================
    // Pivot WORK ORDER
    // ================================================================
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
            </tr>`
        );

        dtWorkOrder = $('#table-workorder').DataTable({
            language: dtLanguage,
            pageLength: 15,
            lengthMenu: [15, 25, 50, 100],
            order: [],
            autoWidth: false,
            width: '100%',
            scrollY: '420px',
            scrollX: true,           // PERBAIKAN: scrollX agar kolom konsisten
            scrollCollapse: true,
            fixedHeader: true,
        });
        $('#table-workorder').closest('.table-responsive').addClass('mon-scroll-box');

        // PERBAIKAN: sesuaikan kolom setelah inisialisasi dan setiap draw
        adjustDataTableColumns(dtWorkOrder);
        dtWorkOrder.on('draw', function() {
            adjustDataTableColumns(this);
        });
    }

    // ================================================================
    // LOADING / ERROR
    // ================================================================
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

    function renderHeaderLabels(){
        document.getElementById('hdr-brand').textContent = fBrand.value || '-';
        document.getElementById('hdr-style').textContent = fStyle.value || '-';
        document.getElementById('hdr-uraian').textContent = fUraian.value || '-';
        document.getElementById('hdr-ocf').textContent = fOcf.value || '-';
        document.getElementById('mon-last-updated').textContent = new Date().toLocaleString('id-ID', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
    }

    // ================================================================
    // REFRESH DATA
    // ================================================================
    function refresh(){
        const filters = currentFilters();
        const hasAnyFilter = !!(filters.uraian || filters.brand || filters.style || filters.ocf);

        if (!hasAnyFilter) {
            widgets.style.display = 'none';
            emptyNotice.style.display = '';
            document.getElementById('mon-last-updated').textContent = '-';
            document.getElementById('hdr-brand').textContent = '-';
            document.getElementById('hdr-style').textContent = '-';
            document.getElementById('hdr-uraian').textContent = '-';
            document.getElementById('hdr-ocf').textContent = '-';
        } else {
            emptyNotice.style.display = 'none';
            widgets.style.display = '';
            renderHeaderLabels();
            showLoading();
        }

        // Fetch TETAP dijalankan meski belum ada filter sama sekali --
        // payload widget-nya kosong (lihat
        // MonitoringDashboardController::emptyPayload()), tapi dropdown
        // Brand/Style/OCF/Uraian tetap dikirim balik supaya keempat select
        // bisa direset/di-cascade ulang dengan benar (bolak-balik) setiap
        // kali salah satu filter berubah, termasuk saat filter dikosongkan
        // lagi lewat tombol Reset Filter. Sama seperti refresh() di
        // rekonsiliasi_blade.php.
        const params = buildQueryParams(filters);
        fetch(`${endpoint}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(json => {
                applyCascadedFilterOptions(json);

                if (hasAnyFilter) {
                    renderOrderPivot(json.orderPivot);
                    renderMaterialPivot(json.materialPivot);
                    renderWorkOrderPivot(json.workOrderPivot);
                    hideLoading();
                }
            })
            .catch(err => {
                if (hasAnyFilter) {
                    hideLoading();
                    showErrorAlert(err.message);
                }
            });
    }

    // ================================================================
    // FILTER HANDLERS
    // ================================================================
    // Keempat select saling menyaring dua arah (bolak-balik) lewat
    // applyCascadedFilterOptions() yang dipanggil dari dalam refresh(),
    // jadi TIDAK perlu lagi memaksa clear select lain saat salah satu
    // berubah -- pilihan yang masih valid otomatis dipertahankan, yang
    // sudah tidak valid otomatis ke-clear karena tidak ada lagi di opsi
    // baru. Sama seperti pola di rekonsiliasi_blade.php.
    $(fBrand).on('select2:select select2:clear', refresh);
    $(fStyle).on('select2:select select2:clear', refresh);
    $(fOcf).on('select2:select select2:clear', refresh);
    $(fUraian).on('select2:select select2:clear', refresh);

    /**
     * Tombol "Reset Filter": klik ikon "x" bawaan select2 terbukti tidak
     * selalu benar-benar mengosongkan value <select> di DOM setelah
     * option-nya di-rebuild lewat populateSelect(). Tombol ini memaksa
     * keempat select ke null lewat API resmi select2
     * (.val(null).trigger('change')) SEBELUM memanggil refresh() secara
     * langsung -- tidak bergantung pada event select2:clear sama sekali,
     * jadi hasilnya selalu pasti: semua dropdown balik menampilkan data
     * penuh (unfiltered). Sama seperti #btn-reset-filter di
     * rekonsiliasi_blade.php.
     */
    const btnResetFilter = document.getElementById('btn-reset-filter');
    btnResetFilter?.addEventListener('click', () => {
        [fBrand, fStyle, fOcf, fUraian].forEach(el => {
            $(el).val(null).trigger('change');
        });
        refresh();
    });

    // Dropdown "Urutan" di card Pivot MATERIAL PURCHASE
    if (fMaterialSort) {
        fMaterialSort.addEventListener('change', function(){
            if (dtMaterial) {
                dtMaterial.order(currentMaterialOrder()).draw();
                rerenderOpenMaterialChildren();
            } else {
                renderMaterialPivot(lastMaterialRows);
            }
        });
    }

    // ================================================================
    // SYNC ALL
    // ================================================================
    function runSyncAll(){
        const steps = [
            { url: syncMsNegaraUrl,   label: 'Sync Master Negara' },
            { url: syncMsSupplierUrl, label: 'Sync Master Supplier' },
            { url: syncMsBarangUrl,   label: 'Sync Master Barang' },
            { url: syncBomUrl,        label: 'Sync BOM' },
            { url: syncPoUrl,         label: 'Sync PO' },
            { url: syncRekonUrl,      label: 'Sync Rekonsiliasi' },
            { url: syncProdlineUrl,   label: 'Sync Production Line' },
            { url: syncShipmentUrl,   label: 'Sync Shipment' },
            { url: syncWorkOrderUrl,  label: 'Sync Work Order' },
        ].filter(s => !!s.url);

        Swal.fire({
            title: 'Sync semua data?',
            html: `Proses berikut dijalankan berurutan dan bisa memakan waktu cukup lama:<br><small>${steps.map(s => s.label).join(' &rarr; ')}</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, sync semua',
        }).then((res) => {
            if (!res.isConfirmed) return;
            runSyncStep(steps, 0, []);
        });
    }

    function runSyncStep(steps, index, results){
        if (index >= steps.length) {
            Swal.close();
            setTimeout(() => {
                const failed = results.filter(r => !r.success);
                if (failed.length === 0) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Selesai',
                        text: 'Semua proses sync berhasil dijalankan.',
                        allowOutsideClick: true,
                        allowEscapeKey: true,
                    }).then(refresh);
                } else {
                    const failedLabels = failed.map(r => r.label).join(', ');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Selesai dengan error',
                        text: `Berhasil ${results.length - failed.length}/${results.length} proses. Gagal: ${failedLabels}.`,
                        allowOutsideClick: true,
                        allowEscapeKey: true,
                    }).then(refresh);
                }
            }, 200);
            return;
        }

        const step = steps[index];
        Swal.close();

        const startedAt = Date.now();
        let elapsedTimer = null;

        Swal.fire({
            title: `${step.label}... (${index + 1}/${steps.length})`,
            html: 'Menarik data terbaru dari smartit, mohon tunggu.<br><small id="sync-elapsed" class="text-muted">0 detik berjalan</small>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
                elapsedTimer = setInterval(() => {
                    const secs = Math.round((Date.now() - startedAt) / 1000);
                    const el = document.getElementById('sync-elapsed');
                    if (el) {
                        el.textContent = secs > 30
                            ? `${secs} detik berjalan -- proses smartit memang bisa lama, mohon tunggu`
                            : `${secs} detik berjalan`;
                    }
                }, 1000);
            },
            willClose: () => {
                if (elapsedTimer) clearInterval(elapsedTimer);
            },
        });

        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 15 * 60 * 1000);

        fetch(step.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            signal: controller.signal,
        })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(json => {
                results.push({ label: step.label, success: !!json.success, message: json.message || json.output });
                runSyncStep(steps, index + 1, results);
            })
            .catch((err) => {
                clearTimeout(timeoutId);
                const timedOut = err && err.name === 'AbortError';
                results.push({
                    label: step.label,
                    success: false,
                    message: timedOut ? 'Timeout -- proses lebih dari 15 menit.' : 'Request gagal dikirim.',
                });
                runSyncStep(steps, index + 1, results);
            });
    }

    if (btnSyncAll) btnSyncAll.addEventListener('click', runSyncAll);

    refresh();

})();
</script>

@include('sweetalert::alert')
</body>
</html>