{{--
    Form Recruitment (versi AI), desain ulang.

    Layout: rail kiri (dokumen + daftar isi) + kanvas kanan (form per section).
    Desktop: dua kolom. Mobile: satu kolom + action bar lengket di bawah.

    Design system: SB Admin 2 (Nunito, kartu border #e3e6f0, radius .35rem,
    palet #4e73df / #1cc88a / #f6c23e / #e74a3b).
--}}
@extends('recruitment_ai.layout')

@section('title', 'Formulir Pendaftaran | E-HRIS')
@section('page_title', 'Formulir Pendaftaran')

@section('steps')
    <span class="rf-step" data-step="1"><span class="rf-step-dot">1</span><span class="rf-step-label">Dokumen</span></span>
    <span class="rf-step is-active" data-step="2"><span class="rf-step-dot">2</span><span class="rf-step-label">Periksa Data</span></span>
    <span class="rf-step" data-step="3"><span class="rf-step-dot">3</span><span class="rf-step-label">Kirim</span></span>
@endsection

@section('content')

@php
    $hasFormData = ! empty($draft['fields']);
    $hasDocuments = ! empty($draftFiles);
@endphp

@if (session('error'))
    <div class="rf-shell" style="grid-template-columns:minmax(0,1fr);">
        <div class="rf-alert">
            <div class="rf-alert-head">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <b>Pengajuan belum bisa diproses</b>
                    <span>{{ session('error') }}</span>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="rf-shell">

    {{-- ══════════════════ RAIL ══════════════════ --}}
    <aside class="rf-rail">

        {{-- Dokumen --}}
        <div class="rf-panel">
            <div class="rf-panel-head" data-doc-toggle="rf-doc-body" role="button" style="cursor:pointer;">
                <i class="fas fa-folder-open"></i>
                Dokumen
                <span class="rf-count" id="rf-doc-counter">0 / {{ count($documents) }}</span>
                <i class="fas fa-chevron-down ms-1" style="font-size:.65rem;" id="rf-doc-caret"></i>
            </div>

            <div class="collapse show" id="rf-doc-body">
                <div class="rf-panel-body">
                    <div class="rf-progress">
                        <div class="rf-progress-bar" id="rf-progress-bar"></div>
                    </div>
                    <div class="rf-progress-meta">
                        <span id="rf-progress-text">Belum ada dokumen dibaca</span>
                        <span id="rf-progress-pct">0%</span>
                    </div>

                    <p class="rf-note mb-3">
                        <i class="fas fa-magic"></i>
                        <span>Unggah dokumen lalu klik <strong>tombol AI</strong> di sampingnya, isinya dibaca lalu form terisi otomatis. Anda tinggal memeriksa hasilnya.</span>
                    </p>

                    <div class="mt-2" id="rf-doc-list">
                        @foreach ($documents as $docKey => $doc)
                            @php $uploaded = $draftFiles[$docKey] ?? null; @endphp
                            <div class="rf-doc {{ $uploaded ? 'is-uploaded' : '' }}" data-doc="{{ $docKey }}">
                                <span class="rf-doc-icon">
                                    <i class="fas {{ $doc['icon'] }}"></i>
                                </span>

                                <div class="rf-doc-main">
                                    <div class="rf-doc-title">
                                        {{ $doc['label'] }}
                                        @if ($doc['required'])<span class="rf-req">*</span>@endif
                                    </div>
                                    <div class="rf-doc-sub" data-doc-status="{{ $docKey }}">
                                        @if ($uploaded)
                                            {{ $uploaded['name'] }}
                                        @else
                                            {{ $doc['hint'] }}
                                        @endif
                                    </div>
                                </div>

                                <div class="rf-doc-actions">
                                    <button type="button" class="rf-icon-btn" data-doc-choose="{{ $docKey }}"
                                        title="{{ $uploaded ? 'Ganti berkas' : 'Unggah berkas' }}">
                                        <i class="fas fa-upload"></i>
                                    </button>

                                    @if ($doc['extract'])
                                        <button type="button" class="rf-icon-btn {{ $uploaded ? 'is-primary' : '' }}" data-doc-extract="{{ $docKey }}"
                                            title="Baca dengan AI" {{ $uploaded ? '' : 'disabled' }}>
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    @endif

                                    <button type="button" class="rf-icon-btn is-danger {{ $uploaded ? '' : 'd-none' }}" data-doc-remove="{{ $docKey }}"
                                        title="Hapus berkas">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>

                                @php
                                    // accept harus berupa ".ext" (atau MIME type). Tanpa titik,
                                    // browser mengabaikan token-nya sehingga JPG/PNG tidak muncul
                                    // di dialog pemilih berkas.
                                    $acceptAttr = implode(',', array_map(
                                        fn ($ext) => '.' . ltrim(trim($ext), '.'),
                                        explode(',', $doc['accept'])
                                    ));
                                @endphp
                                <input type="file" class="d-none" data-doc-input="{{ $docKey }}" accept="{{ $acceptAttr }}">
                            </div>
                        @endforeach
                    </div>

                    {{-- Proses seluruh dokumen sekaligus --}}
                    <button type="button" class="rf-btn-all" id="rf-extract-all" disabled>
                        <i class="fas fa-magic"></i>
                        <span id="rf-extract-all-label">Proses semua dengan AI</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Daftar isi --}}
        <div class="rf-panel rf-toc">
            <div class="rf-panel-head">
                <i class="fas fa-list-ul"></i>
                Isi Form
            </div>
            <div class="rf-panel-body" id="rf-toc-list">
                @foreach ($sections as $sectionKey => $section)
                    <button type="button" class="rf-toc-item" data-toc="{{ $sectionKey }}">
                        <i class="fas {{ $section['icon'] }} rf-toc-icon"></i>
                        <span>{{ $section['title'] }}</span>
                        <span class="rf-toc-count" data-toc-count="{{ $sectionKey }}">0</span>
                    </button>
                @endforeach
            </div>
        </div>
    </aside>

    {{-- ══════════════════ KANVAS ══════════════════ --}}
    <div>

        {{-- Ringkasan error validasi --}}
        @if ($errors->any())
            <div class="rf-alert">
                <div class="rf-alert-head">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <b>{{ $errors->count() }} data perlu diperbaiki</b>
                        <span>Bagian yang bermasalah sudah ditandai merah dan otomatis dibuka.</span>
                    </div>
                </div>
                <ul>
                    @foreach ($errors->all() as $errorMessage)
                        <li>{{ $errorMessage }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header halaman --}}
        <div class="rf-pagehead">
            <div class="rf-pagehead-main">
                <span class="rf-eyebrow">Rekrutmen</span>
                <h1>Formulir Pendaftaran</h1>
                <p>Isi data manual atau unggah dokumen lalu biarkan AI membacanya. Periksa kembali sebelum mengirim.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 justify-content-end">
                <div class="rf-summary" id="rf-summary"></div>
            </div>
        </div>

        <form id="recruitment-ai-form" action="{{ route('recruitment-ai.submit') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- Section --}}
            @php
            // Section yang dibuka otomatis: yang pertama + yang punya error validasi.
            $openSections = array_values(array_unique(array_merge(['pribadi'], $errorSections)));
        @endphp

        @foreach ($sections as $sectionKey => $section)
                <div class="rf-section tone-{{ $section['tone'] ?? 'primary' }}" id="sec-{{ $sectionKey }}" data-section="{{ $sectionKey }}"
                     style="animation-delay: {{ $loop->index * 45 }}ms;">
                    <div class="rf-section-head" data-target="#body-{{ $sectionKey }}" role="button">
                        <span class="rf-sec-index">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="rf-sec-icon"><i class="fas {{ $section['icon'] }}"></i></span>
                        <span class="rf-sec-title">{{ $section['title'] }}</span>
                        <span class="rf-chip rf-chip-neutral" data-section-count="{{ $sectionKey }}">0</span>
                        <i class="fas fa-chevron-down rf-sec-chevron"></i>
                    </div>

                    <div class="collapse {{ in_array($sectionKey, $openSections) ? 'show' : '' }}" id="body-{{ $sectionKey }}">
                        <div class="rf-section-body">
                            <div class="row rf-formgrid">
                                @foreach ($section['fields'] as $fieldKey => $fieldDef)
                                    @php
                                        // Field daftar berulang / field lebar penuh.
                                        $isWide = ($fieldDef['type'] ?? '') === 'list'
                                            || (int) ($fieldDef['width'] ?? 6) === 12;
                                        // Kalau section ini cuma punya 1 field, judulnya tidak
                                        // perlu diulang (bisa lewat header section).
                                        $showFieldTitle = count($section['fields']) > 1;
                                    @endphp

                                    @if (! empty($fieldDef['copy_from']))
                                        @php $copy = $fieldDef['copy_from']; @endphp
                                        <div class="col-12">
                                            <label class="rf-same">
                                                <input type="checkbox" data-copy-toggle name="same_as_asal" value="1"
                                                    data-sources="{{ implode(',', $copy['sources']) }}"
                                                    data-targets="{{ implode(',', $copy['targets']) }}"
                                                    @checked(old('same_as_asal'))>
                                                <span>{{ $copy['label'] }}</span>
                                            </label>
                                        </div>
                                    @endif

                                    <div class="{{ $isWide ? 'col-12' : 'col-12 col-md-6' }}">
                                        @include('recruitment_ai.partials._field', [
                                            'key'        => $fieldKey,
                                            'definition' => $fieldDef,
                                            'draft'      => $draft,
                                            'draftMeta'  => $draftMeta,
                                            'documents'  => $documents,
                                            'lowConf'    => $lowConf,
                                            'fieldIcons' => $fieldIcons,
                                            'showTitle'  => $showFieldTitle,
                                        ])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Deklarasi + kirim --}}
            <div class="rf-submitblock">
                <div class="rf-note mb-3">
                    <i class="fas fa-circle-info"></i>
                    <span>Pastikan seluruh data sudah Anda periksa. Field bertanda <b>*</b> wajib diisi.</span>
                </div>

                <label class="rf-declare">
                    <input type="checkbox" name="deklarasi" value="1" @checked(old('deklarasi'))>
                    <span>
                        Saya menyatakan bahwa data yang saya isi pada formulir ini benar dan dapat dipertanggungjawabkan,
                        serta bersedia mengikuti proses seleksi dengan data yang sebenarnya.
                    </span>
                </label>
                @error('deklarasi')<p class="rf-error">{{ $message }}</p>@enderror
                @error('documents')<p class="rf-error">{{ $message }}</p>@enderror

                <div class="rf-submit-actions">
                    <button type="button" class="rf-btn rf-btn-outline rf-btn-md rf-btn-reset-fields" id="rf-reset-all-bottom" title="Kosongkan isian formulir">
                        <i class="fas fa-undo"></i>
                        <span>Reset Field</span>
                    </button>
                    <button type="submit" class="rf-btn rf-btn-primary rf-btn-md">
                        <i class="fas fa-paper-plane"></i>
                        Kirim Pengajuan
                    </button>
                    <small>Pengajuan diteruskan ke HR untuk proses seleksi.</small>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Action bar mobile --}}
