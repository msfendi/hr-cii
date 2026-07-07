<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lowongan Pekerjaan - PT. Chutex International Indonesia</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6/css/all.min.css">

    {{-- Hanya CSS inti Quill, dipakai supaya konten rich text (list bullet/numbering, indent, dll)
         hasil dari editor admin bisa dirender dengan tampilan yang sama persis di halaman publik ini --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.core.css">

    <style>
        body{
            background:#f4f6fb;
        }

        /* ===================== COMPANY BRANDING BAR ===================== */

        .company-bar{
            background:#fff;
            border-bottom:1px solid #eaecf4;
            padding:.9rem 1rem;
        }

        .company-bar-inner{
            max-width:960px;
            margin:0 auto;
            display:flex;
            align-items:center;
            gap:.9rem;
        }

        .company-logo{
            width:52px;
            height:52px;
            object-fit:contain;
            border-radius:.5rem;
            background:#fff;
            flex-shrink:0;
        }

        .company-name{
            font-weight:700;
            font-size:1rem;
            color:#2e2f45;
            line-height:1.25;
        }

        .company-address{
            font-size:.78rem;
            color:#858796;
            line-height:1.3;
        }

        .page-hero{
            background:linear-gradient(135deg,#4e73df,#224abe);
            color:#fff;
            padding:2.5rem 1rem;
            border-radius:0 0 1.2rem 1.2rem;
            margin-bottom:2rem;
        }

        .page-hero h1{
            font-weight:700;
        }

        .search-box .input-group-text{
            background:#fff;
            border-right:none;
            color:#b7b9c8;
        }

        .search-box input{
            border-left:none;
        }

        .search-box input:focus{
            box-shadow:none;
            border-color:#ced4da;
        }

        .vacancy-card{
            border:none;
            border-radius:.9rem;
            transition:transform .15s ease, box-shadow .15s ease;
            overflow:hidden;
        }

        .vacancy-card:hover{
            transform:translateY(-3px);
            box-shadow:0 .5rem 1.5rem rgba(0,0,0,.1) !important;
        }

        .vacancy-card .card-accent{
            height:6px;
            width:100%;
        }

        .accent-open{ background:#1cc88a; }
        .accent-upcoming{ background:#36b9cc; }
        .accent-expired{ background:#f6c23e; }
        .accent-closed{ background:#858796; }
        .accent-draft{ background:#d1d3e2; }

        .badge-status{
            font-size:.72rem;
            font-weight:700;
            padding:.35em .65em;
            border-radius:50rem;
            white-space:nowrap;
        }

        .badge-open{ background:#e6f8f1; color:#159d6f; }
        .badge-upcoming{ background:#e3f6f9; color:#1a8fa3; }
        .badge-expired{ background:#fef5e0; color:#b8860b; }
        .badge-closed{ background:#eaeaea; color:#5a5c69; }
        .badge-draft{ background:#eef0fb; color:#6f74b0; }

        .badge-applicant{
            font-size:.72rem;
            font-weight:700;
            padding:.35em .65em;
            border-radius:50rem;
            background:#eef2ff;
            color:#4e73df;
            display:inline-block;
        }

        .tag-pill{
            display:inline-block;
            font-size:.72rem;
            background:#f4f6fb;
            color:#5a5c69;
            border:1px solid #e3e6f0;
            padding:.2rem .55rem;
            border-radius:50rem;
            margin:0 .25rem .25rem 0;
        }

        .vacancy-position{
            font-size:1.05rem;
            font-weight:700;
            color:#2e2f45;
        }

        .vacancy-meta{
            font-size:.78rem;
            color:#858796;
        }

        .description-preview{
            font-size:.82rem;
            color:#6b6d7d;
            display:-webkit-box;
            -webkit-line-clamp:3;
            -webkit-box-orient:vertical;
            overflow:hidden;
            margin-bottom:.5rem;
        }

        .btn-detail-link{
            font-size:.78rem;
            font-weight:700;
            padding:0;
            color:#4e73df;
        }

        .btn-detail-link:hover{
            color:#224abe;
            text-decoration:none;
        }

        .empty-state, .loading-state{
            padding:3.5rem 1rem;
            text-align:center;
            color:#b7b9c8;
        }

        .btn-melamar[disabled]{
            pointer-events:none;
            opacity:.65;
        }

        /* ===================== DETAIL MODAL ===================== */

        #detailModal .modal-dialog{
            max-width:820px;
        }

        #detailModal .modal-header{
            border-bottom:none;
            padding-bottom:0;
        }

        .detail-hero{
            border-radius:.9rem;
            padding:1.25rem 1.5rem;
            margin-bottom:1.25rem;
            color:#fff;
            background:linear-gradient(135deg, #4e73df, #6f86e6);
        }

        .detail-hero.hero-open{ background:linear-gradient(135deg, #17b881, #1cc88a); }
        .detail-hero.hero-upcoming{ background:linear-gradient(135deg, #1a8fa3, #36b9cc); }
        .detail-hero.hero-expired{ background:linear-gradient(135deg, #d99a1c, #f6c23e); }
        .detail-hero.hero-closed{ background:linear-gradient(135deg, #6b6d7d, #858796); }
        .detail-hero.hero-draft{ background:linear-gradient(135deg, #9195c9, #b7bade); }

        .detail-hero-position{
            font-size:1.3rem;
            font-weight:700;
            line-height:1.3;
        }

        .detail-hero-meta{
            font-size:.85rem;
            opacity:.9;
            margin-top:.15rem;
        }

        .detail-hero .badge-status{
            background:rgba(255,255,255,.92);
        }

        .detail-stats-grid{
            display:grid;
            grid-template-columns:repeat(4, 1fr);
            gap:.75rem;
            margin-bottom:1.25rem;
        }

        @media (max-width: 576px){
            .detail-stats-grid{ grid-template-columns:repeat(2, 1fr); }
        }

        .detail-stat-item{
            display:flex;
            align-items:center;
            gap:.65rem;
            background:#f8f9fc;
            border:1px solid #eaecf4;
            border-radius:.7rem;
            padding:.65rem .8rem;
        }

        .detail-stat-icon{
            width:36px;
            height:36px;
            min-width:36px;
            border-radius:.6rem;
            background:#eef2ff;
            color:#4e73df;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:.9rem;
        }

        .detail-stat-label{
            font-size:.7rem;
            color:#96999f;
            text-transform:uppercase;
            letter-spacing:.02em;
        }

        .detail-stat-value{
            font-size:.9rem;
            font-weight:700;
            color:#2e2f45;
        }

        .detail-section{
            background:#fff;
            border:1px solid #eaecf4;
            border-radius:.7rem;
            padding:.9rem 1rem;
            margin-bottom:1rem;
            height:100%;
        }

        .section-label{
            font-size:.78rem;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.03em;
            color:#4e73df;
            margin-bottom:.6rem;
        }

        /* Konten rich text (hasil Quill editor) - dibungkus class ql-editor
           supaya list bullet/numbering & indent yang disimpan Quill tetap tampil rapi */
        .detail-richtext.ql-editor{
            padding:0;
            font-size:.88rem;
            color:#4a4c5f;
            line-height:1.6;
        }

        .detail-checklist{
            list-style:none;
            padding-left:0;
            margin-bottom:0;
        }

        .detail-checklist li{
            font-size:.85rem;
            color:#4a4c5f;
            padding:.3rem 0;
            border-bottom:1px dashed #eee;
            display:flex;
            align-items:flex-start;
            gap:.4rem;
        }

        .detail-checklist li:last-child{ border-bottom:none; }
        .detail-checklist li i{ margin-top:.2rem; }

        .detail-empty-note{
            font-size:.82rem;
            color:#b7b9c8;
            font-style:italic;
            margin-bottom:0;
        }

        .detail-company-footer{
            display:flex;
            align-items:center;
            gap:.6rem;
            font-size:.76rem;
            color:#96999f;
            border-top:1px solid #eaecf4;
            padding-top:.75rem;
            margin-top:.25rem;
        }

        .detail-company-footer img{
            width:28px;
            height:28px;
            object-fit:contain;
        }
    </style>
</head>
<body>

    {{-- ===================== COMPANY BRANDING BAR ===================== --}}
    <div class="company-bar">
        <div class="company-bar-inner">
            <img src="{{ asset('img/chutex_logo.png') }}" alt="Logo PT. Chutex International Indonesia" class="company-logo">
            <div>
                <div class="company-name">PT. Chutex International Indonesia</div>
                <div class="company-address">
                    <i class="fas fa-map-marker-alt mr-1"></i>Menggungan, RT.02/RW.10, Dusun III, Telukan, Kec. Grogol, Kabupaten Sukoharjo, Jawa Tengah 57552
                </div>
            </div>
        </div>
    </div>

    <div class="page-hero text-center">
        <h1 class="h2 mb-2">Lowongan Pekerjaan</h1>
        <p class="mb-0">Temukan posisi yang sesuai dengan minat dan keahlianmu, lalu ajukan lamaranmu sekarang.</p>
    </div>

    <div class="container">

        {{-- ===================== FILTER: PENCARIAN POSISI SAJA ===================== --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form id="filterForm">
                    <div class="row align-items-end">
                        <div class="col-md-9 mb-3 mb-md-0">
                            <label class="small font-weight-bold text-gray-600 mb-1 d-block">Cari Posisi</label>
                            <div class="input-group search-box">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" class="form-control" id="searchPosition" placeholder="cth: Staff Finance, Marketing, dll">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-block shadow-sm">
                                <i class="fas fa-search fa-sm mr-1"></i> Cari
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===================== GRID LOWONGAN ===================== --}}
        <div id="loadingState" class="loading-state" style="display:none;">
            <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
            <p class="mb-0">Memuat lowongan pekerjaan...</p>
        </div>

        <div id="emptyState" class="empty-state" style="display:none;">
            <i class="fas fa-briefcase fa-2x mb-2"></i>
            <p class="mb-0">Belum ada lowongan pekerjaan yang cocok dengan pencarianmu.</p>
        </div>

        <div class="row pb-5" id="vacancyGrid"></div>
    </div>

    {{-- ===================== MODAL DETAIL LOWONGAN ===================== --}}
    <div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-weight-bold">Detail Lowongan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="detailBody">
                    <!-- diisi via JS -->
                </div>
                <div class="modal-footer" id="detailFooter">
                    <!-- diisi via JS -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const routes = {
            data: "{{ route('job-vacancy.public-data') }}",
            // Tombol "Melamar" mengarah ke halaman recruitments.index. id lowongan
            // disertakan sebagai query string agar halaman recruitments tahu
            // posisi mana yang sedang dilamar (sesuaikan nama parameter bila perlu).
            recruitments: "{{ route('recruitments.index') }}",
        };

        const statusAccent = {
            open: 'accent-open',
            upcoming: 'accent-upcoming',
            expired: 'accent-expired',
            closed: 'accent-closed',
            draft: 'accent-draft',
        };

        const statusBadge = {
            open: 'badge-open',
            upcoming: 'badge-upcoming',
            expired: 'badge-expired',
            closed: 'badge-closed',
            draft: 'badge-draft',
        };

        const statusHero = {
            open: 'hero-open',
            upcoming: 'hero-upcoming',
            expired: 'hero-expired',
            closed: 'hero-closed',
            draft: 'hero-draft',
        };

        let vacancyStore = [];

        function escapeHtml(value) {
            return $('<div>').text(value ?? '').html();
        }

        // Mengambil versi teks polos dari HTML (untuk ringkasan singkat di kartu)
        function stripHtml(html) {
            return $('<div>').html(html || '').text().replace(/\s+/g, ' ').trim();
        }

        function deadlineText(v) {
            if (v.computed_status === 'open') {
                return v.days_left === 0 ? 'Berakhir hari ini' : `${v.days_left} hari lagi`;
            } else if (v.computed_status === 'upcoming') {
                return `Dibuka ${v.open_date_formatted}`;
            } else if (v.computed_status === 'expired') {
                return `Berakhir ${v.close_date_formatted}`;
            } else if (v.computed_status === 'closed') {
                return 'Pendaftaran ditutup';
            }
            return 'Belum dipublikasikan';
        }

        function melamarButtonHtml(v, blockClass = 'btn-block') {
            const isOpen = v.computed_status === 'open';
            const melamarUrl = `${routes.recruitments}?job_vacancy_id=${v.id}`;
            return isOpen
                ? `<a href="${melamarUrl}" class="btn btn-primary ${blockClass} btn-melamar">
                       <i class="fas fa-paper-plane mr-1"></i> Melamar
                   </a>`
                : `<button class="btn btn-secondary ${blockClass} btn-melamar" disabled>
                       <i class="fas fa-lock mr-1"></i> ${v.computed_status_label}
                   </button>`;
        }

        function renderVacancyCard(v) {
            const accent = statusAccent[v.computed_status] || 'accent-draft';
            const badge = statusBadge[v.computed_status] || 'badge-draft';

            const criteriaTags = (v.criteria || []).slice(0, 4)
                .map(c => `<span class="tag-pill">${escapeHtml(c)}</span>`).join('');
            const moreCriteria = (v.criteria || []).length > 4
                ? `<span class="tag-pill">+${v.criteria.length - 4} lainnya</span>` : '';

            const docTags = (v.required_documents || []).slice(0, 3)
                .map(d => `<span class="tag-pill"><i class="fas fa-paperclip mr-1"></i>${escapeHtml(d)}</span>`).join('');
            const moreDocs = (v.required_documents || []).length > 3
                ? `<span class="tag-pill">+${v.required_documents.length - 3} lainnya</span>` : '';

            const descriptionPreview = stripHtml(v.job_description);

            return `
            <div class="col-xl-4 col-md-6 mb-4" data-id="${v.id}">
                <div class="card vacancy-card shadow h-100">
                    <div class="card-accent ${accent}"></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="vacancy-position">${escapeHtml(v.position)}</div>
                                <div class="vacancy-meta"><i class="fas fa-building mr-1"></i>${escapeHtml(v.department)} &middot; ${v.employment_type_label}</div>
                            </div>
                            <span class="badge-status ${badge}">${v.computed_status_label}</span>
                        </div>

                        <div class="d-flex flex-wrap align-items-center mt-3 mb-2">
                            <div class="mr-3 mb-2">
                                <i class="fas fa-users text-gray-400 mr-1"></i>
                                <span class="small font-weight-bold">${v.total_needed} orang dibutuhkan</span>
                            </div>
                            <span class="badge-applicant mb-2">
                                <i class="fas fa-user-friends mr-1"></i>${v.applicant_count} orang sudah melamar
                            </span>
                        </div>

                        <div class="vacancy-meta mb-2">
                            <i class="fas fa-calendar-day mr-1"></i>${v.open_date_formatted} &ndash; ${v.close_date_formatted}
                            &middot; <span class="font-weight-bold">${deadlineText(v)}</span>
                        </div>

                        ${descriptionPreview ? `<p class="description-preview">${escapeHtml(descriptionPreview)}</p>` : ''}
                        ${v.job_description ? `<button type="button" class="btn btn-link btn-detail-link mb-2 p-0" data-action="detail"><i class="fas fa-circle-info mr-1"></i>Lihat detail lengkap</button>` : ''}

                        ${criteriaTags ? `<div class="mt-2 mb-1"><div class="vacancy-meta font-weight-bold mb-1">Kriteria</div>${criteriaTags}${moreCriteria}</div>` : ''}
                        ${docTags ? `<div class="mt-2"><div class="vacancy-meta font-weight-bold mb-1">Dokumen</div>${docTags}${moreDocs}</div>` : ''}
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0">
                        ${melamarButtonHtml(v)}
                    </div>
                </div>
            </div>`;
        }

        function showDetail(v) {
            const badge = statusBadge[v.computed_status] || 'badge-draft';
            const hero = statusHero[v.computed_status] || 'hero-draft';

            const criteriaList = (v.criteria || []).length
                ? '<ul class="detail-checklist">' + v.criteria.map(c => `<li><i class="fas fa-check-circle text-success"></i><span>${escapeHtml(c)}</span></li>`).join('') + '</ul>'
                : '<p class="detail-empty-note">Tidak ada kriteria khusus.</p>';

            const docList = (v.required_documents || []).length
                ? '<ul class="detail-checklist">' + v.required_documents.map(d => `<li><i class="fas fa-paperclip text-primary"></i><span>${escapeHtml(d)}</span></li>`).join('') + '</ul>'
                : '<p class="detail-empty-note">Tidak ada dokumen khusus.</p>';

            $('#detailBody').html(`
                <div class="detail-hero ${hero}">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <div class="detail-hero-position">${escapeHtml(v.position)}</div>
                            <div class="detail-hero-meta">
                                <i class="fas fa-building mr-1"></i>${escapeHtml(v.department)}
                                &middot; <i class="fas fa-briefcase mr-1"></i>${v.employment_type_label}
                            </div>
                        </div>
                        <span class="badge-status ${badge} mt-1">${v.computed_status_label}</span>
                    </div>
                </div>

                <div class="detail-stats-grid">
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-users"></i></div>
                        <div>
                            <div class="detail-stat-label">Dibutuhkan</div>
                            <div class="detail-stat-value">${v.total_needed} orang</div>
                        </div>
                    </div>
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-user-friends"></i></div>
                        <div>
                            <div class="detail-stat-label">Pelamar</div>
                            <div class="detail-stat-value">${v.applicant_count} orang</div>
                        </div>
                    </div>
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-calendar-day"></i></div>
                        <div>
                            <div class="detail-stat-label">Dibuka</div>
                            <div class="detail-stat-value">${v.open_date_formatted}</div>
                        </div>
                    </div>
                    <div class="detail-stat-item">
                        <div class="detail-stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="detail-stat-label">Ditutup</div>
                            <div class="detail-stat-value">${v.close_date_formatted}</div>
                        </div>
                    </div>
                </div>

                ${v.job_description ? `
                <div class="detail-section">
                    <div class="section-label"><i class="fas fa-align-left mr-1"></i>Deskripsi Pekerjaan</div>
                    <div class="detail-richtext ql-editor">${v.job_description}</div>
                </div>` : ''}

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="detail-section">
                            <div class="section-label"><i class="fas fa-list-check mr-1"></i>Kriteria yang Dibutuhkan</div>
                            ${criteriaList}
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="detail-section">
                            <div class="section-label"><i class="fas fa-file-alt mr-1"></i>Dokumen yang Diperlukan</div>
                            ${docList}
                        </div>
                    </div>
                </div>

                <div class="detail-company-footer">
                    <img src="{{ asset('img/chutex_logo.png') }}" alt="Logo Chutex">
                    <span>PT. Chutex International Indonesia &middot; Menggungan, RT.02/RW.10, Dusun III, Telukan, Kec. Grogol, Kabupaten Sukoharjo, Jawa Tengah 57552</span>
                </div>
            `);

            $('#detailFooter').html(`
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                ${melamarButtonHtml(v, '')}
            `);

            $('#detailModal').modal('show');
        }

        function findVacancy(id) {
            return vacancyStore.find(v => String(v.id) === String(id));
        }

        function loadVacancies() {
            const params = {
                search: $('#searchPosition').val(),
            };

            $('#loadingState').show();
            $('#emptyState').hide();
            $('#vacancyGrid').empty();

            fetch(routes.data + '?' + new URLSearchParams(params))
                .then(async res => {
                    const body = await res.json();
                    if (!res.ok) throw body;
                    return body;
                })
                .then(data => {
                    $('#loadingState').hide();
                    vacancyStore = data.rows;

                    if (!data.rows.length) {
                        $('#emptyState').show();
                        return;
                    }

                    $('#vacancyGrid').html(data.rows.map(renderVacancyCard).join(''));
                })
                .catch(err => {
                    $('#loadingState').hide();
                    $('#emptyState').show();
                    console.error('Gagal memuat data lowongan:', err);
                });
        }

        $(document).ready(function () {
            // Tampilkan SEMUA data job_vacancies begitu halaman dimuat pertama kali.
            loadVacancies();

            $('#filterForm').on('submit', function (e) {
                e.preventDefault();
                loadVacancies();
            });

            $(document).on('click', '[data-action="detail"]', function () {
                const id = $(this).closest('[data-id]').data('id');
                const vacancy = findVacancy(id);
                if (vacancy) showDetail(vacancy);
            });
        });
    </script>
</body>
</html>