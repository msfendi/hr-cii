{{--
    View  : recruitments/step-3.blade.php
    Step  : 3 — Pengalaman Kerja (Work Experience)
    Pattern: Dynamic multi-entry — user bisa tambah/hapus entri pengalaman kerja
    Fields per entry: Nama Perusahaan, Masa Kerja Dari/Sampai,
                      Jabatan, Bagian/Departemen, Alasan Keluar
--}}

@extends('layouts_recruitments.app')

{{-- ============================================================
     META
     ============================================================ --}}
@section('title', 'Registrasi — Step 3: Pengalaman Kerja | RecruitFlow')

@push('styles')
<style>
    /* ---------- Shared rf- token classes ---------- */
    .rf-label {
        display: block;
        font-size: 0.75rem; line-height: 1; font-weight: 700;
        color: #1a1b22; margin-bottom: 0.5rem; letter-spacing: 0.02em;
    }
    .rf-input,
    .rf-select,
    .rf-textarea {
        width: 100%; padding: 0.625rem 1rem;
        background: #ffffff; border: 1px solid #c4c6d5;
        border-radius: 0.5rem; font-size: 0.875rem; line-height: 1.5;
        color: #1a1b22; font-family: 'Nunito Sans', sans-serif;
        box-shadow: 0 .1rem .5rem 0 rgba(58,59,69,.06);
        transition: border-color .15s, box-shadow .15s;
    }
    .rf-textarea { resize: vertical; min-height: 80px; }
    .rf-input:focus, .rf-select:focus, .rf-textarea:focus {
        outline: none; border-color: #2b54bf;
        box-shadow: 0 0 0 3px rgba(43,84,191,.12);
    }
    .rf-select { appearance: none; padding-right: 2.5rem; cursor: pointer; }
    .rf-icon-suffix {
        position: absolute; inset-block: 0; right: 0;
        padding-right: 0.75rem; display: flex; align-items: center;
        pointer-events: none; color: #747684;
    }
    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }

    /* ---------- Experience entry card ---------- */
    .exp-card {
        background: #ffffff;
        border: 1px solid #c4c6d5;
        border-left: 3px solid #2b54bf;
        border-radius: 0.75rem;
        padding: 1.5rem;
        position: relative;
        animation: expCardIn .3s ease both;
    }
    @keyframes expCardIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    /* Card header */
    .exp-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #eeedf7;
    }
    .exp-card-index {
        display: inline-flex; align-items: center; gap: 0.5rem;
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; color: #2b54bf;
    }
    .exp-card-index .badge {
        width: 1.5rem; height: 1.5rem; border-radius: 50%;
        background: rgba(43,84,191,.1); display: flex;
        align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 700; color: #2b54bf;
    }
    /* Delete button */
    .exp-delete-btn {
        display: inline-flex; align-items: center; gap: 0.375rem;
        padding: 0.375rem 0.75rem; border-radius: 0.75rem;
        font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em;
        color: #ba1a1a; background: transparent;
        border: 1px solid transparent;
        transition: all .15s; cursor: pointer;
    }
    .exp-delete-btn:hover {
        background: #ffdad6; border-color: #ba1a1a;
    }
    .exp-delete-btn span.material-symbols-outlined { font-size: 1rem; }

    /* Add button */
    .rf-add-btn {
        width: 100%;
        padding: 1rem;
        border: 2px dashed #c4c6d5;
        border-radius: 0.75rem;
        display: flex; align-items: center; justify-content: center;
        gap: 0.625rem;
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em;
        text-transform: uppercase; color: #2b54bf;
        background: transparent; cursor: pointer;
        transition: all .2s;
    }
    .rf-add-btn:hover {
        border-color: #2b54bf;
        background: rgba(43,84,191,.04);
    }
    .rf-add-btn span.material-symbols-outlined { font-size: 1.25rem; }

    /* Empty state */
    .rf-empty-state {
        text-align: center; padding: 3rem 1.5rem;
        border: 2px dashed #c4c6d5; border-radius: 0.75rem;
        color: #434653; display: none;
    }
    .rf-empty-state.visible { display: block; }
    .rf-empty-state span.material-symbols-outlined {
        font-size: 2.5rem; color: #c4c6d5; display: block; margin-bottom: 0.75rem;
    }

    /* Fade-in for page sections */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .rf-section-block { animation: rfFadeUp .35s ease both; }
    .d1 { animation-delay:.04s; } .d2 { animation-delay:.12s; }

    /* "Masih bekerja" checkbox row */
    .rf-still-working {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.75rem; font-weight: 700; color: #434653;
        cursor: pointer; user-select: none;
    }
    .rf-still-working input { accent-color: #2b54bf; }
</style>
@endpush

{{-- ============================================================
     BREADCRUMB
     ============================================================ --}}
@section('breadcrumb')
    <span class="hover:text-primary cursor-pointer transition-colors">Dashboard</span>
    <span class="mx-2 text-outline">/</span>
    <span class="text-on-surface font-bold">Registrasi</span>
@endsection

@section('page_title', 'Form Pendaftaran')

{{-- ============================================================
     CONTENT
     ============================================================ --}}
@section('content')

    @php
        $steps = [
            'Data Pribadi', 'Kontak & Alamat', 'Pengalaman Kerja', 'Data Keluarga',
            'Riwayat Pendidikan', 'Motivasi & Kegiatan', 'Data Fisik',
            'Upload Dokumen',
        ];
        $currentStep = 3;
        $totalSteps  = count($steps);

        // Prioritas: old() saat validasi gagal → savedData session saat back navigation
        // Gunakan $oldExperiences untuk render agar data session tidak diabaikan
        $oldExperiences = old('experiences') ?? ($savedData['experiences'] ?? null);
        $hasExpData = is_array($oldExperiences)
            && count($oldExperiences) > 0
            && !empty(array_filter($oldExperiences, fn($e) => is_array($e) && !empty(array_filter($e))));
    @endphp

    {{-- Stepper --}}
    @include('layouts_recruitments.partials._stepper', [
        'currentStep' => $currentStep,
        'steps'       => $steps,
    ])

    {{-- ======================================================
         WIZARD CARD
         ====================================================== --}}
    <div class="bg-surface-container-lowest rounded-xl
                shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
                border-l-4 border-primary overflow-hidden mb-8">

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-8 py-5
                    border-b border-outline-variant bg-surface-container-low">
            <span class="material-symbols-outlined text-primary"
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">work_history</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">
                    Step 3 — Pengalaman Kerja
                </h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Tambahkan riwayat pekerjaan, dimulai dari yang paling terbaru.
                </p>
            </div>
        </div>

        {{-- ====== FORM ====== --}}
        <form id="registrationForm"
              action="{{ route('recruitments.step.store', ['step' => 3]) }}"
              method="POST"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 space-y-5 rf-section-block d1">

                {{-- Info note --}}
                <div class="flex items-start gap-3 p-4 bg-primary/5 border border-primary/15 rounded-xl">
                    <span class="material-symbols-outlined text-primary mt-0.5"
                          style="font-size:1.1rem;">info</span>
                    <p class="text-label-lg text-on-surface-variant font-normal leading-relaxed">
                        Jika tidak memiliki pengalaman kerja, klik <strong class="text-on-surface">Selanjutnya</strong>
                        langsung tanpa mengisi form di bawah.
                        Urutan disarankan dari pengalaman paling baru.
                    </p>
                </div>

                {{-- ================================================
                     Dynamic entry list
                     ================================================ --}}
                <div id="exp-list" class="space-y-5">

                    {{-- Empty state (shown when semua card dihapus) --}}
                    <div class="rf-empty-state" id="exp-empty">
                        <span class="material-symbols-outlined">work_off</span>
                        <p class="text-body-md font-semibold text-on-surface">Belum ada pengalaman kerja</p>
                        <p class="text-label-lg text-on-surface-variant mt-1">
                            Klik tombol di bawah untuk menambahkan.
                        </p>
                    </div>

                    {{-- Pre-filled dari old() (validasi gagal) ATAU savedData session (back navigation) --}}
                    @if($hasExpData)
                        @foreach($oldExperiences as $i => $exp)
                            <div class="exp-card" data-index="{{ $i }}">
                                @include('recruitments_form.partials._exp-fields', ['i' => $i, 'exp' => $exp])
                            </div>
                        @endforeach
                    @else
                        {{-- Default: satu entry kosong --}}
                        <div class="exp-card" data-index="0">
                            @include('recruitments_form.partials._exp-fields', ['i' => 0, 'exp' => []])
                        </div>
                    @endif

                </div>

                {{-- Add button --}}
                <button type="button" id="btn-add-exp" class="rf-add-btn">
                    <span class="material-symbols-outlined">add_circle</span>
                    Tambah Pengalaman Kerja
                </button>

            </div>{{-- /px-8 py-8 --}}

            {{-- ====================================================
                 NAV FOOTER
                 ==================================================== --}}
            <div class="flex items-center justify-between px-8 py-5
                        border-t border-outline-variant bg-surface-container-low">

                {{-- Prev --}}
                <a href="{{ route('recruitments.step', ['step' => 2]) }}"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                               border border-outline text-on-surface-variant text-label-lg font-bold
                               hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Sebelumnya
                </a>

                {{-- Step counter --}}
                <span class="text-label-lg text-on-surface-variant">Step 3 dari 8</span>

                {{-- Next --}}
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

{{-- ============================================================
     SCRIPTS
     ============================================================ --}}
@push('scripts')
<script>
(function () {
    /* -------------------------------------------------------
       Template HTML untuk satu entry card baru
       Menggunakan index __IDX__ yang akan di-replace saat inject
       ------------------------------------------------------- */
    const TEMPLATE = `
    <div class="exp-card" data-index="__IDX__">
        <!-- Header -->
        <div class="exp-card-header">
            <div class="exp-card-index">
                <div class="badge">__NUM__</div>
                Pengalaman Kerja ke-__NUM__
            </div>
            <button type="button" class="exp-delete-btn"
                    onclick="removeExp(this)" title="Hapus entri ini">
                <span class="material-symbols-outlined">delete</span>
                Hapus
            </button>
        </div>

        <!-- Fields grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Nama Perusahaan -->
            <div class="md:col-span-2">
                <label class="rf-label" for="exp___IDX___perusahaan">
                    Nama Perusahaan <span style="color:#ba1a1a">*</span>
                </label>
                <input class="rf-input"
                       id="exp___IDX___perusahaan"
                       name="experiences[__IDX__][perusahaan]"
                       type="text" placeholder="Nama perusahaan">
            </div>

            <!-- Masa Kerja Dari -->
            <div>
                <label class="rf-label" for="exp___IDX___dari">Masa Kerja (Dari)</label>
                <input class="rf-input"
                       id="exp___IDX___dari"
                       name="experiences[__IDX__][dari]"
                       type="month">
            </div>

            <!-- Masa Kerja Sampai -->
            <div>
                <label class="rf-label" for="exp___IDX___sampai">Masa Kerja (Sampai)</label>
                <input class="rf-input" id="exp___IDX___sampai"
                       name="experiences[__IDX__][sampai]"
                       type="month">
                <!-- Masih bekerja di sini -->
                <label class="rf-still-working mt-2">
                    <input type="checkbox"
                           name="experiences[__IDX__][masih_bekerja]"
                           value="1"
                           onchange="toggleSampai(this, '__IDX__')">
                    Masih bekerja di sini
                </label>
            </div>

            <!-- Jabatan -->
            <div>
                <label class="rf-label" for="exp___IDX___jabatan">Jabatan</label>
                <input class="rf-input"
                       id="exp___IDX___jabatan"
                       name="experiences[__IDX__][jabatan]"
                       type="text" placeholder="Jabatan / posisi">
            </div>

            <!-- Bagian / Departemen -->
            <div>
                <label class="rf-label" for="exp___IDX___departemen">Bagian / Departemen</label>
                <input class="rf-input"
                       id="exp___IDX___departemen"
                       name="experiences[__IDX__][departemen]"
                       type="text" placeholder="Departemen">
            </div>

            <!-- Alasan Keluar -->
            <div class="md:col-span-2">
                <label class="rf-label" for="exp___IDX___alasan">Alasan Keluar</label>
                <textarea class="rf-textarea"
                          id="exp___IDX___alasan"
                          name="experiences[__IDX__][alasan]"
                          rows="3"
                          placeholder="Tuliskan alasan keluar dari perusahaan ini..."></textarea>
            </div>

        </div>
    </div>`;

    /* -------------------------------------------------------
       State
       ------------------------------------------------------- */
    let counter = document.querySelectorAll('.exp-card').length;   // init dari server-rendered cards
    const list  = document.getElementById('exp-list');
    const empty = document.getElementById('exp-empty');

    /* -------------------------------------------------------
       Helpers
       ------------------------------------------------------- */
    function refreshEmpty() {
        const cards = list.querySelectorAll('.exp-card');
        empty.classList.toggle('visible', cards.length === 0);
    }

    function reindexHeaders() {
        const cards = list.querySelectorAll('.exp-card');
        cards.forEach((card, idx) => {
            const badge = card.querySelector('.exp-card-index .badge');
            const label = card.querySelector('.exp-card-index');
            if (badge) badge.textContent = idx + 1;
            if (label) label.lastChild.textContent = ` Pengalaman Kerja ke-${idx + 1}`;
        });
    }

    /* -------------------------------------------------------
       Add
       ------------------------------------------------------- */
    document.getElementById('btn-add-exp').addEventListener('click', function () {
        const idx  = counter++;
        const num  = list.querySelectorAll('.exp-card').length + 1;
        const html = TEMPLATE
            .replaceAll('__IDX__', idx)
            .replaceAll('__NUM__', num);

        const temp = document.createElement('div');
        temp.innerHTML = html.trim();
        const card = temp.firstElementChild;
        list.appendChild(card);

        // Scroll ke card baru
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        refreshEmpty();
    });

    /* -------------------------------------------------------
       Remove (global function dipanggil dari onclick)
       ------------------------------------------------------- */
    window.removeExp = function (btn) {
        const card = btn.closest('.exp-card');
        // Animasi keluar
        card.style.transition = 'opacity .2s, transform .2s';
        card.style.opacity    = '0';
        card.style.transform  = 'translateY(-8px)';
        setTimeout(() => {
            card.remove();
            reindexHeaders();
            refreshEmpty();
        }, 200);
    };

    /* -------------------------------------------------------
       Toggle "sampai" field saat masih bekerja
       ------------------------------------------------------- */
    window.toggleSampai = function (checkbox, idx) {
        const sampaiEl = document.getElementById('exp_' + idx + '_sampai');
        if (!sampaiEl) return;
        if (checkbox.checked) {
            sampaiEl.value    = '';
            sampaiEl.disabled = true;
            sampaiEl.style.opacity = '.4';
            sampaiEl.style.cursor  = 'not-allowed';
        } else {
            sampaiEl.disabled = false;
            sampaiEl.style.opacity = '';
            sampaiEl.style.cursor  = '';
        }
    };

    /* -------------------------------------------------------
       Init
       ------------------------------------------------------- */
    // Attach delete handler ke server-rendered cards (jika ada dari old())
    document.querySelectorAll('.exp-card .exp-delete-btn').forEach(btn => {
        btn.addEventListener('click', function () { window.removeExp(this); });
    });

    refreshEmpty();
})();
</script>
@endpush
