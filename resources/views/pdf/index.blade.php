<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>AI PDF Extractor - Chutex</title>

    <!-- SB Admin 2 (Bootstrap + Fonts) -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/css/sb-admin-2.min.css">

    <style>
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .status-live { background: #1cc88a; box-shadow: 0 0 0 3px rgba(28,200,138,.25); animation: pulse 1.5s infinite; }
        .status-down { background: #e74a3b; box-shadow: 0 0 0 3px rgba(231,74,59,.25); }
        .status-wait { background: #f6c23e; box-shadow: 0 0 0 3px rgba(246,194,62,.25); }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(28,200,138,.5);} 70% { box-shadow: 0 0 0 8px rgba(28,200,138,0);} 100% { box-shadow: 0 0 0 0 rgba(28,200,138,0);} }

        .error-banner {
            display: none;
            align-items: center;
            gap: 10px;
            background: #fdeceb;
            border: 1px solid #f1b6b0;
            color: #7a1f16;
            padding: 10px 16px;
            border-radius: .35rem;
            margin-bottom: 1rem;
        }
        .error-banner.show { display: flex; }
        .error-banner .retry-btn { margin-left: auto; white-space: nowrap; }

        /* --- Dropzone upload --- */
        .dropzone {
            border: 2px dashed #d1d3e2;
            border-radius: .5rem;
            padding: 40px 20px;
            text-align: center;
            background: #f8f9fc;
            transition: all .15s ease;
            cursor: pointer;
        }
        .dropzone:hover, .dropzone.is-dragover {
            border-color: #4e73df;
            background: #eef1fb;
        }
        .dropzone i { color: #b7b9cc; }
        .dropzone.is-dragover i { color: #4e73df; }
        .dropzone-filename {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eaecf4;
            padding: 6px 14px;
            border-radius: 2rem;
            font-size: .85rem;
            color: #3a3b45;
        }
        .dropzone-filename i.fa-times { cursor: pointer; color: #e74a3b; }

        .upload-progress-wrap { display: none; }

        /* --- Badge status dokumen --- */
        .badge-status-pending  { background:#f6c23e; color:#3a3b45; }
        .badge-status-processing { background:#4e73df; color:#fff; }
        .badge-status-processed  { background:#1cc88a; color:#fff; }
        .badge-status-failed     { background:#e74a3b; color:#fff; }

        /* --- Tabel dokumen --- */
        .doc-row { cursor: pointer; }
        .doc-row:hover { background: #f8f9fc; }
        .doc-filename { font-weight: 700; color: #3a3b45; }

        /* --- Hasil pencarian key --- */
        .search-result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border: 1px solid #eaecf4;
            border-radius: .35rem;
            margin-bottom: 8px;
        }
        .search-result-key { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #858796; font-weight: 700; }
        .search-result-value { font-weight: 700; color: #3a3b45; }
        .search-result-doc { font-size: .78rem; color: #4e73df; cursor: pointer; }

        /* --- Modal detail dokumen --- */
        .field-table th { width: 40%; color: #858796; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        .extracted-table-wrap { margin-bottom: 1.5rem; }
        .extracted-table-name { font-weight: 700; color: #4e73df; margin-bottom: .5rem; }
        .empty-state { text-align: center; color: #b7b9cc; padding: 30px 0; font-size: .85rem; }
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
                    <span class="text-gray-600 small">Total dokumen: <strong id="totalDocCount">-</strong></span>
                </div>
            </nav>
            <!-- End Topbar -->

            <div class="container-fluid">

                <h1 class="h3 mb-4 text-gray-800">AI PDF Data Extractor &mdash; Chutex</h1>

                <!-- Error banner -->
                <div class="error-banner" id="errorBanner">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span id="errorBannerText">Gagal memuat data.</span>
                    <button class="btn btn-sm btn-outline-danger retry-btn" id="retryBtn">
                        <i class="fas fa-redo"></i> Coba Lagi
                    </button>
                </div>

                <div class="row">
                    <!-- ============================================================ -->
                    <!-- Card: Upload PDF -->
                    <!-- ============================================================ -->
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-file-upload mr-1"></i> Upload PDF
                                </h6>
                            </div>
                            <div class="card-body">
                                <form id="uploadForm">
                                    <div class="dropzone" id="dropzone">
                                        <div id="dropzoneEmpty">
                                            <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                                            <p class="mb-1 font-weight-bold text-gray-700">Seret PDF ke sini</p>
                                            <p class="text-gray-500 small mb-0">atau klik untuk memilih file (maks. 20MB)</p>
                                        </div>
                                        <div id="dropzoneFilled" style="display:none;">
                                            <i class="fas fa-file-pdf fa-3x mb-3 text-danger"></i>
                                            <div class="dropzone-filename">
                                                <span id="selectedFileName">-</span>
                                                <i class="fas fa-times" id="removeFileBtn" title="Hapus"></i>
                                            </div>
                                        </div>
                                        <input type="file" id="pdfInput" name="pdf" accept="application/pdf" hidden>
                                    </div>

                                    <div class="form-group mt-3">
                                        <label for="categorySelect" class="small font-weight-bold text-gray-700">Kategori</label>
                                        <select class="form-control" id="categorySelect" name="category_id">
                                            <option value="">(Tanpa kategori)</option>
                                        </select>
                                    </div>

                                    <div class="upload-progress-wrap mb-3" id="uploadProgressWrap">
                                        <div class="d-flex justify-content-between small text-gray-600 mb-1">
                                            <span id="uploadProgressLabel">Mengupload...</span>
                                            <span id="uploadProgressPercent">0%</span>
                                        </div>
                                        <div class="progress" style="height:8px;">
                                            <div class="progress-bar bg-primary" id="uploadProgressBar" role="progressbar" style="width:0%"></div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-block" id="uploadSubmitBtn">
                                        <i class="fas fa-magic mr-1"></i> Upload &amp; Proses dengan AI
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- Card: Cari data berdasarkan key -->
                    <!-- ============================================================ -->
                    <div class="col-lg-7 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-search mr-1"></i> Cari Data (contoh: Vessel/Voy)
                                </h6>
                            </div>
                            <div class="card-body d-flex flex-column">
                                <form id="searchForm" class="form-inline mb-3">
                                    <input type="text" class="form-control flex-grow-1 mr-2" id="searchKeyInput"
                                           placeholder="Ketik nama field, contoh: Vessel/Voy" autocomplete="off">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                </form>

                                <div id="searchResultsWrap" style="flex:1; overflow-y:auto; max-height: 320px;">
                                    <div class="empty-state" id="searchEmptyState">
                                        <i class="fas fa-search fa-2x mb-2"></i><br>
                                        Ketik kata kunci lalu tekan Cari untuk menemukan data di semua PDF.
                                    </div>
                                    <div id="searchResultsList"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- Card: Daftar dokumen -->
                <!-- ============================================================ -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-folder-open mr-1"></i> Daftar Dokumen
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary" id="refreshDocsBtn">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" id="docTable">
                                        <thead>
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>Nama File</th>
                                            <th>Kategori</th>
                                            <th>Status</th>
                                            <th>Diupload</th>
                                            <th style="width:100px;">Aksi</th>
                                        </tr>
                                        </thead>
                                        <tbody id="docTableBody">
                                        <tr>
                                            <td colspan="6" class="empty-state">Memuat data...</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        @include('layout.footer')
    </div>
</div>

<!-- ============================================================ -->
<!-- Modal: Detail dokumen (field + tabel hasil ekstraksi AI) -->
<!-- ============================================================ -->
<div class="modal fade" id="docDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-pdf text-danger mr-2"></i>
                    <span id="modalDocFilename">-</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalLoading" class="empty-state">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Memuat detail dokumen...
                </div>

                <div id="modalContent" style="display:none;">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#modalTabFields" role="tab">
                                <i class="fas fa-list mr-1"></i> Data (Key/Value)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#modalTabTables" role="tab">
                                <i class="fas fa-table mr-1"></i> Tabel <span class="badge badge-secondary" id="modalTableCount">0</span>
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="modalTabFields" role="tabpanel">
                            <table class="table table-sm field-table">
                                <tbody id="modalFieldsBody"></tbody>
                            </table>
                            <div class="empty-state" id="modalFieldsEmpty" style="display:none;">
                                Tidak ada field key-value yang terdeteksi di dokumen ini.
                            </div>
                        </div>

                        <div class="tab-pane fade" id="modalTabTables" role="tabpanel">
                            <div id="modalTablesWrap"></div>
                            <div class="empty-state" id="modalTablesEmpty" style="display:none;">
                                Tidak ada tabel yang terdeteksi di dokumen ini.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap core JavaScript-->
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/StartBootstrap/startbootstrap-sb-admin-2@gh-pages/js/sb-admin-2.min.js"></script>

<script>
/* ============================================================
   Konfigurasi endpoint - sesuaikan dengan routes/api.php Anda
   ============================================================ */
const API_BASE          = '/api';
const UPLOAD_URL        = `${API_BASE}/pdf-documents`;
const DOC_LIST_URL      = `${API_BASE}/pdf-documents`;
const DOC_DETAIL_URL    = (id) => `${API_BASE}/pdf-documents/${id}`;
const SEARCH_URL        = `${API_BASE}/pdf-data/search`;
const CATEGORIES_URL    = `${API_BASE}/categories`;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function showErrorBanner(message) {
    document.getElementById('errorBannerText').textContent = message;
    document.getElementById('errorBanner').classList.add('show');
}
function hideErrorBanner() {
    document.getElementById('errorBanner').classList.remove('show');
}

async function fetchJson(url, options = {}) {
    const res = await fetch(url, {
        headers: { 'Accept': 'application/json', ...(options.headers || {}) },
        ...options,
    });
    const contentType = res.headers.get('content-type') || '';
    if (!res.ok) {
        let detail = `HTTP ${res.status} ${res.statusText}`;
        if (contentType.includes('application/json')) {
            try {
                const errJson = await res.json();
                detail = errJson.message || detail;
            } catch (_) {}
        }
        throw new Error(detail);
    }
    if (!contentType.includes('application/json')) {
        throw new Error('Server tidak mengembalikan JSON (kemungkinan error/redirect di Laravel)');
    }
    return res.json();
}

/* ---------- Dropzone upload ---------- */
const dropzone       = document.getElementById('dropzone');
const pdfInput        = document.getElementById('pdfInput');
const dropzoneEmpty   = document.getElementById('dropzoneEmpty');
const dropzoneFilled  = document.getElementById('dropzoneFilled');
const selectedFileName = document.getElementById('selectedFileName');
const removeFileBtn  = document.getElementById('removeFileBtn');

dropzone.addEventListener('click', (e) => {
    if (e.target !== removeFileBtn) pdfInput.click();
});
dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('is-dragover'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragover'));
dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('is-dragover');
    if (e.dataTransfer.files.length) {
        pdfInput.files = e.dataTransfer.files;
        showSelectedFile();
    }
});
pdfInput.addEventListener('change', showSelectedFile);
removeFileBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    pdfInput.value = '';
    dropzoneEmpty.style.display = '';
    dropzoneFilled.style.display = 'none';
});

function showSelectedFile() {
    if (pdfInput.files.length) {
        selectedFileName.textContent = pdfInput.files[0].name;
        dropzoneEmpty.style.display = 'none';
        dropzoneFilled.style.display = '';
    }
}

/* ---------- Load kategori untuk dropdown ---------- */
async function loadCategories() {
    try {
        const categories = await fetchJson(CATEGORIES_URL);
        const select = document.getElementById('categorySelect');
        categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.name;
            select.appendChild(opt);
        });
    } catch (e) {
        // Kalau endpoint kategori belum ada, cukup diamkan (dropdown tetap "Tanpa kategori")
        console.warn('Gagal memuat kategori:', e.message);
    }
}

/* ---------- Submit upload ---------- */
document.getElementById('uploadForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!pdfInput.files.length) {
        showErrorBanner('Pilih file PDF terlebih dahulu.');
        return;
    }

    const formData = new FormData();
    formData.append('pdf', pdfInput.files[0]);
    const categoryId = document.getElementById('categorySelect').value;
    if (categoryId) formData.append('category_id', categoryId);

    const submitBtn = document.getElementById('uploadSubmitBtn');
    const progressWrap = document.getElementById('uploadProgressWrap');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressPercent = document.getElementById('uploadProgressPercent');
    const progressLabel = document.getElementById('uploadProgressLabel');

    submitBtn.disabled = true;
    progressWrap.style.display = 'block';
    progressLabel.textContent = 'Mengupload file...';
    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    hideErrorBanner();

    try {
        await new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', UPLOAD_URL);
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.addEventListener('progress', (evt) => {
                if (evt.lengthComputable) {
                    const pct = Math.round((evt.loaded / evt.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressPercent.textContent = pct + '%';
                    if (pct >= 100) progressLabel.textContent = 'Menunggu AI memproses PDF...';
                }
            });

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(JSON.parse(xhr.responseText));
                } else {
                    let msg = `Upload gagal (HTTP ${xhr.status})`;
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (_) {}
                    reject(new Error(msg));
                }
            };
            xhr.onerror = () => reject(new Error('Gagal terhubung ke server.'));
            xhr.send(formData);
        });

        // Reset form setelah sukses
        pdfInput.value = '';
        dropzoneEmpty.style.display = '';
        dropzoneFilled.style.display = 'none';
        document.getElementById('categorySelect').value = '';

        await loadDocuments();
    } catch (err) {
        console.error(err);
        showErrorBanner(err.message || 'Upload gagal.');
    } finally {
        submitBtn.disabled = false;
        progressWrap.style.display = 'none';
    }
});

/* ---------- Load daftar dokumen ---------- */
async function loadDocuments() {
    const tbody = document.getElementById('docTableBody');
    try {
        const documents = await fetchJson(DOC_LIST_URL);
        document.getElementById('totalDocCount').textContent = documents.length;

        if (!documents.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="empty-state">Belum ada dokumen yang diupload.</td></tr>`;
            return;
        }

        tbody.innerHTML = documents.map((doc, idx) => {
            const statusBadge = renderStatusBadge(doc.status);
            const categoryName = doc.category ? escapeHtml(doc.category.name) : '<span class="text-gray-400">-</span>';
            const uploadedAt = doc.created_at ? new Date(doc.created_at).toLocaleString('id-ID') : '-';

            return `
            <tr class="doc-row" data-id="${doc.id}">
                <td>${idx + 1}</td>
                <td><i class="fas fa-file-pdf text-danger mr-1"></i> <span class="doc-filename">${escapeHtml(doc.original_filename)}</span></td>
                <td>${categoryName}</td>
                <td>${statusBadge}</td>
                <td class="text-gray-600 small">${uploadedAt}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary view-doc-btn" data-id="${doc.id}">
                        <i class="fas fa-eye"></i> Lihat
                    </button>
                </td>
            </tr>`;
        }).join('');

        document.querySelectorAll('.view-doc-btn').forEach(btn => {
            btn.addEventListener('click', () => openDocDetail(btn.dataset.id));
        });
        document.querySelectorAll('.doc-row').forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.target.closest('.view-doc-btn')) return;
                openDocDetail(row.dataset.id);
            });
        });

        hideErrorBanner();
    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="6" class="empty-state">Gagal memuat data.</td></tr>`;
        showErrorBanner(err.message || 'Gagal memuat daftar dokumen.');
    }
}

function renderStatusBadge(status) {
    const map = {
        pending:    ['badge-status-pending', 'Menunggu'],
        processing: ['badge-status-processing', 'Diproses AI...'],
        processed:  ['badge-status-processed', 'Selesai'],
        failed:     ['badge-status-failed', 'Gagal'],
    };
    const [cls, label] = map[status] || ['badge-secondary', status];
    return `<span class="badge ${cls}">${label}</span>`;
}

/* ---------- Modal detail dokumen ---------- */
async function openDocDetail(id) {
    $('#docDetailModal').modal('show');
    document.getElementById('modalLoading').style.display = '';
    document.getElementById('modalContent').style.display = 'none';
    document.getElementById('modalDocFilename').textContent = 'Memuat...';

    try {
        const doc = await fetchJson(DOC_DETAIL_URL(id));

        document.getElementById('modalDocFilename').textContent = doc.original_filename;

        // Render fields (key-value)
        const fieldsBody = document.getElementById('modalFieldsBody');
        const fields = doc.extracted_data || [];
        if (fields.length) {
            fieldsBody.innerHTML = fields.map(f => `
                <tr>
                    <th>${escapeHtml(f.data_key)}</th>
                    <td>${escapeHtml(f.data_value) || '<span class="text-gray-400">-</span>'}</td>
                </tr>
            `).join('');
            document.getElementById('modalFieldsEmpty').style.display = 'none';
        } else {
            fieldsBody.innerHTML = '';
            document.getElementById('modalFieldsEmpty').style.display = '';
        }

        // Render tabel-tabel
        const tables = doc.extracted_tables || [];
        document.getElementById('modalTableCount').textContent = tables.length;
        const tablesWrap = document.getElementById('modalTablesWrap');

        if (tables.length) {
            tablesWrap.innerHTML = tables.map(t => {
                const headers = t.headers || [];
                const rows = t.rows || [];
                const headHtml = headers.map(h => `<th>${escapeHtml(h)}</th>`).join('');
                const rowsHtml = rows.map(r => {
                    const cells = headers.map(h => `<td>${escapeHtml(r.row_data[h])}</td>`).join('');
                    return `<tr>${cells}</tr>`;
                }).join('');

                return `
                <div class="extracted-table-wrap">
                    <div class="extracted-table-name">
                        <i class="fas fa-table mr-1"></i> ${escapeHtml(t.table_name) || 'Tabel'}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="thead-light"><tr>${headHtml}</tr></thead>
                            <tbody>${rowsHtml}</tbody>
                        </table>
                    </div>
                </div>`;
            }).join('');
            document.getElementById('modalTablesEmpty').style.display = 'none';
        } else {
            tablesWrap.innerHTML = '';
            document.getElementById('modalTablesEmpty').style.display = '';
        }

        document.getElementById('modalLoading').style.display = 'none';
        document.getElementById('modalContent').style.display = '';
    } catch (err) {
        console.error(err);
        document.getElementById('modalLoading').innerHTML =
            `<div class="text-danger"><i class="fas fa-exclamation-triangle mb-2"></i><br>Gagal memuat detail: ${escapeHtml(err.message)}</div>`;
    }
}

/* ---------- Pencarian by key ---------- */
document.getElementById('searchForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const key = document.getElementById('searchKeyInput').value.trim();
    if (!key) return;

    const listEl = document.getElementById('searchResultsList');
    const emptyEl = document.getElementById('searchEmptyState');
    listEl.innerHTML = `<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Mencari...</div>`;
    emptyEl.style.display = 'none';

    try {
        const results = await fetchJson(`${SEARCH_URL}?key=${encodeURIComponent(key)}`);

        if (!results.length) {
            listEl.innerHTML = `<div class="empty-state"><i class="fas fa-inbox fa-2x mb-2"></i><br>Tidak ada data ditemukan untuk "${escapeHtml(key)}".</div>`;
            return;
        }

        listEl.innerHTML = results.map(r => `
            <div class="search-result-item">
                <div>
                    <div class="search-result-key">${escapeHtml(r.data_key)}</div>
                    <div class="search-result-value">${escapeHtml(r.data_value) || '-'}</div>
                    <div class="search-result-doc" data-id="${r.pdf_document_id}">
                        <i class="fas fa-file-pdf mr-1"></i>${escapeHtml(r.pdf_document ? r.pdf_document.original_filename : '')}
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary view-search-doc-btn" data-id="${r.pdf_document_id}">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        `).join('');

        listEl.querySelectorAll('.view-search-doc-btn, .search-result-doc').forEach(el => {
            el.addEventListener('click', () => openDocDetail(el.dataset.id));
        });

        hideErrorBanner();
    } catch (err) {
        console.error(err);
        listEl.innerHTML = '';
        emptyEl.style.display = '';
        showErrorBanner(err.message || 'Pencarian gagal.');
    }
});

/* ---------- Init ---------- */
document.getElementById('retryBtn').addEventListener('click', () => {
    hideErrorBanner();
    loadDocuments();
});
document.getElementById('refreshDocsBtn').addEventListener('click', loadDocuments);

loadCategories();
loadDocuments();
</script>

</body>
</html>
