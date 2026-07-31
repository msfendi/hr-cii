<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Upload Sheet ORDER - hris.chutex.id</title>

    <!-- SB Admin 2 (Bootstrap + Fonts) -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/css/sb-admin-2.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">

    <style>
        .import-card { border: none; border-radius: .75rem; overflow: hidden; }
        .import-card .card-header-gradient {
            background: linear-gradient(90deg, #4e73df 0%, #6f8ff0 100%);
            color: #fff; padding: 1.25rem 1.5rem; border: none;
        }
        .import-card .card-header-gradient h6 { color: #fff; letter-spacing: .02em; }
        .import-card .card-header-gradient small { color: rgba(255,255,255,.85); }

        .drop-zone {
            border: 2px dashed #d1d5db; border-radius: .5rem; padding: 28px 16px;
            text-align: center; cursor: pointer; transition: all .2s ease; background: #f8f9fc;
        }
        .drop-zone:hover, .drop-zone.dragover { border-color: #4e73df; background: #eef2ff; }
        .drop-zone i { font-size: 2rem; color: #b7c0d8; transition: color .2s ease; }
        .drop-zone:hover i, .drop-zone.dragover i { color: #4e73df; }
        .drop-zone .file-name { font-weight: 700; color: #4e73df; word-break: break-all; }

        .mode-option {
            border: 1px solid #e3e6f0; border-radius: .5rem; padding: 10px 14px;
            cursor: pointer; transition: all .15s ease; flex: 1;
        }
        .mode-option:hover { border-color: #b7c0d8; }
        .mode-option.active { border-color: #4e73df; background: #eef2ff; }
        .mode-option input { margin-right: 6px; }

        #progressSection { display: none; }
        .progress { height: 14px; border-radius: 10px; background: #eaecf4; }
        .progress-bar-striped-anim {
            background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
            background-size: 1rem 1rem;
            animation: progress-stripes 1s linear infinite;
        }
        @keyframes progress-stripes { from { background-position: 1rem 0; } to { background-position: 0 0; } }

        .progress-status-text { font-size: .85rem; color: #5a5c69; min-height: 20px; }
        .progress-status-text .badge { font-size: .75rem; }
        .progress-pulse { animation: pulseDot 1.2s infinite; }
        @keyframes pulseDot { 0% { opacity: .4; } 50% { opacity: 1; } 100% { opacity: .4; } }

        .result-alert { display: none; }
        #btnSubmit[disabled] { cursor: not-allowed; opacity: .7; }

        #ordersTable_wrapper .table { font-size: .85rem; }
    </style>
</head>
<body id="page-top">

<div id="wrapper">

    @include('layout.sidebar')

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">

            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>
                <div class="ml-auto d-flex align-items-center mr-3">
                    <a href="{{ route('monitoring.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left fa-sm"></i> Kembali ke Dashboard
                    </a>
                </div>
            </nav>
            <!-- End Topbar -->

            <div class="container-fluid">

                <h1 class="h3 mb-4 text-gray-800">Upload Sheet ORDER</h1>

                @if(session('status'))
                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 pl-3">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="row justify-content-start">
                    <div class="col-lg-12">
                        <div class="card import-card shadow mb-4">
                            <div class="card-header-gradient">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-file-excel mr-2"></i>Form Import Sheet ORDER</h6>
                                <small>File harus mengikuti urutan kolom A s/d AW pada template asli</small>
                            </div>
                            <div class="card-body">

                                <form id="importForm" method="POST" action="{{ route('monitoring.order.import.store') }}" enctype="multipart/form-data">
                                    @csrf

                                    <div class="form-group">
                                        <label class="font-weight-bold small text-gray-700">File Excel (.xlsx / .xls, maks 50MB)</label>
                                        <div class="drop-zone" id="dropZone">
                                            <i class="fas fa-cloud-upload-alt d-block mb-2"></i>
                                            <div id="dropZoneText">
                                                <span class="text-gray-600">Klik untuk pilih file</span> atau drag &amp; drop di sini
                                            </div>
                                            <div class="file-name mt-2" id="fileNameLabel" style="display:none"></div>
                                        </div>
                                        <input type="file" name="file" id="fileInput" accept=".xlsx,.xls" required class="d-none">
                                    </div>

                                    <div class="form-group">
                                        <label class="font-weight-bold small text-gray-700 d-block">Mode Import</label>
                                        <div class="d-flex" style="gap:10px">
                                            <label class="mode-option active mb-0" id="modeAppendBox">
                                                <input type="radio" name="mode" id="mode-append" value="append" checked>
                                                <i class="fas fa-plus-circle text-success mr-1"></i> Tambah (append)
                                            </label>
                                            <label class="mode-option mb-0" id="modeReplaceBox">
                                                <input type="radio" name="mode" id="mode-replace" value="replace">
                                                <i class="fas fa-sync-alt text-warning mr-1"></i> Ganti semua (replace)
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" id="btnSubmit" class="btn btn-primary btn-block mt-3">
                                        <i class="fas fa-file-import fa-sm mr-1"></i> Import Sekarang
                                    </button>
                                </form>

                                <!-- PROGRESS SECTION -->
                                <div id="progressSection" class="mt-4">
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold small text-gray-700">
                                            <i class="fas fa-circle text-primary progress-pulse mr-1" style="font-size:.5rem"></i>
                                            Sedang mengimpor data...
                                        </span>
                                        <span class="font-weight-bold text-primary" id="progressPercent">0%</span>
                                    </div>
                                    <div class="progress">
                                        <div id="progressBar" class="progress-bar bg-primary progress-bar-striped-anim"
                                             role="progressbar" style="width:0%"></div>
                                    </div>
                                    <div class="progress-status-text mt-2" id="progressStatusText">
                                        Menyiapkan file...
                                    </div>
                                </div>

                                <!-- RESULT ALERTS -->
                                <div class="alert alert-success result-alert mt-4" id="successAlert">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    <span id="successText"></span>
                                </div>
                                <div class="alert alert-danger result-alert mt-4" id="errorAlert">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    <span id="errorText"></span>
                                </div>

                            </div>
                        </div>

                        <!-- DATA HASIL IMPORT -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-table mr-1"></i> Data Order (mon_orders)
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefreshTable">
                                    <i class="fas fa-sync-alt fa-sm"></i> Refresh
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="ordersTable" class="table table-bordered table-hover w-100">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Uraian</th>
                                                <th>OCF</th>
                                                <th>Buyer PO</th>
                                                <th>Buyer</th>
                                                <th>Brand</th>
                                                <th>Style</th>
                                                <th>Item</th>
                                                <th>Qty Ord</th>
                                                <th>Destination</th>
                                                <th>Shipment Mode</th>
                                                <th>Prod. Delivery</th>
                                                <th>Buyer Delivery</th>
                                                <th>Catatan</th>
                                                <th>Batch Import</th>
                                                <th>Diimport Pada</th>
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
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; {{ date('Y') }} hris.chutex.id</span>
                </div>
            </div>
        </footer>
        <!-- End of Footer -->

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

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function(){
    const form            = document.getElementById('importForm');
    const dropZone        = document.getElementById('dropZone');
    const fileInput       = document.getElementById('fileInput');
    const fileNameLabel   = document.getElementById('fileNameLabel');
    const dropZoneText    = document.getElementById('dropZoneText');
    const btnSubmit       = document.getElementById('btnSubmit');
    const progressSection = document.getElementById('progressSection');
    const progressBar     = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');
    const progressStatus  = document.getElementById('progressStatusText');
    const successAlert    = document.getElementById('successAlert');
    const successText     = document.getElementById('successText');
    const errorAlert      = document.getElementById('errorAlert');
    const errorText       = document.getElementById('errorText');
    const modeAppendBox   = document.getElementById('modeAppendBox');
    const modeReplaceBox  = document.getElementById('modeReplaceBox');

    let pollTimer = null;

    // --- DataTables: tabel hasil import (mon_orders) ---
    const ordersTable = $('#ordersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ url('monitoring/order-import/data') }}',
            type: 'GET'
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'id' },
            { data: 'uraian' },
            { data: 'ocf_no' },
            { data: 'buyer_po' },
            { data: 'buyer' },
            { data: 'brand' },
            { data: 'style' },
            { data: 'item' },
            { data: 'qty_ord' },
            { data: 'destination' },
            { data: 'shipment_mode' },
            { data: 'production_delivery' },
            { data: 'buyer_delivery' },
            { data: 'catatan' },
            { data: 'import_batch' },
            { data: 'created_at' }
        ],
        language: {
            processing: 'Memuat data...',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ baris',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_ total baris)',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
            zeroRecords: 'Data tidak ditemukan'
        }
    });

    document.getElementById('btnRefreshTable').addEventListener('click', () => {
        ordersTable.ajax.reload(null, false);
    });

    // --- Drag & drop / klik untuk pilih file ---
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateFileLabel();
        }
    });
    fileInput.addEventListener('change', updateFileLabel);

    function updateFileLabel(){
        if (fileInput.files.length) {
            dropZoneText.style.display = 'none';
            fileNameLabel.style.display = 'block';
            fileNameLabel.innerHTML = `<i class="fas fa-file-excel text-success mr-1"></i>${fileInput.files[0].name}`;
        }
    }

    // --- Toggle tampilan mode append/replace ---
    [modeAppendBox, modeReplaceBox].forEach(box => {
        box.addEventListener('click', () => {
            modeAppendBox.classList.toggle('active', box === modeAppendBox);
            modeReplaceBox.classList.toggle('active', box === modeReplaceBox);
        });
    });

    function fmt(n){ return Number(n || 0).toLocaleString('id-ID'); }

    function resetUI(){
        successAlert.style.display = 'none';
        errorAlert.style.display = 'none';
        progressSection.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.classList.remove('bg-success', 'bg-danger');
        progressBar.classList.add('bg-primary', 'progress-bar-striped-anim');
        progressPercent.textContent = '0%';
        progressStatus.textContent = 'Menyiapkan file...';
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Mengimpor...';
    }

    function finishUI(){
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fas fa-file-import fa-sm mr-1"></i> Import Sekarang';
    }

    function pollProgress(batchId, total){
        pollTimer = setInterval(() => {
            fetch(`{{ url('monitoring/order-import/progress') }}/${batchId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(state => {
                const processed      = state.processed || 0;
                const totalRows      = state.total || total || 0;
                const skippedCancel  = state.skipped_cancel || 0;
                const skippedBlank   = state.skipped_blank || 0;
                const touched        = processed + skippedCancel + skippedBlank;
                const pct = totalRows > 0 ? Math.min(100, Math.round((touched / totalRows) * 100)) : 0;

                progressBar.style.width = pct + '%';
                progressPercent.textContent = pct + '%';

                if (totalRows > 0) {
                    progressStatus.innerHTML = `Baris ke <b>${fmt(touched)}</b> dari <b>${fmt(totalRows)}</b>` +
                        (state.last ? ` &mdash; data terakhir: <span class="badge badge-light border">${state.last}</span>` : '');
                } else {
                    progressStatus.textContent = `Memproses ${fmt(processed)} baris...`;
                }

                if (state.status === 'done') {
                    clearInterval(pollTimer);
                    progressBar.classList.remove('progress-bar-striped-anim', 'bg-primary');
                    progressBar.classList.add('bg-success');
                    progressBar.style.width = '100%';
                    progressPercent.textContent = '100%';

                    let detail = `<b>${fmt(processed)}</b> dari <b>${fmt(totalRows)}</b> baris berhasil diimport.`;
                    if (skippedCancel > 0) {
                        detail += `<br>${fmt(skippedCancel)} baris dilewati karena status <b>CANCEL</b>.`;
                    }
                    if (skippedBlank > 0) {
                        detail += `<br>${fmt(skippedBlank)} baris dilewati karena kolom uraian kosong.`;
                    }

                    successText.textContent = `Import selesai. ${fmt(processed)} dari ${fmt(totalRows)} baris berhasil diproses. Batch: ${batchId}`;
                    successAlert.style.display = 'block';
                    finishUI();

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Import Selesai!',
                            html: detail,
                            confirmButtonText: 'OK'
                        });
                    }

                    // refresh tabel hasil import
                    ordersTable.ajax.reload(null, false);

                } else if (state.status === 'error') {
                    clearInterval(pollTimer);
                    progressBar.classList.remove('progress-bar-striped-anim', 'bg-primary');
                    progressBar.classList.add('bg-danger');
                    errorText.textContent = state.message || 'Import gagal diproses.';
                    errorAlert.style.display = 'block';
                    finishUI();

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Import Gagal',
                            text: state.message || 'Import gagal diproses.'
                        });
                    }
                }
            })
            .catch(() => {
                // network hiccup: biarkan interval jalan lagi di tick berikutnya
            });
        }, 900);
    }

    form.addEventListener('submit', function(e){
        e.preventDefault();
        resetUI();

        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        })
        .then(async (r) => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Gagal memulai import.'));
            }
            return data;
        })
        .then(data => {
            progressStatus.textContent = `File diterima, total ${fmt(data.total)} baris terdeteksi. Memulai proses...`;
            pollProgress(data.batch_id, data.total);
        })
        .catch(err => {
            progressSection.style.display = 'none';
            errorText.textContent = err.message || 'Gagal memulai import.';
            errorAlert.style.display = 'block';
            finishUI();
        });
    });
})();
</script>

</body>
</html>