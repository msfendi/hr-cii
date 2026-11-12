<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AttendanceWorkLocation;
use App\Models\CanteenSetting;
use App\Models\EmployeeFace;
use App\Models\FaceAttendanceSetting;
use App\Models\FaceRecognitionSetting;
use App\Models\Holiday;
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert;

class FaceAttendanceSettingController extends Controller
{
    /**
     * Halaman pengaturan window presensi wajah (Admin/HRD).
     */
    public function index()
    {
        // PERBAIKAN V35 HOTFIX (2026-11-09) — $setting dibungkus try/catch.
        // FaceAttendanceSetting::current() sendiri sudah punya fallback,
        // tapi pemanggilnya tetap harus aman supaya halaman TIDAK pernah 500
        // hanya karena satu query gagal.
        try {
            $setting = FaceAttendanceSetting::current();
        } catch (\Throwable $e) {
            \Log::warning('FaceAttendanceSettingController: gagal memuat setting: ' . $e->getMessage());
            $setting = new FaceAttendanceSetting();
            $setting->clock_in_before_minutes = 15;
            $setting->clock_out_after_minutes = 15;
            $setting->is_saturday_enabled = false;
            $setting->is_sunday_enabled = false;
            $setting->is_holiday_enabled = false;
            $setting->is_npk_required_enabled = true;
        }

        // PERBAIKAN V33 (2026-11-09) -- index() /canteen-transaction +
        // /face-attendance/settings "sangat lama" diakses di sini.
        //
        // Lambat karena TIGA penyebab yang saling menumpuk, BUKAN karena
        // queries SQL baca data nya sendiri (semua tabel kecil):
        //
        //   1. Schema::hasTable / hasColumn() dipanggil BERULANG-ULANG
        //      (37x di 2 controller + model ini). Di SQL Server tiap
        //      panggilan = query ke INFORMATION_SCHEMA/ sys.columns
        //      lewat koneksi remote (CII di 192.168.3.88). Di Laragon
        //      yangokoneksi TLS `encrypt=yes` + trust cert ke SQL
        //      Server jauh, tiap query schema itu 50-300ms. 37x = 2-10
        //      detik per halaman load, tersinkron (blocking).
        //
        //      Fix: pakai getColumnListing() + TTL cache 1 jam
        //      (pola yang SUDAH dipakai FaceAttendanceSetting::cachedColumns
        //      sejak V33 2026-11-05). Satu info schema untuk semua kolom.
        //
        //   2. EmployeeFace::get() tanpa ->select() mengangkut seluruh
        //      kolom termasuk face_img (base64 foto, ratusan KB per
        //      karyawan) dan descriptor (JSON 128 float). Di settings
        //      kedua kolom TIDAK dipakai (view hanya render
        //      npk/nama/is_attendance_enabled/location_scope/allowed).
        //      Kalau ada 2.000 karyawan * 500KB = 1 GB data diangkut
        //      setiap load. Fix: ->select kolom ringan saja.
        //
        //   3. Asset render-blocking: face-api.js 1,3MB + model AI 6,6MB
        //      + CDN (fonts.googleapis, jsdelivr, unpkg leaflet)
        //      dimuat sync di <head>/inline. Di LAN yang lambat /
        //      firewall ke internet, browser hang di render.
        //      Fix: defer face-api/Leaflet/SweetAlert, preconnect CDN.
        //
        // Lihat FaceRecognitionSetting::cachedColumns() (helper baru)
        // untuk pola cache kolom yang dipakai controller ini.

        // Baca daftar kolom SEKALI (cached 1 jam), lalu cek in_array
        // di memori -- tanpa query INFORMATION_SCHEMA tambahan.
        $frSettingsCols = [];
        $canteenSettingsCols = [];
        $hasHolidaysTable = false;
        try {
            $frSettingsCols = \App\Models\FaceRecognitionSetting::cachedColumns('face_recognition_settings');
        } catch (\Throwable $e) {
            $frSettingsCols = [];
        }
        try {
            $canteenSettingsCols = \App\Models\CanteenSetting::cachedColumns('canteen_settings');
        } catch (\Throwable $e) {
            $canteenSettingsCols = [];
        }

        // Helper lokal: cek kolom dari daftar cached (tanpa query).
        $colExists = function (array $cols, string $col): bool {
            return in_array($col, $cols, true);
        };

        // PERBAIKAN V35 HOTFIX (2026-11-09) — query lokasi dibungkus
        // try/catch. Tabel attendance_work_locations ada di koneksi 'cii'
        // (default) ke SQL Server remote; timeout harus jadi fallback
        // kosong, bukan 500.
        $locations = collect();
        try {
            $locations = AttendanceWorkLocation::orderBy('name')->get();
        } catch (\Throwable $e) {
            \Log::warning('FaceAttendanceSettingController: gagal memuat lokasi kerja: ' . $e->getMessage());
            $locations = collect();
        }

        // Ambil data hari libur dari tabel holidays (database CII).
        // PERBAIKAN V34 HOTFIX (2026-11-09) -- jangan pakai
        // cachedHasTable() lagi. Langsung coba query; kalau DB timeout
        // / tabel tidak ada, catch di bawah akan memberi collect()
        // kosong dan halaman tetap tampil. Di V33, cachedHasTable()
        // memakai Schema::getTables() yang query berat (join
        // sys.partitions+allocation_units) dan bisa timeout -> 500.
        $holidays = collect();
        try {
            $holidays = Holiday::orderBy('holiday_date')->get();
        } catch (\Throwable $e) {
            $holidays = collect();
        }

        // Rekap employee face + pengaturan akses per karyawan.
        // PERBAIKAN V33: ->select() kolom ringan saja -- JANGAN bawa
        // face_img (base64 foto) & descriptor (JSON 128 float) dari
        // DB. View settings hanya butuh:
        //   id, npk, name, is_active, is_attendance_enabled,
        //   location_scope, allowed_location_ids
        // Ini menghilangkan transfer ratusan MB per load bila
        // jumlah karyawan besar (lihat komentar panjang di atas).
        $employeeFaces = collect();
        try {
            $employeeFaces = EmployeeFace::query()
                ->select([
                    'id', 'npk', 'name', 'is_active',
                    'is_attendance_enabled', 'location_scope', 'allowed_location_ids',
                ])
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
            \Log::warning('FaceAttendanceSettingController: gagal memuat daftar karyawan: ' . $e->getMessage());
            $employeeFaces = collect();
        }

        // PERBAIKAN V31.2: pengaturan liveness dipindah ke halaman ini.
        // Dibungkus try/catch supaya halaman tetap tampil walau tabel
        // face_recognition_settings belum ada / belum dimigrasi.
        // PERBAIKAN V34 HOTFIX (2026-11-09): JANGAN pakai cachedHasTable()
        // di request path settings. Di V33 cachedHasTable memakai
        // Schema::getTables() yang query berat (join
        // sys.partitions+allocation_units) dan bisa timeout -> 500.
        // Di V34, cukup periksa daftar kolom yang sudah di-cache
        // ($frSettingsCols) yang berasal dari 1x getColumnListing.
        // Kalau kolom tidak ada, livenessTersedia=false dan halaman
        // tetap tampil (badge tidak tersedia).
        $liveness = new \App\Models\FaceRecognitionSetting();
        $livenessTersedia = false;
        try {
            if ($frSettingsCols !== []) {
                $liveness = \App\Models\FaceRecognitionSetting::current();
                $livenessTersedia = $colExists($frSettingsCols, 'is_liveness_enabled');
            }
        } catch (\Throwable $e) {
            $livenessTersedia = false;
        }

        // Random challenge (anti-kecurangan) memakai objek setting yang sama,
        // tapi dicek terpisah supaya halaman tetap tampil kalau kolumnya belum ada.
        // V34: pakai $frSettingsCols, bukan cachedHasTable().
        $randomChallengeTersedia = false;
        try {
            if ($frSettingsCols !== []) {
                $randomChallengeTersedia = $colExists($frSettingsCols, 'is_random_challenge_enabled');
            }
        } catch (\Throwable $e) {
            $randomChallengeTersedia = false;
        }

        // PERBAIKAN V32 (2026-11-09): mode canteen = liveness ATAU
        // random challenge (bukan keduanya). Disimpan di
        // face_recognition_settings.canteen_verification_mode.
        //
        // Permintaan user: "liveness dan random challenge untuk canteen
        // transaction ada di table face_recognition_settings bukan
        // canteen_settings". Jadi canteen_settings TIDAK dipakai lagi
        // untuk verifikasi canteen.
        //
        // Kolom boolean lama (liveness_canteen_enabled,
        // random_challenge_canteen_enabled) tetap dicek hanya sebagai
        // fallback kompatibilitas sebelum migrasi mode jalan.
        $canteenTersedia = false;
        $canteenModeTersedia = false;
        $canteenLivenessTersedia = false;
        $canteenChallengeTersedia = false;
        // PERBAIKAN V34 HOTFIX (2026-11-09): pakai $frSettingsCols
        // (daftar kolom cached), bukan cachedHasTable() yang berat.
        try {
            if ($frSettingsCols !== []) {
                $canteenTersedia = true;
                $canteenModeTersedia = $colExists($frSettingsCols, 'canteen_verification_mode');
                $canteenLivenessTersedia = $colExists($frSettingsCols, 'liveness_canteen_enabled');
                $canteenChallengeTersedia = $colExists($frSettingsCols, 'random_challenge_canteen_enabled');
            }
        } catch (\Throwable $e) {
            $canteenTersedia = false;
        }

        // Nilai EFFECTIVE yang dipakai controller kantin.
        $canteenVerificationMode = \App\Models\FaceRecognitionSetting::canteenVerificationMode();
        $canteenLivenessEnabled = $canteenVerificationMode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_LIVENESS;
        $canteenChallengeEnabled = $canteenVerificationMode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_RANDOM;

        // PERBAIKAN V32 (2026-11-09): setting geomap untuk canteen
        // transaction ditampilkan di halaman ini juga.
        //
        // CATATAN MIGRASI: TIDAK PERLU migration baru. Tabel
        // `canteen_settings` sudah punya kolom `require_geomap` dan
        // `allowed_work_location_ids` sejak migration
        // 2026_11_09_130000_create_canteen_transaction_and_satisfaction_tables.
        // Kolom yang sama juga bisa disetting dari /canteen-transaction/admin,
        // jadi keduanya konsisten (satu sumber kebenaran).
        //
        // Kalau tabelnya belum ada (server lama), setelan di-default supaya
        // halaman tetap tampil.
        $canteenSetting = null;
        $canteenRequireGeomap = false;
        $canteenAllowedLocationIds = [];
        // PERBAIKAN V34 HOTFIX (2026-11-09) -- jangan pakai
        // cachedHasTable() lagi di path settings. CanteenSetting::current()
        // sudah punya try/catch + fallback yang membuat objek aman
        // meski DB timeout / tabel tidak ada. Jadi cukup coba direct,
        // bakan query checker tambahan yang berat.
        try {
            $canteenSetting = CanteenSetting::current();
            $canteenRequireGeomap = (bool) ($canteenSetting->require_geomap ?? false);
            $canteenAllowedLocationIds = array_values(array_filter(
                array_map('intval', (array) ($canteenSetting->allowed_work_location_ids ?? []))
            ));
        } catch (\Throwable $e) {
            \Log::warning('Gagal memuat canteen_settings untuk form geomap: ' . $e->getMessage());
            $canteenSetting = null;
            $canteenRequireGeomap = false;
            $canteenAllowedLocationIds = [];
        }

        return view('face_attendance.settings', compact(
            'setting',
            'locations',
            'holidays',
            'employeeFaces',
            'liveness',
            'livenessTersedia',
            'randomChallengeTersedia',
            'canteenTersedia',
            'canteenModeTersedia',
            'canteenLivenessTersedia',
            'canteenChallengeTersedia',
            'canteenVerificationMode',
            'canteenLivenessEnabled',
            'canteenChallengeEnabled',
            'canteenSetting',
            'canteenRequireGeomap',
            'canteenAllowedLocationIds'
        ));
    }