<div class="rf-actionbar">
    <div class="flex-grow-1" style="min-width:0;">
        <div class="rf-progress"><div class="rf-progress-bar" id="rf-progress-bar-mobile"></div></div>
        <div style="font-size:.68rem;color:#5f6368;font-weight:700;" id="rf-progress-text-mobile">0 / {{ count($documents) }} dokumen</div>
    </div>
    <button type="button" class="rf-btn rf-btn-primary" id="rf-open-docs">
        <i class="fas fa-folder-open"></i>
        Dokumen
    </button>
</div>

@endsection

@push('scripts')
<script>
    const RF = {
        uploadUrl:      @json(route('recruitment-ai.upload')),
        deleteUrl:      @json(route('recruitment-ai.upload.delete')),
        extractUrl:     @json(route('recruitment-ai.extract')),
        resetFieldsUrl: @json(route('recruitment-ai.reset-fields')),
        csrf:           document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        documents:   @json($documents),
        sections:    @json($sections),
        positions:   @json($positionMap),
        lowConf:     {{ $lowConf }},
        dirty:       new Set()
    };

    /* ══════════════ Aksi dokumen ══════════════ */
    /* Accordion dikelola manual (tidak memakai plugin Collapse Bootstrap)
       supaya open/close selalu bekerja, baik di Bootstrap 4 maupun 5,
       dan tidak bergantung pada animasi transisi. */
    const toggleCollapse = (el, force) => {
        if (!el) return false;
        const open = force === undefined ? !el.classList.contains('show') : !!force;
        el.classList.toggle('show', open);
        return open;
    };

    /* Di layar sempit panel dokumen mulai terlipat supaya form langsung terlihat.
       Tombol "Dokumen" di action bar tetap bisa membukanya. */
    (function collapseDocumentsOnSmallScreen() {
        if (!window.matchMedia('(max-width: 1199.98px)').matches) return;
        const body = document.getElementById('rf-doc-body');
        if (body) body.classList.remove('show');
    })();

    document.addEventListener('click', function (e) {
        const chooseBtn = e.target.closest('[data-doc-choose]');
        if (chooseBtn) {
            const key = chooseBtn.getAttribute('data-doc-choose');
            const input = document.querySelector(`[data-doc-input="${key}"]`);
            if (input) input.click();
            return;
        }

        const removeBtn = e.target.closest('[data-doc-remove]');
        if (removeBtn) {
            const key = removeBtn.getAttribute('data-doc-remove');
            const row = removeBtn.closest('[data-doc]');

            fetch(RF.deleteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': RF.csrf },
                body: JSON.stringify({ document: key })
            })
                .then(readJson)
                .then(res => {
                    if (!res.success) throw new Error(apiMessage(res, 'Gagal menghapus berkas.'));

                    row.classList.remove('is-uploaded', 'is-error');
                    const status = row.querySelector('[data-doc-status]');
                    status.className = 'rf-doc-sub';
                    status.textContent = RF.documents[key].hint;
                    row.querySelector('[data-doc-extract]')?.setAttribute('disabled', 'disabled');
                    row.querySelector('[data-doc-remove]').classList.add('d-none');
                    refresh();
                })
                .catch(err => {
                    row.classList.add('is-error');
                    const status = row.querySelector('[data-doc-status]');
                    status.className = 'rf-doc-sub is-err';
                    status.textContent = err.message || 'Gagal menghapus berkas.';
                });
            return;
        }

        const extractBtn = e.target.closest('[data-doc-extract]');
        if (extractBtn) {
            extractDocument(extractBtn.getAttribute('data-doc-extract'));
            return;
        }

        /* Header panel dokumen → buka/tutup */
        const docHead = e.target.closest('[data-doc-toggle]');
        if (docHead) {
            const body = document.getElementById(docHead.getAttribute('data-doc-toggle'));
            const open = toggleCollapse(body);
            const caret = document.getElementById('rf-doc-caret');
            if (caret) caret.style.transform = open ? 'rotate(180deg)' : '';
            return;
        }

        /* Header section → buka/tutup */
        const secHead = e.target.closest('.rf-section-head');
        if (secHead) {
            const sec = secHead.closest('.rf-section');
            const body = document.getElementById(secHead.getAttribute('data-target').replace('#', ''));
            if (body) {
                const open = toggleCollapse(body);
                sec.classList.toggle('is-open', open);
            }
            return;
        }

        /* Daftar isi → buka section & scroll */
        const toc = e.target.closest('[data-toc]');
        if (toc) {
            const key = toc.getAttribute('data-toc');
            const sec = document.getElementById('sec-' + key);
            const body = document.getElementById('body-' + key);
            if (body && !body.classList.contains('show')) {
                toggleCollapse(body, true);
                sec?.classList.add('is-open');
            }
            sec?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    document.addEventListener('change', function (e) {
        const input = e.target.closest('[data-doc-input]');
        if (!input || !input.files || !input.files.length) return;

        uploadDocument(input.getAttribute('data-doc-input'), input.files[0]);
    });

    /* ══════════════ Upload ══════════════ */
    /* Baca respons JSON dengan aman. Kalau server membalas HTML (sesi 419,
       error 500, redirect), tampilkan pesan yang jelas — bukan
       "Unexpected token '<' is not valid JSON". */
    function readJson(response) {
        return response.text().then(function (text) {
            var data = null;
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }

            if (data === null) {
                var msg = 'Server tidak merespons dengan benar (HTTP ' + response.status + ').';
                if (response.status === 419) msg = 'Sesi Anda berakhir. Muat ulang halaman lalu coba lagi.';
                else if (response.status === 413) msg = 'Berkas terlalu besar.';
                else if (response.status === 0) msg = 'Koneksi terputus. Periksa jaringan lalu coba lagi.';
                var err = new Error(msg);
                err.status = response.status;
                throw err;
            }

            return data;
        });
    }

    /* Ambil pesan error dari respons API (validasi Laravel / pesan custom). */
    function apiMessage(res, fallback) {
        if (!res) return fallback;
        if (res.message && res.message !== 'The given data was invalid.') return res.message;
        if (res.errors) {
            var first = Object.values(res.errors)[0];
            if (Array.isArray(first) && first.length) return first[0];
            if (typeof first === 'string') return first;
        }
        return res.message || fallback;
    }

    /* Pesan error AI yang panjang (gabungan error provider) diringkas;
       detail lengkap tetap bisa dilihat lewat tooltip. */
    function setDocError(status, detail) {
        const full = String(detail || '').trim();

        status.className = 'rf-doc-sub is-err';
        status.textContent = friendlyAiError(full);

        if (full) status.title = full.slice(0, 800);
        else status.removeAttribute('title');
    }

    function friendlyAiError(message) {
        if (/kuota|quota|429|rate limit/i.test(message)) return 'Kuota AI sedang penuh. Coba lagi nanti.';
        if (/401|api key|unauthor/i.test(message)) return 'Layanan AI belum bisa diakses. Hubungi admin.';
        if (/timeout|timed out|waktu habis/i.test(message)) return 'AI terlalu lama merespons. Coba lagi.';
        if (/tidak dapat dibaca|tidak mengembalikan|format jawaban/i.test(message)) return 'Jawaban AI tidak terbaca. Coba lagi.';
        return 'AI gagal membaca dokumen. Coba lagi.';
    }

    function uploadDocument(key, file) {
        const row = document.querySelector(`[data-doc="${key}"]`);
        const status = row.querySelector('[data-doc-status]');

        row.classList.add('is-busy');
        row.classList.remove('is-uploaded', 'is-error');
        status.className = 'rf-doc-sub';
        status.textContent = 'Mengunggah…';

        const fd = new FormData();
        fd.append('document', key);
        fd.append('file', file);

        fetch(RF.uploadUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': RF.csrf },
            body: fd
        })
            .then(readJson)
            .then(res => {
                row.classList.remove('is-busy');

                if (!res.success) throw new Error(apiMessage(res, 'Upload gagal.'));

                row.classList.add('is-uploaded');
                status.className = 'rf-doc-sub is-ok';
                status.textContent = res.file.name;
                row.querySelector('[data-doc-extract]')?.removeAttribute('disabled');
                row.querySelector('[data-doc-remove]').classList.remove('d-none');
                refresh();
            })
            .catch(err => {
                row.classList.remove('is-busy');
                row.classList.add('is-error');
                status.className = 'rf-doc-sub is-err';
                status.textContent = err.message || 'Upload gagal.';
            });
    }

    /* ══════════════ Ekstraksi AI ══════════════ */
    function extractDocument(key) {
        const row = document.querySelector(`[data-doc="${key}"]`);
        const status = row.querySelector('[data-doc-status]');

        row.classList.add('is-busy');
        status.className = 'rf-doc-sub';
        status.textContent = 'AI sedang membaca…';

        const pText = document.getElementById('rf-progress-text');
        if (pText) pText.textContent = 'AI sedang membaca dokumen…';

        return fetch(RF.extractUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': RF.csrf },
            body: JSON.stringify({ document: key })
        })
            .then(readJson)
            .then(res => {
                row.classList.remove('is-busy');

                if (res.errors && Object.keys(res.errors).length) {
                    row.classList.add('is-error');
                    setDocError(status, res.errors[key] || '');
                    return;
                }

                if (res.success === false) {
                    row.classList.add('is-error');
                    setDocError(status, apiMessage(res, ''));
                    return;
                }

                const info = (res.results || {})[key] || {};
                status.className = 'rf-doc-sub is-ok';
                status.textContent = `${info.count || 0} field terbaca`;
                if (info.note) status.title = info.note;
                else status.removeAttribute('title');

                applyDraft(res.draft || {});
                refresh();
            })
            .catch(err => {
                row.classList.remove('is-busy');
                row.classList.add('is-error');
                status.className = 'rf-doc-sub is-err';
                status.textContent = err.message || 'Ekstraksi gagal.';
                status.removeAttribute('title');
            });
    }

    /* ══════════════ Proses semua dokumen sekaligus ══════════════ */
    function extractAllDocuments() {
        const list = document.getElementById('rf-extract-all');
        const label = document.getElementById('rf-extract-all-label');
        if (!list) return;

        // Hanya dokumen yang sudah diunggah DAN mendukung ekstraksi.
        const rows = Array.from(document.querySelectorAll('[data-doc].is-uploaded'))
            .filter(row => (RF.documents[row.getAttribute('data-doc')] || {}).extract);

        if (!rows.length) return;

        const originalLabel = label ? label.textContent : '';
        rows.forEach(row => {
            row.classList.remove('is-error');
            row.classList.add('is-busy');
            const status = row.querySelector('[data-doc-status]');
            if (status) {
                status.className = 'rf-doc-sub';
                status.textContent = 'Menunggu AI…';
            }
        });

        list.disabled = true;
        list.classList.add('is-busy');
        if (label) label.textContent = `Membaca ${rows.length} dokumen…`;

        const pText = document.getElementById('rf-progress-text');
        if (pText) pText.textContent = 'AI sedang membaca semua dokumen…';

        return fetch(RF.extractUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': RF.csrf },
            body: JSON.stringify({ document: 'all' })
        })
            .then(readJson)
            .then(res => {
                if (res.success === false && !(res.errors && Object.keys(res.errors).length)) {
                    throw new Error(apiMessage(res, 'Ekstraksi gagal.'));
                }

                rows.forEach(row => {
                    const key = row.getAttribute('data-doc');
                    const status = row.querySelector('[data-doc-status]');
                    row.classList.remove('is-busy');

                    if (res.errors && res.errors[key]) {
                        row.classList.add('is-error');
                        if (status) setDocError(status, res.errors[key]);
                        return;
                    }

                    const info = (res.results || {})[key];
                    if (info && status) {
                        status.className = 'rf-doc-sub is-ok';
                        status.textContent = `${info.count || 0} field terbaca`;
                        if (info.note) status.title = info.note;
                        else status.removeAttribute('title');
                    }
                });

                applyDraft(res.draft || {});
            })
            .catch(err => {
                rows.forEach(row => {
                    row.classList.remove('is-busy');
                    row.classList.add('is-error');
                    const status = row.querySelector('[data-doc-status]');
                    if (status) {
                        status.className = 'rf-doc-sub is-err';
                        status.textContent = err.message || 'Ekstraksi gagal.';
                        status.removeAttribute('title');
                    }
                });
            })
            .finally(() => {
                list.classList.remove('is-busy');
                if (label) label.textContent = originalLabel;
                refresh();
            });
    }

    document.getElementById('rf-extract-all')?.addEventListener('click', extractAllDocuments);

    /* Action bar mobile → buka panel dokumen */
    document.getElementById('rf-open-docs')?.addEventListener('click', function () {
        const body = document.getElementById('rf-doc-body');
        if (body && !body.classList.contains('show')) {
            toggleCollapse(body, true);
            const caret = document.getElementById('rf-doc-caret');
            if (caret) caret.style.transform = 'rotate(180deg)';
        }
        body?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    /* ══════════════ Terapkan hasil AI ══════════════ */
    function applyDraft(draft) {
        const fields = draft.fields || {};
        const meta = draft.meta || {};

        Object.keys(fields).forEach(key => {
            const value = fields[key];
            const wrap = document.querySelector(`[data-field="${key}"]`);
            if (!wrap) return;

            const input = wrap.querySelector(`[name="${cssEscape(key)}"]`);
            if (input && !RF.dirty.has(key)) {
                if (input.type === 'checkbox') {
                    input.checked = !!value;
                } else if (input.type === 'radio') {
                    const val = value === null || value === undefined ? '' : String(value);
                    let hit = false;
                    wrap.querySelectorAll(`input[type="radio"][name="${cssEscape(key)}"]`).forEach(radio => {
                        radio.checked = radio.value === val;
                        if (radio.checked) hit = true;
                    });
                    if (hit) wrap.querySelector('.rf-radios')?.classList.add('is-ai');
                } else {
                    setControlValue(input, value);
                    input.classList.add('is-ai');
                }
            }

            /* grup (ayah / ibu) */
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                Object.keys(value).forEach(sub => {
                    const subInput = wrap.querySelector(`[name="${cssEscape(key + '.' + sub)}"]`);
                    if (subInput && !RF.dirty.has(key + '.' + sub)) {
                        subInput.value = value[sub] ?? '';
                        subInput.classList.add('is-ai');
                    }
                });
            }

            /* daftar berulang */
            if (Array.isArray(value) && value.length) {
                const listEl = wrap.querySelector(`[data-list-rows="${key}"]`);
                const template = wrap.querySelector(`[data-list-template="${key}"]`);
                if (listEl && template) {
                    listEl.innerHTML = '';
                    value.forEach(row => listEl.appendChild(buildRow(template, row)));
                    renumberList(key);
                }
            }
        });

        /* List yang dikosongkan server (mis. data anak) harus ikut hilang. */
        (draft.cleared || []).forEach(key => {
            const listEl = document.querySelector(`[data-list-rows="${cssEscape(key)}"]`);
            if (!listEl) return;

            listEl.innerHTML = '';

            const empty = document.createElement('p');
            empty.className = 'rf-list-empty';
            empty.setAttribute('data-list-empty', key);
            empty.textContent = 'Belum ada data. Klik tombol di bawah untuk menambah, atau biarkan AI yang mengisinya dari dokumen.';
            listEl.appendChild(empty);

            renumberList(key);
        });

        /* Jabatan mengikuti department yang dipilih (termasuk hasil AI). */
        syncJabatanOptions();

        /* Alamat sekarang otomatis disamakan bila AI mengisinya kosong/sama. */
        maybeAutoCopyAddress();

        /* badge AI & pesan konflik */
        Object.keys(meta).forEach(key => {
            const badgeWrap = document.querySelector(`[data-badge-wrap="${key}"]`);
            if (badgeWrap) {
                badgeWrap.innerHTML = aiChipHtml(meta[key]);
            }

            const conflictWrap = document.querySelector(`[data-conflict-wrap="${key}"]`);
            if (conflictWrap) {
                conflictWrap.innerHTML = aiConflictHtml(meta[key]);
            }
        });

        /* Sinkronkan status masih bekerja & validasi umur minimal */
        syncMasihBekerja();
        validateMinAge(document.getElementById('fld-tanggal_lahir'));
    }

    function aiChipHtml(m) {
        const conf = Number(m.confidence || 0);
        const doc = RF.documents[m.source] || {};
        const srcLabel = doc.short || doc.label || m.source || '-';
        const srcFull = doc.label || m.source || '-';
        const cls = m.conflict ? 'conflict' : (conf < RF.lowConf ? 'low' : 'ok');
        const text = m.conflict ? 'Konflik' : `${srcLabel} · ${Math.round(conf * 100)}%`;
        const tip = m.conflict
            ? 'Ditemukan lebih dari satu nilai berbeda, pilih yang benar'
            : `Sumber: ${srcFull} · keyakinan ${Math.round(conf * 100)}%`;

        return `<span class="rf-ai-chip ${cls}" title="${escapeHtml(tip)}"><i class="fas fa-magic"></i> ${escapeHtml(text)}</span>`;
    }

    function aiConflictHtml(m) {
        if (!m.conflict || !(m.alternatives || []).length) {
            return '';
        }

        return '<div class="rf-ai-alt"><b><i class="fas fa-exclamation-triangle me-1"></i> Nilai lain yang ditemukan:</b>'
            + m.alternatives.map(a => {
                const v = (a.value && typeof a.value === 'object') ? JSON.stringify(a.value) : a.value;
                const lbl = (RF.documents[a.source] || {}).short || (RF.documents[a.source] || {}).label || a.source || '-';
                return `<span class="rf-ai-alt-item"><b>${escapeHtml(v)}</b> &mdash; dari ${escapeHtml(lbl)}</span>`;
            }).join('')
            + '</div>';
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cssEscape(value) {
        return String(value).replace(/["\\]/g, '\\$&');
    }

    /* Nilai AI dicocokkan ke opsi select, atau tambahkan sebagai opsi baru
       jika bukan master select (mis. posisi baru). */
    function setControlValue(input, value) {
        const val = value === null || value === undefined ? '' : String(value);

        if (input.tagName === 'SELECT' && val !== '') {
            const options = Array.from(input.options);
            const exists = options.some(opt => opt.value === val);

            if (!exists) {
                // Cocokkan longgar / alias untuk tingkat pendidikan
                const isPendidikan = input.name && (input.name.includes('pendidikan') || input.name.includes('tingkat'));
                let matched = null;

                if (isPendidikan) {
                    const uv = val.toUpperCase();
                    if (uv.includes('SMK') || uv.includes('SMA') || uv.includes('SLTA') || uv.includes('SMU')) {
                        matched = options.find(o => o.value === 'SMA/SMK' || o.value === 'SMA' || o.value === 'SMK');
                    } else if (uv.includes('SMP') || uv.includes('SLTP')) {
                        matched = options.find(o => o.value === 'SMP');
                    } else if (/\bSD\b/.test(uv)) {
                        matched = options.find(o => o.value === 'SD');
                    } else if (uv.includes('S1') || uv.includes('D4') || uv.includes('SARJANA')) {
                        matched = options.find(o => o.value === 'S1');
                    } else if (uv.includes('D3') || uv.includes('DIPLOMA')) {
                        matched = options.find(o => o.value === 'D3');
                    } else if (uv.includes('S2') || uv.includes('MAGISTER')) {
                        matched = options.find(o => o.value === 'S2');
                    } else if (uv.includes('S3') || uv.includes('DOKTOR')) {
                        matched = options.find(o => o.value === 'S3');
                    }
                } else if (input.name && input.name.includes('transportasi')) {
                    const uv = val.toUpperCase();
                    if (uv.includes('MOBIL')) {
                        matched = options.find(o => o.value.includes('Mobil'));
                    } else if (uv.includes('MOTOR')) {
                        matched = options.find(o => o.value.includes('Motor'));
                    } else if (uv.includes('UMUM') || uv.includes('BUS') || uv.includes('KERETA') || uv.includes('ANGKOT')) {
                        matched = options.find(o => o.value.includes('Umum'));
                    } else if (uv.includes('KALI') || uv.includes('JALAN')) {
                        matched = options.find(o => o.value.includes('Jalan'));
                    }
                } else {
                    matched = options.find(o => o.value.toLowerCase() === val.toLowerCase());
                }

                if (matched) {
                    input.value = matched.value;
                    return;
                }

                // Jangan tambahkan option tak dikenal pada select yang punya aturan validasi ketat
                const isMasterSelect = isPendidikan || (input.name && (
                    input.name.includes('transportasi') ||
                    input.name.includes('agama') ||
                    input.name.includes('golongan_darah') ||
                    input.name.includes('status_pernikahan') ||
                    input.name.includes('status_domisili') ||
                    input.name.includes('warga_negara') ||
                    input.name.includes('gender')
                ));

                if (!isMasterSelect) {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    input.appendChild(opt);
                } else {
                    return;
                }
            }
        }

        input.value = val;
    }

    /* ══════════════ Department → Posisi ══════════════ */
    function jabatanOptionsFor(dept, current) {
        const select = document.getElementById('fld-jabatan');
        if (!select) return;

        select.innerHTML = '<option value="">Pilih...</option>';

        (RF.positions[dept] || []).forEach(pos => {
            const opt = document.createElement('option');
            opt.value = pos;
            opt.textContent = pos;
            select.appendChild(opt);
        });

        // Pertahankan nilai lama / hasil AI yang tidak ada di master.
        if (current && !Array.from(select.options).some(opt => opt.value === current)) {
            const opt = document.createElement('option');
            opt.value = current;
            opt.textContent = current;
            select.appendChild(opt);
        }

        select.value = current || '';
    }

    function syncJabatanOptions(forceValue) {
        const deptEl = document.getElementById('fld-department');
        const jabEl  = document.getElementById('fld-jabatan');
        if (!deptEl || !jabEl) return;

        const current = forceValue !== undefined ? forceValue : jabEl.value;
        jabatanOptionsFor(deptEl.value, current);
    }

    document.getElementById('fld-department')?.addEventListener('change', function () {
        const jabEl = document.getElementById('fld-jabatan');
        const current = jabEl ? jabEl.value : '';
        // Pertahankan posisi kalau memang milik department yang baru dipilih.
        const keep = current && (RF.positions[this.value] || []).includes(current) ? current : '';
        syncJabatanOptions(keep);
    });
    syncJabatanOptions();

    /* ══════════════ Alamat sekarang = alamat asal ══════════════ */
    const copyToggle  = document.querySelector('[data-copy-toggle]');
    const copySources = copyToggle ? copyToggle.getAttribute('data-sources').split(',').filter(Boolean) : [];
    const copyTargets = copyToggle ? copyToggle.getAttribute('data-targets').split(',').filter(Boolean) : [];

    function controlOf(key) {
        const wrap = document.querySelector(`[data-field="${cssEscape(key)}"]`);
        return wrap ? wrap.querySelector(`[name="${cssEscape(key)}"]`) : null;
    }

    function copiedAddresses() {
        if (!copyToggle || !copyToggle.checked) return;

        copyTargets.forEach((target, index) => {
            const sourceKey = copySources[index];
            const wrap = document.querySelector(`[data-field="${cssEscape(target)}"]`);
            const el = controlOf(target);
            if (!wrap || !el) return;

            wrap.classList.add('is-locked');
            el.tabIndex = -1;

            const source = controlOf(sourceKey);
            setControlValue(el, source ? source.value : '');
            el.classList.remove('is-ai');
        });
    }

    function unlockAddresses() {
        copyTargets.forEach(target => {
            const wrap = document.querySelector(`[data-field="${cssEscape(target)}"]`);
            const el = controlOf(target);
            if (!wrap || !el) return;

            wrap.classList.remove('is-locked');
            el.removeAttribute('tabindex');
        });
    }

    function maybeAutoCopyAddress() {
        if (!copyToggle) return;

        if (copyToggle.checked) {
            copiedAddresses();
            return;
        }

        // Jangan menimpa isian manual pengguna.
        if (RF.dirty.has('alamat_sekarang')) return;

        const asal = (controlOf('alamat_asal')?.value || '').trim();
        if (!asal) return;

        const sekarang = (controlOf('alamat_sekarang')?.value || '').trim();
        const sama = sekarang !== '' && sekarang.toUpperCase() === asal.toUpperCase();

        if (sekarang === '' || sama) {
            copyToggle.checked = true;
            copiedAddresses();
        }
    }

    if (copyToggle) {
        copyToggle.addEventListener('change', function () {
            if (this.checked) copiedAddresses();
            else unlockAddresses();
        });

        copySources.forEach(key => {
            const el = controlOf(key);
            if (!el) return;
            el.addEventListener('input', copiedAddresses);
            el.addEventListener('change', copiedAddresses);
        });

        // Halaman kembali dari validasi error: checkbox sudah tercentang.
        if (copyToggle.checked) copiedAddresses();
    }

    /* ══════════════ Daftar berulang ══════════════ */
    function buildRow(template, row) {
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replace(/__INDEX__/g, '0');
        const node = holder.firstElementChild;

        if (row) {
            Object.keys(row).forEach(key => {
                const inputs = Array.from(node.querySelectorAll(`[name$="[${key}]"]`));
                if (!inputs.length) return;

                // Radio group (gender saudara/anak): centang yang nilainya cocok.
                if (inputs[0].type === 'radio') {
                    const val = row[key] === null || row[key] === undefined ? '' : String(row[key]);
                    inputs.forEach(radio => { radio.checked = radio.value === val; });
                    if (val) inputs[0].closest('.rf-radios')?.classList.add('is-ai');
                    return;
                }

                const input = inputs[0];

                if (input.type === 'checkbox') {
                    input.checked = !!row[key];
                } else {
                    setControlValue(input, row[key]);
                    input.classList.add('is-ai');
                }
            });
        }

        if (node) {
            syncMasihBekerja(node);
        }

        return node;
    }

    function renumberList(key) {
        const container = document.querySelector(`[data-list-rows="${key}"]`);
        if (!container) return;

        const rows = Array.from(container.querySelectorAll('[data-row]'));

        rows.forEach((row, index) => {
            // update nama & id input
            row.querySelectorAll('input, select, textarea').forEach(el => {
                el.name = el.name.replace(/\[\d+\]/, `[${index}]`);
                if (el.id) el.id = el.id.replace(/-\d+-/, `-${index}-`);
            });

            // update nomor & judul card
            const num = row.querySelector('.rf-entry-num');
            if (num) num.textContent = index + 1;

            const title = row.querySelector('.rf-entry-title');
            if (title) {
                const base = title.dataset.base || title.textContent.replace(/\s*ke-\d+\s*$/, '');
                title.dataset.base = base;
                title.textContent = `${base} ke-${index + 1}`;
            }
        });
    }

    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('[data-add-list]');
        if (addBtn) {
            const key = addBtn.getAttribute('data-add-list');
            const container = document.querySelector(`[data-list-rows="${key}"]`);
            const template = document.querySelector(`[data-list-template="${key}"]`);
            if (!container || !template) return;

            container.querySelector('[data-list-empty]')?.remove();
            container.appendChild(buildRow(template, null));
            renumberList(key);
            refresh();
            return;
        }

        const rmBtn = e.target.closest('[data-remove-row]');
        if (rmBtn) {
            const row = rmBtn.closest('[data-row]');
            const key = row.getAttribute('data-row');
            row.remove();
            renumberList(key);
            refresh();
        }
    });

    /* Tandai field yang disentuh user, nilai AI tidak menimpa */
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!el.name) return;
        const wrap = el.closest('[data-field]');
        if (!wrap) return;
        RF.dirty.add(wrap.getAttribute('data-field'));
        el.classList.remove('is-ai');
        el.closest('.rf-radios')?.classList.remove('is-ai');
    });

    /* ══════════════ Status Masih Bekerja & Validasi Umur ══════════════ */
    function syncMasihBekerja(root) {
        const ctx = root || document;
        ctx.querySelectorAll('input[type="checkbox"][name$="[masih_bekerja]"]').forEach(cb => {
            const row = cb.closest('[data-row]');
            if (!row) return;
            const sampai = row.querySelector('[name$="[sampai]"]');
            if (!sampai) return;

            if (cb.checked) {
                sampai.value = '';
                sampai.disabled = true;
            } else {
                sampai.disabled = false;
            }
        });
    }

    function validateMinAge(input) {
        if (!input || !input.value) return true;
        const birth = new Date(input.value + 'T00:00:00');
        if (isNaN(birth.getTime())) return true;

        const today = new Date();
        const minAgeDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());

        if (birth > minAgeDate) {
            input.setCustomValidity('Pendaftar harus berusia minimal 18 tahun.');
            input.classList.add('is-invalid');
            return false;
        } else {
            input.setCustomValidity('');
            input.classList.remove('is-invalid');
            return true;
        }
    }

    /* Ringkasan "terisi" ikut berubah saat user mengetik/memilih. */
    let refreshTimer = null;
    function scheduleRefresh() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(refresh, 150);
    }
    document.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'fld-tanggal_lahir') {
            validateMinAge(e.target);
        }
        if (e.target.closest('#recruitment-ai-form')) scheduleRefresh();
    });
    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[type="checkbox"][name$="[masih_bekerja]"]')) {
            syncMasihBekerja(e.target.closest('[data-row]'));
        }
        if (e.target && e.target.id === 'fld-tanggal_lahir') {
            validateMinAge(e.target);
        }
        if (e.target.closest('#recruitment-ai-form')) scheduleRefresh();
    });

    /* Nomor telepon tanpa tanda hubung/spasi (termasuk isian manual). */
    document.addEventListener('blur', function (e) {
        const wrap = e.target.closest?.('[data-field]');
        const key = wrap?.getAttribute('data-field');
        if (!key || !['nomor_hp', 'no_telepon', 'no_telepon_darurat'].includes(key)) return;

        let digits = (e.target.value || '').replace(/\D+/g, '');
        if (!digits) return;
        if (digits.startsWith('62')) digits = '0' + digits.slice(2);
        else if (digits.startsWith('8')) digits = '0' + digits;

        e.target.value = digits;
        scheduleRefresh();
    }, true);

    /* ══════════════ Status & ringkasan ══════════════ */
    function refresh() {
        /* progress dokumen */
        const uploaded = document.querySelectorAll('[data-doc].is-uploaded').length;
        const total = Object.keys(RF.documents).length;
        const read = document.querySelectorAll('[data-doc-sub].is-ok').length;

        const pct = total ? Math.round((uploaded / total) * 100) : 0;
        ['rf-progress-bar', 'rf-progress-bar-mobile'].forEach(id => {
            const bar = document.getElementById(id);
            if (bar) bar.style.width = pct + '%';
        });

        const counter = document.getElementById('rf-doc-counter');
        if (counter) counter.textContent = `${uploaded} / ${total}`;

        const pText = document.getElementById('rf-progress-text');
        if (pText) pText.textContent = read ? `${read} dokumen terbaca` : (uploaded ? 'Siap dibaca AI' : 'Belum ada dokumen dibaca');

        const pPct = document.getElementById('rf-progress-pct');
        if (pPct) pPct.textContent = pct + '%';

        const pMobile = document.getElementById('rf-progress-text-mobile');
        if (pMobile) pMobile.textContent = `${uploaded} / ${total} dokumen`;

        /* Tombol "proses semua" aktif kalau sudah ada dokumen terunggah */
        const allBtn = document.getElementById('rf-extract-all');
        if (allBtn && !allBtn.classList.contains('is-busy')) {
            allBtn.disabled = uploaded === 0;
        }

        /* ringkasan field */
        let aiFilled = 0, needCheck = 0;
        const filled = countFilled(document).filled;

        document.querySelectorAll('[data-field]').forEach(wrap => {
            const chip = wrap.querySelector('.rf-ai-chip');
            if (chip) {
                aiFilled++;
                if (chip.classList.contains('low') || chip.classList.contains('conflict')) needCheck++;
            }
        });

        const summary = document.getElementById('rf-summary');
        if (summary) {
            summary.innerHTML = [
                `<span class="rf-chip rf-chip-primary"><i class="fas fa-magic"></i> ${aiFilled} field dari AI</span>`,
                needCheck ? `<span class="rf-chip rf-chip-warning"><i class="fas fa-exclamation-triangle"></i> ${needCheck} perlu dicek</span>` : '',
                `<span class="rf-chip rf-chip-neutral"><i class="fas fa-pen"></i> ${filled} terisi</span>`,
            ].filter(Boolean).join('');
        }

        /* counter per section */
        document.querySelectorAll('[data-section-count]').forEach(el => {
            const key = el.getAttribute('data-section-count');
            const sec = document.getElementById('sec-' + key);
            if (!sec) return;

            const counts = countFilled(sec);
            const f = counts.filled;

            const ai = sec.querySelectorAll('.rf-ai-chip').length;
            const warn = sec.querySelectorAll('.rf-ai-chip.low, .rf-ai-chip.conflict').length;

            el.textContent = ai ? `${f} terisi · ${ai} AI` : `${f} terisi`;
            el.className = 'rf-chip ' + (warn ? 'rf-chip-warning' : (ai ? 'rf-chip-primary' : 'rf-chip-neutral'));

            const toc = document.querySelector(`[data-toc-count="${key}"]`);
            if (toc) {
                toc.textContent = `${f}/${counts.total}`;
                toc.parentElement.classList.toggle('is-warn', warn > 0);
            }
        });
    }

    /* Hitung field terisi; sekelompok radio dihitung satu field. */
    function countFilled(root) {
        const radios = new Set();
        let filled = 0, total = 0;

        root.querySelectorAll('input:not([type=hidden]):not([data-copy-toggle]), select, textarea').forEach(input => {
            if (input.type === 'radio') {
                if (radios.has(input.name)) return;
                radios.add(input.name);
                total++;
                if (input.checked) filled++;
                return;
            }

            total++;
            if (input.type === 'checkbox' ? input.checked : (input.value || '').trim() !== '') filled++;
        });

        return { filled, total };
    }

    /* ══════════════ Reset Semua Field ══════════════ */
    function resetAllFields() {
        if (!confirm('Apakah Anda yakin ingin mengosongkan semua isian formulir? Dokumen yang sudah diunggah akan tetap tersimpan.')) {
            return;
        }

        const form = document.getElementById('recruitment-ai-form');
        if (!form) return;

        // 1. Text, number, date, month, email, textarea
        form.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea').forEach(input => {
            input.value = '';
            input.classList.remove('is-ai', 'is-invalid');
        });

        // 2. Selects
        form.querySelectorAll('select').forEach(sel => {
            sel.value = '';
            sel.selectedIndex = 0;
            sel.classList.remove('is-ai', 'is-invalid');
        });

        // 3. Radios
        form.querySelectorAll('input[type=radio]').forEach(radio => {
            radio.checked = false;
        });
        form.querySelectorAll('.rf-radios').forEach(wrap => wrap.classList.remove('is-ai'));

        // 4. Checkboxes (termasuk same_as_asal dan deklarasi)
        form.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.checked = false;
        });
        if (typeof unlockAddresses === 'function') {
            unlockAddresses();
        }

        // 5. Repeatable lists (education, experience, saudara, anak)
        document.querySelectorAll('[data-list-rows]').forEach(listEl => {
            const key = listEl.getAttribute('data-list-rows');
            listEl.innerHTML = '';
            const empty = document.createElement('p');
            empty.className = 'rf-list-empty';
            empty.setAttribute('data-list-empty', key);
            empty.textContent = 'Belum ada data. Klik tombol di bawah untuk menambah, atau biarkan AI yang mengisinya dari dokumen.';
            listEl.appendChild(empty);
        });

        // 6. Hapus semua AI badges & pesan konflik
        document.querySelectorAll('[data-badge-wrap]').forEach(b => {
            b.innerHTML = '';
        });
        document.querySelectorAll('[data-conflict-wrap]').forEach(c => {
            c.innerHTML = '';
        });

        // 7. Bersihkan tracking dirty field
        RF.dirty.clear();

        // 8. Sinkronkan opsi jabatan
        if (typeof syncJabatanOptions === 'function') {
            syncJabatanOptions('');
        }

        // 9. Reset status dokumen di panel samping menjadi normal
        document.querySelectorAll('[data-doc].is-uploaded').forEach(row => {
            const status = row.querySelector('[data-doc-status]');
            if (status && status.classList.contains('is-ok')) {
                status.className = 'rf-doc-sub';
                status.removeAttribute('title');
            }
        });

        // 10. Perbarui ringkasan, counter, status kerja & umur
        syncMasihBekerja();
        validateMinAge(document.getElementById('fld-tanggal_lahir'));
        refresh();

        // 11. Kirim reset ke session server
        if (RF.resetFieldsUrl) {
            fetch(RF.resetFieldsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': RF.csrf,
                    'Accept': 'application/json'
                }
            }).catch(err => {
                console.warn('Gagal mereset draft di server:', err);
            });
        }
    }

    document.querySelectorAll('.rf-btn-reset-fields').forEach(btn => {
        btn.addEventListener('click', resetAllFields);
    });

    /* chevron section & panel dokumen mengikuti state open/close */
    document.querySelectorAll('.rf-section').forEach(sec => {
        const body = document.getElementById('body-' + sec.getAttribute('data-section'));
        if (body && body.classList.contains('show')) sec.classList.add('is-open');
    });

    document.addEventListener('DOMContentLoaded', function () {
        syncMasihBekerja();
        validateMinAge(document.getElementById('fld-tanggal_lahir'));
        refresh();
    });
    syncMasihBekerja();
    validateMinAge(document.getElementById('fld-tanggal_lahir'));
    refresh();
</script>
@endpush
