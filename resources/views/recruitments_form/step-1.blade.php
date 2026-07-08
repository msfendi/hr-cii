{{--
    View  : recruitments/step-1.blade.php
    Step  : 1 — Data Pribadi (Personal Information)
    Fields: Nama Lengkap, NIK, No KK, SIM,
            Tempat/Tanggal Lahir, WN, Umur (auto), Golongan Darah,
            Jenis Kelamin, Status Pernikahan, KB, Tanggungan,
            Nomor HP, Agama, Bakat/Hobby, Transportasi,
            Pendidikan, Jabatan, Department,
            BPJS TK, BPJS Kesehatan
--}}

@extends('layouts_recruitments.app')

{{-- ============================================================
     META
     ============================================================ --}}
@section('title', 'Registrasi — Step 1: Data Pribadi | RecruitFlow')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* select2 override */
    .select2-container--default .select2-selection--single {
        background-color: transparent !important;
        border: 1px solid #747684 !important;
        border-radius: 0.25rem !important;
        height: 38px !important;
        padding: 0.2rem 0.5rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #1a1b22 !important;
        line-height: 1.5 !important;
        font-size: 0.875rem !important;
    }
    /* ---------- Reusable field token classes ---------- */
    .rf-label {
        display: block;
        font-size: 0.75rem;
        line-height: 1;
        font-weight: 700;
        color: #1a1b22;       /* on-surface */
        margin-bottom: 0.5rem;
        letter-spacing: 0.02em;
    }
    .rf-input,
    .rf-select {
        width: 100%;
        padding: 0.625rem 1rem;
        background: #ffffff;   /* surface-container-lowest */
        border: 1px solid #c4c6d5;   /* outline-variant */
        border-radius: 0.5rem;       /* xl */
        font-size: 0.875rem;
        line-height: 1.5;
        color: #1a1b22;
        box-shadow: 0 .1rem .5rem 0 rgba(58,59,69,.06);
        transition: border-color .15s, box-shadow .15s;
    }
    .rf-input:focus,
    .rf-select:focus {
        outline: none;
        border-color: #2b54bf;  /* primary */
        box-shadow: 0 0 0 3px rgba(43,84,191,.12);
    }
    .rf-input.error { border-color: #ba1a1a; }
    .rf-input-readonly {
        composes: rf-input;
        background: #eeedf7;   /* surface-container */
        color: #434653;        /* on-surface-variant */
        cursor: not-allowed;
    }
    .rf-select { appearance: none; padding-right: 2.5rem; cursor: pointer; }
    .rf-input-icon { padding-left: 2.5rem; }
    .rf-icon-prefix {
        position: absolute;
        inset-block: 0; left: 0;
        padding-left: 0.75rem;
        display: flex; align-items: center;
        pointer-events: none;
        color: #747684;   /* outline */
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
    .rf-section-title span.material-symbols-outlined {
        font-size: 1.1rem;
        color: #2b54bf;
    }
    .rf-section-title h3 {
        font-size: 1rem;
        font-weight: 600;
        color: #1a1b22;
    }
    .rf-section-title .divider {
        flex: 1;
        height: 1px;
        background: #c4c6d5;
        margin-left: 0.5rem;
    }
    /* Radio pill */
    .rf-radio-label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: #1a1b22;
        cursor: pointer;
        padding: 0.375rem 0.875rem;
        border: 1px solid #c4c6d5;
        border-radius: 0.75rem;    /* full */
        transition: all .15s;
    }
    .rf-radio-label:hover { border-color: #2b54bf; background: rgba(43,84,191,.04); }
    .rf-radio-label input { accent-color: #2b54bf; }
    /* BPJS sub-card */
    .rf-sub-card {
        padding: 1.25rem;
        background: #f3f3fc;   /* surface-container-low */
        border: 1px solid rgba(196,198,213,.6);
        border-radius: 0.75rem;
    }
    .rf-sub-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #434653;
        margin-bottom: 1rem;
    }
    /* Error text */
    .rf-error { font-size: 0.7rem; color: #ba1a1a; margin-top: 0.25rem; }
    /* Fade-up stagger */
    @keyframes rfFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .rf-section-block {
        animation: rfFadeUp .35s ease both;
        padding-top: 1.5rem;
        border-top: 1px solid #c4c6d5;
    }
    .rf-section-block:first-child { border-top: none; padding-top: 0; }
    .d1{ animation-delay:.04s; } .d2{ animation-delay:.10s; }
    .d3{ animation-delay:.16s; } .d4{ animation-delay:.22s; }
    .d5{ animation-delay:.28s; }
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
        $currentStep = 1;
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
                  style="font-variation-settings:'FILL' 1; font-size:1.5rem;">badge</span>
            <div>
                <h2 class="text-headline-md font-semibold text-primary leading-tight">
                    Step 1 — Data Pribadi
                </h2>
                <p class="text-label-lg text-on-surface-variant font-normal mt-0.5">
                    Isi informasi pribadi sesuai dokumen resmi (KTP / KK).
                </p>
            </div>
        </div>

        {{-- ====== FORM ====== --}}
        <form id="registrationForm"
              action="{{ route('recruitments.step.store', ['step' => 1]) }}"
              method="POST"
              class="flex flex-col">
            @csrf

            <div class="px-8 py-8 space-y-8">

                {{-- -----------------------------------------------
                     S1 · Identitas Utama
                     ----------------------------------------------- --}}
                <div class="rf-section-block d1">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">fingerprint</span>
                        <h3>Identitas Utama</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        {{-- Nama Lengkap --}}
                        <div class="md:col-span-2">
                            <label class="rf-label" for="nama_lengkap">
                                Nama Lengkap <span class="text-error">*</span>
                            </label>
                            <input class="rf-input @error('nama_lengkap') error @enderror"
                                   id="nama_lengkap" name="nama_lengkap"
                                   value="{{ old('nama_lengkap', $savedData['nama_lengkap'] ?? '') }}"
                                   placeholder="Sesuai KTP" type="text">
                            @error('nama_lengkap')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- NIK --}}
                        <div>
                            <label class="rf-label" for="nik">NIK <span class="text-error">*</span></label>
                            <input class="rf-input @error('nik') error @enderror"
                                   id="nik" name="nik"
                                   value="{{ old('nik', $savedData['nik'] ?? '') }}"
                                   maxlength="16" placeholder="16 digit NIK" type="text">
                            @error('nik')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- No KK --}}
                        <div>
                            <label class="rf-label" for="no_kk">No. KK <span class="text-error">*</span></label>
                            <input class="rf-input @error('no_kk') error @enderror"
                                   id="no_kk" name="no_kk"
                                   value="{{ old('no_kk', $savedData['no_kk'] ?? '') }}"
                                   maxlength="16" placeholder="16 digit No KK" type="text">
                            @error('no_kk')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- SIM --}}
                        <div>
                            <label class="rf-label" for="sim">
                                Nomor SIM
                                <span style="color:#747684;font-weight:400;font-size:0.68rem;margin-left:4px;">(Opsional)</span>
                            </label>
                            <input class="rf-input"
                                   id="sim" name="sim"
                                   value="{{ old('sim', $savedData['sim'] ?? '') }}"
                                   placeholder="Nomor SIM" type="text">
                        </div>

                    </div>
                </div>

                {{-- -----------------------------------------------
                     S2 · Kelahiran & Kewarganegaraan
                     ----------------------------------------------- --}}
                <div class="rf-section-block d2">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">cake</span>
                        <h3>Kelahiran &amp; Kewarganegaraan</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                        <div>
                            <label class="rf-label" for="tempat_lahir">Tempat Lahir <span class="text-error">*</span></label>
                            <input class="rf-input @error('tempat_lahir') error @enderror" id="tempat_lahir" name="tempat_lahir"
                                   value="{{ old('tempat_lahir', $savedData['tempat_lahir'] ?? '') }}"
                                   placeholder="Kota / Kabupaten" type="text">
                            @error('tempat_lahir')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="rf-label" for="tanggal_lahir">Tanggal Lahir <span class="text-error">*</span></label>
                            <input class="rf-input @error('tanggal_lahir') error @enderror" id="tanggal_lahir" name="tanggal_lahir"
                                   value="{{ old('tanggal_lahir', $savedData['tanggal_lahir'] ?? '') }}" type="date">
                            @error('tanggal_lahir')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="rf-label" for="warga_negara">Warga Negara <span class="text-error">*</span></label>
                            <div class="relative">
                                <select class="rf-select @error('warga_negara') error @enderror" id="warga_negara" name="warga_negara">
                                    <option value="" disabled {{ old('warga_negara', $savedData['warga_negara'] ?? '') === '' ? 'selected' : '' }}>Pilih WN</option>
                                    <option value="WNI" {{ old('warga_negara', $savedData['warga_negara'] ?? '') === 'WNI' ? 'selected' : '' }}>WNI (Warga Negara Indonesia)</option>
                                    <option value="WNA" {{ old('warga_negara', $savedData['warga_negara'] ?? '') === 'WNA' ? 'selected' : '' }}>WNA (Warga Negara Asing)</option>
                                </select>
                                <div class="rf-icon-suffix">
                                    <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                </div>
                            </div>
                            @error('warga_negara')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Umur (readonly, auto-calc) --}}
                        <div>
                            <label class="rf-label" for="umur">Umur</label>
                            <input class="rf-input" style="background:#eeedf7;color:#434653;cursor:not-allowed;"
                                   id="umur" name="umur" readonly type="text"
                                   placeholder="Otomatis dari tanggal lahir">
                            <p style="font-size:0.65rem;color:#747684;margin-top:0.25rem;">
                                Dihitung otomatis dari tanggal lahir
                            </p>
                        </div>

                        <div>
                            <label class="rf-label" for="golongan_darah">Golongan Darah <span class="text-error">*</span></label>
                            <div class="relative">
                                <select class="rf-select @error('golongan_darah') error @enderror" id="golongan_darah" name="golongan_darah">
                                    <option value="" disabled {{ old('golongan_darah', $savedData['golongan_darah'] ?? '') === '' ? 'selected' : '' }}>Pilih</option>
                                    @foreach(['A','B','AB','O'] as $gd)
                                        <option value="{{ $gd }}" {{ old('golongan_darah', $savedData['golongan_darah'] ?? '') === $gd ? 'selected' : '' }}>{{ $gd }}</option>
                                    @endforeach
                                </select>
                                <div class="rf-icon-suffix">
                                    <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                </div>
                            </div>
                            @error('golongan_darah')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- -----------------------------------------------
                     S3 · Status Sosial
                     ----------------------------------------------- --}}
                <div class="rf-section-block d3">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">groups</span>
                        <h3>Status Sosial</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        {{-- Jenis Kelamin --}}
                        <div>
                            <label class="rf-label">Jenis Kelamin <span class="text-error">*</span></label>
                            <div class="flex flex-wrap gap-3 mt-1">
                                <label class="rf-radio-label @error('jenis_kelamin') border-[#ba1a1a] @enderror">
                                    <input type="radio" name="jenis_kelamin" value="L"
                                           {{ old('jenis_kelamin', $savedData['jenis_kelamin'] ?? '') === 'L' ? 'checked' : '' }}>
                                    <span class="material-symbols-outlined" style="font-size:1rem;">man</span>
                                    Laki-laki
                                </label>
                                <label class="rf-radio-label @error('jenis_kelamin') border-[#ba1a1a] @enderror">
                                    <input type="radio" name="jenis_kelamin" value="P"
                                           {{ old('jenis_kelamin', $savedData['jenis_kelamin'] ?? '') === 'P' ? 'checked' : '' }}>
                                    <span class="material-symbols-outlined" style="font-size:1rem;">woman</span>
                                    Perempuan
                                </label>
                            </div>
                            @error('jenis_kelamin')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Status Pernikahan --}}
                        <div>
                            <label class="rf-label" for="status_pernikahan">Status Pernikahan <span class="text-error">*</span></label>
                            <div class="relative">
                                <select class="rf-select @error('status_pernikahan') error @enderror" id="status_pernikahan" name="status_pernikahan">
                                    <option value="" disabled {{ old('status_pernikahan', $savedData['status_pernikahan'] ?? '') === '' ? 'selected' : '' }}>Pilih Status</option>
                                    @foreach(['Belum Kawin','Kawin','Cerai Hidup','Cerai Mati'] as $sp)
                                        <option value="{{ $sp }}" {{ old('status_pernikahan', $savedData['status_pernikahan'] ?? '') === $sp ? 'selected' : '' }}>{{ $sp }}</option>
                                    @endforeach
                                </select>
                                <div class="rf-icon-suffix">
                                    <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                </div>
                            </div>
                            @error('status_pernikahan')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- KB --}}
                        <div>
                            <label class="rf-label">Mengikuti KB? <span class="text-error">*</span></label>
                            <div class="flex flex-wrap gap-3 mt-1">
                                <label class="rf-radio-label">
                                    <input type="radio" name="kb" value="Ya" {{ old('kb', $savedData['kb'] ?? 'Tidak') === 'Ya' ? 'checked' : '' }}>
                                    Ya
                                </label>
                                <label class="rf-radio-label">
                                    <input type="radio" name="kb" value="Tidak" {{ old('kb', $savedData['kb'] ?? 'Tidak') === 'Tidak' ? 'checked' : '' }}>
                                    Tidak
                                </label>
                            </div>
                        </div>

                        {{-- Tanggungan --}}
                        <div>
                            <label class="rf-label" for="tanggungan">Jumlah Tanggungan</label>
                            <input class="rf-input" id="tanggungan" name="tanggungan"
                                   value="{{ old('tanggungan', $savedData['tanggungan'] ?? 0) }}"
                                   min="0" type="number" placeholder="0">
                        </div>

                    </div>
                </div>

                {{-- -----------------------------------------------
                     S4 · Kontak & Agama
                     ----------------------------------------------- --}}
                <div class="rf-section-block d4">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">contact_phone</span>
                        <h3>Kontak &amp; Agama</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        {{-- Nomor HP --}}
                        <div>
                            <label class="rf-label" for="nomor_hp">Nomor HP <span class="text-error">*</span></label>
                            <div class="relative">
                                <div class="rf-icon-prefix">
                                    <span class="material-symbols-outlined" style="font-size:1rem;">phone_iphone</span>
                                </div>
                                <input class="rf-input rf-input-icon @error('nomor_hp') error @enderror"
                                       id="nomor_hp" name="nomor_hp"
                                       value="{{ old('nomor_hp', $savedData['nomor_hp'] ?? '') }}"
                                       placeholder="08xxxxxxxxxx" type="tel">
                            </div>
                            @error('nomor_hp')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Agama --}}
                        <div>
                            <label class="rf-label" for="agama">Agama <span class="text-error">*</span></label>
                            <div class="relative">
                                <select class="rf-select @error('agama') error @enderror" id="agama" name="agama">
                                    <option value="" disabled {{ old('agama', $savedData['agama'] ?? '') === '' ? 'selected' : '' }}>Pilih Agama</option>
                                    @foreach(['Islam','Kristen','Katolik','Hindu','Buddha','Khonghucu'] as $ag)
                                        <option value="{{ $ag }}" {{ old('agama', $savedData['agama'] ?? '') === $ag ? 'selected' : '' }}>{{ $ag }}</option>
                                    @endforeach
                                </select>
                                <div class="rf-icon-suffix">
                                    <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                </div>
                            </div>
                            @error('agama')
                                <p class="rf-error">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Hobby --}}
                        <div>
                            <label class="rf-label" for="hobby">Bakat / Hobby</label>
                            <input class="rf-input" id="hobby" name="hobby"
                                   value="{{ old('hobby', $savedData['hobby'] ?? '') }}"
                                   placeholder="Contoh: Membaca, Olahraga" type="text">
                        </div>

                        {{-- Transportasi --}}
                        <div>
                            <label class="rf-label" for="transportasi">Mode Transportasi Utama</label>
                            <div class="relative">
                                <select class="rf-select" id="transportasi" name="transportasi">
                                    <option value="" disabled {{ old('transportasi', $savedData['transportasi'] ?? '') === '' ? 'selected' : '' }}>Pilih Transportasi</option>
                                    @foreach([
                                        'Kendaraan Pribadi (Mobil)',
                                        'Kendaraan Pribadi (Motor)',
                                        'Kendaraan Umum',
                                        'Jalan Kaki',
                                    ] as $tr)
                                        <option value="{{ $tr }}" {{ old('transportasi', $savedData['transportasi'] ?? '') === $tr ? 'selected' : '' }}>{{ $tr }}</option>
                                    @endforeach
                                </select>
                                <div class="rf-icon-suffix">
                                    <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- -----------------------------------------------
                     S5 · Pekerjaan & BPJS
                     ----------------------------------------------- --}}
                <div class="rf-section-block d5">
                    <div class="rf-section-title">
                        <span class="material-symbols-outlined">work</span>
                        <h3>Pekerjaan &amp; BPJS</h3>
                        <div class="divider"></div>
                    </div>

                    <div class="space-y-5">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                            {{-- Pendidikan --}}
                            <div>
                                <label class="rf-label" for="pendidikan">Pendidikan Terakhir</label>
                                <div class="relative">
                                    <select class="rf-select" id="pendidikan" name="pendidikan">
                                        <option value="" disabled {{ old('pendidikan', $savedData['pendidikan'] ?? '') === '' ? 'selected' : '' }}>Pilih Pendidikan</option>
                                        @foreach(['SD','SMP','SMA/SMK','D3','S1','S2','S3'] as $p)
                                            <option value="{{ $p }}" {{ old('pendidikan', $savedData['pendidikan'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                                        @endforeach
                                    </select>
                                    <div class="rf-icon-suffix">
                                        <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Department --}}
                            <div>
                                <label class="rf-label" for="department">Department</label>
                                <select class="rf-select select2-dept" id="department" name="department" required>
                                    <option value="" disabled selected>Pilih / Masukkan Department</option>
                                    @foreach($positions ?? [] as $dept => $posList)
                                        <option value="{{ $dept }}" {{ old('department', $savedData['department'] ?? '') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Jabatan --}}
                            <div>
                                <label class="rf-label" for="jabatan">Jabatan</label>
                                <select class="rf-select select2-jabatan" id="jabatan" name="jabatan" required>
                                    <option value="" disabled selected>Pilih / Masukkan Jabatan</option>
                                </select>
                            </div>

                        </div>

                        {{-- BPJS Sub-card --}}
                        <div class="rf-sub-card">
                            <div class="rf-sub-title">
                                <span class="material-symbols-outlined" style="font-size:1.1rem;">account_balance_wallet</span>
                                Informasi BPJS
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                                <div>
                                    <label class="rf-label" for="bpjs_tk">BPJS Ketenagakerjaan</label>
                                    <div class="relative">
                                        <div class="rf-icon-prefix">
                                            <span class="material-symbols-outlined" style="font-size:1rem;">badge</span>
                                        </div>
                                        <input class="rf-input rf-input-icon"
                                               id="bpjs_tk" name="bpjs_tk"
                                               value="{{ old('bpjs_tk', $savedData['bpjs_tk'] ?? '') }}"
                                               placeholder="Nomor BPJS TK" type="text">
                                    </div>
                                </div>

                                <div>
                                    <label class="rf-label" for="bpjs_kes">BPJS Kesehatan</label>
                                    <div class="relative">
                                        <div class="rf-icon-prefix">
                                            <span class="material-symbols-outlined" style="font-size:1rem;">health_and_safety</span>
                                        </div>
                                        <input class="rf-input rf-input-icon"
                                               id="bpjs_kes" name="bpjs_kes"
                                               value="{{ old('bpjs_kes', $savedData['bpjs_kes'] ?? '') }}"
                                               placeholder="Nomor BPJS Kesehatan" type="text">
                                    </div>
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

                {{-- Spacer (prev hidden on step 1) --}}
                <div></div>

                {{-- Step counter --}}
                <span class="text-label-lg text-on-surface-variant">Step 1 dari 8</span>

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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Select2 logic for Department and Jabatan
    var positionData = @json($positions ?? []);
    var oldJabatan = "{{ old('jabatan', $savedData['jabatan'] ?? '') }}";

    $(document).ready(function() {
        $('.select2-dept').select2({
            placeholder: 'Pilih / Masukkan Department'
        });
        
        $('.select2-jabatan').select2({
            placeholder: 'Pilih / Masukkan Jabatan'
        });

        $('#department').on('change', function() {
            var selectedDept = $(this).val();
            var posOptions = '<option value="" disabled selected>Pilih / Masukkan Jabatan</option>';
            if (selectedDept && positionData[selectedDept]) {
                var uniquePositions = [];
                positionData[selectedDept].forEach(function(item) {
                    if(!uniquePositions.includes(item.position)){
                        uniquePositions.push(item.position);
                        var isSelected = (item.position === oldJabatan) ? 'selected' : '';
                        posOptions += '<option value="' + item.position + '" ' + isSelected + '>' + item.position + '</option>';
                    }
                });
            }
            
            var $jabatan = $('#jabatan');
            $jabatan.html(posOptions);
            
            // Restore old jabatan if typed manually and not in the list
            if (oldJabatan && !$jabatan.find("option[value='" + oldJabatan + "']").length) {
                var newOption = new Option(oldJabatan, oldJabatan, true, true);
                $jabatan.append(newOption);
            }
            
            $jabatan.trigger('change.select2');
        });

        // Trigger change on load if department is already selected (back button or validation error)
        if ($('#department').val()) {
            $('#department').trigger('change');
        }
    });

    // Auto-calculate age from tanggal_lahir + validasi minimal 18 tahun
    const dobEl     = document.getElementById('tanggal_lahir');
    const ageEl     = document.getElementById('umur');
    const submitBtn = document.querySelector('[type="submit"]');
    const MIN_AGE   = 18;

    // Batas maksimal input: harus sudah berulang tahun ke-18
    (function setMaxDate() {
        const max = new Date();
        max.setFullYear(max.getFullYear() - MIN_AGE);
        if (dobEl) dobEl.max = max.toISOString().split('T')[0];
    })();

    // Hapus error age sebelumnya jika ada
    function clearAgeError() {
        const old = document.getElementById('age-error-msg');
        if (old) old.remove();
    }

    function showAgeError(msg) {
        clearAgeError();
        const p = document.createElement('p');
        p.id        = 'age-error-msg';
        p.className = 'rf-error';
        p.textContent = msg;
        dobEl.closest('div').appendChild(p);
    }

    function calcAge(dob) {
        clearAgeError();
        if (!dob) {
            ageEl.value = '';
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        const today = new Date();
        const birth = new Date(dob);

        // Cegah tanggal masa depan
        if (birth > today) {
            ageEl.value = '';
            showAgeError('Tanggal lahir tidak boleh di masa depan.');
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;

        ageEl.value = age + ' Tahun';

        if (age < MIN_AGE) {
            showAgeError(`Usia minimum pendaftaran adalah ${MIN_AGE} tahun. Usia Anda saat ini: ${age} tahun.`);
            if (submitBtn) submitBtn.disabled = true;
        } else {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    dobEl?.addEventListener('change', e => calcAge(e.target.value));
    calcAge(dobEl?.value); // on page load (e.g. after validation bounce-back)
</script>
@endpush