    /**
     * Simpan pengaturan window presensi.
     */
    public function updateWindow(Request $request)
    {
        $request->validate([
            'clock_in_before_minutes' => 'required|integer|min:0|max:240',
            'clock_in_allowance_minutes' => 'nullable|integer|min:0|max:120',
            'clock_out_after_minutes' => 'required|integer|min:0|max:240',
            'clock_out_allowance_minutes' => 'nullable|integer|min:0|max:120',
        ]);

        $setting = FaceAttendanceSetting::current();

        $setting->clock_in_before_minutes = (int) $request->input('clock_in_before_minutes');
        $setting->clock_out_after_minutes = (int) $request->input('clock_out_after_minutes');
        $setting->is_saturday_enabled = $request->boolean('is_saturday_enabled');
        $setting->is_sunday_enabled = $request->boolean('is_sunday_enabled');
        $setting->is_holiday_enabled = $request->boolean('is_holiday_enabled');

        // PERBAIKAN V31.2: simpan allowance hanya bila kolomnya sudah ada.
        // Tanpa penjagaan ini, SQL Server akan melempar error dan setting
        // lain ikut gagal tersimpan.
        if (FaceAttendanceSetting::hasAllowanceColumn()) {
            $setting->clock_in_allowance_minutes = (int) $request->input('clock_in_allowance_minutes', 0);
        }
        if (FaceAttendanceSetting::hasAllowanceColumn('clock_out_allowance_minutes')) {
            $setting->clock_out_allowance_minutes = (int) $request->input('clock_out_allowance_minutes', 0);
        }

        // PERBAIKAN 4 (2026-11-06): toggle "input NPK dulu" disimpan
        // di face_recognition_settings.
        //
        // PERBAIKAN V41 (2026-11-10): toggle tersebut PINDAH dari kartu
        // "Liveness Check (Anti Titip Absen)" ke kartu "Window Jam Presensi
        // & Hari Kerja" (permintaan user). Supaya form ini tetap utuh,
        // updateWindow() sekarang juga menulis is_npk_required_enabled.
        //
        // PENTING (backward compatibility): kalau field ini TIDAK dikirim
        // sama sekali oleh request, jangan menimpa nilai yang sudah ada.
        // Form lama / form lain yang POST ke endpoint ini tanpa field NPK
        // harus tetap bisa menyimpan pengaturan window-nya.
        if ($request->has('is_npk_required_enabled')) {
            try {
                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'is_npk_required_enabled')) {
                    $npkSetting = \App\Models\FaceRecognitionSetting::current();
                    $npkSetting->is_npk_required_enabled = $request->boolean('is_npk_required_enabled');
                    $npkSetting->save();
                }
            } catch (\Throwable $e) {
                // Jangan gagalkan penyimpanan pengaturan window hanya
                // karena kolom/tabel NPK belum siap (fail-soft).
                \Log::error('Gagal menyimpan toggle Input NPK dari form Window: ' . $e->getMessage());
            }
        }

        $pesan = 'Pengaturan window presensi wajah berhasil disimpan.';
        if (!FaceAttendanceSetting::hasAllowanceColumn()) {
            $pesan .= ' CATATAN: kolom allowance clock in belum ada di database, nilainya BELUM tersimpan. '
                    . 'Jalankan "php artisan migrate" lalu simpan ulang.';
        }
        if (!FaceAttendanceSetting::hasAllowanceColumn('clock_out_allowance_minutes')) {
            $pesan .= ' CATATAN: kolom allowance clock out belum ada di database, nilainya BELUM tersimpan. '
                    . 'Jalankan "php artisan migrate" lalu simpan ulang.';
        }

        return redirect()
            ->route('face.attendance.settings.index')
            ->with('success', $pesan);
    }

    /**
     * PERBAIKAN V31.2: simpan pengaturan liveness check.
     *
     * Sebelumnya pengaturan ini hanya ada di menu Face Recognition.
     * Sekarang bisa diatur dari halaman Pengaturan Presensi Wajah.
     * Form di halaman Face Recognition TETAP berfungsi (tidak dihapus
     * dari sisi simpan) agar tidak ada fitur yang hilang.
     */
        public function updateLiveness(Request $request)
        {
            $request->validate([
                'is_liveness_enabled'       => 'nullable|boolean',
                'liveness_presensi_enabled' => 'nullable|boolean',
                'liveness_login_enabled'    => 'nullable|boolean',
                'liveness_slip_enabled'     => 'nullable|boolean',
                'liveness_cuti_enabled'     => 'nullable|boolean',
                // PERBAIKAN V31.3d (2026-11-04): menu registrasi mandiri.
                'liveness_registrasi_enabled' => 'nullable|boolean',
                // PERBAIKAN 4 (2026-11-06): toggle "input NPK dulu" pindah ke face_recognition_settings
                'is_npk_required_enabled'   => 'nullable|boolean',
                // PERBAIKAN V32 (2026-11-09): mode canteen -- liveness ATAU
                // random challenge. Nilai: liveness | random_challenge | none.
                // Field lama liveness_canteen_enabled tetap diterima untuk
                // kompatibilitas form lama, tetapi tidak lagi ditulis ke
                // canteen_settings.
                'canteen_verification_mode' => 'nullable|string|in:liveness,random_challenge,none',
                'liveness_canteen_enabled'  => 'nullable|boolean',
            ]);

            try {
                // PERBAIKAN V34 HOTFIX: kalau kolom tidak bisa dibaca
                // (DB timeout / tabel belum ada), current() akan return
                // fallback object yang aman, bukan 500. Tapi untuk jalur
                // update (write path), lebih baik tetap cek kolom
                // daripada lanjut write ke tabel yang tidak ada.
                // Jadi tampilkan pesan migrasi bila daftar kolom kosong.
                if (\App\Models\FaceRecognitionSetting::cachedColumns('face_recognition_settings') === []) {
                    return redirect()
                        ->route('face.attendance.settings.index')
                        ->with('error', 'Tabel face_recognition_settings belum ada. Jalankan "php artisan migrate".');
                }

                $setting = \App\Models\FaceRecognitionSetting::current();

                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'is_liveness_enabled')) {
                    $setting->is_liveness_enabled = $request->boolean('is_liveness_enabled');
                }
                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_presensi_enabled')) {
                    $setting->liveness_presensi_enabled = $request->boolean('liveness_presensi_enabled');
                }
                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_login_enabled')) {
                    $setting->liveness_login_enabled = $request->boolean('liveness_login_enabled');
                }
                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_slip_enabled')) {
                    $setting->liveness_slip_enabled = $request->boolean('liveness_slip_enabled');
                }
                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_cuti_enabled')) {
                    $setting->liveness_cuti_enabled = $request->boolean('liveness_cuti_enabled');
                }
                // PERBAIKAN V31.3d: guard hasColumn tetap dipakai supaya halaman
                // ini tidak error bila migrasi belum dijalankan di server.
                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_registrasi_enabled')) {
                    $setting->liveness_registrasi_enabled = $request->boolean('liveness_registrasi_enabled');
                }
                // PERBAIKAN 4 (2026-11-06): simpan toggle "input NPK dulu" di sini juga.
                //
                // PERBAIKAN V41 (2026-11-10): toggle tersebut PINDAH ke kartu
                // "Window Jam Presensi & Hari Kerja" (permintaan user) dan
                // disimpan lewat updateWindow(). Jadi form liveness TIDAK lagi
                // mengirim field itu.
                //
                // PENTING: form liveness tidak mengirim field ini, jadi kita
                // WAJIB hanya menulisnya kalau request memang membawanya.
                // Kalau tidak, $request->boolean() mengembalikan false dan
                // nilai yang sudah tersimpan akan TER-TIMPA diam-diam setiap
                // kali user menyimpan pengaturan liveness.
                if ($request->has('is_npk_required_enabled')
                    && \App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'is_npk_required_enabled')) {
                    $setting->is_npk_required_enabled = $request->boolean('is_npk_required_enabled');
                }
                // PERBAIKAN V32 (2026-11-09): mode canteen di
                // face_recognition_settings (BUKAN canteen_settings).
                // Dua kolom boolean lama tetap ditulis hanya sebagai
                // cermin kompatibilitas; nilai efektif ditentukan oleh
                // canteen_verification_mode.
                //
                // PERBAIKAN V37 (2026-11-09): mode canteen sekarang punya
                // KARTU SENDIRI (updateCanteenVerificationMode). Jadi
                // form liveness TIDAK boleh menimpa mode aktif kalau
                // field itu tidak dikirim. Hanya tulis bila request
                // memang membawa field tersebut (backward compatibility).
                $hasCanteenModeInput = $request->has('canteen_verification_mode')
                    && $request->input('canteen_verification_mode') !== '';

                if ($hasCanteenModeInput) {
                    $mode = \App\Models\FaceRecognitionSetting::normalizeCanteenVerificationMode(
                        $request->input('canteen_verification_mode')
                    );

                    if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'canteen_verification_mode')) {
                        $setting->canteen_verification_mode = $mode;
                    }

                    if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_canteen_enabled')) {
                        $setting->liveness_canteen_enabled = ($mode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_LIVENESS);
                    }

                    if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'random_challenge_canteen_enabled')) {
                        $setting->random_challenge_canteen_enabled = ($mode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_RANDOM);
                    }
                }

                $setting->save();

                return redirect()
                    ->route('face.attendance.settings.index')
                    ->with('success', 'Pengaturan Liveness Check & Input NPK Dulu berhasil disimpan.');
            } catch (\Throwable $e) {
                \Log::error('Gagal simpan liveness/npk dari Pengaturan Presensi Wajah: ' . $e->getMessage());

                return redirect()
                    ->route('face.attendance.settings.index')
                    ->with('error', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
            }
        }

    /**
     * PERBAIKAN V37 (2026-11-09) -- Simpan "Mode Verifikasi Transaksi
     * Kantin" dari KARTU SENDIRI.
     *
     * Permintaan user: mode verifikasi transaksi kantin tidak lagi
     * displayed / disimpan lewat kartu Liveness Check (Anti Titip Absen)
     * maupun Random Challenge (Anti-Kecurangan), tapi jadi satu card
     * tersendiri.
     *
     * Konsekuensi:
     *   - Card ini TIDAK menyentuh setting liveness / random challenge
     *     global. Hanya kolom canteen_verification_mode yang ditulis,
     *     plus dua kolom boolean kompatibilitas sebagai cermin.
     *   - updateLiveness() & updateRandomChallenge() tetap menerima
     *     field canteen_verification_mode untuk backward compatibility
     *     (form lama / bookmark), tetapi kalau field tidak dikirim, mode
     *     yang aktif TIDAK lagi ditimpa menjadi default.
     */
    public function updateCanteenVerificationMode(Request $request)
    {
        $validated = $request->validate([
            'canteen_verification_mode' => 'required|string|in:liveness,random_challenge,none',
        ]);

        try {
            // PERBAIKAN V34 HOTFIX pattern: pakai cachedColumns() (ringan)
            // bukan Schema::getTables() yang berat.
            if (\App\Models\FaceRecognitionSetting::cachedColumns('face_recognition_settings') === []) {
                return redirect()
                    ->route('face.attendance.settings.index')
                    ->with('error', 'Tabel face_recognition_settings belum ada. Jalankan "php artisan migrate".');
            }

            if (! \App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'canteen_verification_mode')) {
                return redirect()
                    ->route('face.attendance.settings.index')
                    ->with('error', 'Kolom canteen_verification_mode belum tersedia. Jalankan "php artisan migrate".');
            }

            $setting = \App\Models\FaceRecognitionSetting::current();
            $mode = \App\Models\FaceRecognitionSetting::normalizeCanteenVerificationMode(
                $validated['canteen_verification_mode']
            );

            $setting->canteen_verification_mode = $mode;

            // Cermin kompatibilitas ke dua kolom boolean lama.
            if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_canteen_enabled')) {
                $setting->liveness_canteen_enabled = ($mode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_LIVENESS);
            }
            if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'random_challenge_canteen_enabled')) {
                $setting->random_challenge_canteen_enabled = ($mode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_RANDOM);
            }

            $setting->save();

            $label = [
                \App\Models\FaceRecognitionSetting::CANTEEN_MODE_LIVENESS => 'Liveness',
                \App\Models\FaceRecognitionSetting::CANTEEN_MODE_RANDOM => 'Random Challenge',
                \App\Models\FaceRecognitionSetting::CANTEEN_MODE_NONE => 'Tanpa Verifikasi Gerakan',
            ][$mode] ?? $mode;

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('success', 'Mode Verifikasi Transaksi Kantin berhasil disimpan: ' . $label . '.');
        } catch (\Throwable $e) {
            \Log::error('Gagal simpan mode verifikasi transaksi kantin: ' . $e->getMessage());

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('error', 'Gagal menyimpan mode verifikasi transaksi kantin: ' . $e->getMessage());
        }
    }

    /**
     * Simpan pengaturan Random Challenge (anti-kecurangan).
     *
     * TERPISAH dari updateLiveness() dengan sengaja. Random challenge
     * adalah fitur TAMBAHAN yang berdiri sendiri; logika liveness tidak
     * boleh tersentuh oleh fitur ini.
     *
     * Kolom hanya ditulis bila sudah tersedia di database (hasColumn),
     * sehingga halaman ini tetap berfungsi walau migrasi belum dijalankan.
     */
    public function updateRandomChallenge(Request $request)
    {
        $request->validate([
            'is_random_challenge_enabled'         => 'nullable|boolean',
            'random_challenge_presensi_enabled'   => 'nullable|boolean',
            'random_challenge_login_enabled'      => 'nullable|boolean',
            'random_challenge_slip_enabled'       => 'nullable|boolean',
            'random_challenge_cuti_enabled'       => 'nullable|boolean',
            'random_challenge_registrasi_enabled' => 'nullable|boolean',
            // PERBAIKAN V32 (2026-11-09): mode canteen -- liveness ATAU
            // random challenge. Nilai: liveness | random_challenge | none.
            'canteen_verification_mode' => 'nullable|string|in:liveness,random_challenge,none',
            'random_challenge_canteen_enabled'   => 'nullable|boolean',
        ]);

        try {
            // PERBAIKAN V34 HOTFIX: pakai cachedColumns() bukan
            // cachedHasTable() (berat, getTables join sys.partitions).
            if (\App\Models\FaceRecognitionSetting::cachedColumns('face_recognition_settings') === []) {
                return redirect()
                    ->route('face.attendance.settings.index')
                    ->with('error', 'Tabel face_recognition_settings belum ada. Jalankan "php artisan migrate".');
            }

            $setting = \App\Models\FaceRecognitionSetting::current();

            $pairs = [
                'is_random_challenge_enabled'         => $request->boolean('is_random_challenge_enabled'),
                'random_challenge_presensi_enabled'   => $request->boolean('random_challenge_presensi_enabled'),
                'random_challenge_login_enabled'      => $request->boolean('random_challenge_login_enabled'),
                'random_challenge_slip_enabled'       => $request->boolean('random_challenge_slip_enabled'),
                'random_challenge_cuti_enabled'       => $request->boolean('random_challenge_cuti_enabled'),
                'random_challenge_registrasi_enabled' => $request->boolean('random_challenge_registrasi_enabled'),
                // PERBAIKAN V37 (2026-11-09): random_challenge_canteen_enabled
                // TIDAK lagi ditulis dari form ini. Kolom itu kini hanya
                // cermin dari canteen_verification_mode yang diatur lewat
                // kartu "Mode Verifikasi Transaksi Kantin" sendiri.
                // Sebelumnya kolom ini ditulis dari $request->boolean()
                // padahal form ini tidak punya input untuk itu, sehingga
                // selalu bernilai false dan bisa mematikan mode random
                // challenge tanpa sengaja.
            ];

            foreach ($pairs as $column => $value) {
                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', $column)) {
                    $setting->{$column} = $value;
                }
            }

            // PERBAIKAN V32 (2026-11-09): sinkronkan mode canteen.
            // Mode effective = canteen_verification_mode; kolom boolean
            // lama ditulis sebagai cermin agar pembaca lama tidak salah.
            //
            // PERBAIKAN V37 (2026-11-09): mode canteen punya kartu sendiri,
            // jadi form random challenge TIDAK boleh menimpa mode aktif
            // bila field itu tidak dikirim.
            $hasCanteenModeInput = $request->has('canteen_verification_mode')
                && $request->input('canteen_verification_mode') !== '';

            if ($hasCanteenModeInput) {
                $mode = \App\Models\FaceRecognitionSetting::normalizeCanteenVerificationMode(
                    $request->input('canteen_verification_mode')
                );

                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'canteen_verification_mode')) {
                    $setting->canteen_verification_mode = $mode;
                }

                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'liveness_canteen_enabled')) {
                    $setting->liveness_canteen_enabled = ($mode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_LIVENESS);
                }

                if (\App\Models\FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'random_challenge_canteen_enabled')) {
                    $setting->random_challenge_canteen_enabled = ($mode === \App\Models\FaceRecognitionSetting::CANTEEN_MODE_RANDOM);
                }
            }

            $setting->save();

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('success', 'Pengaturan Random Challenge (anti-kecurangan) berhasil disimpan.');
        } catch (\Throwable $e) {
            \Log::error('Gagal simpan random challenge dari Pengaturan Presensi Wajah: ' . $e->getMessage());

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('error', 'Gagal menyimpan pengaturan random challenge: ' . $e->getMessage());
        }
    }

    /**
     * PERBAIKAN V32 (2026-11-09): simpan setting geomap kantin dari
     * halaman /face-attendance/settings.
     *
     * Nilai disimpan di tabel `canteen_settings` (kolom
     * `require_geomap` + `allowed_work_location_ids`) -- tabel yang sama
     * dengan yang dipakai /canteen-transaction/admin, sehingga tidak ada
     * dua sumber kebenaran.
     *
     * TIDAK PERLU migration baru karena kedua kolom sudah ada.
     */
    public function updateCanteenGeomap(Request $request)
    {
        $request->validate([
            'require_geomap' => 'nullable|boolean',
            'allowed_work_location_ids' => 'nullable|array',
            'allowed_work_location_ids.*' => 'integer|min:1',
        ]);

        // PERBAIKAN V32 (2026-11-09): dukung respons JSON agar sisi klien
        // bisa menampilkan konfirmasi + hasil tanpa reload halaman.
        // Permintaan JSON dipakai oleh form geomap di
        // resources/views/face_attendance/settings.blade.php
        $wantsJson = $request->expectsJson() || $request->wantsJson();

        try {
            // PERBAIKAN V34 HOTFIX: pakai cachedColumns() bukan
            // cachedHasTable() (berat).
            if (\App\Models\CanteenSetting::cachedColumns('canteen_settings') === []) {
                $pesan = 'Tabel canteen_settings belum ada. Jalankan "php artisan migrate".';

                if ($wantsJson) {
                    return response()->json([
                        'ok' => false,
                        'message' => $pesan,
                    ], 503);
                }

                Alert::error('Gagal Menyimpan', $pesan);

                return redirect()
                    ->route('face.attendance.settings.index')
                    ->with('error', $pesan);
            }

            $allowedIds = (array) $request->input('allowed_work_location_ids', []);
            $allowedIds = array_values(array_filter(array_map('intval', $allowedIds)));

            $setting = CanteenSetting::current();
            $setting->require_geomap = $request->boolean('require_geomap', false);
            $setting->allowed_work_location_ids = $allowedIds;
            $setting->save();

            $pesan = 'Pengaturan geomap transaksi kantin berhasil disimpan.';
            $peringatan = null;
            if ($setting->require_geomap && count($allowedIds) === 0) {
                $peringatan = 'CATATAN: geomap wajib diaktifkan tetapi tidak ada lokasi yang '
                    . 'dipilih, sehingga semua transaksi kantin akan DITOLAK (fail-closed).';
                $pesan .= ' ' . $peringatan;
            }

            if ($wantsJson) {
                return response()->json([
                    'ok' => true,
                    'message' => $pesan,
                    'warning' => $peringatan,
                    'require_geomap' => (bool) $setting->require_geomap,
                    'allowed_work_location_ids' => $allowedIds,
                ]);
            }

            Alert::success('Berhasil Disimpan', $pesan);

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('success', $pesan);
        } catch (\Throwable $e) {
            \Log::error('Gagal simpan geomap kantin dari Pengaturan Presensi Wajah: ' . $e->getMessage());

            $pesan = 'Gagal menyimpan pengaturan geomap kantin: ' . $e->getMessage();

            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $pesan,
                ], 500);
            }

            Alert::error('Gagal Menyimpan', $pesan);

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('error', $pesan);
        }
    }

    /**
     * Update pengaturan akses presensi wajah per karyawan.
     */
    public function updateEmployeeAccess(Request $request, $id)
    {
        $employeeFace = EmployeeFace::findOrFail($id);

        $request->validate([
            'is_attendance_enabled' => 'required|boolean',
            'location_scope'        => 'required|in:all,selected',
            'allowed_location_ids'  => 'nullable|array',
        ]);

        $employeeFace->is_attendance_enabled = $request->boolean('is_attendance_enabled');
        $employeeFace->location_scope = $request->input('location_scope', 'all');

        if ($employeeFace->location_scope === 'selected') {
            $ids = (array) $request->input('allowed_location_ids', []);
            $ids = array_values(array_filter(array_map('intval', $ids)));
            $employeeFace->allowed_location_ids = json_encode($ids);
        } else {
            $employeeFace->allowed_location_ids = null;
        }

        $employeeFace->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengaturan akses presensi untuk ' . $employeeFace->name . ' berhasil disimpan.',
            ]);
        }

        return redirect()
            ->route('face.attendance.settings.index')
            ->with('success', 'Pengaturan akses presensi untuk ' . $employeeFace->name . ' berhasil disimpan.');
    }
}
