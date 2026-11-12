@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h3 mb-1 text-gray-800">Pengaturan Presensi Wajah</h1>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Atur window jam presensi, hari kerja, dan izin akses per karyawan.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('face.attendance.admin.index') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-users-cog mr-1"></i> Rekap Semua Karyawan
            </a>
            {{-- PERBAIKAN (2026-11-03): tombol "Pengaturan Card Menu"
                 dihapus dari halaman ini sesuai permintaan. Pengaturan
                 card menu HRIS kini diakses dari menunya sendiri, bukan
                 dari Pengaturan Presensi Wajah. --}}
            <a href="{{ route('face-recognition.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Wajah
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="row">
        <!-- Pengaturan Window & Hari Kerja -->
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-clock mr-1"></i> Window Jam Presensi &amp; Hari Kerja
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('face.attendance.settings.update-window') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="clock_in_before_minutes" class="font-weight-bold small">
                                        Menit sebelum jam kerja mulai (Clock In)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="clock_in_before_minutes"
                                            name="clock_in_before_minutes" min="0" max="240" step="1"
                                            value="{{ old('clock_in_before_minutes', $setting->clock_in_before_minutes) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">menit</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Contoh: 15 berarti Clock In dibuka pukul 07:45 untuk shift 08:00.
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="clock_in_allowance_minutes" class="font-weight-bold small">
                                        Allowance window Clock In
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="clock_in_allowance_minutes"
                                            name="clock_in_allowance_minutes" min="0" max="120" step="1"
                                            value="{{ old('clock_in_allowance_minutes', $setting->allowanceMinutes()) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">menit</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Kelonggaran SETELAH jam kerja mulai. Contoh: window normal 07:45 - 08:00,
                                        dengan allowance 5 menit menjadi <strong>07:45 - 08:05</strong>.
                                        Isi 0 bila tidak ingin ada kelonggaran.
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="clock_out_after_minutes" class="font-weight-bold small">
                                        Menit setelah jam kerja selesai (Clock Out)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="clock_out_after_minutes"
                                            name="clock_out_after_minutes" min="0" max="240" step="1"
                                            value="{{ old('clock_out_after_minutes', $setting->clock_out_after_minutes) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">menit</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Contoh: 15 berarti Clock Out ditutup pukul 17:15 untuk shift 17:00.
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="clock_out_allowance_minutes" class="font-weight-bold small">
                                        Allowance window Clock Out
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="clock_out_allowance_minutes"
                                            name="clock_out_allowance_minutes" min="0" max="120" step="1"
                                            value="{{ old('clock_out_allowance_minutes', $setting->allowanceOutMinutes()) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">menit</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Kelonggaran TAMBAHAN setelah batas Clock Out.
                                        Contoh: after 15 + allowance 10 &rarr; window Clock Out
                                        <strong>17:00 - 17:25</strong>. Dihitung setelah
                                        &ldquo;menit setelah jam kerja selesai&rdquo;.
                                        Isi 0 bila tidak ingin ada kelonggaran.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <p class="font-weight-bold small text-gray-700 mb-2">Hari Kerja Presensi Wajah</p>

                        <div class="custom-control custom-switch mb-2">
                            <input type="hidden" name="is_saturday_enabled" value="0">
                            <input class="custom-control-input" type="checkbox" id="is_saturday_enabled"
                                name="is_saturday_enabled" value="1"
                                {{ old('is_saturday_enabled', $setting->is_saturday_enabled) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_saturday_enabled">
                                Sabtu <span class="text-muted">(bisa ON/OFF)</span>
                            </label>
                        </div>

                        <div class="custom-control custom-switch mb-2">
                            <input type="hidden" name="is_sunday_enabled" value="0">
                            <input class="custom-control-input" type="checkbox" id="is_sunday_enabled"
                                name="is_sunday_enabled" value="1"
                                {{ old('is_sunday_enabled', $setting->is_sunday_enabled) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_sunday_enabled">
                                Minggu <span class="text-muted">(bisa ON/OFF)</span>
                            </label>
                        </div>

                        <div class="custom-control custom-switch mb-3">
                            <input type="hidden" name="is_holiday_enabled" value="0">
                            <input class="custom-control-input" type="checkbox" id="is_holiday_enabled"
                                name="is_holiday_enabled" value="1"
                                {{ old('is_holiday_enabled', $setting->is_holiday_enabled) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_holiday_enabled">
                                Hari Libur <span class="text-muted">(bisa ON/OFF, tanggal dari tabel holidays)</span>
                            </label>
                        </div>

                        <div class="alert alert-secondary py-2 px-3 small mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Senin sampai Jumat <strong>selalu aktif</strong>. Hari libur mengikuti toggle di atas
                            dan tanggalnya diambil dari tabel <code>holidays</code> pada database CII.
                        </div>

                        {{-- ================================================================
                             PERBAIKAN V41 (2026-11-10): toggle "Input NPK Dulu" PINDAH
                             dari kartu "Liveness Check (Anti Titip Absen)" ke kartu
                             ini (permintaan user). Penyimpanan tetap ke tabel
                             face_recognition_settings, tapi sekarang lewat
                             updateWindow() sehingga form kartu ini utuh.

                             Field is_npk_required_enabled TIDAK lagi dikirim form
                             liveness -- hanya ada SATU sumber pengaturan (sumber
                             tunggal), jadi tidak bisa berubah dari dua tempat
                             sekaligus.
                             ================================================================ --}}
                        <hr class="my-3">

                        <div class="d-flex align-items-center mb-2">
                            <p class="font-weight-bold small text-gray-700 mb-0">
                                <i class="fas fa-id-card mr-1 text-primary"></i> Alur Presensi Wajah
                            </p>
                        </div>

                        <div class="border rounded p-3 bg-light">
                            <input type="hidden" name="is_npk_required_enabled" value="0">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_npk_required_enabled"
                                    name="is_npk_required_enabled" value="1"
                                    {{ old('is_npk_required_enabled', $liveness->is_npk_required_enabled ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold small" for="is_npk_required_enabled">
                                    Input NPK Dulu <span class="text-muted">(/face-attendance/login)</span>
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1 mb-0">
                                Jika <strong>ON</strong>, karyawan wajib isi NPK terlebih dahulu sebelum kamera
                                wajah aktif. Jika <strong>OFF</strong>, wajah langsung terdeteksi tanpa NPK.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm mt-3">
                            <i class="fas fa-save mr-1"></i> Simpan Pengaturan Window
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Hari Libur -->
        {{-- RAPI: card memakai `h-100` agar mengikuti tinggi kolom (rata dengan
             kartu Window di sebelah), TAPI TIDAK memakai max-height tetap.
             Tabel memakai DataTables (pencarian/sortir/paginasi) sehingga
             area kosong di bawah tabel tidak muncul: card mengikuti isi
             wrapper DataTables, bukan tinggi tetap. --}}
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calendar-times mr-1"></i> Daftar Hari Libur (tabel <code>holidays</code>)
                    </h6>
                </div>
                <div class="card-body p-3 d-flex flex-column">
                    @if($holidays->count() > 0)
                        {{-- DATATABLES: pencarian, sortir, dan paginasi untuk tabel holidays.
                             Card-body p-3 memberi jarak dari tepi card; CSS scoped
                             #holidaysTable_wrapper memberi jarak antar tabel dan controls. --}}
                        <div class="table-responsive flex-grow-1">
                            <table class="table table-sm table-striped mb-0" id="holidaysTable" width="100%">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama Hari Libur</th>
                                        <th class="text-center">Jenis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($holidays as $holiday)
                                        <tr>
                                            <td class="text-nowrap"
                                                data-order="{{ $holiday->holiday_date ? \Carbon\Carbon::parse($holiday->holiday_date)->format('Y-m-d') : '' }}">
                                                {{ $holiday->holiday_date ? \Carbon\Carbon::parse($holiday->holiday_date)->format('d-m-Y') : '-' }}
                                            </td>
                                            <td>{{ $holiday->name ?: '-' }}</td>
                                            <td class="text-center">
                                                @if($holiday->is_national)
                                                    <span class="badge badge-danger">Nasional</span>
                                                @else
                                                    <span class="badge badge-secondary">Internal</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                            <p class="mb-0">Data hari libur tidak ditemukan atau tabel <code>holidays</code> belum tersedia.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Pengaturan Liveness Check (dipindah dari menu Face Recognition) -->
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fingerprint mr-1"></i> Pengaturan Liveness Check (Anti Titip Absen)
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Liveness Check memastikan yang di depan kamera benar-benar manusia hidup,
                        bukan foto atau layar HP. Karyawan diminta <strong>berkedip</strong> dan
                        <strong>menoleh (geleng)</strong> sebelum wajah dicocokkan.
                        Bila <strong>Master OFF</strong>, semua halaman mencocokkan wajah tanpa
                        kedip/geleng.
                    </p>

                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Catatan keamanan:</strong> mematikan liveness membuat sistem lebih
                        mudah ditipu dengan foto wajah karyawan.
                    </div>

                    @if(!$livenessTersedia)
                        <div class="alert alert-danger py-2 px-3 small mb-3">
                            <i class="fas fa-database mr-1"></i>
                            Tabel/kolom pengaturan liveness belum tersedia di database.
                            Jalankan <code>php artisan migrate</code> lalu muat ulang halaman ini.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('face.attendance.settings.update-liveness') }}">
                        @csrf

                        <div class="border rounded p-3 mb-3 bg-white">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_liveness_enabled" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_liveness_enabled"
                                    name="is_liveness_enabled" value="1"
                                    {{ ($liveness->is_liveness_enabled ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold text-gray-800" for="is_liveness_enabled">
                                    Aktifkan Liveness Check (Global)
                                </label>
                            </div>
                            <small class="text-muted d-block ml-4">
                                Switch utama. Jika OFF, semua switch per menu di bawah tidak berlaku.
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="liveness_presensi_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="liveness_presensi_enabled"
                                            name="liveness_presensi_enabled" value="1"
                                            {{ ($liveness->liveness_presensi_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="liveness_presensi_enabled">
                                            Presensi Wajah
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/face-attendance</small>
                                </div>
                            </div>

                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="liveness_login_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="liveness_login_enabled"
                                            name="liveness_login_enabled" value="1"
                                            {{ ($liveness->liveness_login_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="liveness_login_enabled">
                                            Login Presensi Wajah
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/face-attendance/login</small>
                                </div>
                            </div>

                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="liveness_slip_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="liveness_slip_enabled"
                                            name="liveness_slip_enabled" value="1"
                                            {{ ($liveness->liveness_slip_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="liveness_slip_enabled">
                                            Slip Gaji
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/employee-payroll</small>
                                </div>
                            </div>

                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="liveness_cuti_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="liveness_cuti_enabled"
                                            name="liveness_cuti_enabled" value="1"
                                            {{ ($liveness->liveness_cuti_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="liveness_cuti_enabled">
                                            Pengajuan Cuti
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/pengajuan-cuti</small>
                                </div>
                            </div>

                            {{-- PERBAIKAN V31.3d (2026-11-04): sakelar untuk
                                 registrasi mandiri (/self-register-face).
                                 Dipisah karena risikonya berbeda: di sini
                                 liveness mencegah orang mendaftarkan wajah
                                 ORANG LAIN, bukan mencegah titip absen. --}}
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="liveness_registrasi_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="liveness_registrasi_enabled"
                                            name="liveness_registrasi_enabled" value="1"
                                            {{ ($liveness->liveness_registrasi_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="liveness_registrasi_enabled">
                                            Registrasi Wajah Mandiri
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/self-register-face (Langkah 4)</small>
                                </div>
                            </div>

                            {{-- PERBAIKAN V41 (2026-11-10): toggle "Input NPK Dulu"
                                 PINDAH dari kartu ini ke kartu "Window Jam Presensi &
                                 Hari Kerja" (permintaan user). Form liveness tidak
                                 lagi mengirim field is_npk_required_enabled, dan
                                 nilainya TIDAK lagi bisa berubah dari kartu ini
                                 supaya hanya ada SATU tempat pengaturan (sumber
                                 tunggal). --}}

                            {{-- PERBAIKAN V37 (2026-11-09): blok "Mode
                                 Verifikasi Transaksi Kantin" DIHAPUS dari
                                 kartu Liveness Check. Sekarang mode itu
                                 punya kartu sendiri di halaman ini
                                 (lihat "Mode Verifikasi Transaksi Kantin"
                                 di bawah). Sumber tunggal = 1 tempat.

                                 PERBAIKAN V41 (2026-11-10): toggle "Input NPK
                                 Dulu" juga PINDAH ke kartu "Window Jam
                                 Presensi & Hari Kerja" (permintaan user).
                                 Form liveness tidak lagi mengirim field itu;
                                 nilainya sekarang diatur dari form window. --}}
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm mt-2">
                            <i class="fas fa-save mr-1"></i> Simpan Pengaturan Liveness
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ================================================================
             RANDOM CHALLENGE (ANTI-KECURANGAN) -- V1, 2026-11-09
             ================================================================
             Form TERPISAH dari form liveness di atas. Tujuannya jelas:
             logika liveness tidak boleh ikut tersentuh, dan perubahan
             random challenge tidak boleh mengubah apa pun soal liveness.
             ================================================================ --}}
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-random mr-1"></i> Random Challenge (Anti-Kecurangan)
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Sebelum wajah dicocokkan, sistem meminta gerakan <strong>acak</strong>:
                        <strong>hadap ke kiri</strong>, <strong>hadap ke kanan</strong>, atau
                        <strong>buka mulut</strong>. Karena tantangannya dipilih acak dan tidak
                        diketahui lebih dulu, foto atau rekaman video lama tidak bisa dipakai
                        untuk menipu sistem.
                    </p>

                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <strong>Catatan:</strong> fitur ini <strong>terpisah</strong> dari Liveness Check
                        dan tidak mengubah logika liveness sama sekali.
                        Mode verifikasi <strong>Transaksi Kantin</strong> TIDAK diatur di
                        kartu ini — punya <strong>kartu sendiri</strong> ("Mode Verifikasi
                        Transaksi Kantin") supaya tidak ada dua tempat untuk satu pengaturan.
                        Kartu ini tetap mengatur random challenge untuk menu
                        presensi / login / slip / cuti / registrasi.
                    </div>

                    @if(!$randomChallengeTersedia)
                        <div class="alert alert-danger py-2 px-3 small mb-3">
                            <i class="fas fa-database mr-1"></i>
                            Kolom pengaturan random challenge belum tersedia di database.
                            Jalankan <code>php artisan migrate</code> lalu muat ulang halaman ini.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('face.attendance.settings.update-random-challenge') }}">
                        @csrf

                        <div class="border rounded p-3 mb-3 bg-white">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_random_challenge_enabled" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_random_challenge_enabled"
                                    name="is_random_challenge_enabled" value="1"
                                    {{ ($liveness->is_random_challenge_enabled ?? false) ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold text-gray-800" for="is_random_challenge_enabled">
                                    Aktifkan Random Challenge (Global)
                                </label>
                            </div>
                            <small class="text-muted d-block ml-4">
                                Switch utama. Jika OFF, semua switch per menu di bawah tidak berlaku.
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="random_challenge_presensi_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="random_challenge_presensi_enabled"
                                            name="random_challenge_presensi_enabled" value="1"
                                            {{ ($liveness->random_challenge_presensi_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="random_challenge_presensi_enabled">
                                            Presensi Wajah
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/face-attendance</small>
                                </div>
                            </div>

                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="random_challenge_login_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="random_challenge_login_enabled"
                                            name="random_challenge_login_enabled" value="1"
                                            {{ ($liveness->random_challenge_login_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="random_challenge_login_enabled">
                                            Login Presensi Wajah
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/face-attendance/login</small>
                                </div>
                            </div>

                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="random_challenge_slip_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="random_challenge_slip_enabled"
                                            name="random_challenge_slip_enabled" value="1"
                                            {{ ($liveness->random_challenge_slip_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="random_challenge_slip_enabled">
                                            Slip Gaji
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/employee-payroll</small>
                                </div>
                            </div>

                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="random_challenge_cuti_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="random_challenge_cuti_enabled"
                                            name="random_challenge_cuti_enabled" value="1"
                                            {{ ($liveness->random_challenge_cuti_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="random_challenge_cuti_enabled">
                                            Pengajuan Cuti
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/pengajuan-cuti</small>
                                </div>
                            </div>

                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 bg-light h-100">
                                    <input type="hidden" name="random_challenge_registrasi_enabled" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="random_challenge_registrasi_enabled"
                                            name="random_challenge_registrasi_enabled" value="1"
                                            {{ ($liveness->random_challenge_registrasi_enabled ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold small" for="random_challenge_registrasi_enabled">
                                            Registrasi Wajah Mandiri
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">/self-register-face (Langkah 4)</small>
                                </div>
                            </div>

                            {{-- PERBAIKAN V37 (2026-11-09): blok "Transaksi
                                 Kantin" yang tadinya ada di kartu ini
                                 DIHAPUS. Mode canteen sekarang diatur lewat
                                 kartu sendiri "Mode Verifikasi Transaksi
                                 Kantin", sehingga tidak ada dua kontrol
                                 untuk satu pengaturan yang sama. --}}
                        </div>

                        <button type="submit" class="btn btn-danger btn-sm mt-2">
                            <i class="fas fa-save mr-1"></i> Simpan Pengaturan Random Challenge
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ============================================================
             PERBAIKAN V37 (2026-11-09) -- KARTU SENDIRI:
             MODE VERIFIKASI TRANSAKSI KANTIN
             ============================================================
             Permintaan user: "Mode Verifikasi Transaksi Kantin
             (/canteen-transaction) dan Transaksi Kantin digabungkan
             dalam 1 card sendiri, jangan di Pengaturan Liveness Check
             (Anti Titip Absen) dan Random Challenge (Anti-Kecurangan)".

             Jadi:
               - Mode verifikasi DIHAPUS dari kartu Liveness Check.
               - Blok "Transaksi Kantin" DIHAPUS dari kartu Random Challenge.
               - Semua kendali canteen sekarang HANYA di card ini.
             Form memakai route KHUSUS
             face.attendance.settings.update-canteen-verification-mode,
             sehingga menyimpan card ini tidak pernah menimpa setting
             liveness / random challenge global.
             ============================================================ --}}
        <div class="col-xl-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-utensils mr-1"></i> Mode Verifikasi Transaksi Kantin
                        <span class="text-muted font-weight-normal">(/canteen-transaction)</span>
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Atur bagaimana wajah karyawan diverifikasi saat scan
                        <strong>Transaksi Kantin</strong>. Pilih <strong>SALAH SATU</strong>
                        mode di bawah. Mode ini berdiri sendiri dan tidak mengubah
                        pengaturan Liveness Check maupun Random Challenge untuk
                        menu presensi / login / slip / cuti / registrasi.
                    </p>

                    @if(!($canteenModeTersedia ?? false))
                        <div class="alert alert-danger py-2 px-3 small mb-3">
                            <i class="fas fa-database mr-1"></i>
                            Kolom <code>canteen_verification_mode</code> belum tersedia di
                            database. Jalankan <code>php artisan migrate</code> lalu muat
                            ulang halaman ini.
                        </div>
                    @else
                        <form method="POST" action="{{ route('face.attendance.settings.update-canteen-verification-mode') }}">
                            @csrf

                            <div class="border rounded p-3 mb-3 bg-white">
                                <label class="font-weight-bold d-block mb-2" for="canteen_verification_mode">
                                    Mode Verifikasi Transaksi Kantin
                                </label>
                                <select name="canteen_verification_mode" id="canteen_verification_mode"
                                        class="form-control form-control-sm">
                                    <option value="liveness" {{ ($canteenVerificationMode ?? 'liveness') === 'liveness' ? 'selected' : '' }}>
                                        Liveness — karyawan diminta berkedip / menoleh
                                    </option>
                                    <option value="random_challenge" {{ ($canteenVerificationMode ?? '') === 'random_challenge' ? 'selected' : '' }}>
                                        Random Challenge — tantangan acak (hadap kiri / kanan / buka mulut)
                                    </option>
                                    <option value="none" {{ ($canteenVerificationMode ?? '') === 'none' ? 'selected' : '' }}>
                                        Tanpa Verifikasi Gerakan — wajah langsung dicocokkan
                                    </option>
                                </select>
                                <small class="text-muted d-block mt-2">
                                    Simpan ke tabel <code>face_recognition_settings</code>
                                    (kolom <code>canteen_verification_mode</code>).
                                    Kolom boolean lama
                                    (<code>liveness_canteen_enabled</code> /
                                    <code>random_challenge_canteen_enabled</code>) hanya
                                    ditulis sebagai cermin kompatibilitas.
                                </small>
                            </div>

                            <div class="border rounded p-2 bg-light mb-3">
                                <div class="small mb-1"><strong>Mode aktif saat ini</strong></div>
                                <div class="small">
                                    @if(($canteenVerificationMode ?? '') === 'random_challenge')
                                        <strong class="text-danger">Random Challenge</strong>
                                    @elseif(($canteenVerificationMode ?? '') === 'none')
                                        <strong class="text-success">Tanpa Verifikasi Gerakan</strong>
                                    @else
                                        <strong class="text-primary">Liveness</strong>
                                    @endif
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm mt-2">
                                <i class="fas fa-save mr-1"></i> Simpan Mode Verifikasi Transaksi Kantin
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============================================================
             PERBAIKAN V32 (2026-11-09) -- SETTING GEOMAP TRANSAKSI KANTIN
             ============================================================
             Permintaan user: geomap yang diperbolehkan untuk canteen
             transaction harus bisa disetting dari /face-attendance/settings,
             dan di canteen transaction HANYA boleh mengijinkan dari geomap
             yang sudah disetting di sini.

             CATATAN MIGRASI: tidak perlu migration baru. Kolom
             `require_geomap` dan `allowed_work_location_ids` sudah ada di
             tabel `canteen_settings` (migration 2026_11_09_130000).
             Nilai disimpan di tabel yang SAMA dengan /canteen-transaction/admin
             supaya keduanya tidak berbeda.
             --}}
        <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-map-marked-alt mr-2"></i>Geomap Transaksi Kantin
                        </h6>
                        {{-- PERBAIKAN V32 (2026-11-09): beri space bawah agar tidak
                             berhimpitan dengan card di bawahnya (Izin Presensi
                             Wajah per Karyawan). mb-4 pada wrapper col-12 ini
                             tidak cukup karena card di dalamnya juga punya
                             shadow; tambah spacer eksplisit per-card. --}}
                        <span class="badge badge-light border small text-muted">
                            Mode verifikasi: @if(($canteenVerificationMode ?? '') === 'random_challenge') Random Challenge @elseif(($canteenVerificationMode ?? '') === 'none') Tanpa Verifikasi @else Liveness @endif
                        </span>
                    </div>
                    <div class="card-body">
                        @if($canteenSetting === null)
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Tabel <code>canteen_settings</code> belum tersedia di database, sehingga
                                pengaturan geomap kantin belum bisa disimpan. Jalankan
                                <code>php artisan migrate</code> di server.
                            </div>
                        @else
                        <form method="POST" id="canteenGeomapForm"
                              action="{{ route('face.attendance.settings.update-canteen-geomap') }}">
                            @csrf

                            {{-- ================================================================
                                 PERBAIKAN V41 (2026-11-10): UI/UX CARD GEOMAP INTERAKTIF
                                 - Mini map + polygon digambar di dalam kartu ini (sebelumnya
                                   hanya di halaman transaksi), memakai helper
                                   extractPolygonRing() yang SAMA dengan halaman
                                   transaksi supaya yang digambar = yang dicek.
                                 - Pilih lokasi diganti checklist card yang bisa diklik
                                   (lebih jelas daripada select multiple yang hanya
                                   bisa dipilih dengan Ctrl/Cmd).
                                 - Preview langsung: polygon terpilih disorot di peta.
                                 - Fail-closed: polygon kosong / tidak terbaca TIDAK
                                   digambar dan TIDAK dianggap sah.
                                 ================================================================ --}}
                            @php
                                $canteenLocGeo = [];
                                foreach (($locations ?? []) as $loc) {
                                    $canteenLocGeo[] = [
                                        'id'    => (int) $loc->id,
                                        'name'  => $loc->name,
                                        'ring'  => $loc->coordinates ?? [],
                                    ];
                                }
                            @endphp

                            <div class="row">
                                <div class="col-lg-5">
                                    <div class="form-group mb-3">
                                        <div class="custom-control custom-switch">
                                            <input type="hidden" name="require_geomap" value="0">
                                            <input type="checkbox" class="custom-control-input" id="canteen_require_geomap"
                                                name="require_geomap" value="1"
                                                {{ ($canteenRequireGeomap ?? false) ? 'checked' : '' }}>
                                            <label class="custom-control-label font-weight-bold" for="canteen_require_geomap">
                                                Wajib berada di dalam geomap yang diperbolehkan
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            Jika dinonaktifkan, semua karyawan bisa transaksi kantin dari mana saja
                                            (tetap harus lolos validasi NPK dan verifikasi wajah sesuai mode).
                                            Jika diaktifkan, hanya lokasi yang dipilih di bawah yang boleh dipakai.
                                        </small>
                                    </div>

                                    <div class="form-group mb-0">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="font-weight-bold mb-0">Geomap yang diperbolehkan</label>
                                            <span class="badge badge-primary" id="canteenGeomapCountBadge">0 dipilih</span>
                                        </div>

                                        @if(count($locations ?? []) === 0)
                                            <div class="alert alert-info mb-0">
                                                Belum ada data geomap presensi. Tambahkan lokasi di menu
                                                <strong>Face Attendance &rarr; Lokasi</strong> terlebih dahulu.
                                            </div>
                                        @else
                                            <div class="border rounded overflow" style="max-height: 320px; overflow-y: auto;">
                                                @foreach($locations as $loc)
                                                    @php
                                                        $isChecked = in_array((int) $loc->id, array_map('intval', $canteenAllowedLocationIds ?? []));
                                                        $hasPoly   = count($loc->coordinates ?? []) >= 3;
                                                    @endphp
                                                    <label class="d-flex align-items-start p-2 mb-0 border-bottom canteen-loc-item
                                                        {{ $isChecked ? 'loc-checked' : '' }}"
                                                        style="cursor: pointer; border-color: #eef0f4 !important;">
                                                        <input type="checkbox" class="custom-control-input mt-1 mr-2"
                                                            name="allowed_work_location_ids[]" value="{{ $loc->id }}"
                                                            data-loc-id="{{ $loc->id }}"
                                                            data-loc-has-poly="{{ $hasPoly ? '1' : '0' }}"
                                                            {{ $isChecked ? 'checked' : '' }}>
                                                        <span class="flex-grow-1">
                                                            <span class="d-block font-weight-bold small">{{ $loc->name }}</span>
                                                            <span class="d-inline-flex align-items-center mt-1">
                                                                @if($hasPoly)
                                                                    <span class="badge badge-success badge-pill" style="font-size: .62rem;">
                                                                        <i class="fas fa-check mr-1"></i>Polygon tersedia
                                                                    </span>
                                                                @else
                                                                    <span class="badge badge-warning badge-pill" style="font-size: .62rem;">
                                                                        <i class="fas fa-exclamation-triangle mr-1"></i>Tanpa polygon
                                                                    </span>
                                                                @endif
                                                                {{-- Penanda jelas bahwa lokasi ini SUDAH DIPILIH.
                                                                     Muncul/hilang via CSS berdasarkan state
                                                                     checkbox, jadi selalu sinkron. --}}
                                                                <span class="badge badge-pill loc-selected-badge ml-1"
                                                                      style="font-size: .62rem; background:#1cc88a; color:#fff;">
                                                                    <i class="fas fa-check-double mr-1"></i>Dipilih
                                                                </span>
                                                            </span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                Kotak centang di sebelah kiri. Biarkan kosong = tidak ada lokasi
                                                yang diizinkan. Lokasi tanpa polygon akan ditolak saat transaksi
                                                (fail-closed).
                                            </small>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-lg-7">
                                    {{-- Mini map: polygon area kantin. --}}
                                    <div class="border rounded overflow bg-white">
                                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"
                                            style="background: #f8fafc;">
                                            <span class="font-weight-bold small text-gray-700">
                                                <i class="fas fa-map-marked-alt mr-1 text-primary"></i>
                                                Peta Area Transaksi Kantin
                                            </span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                id="canteenGeomapFitBtn" title="Tampilkan seluruh area">
                                                <i class="fas fa-expand-arrows-alt mr-1"></i>Fit Area
                                            </button>
                                        </div>
                                        <div id="canteenGeomapMiniMap" style="height: 320px; width: 100%; background: #eef1f7;"></div>
                                        <div class="px-3 py-2 border-top" style="background: #f8fafc;">
                                            <div class="d-flex flex-wrap small text-muted">
                                                <span class="mr-3 mb-1">
                                                    <span style="display:inline-block;width:12px;height:12px;background:#2563eb;border-radius:2px;vertical-align:middle;"></span>
                                                    Area GPS diizinkan
                                                </span>
                                                <span class="mr-3 mb-1">
                                                    <span style="display:inline-block;width:12px;height:12px;background:#94a3b8;border-radius:2px;vertical-align:middle;"></span>
                                                    Tidak diizinkan
                                                </span>
                                                <span class="mb-1">
                                                    <span style="display:inline-block;width:12px;height:12px;background:#22c55e;border-radius:50%;vertical-align:middle;"></span>
                                                    Terpilih
                                                </span>
                                            </div>
                                            <div class="small mt-1" id="canteenGeomapHint">
                                                Klik lokasi di daftar untuk melihat areanya di peta.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="canteenGeomapLocationsJson"
                                value="{{ json_encode($canteenLocGeo) }}">

                            @if(($canteenRequireGeomap ?? false) && count($canteenAllowedLocationIds ?? []) === 0)
                                <div class="alert alert-danger">
                                    <i class="fas fa-ban mr-2"></i>
                                    <strong>Perhatian:</strong> geomap wajib diaktifkan tetapi tidak ada lokasi
                                    yang dipilih. Dengan kondisi ini, <strong>semua transaksi kantin akan
                                    ditolak</strong> sampai ada lokasi yang dipilih. Ini disengaja
                                    (fail-closed) supaya transaksi tidak lolos tanpa validasi lokasi.
                                </div>
                            @endif

                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save mr-1"></i> Simpan Pengaturan Geomap Kantin
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- PERBAIKAN V32 (2026-11-09): spacer eksplisit antara
                 Geomap Transaksi Kantin dan Izin Presensi Wajah per
                 Karyawan. Sebelumnya kedua card col-12 menempel tanpa
                 jarak vertikal yang jelas (berhimpitan). --}}
            <div class="col-12" aria-hidden="true" style="height: 1.5rem;"></div>

            <!-- Akses Presensi per Karyawan -->
            <div class="col-12">
                <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users-cog mr-1"></i> Izin Presensi Wajah per Karyawan
                    </h6>
                    <span class="small text-muted">
                        Total karyawan terdaftar: <strong id="employeeFaceTotal">{{ $employeeFaces->count() }}</strong>
                    </span>
                </div>
                <div class="card-body p-3">
                    @if($employeeFaces->count() > 0)
                        {{-- DATATABLES: pencarian, sortir, dan paginasi.
                             Padding card-body (p-3) memberi jarak dari tepi card,
                             sedangkan .table-responsive memberi space antar tabel. --}}
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0" id="employeeFaceTable" width="100%">
                                <thead class="thead-light">
                                    <tr>
                                        <th>NPK</th>
                                        <th>Nama</th>
                                        <th class="text-center">Presensi</th>
                                        <th class="text-center">Scope Lokasi</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employeeFaces as $face)
                                        @php
                                            $scope = $face->location_scope ?: 'all';
                                            $allowed = $face->allowed_location_id_list;
                                        @endphp
                                        <tr>
                                            <td><strong>{{ $face->npk }}</strong></td>
                                            <td>{{ $face->name ?: '-' }}</td>
                                            <td class="text-center">
                                                @if($face->is_attendance_enabled)
                                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i>ON</span>
                                                @else
                                                    <span class="badge badge-secondary"><i class="fas fa-times mr-1"></i>OFF</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($scope === 'all')
                                                    <span class="badge badge-info">Semua Lokasi</span>
                                                @else
                                                    <span class="badge badge-warning">{{ count($allowed) }} Lokasi Dipilih</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-primary btn-manage-face-access"
                                                    data-id="{{ $face->id }}"
                                                    data-npk="{{ $face->npk }}"
                                                    data-name="{{ $face->name }}"
                                                    data-enabled="{{ $face->is_attendance_enabled ? '1' : '0' }}"
                                                    data-scope="{{ $scope }}"
                                                    data-allowed="{{ json_encode($allowed) }}"
                                                    data-url="{{ route('face.attendance.settings.update-employee', $face->id) }}">
                                                    <i class="fas fa-sliders-h mr-1"></i> Kelola Akses
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-user-slash fa-3x mb-3"></i>
                            <p class="mb-0">Belum ada karyawan terdaftar pada face recognition.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Kelola Akses Presensi per Karyawan -->
<div class="modal fade" id="faceAccessModal" tabindex="-1" role="dialog" aria-labelledby="faceAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-3">
                <h6 class="modal-title font-weight-bold" id="faceAccessModalLabel">
                    <i class="fas fa-sliders-h mr-1"></i> Kelola Izin Presensi Wajah
                </h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="faceAccessForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info py-2 px-3 small">
                        Karyawan: <strong id="faNpk"></strong> &mdash; <span id="faName"></span>
                    </div>

                    <div class="custom-control custom-switch mb-3">
                        <input type="hidden" name="is_attendance_enabled" id="faEnabledValue" value="0">
                        <input class="custom-control-input" type="checkbox" id="faEnabled"
                            name="is_attendance_enabled" value="1">
                        <label class="custom-control-label font-weight-bold" for="faEnabled">
                            Izinkan Presensi Wajah untuk karyawan ini
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Cakupan Lokasi Geo Map</label>
                        <div class="custom-control custom-radio mb-1">
                            <input class="custom-control-input" type="radio" id="faScopeAll" name="location_scope" value="all" checked>
                            <label class="custom-control-label" for="faScopeAll">Semua Lokasi</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input class="custom-control-input" type="radio" id="faScopeSelected" name="location_scope" value="selected">
                            <label class="custom-control-label" for="faScopeSelected">Hanya Lokasi yang Dipilih</label>
                        </div>
                    </div>

                    <div class="form-group" id="faLocationPicker" style="display:none;">
                        <label class="font-weight-bold small">Pilih Lokasi Presensi</label>
                        @if($locations->count() > 0)
                            @foreach($locations as $loc)
                                <div class="custom-control custom-checkbox mb-1">
                                    <input class="custom-control-input" type="checkbox" id="faLoc{{ $loc->id }}"
                                        name="allowed_location_ids[]" value="{{ $loc->id }}">
                                    <label class="custom-control-label" for="faLoc{{ $loc->id }}">
                                        {{ $loc->name }}
                                        @if(!$loc->is_active)
                                            <span class="badge badge-secondary ml-1">Nonaktif</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        @else
                            <div class="alert alert-warning py-2 px-3 small mb-0">
                                Belum ada lokasi presensi. Tambahkan lokasi pada menu Manage Geo Map terlebih dahulu.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('faceAccessModal');
    if (!modal) return;

    const form = document.getElementById('faceAccessForm');
    const scopeAll = document.getElementById('faScopeAll');
    const scopeSelected = document.getElementById('faScopeSelected');
    const picker = document.getElementById('faLocationPicker');
    const enabled = document.getElementById('faEnabled');
    const enabledValue = document.getElementById('faEnabledValue');

    function togglePicker() {
        picker.style.display = scopeSelected.checked ? 'block' : 'none';
    }

    scopeAll.addEventListener('change', togglePicker);
    scopeSelected.addEventListener('change', togglePicker);

    enabled.addEventListener('change', function () {
        enabledValue.value = enabled.checked ? '1' : '0';
    });

    // Fungsi ini dipanggil ulang oleh DataTables setiap kali <tbody>
    // dirender ulang (sortir / paginasi / pencarian), supaya tombol
    // "Kelola Akses" tetap punya event listener.
    window.__initFaceAccessButtons = function () {
        document.querySelectorAll('.btn-manage-face-access').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';

            btn.addEventListener('click', function () {
                const d = btn.dataset;

                form.setAttribute('action', d.url);
                document.getElementById('faNpk').textContent = d.npk || '-';
                document.getElementById('faName').textContent = d.name || '-';

                const isEnabled = d.enabled === '1';
                enabled.checked = isEnabled;
                enabledValue.value = isEnabled ? '1' : '0';

                const scope = d.scope || 'all';
                scopeAll.checked = scope !== 'selected';
                scopeSelected.checked = scope === 'selected';

                let allowed = [];
                try {
                    allowed = JSON.parse(d.allowed || '[]');
                } catch (e) {
                    allowed = [];
                }

                document.querySelectorAll('[id^="faLoc"]').forEach(function (cb) {
                    cb.checked = allowed.indexOf(parseInt(cb.value, 10)) !== -1;
                });

                togglePicker();
                $(modal).modal('show');
            });
        });
    };

    window.__initFaceAccessButtons();
})();
</script>
@endsection

@section('scripts')
<!-- Page level plugins: DataTables untuk "Izin Presensi Wajah per Karyawan" -->
<link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.bootstrap4.min.css') }}">

{{-- PERBAIKAN V41 (2026-11-10): Leaflet untuk mini map pada kartu
     "Geomap Transaksi Kantin". Dimuat defer supaya tidak memblokir
     render halaman (pola yang sama dipakai halaman transaksi). --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
    .leaflet-container { background: #eef1f7; }
    .canteen-loc-item { transition: background-color .15s ease-in-out; }
    .canteen-loc-item:hover { background-color: #f1f5f9; }
    .canteen-loc-item.is-active { background-color: #e0edff !important; box-shadow: inset 3px 0 0 #1d3f86; }
    /* PERBAIKAN V43: penanda visual jelas bahwa lokasi SUDAH DIPILIH. */
    .canteen-loc-item.loc-checked {
        background-color: #eafaf2 !important;
        box-shadow: inset 3px 0 0 #1cc88a;
    }
    .canteen-loc-item .loc-selected-badge { display: none; }
    .canteen-loc-item.loc-checked .loc-selected-badge { display: inline-flex !important; }
    #canteenGeomapMiniMap .leaflet-popup-content {
        font-size: .82rem;
        font-weight: 700;
        color: #1f2937;
    }
</style>

{{-- PERBAIKAN V32 (2026-11-09): SweetAlert2 untuk konfirmasi simpan
     Geomap Transaksi Kantin. Layout memuat sweetalert::alert, tetapi
     paket itu hanya menyertakan JS-nya bila session membawa
     alert.config / alert.delete. Halaman ini memakai Swal.fire() secara
     langsung, jadi JS dimuat eksplisit di sini. --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* Popup SweetAlert konfirmasi/hasil simpan Geomap Transaksi Kantin. */
    .swal-geomap-popup {
        border-radius: 16px !important;
        border-top: 4px solid #1d3f86 !important;
        box-shadow: 0 20px 55px rgba(0, 0, 0, .45) !important;
    }
    .swal-geomap-popup .swal2-title {
        font-size: 1.18rem !important;
        font-weight: 800 !important;
        color: #1f2937 !important;
    }
    .swal-geomap-popup .swal2-html-container {
        text-align: left !important;
    }
    .swal-geomap-popup .swal2-confirm {
        background: #1d3f86 !important;
        border-radius: 9px !important;
        font-weight: 700 !important;
        font-size: .9rem !important;
        padding: .55rem 1.4rem !important;
    }
    .swal-geomap-popup .swal2-cancel {
        background: #e5e7eb !important;
        color: #374151 !important;
        border-radius: 9px !important;
        font-weight: 700 !important;
        font-size: .9rem !important;
        padding: .55rem 1.4rem !important;
    }
</style>
<style>
    /* Padding DataTables di dalam card pada halaman ini.
       Selector memakai ID tabel (scoped) agar tidak memengaruhi halaman lain.
       Berlaku untuk: holidaysTable dan employeeFaceTable. */
    #holidaysTable_wrapper .dataTables_length,
    #holidaysTable_wrapper .dataTables_filter,
    #employeeFaceTable_wrapper .dataTables_length,
    #employeeFaceTable_wrapper .dataTables_filter {
        padding: 0 0 .75rem 0;
    }

    /* Jarak antar isi tabel dan tepi card. */
    #holidaysTable_wrapper table.dataTable,
    #employeeFaceTable_wrapper table.dataTable {
        margin-top: 0 !important;
        margin-bottom: .5rem !important;
    }

    /* Sel tabel diberi jarak kiri-kanan agar tidak menempel tepi. */
    #holidaysTable_wrapper table.dataTable thead th,
    #holidaysTable_wrapper table.dataTable tbody td,
    #employeeFaceTable_wrapper table.dataTable thead th,
    #employeeFaceTable_wrapper table.dataTable tbody td {
        padding: .65rem .75rem;
        vertical-align: middle;
    }

    /* Baris info (jumlah data) & pagination diberi jarak dari tabel. */
    #holidaysTable_wrapper .dataTables_info,
    #holidaysTable_wrapper .dataTables_paginate,
    #employeeFaceTable_wrapper .dataTables_info,
    #employeeFaceTable_wrapper .dataTables_paginate {
        padding-top: .5rem;
    }

    /* Padding sel pertama/terakhir tetap simetris supaya kolom
       NPK/Tanggal dan Aksi/Jenis tidak jadi renggang. */
    #holidaysTable_wrapper table.dataTable thead th:first-child,
    #holidaysTable_wrapper table.dataTable tbody td:first-child,
    #employeeFaceTable_wrapper table.dataTable thead th:first-child,
    #employeeFaceTable_wrapper table.dataTable tbody td:first-child {
        padding-left: .75rem;
    }

    #holidaysTable_wrapper table.dataTable thead th:last-child,
    #holidaysTable_wrapper table.dataTable tbody td:last-child,
    #employeeFaceTable_wrapper table.dataTable thead th:last-child,
    #employeeFaceTable_wrapper table.dataTable tbody td:last-child {
        padding-right: .75rem;
    }

    /* Card hari libur: wrapper DataTables mengisi sisa tinggi card
       tanpa memaksa tinggi tetap (menghindari area kosong).
       Rantai: card-body(flex-column) > table-responsive(flex-grow-1)
               > #holidaysTable_wrapper > tabel */
    #holidaysTable_wrapper {
        flex: 1 1 auto;
        min-height: 0;
    }
</style>
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script>
/* ---------------------------------------------------------
   DataTables: Daftar Hari Libur (tabel holidays)
   Client-side: seluruh $holidays sudah dimuat controller
   (Holiday::orderBy('holiday_date')->get()).
   --------------------------------------------------------- */
(function () {
    const table = document.getElementById('holidaysTable');
    if (!table || typeof jQuery === 'undefined') return;

    jQuery(function ($) {
        if (!$.fn.DataTable) return;

        const dt = $('#holidaysTable').DataTable({
            processing: true,
            serverSide: false,
            autoWidth: false,
            deferRender: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Semua']],
            // Default urut berdasarkan tanggal (kolom 0) secara kronologis.
            // Kolom Tanggal memakai data-order="Y-m-d" supaya sortir
            // mengikuti tanggal asli, bukan teks "d-m-Y".
            order: [[0, 'asc']],
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ hari libur',
                infoEmpty: 'Tidak ada hari libur',
                zeroRecords: 'Hari libur tidak ditemukan',
                emptyTable: 'Belum ada data hari libur.',
                processing: 'Memproses...',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya'
                }
            },
            columnDefs: [
                // Kolom Jenis hanya badge -> tidak perlu diurutkan/dicari.
                { targets: 2, orderable: false }
            ]
        });

        window.holidaysTable = dt;
    });
})();
</script>
<script>
(function () {
    const table = document.getElementById('employeeFaceTable');
    if (!table || typeof jQuery === 'undefined') return;

    jQuery(function ($) {
        if (!$.fn.DataTable) return;

        const dt = $('#employeeFaceTable').DataTable({
            // Client-side: seluruh $employeeFaces sudah dimuat oleh controller.
            // Cocok karena daftar karyawan face recognition relatif terbatas.
            processing: true,
            serverSide: false,
            autoWidth: false,
            deferRender: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Semua']],
            order: [[1, 'asc']],
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ karyawan',
                infoEmpty: 'Tidak ada karyawan',
                zeroRecords: 'Karyawan tidak ditemukan',
                emptyTable: 'Belum ada karyawan terdaftar pada face recognition.',
                processing: 'Memproses...',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya'
                }
            },
            columnDefs: [
                { targets: 2, orderable: false },
                { targets: 4, orderable: false, searchable: false }
            ],
            drawCallback: function () {
                // Event listener "Kelola Akses" dipasang ulang karena
                // DataTables merender ulang <tbody> saat sort/paginasi.
                if (typeof window.__initFaceAccessButtons === 'function') {
                    window.__initFaceAccessButtons();
                }
            }
        });

        // Simpan instance agar bisa dipakai bila perlu (mis. refresh count).
        window.employeeFaceTable = dt;
    });
})();
</script>

{{-- ====================================================================
     PERBAIKAN V32 (2026-11-09) - GEOMAP TRANSAKSI KANTIN
     Konfirmasi sebelum simpan + notifikasi hasil (SweetAlert2).
     ====================================================================
     Permintaan user: "saat simpan Geomap Transaksi Kantin tampilkan
     sweet alert confirmation dan berhasilnya".

     Alur:
       1. User klik "Simpan Pengaturan Geomap Kantin".
       2. SweetAlert konfirmasi muncul (ringkasan: wajib/tidak + jumlah
          lokasi terpilih). Tombol "Batal" membatalkan submit.
       3. Setelah user konfirmasi, form dikirim via fetch() dengan
          header Accept: application/json.
       4. Server membalas JSON { ok, message, warning }.
       5. SweetAlert hasil ditampilkan: "Berhasil Disimpan" (hijau)
          atau "Gagal Menyimpan" (merah).
       6. Halaman di-reload supaya nilai form Sinkron dengan server.

     Fallback: bila SweetAlert2 tidak termuat, form dikirim normal
     (POST biasa) sehingga pengaturan tetap bisa disimpan.
     ==================================================================== --}}
<script>
(function () {
    'use strict';

    var form = document.getElementById('canteenGeomapForm');
    if (!form) {
        return;
    }

    var btnSubmit = form.querySelector('button[type="submit"]');
    var lastFocused = null;

    /**
     * Hitung ringkasan singkat dari state form saat ini.
     */
    function summarize() {
        var toggle = form.querySelector('#canteen_require_geomap');
        var wajib = !!(toggle && toggle.checked);

        // PERBAIKAN V41 (2026-11-10): pilih lokasi sudah diganti dari
        // <select multiple> jadi CHECKLIST. Fungsi ini sebelumnya masih
        // membaca <select> yang sudah tidak ada, sehingga jumlah selalu 0
        // dan ringkasan konfirmasi selalu salah ("0 lokasi dipilih")
        // walau user sudah mencentang beberapa lokasi.
        var boxes = form.querySelectorAll('input[name="allowed_work_location_ids[]"]');
        var jumlah = 0;
        var nama = [];

        for (var i = 0; i < boxes.length; i++) {
            if (boxes[i].checked) {
                jumlah++;

                var label = boxes[i].closest('.canteen-loc-item');
                var teks = label
                    ? (label.querySelector('.font-weight-bold') || {}).textContent
                    : '';
                nama.push((teks || '').trim());
            }
        }

        return {
            wajib: wajib,
            jumlah: jumlah,
            nama: nama
        };
    }

    /**
     * Bangun HTML ringkasan untuk isi SweetAlert konfirmasi.
     * Semua teks yang berasal dari DB (nama lokasi) di-escape supaya
     * tidak bisa menyuntikkan HTML.
     */
    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[c];
        });
    }

    function buildConfirmHtml(info) {
        var baris = [];

        baris.push(
            '<div style="text-align:left;font-size:.92rem;line-height:1.6">'
            + '<div><b>Wajib geomap:</b> '
            + (info.wajib ? '<span style="color:#15803d;font-weight:700">YA</span>'
                          : '<span style="color:#b45309;font-weight:700">TIDAK</span>')
            + '</div>'
            + '<div><b>Lokasi dipilih:</b> ' + info.jumlah + ' lokasi</div>'
        );

        if (info.nama.length) {
            baris.push(
                '<div style="margin-top:8px;max-height:130px;overflow:auto">'
                + info.nama.map(function (n) {
                    return '<div style="font-size:.85rem;padding:2px 0">'
                        + '<span style="color:#6b7280">&bull;</span> ' + escapeHtml(n) + '</div>';
                }).join('')
                + '</div>'
            );
        }

        if (info.wajib && info.jumlah === 0) {
            baris.push(
                '<div style="margin-top:10px;padding:9px 11px;border-radius:8px;'
                + 'background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;font-size:.83rem">'
                + '<b>Perhatian:</b> geomap wajib diaktifkan tetapi tidak ada lokasi yang dipilih. '
                + 'Semua transaksi kantin akan DITOLAK (fail-closed).'
                + '</div>'
            );
        }

        baris.push('</div>');
        return baris.join('');
    }

    /**
     * SweetAlert sukses / gagal.
     */
    function showResult(ok, message, warning) {
        var judul = ok ? 'Berhasil Disimpan' : 'Gagal Menyimpan';
        var ikon = ok ? 'success' : 'error';

        if (warning) {
            Swal.fire({
                icon: ikon,
                title: judul,
                html: '<div style="text-align:left;font-size:.9rem;line-height:1.6">'
                    + '<div>' + escapeHtml(message) + '</div>'
                    + '<div style="margin-top:10px;padding:9px 11px;border-radius:8px;'
                    + 'background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:.83rem">'
                    + escapeHtml(warning) + '</div></div>',
                confirmButtonText: 'Mengerti',
                customClass: { popup: 'swal-geomap-popup' }
            });
        } else {
            Swal.fire({
                icon: ikon,
                title: judul,
                text: message,
                confirmButtonText: 'Mengerti',
                customClass: { popup: 'swal-geomap-popup' }
            });
        }
    }

    // Konfirmasi + submit via fetch.
    form.addEventListener('submit', function (event) {
        // Bila SweetAlert tidak tersedia -> submit normal (fallback).
        if (typeof Swal === 'undefined' || !Swal || typeof Swal.fire !== 'function') {
            return;
        }

        event.preventDefault();
        lastFocused = document.activeElement;

        var info = summarize();

        Swal.fire({
            title: 'Simpan Pengaturan Geomap Kantin?',
            html: buildConfirmHtml(info),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: { popup: 'swal-geomap-popup' }
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            // Tutup konfirmasi, tampilkan proses menyimpan.
            Swal.fire({
                title: 'Menyimpan...',
                allowOutsideClick: false,
                didOpen: function () { Swal.showLoading(); },
                customClass: { popup: 'swal-geomap-popup' }
            });

            var formData = new FormData(form);
            var url = form.getAttribute('action');

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(function (res) {
                return res.json()
                    .catch(function () {
                        return { ok: false, message: 'Server membalas dengan format yang tidak dikenali (HTTP ' + res.status + ').' };
                    })
                    .then(function (data) {
                        return { status: res.status, data: data };
                    });
            })
            .then(function (resultJson) {
                var data = resultJson.data || {};

                if (data.ok) {
                    showResult(true, data.message || 'Pengaturan geomap transaksi kantin berhasil disimpan.', data.warning);
                } else {
                    showResult(false, data.message || 'Gagal menyimpan pengaturan geomap kantin.');
                }

                // Reload supaya form menampilkan nilai yang benar-benar
                // tersimpan di server (bukan hanya state lokal).
                setTimeout(function () { window.location.reload(); }, 1200);
            })
            .catch(function (err) {
                showResult(false, 'Gagal menghubungi server: ' + (err && err.message ? err.message : 'kesalahan jaringan.'));
                if (lastFocused && lastFocused.focus) {
                    lastFocused.focus();
                }
            });
        });
    });
})();
</script>

{{-- ====================================================================
     PERBAIKAN V41 (2026-11-10) - MINI MAP GEOMAP TRANSAKSI KANTIN
     Menggambar polygon area kantin di dalam kartu "Geomap Transaksi
     Kantin" pada halaman Pengaturan Presensi Wajah.

     PENTING:
       - Helper extractPolygonRing() di sini adalah SALINAN dari helper
         yang sama di resources/views/canteen_transaction/index.blade.php.
         Keduanya menormalisasi 4 bentuk GeoJSON (Feature, Geometry,
         array polos, FeatureCollection) sehingga polygon yang digambar
         = polygon yang dicek saat transaksi.
       - Fail-closed: ring dengan < 3 titik TIDAK digambar sama sekali
         dan TIDAK dianggap sebagai area yang sah.
       - Peta otomatis fit ke bounds seluruh polygon agar area PASTI
         terlihat tanpa perlu zoom manual.
     ==================================================================== --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    'use strict';

    var jsonEl = document.getElementById('canteenGeomapLocationsJson');
    var mapEl  = document.getElementById('canteenGeomapMiniMap');
    if (!jsonEl || !mapEl || typeof window.L === 'undefined' || !window.L.map) {
        return;
    }

    var locations = [];
    try {
        locations = JSON.parse(jsonEl.value || '[]');
    } catch (e) {
        locations = [];
    }
    if (!Array.isArray(locations) || locations.length === 0) {
        return;
    }

    /**
     * Normalisasi GeoJSON -> ring [lng, lat].
     * SALINAN dari helper di halaman transaksi (harus sinkron).
     */
    function extractPolygonRing(polygonGeojson) {
        if (!polygonGeojson) return [];

        var data = polygonGeojson;
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (e) { return []; }
        }
        if (!data || typeof data !== 'object') return [];

        // Bentuk 3: array polos berisi titik [lng,lat].
        if (Array.isArray(data) && data.length && Array.isArray(data[0]) && typeof data[0][0] !== 'object') {
            return data
                .map(function (p) { return [parseFloat(p[0]), parseFloat(p[1])]; })
                .filter(function (p) { return !isNaN(p[0]) && !isNaN(p[1]); });
        }

        // Bentuk 4: FeatureCollection -> ambil feature pertama.
        if (data.type === 'FeatureCollection') {
            var first = (data.features || [])[0];
            return first ? extractPolygonRing(first) : [];
        }

        // Bentuk 1: Feature -> turun ke geometry.
        if (data.type === 'Feature' && data.geometry) {
            return extractPolygonRing(data.geometry);
        }

        // Bentuk 2: Geometry Polygon / MultiPolygon.
        var coords = data.coordinates;
        if (!Array.isArray(coords) || coords.length === 0) return [];

        // MultiPolygon -> ambil polygon pertama.
        if (Array.isArray(coords[0]) && Array.isArray(coords[0][0]) && Array.isArray(coords[0][0][0])) {
            coords = coords[0];
        }
        // Polygon -> ambil outer ring.
        if (Array.isArray(coords[0]) && Array.isArray(coords[0][0])) {
            coords = coords[0];
        }
        if (!Array.isArray(coords) || coords.length === 0) return [];

        return coords
            .map(function (p) { return [parseFloat(p[0]), parseFloat(p[1])]; })
            .filter(function (p) { return !isNaN(p[0]) && !isNaN(p[1]); });
    }

    var map = window.L.map(mapEl, { zoomControl: true, attributionControl: true })
        .setView([-6.2088, 106.8456], 13);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var layersById = {};
    var allBounds = null;

    locations.forEach(function (loc) {
        var ring = extractPolygonRing(loc.ring);

        // Fail-closed: tanpa polygon yang valid, tidak digambar.
        if (!ring || ring.length < 3) {
            return;
        }

        // PERBAIKAN V43 (2026-11-10) — BUG: "polygon rusak" di Peta Area
        // Transaksi Kantin.
        //
        // AKAR MASALAH:
        //   Leaflet expects polygon points as [LAT, LNG].
        //   GeoJSON stores polygon rings as [LNG, LAT].
        //   Code lama memanggil L.polygon(ring) dengan ring [LNG, LAT].
        //   Hasilnya: bentuk digambar "di tempat yang benar" tapi
        //   getBounds() melaporkan lat/lng TERBALIK. Akibatnya
        //   fitBounds() mengarahkan kamera ke lat ~85 (clamp Web Mercator)
        //   / lng ~-7.6 -> peta jadi blok abu-abu tanpa tile, polygon
        //   tidak terlihat, zoom melonjak ke 18-19.
        //
        // SOLUSI (pola yang sama dengan /face-attendance/map):
        //   Bungkus ring [LNG, LAT] sebagai GeoJSON Feature lalu pakai
        //   L.geoJSON(). Leaflet menafsirkan GeoJSON dengan konvensi
        //   [LNG, LAT] yang benar, jadi bounds akurat dan fitBounds
        //   mengarah ke area yang benar.
        var geoJson = {
            type: 'Feature',
            properties: { name: loc.name || 'Area' },
            geometry: {
                type: 'Polygon',
                coordinates: [ring]
            }
        };

        var polygon = window.L.geoJSON(geoJson, {
            style: {
                color: '#94a3b8',
                weight: 2,
                fillColor: '#94a3b8',
                fillOpacity: 0.18
            }
        }).addTo(map);

        polygon.bindPopup(loc.name || 'Area');

        layersById[String(loc.id)] = polygon;

        var b = polygon.getBounds();
        allBounds = allBounds ? allBounds.extend(b) : b;
    });

    function isSelected(locId) {
        var cb = document.querySelector('input[name="allowed_work_location_ids[]"][data-loc-id="' + locId + '"]');
        return !!(cb && cb.checked);
    }

    function refreshStyles() {
        locations.forEach(function (loc) {
            var poly = layersById[String(loc.id)];
            if (!poly) return;

            if (isSelected(loc.id)) {
                poly.setStyle({ color: '#22c55e', fillColor: '#22c55e', fillOpacity: 0.38, weight: 3 });
            } else {
                poly.setStyle({ color: '#94a3b8', fillColor: '#94a3b8', fillOpacity: 0.18, weight: 2 });
            }
        });
    }

    function fitAll() {
        if (allBounds && allBounds.isValid()) {
            map.fitBounds(allBounds.pad(0.15));
        }
    }

    // Fit ke seluruh area saat peta selesai digambar.
    fitAll();
    setTimeout(function () { map.invalidateSize(); fitAll(); }, 220);

    var fitBtn = document.getElementById('canteenGeomapFitBtn');
    if (fitBtn) {
        fitBtn.addEventListener('click', function () {
            fitAll();
            map.invalidateSize();
        });
    }

    // Interaksi checklist: centang lokasi -> highlight polygon di peta
    // + perbarui kelas baris (loc-checked) supaya badge "Dipilih" muncul.
    document.addEventListener('change', function (ev) {
        var cb = ev.target;
        if (!cb || cb.name !== 'allowed_work_location_ids[]') return;

        refreshStyles();
        updateCountBadge();

        var label = cb.closest ? cb.closest('.canteen-loc-item') : null;
        if (label) label.classList.toggle('loc-checked', cb.checked);

        var poly = layersById[String(cb.value)];
        if (poly && cb.checked) {
            map.fitBounds(poly.getBounds().pad(0.35));
        }
    });

    function updateCountBadge() {
        var badge = document.getElementById('canteenGeomapCountBadge');
        if (!badge) return;

        var boxes = document.querySelectorAll('input[name="allowed_work_location_ids[]"]');
        var n = 0;
        var tanpaPoly = 0;

        for (var i = 0; i < boxes.length; i++) {
            if (boxes[i].checked) {
                n++;
                if (boxes[i].getAttribute('data-loc-has-poly') !== '1') tanpaPoly++;
            }
        }

        badge.textContent = n + ' dipilih';
        badge.className = 'badge ' + (n > 0 ? 'badge-success' : 'badge-secondary');

        var hint = document.getElementById('canteenGeomapHint');
        if (hint) {
            if (n === 0) {
                hint.innerHTML = 'Belum ada lokasi yang dipilih. Semua transaksi akan ditolak bila geomap wajib aktif.';
                hint.className = 'small mt-1 text-danger';
            } else if (tanpaPoly > 0) {
                hint.innerHTML = n + ' lokasi dipilih, tetapi <strong>' + tanpaPoly + '</strong> di antaranya tidak punya polygon '
                    + 'dan akan ditolak saat transaksi (fail-closed).';
                hint.className = 'small mt-1 text-warning';
            } else {
                hint.innerHTML = n + ' lokasi dipilih. Area hijau = lokasi yang diizinkan, abu-abu = belum diizinkan.';
                hint.className = 'small mt-1 text-muted';
            }
        }
    }

    // Klik baris lokasi -> fokuskan peta ke polygon lokasi tersebut.
    document.addEventListener('click', function (ev) {
        var label = ev.target.closest ? ev.target.closest('.canteen-loc-item') : null;
        if (!label) return;

        var cb = label.querySelector('input[type="checkbox"]');
        if (!cb) return;

        var poly = layersById[String(cb.value)];
        if (poly) {
            map.fitBounds(poly.getBounds().pad(0.35));
            poly.openPopup();
        }

        document.querySelectorAll('.canteen-loc-item').forEach(function (el) {
            el.classList.remove('is-active');
        });
        label.classList.add('is-active');
    });

    updateCountBadge();
    refreshStyles();
})();
</script>
@endsection
