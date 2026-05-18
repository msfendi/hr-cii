{{--
    View  : recruitments/step-2.blade.php
    Step  : 2 — Kontak & Alamat (Contact & Address)
    Fields: Alamat Asal (Lengkap, Kab/Kota, Status Domisili)
            Alamat Sekarang (Lengkap, Kab/Kota, Status Domisili)
            No. Telepon
            Kontak Darurat (Nama, Hubungan, No. Telepon)
--}}

@extends('layouts_recruitments.app')

{{-- ============================================================
     META
     ============================================================ --}}
@section('title', 'Registrasi — Step 2: Kontak & Alamat | RecruitFlow')

@push('styles')
<style>
    /* ---------- Reusable field token classes (sama dengan step-1) ---------- */
    .rf-label {
        display: block;
        font-size: 0.75rem;
        line-height: 1;
        font-weight: 700;
        color: #1a1b22;
        margin-bottom: 0.5rem;
        letter-spacing: 0.02em;
    }
    .rf-input,
    .rf-select,
    .rf-textarea {
        width: 100%;
        padding: 0.625rem 1rem;
        background: #ffffff;
        border: 1px solid #c4c6d5;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        line-height: 1.5;
        color: #1a1b22;
        box-shadow: 0 .1rem .5rem 0 rgba(58,59,69,.06);
        transition: border-color .15s, box-shadow .15s;
        font-family: 'Nunito Sans', sans-serif;
    }
    .rf-textarea { resize: vertical; min-height: 90px; }
    .rf-input:focus,
    .rf-select:focus,
    .rf-textarea:focus {
        outline: none;
        border-color: #2b54bf;
        box-shadow: 0 0 0 3px rgba(43,84,191,.12);
    }
    .rf-input.error,
    .rf-textarea.error { border-color: #ba1a1a; }
    .rf-select { appearance: none; padding-right: 2.5rem; cursor: pointer; }
    .rf-input-icon { padding-left: 2.5rem; }
    .rf-icon-prefix {
        position: absolute;
        inset-block: 0; left: 0;
        padding-left: 0.75rem;
        display: flex; align-items: center;
        pointer-events: none;
        color: #747684;
    }
    .rf-icon-suffix {
        position: absolute;
        inset-block: 0; right: 0;
        padding-right: 0.75rem;
        display: flex; align-items: center;
        pointer-events: none;
        color: #747684;
    }
    /* Section title row */
    .rf-section-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }
    .rf-section-title span.material-symbols-outlined { font-size: 1.1rem; color: #2b54bf; }
    .rf-section-title h3 { font-size: 1rem; font-weight: 600; color: #1a1b22; }
    .rf-section-title .divider { flex: 1; height: 1px; background: #c4c6d5; margin-left: 0.5rem; }
    /* Address sub-label badge */
    .rf-addr-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #2b54bf;
        padding: 0.25rem 0.625rem;
        background: rgba(43,84,191,.08);
        border-radius: 0.75rem;
        margin-bottom: 1rem;
    }
    /* Section blocks with stagger */
    .rf-section-block {
        animation: rfFadeUp .35s ease both;
        padding-top: 1.5rem;
        border-top: 1px solid #c4c6d5;
    }
    .rf-section-block:first-child { border-top: none; padding-top: 0; }
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .d1{ animation-delay:.04s; } .d2{ animation-delay:.10s; }
    .d3{ animation-delay:.16s; } .d4{ animation-delay:.22s; }
    /* Error text */
    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }
    /* Emergency sub-card accent */
    .rf-emergency-card {
        background: #f3f3fc;
        border: 1px solid rgba(196,198,213,.6);
        border-left: 3px solid #8c4b00;   /* tertiary */
        border-radius: 0.75rem;
        padding: 1.25rem;
    }
    .rf-emergency-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #8c4b00;
        margin-bottom: 1rem;
    }
    .rf-emergency-title span.material-symbols-outlined { font-size: 1.1rem; }
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
        $currentStep = 2;
        $totalSteps  = count($steps);
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
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">home_pin</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">
                    Step 2 — Kontak &amp; Alamat
                </h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Isi alamat asal, alamat sekarang, dan kontak darurat.
                </p>
            </div>
        </div>

        {{-- ====== FORM ====== --}}
        <form id="registrationForm"
              action="{{ route('recruitments.step.store', ['step' => 2]) }}"
              method="POST"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 space-y-8">

                {{-- -----------------------------------------------
                     S1 · Alamat Asal (KTP)
                     ----------------------------------------------- --}}
                <div class="rf-section-block d1">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">location_on</span>
                        <h3>Alamat</h3>
                        <div class="divider"></div>
                    </div>

                    {{-- ---- Alamat Asal ---- --}}
                    <div class="space-y-4 mb-8">
                        <div class="rf-addr-badge">
                            <span class="material-symbols-outlined" style="font-size:0.85rem;">home</span>
                            Alamat Asal (Sesuai KTP)
                        </div>

                        {{-- Alamat Lengkap Asal --}}
                        <div>
                            <label class="rf-label" for="alamat_asal">
                                Alamat Lengkap <span class="text-error">*</span>
                            </label>
                            <textarea class="rf-textarea @error('alamat_asal') error @enderror"
                                      id="alamat_asal" name="alamat_asal"
                                      rows="3"
                                      placeholder="Masukkan alamat lengkap asal sesuai KTP">{{ old('alamat_asal', $savedData['alamat_asal'] ?? '') }}</textarea>
                            @error('alamat_asal')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                            {{-- Kab/Kota Asal --}}
                            <div>
                                <label class="rf-label" for="kab_kota_asal">
                                    Kabupaten / Kota <span class="text-error">*</span>
                                </label>
                                <input class="rf-input"
                                       id="kab_kota_asal" name="kab_kota_asal"
                                       value="{{ old('kab_kota_asal', $savedData['kab_kota_asal'] ?? '') }}"
                                       placeholder="Masukkan kota atau kabupaten" type="text">
                            </div>

                            {{-- Status Domisili Asal --}}
                            <div>
                                <label class="rf-label" for="status_domisili_asal">Status Domisili</label>
                                <div class="relative">
                                    <select class="rf-select" id="status_domisili_asal" name="status_domisili_asal">
                                        <option value="" disabled {{ old('status_domisili_asal', $savedData['status_domisili_asal'] ?? '') === '' ? 'selected' : '' }}>Pilih status</option>
                                        @foreach(['Milik Sendiri','Sewa/Kontrak','Ikut Orang Tua'] as $sd)
                                            <option value="{{ $sd }}" {{ old('status_domisili_asal', $savedData['status_domisili_asal'] ?? '') === $sd ? 'selected' : '' }}>{{ $sd }}</option>
                                        @endforeach
                                    </select>
                                    <div class="rf-icon-suffix">
                                        <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Divider between addresses --}}
                    <div class="flex items-center gap-3 my-6">
                        <div class="flex-1 h-px bg-outline-variant/60"></div>
                        <span class="text-label-md text-outline px-2">atau</span>
                        <div class="flex-1 h-px bg-outline-variant/60"></div>
                    </div>

                    {{-- ---- Alamat Sekarang ---- --}}
                    <div class="space-y-4">
                        <div class="rf-addr-badge" style="color:#5b5e6c; background:rgba(91,94,108,.08);">
                            <span class="material-symbols-outlined" style="font-size:0.85rem;">apartment</span>
                            Alamat Sekarang (Domisili Aktual)
                        </div>

                        {{-- Checkbox sama dengan asal --}}
                        <label class="inline-flex items-center gap-2 cursor-pointer text-body-md text-on-surface-variant
                                      hover:text-on-surface transition-colors duration-150">
                            <input type="checkbox" id="same_as_asal" name="same_as_asal" value="1"
                                   class="accent-primary h-4 w-4 rounded"
                                   {{ old('same_as_asal', $savedData['same_as_asal'] ?? null) ? 'checked' : '' }}>
                            <span class="text-label-lg">Sama dengan alamat asal</span>
                        </label>

                        <div id="alamat-sekarang-fields" class="{{ old('same_as_asal', $savedData['same_as_asal'] ?? null) ? 'opacity-50 pointer-events-none' : '' }}">

                            {{-- Alamat Lengkap Sekarang --}}
                            <div class="mb-4">
                                <label class="rf-label" for="alamat_sekarang">Alamat Lengkap</label>
                                <textarea class="rf-textarea @error('alamat_sekarang') error @enderror"
                                          id="alamat_sekarang" name="alamat_sekarang"
                                          rows="3"
                                          placeholder="Masukkan alamat tinggal saat ini">{{ old('alamat_sekarang', $savedData['alamat_sekarang'] ?? '') }}</textarea>
                                @error('alamat_sekarang')
                                    <p class="rf-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                                {{-- Kab/Kota Sekarang --}}
                                <div>
                                    <label class="rf-label" for="kab_kota_sekarang">Kabupaten / Kota</label>
                                    <input class="rf-input"
                                           id="kab_kota_sekarang" name="kab_kota_sekarang"
                                           value="{{ old('kab_kota_sekarang', $savedData['kab_kota_sekarang'] ?? '') }}"
                                           placeholder="Masukkan kota atau kabupaten" type="text">
                                </div>

                                {{-- Status Domisili Sekarang --}}
                                <div>
                                    <label class="rf-label" for="status_domisili_sekarang">Status Domisili</label>
                                    <div class="relative">
                                        <select class="rf-select" id="status_domisili_sekarang" name="status_domisili_sekarang">
                                            <option value="" disabled {{ old('status_domisili_sekarang', $savedData['status_domisili_sekarang'] ?? '') === '' ? 'selected' : '' }}>Pilih status</option>
                                            @foreach(['Milik Sendiri','Sewa/Kontrak','Ikut Orang Tua'] as $sd)
                                                <option value="{{ $sd }}" {{ old('status_domisili_sekarang', $savedData['status_domisili_sekarang'] ?? '') === $sd ? 'selected' : '' }}>{{ $sd }}</option>
                                            @endforeach
                                        </select>
                                        <div class="rf-icon-suffix">
                                            <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- -----------------------------------------------
                     S2 · No. Telepon
                     ----------------------------------------------- --}}
                <div class="rf-section-block d2">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">call</span>
                        <h3>No. Telepon</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="rf-label" for="no_telepon">
                                No. Telepon Rumah / Lainnya
                            </label>
                            <div class="relative">
                                <div class="rf-icon-prefix">
                                    <span class="material-symbols-outlined" style="font-size:1rem;">call</span>
                                </div>
                                <input class="rf-input rf-input-icon"
                                       id="no_telepon" name="no_telepon"
                                       value="{{ old('no_telepon', $savedData['no_telepon'] ?? '') }}"
                                       placeholder="+62 812-3456-7890" type="tel">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- -----------------------------------------------
                     S3 · Kontak Darurat
                     ----------------------------------------------- --}}
                <div class="rf-section-block d3">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined" style="color:#8c4b00;">medical_information</span>
                        <h3>Kontak Darurat</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="rf-emergency-card">
                        <div class="rf-emergency-title">
                            <span class="material-symbols-outlined">emergency</span>
                            Informasi Kontak Darurat
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                            {{-- Nama Darurat --}}
                            <div>
                                <label class="rf-label" for="nama_darurat">
                                    Nama <span class="text-error">*</span>
                                </label>
                                <input class="rf-input @error('nama_darurat') error @enderror"
                                       id="nama_darurat" name="nama_darurat"
                                       value="{{ old('nama_darurat', $savedData['nama_darurat'] ?? '') }}"
                                       placeholder="Nama kontak darurat" type="text">
                                @error('nama_darurat')
                                    <p class="rf-error">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Hubungan --}}
                            <div>
                                <label class="rf-label" for="hubungan_darurat">
                                    Hubungan <span class="text-error">*</span>
                                </label>
                                <input class="rf-input"
                                       id="hubungan_darurat" name="hubungan_darurat"
                                       value="{{ old('hubungan_darurat', $savedData['hubungan_darurat'] ?? '') }}"
                                       placeholder="Contoh: Orang Tua, Pasangan" type="text">
                            </div>

                            {{-- No. Telepon Darurat --}}
                            <div>
                                <label class="rf-label" for="no_telepon_darurat">
                                    No. Telepon <span class="text-error">*</span>
                                </label>
                                <div class="relative">
                                    <div class="rf-icon-prefix">
                                        <span class="material-symbols-outlined" style="font-size:1rem;">phone_iphone</span>
                                    </div>
                                    <input class="rf-input rf-input-icon"
                                           id="no_telepon_darurat" name="no_telepon_darurat"
                                           value="{{ old('no_telepon_darurat', $savedData['no_telepon_darurat'] ?? '') }}"
                                           placeholder="08xxxxxxxxxx" type="tel">
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>{{-- /px-8 py-8 --}}

            {{-- ====================================================
                 NAV FOOTER
                 ==================================================== --}}
            <div class="flex items-center justify-between px-8 py-5
                        border-t border-outline-variant bg-surface-container-low">

                {{-- Prev --}}
                <a href="{{ route('recruitments.step', ['step' => 1]) }}"
                   class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full
                          border border-outline text-on-surface-variant text-label-lg font-bold
                          hover:bg-surface-container transition-colors duration-200">
                    <span class="material-symbols-outlined" style="font-size:1rem;">arrow_back</span>
                    Sebelumnya
                </a>

                {{-- Step counter --}}
                <span class="text-label-lg text-on-surface-variant">Step 2 dari 8</span>

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
    // "Sama dengan alamat asal" checkbox logic
    const sameCheckbox       = document.getElementById('same_as_asal');
    const sekarangFields     = document.getElementById('alamat-sekarang-fields');

    // Field refs for copy
    const asalAlamat   = document.getElementById('alamat_asal');
    const asalKabKota  = document.getElementById('kab_kota_asal');
    const asalDomisili = document.getElementById('status_domisili_asal');

    const nowAlamat    = document.getElementById('alamat_sekarang');
    const nowKabKota   = document.getElementById('kab_kota_sekarang');
    const nowDomisili  = document.getElementById('status_domisili_sekarang');

    function toggleSekarang(checked) {
        if (checked) {
            // Copy values
            nowAlamat.value   = asalAlamat.value;
            nowKabKota.value  = asalKabKota.value;
            nowDomisili.value = asalDomisili.value;
            // Disable & dim
            sekarangFields.classList.add('opacity-50', 'pointer-events-none');
        } else {
            sekarangFields.classList.remove('opacity-50', 'pointer-events-none');
        }
    }

    sameCheckbox?.addEventListener('change', e => toggleSekarang(e.target.checked));
    // Init on load
    toggleSekarang(sameCheckbox?.checked);
</script>
@endpush
