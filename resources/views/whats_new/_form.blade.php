{{--
    Form bersama untuk tambah & edit banner What's New.
    Variabel: $item (WhatsNewItem), $aksi (URL simpan), $metode (POST/PUT),
              $ruteTersedia (array nama route untuk datalist tombol).
--}}
<div class="row">
    <div class="col-lg-7">
        <div class="form-group">
            <label class="font-weight-bold">Judul <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                   maxlength="150" required value="{{ old('title', $item->title) }}"
                   placeholder="Contoh: Slip gaji periode November sudah bisa diunduh">
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label class="font-weight-bold">Penjelasan singkat</label>
            <textarea name="summary" rows="3" maxlength="1000"
                      class="form-control @error('summary') is-invalid @enderror"
                      placeholder="Satu sampai tiga kalimat: apa yang baru dan apa yang perlu dilakukan karyawan.">{{ old('summary', $item->summary) }}</textarea>
            <small class="text-muted">Maksimal 1000 karakter. Tampil di bawah judul di dalam modal.</small>
            @error('summary') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-lg-5">
        <div class="form-group">
            <label class="font-weight-bold">Jenis media</label>
            <select name="media_type" id="wn-media-type" class="form-control">
                @foreach (['image' => 'Gambar', 'video' => 'Video', 'none' => 'Tanpa media (teks saja)'] as $nilai => $label)
                    <option value="{{ $nilai }}" @selected(old('media_type', $item->media_type ?: 'image') === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group" id="wn-berkas">
            <label class="font-weight-bold">Unggah berkas</label>
            <input type="file" name="media_file" id="wn-media-file"
                   class="form-control-file @error('media_file') is-invalid @enderror"
                   accept="image/*">
            <small class="text-muted d-block mt-1">
                Gambar: JPG/PNG/WEBP (maks 5 MB per berkas, batas server 50 MB).
                Video: MP4/WEBM (maks 50 MB).
            </small>
            @error('media_file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

            @if ($item->media_path)
                <div class="custom-control custom-checkbox mt-2">
                    <input type="checkbox" class="custom-control-input" id="wn-hapus-media" name="hapus_media" value="1">
                    <label class="custom-control-label small" for="wn-hapus-media">
                        Hapus berkas yang sekarang ({{ basename($item->media_path) }})
                    </label>
                </div>
            @endif
        </div>

        <div class="form-group">
            <label class="font-weight-bold">Atau URL media</label>
            <input type="url" name="media_url" class="form-control @error('media_url') is-invalid @enderror"
                   maxlength="500" value="{{ old('media_url', $item->media_url) }}"
                   placeholder="https://www.youtube.com/watch?v=... atau https://.../video.mp4">
            <small class="text-muted">YouTube/Vimeo otomatis diputar sebagai embed.</small>
            @error('media_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<hr>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label class="font-weight-bold">Label tombol <span class="text-muted">(opsional)</span></label>
            <input type="text" name="button_label" class="form-control @error('button_label') is-invalid @enderror"
                   maxlength="60" value="{{ old('button_label', $item->button_label) }}"
                   placeholder="Lihat slip gaji">
            @error('button_label') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label class="font-weight-bold">Route tujuan tombol</label>
            <input type="text" name="button_route" list="wn-rute" class="form-control @error('button_route') is-invalid @enderror"
                   maxlength="150" value="{{ old('button_route', $item->button_route) }}"
                   placeholder="hris_v2.biodata">
            <datalist id="wn-rute">
                @foreach (($ruteTersedia ?? []) as $rute)
                    <option value="{{ $rute }}"></option>
                @endforeach
            </datalist>
            <small class="text-muted">Nama route Laravel, tanpa <code>/</code>. Route di luar daftar akan ditolak.</small>
            @error('button_route') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label class="font-weight-bold">Atau URL tombol</label>
            <input type="url" name="button_url" class="form-control @error('button_url') is-invalid @enderror"
                   maxlength="500" value="{{ old('button_url', $item->button_url) }}"
                   placeholder="https://intranet.chutex.id/pengumuman">
            @error('button_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <small class="text-muted">Dipakai bila route kosong. Isi label + salah satu tujuan.</small>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="form-group">
            <label class="font-weight-bold">Urutan tampil</label>
            <input type="number" name="sort_order" min="0" max="999" class="form-control"
                   value="{{ old('sort_order', $item->sort_order ?? 0) }}">
            <small class="text-muted">Angka lebih besar = lebih baru, tampil lebih dulu di modal.</small>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label class="font-weight-bold">Mulai tampil</label>
                <input type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
                       value="{{ old('starts_at', optional($item->starts_at)->format('Y-m-d\TH:i')) }}">
                @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-group col-md-6">
                <label class="font-weight-bold">Selesai tampil</label>
                <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror"
                       value="{{ old('ends_at', optional($item->ends_at)->format('Y-m-d\TH:i')) }}">
                @error('ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <small class="text-muted d-block mt-n2 mb-3">Kosongkan keduanya bila banner tampil terus.</small>

        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="wn-aktif" name="is_active" value="1"
                   @checked(old('is_active', $item->is_active ?? true))>
            <label class="custom-control-label font-weight-bold" for="wn-aktif">Aktif</label>
        </div>
    </div>
</div>

<div class="mt-4 d-flex align-items-center">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Simpan
    </button>
    <a href="{{ route('admin.whats-new.index') }}" class="btn btn-secondary ml-2">Batal</a>
</div>

@push('scripts')
<script>
    (function () {
        var tipe = document.getElementById('wn-media-type');
        var berkas = document.getElementById('wn-media-file');
        var blok = document.getElementById('wn-berkas');
        if (!tipe || !berkas || !blok) return;

        function sesuaikan() {
            var nilai = tipe.value;
            blok.style.display = nilai === 'none' ? 'none' : '';
            berkas.setAttribute('accept', nilai === 'video' ? 'video/*' : 'image/*');
        }

        tipe.addEventListener('change', sesuaikan);
        sesuaikan();
    })();
</script>
@endpush