{{--
    View  : recruitments/step-5.blade.php
    Step  : 5 — Riwayat Pendidikan (Education History)
    Pattern: Inline editable table — setiap baris adalah satu entry pendidikan formal
    Fields per row: Tingkat, Nama Sekolah/Institusi, Jurusan, Tahun Dari, Tahun Sampai, Lulus
--}}

@extends('layouts_recruitments.app')

@section('title', 'Registrasi — Step 5: Riwayat Pendidikan | RecruitFlow')

@push('styles')
<style>
    /* ---------- Shared rf- token classes ---------- */
    .rf-label {
        display: block; font-size: 0.75rem; line-height: 1; font-weight: 700;
        color: #434653; margin-bottom: 0.375rem; letter-spacing: 0.02em;
    }
    .rf-input, .rf-select {
        width: 100%; padding: 0.5rem 0.75rem;
        background: #ffffff; border: 1px solid #c4c6d5;
        border-radius: 0.5rem; font-size: 0.8125rem; line-height: 1.5;
        color: #1a1b22; font-family: 'Nunito Sans', sans-serif;
        transition: border-color .15s, box-shadow .15s;
    }
    .rf-input:focus, .rf-select:focus {
        outline: none; border-color: #2b54bf;
        box-shadow: 0 0 0 3px rgba(43,84,191,.12);
    }
    .rf-select { appearance: none; padding-right: 2rem; cursor: pointer; }
    .rf-icon-suffix {
        position: absolute; inset-block: 0; right: 0;
        padding-right: 0.5rem; display: flex; align-items: center;
        pointer-events: none; color: #747684;
    }
    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }

    /* ---------- Table styles ---------- */
    .edu-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
        font-family: 'Nunito Sans', sans-serif;
    }
    .edu-table thead tr {
        background: #eeedf7;   /* surface-container */
    }
    .edu-table thead th {
        padding: 0.75rem 0.875rem;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #434653;
        border-bottom: 2px solid #c4c6d5;
        white-space: nowrap;
    }
    .edu-table thead th.center { text-align: center; }

    .edu-table tbody tr {
        background: #ffffff;
        transition: background .15s;
        animation: rowIn .25s ease both;
    }
    .edu-table tbody tr:hover { background: #f3f3fc; }
    @keyframes rowIn {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .edu-table tbody td {
        padding: 0.625rem 0.75rem;
        border-bottom: 1px solid #eeedf7;
        vertical-align: middle;
    }
    .edu-table tbody td.center { text-align: center; }

    /* Row number badge */
    .row-num {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.375rem; height: 1.375rem;
        border-radius: 50%;
        background: #eeedf7; color: #434653;
        font-size: 0.65rem; font-weight: 700;
        flex-shrink: 0;
    }

    /* Checkbox */
    .rf-checkbox {
        width: 1.1rem; height: 1.1rem;
        accent-color: #2b54bf; cursor: pointer;
        border-radius: 0.25rem;
    }

    /* Delete row button */
    .row-del-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.75rem; height: 1.75rem;
        border-radius: 50%;
        color: #ba1a1a; background: transparent;
        border: none; cursor: pointer;
        transition: background .15s;
    }
    .row-del-btn:hover { background: #ffdad6; }
    .row-del-btn span.material-symbols-outlined { font-size: 1rem; }

    /* Add row button */
    .rf-add-row-btn {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.5rem 1rem; border-radius: 0.75rem;
        background: #e0e1f2; color: #444653;
        font-size: 0.75rem; font-weight: 700;
        border: none; cursor: pointer;
        transition: background .15s, color .15s;
    }
    .rf-add-row-btn:hover { background: #2b54bf; color: #fff; }
    .rf-add-row-btn span.material-symbols-outlined { font-size: 1rem; }

    /* Responsive: stack to cards on mobile */
    @media (max-width: 767px) {
        .edu-table-wrapper { overflow-x: auto; }
        .edu-table { min-width: 680px; }
    }

    /* Section animation */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .rf-section-block { animation: rfFadeUp .35s ease both; }

    /* Count badge */
    .rf-count-badge {
        font-size: 0.7rem; font-weight: 700;
        padding: 0.2rem 0.625rem; border-radius: 0.75rem;
        background: #e0e1f2; color: #444653; transition: all .2s;
    }

    /* Empty state inside table */
    .edu-empty-row td {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #747684;
    }
    .edu-empty-row.hidden { display: none; }
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
        $currentStep = 5;
        $totalSteps  = count($steps);
        $oldEdu = old('education', $savedData['education'] ?? []);
    @endphp

    @include('layouts_recruitments.partials._stepper', ['currentStep' => $currentStep, 'steps' => $steps])

    <div class="bg-surface-container-lowest rounded-xl
                shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
                border-l-4 border-primary overflow-hidden mb-8">

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-8 py-5 border-b border-outline-variant bg-surface-container-low">
            <span class="material-symbols-outlined text-primary"
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">school</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">
                    Step 5 — Riwayat Pendidikan Formal
                </h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Isi riwayat pendidikan dari yang paling awal hingga terakhir.
                </p>
            </div>
        </div>

        <form id="registrationForm"
              action="{{ route('recruitments.step.store', ['step' => 5]) }}"
              method="POST"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 rf-section-block">

                {{-- Section title row --}}
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary" style="font-size:1.1rem;">menu_book</span>
                        <h3 class="text-body-lg font-semibold text-on-surface">Pendidikan Formal</h3>
                        <span class="rf-count-badge" id="edu-count-badge">
                            <span id="edu-count">{{ count($oldEdu) ?: 0 }}</span> entri
                        </span>
                    </div>
                </div>

                {{-- Info note --}}
                <div class="flex items-start gap-3 p-3 bg-primary/5 border border-primary/15 rounded-xl mb-5">
                    <span class="material-symbols-outlined text-primary mt-0.5" style="font-size:1rem;">info</span>
                    <p class="text-label-lg text-on-surface-variant font-normal leading-relaxed">
                        Isi dari jenjang SD hingga pendidikan terakhir. Kolom <strong class="text-on-surface">Lulus</strong> centang jika sudah menyelesaikan jenjang tersebut.
                    </p>
                </div>

                {{-- Error bag --}}
                @if($errors->has('education.*'))
                    <div class="mb-5 p-4 bg-error-container rounded-xl border border-error/30">
                        <p class="text-label-lg text-on-error-container font-semibold mb-1">
                            Terdapat kesalahan pada riwayat pendidikan:
                        </p>
                        <ul class="list-disc list-inside text-label-lg text-on-error-container space-y-0.5">
                            @foreach($errors->get('education.*') as $field => $msgs)
                                @foreach($msgs as $msg)
                                    <li>{{ $msg }}</li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- ================================================
                     Editable Table
                     ================================================ --}}
                <div class="rounded-xl border border-outline-variant overflow-x-auto mb-5 edu-table-wrapper">
                    <table class="edu-table" id="edu-table">
                        <thead>
                            <tr>
                                <th style="width:2rem;">#</th>
                                <th style="min-width:110px;">Tingkat</th>
                                <th style="min-width:180px;">Nama Sekolah / Institusi</th>
                                <th style="min-width:130px;">Jurusan / Program</th>
                                <th style="width:90px;">Dari (Thn)</th>
                                <th style="width:90px;">Sampai (Thn)</th>
                                <th style="width:56px;" class="center">Lulus</th>
                                <th style="width:48px;" class="center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="edu-tbody">

                            {{-- Empty state row --}}
                            <tr class="edu-empty-row {{ count($oldEdu) ? 'hidden' : '' }}" id="edu-empty-row">
                                <td colspan="8">
                                    <span class="material-symbols-outlined" style="font-size:2rem; color:#c4c6d5; display:block; margin-bottom:.5rem;">school</span>
                                    <p class="text-label-lg font-semibold" style="color:#434653;">Belum ada data pendidikan</p>
                                    <p class="text-label-md" style="color:#747684; margin-top:.25rem;">Klik tombol di bawah untuk menambahkan baris.</p>
                                </td>
                            </tr>

                            {{-- Server-rendered rows from old() --}}
                            @foreach($oldEdu as $i => $edu)
                                <tr data-index="{{ $i }}">
                                    <td><span class="row-num">{{ $i + 1 }}</span></td>
                                    <td>
                                        <div class="relative">
                                            <select class="rf-select" name="education[{{ $i }}][tingkat]">
                                                <option value="" disabled {{ empty($edu['tingkat']) ? 'selected' : '' }}>Pilih...</option>
                                                @foreach(['SD','SMP','SMA', 'SMK', 'D1', 'D2', 'D3', 'D4', 'S1','S2','S3'] as $t)
                                                    <option value="{{ $t }}" {{ ($edu['tingkat'] ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
                                                @endforeach
                                            </select>
                                            <div class="rf-icon-suffix">
                                                <span class="material-symbols-outlined" style="font-size:.9rem;">expand_more</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input class="rf-input" type="text"
                                               name="education[{{ $i }}][institusi]"
                                               value="{{ $edu['institusi'] ?? '' }}"
                                               placeholder="Nama institusi">
                                    </td>
                                    <td>
                                        <input class="rf-input" type="text"
                                               name="education[{{ $i }}][jurusan]"
                                               value="{{ $edu['jurusan'] ?? '' }}"
                                               placeholder="Jurusan / prodi">
                                    </td>
                                    <td>
                                        <input class="rf-input" type="number"
                                               name="education[{{ $i }}][dari]"
                                               value="{{ $edu['dari'] ?? '' }}"
                                               placeholder="YYYY" min="1950" max="{{ date('Y') + 6 }}">
                                    </td>
                                    <td>
                                        <input class="rf-input" type="number"
                                               name="education[{{ $i }}][sampai]"
                                               value="{{ $edu['sampai'] ?? '' }}"
                                               placeholder="YYYY" min="1950" max="{{ date('Y') + 6 }}">
                                    </td>
                                    <td class="center">
                                        <input class="rf-checkbox" type="checkbox"
                                               name="education[{{ $i }}][lulus]"
                                               value="1"
                                               {{ !empty($edu['lulus']) ? 'checked' : '' }}>
                                    </td>
                                    <td class="center">
                                        <button type="button" class="row-del-btn" onclick="removeEduRow(this)" title="Hapus baris ini">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>

                {{-- Add row button --}}
                <button type="button" id="btn-add-edu" class="rf-add-row-btn">
                    <span class="material-symbols-outlined">add_circle</span>
                    Tambah Baris Pendidikan
                </button>

            </div>{{-- /px-8 py-8 --}}

            {{-- Nav Footer --}}
            <div class="flex items-center justify-between px-8 py-5
                        border-t border-outline-variant bg-surface-container-low">

                <a href="{{ route('recruitments.step', ['step' => 4]) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                          border border-outline text-on-surface-variant text-label-lg font-bold
                          hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Sebelumnya
                </a>

                <span class="text-label-lg text-on-surface-variant">Step 5 dari 8</span>

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

    const TINGKAT_OPTIONS = ['SD','SMP','SMA','SMK','D1','D2','D3','D4','S1','S2','S3'];
    const MAX_YEAR = {{ date('Y') + 6 }};

    let counter = document.querySelectorAll('#edu-tbody tr[data-index]').length;

    /* ================================================================
       HELPERS
       ================================================================ */
    function refreshState() {
        const rows  = document.querySelectorAll('#edu-tbody tr[data-index]');
        const empty = document.getElementById('edu-empty-row');
        const count = document.getElementById('edu-count');

        empty.classList.toggle('hidden', rows.length > 0);
        count.textContent = rows.length;

        // Re-number badges
        rows.forEach((row, idx) => {
            const badge = row.querySelector('.row-num');
            if (badge) badge.textContent = idx + 1;
        });
    }

    /* ================================================================
       BUILD ROW HTML
       ================================================================ */
    function buildRow(idx) {
        const opts = TINGKAT_OPTIONS.map(t =>
            `<option value="${t}">${t}</option>`
        ).join('');

        return `
        <tr data-index="${idx}">
            <td><span class="row-num">${idx + 1}</span></td>

            <td>
                <div class="relative">
                    <select class="rf-select" name="education[${idx}][tingkat]">
                        <option value="" disabled selected>Pilih...</option>
                        ${opts}
                    </select>
                    <div class="rf-icon-suffix">
                        <span class="material-symbols-outlined" style="font-size:.9rem;">expand_more</span>
                    </div>
                </div>
            </td>

            <td>
                <input class="rf-input" type="text"
                       name="education[${idx}][institusi]"
                       placeholder="Nama institusi">
            </td>

            <td>
                <input class="rf-input" type="text"
                       name="education[${idx}][jurusan]"
                       placeholder="Jurusan / prodi">
            </td>

            <td>
                <input class="rf-input" type="number"
                       name="education[${idx}][dari]"
                       placeholder="YYYY" min="1950" max="${MAX_YEAR}">
            </td>

            <td>
                <input class="rf-input" type="number"
                       name="education[${idx}][sampai]"
                       placeholder="YYYY" min="1950" max="${MAX_YEAR}">
            </td>

            <td class="center">
                <input class="rf-checkbox" type="checkbox"
                       name="education[${idx}][lulus]" value="1">
            </td>

            <td class="center">
                <button type="button" class="row-del-btn"
                        onclick="removeEduRow(this)" title="Hapus baris ini">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </td>
        </tr>`;
    }

    /* ================================================================
       ADD
       ================================================================ */
    document.getElementById('btn-add-edu').addEventListener('click', () => {
        const idx  = counter++;
        const temp = document.createElement('tbody');
        temp.innerHTML = buildRow(idx).trim();
        const row = temp.firstElementChild;

        document.getElementById('edu-tbody').appendChild(row);
        refreshState();

        // Focus pada select Tingkat di baris baru
        row.querySelector('select')?.focus();
    });

    /* ================================================================
       REMOVE (global — dipanggil dari onclick inline)
       ================================================================ */
    window.removeEduRow = function (btn) {
        const row = btn.closest('tr');
        row.style.transition = 'opacity .2s, transform .2s';
        row.style.opacity    = '0';
        row.style.transform  = 'translateY(-6px)';
        setTimeout(() => { row.remove(); refreshState(); }, 200);
    };

    /* ================================================================
       INIT
       ================================================================ */
    refreshState();

})();
</script>
@endpush
