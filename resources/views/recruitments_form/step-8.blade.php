{{--
    View  : recruitments/step-8.blade.php
    Step  : 8 — Upload Dokumen
    Fields: Surat Lamaran*, CV*, Scan KTP*, Scan KK, Pas Foto,
            Scan Ijazah, Scan Akta Kelahiran, Scan SKCK, Scan Blanko Kesehatan
    + Deklarasi checkbox
--}}

@extends('layouts_recruitments.app')

@section('title', 'Registrasi — Step 8: Upload Dokumen | RecruitFlow')

@push('styles')
<style>
    /* ---------- Upload zone ---------- */
    .upload-zone {
        border: 2px dashed #c4c6d5;
        border-radius: 0.75rem;
        padding: 1.25rem 1rem;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 0.5rem;
        min-height: 120px; cursor: pointer;
        transition: border-color .2s, background .2s;
        position: relative;
        text-align: center;
    }
    .upload-zone:hover {
        border-color: #2b54bf;
        background: rgba(43,84,191,.04);
    }
    .upload-zone.uploaded {
        border-color: #1a6b3c;
        border-style: solid;
        background: rgba(26,107,60,.04);
    }
    .upload-zone.has-error {
        border-color: #ba1a1a;
        background: rgba(186,26,26,.03);
    }
    .upload-zone .uz-icon {
        font-size: 2rem; color: #5b5e6c;
        transition: color .2s;
    }
    .upload-zone:hover .uz-icon { color: #2b54bf; }
    .upload-zone.uploaded .uz-icon { color: #1a6b3c; }
    .upload-zone .uz-label {
        font-size: 0.8rem; color: #434653;
        transition: color .2s; font-weight: 600;
    }
    .upload-zone.uploaded .uz-label { color: #1a6b3c; }
    .upload-zone .uz-hint {
        font-size: 0.65rem; color: rgba(67,70,83,.55);
        font-weight: 600; letter-spacing: 0.02em;
    }

    /* Required badge */
    .doc-required {
        display: inline-flex; align-items: center; gap: 0.25rem;
        font-size: 0.65rem; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; color: #ba1a1a;
        background: #ffdad6; border-radius: 0.75rem;
        padding: 0.1rem 0.5rem; margin-left: 0.375rem;
    }

    /* Section label */
    .doc-label {
        display: flex; align-items: center;
        font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.05em; text-transform: uppercase;
        color: #434653; margin-bottom: 0.5rem;
    }

    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }

    /* Declaration area */
    .rf-declaration {
        display: flex; align-items: flex-start; gap: 0.875rem;
        padding: 1.25rem 2rem;
        background: #f3f3fc;
        border-top: 1px solid #c4c6d5;
        cursor: pointer;
    }
    .rf-declaration input[type=checkbox] {
        width: 1.1rem; height: 1.1rem; flex-shrink: 0;
        margin-top: 0.15rem; accent-color: #2b54bf; cursor: pointer;
    }
    .rf-declaration p {
        font-size: 0.8125rem; line-height: 1.65; color: #434653;
    }

    /* Section group header */
    .doc-group-title {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; color: #2b54bf;
        padding: 0.25rem 0.625rem; border-radius: 0.75rem;
        background: rgba(43,84,191,.08);
        margin-bottom: 1rem;
    }
    .doc-group-title span.material-symbols-outlined { font-size: 0.9rem; }

    /* Section block anim */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .rf-section-block { animation: rfFadeUp .35s ease both; }

    /* Submit loading spinner */
    @keyframes spin { to { transform: rotate(360deg); } }
    .animate-spin { animation: spin .8s linear infinite; }
</style>
@endpush

@section('breadcrumb')
    <span class="hover:text-primary cursor-pointer transition-colors">Dashboard</span>
    <span class="mx-2 text-outline">/</span>
    <span class="text-on-surface font-bold">Registrasi</span>
@endsection

@section('page_title', 'Form Pendaftaran')

@section('content')

    @php
        $steps = [
            'Data Pribadi', 'Kontak & Alamat', 'Pengalaman Kerja', 'Data Keluarga',
            'Riwayat Pendidikan', 'Motivasi & Kegiatan', 'Data Fisik',
            'Upload Dokumen',
        ];
        $currentStep = 8;
        $totalSteps  = count($steps);

        /*
         | Document definitions
         | [field_name, label, accept, hint, required, icon]
         */
        $docs = [
            ['surat_lamaran',        'Surat Lamaran',                '.pdf,.jpg,.jpeg,.png', 'PDF / JPG · Maks 2MB', true,  'description'],
            ['cv',                   'Curriculum Vitae (CV)',         '.pdf,.jpg,.jpeg,.png', 'PDF / JPG · Maks 2MB', true,  'person'],
            ['scan_ktp',             'Scan KTP',                     '.jpg,.jpeg,.png,.pdf', 'JPG / PNG · Maks 2MB', true,  'badge'],
            ['scan_kk',              'Scan Kartu Keluarga (KK)',      '.jpg,.jpeg,.png,.pdf', 'JPG / PNG · Maks 2MB', false, 'family_restroom'],
            ['pas_foto',             'Pas Foto 3×4 (BG Merah)',       '.jpg,.jpeg,.png',      'JPG / PNG · Maks 2MB', false, 'face'],
            ['ijazah',               'Scan Ijazah Terakhir',          '.pdf,.jpg,.jpeg,.png', 'PDF / JPG · Maks 2MB', false, 'school'],
            ['scan_akta_kelahiran',  'Scan Akta Kelahiran',           '.jpg,.jpeg,.png,.pdf', 'JPG / PNG · Maks 2MB', false, 'article'],
            ['scan_skck',            'Scan SKCK Aktif',               '.jpg,.jpeg,.png,.pdf', 'JPG / PNG · Maks 2MB', false, 'verified_user'],
            ['scan_blanko_kesehatan','Scan Blanko Kesehatan',         '.jpg,.jpeg,.png,.pdf', 'JPG / PNG · Maks 2MB', false, 'health_and_safety'],
        ];
    @endphp

    @include('layouts_recruitments.partials._stepper', ['currentStep' => $currentStep, 'steps' => $steps])

    <div class="bg-surface-container-lowest rounded-xl
                shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
                border-l-4 border-primary overflow-hidden mb-8">

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-8 py-5 border-b border-outline-variant bg-surface-container-low">
            <span class="material-symbols-outlined text-primary"
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">upload_file</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">
                    Step 8 — Upload Dokumen
                </h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Format: PDF / JPG / PNG &middot; Maksimal <strong>2MB</strong> per file.
                    Kolom bertanda <span class="text-error font-bold">*</span> wajib diupload.
                </p>
            </div>
        </div>

        <form id="step8Form"
              action="{{ route('recruitments.step.store', ['step' => 8]) }}"
              method="POST"
              enctype="multipart/form-data"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 rf-section-block">

                {{-- Global Error Alert --}}
                @if(session('error'))
                    <div class="mb-6 p-4 rounded-xl bg-error-container border border-error/20 flex items-start gap-3">
                        <span class="material-symbols-outlined text-error mt-0.5">error</span>
                        <div>
                            <h4 class="text-label-lg font-bold text-on-error-container">Gagal Menyimpan Pendaftaran</h4>
                            <p class="text-body-md text-on-error-container mt-1">{{ session('error') }}</p>
                        </div>
                    </div>
                @endif

                {{-- Info note --}}
                <div class="flex items-start gap-3 p-3 bg-primary/5 border border-primary/15 rounded-xl mb-7">
                    <span class="material-symbols-outlined text-primary mt-0.5" style="font-size:1rem;">info</span>
                    <p class="text-label-lg text-on-surface-variant font-normal leading-relaxed">
                        Pastikan dokumen terbaca jelas, tidak buram, dan belum kadaluarsa.
                        Ukuran file maksimal <strong class="text-on-surface">2MB</strong> per dokumen.
                    </p>
                </div>

                {{-- Wajib group --}}
                <div class="mb-7">
                    <div class="doc-group-title">
                        <span class="material-symbols-outlined">priority_high</span>
                        Dokumen Wajib
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                        @foreach($docs as [$field, $label, $accept, $hint, $required, $icon])
                            @if($required)
                                <div>
                                    <div class="doc-label">
                                        {{ $label }}
                                        <span class="doc-required">* Wajib</span>
                                    </div>
                                    <div class="upload-zone @error($field) has-error @enderror"
                                         id="zone_{{ $field }}"
                                         onclick="document.getElementById('{{ $field }}').click()">
                                        <span class="material-symbols-outlined uz-icon" id="icon_{{ $field }}">{{ $icon }}</span>
                                        <span class="uz-label" id="label_{{ $field }}">Ketuk untuk upload</span>
                                        <span class="uz-hint">{{ $hint }}</span>
                                        <input id="{{ $field }}"
                                               name="{{ $field }}"
                                               type="file" class="hidden"
                                               accept="{{ $accept }}"
                                               onchange="previewFile(this,'{{ $field }}')">
                                    </div>
                                    @error($field)
                                        <p class="rf-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Opsional group --}}
                <div>
                    <div class="doc-group-title" style="background:rgba(91,94,108,.08); color:#5b5e6c;">
                        <span class="material-symbols-outlined" style="font-size:.9rem; color:#5b5e6c;">folder_open</span>
                        <span style="color:#5b5e6c;">Dokumen Pendukung <span style="font-weight:400;">(Opsional)</span></span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                        @foreach($docs as [$field, $label, $accept, $hint, $required, $icon])
                            @if(!$required)
                                <div>
                                    <div class="doc-label" style="color:#747684;">{{ $label }}</div>
                                    <div class="upload-zone"
                                         id="zone_{{ $field }}"
                                         onclick="document.getElementById('{{ $field }}').click()">
                                        <span class="material-symbols-outlined uz-icon" id="icon_{{ $field }}">{{ $icon }}</span>
                                        <span class="uz-label" id="label_{{ $field }}">Ketuk untuk upload</span>
                                        <span class="uz-hint">{{ $hint }}</span>
                                        <input id="{{ $field }}"
                                               name="{{ $field }}"
                                               type="file" class="hidden"
                                               accept="{{ $accept }}"
                                               onchange="previewFile(this,'{{ $field }}')">
                                    </div>
                                    @error($field)
                                        <p class="rf-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

            </div>{{-- /px-8 py-8 --}}

            {{-- Declaration --}}
            <label class="rf-declaration" for="deklarasi">
                <input type="checkbox" id="deklarasi" name="deklarasi" value="1"
                       {{ old('deklarasi') ? 'checked' : '' }} required>
                <p>
                    Saya menyatakan bahwa semua data dan dokumen yang saya isi adalah
                    <strong class="text-on-surface">benar adanya</strong> dan bersedia
                    mempertanggungjawabkan kebenaran informasi tersebut dalam proses rekrutmen.
                </p>
            </label>
            @error('deklarasi')
                <p class="rf-error px-8 pb-2">{{ $message }}</p>
            @enderror

            {{-- Nav Footer --}}
            <div class="flex items-center justify-between px-8 py-5
                        border-t border-outline-variant bg-surface-container-low">

                <a href="{{ route('recruitments.step', ['step' => 7]) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                          border border-outline text-on-surface-variant text-label-lg font-bold
                          hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Sebelumnya
                </a>

                <span class="text-label-lg text-on-surface-variant">Step 8 dari 8</span>

                <button type="submit" id="submitBtn"
                        class="flex items-center justify-center gap-2 px-6 sm:px-10 py-3 sm:py-2.5 rounded-lg bg-primary text-on-primary hover:bg-primary/90 font-bold text-[0.75rem] sm:text-label-lg shadow-md transition-all hover:shadow-lg">
                    <span class="material-symbols-outlined text-[1.2rem]">check_circle</span>
                    Kirim Pendaftaran
                </button>

            </div>

        </form>
    </div>

@endsection

@push('scripts')
<script>
    /**
     * Update upload zone UI after file selection.
     * @param {HTMLInputElement} input
     * @param {string}           fieldName — used to find zone_, icon_, label_ elements
     */
    function previewFile(input, fieldName) {
        const zone  = document.getElementById('zone_'  + fieldName);
        const icon  = document.getElementById('icon_'  + fieldName);
        const label = document.getElementById('label_' + fieldName);

        if (!input.files || !input.files[0]) return;

        const file   = input.files[0];
        const sizeMB = (file.size / 1024 / 1024).toFixed(2);
        const maxLen = 24;
        const name   = file.name.length > maxLen
                     ? file.name.substring(0, maxLen) + '…'
                     : file.name;

        // Validate size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            label.textContent = '✗ File terlalu besar!';
            zone.classList.add('has-error');
            zone.classList.remove('uploaded');
            icon.textContent = 'error';
            icon.style.color = '#ba1a1a';
            // Reset input so it can be re-selected
            input.value = '';
            return;
        }

        label.textContent = `✓ ${name} (${sizeMB} MB)`;
        zone.classList.add('uploaded');
        zone.classList.remove('has-error');
        icon.textContent = 'check_circle';
        icon.style.color = '';   // let CSS class handle color
    }

    // Loading state on submit
    document.getElementById('step8Form').addEventListener('submit', function(e) {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[1.2rem] animate-spin">progress_activity</span> Mengirim...';
    });
</script>
@endpush
