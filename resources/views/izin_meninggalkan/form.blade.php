<!DOCTYPE html>
<html lang="id">
@include('layout.header')
@include('work_assignment._styles')

<style>
    .im-wrap {
        padding: 1.1rem 1.25rem 1.35rem;
    }

    .im-field {
        margin-bottom: 1rem;
    }

    .im-field .wa-label {
        display: block;
        margin-bottom: .3rem;
    }

    .im-error {
        color: #b02a37;
        font-size: .78rem;
        margin-top: .25rem;
    }

    .im-hint {
        font-size: .76rem;
        color: #71809a;
        margin-top: .2rem;
    }

    .im-range {
        display: flex;
        align-items: flex-end;
        gap: .6rem;
        flex-wrap: wrap;
    }

    .im-range > div { flex: 1 1 140px; }

    .im-range .im-sd {
        flex: 0 0 auto;
        font-weight: 700;
        color: #71809a;
        padding-bottom: .55rem;
    }

    .im-locked {
        background: #f4f6fa;
        border: 1px dashed #cfd9e8;
        border-radius: .5rem;
        padding: .55rem .7rem;
        font-size: .82rem;
        color: #5b6a82;
    }
</style>

<body>

<div class="wa-shell py-3">

    @include('sweetalert::alert')

    <div class="wa-card">

        <div class="wa-head">
            <div class="wa-head-user">
                <div class="emp-avatar">{{ strtoupper(substr($employee['name'] ?? '?', 0, 1)) }}</div>
                <div>
                    <div class="emp-name">{{ $employee['name'] }}</div>
                    <div class="emp-npk">{{ $employee['npk'] }}</div>
                </div>
            </div>
            <div class="wa-head-actions">
                <a href="{{ route('izin-meninggalkan.dashboard') }}" class="btn btn-sm btn-outline-light rounded-pill">
                    <i class="fas fa-arrow-left mr-1"></i>Kembali
                </a>
            </div>
        </div>

        <div class="im-wrap">

            <h4 style="font-size:1rem;font-weight:800;color:#1b3a6b;margin:0 0 .2rem;">
                Form Izin Meninggalkan Tugas / Pekerjaan
            </h4>
            <div class="im-hint" style="margin-bottom:1.1rem;">
                No. Dok CII.FM.HR.02.06 &middot; isi data di bawah, sisanya otomatis dari data karyawan.
            </div>

            {{-- Data karyawan (otomatis) --}}
            <div class="emp-chips" style="margin-bottom:1.15rem;">
                <span class="emp-chip">Bagian: <strong>{{ $employee['bagian'] ?: '-' }}</strong></span>
                <span class="emp-chip">Departemen: <strong>{{ $employee['jabatan'] ?: '-' }}</strong></span>
                <span class="emp-chip">Section: <strong>{{ $employee['section'] ?: '-' }}</strong></span>
            </div>

            <form method="POST" action="{{ route('izin-meninggalkan.submit') }}">
                @csrf

                {{-- 3. Tanggal --}}
                <div class="im-field">
                    <label class="wa-label" for="tanggal">Tanggal</label>
                    <input type="date" id="tanggal" name="tanggal"
                           class="wa-input @error('tanggal') is-invalid @enderror"
                           value="{{ old('tanggal', now()->format('Y-m-d')) }}" required>
                    @error('tanggal')<div class="im-error">{{ $message }}</div>@enderror
                </div>

                {{-- 4. Jam meninggalkan tugas / keadaan kembali (S/D) --}}
                <div class="im-field">
                    <label class="wa-label">Jam meninggalkan tugas &mdash; keadaan kembali</label>
                    <div class="im-range">
                        <div>
                            <input type="time" name="jam_keluar"
                                   class="wa-input @error('jam_keluar') is-invalid @enderror"
                                   value="{{ old('jam_keluar') }}" required>
                            <div class="im-hint">Jam keluar</div>
                            @error('jam_keluar')<div class="im-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="im-sd">S/D</div>
                        <div>
                            <input type="time" name="rencana_kembali"
                                   class="wa-input @error('rencana_kembali') is-invalid @enderror"
                                   value="{{ old('rencana_kembali') }}">
                            <div class="im-hint">Rencana kembali</div>
                            @error('rencana_kembali')<div class="im-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                {{-- 5. Jam kembali aktual --}}
                <div class="im-field">
                    <label class="wa-label">Jam kembali</label>
                    <div class="im-locked">
                        <i class="fas fa-lock mr-1"></i>
                        Diisi otomatis oleh petugas security saat Anda kembali (scan QR).
                    </div>
                </div>

                {{-- 6. Alasan --}}
                <div class="im-field">
                    <label class="wa-label" for="alasan">Alasan</label>
                    <textarea id="alasan" name="alasan" rows="3"
                              class="wa-textarea @error('alasan') is-invalid @enderror"
                              placeholder="Contoh: mengantar orang tua berobat"
                              required>{{ old('alasan') }}</textarea>
                    @error('alasan')<div class="im-error">{{ $message }}</div>@enderror
                </div>

                {{-- Rantai approval --}}
                <div class="wa-section-title" style="margin-bottom:.5rem;">Alur Persetujuan</div>

                @if (empty($chain))
                    <div class="wa-note" style="background:#fff8e6;border-color:#f0dfae;color:#7a5f10;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Rantai approval untuk departemen Anda belum disetel. Hubungi HR sebelum mengajukan.
                    </div>
                @else
                    <div class="flow">
                        @foreach ($chain as $i => $step)
                            @if ($i > 0)
                                <div class="flow-arrow"><i class="fas fa-chevron-right"></i></div>
                            @endif
                            <div class="flow-step">
                                <div>
                                    <span class="lv">{{ $step['level'] }}</span><span class="role">{{ $step['role_label'] }}</span>
                                </div>
                                <div class="who">{{ $step['name'] }}</div>
                                <div class="npk">{{ $step['npk'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary rounded-pill font-weight-bold px-4"
                            @disabled(empty($chain))>
                        <i class="fas fa-paper-plane mr-1"></i>Ajukan
                    </button>
                    <a href="{{ route('izin-meninggalkan.dashboard') }}"
                       class="btn btn-link text-muted">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('layout.footerscript')

</body>
</html>