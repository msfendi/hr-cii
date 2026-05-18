{{--
    Partial : registration/partials/_anak-fields.blade.php
    Usage   : @include('registration.partials._anak-fields', ['i' => $i, 'a' => $a])
    Variables:
    - $i : int   — index (0-based)
    - $a : array — data dari old('anak.$i') atau []
--}}

<div class="family-card-header">
    <span class="family-card-label">Anak ke-{{ $i + 1 }}</span>
    <button type="button" class="rf-delete-btn" onclick="removeCard(this,'anak')">
        <span class="material-symbols-outlined">delete</span> Hapus
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div class="md:col-span-2">
        <label class="rf-label" for="anak_{{ $i }}_nama">Nama Lengkap</label>
        <input class="rf-input @error("anak.{$i}.nama") border-error @enderror"
               id="anak_{{ $i }}_nama" name="anak[{{ $i }}][nama]"
               value="{{ $a['nama'] ?? '' }}" type="text" placeholder="Nama sesuai akta kelahiran">
        @error("anak.{$i}.nama")
            <p class="rf-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="rf-label" for="anak_{{ $i }}_tempat_lahir">Tempat Lahir</label>
        <input class="rf-input" id="anak_{{ $i }}_tempat_lahir"
               name="anak[{{ $i }}][tempat_lahir]"
               value="{{ $a['tempat_lahir'] ?? '' }}" type="text" placeholder="Contoh: Jakarta">
    </div>

    <div>
        <label class="rf-label" for="anak_{{ $i }}_tgl_lahir">Tanggal Lahir</label>
        <input class="rf-input" id="anak_{{ $i }}_tgl_lahir"
               name="anak[{{ $i }}][tgl_lahir]"
               value="{{ $a['tgl_lahir'] ?? '' }}" type="date">
    </div>

    <div>
        <label class="rf-label" for="anak_{{ $i }}_gender">Gender</label>
        <div class="relative">
            <select class="rf-select" id="anak_{{ $i }}_gender" name="anak[{{ $i }}][gender]">
                <option value="" disabled selected>Pilih Gender</option>
                @foreach(['Laki-laki','Perempuan'] as $g)
                    <option value="{{ $g }}" {{ ($a['gender'] ?? '') === $g ? 'selected' : '' }}>{{ $g }}</option>
                @endforeach
            </select>
            <div class="rf-icon-suffix">
                <span class="material-symbols-outlined" style="font-size:1rem;">expand_more</span>
            </div>
        </div>
    </div>

    <div>
        <label class="rf-label" for="anak_{{ $i }}_pendidikan">Pendidikan / Sekolah</label>
        <input class="rf-input" id="anak_{{ $i }}_pendidikan"
               name="anak[{{ $i }}][pendidikan]"
               value="{{ $a['pendidikan'] ?? '' }}" type="text" placeholder="Nama instansi pendidikan">
    </div>

    <div class="md:col-span-2">
        <label class="rf-label" for="anak_{{ $i }}_status">Status / Pekerjaan</label>
        <input class="rf-input" id="anak_{{ $i }}_status"
               name="anak[{{ $i }}][status]"
               value="{{ $a['status'] ?? '' }}" type="text"
               placeholder="Contoh: Pelajar / Mahasiswa / Belum Bekerja">
    </div>

</div>
