{{--
    Partial : registration/partials/_saudara-fields.blade.php
    Usage   : @include('registration.partials._saudara-fields', ['i' => $i, 's' => $s])
    Variables:
    - $i : int   — index (0-based)
    - $s : array — data dari old('saudara.$i') atau []
--}}

<div class="family-card-header">
    <span class="family-card-label">Saudara ke-{{ $i + 1 }}</span>
    <button type="button" class="rf-delete-btn" onclick="removeCard(this,'saudara')">
        <span class="material-symbols-outlined">delete</span> Hapus
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div class="md:col-span-2">
        <label class="rf-label" for="saudara_{{ $i }}_nama">Nama Lengkap</label>
        <input class="rf-input @error("saudara.{$i}.nama") border-error @enderror"
               id="saudara_{{ $i }}_nama" name="saudara[{{ $i }}][nama]"
               value="{{ $s['nama'] ?? '' }}" type="text" placeholder="Nama sesuai KTP">
        @error("saudara.{$i}.nama")
            <p class="rf-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="rf-label" for="saudara_{{ $i }}_tgl_lahir">Tanggal Lahir</label>
        <input class="rf-input" id="saudara_{{ $i }}_tgl_lahir"
               name="saudara[{{ $i }}][tgl_lahir]"
               value="{{ $s['tgl_lahir'] ?? '' }}" type="date">
    </div>

    <div>
        <label class="rf-label" for="saudara_{{ $i }}_gender">Gender</label>
        <div class="relative">
            <select class="rf-select" id="saudara_{{ $i }}_gender" name="saudara[{{ $i }}][gender]">
                <option value="" disabled selected>Pilih Gender</option>
                @foreach(['Laki-laki','Perempuan'] as $g)
                    <option value="{{ $g }}" {{ ($s['gender'] ?? '') === $g ? 'selected' : '' }}>{{ $g }}</option>
                @endforeach
            </select>
            <div class="rf-icon-suffix">
                <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
            </div>
        </div>
    </div>

    <div>
        <label class="rf-label" for="saudara_{{ $i }}_pendidikan">Pendidikan</label>
        <div class="relative">
            <select class="rf-select" id="saudara_{{ $i }}_pendidikan" name="saudara[{{ $i }}][pendidikan]">
                <option value="" disabled selected>Pilih Pendidikan</option>
                @foreach(['SD','SMP','SMA/SMK','D3','S1','S2','S3'] as $p)
                    <option value="{{ $p }}" {{ ($s['pendidikan'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
            <div class="rf-icon-suffix">
                <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
            </div>
        </div>
    </div>

    <div>
        <label class="rf-label" for="saudara_{{ $i }}_pekerjaan">Pekerjaan</label>
        <input class="rf-input" id="saudara_{{ $i }}_pekerjaan"
               name="saudara[{{ $i }}][pekerjaan]"
               value="{{ $s['pekerjaan'] ?? '' }}" type="text" placeholder="Pekerjaan saat ini">
    </div>

</div>
