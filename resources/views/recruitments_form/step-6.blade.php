{{--
    View  : recruitments/step-6.blade.php
    Step  : 6 — Motivasi & Kegiatan Ekstra
    Fields: Motivasi Melamar (textarea), Kegiatan Extra yang Disukai (textarea)
--}}

@extends('layouts_recruitments.app')

@section('title', 'Registrasi — Step 6: Motivasi & Kegiatan | RecruitFlow')

@push('styles')
<style>
    /* ---------- Shared rf- token classes ---------- */
    .rf-label {
        display: block; font-size: 0.75rem; line-height: 1; font-weight: 700;
        color: #1a1b22; margin-bottom: 0.5rem; letter-spacing: 0.02em;
    }
    .rf-textarea {
        width: 100%; padding: 0.75rem 1rem;
        background: #ffffff; border: 1px solid #c4c6d5;
        border-radius: 0.5rem; font-size: 0.875rem; line-height: 1.65;
        color: #1a1b22; font-family: 'Nunito Sans', sans-serif;
        box-shadow: 0 .1rem .5rem 0 rgba(58,59,69,.06);
        transition: border-color .15s, box-shadow .15s;
        resize: vertical; min-height: 140px;
    }
    .rf-textarea:focus {
        outline: none; border-color: #2b54bf;
        box-shadow: 0 0 0 3px rgba(43,84,191,.12);
    }
    .rf-textarea.error { border-color: #ba1a1a; }
    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }

    /* Character counter */
    .char-counter {
        font-size: 0.68rem; font-weight: 600; color: #747684;
        text-align: right; margin-top: 0.25rem;
        transition: color .15s;
    }
    .char-counter.warn  { color: #8c4b00; }
    .char-counter.over  { color: #ba1a1a; font-weight: 700; }

    /* Section block animation */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .rf-section-block { animation: rfFadeUp .35s ease both; }
    .d1 { animation-delay:.04s; }
    .d2 { animation-delay:.12s; }

    /* Tip card */
    .rf-tip-card {
        display: flex; gap: 0.75rem; align-items: flex-start;
        padding: 0.875rem 1rem;
        background: rgba(43,84,191,.05);
        border: 1px solid rgba(43,84,191,.15);
        border-radius: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .rf-tip-card span.material-symbols-outlined { font-size: 1rem; color: #2b54bf; margin-top: .1rem; }
    .rf-tip-card p { font-size: 0.75rem; font-weight: 400; color: #434653; line-height: 1.6; }
    .rf-tip-card strong { color: #1a1b22; font-weight: 700; }
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
        $currentStep = 6;
        $totalSteps  = count($steps);

        $maxMotivasi = 1000;
        $maxKegiatan = 800;
    @endphp

    @include('layouts_recruitments.partials._stepper', ['currentStep' => $currentStep, 'steps' => $steps])

    <div class="bg-surface-container-lowest rounded-xl
                shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
                border-l-4 border-primary overflow-hidden mb-8">

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-8 py-5 border-b border-outline-variant bg-surface-container-low">
            <span class="material-symbols-outlined text-primary"
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">lightbulb</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">
                    Step 6 — Motivasi &amp; Kegiatan Ekstra
                </h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Ceritakan motivasi Anda melamar dan kegiatan ekstra yang Anda sukai.
                </p>
            </div>
        </div>

        <form id="registrationForm"
              action="{{ route('recruitments.step.store', ['step' => 6]) }}"
              method="POST"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 space-y-8">

                {{-- ================================================
                     Field 1 · Motivasi Melamar
                     ================================================ --}}
                <div class="rf-section-block d1">

                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary" style="font-size:1.1rem;">campaign</span>
                        <h3 class="text-body-lg font-semibold text-on-surface">Motivasi Melamar</h3>
                        <div style="flex:1; height:1px; background:#c4c6d5; margin-left:.5rem;"></div>
                    </div>

                    <div class="rf-tip-card">
                        <span class="material-symbols-outlined">tips_and_updates</span>
                        <p>
                            Tuliskan <strong>alasan yang tulus</strong> mengapa Anda ingin bergabung dengan perusahaan ini.
                            Sertakan nilai-nilai yang Anda cari, kontribusi yang ingin Anda berikan,
                            dan bagaimana posisi ini sesuai dengan tujuan karir Anda.
                            <strong>Minimal 50 karakter, maksimal {{ number_format($maxMotivasi) }} karakter.</strong>
                        </p>
                    </div>

                    <div>
                        <label class="rf-label" for="motivasi">
                            Motivasi Melamar <span class="text-error">*</span>
                        </label>
                        <textarea class="rf-textarea @error('motivasi') error @enderror"
                                  id="motivasi"
                                  name="motivasi"
                                  rows="7"
                                  maxlength="{{ $maxMotivasi }}"
                                  placeholder="Mengapa Anda ingin bergabung dengan perusahaan ini? Ceritakan motivasi, nilai-nilai, dan harapan Anda..."
                                  oninput="updateCounter(this,'counter-motivasi',{{ $maxMotivasi }})">{{ old('motivasi', $savedData['motivasi'] ?? '') }}</textarea>
                        <div class="char-counter" id="counter-motivasi">
                            <span id="len-motivasi">{{ strlen(old('motivasi', $savedData['motivasi'] ?? '')) }}</span> / {{ $maxMotivasi }} karakter
                        </div>
                        @error('motivasi')
                            <p class="rf-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- ================================================
                     Field 2 · Kegiatan Ekstra
                     ================================================ --}}
                <div class="rf-section-block d2" style="padding-top:1.5rem; border-top:1px solid #c4c6d5;">

                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-primary" style="font-size:1.1rem;">sports_esports</span>
                        <h3 class="text-body-lg font-semibold text-on-surface">Kegiatan Ekstra yang Disukai</h3>
                        <div style="flex:1; height:1px; background:#c4c6d5; margin-left:.5rem;"></div>
                    </div>

                    <div class="rf-tip-card">
                        <span class="material-symbols-outlined">tips_and_updates</span>
                        <p>
                            Ceritakan <strong>kegiatan di luar pekerjaan</strong> yang Anda minati —
                            seperti olahraga, seni, kegiatan sosial, atau pengembangan diri.
                            Ini membantu kami memahami karakter dan keseimbangan hidup Anda.
                            <strong>Maksimal {{ number_format($maxKegiatan) }} karakter.</strong>
                        </p>
                    </div>

                    <div>
                        <label class="rf-label" for="kegiatan_ekstra">
                            Kegiatan Ekstra <span class="text-error">*</span>
                        </label>
                        <textarea class="rf-textarea @error('kegiatan_ekstra') error @enderror"
                                  id="kegiatan_ekstra"
                                  name="kegiatan_ekstra"
                                  rows="6"
                                  maxlength="{{ $maxKegiatan }}"
                                  placeholder="Kegiatan apa yang Anda sukai di luar pekerjaan? Ceritakan minat, hobi, atau aktivitas yang rutin Anda lakukan..."
                                  oninput="updateCounter(this,'counter-kegiatan',{{ $maxKegiatan }})">{{ old('kegiatan_ekstra', $savedData['kegiatan_ekstra'] ?? '') }}</textarea>
                        <div class="char-counter" id="counter-kegiatan">
                            <span id="len-kegiatan">{{ strlen(old('kegiatan_ekstra', $savedData['kegiatan_ekstra'] ?? '')) }}</span> / {{ $maxKegiatan }} karakter
                        </div>
                        @error('kegiatan_ekstra')
                            <p class="rf-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

            </div>{{-- /px-8 py-8 --}}

            {{-- Nav Footer --}}
            <div class="flex items-center justify-between px-8 py-5
                        border-t border-outline-variant bg-surface-container-low">

                <a href="{{ route('recruitments.step', ['step' => 5]) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                          border border-outline text-on-surface-variant text-label-lg font-bold
                          hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Sebelumnya
                </a>

                <span class="text-label-lg text-on-surface-variant">Step 6 dari 8</span>

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
    /**
     * Update character counter display below a textarea.
     * @param {HTMLTextAreaElement} el      — textarea element
     * @param {string}              counterId — id of the counter <div>
     * @param {number}              max     — max characters allowed
     */
    function updateCounter(el, counterId, max) {
        const len     = el.value.length;
        const counter = document.getElementById(counterId);
        if (!counter) return;

        // Update the <span> inside the counter div
        const span = counter.querySelector('span') ?? counter;
        span.textContent = len;

        // Colour feedback
        counter.classList.remove('warn', 'over');
        if (len >= max)              counter.classList.add('over');
        else if (len >= max * 0.85)  counter.classList.add('warn');
    }

    // Init counters on page load (handles old() bounce-back values)
    document.addEventListener('DOMContentLoaded', () => {
        [
            { id: 'motivasi',       counter: 'counter-motivasi',  max: {{ $maxMotivasi }} },
            { id: 'kegiatan_ekstra', counter: 'counter-kegiatan', max: {{ $maxKegiatan }} },
        ].forEach(({ id, counter, max }) => {
            const el = document.getElementById(id);
            if (el) updateCounter(el, counter, max);
        });
    });
</script>
@endpush
