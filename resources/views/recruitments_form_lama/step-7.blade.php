{{--
    View  : recruitments/step-7.blade.php
    Step  : 7 — Data Fisik
    Fields: Tinggi Badan (cm), Berat Badan (kg)
--}}

@extends('layouts_recruitments.app')

@section('title', 'Registrasi — Step 7: Data Fisik | RecruitFlow')

@push('styles')
<style>
    /* ---------- Shared rf- token classes ---------- */
    .rf-label {
        display: block; font-size: 0.75rem; line-height: 1; font-weight: 700;
        color: #434653; margin-bottom: 0.5rem; letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .rf-input {
        width: 100%; padding: 0.625rem 3rem 0.625rem 1rem;
        background: #ffffff; border: 1px solid #c4c6d5;
        border-radius: 0.5rem; font-size: 0.875rem; line-height: 1.5;
        color: #1a1b22; font-family: 'Nunito Sans', sans-serif;
        box-shadow: 0 .1rem .5rem 0 rgba(58,59,69,.06);
        transition: border-color .15s, box-shadow .15s;
        outline: none;
    }
    .rf-input:focus {
        border-color: #2b54bf;
        box-shadow: 0 0 0 3px rgba(43,84,191,.12);
    }
    .rf-input.error { border-color: #ba1a1a; }
    .rf-unit {
        position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
        font-size: 0.8rem; font-weight: 700; color: rgba(67,70,83,.5);
        pointer-events: none; letter-spacing: 0.03em;
    }
    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }

    /* BMI Result card */
    .bmi-card {
        display: flex; align-items: center; gap: 1rem;
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        border: 1px solid #c4c6d5;
        background: #f3f3fc;
        transition: all .3s;
    }
    .bmi-value {
        font-size: 2rem; font-weight: 800; line-height: 1;
        color: #2b54bf; min-width: 4rem;
        transition: color .3s;
    }
    .bmi-label {
        font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 0.2rem 0.625rem; border-radius: 0.75rem;
        background: #e0e1f2; color: #444653;
        display: inline-block; margin-top: 0.25rem;
        transition: background .3s, color .3s;
    }
    .bmi-card.underweight { border-color: #8c4b00; background: #fff8f3; }
    .bmi-card.underweight .bmi-value { color: #8c4b00; }
    .bmi-card.underweight .bmi-label { background: #ffdcc2; color: #6d3900; }
    .bmi-card.normal       { border-color: #1a6b3c; background: #f0faf4; }
    .bmi-card.normal       .bmi-value { color: #1a6b3c; }
    .bmi-card.normal       .bmi-label { background: #c8f0da; color: #0e4427; }
    .bmi-card.overweight   { border-color: #8c4b00; background: #fff8f3; }
    .bmi-card.overweight   .bmi-value { color: #8c4b00; }
    .bmi-card.overweight   .bmi-label { background: #ffdcc2; color: #6d3900; }
    .bmi-card.obese        { border-color: #ba1a1a; background: #fff7f7; }
    .bmi-card.obese        .bmi-value { color: #ba1a1a; }
    .bmi-card.obese        .bmi-label { background: #ffdad6; color: #93000a; }

    /* Section animation */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .rf-section-block { animation: rfFadeUp .35s ease both; }
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
        $currentStep = 7;
        $totalSteps  = count($steps);
    @endphp

    @include('layouts_recruitments.partials._stepper', ['currentStep' => $currentStep, 'steps' => $steps])

    <div class="bg-surface-container-lowest rounded-xl
                shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.15)]
                border-l-4 border-primary overflow-hidden mb-8">

        {{-- Card Header --}}
        <div class="flex items-center gap-3 px-8 py-5 border-b border-outline-variant bg-surface-container-low">
            <span class="material-symbols-outlined text-primary"
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">monitoring</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">
                    Step 7 — Data Fisik
                </h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Isi informasi kondisi fisik Anda saat ini.
                </p>
            </div>
        </div>

        <form id="registrationForm"
              action="{{ route('recruitments.step.store', ['step' => 7]) }}"
              method="POST"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 rf-section-block">

                {{-- Section title --}}
                <div class="flex items-center gap-2 mb-6">
                    <span class="material-symbols-outlined text-primary" style="font-size:1.1rem;">straighten</span>
                    <h3 class="text-body-lg font-semibold text-on-surface">Pengukuran Tubuh</h3>
                    <div style="flex:1; height:1px; background:#c4c6d5; margin-left:.5rem;"></div>
                </div>

                {{-- Input grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">

                    {{-- Tinggi Badan --}}
                    <div>
                        <label class="rf-label" for="tinggi_badan">Tinggi Badan</label>
                        <div class="relative">
                            <input class="rf-input @error('tinggi_badan') error @enderror"
                                   id="tinggi_badan" name="tinggi_badan"
                                   type="number" min="100" max="250"
                                   inputmode="numeric"
                                   value="{{ old('tinggi_badan', $savedData['tinggi_badan'] ?? '') }}"
                                   placeholder="0">
                            <span class="rf-unit">cm</span>
                        </div>
                        @error('tinggi_badan')
                            <p class="rf-error">{{ $message }}</p>
                        @enderror
                        <p style="font-size:.65rem; color:#747684; margin-top:.25rem;">Rentang: 100 – 250 cm</p>
                    </div>

                    {{-- Berat Badan --}}
                    <div>
                        <label class="rf-label" for="berat_badan">Berat Badan</label>
                        <div class="relative">
                            <input class="rf-input @error('berat_badan') error @enderror"
                                   id="berat_badan" name="berat_badan"
                                   type="number" min="20" max="200"
                                   inputmode="numeric"
                                   value="{{ old('berat_badan', $savedData['berat_badan'] ?? '') }}"
                                   placeholder="0">
                            <span class="rf-unit">kg</span>
                        </div>
                        @error('berat_badan')
                            <p class="rf-error">{{ $message }}</p>
                        @enderror
                        <p style="font-size:.65rem; color:#747684; margin-top:.25rem;">Rentang: 20 – 200 kg</p>
                    </div>
                </div>

            </div>{{-- /px-8 py-8 --}}

            {{-- Nav Footer --}}
            <div class="flex items-center justify-between px-8 py-5
                        border-t border-outline-variant bg-surface-container-low">

                <a href="{{ route('recruitments.step', ['step' => 6]) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                          border border-outline text-on-surface-variant text-label-lg font-bold
                          hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Sebelumnya
                </a>

                <span class="text-label-lg text-on-surface-variant">Step 7 dari 8</span>

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

@endpush
