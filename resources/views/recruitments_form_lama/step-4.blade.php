{{--
    View  : recruitments/step-4.blade.php
    Step  : 4 — Data Keluarga (Family Information)
    Sections:
      1. Data Orang Tua — Ayah & Ibu (fixed, 2 kolom)
      2. Data Saudara Kandung — dynamic multi-entry
      3. Data Anak — dynamic multi-entry
--}}

@extends('layouts_recruitments.app')

@section('title', 'Registrasi — Step 4: Data Keluarga | RecruitFlow')

@push('styles')
<style>
    /* ---------- Shared rf- token classes ---------- */
    .rf-label {
        display: block; font-size: 0.75rem; line-height: 1; font-weight: 700;
        color: #434653; margin-bottom: 0.375rem; letter-spacing: 0.02em;
    }
    .rf-input, .rf-select {
        width: 100%; padding: 0.575rem 0.875rem;
        background: #ffffff; border: 1px solid #c4c6d5;
        border-radius: 0.5rem; font-size: 0.875rem; line-height: 1.5;
        color: #1a1b22; font-family: 'Nunito Sans', sans-serif;
        box-shadow: 0 .1rem .5rem 0 rgba(58,59,69,.05);
        transition: border-color .15s, box-shadow .15s;
    }
    .rf-input:focus, .rf-select:focus {
        outline: none; border-color: #2b54bf;
        box-shadow: 0 0 0 3px rgba(43,84,191,.12);
    }
    .rf-select { appearance: none; padding-right: 2.25rem; cursor: pointer; }
    .rf-icon-suffix {
        position: absolute; inset-block: 0; right: 0;
        padding-right: 0.625rem; display: flex; align-items: center;
        pointer-events: none; color: #747684;
    }
    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }

    /* ---------- Section title ---------- */
    .rf-section-title {
        display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem;
    }
    .rf-section-title span.material-symbols-outlined { font-size: 1.1rem; color: #2b54bf; }
    .rf-section-title h3 { font-size: 1rem; font-weight: 600; color: #1a1b22; }
    .rf-section-title .divider { flex: 1; height: 1px; background: #c4c6d5; margin-left: 0.5rem; }

    /* ---------- Parent sub-column ---------- */
    .parent-col-title {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 1.15rem; font-weight: 700; color: #5b5e6c;
        padding-bottom: 0.625rem;
        border-bottom: 1px solid #e2e1eb;
        margin-bottom: 1rem;
    }
    .parent-col-title span.material-symbols-outlined { font-size: 1.25rem; }

    /* ---------- Dynamic entry card ---------- */
    .family-card {
        background: #f3f3fc;
        border: 1px solid #c4c6d5;
        border-left: 3px solid #2b54bf;
        border-radius: 0.75rem;
        padding: 1.25rem;
        animation: cardIn .25s ease both;
    }
    .family-card.anak { border-left-color: #8c4b00; }

    @keyframes cardIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .family-card-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 1rem; padding-bottom: 0.625rem;
        border-bottom: 1px solid rgba(196,198,213,.5);
    }
    .family-card-label {
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; color: #2b54bf;
    }
    .family-card.anak .family-card-label { color: #8c4b00; }

    .rf-delete-btn {
        display: inline-flex; align-items: center; gap: 0.25rem;
        padding: 0.25rem 0.625rem; border-radius: 0.75rem;
        font-size: 0.7rem; font-weight: 700; color: #ba1a1a;
        background: transparent; border: 1px solid transparent;
        transition: all .15s; cursor: pointer;
    }
    .rf-delete-btn:hover { background: #ffdad6; border-color: #ba1a1a; }
    .rf-delete-btn span.material-symbols-outlined { font-size: 0.95rem; }

    /* ---------- Add button ---------- */
    .rf-add-btn {
        width: 100%; padding: 0.875rem;
        border: 2px dashed #c4c6d5; border-radius: 0.75rem;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em;
        text-transform: uppercase; color: #2b54bf;
        background: transparent; cursor: pointer; transition: all .2s;
    }
    .rf-add-btn:hover { border-color: #2b54bf; background: rgba(43,84,191,.04); }
    .rf-add-btn.anak { color: #8c4b00; }
    .rf-add-btn.anak:hover { border-color: #8c4b00; background: rgba(140,75,0,.04); }
    .rf-add-btn span.material-symbols-outlined { font-size: 1.15rem; }

    /* ---------- Empty state ---------- */
    .rf-empty {
        text-align: center; padding: 2rem 1rem;
        color: #747684; display: none;
    }
    .rf-empty.visible { display: block; }
    .rf-empty span.material-symbols-outlined {
        font-size: 2rem; color: #c4c6d5; display: block; margin-bottom: 0.5rem;
    }

    /* ---------- Section block animation ---------- */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .rf-section-block { animation: rfFadeUp .35s ease both; }
    .d1 { animation-delay:.04s; } .d2 { animation-delay:.11s; } .d3 { animation-delay:.18s; }

    /* ---------- Badge counter ---------- */
    .rf-count-badge {
        font-size: 0.7rem; font-weight: 700;
        padding: 0.2rem 0.625rem; border-radius: 0.75rem;
        background: #e0e1f2; color: #444653;
        transition: all .2s;
    }
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
        $currentStep = 4;
        $totalSteps  = count($steps);
    @endphp

    @include('layouts_recruitments.partials._stepper', ['currentStep' => $currentStep, 'steps' => $steps])

    <div class="bg-surface-container-lowest rounded-xl
                shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
                border-l-4 border-primary overflow-hidden mb-8">

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-8 py-5 border-b border-outline-variant bg-surface-container-low">
            <span class="material-symbols-outlined text-primary"
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">family_restroom</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">Step 4 — Data Keluarga</h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Isi data orang tua, saudara kandung, dan anak (jika ada).
                </p>
            </div>
        </div>

        <form id="registrationForm"
              action="{{ route('recruitments.step.store', ['step' => 4]) }}"
              method="POST"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 space-y-10">

                {{-- ================================================
                     S1 · Data Orang Tua
                     ================================================ --}}
                <div class="rf-section-block d1">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">supervisor_account</span>
                        <h3>Data Orang Tua</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                        {{-- Ayah --}}
                        <div class="space-y-4">
                            <div class="parent-col-title">
                                <span class="material-symbols-outlined">man</span> Ayah
                            </div>

                            <div>
                                <label class="rf-label" for="ayah_nama">Nama Lengkap</label>
                                <input class="rf-input" id="ayah_nama" name="ayah[nama]"
                                       value="{{ old('ayah.nama', $savedData['ayah']['nama'] ?? '') }}" type="text" placeholder="Nama sesuai KTP">
                            </div>
                            <div>
                                <label class="rf-label" for="ayah_tgl_lahir">Tanggal Lahir</label>
                                <input class="rf-input" id="ayah_tgl_lahir" name="ayah[tgl_lahir]"
                                       value="{{ old('ayah.tgl_lahir', $savedData['ayah']['tgl_lahir'] ?? '') }}" type="date">
                            </div>
                            <div>
                                <label class="rf-label" for="ayah_pendidikan">Pendidikan Terakhir</label>
                                <div class="relative">
                                    <select class="rf-select" id="ayah_pendidikan" name="ayah[pendidikan]">
                                        <option value="" disabled {{ old('ayah.pendidikan', $savedData['ayah']['pendidikan'] ?? '') === '' ? 'selected' : '' }}>Pilih Pendidikan</option>
                                        @foreach(['SD','SMP','SMA/SMK','D3','S1','S2','S3'] as $p)
                                            <option value="{{ $p }}" {{ old('ayah.pendidikan', $savedData['ayah']['pendidikan'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                                        @endforeach
                                    </select>
                                    <div class="rf-icon-suffix">
                                        <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="rf-label" for="ayah_pekerjaan">Pekerjaan</label>
                                <input class="rf-input" id="ayah_pekerjaan" name="ayah[pekerjaan]"
                                       value="{{ old('ayah.pekerjaan', $savedData['ayah']['pekerjaan'] ?? '') }}" type="text" placeholder="Pekerjaan saat ini">
                            </div>
                        </div>

                        {{-- Ibu --}}
                        <div class="space-y-4">
                            <div class="parent-col-title">
                                <span class="material-symbols-outlined">woman</span> Ibu
                            </div>

                            <div>
                                <label class="rf-label" for="ibu_nama">Nama Lengkap</label>
                                <input class="rf-input" id="ibu_nama" name="ibu[nama]"
                                       value="{{ old('ibu.nama', $savedData['ibu']['nama'] ?? '') }}" type="text" placeholder="Nama sesuai KTP">
                            </div>
                            <div>
                                <label class="rf-label" for="ibu_tgl_lahir">Tanggal Lahir</label>
                                <input class="rf-input" id="ibu_tgl_lahir" name="ibu[tgl_lahir]"
                                       value="{{ old('ibu.tgl_lahir', $savedData['ibu']['tgl_lahir'] ?? '') }}" type="date">
                            </div>
                            <div>
                                <label class="rf-label" for="ibu_pendidikan">Pendidikan Terakhir</label>
                                <div class="relative">
                                    <select class="rf-select" id="ibu_pendidikan" name="ibu[pendidikan]">
                                        <option value="" disabled {{ old('ibu.pendidikan', $savedData['ibu']['pendidikan'] ?? '') === '' ? 'selected' : '' }}>Pilih Pendidikan</option>
                                        @foreach(['SD','SMP','SMA/SMK','D3','S1','S2','S3'] as $p)
                                            <option value="{{ $p }}" {{ old('ibu.pendidikan', $savedData['ibu']['pendidikan'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                                        @endforeach
                                    </select>
                                    <div class="rf-icon-suffix">
                                        <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="rf-label" for="ibu_pekerjaan">Pekerjaan</label>
                                <input class="rf-input" id="ibu_pekerjaan" name="ibu[pekerjaan]"
                                       value="{{ old('ibu.pekerjaan', $savedData['ibu']['pekerjaan'] ?? '') }}" type="text" placeholder="Pekerjaan saat ini">
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ================================================
                     S2 · Data Saudara Kandung (dynamic)
                     ================================================ --}}
                <div class="rf-section-block d2">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">diversity_1</span>
                        <h3>Data Saudara Kandung</h3>
                        <span class="rf-count-badge" id="saudara-count-badge">
                            <span id="saudara-count">0</span> entri
                        </span>
                        <div class="divider"></div>
                    </div>

                    <div class="space-y-4">

                        {{-- Info note --}}
                        <div class="flex items-start gap-3 p-3 bg-primary/5 border border-primary/15 rounded-xl mb-2">
                            <span class="material-symbols-outlined text-primary mt-0.5" style="font-size:1rem;">info</span>
                            <p class="text-label-lg text-on-surface-variant font-normal leading-relaxed">
                                Opsional — lewati bagian ini jika tidak memiliki saudara kandung.
                            </p>
                        </div>

                        {{-- Entry list --}}
                        <div id="saudara-list" class="space-y-4">

                            <div class="rf-empty visible" id="saudara-empty">
                                <span class="material-symbols-outlined">group_off</span>
                                <p class="text-label-lg font-semibold">Belum ada data saudara</p>
                                <p class="text-label-md mt-0.5">Klik tombol di bawah untuk menambahkan.</p>
                            </div>

                            @if(is_array(old('saudara')) && count(old('saudara')))
                                @foreach(old('saudara') as $i => $s)
                                    <div class="family-card" data-index="{{ $i }}">
                                        @include('recruitments_form.partials._saudara-fields', ['i' => $i, 's' => $s])
                                    </div>
                                @endforeach
                            @elseif(!empty($savedData['saudara']) && is_array($savedData['saudara']))
                                @foreach($savedData['saudara'] as $i => $s)
                                    <div class="family-card" data-index="{{ $i }}">
                                        @include('recruitments_form.partials._saudara-fields', ['i' => $i, 's' => $s])
                                    </div>
                                @endforeach
                            @endif

                        </div>

                        <button type="button" id="btn-add-saudara" class="rf-add-btn">
                            <span class="material-symbols-outlined">person_add</span>
                            Tambah Saudara Kandung
                        </button>
                    </div>
                </div>

                {{-- ================================================
                     S3 · Data Anak (dynamic)
                     ================================================ --}}
                <div class="rf-section-block d3">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined" style="color:#8c4b00;">child_care</span>
                        <h3>Data Anak</h3>
                        <span class="rf-count-badge" id="anak-count-badge"
                              style="background:#ffdcc2; color:#6d3900;">
                            <span id="anak-count">0</span> entri
                        </span>
                        <div class="divider"></div>
                    </div>

                    <div class="space-y-4">

                        <div class="flex items-start gap-3 p-3 bg-primary/5 border border-primary/15 rounded-xl mb-2">
                            <span class="material-symbols-outlined text-primary mt-0.5" style="font-size:1rem;">info</span>
                            <p class="text-label-lg text-on-surface-variant font-normal leading-relaxed">
                                Opsional — lewati bagian ini jika belum memiliki anak.
                            </p>
                        </div>

                        <div id="anak-list" class="space-y-4">

                            <div class="rf-empty visible" id="anak-empty">
                                <span class="material-symbols-outlined">child_friendly</span>
                                <p class="text-label-lg font-semibold">Belum ada data anak</p>
                                <p class="text-label-md mt-0.5">Klik tombol di bawah untuk menambahkan.</p>
                            </div>

                            @if(is_array(old('anak')) && count(old('anak')))
                                @foreach(old('anak') as $i => $a)
                                    <div class="family-card anak" data-index="{{ $i }}">
                                        @include('recruitments_form.partials._anak-fields', ['i' => $i, 'a' => $a])
                                    </div>
                                @endforeach
                            @elseif(!empty($savedData['anak']) && is_array($savedData['anak']))
                                @foreach($savedData['anak'] as $i => $a)
                                    <div class="family-card anak" data-index="{{ $i }}">
                                        @include('recruitments_form.partials._anak-fields', ['i' => $i, 'a' => $a])
                                    </div>
                                @endforeach
                            @endif

                        </div>

                        <button type="button" id="btn-add-anak" class="rf-add-btn anak">
                            <span class="material-symbols-outlined">add_circle</span>
                            Tambah Data Anak
                        </button>
                    </div>
                </div>

            </div>{{-- /px-8 py-8 --}}

            {{-- Nav Footer --}}
            <div class="flex items-center justify-between px-8 py-5
                        border-t border-outline-variant bg-surface-container-low">

                <a href="{{ route('recruitments.step', ['step' => 3]) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                          border border-outline text-on-surface-variant text-label-lg font-bold
                          hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Sebelumnya
                </a>

                <span class="text-label-lg text-on-surface-variant">Step 4 dari 8</span>

                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                               bg-primary text-white text-label-lg font-bold shadow-sm
                               hover:bg-primary/90 active:scale-95 transition-all duration-150">
                    Selanjutnya
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_forward</span>
                </button>

            </div>

        </form>
    </div>

@endsection

@push('scripts')
<script>
(function () {

    /* ================================================================
       TEMPLATES
       ================================================================ */
    const SAUDARA_TPL = `
    <div class="family-card" data-index="__IDX__">
        <div class="family-card-header">
            <span class="family-card-label">Saudara ke-__NUM__</span>
            <button type="button" class="rf-delete-btn" onclick="removeCard(this,'saudara')">
                <span class="material-symbols-outlined">delete</span> Hapus
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="rf-label" for="saudara___IDX___nama">Nama Lengkap</label>
                <input class="rf-input" id="saudara___IDX___nama"
                       name="saudara[__IDX__][nama]" type="text" placeholder="Nama sesuai KTP">
            </div>
            <div>
                <label class="rf-label" for="saudara___IDX___tgl_lahir">Tanggal Lahir</label>
                <input class="rf-input" id="saudara___IDX___tgl_lahir"
                       name="saudara[__IDX__][tgl_lahir]" type="date">
            </div>
            <div>
                <label class="rf-label" for="saudara___IDX___gender">Gender</label>
                <div class="relative">
                    <select class="rf-select" id="saudara___IDX___gender" name="saudara[__IDX__][gender]">
                        <option value="" disabled selected>Pilih Gender</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                    <div class="rf-icon-suffix">
                        <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                    </div>
                </div>
            </div>
            <div>
                <label class="rf-label" for="saudara___IDX___pendidikan">Pendidikan</label>
                <div class="relative">
                    <select class="rf-select" id="saudara___IDX___pendidikan" name="saudara[__IDX__][pendidikan]">
                        <option value="" disabled selected>Pilih Pendidikan</option>
                        <option value="SD">SD</option>
                        <option value="SMP">SMP</option>
                        <option value="SMA/SMK">SMA/SMK</option>
                        <option value="D3">D3</option>
                        <option value="S1">S1</option>
                        <option value="S2">S2</option>
                        <option value="S3">S3</option>
                    </select>
                    <div class="rf-icon-suffix">
                        <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                    </div>
                </div>
            </div>
            <div>
                <label class="rf-label" for="saudara___IDX___pekerjaan">Pekerjaan</label>
                <input class="rf-input" id="saudara___IDX___pekerjaan"
                       name="saudara[__IDX__][pekerjaan]" type="text" placeholder="Pekerjaan saat ini">
            </div>
        </div>
    </div>`;

    const ANAK_TPL = `
    <div class="family-card anak" data-index="__IDX__">
        <div class="family-card-header">
            <span class="family-card-label">Anak ke-__NUM__</span>
            <button type="button" class="rf-delete-btn" onclick="removeCard(this,'anak')">
                <span class="material-symbols-outlined">delete</span> Hapus
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="rf-label" for="anak___IDX___nama">Nama Lengkap</label>
                <input class="rf-input" id="anak___IDX___nama"
                       name="anak[__IDX__][nama]" type="text" placeholder="Nama sesuai akta kelahiran">
            </div>
            <div>
                <label class="rf-label" for="anak___IDX___tempat_lahir">Tempat Lahir</label>
                <input class="rf-input" id="anak___IDX___tempat_lahir"
                       name="anak[__IDX__][tempat_lahir]" type="text" placeholder="Contoh: Jakarta">
            </div>
            <div>
                <label class="rf-label" for="anak___IDX___tgl_lahir">Tanggal Lahir</label>
                <input class="rf-input" id="anak___IDX___tgl_lahir"
                       name="anak[__IDX__][tgl_lahir]" type="date">
            </div>
            <div>
                <label class="rf-label" for="anak___IDX___gender">Gender</label>
                <div class="relative">
                    <select class="rf-select" id="anak___IDX___gender" name="anak[__IDX__][gender]">
                        <option value="" disabled selected>Pilih Gender</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                    <div class="rf-icon-suffix">
                        <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                    </div>
                </div>
            </div>
            <div>
                <label class="rf-label" for="anak___IDX___pendidikan">Pendidikan / Sekolah</label>
                <input class="rf-input" id="anak___IDX___pendidikan"
                       name="anak[__IDX__][pendidikan]" type="text" placeholder="Nama instansi pendidikan">
            </div>
            <div class="md:col-span-2">
                <label class="rf-label" for="anak___IDX___status">Status / Pekerjaan</label>
                <input class="rf-input" id="anak___IDX___status"
                       name="anak[__IDX__][status]" type="text" placeholder="Contoh: Pelajar / Mahasiswa / Belum Bekerja">
            </div>
        </div>
    </div>`;

    /* ================================================================
       STATE
       ================================================================ */
    let saudaraCounter = document.querySelectorAll('#saudara-list .family-card').length;
    let anakCounter    = document.querySelectorAll('#anak-list .family-card').length;

    /* ================================================================
       HELPERS
       ================================================================ */
    function refreshEmpty(listId, emptyId, countId, badgeId) {
        const cards = document.querySelectorAll(`#${listId} .family-card`);
        const n     = cards.length;
        document.getElementById(emptyId).classList.toggle('visible', n === 0);
        document.getElementById(countId).textContent = n;
    }

    function reindexLabels(listId, labelPrefix) {
        document.querySelectorAll(`#${listId} .family-card`).forEach((card, idx) => {
            const lbl = card.querySelector('.family-card-label');
            if (lbl) lbl.textContent = `${labelPrefix} ke-${idx + 1}`;
        });
    }

    function injectCard(listId, template, idx, num) {
        const html = template.replaceAll('__IDX__', idx).replaceAll('__NUM__', num);
        const temp = document.createElement('div');
        temp.innerHTML = html.trim();
        const card = temp.firstElementChild;
        document.getElementById(listId).appendChild(card);
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /* ================================================================
       ADD
       ================================================================ */
    document.getElementById('btn-add-saudara').addEventListener('click', () => {
        const n = document.querySelectorAll('#saudara-list .family-card').length + 1;
        injectCard('saudara-list', SAUDARA_TPL, saudaraCounter++, n);
        refreshEmpty('saudara-list', 'saudara-empty', 'saudara-count', 'saudara-count-badge');
    });

    document.getElementById('btn-add-anak').addEventListener('click', () => {
        const n = document.querySelectorAll('#anak-list .family-card').length + 1;
        injectCard('anak-list', ANAK_TPL, anakCounter++, n);
        refreshEmpty('anak-list', 'anak-empty', 'anak-count', 'anak-count-badge');
    });

    /* ================================================================
       REMOVE (global — dipanggil dari onclick di template)
       ================================================================ */
    window.removeCard = function (btn, type) {
        const card = btn.closest('.family-card');
        card.style.transition = 'opacity .2s, transform .2s';
        card.style.opacity    = '0';
        card.style.transform  = 'translateY(-8px)';
        setTimeout(() => {
            card.remove();
            if (type === 'saudara') {
                reindexLabels('saudara-list', 'Saudara');
                refreshEmpty('saudara-list', 'saudara-empty', 'saudara-count', 'saudara-count-badge');
            } else {
                reindexLabels('anak-list', 'Anak');
                refreshEmpty('anak-list', 'anak-empty', 'anak-count', 'anak-count-badge');
            }
        }, 200);
    };

    /* ================================================================
       INIT — hitung dari old() server-rendered cards
       ================================================================ */
    refreshEmpty('saudara-list', 'saudara-empty', 'saudara-count', 'saudara-count-badge');
    refreshEmpty('anak-list',    'anak-empty',    'anak-count',    'anak-count-badge');

})();
</script>
@endpush
