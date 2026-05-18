{{--
    Partial : registration/partials/_exp-fields.blade.php
    Dipakai : @include('registration.partials._exp-fields', ['i' => $i, 'exp' => $exp])

    Variables:
    - $i   : int   — index entry (0-based)
    - $exp : array — data dari old('experiences.$i') atau []
--}}

{{-- Card Header --}}
<div class="exp-card-header">
    <div class="exp-card-index">
        <div class="badge">{{ $i + 1 }}</div>
        Pengalaman Kerja ke-{{ $i + 1 }}
    </div>
    <button type="button" class="exp-delete-btn"
            onclick="removeExp(this)" title="Hapus entri ini">
        <span class="material-symbols-outlined">delete</span>
        Hapus
    </button>
</div>

{{-- Fields Grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-5">

    {{-- Nama Perusahaan --}}
    <div class="md:col-span-2">
        <label class="rf-label" for="exp_{{ $i }}_perusahaan">
            Nama Perusahaan <span class="text-error">*</span>
        </label>
        <input class="rf-input @error("experiences.{$i}.perusahaan") error @enderror"
               id="exp_{{ $i }}_perusahaan"
               name="experiences[{{ $i }}][perusahaan]"
               value="{{ $exp['perusahaan'] ?? '' }}"
               type="text" placeholder="Nama perusahaan">
        @error("experiences.{$i}.perusahaan")
            <p class="rf-error">{{ $message }}</p>
        @enderror
    </div>

    {{-- Masa Kerja Dari --}}
    <div>
        <label class="rf-label" for="exp_{{ $i }}_dari">Masa Kerja (Dari)</label>
        <input class="rf-input"
               id="exp_{{ $i }}_dari"
               name="experiences[{{ $i }}][dari]"
               value="{{ $exp['dari'] ?? '' }}"
               type="month">
    </div>

    {{-- Masa Kerja Sampai --}}
    <div>
        <label class="rf-label" for="exp_{{ $i }}_sampai">Masa Kerja (Sampai)</label>
        <input class="rf-input"
               id="exp_{{ $i }}_sampai"
               name="experiences[{{ $i }}][sampai]"
               value="{{ $exp['sampai'] ?? '' }}"
               type="month"
               {{ !empty($exp['masih_bekerja']) ? 'disabled style="opacity:.4;cursor:not-allowed;"' : '' }}>
        <label class="rf-still-working mt-2">
            <input type="checkbox"
                   name="experiences[{{ $i }}][masih_bekerja]"
                   value="1"
                   onchange="toggleSampai(this, '{{ $i }}')"
                   {{ !empty($exp['masih_bekerja']) ? 'checked' : '' }}>
            Masih bekerja di sini
        </label>
    </div>

    {{-- Jabatan --}}
    <div>
        <label class="rf-label" for="exp_{{ $i }}_jabatan">Jabatan</label>
        <input class="rf-input"
               id="exp_{{ $i }}_jabatan"
               name="experiences[{{ $i }}][jabatan]"
               value="{{ $exp['jabatan'] ?? '' }}"
               type="text" placeholder="Jabatan / posisi">
    </div>

    {{-- Bagian / Departemen --}}
    <div>
        <label class="rf-label" for="exp_{{ $i }}_departemen">Bagian / Departemen</label>
        <input class="rf-input"
               id="exp_{{ $i }}_departemen"
               name="experiences[{{ $i }}][departemen]"
               value="{{ $exp['departemen'] ?? '' }}"
               type="text" placeholder="Departemen">
    </div>

    {{-- Alasan Keluar --}}
    <div class="md:col-span-2">
        <label class="rf-label" for="exp_{{ $i }}_alasan">Alasan Keluar</label>
        <textarea class="rf-textarea"
                  id="exp_{{ $i }}_alasan"
                  name="experiences[{{ $i }}][alasan]"
                  rows="3"
                  placeholder="Tuliskan alasan keluar dari perusahaan ini...">{{ $exp['alasan'] ?? '' }}</textarea>
    </div>

</div>
