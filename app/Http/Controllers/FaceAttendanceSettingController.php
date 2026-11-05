<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\AttendanceWorkLocation;
use App\Models\EmployeeFace;
use App\Models\FaceAttendanceSetting;
use App\Models\Holiday;
use Carbon\Carbon;

class FaceAttendanceSettingController extends Controller
{
    /**
     * Halaman pengaturan window presensi wajah (Admin/HRD).
     */
    public function index()
    {
        $setting = FaceAttendanceSetting::current();

        $locations = AttendanceWorkLocation::orderBy('name')->get();

        // Ambil data hari libur dari tabel holidays (database CII)
        $holidays = collect();
        try {
            if (Schema::hasTable('holidays')) {
                $holidays = Holiday::orderBy('holiday_date')->get();
            }
        } catch (\Throwable $e) {
            $holidays = collect();
        }

        // Rekapemployee face + pengaturan akses per karyawan
        $employeeFaces = EmployeeFace::orderBy('name')->get();

        // PERBAIKAN V31.2: pengaturan liveness dipindah ke halaman ini.
        // Dibungkus try/catch supaya halaman tetap tampil walau tabel
        // face_recognition_settings belum ada / belum dimigrasi.
        $liveness = new \App\Models\FaceRecognitionSetting();
        $livenessTersedia = false;
        try {
            if (Schema::hasTable('face_recognition_settings')) {
                $liveness = \App\Models\FaceRecognitionSetting::current();
                $livenessTersedia = Schema::hasColumn('face_recognition_settings', 'is_liveness_enabled');
            }
        } catch (\Throwable $e) {
            $livenessTersedia = false;
        }

        return view('face_attendance.settings', compact(
            'setting',
            'locations',
            'holidays',
            'employeeFaces',
            'liveness',
            'livenessTersedia'
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

        // PERBAIKAN V33 (2026-11-05): toggle "input NPK dulu" di halaman
        // /face-attendance/login. Kalau aktif, user wajib mengetik NPK
        // sebelum kamera dibuka, dan pencocokan wajah hanya dilakukan
        // terhadap NPK tersebut (lebih akurat, tidak bisa salah orang).
        //
        // Disimpan di sini (bukan form terpisah) supaya admin cukup sekali
        // klik "Simpan" untuk seluruh pengaturan presensi.
        if (FaceAttendanceSetting::hasAllowanceColumn('is_npk_required_enabled')) {
            $setting->is_npk_required_enabled = $request->boolean('is_npk_required_enabled');
        }

        $setting->save();

        $pesan = 'Pengaturan window presensi wajah berhasil disimpan.';
        if (!FaceAttendanceSetting::hasAllowanceColumn()) {
            $pesan .= ' CATATAN: kolom allowance clock in belum ada di database, nilainya BELUM tersimpan. '
                    . 'Jalankan "php artisan migrate" lalu simpan ulang.';
        }
        if (!FaceAttendanceSetting::hasAllowanceColumn('clock_out_allowance_minutes')) {
            $pesan .= ' CATATAN: kolom allowance clock out belum ada di database, nilainya BELUM tersimpan. '
                    . 'Jalankan "php artisan migrate" lalu simpan ulang.';
        }
        if (!FaceAttendanceSetting::hasAllowanceColumn('is_npk_required_enabled')) {
            $pesan .= ' CATATAN: kolom "Input NPK Dulu" belum ada di database, nilainya BELUM tersimpan. '
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
        ]);

        try {
            if (!Schema::hasTable('face_recognition_settings')) {
                return redirect()
                    ->route('face.attendance.settings.index')
                    ->with('error', 'Tabel face_recognition_settings belum ada. Jalankan "php artisan migrate".');
            }

            $setting = \App\Models\FaceRecognitionSetting::current();

            if (Schema::hasColumn('face_recognition_settings', 'is_liveness_enabled')) {
                $setting->is_liveness_enabled = $request->boolean('is_liveness_enabled');
            }
            if (Schema::hasColumn('face_recognition_settings', 'liveness_presensi_enabled')) {
                $setting->liveness_presensi_enabled = $request->boolean('liveness_presensi_enabled');
            }
            if (Schema::hasColumn('face_recognition_settings', 'liveness_login_enabled')) {
                $setting->liveness_login_enabled = $request->boolean('liveness_login_enabled');
            }
            if (Schema::hasColumn('face_recognition_settings', 'liveness_slip_enabled')) {
                $setting->liveness_slip_enabled = $request->boolean('liveness_slip_enabled');
            }
            if (Schema::hasColumn('face_recognition_settings', 'liveness_cuti_enabled')) {
                $setting->liveness_cuti_enabled = $request->boolean('liveness_cuti_enabled');
            }
            // PERBAIKAN V31.3d: guard hasColumn tetap dipakai supaya halaman
            // ini tidak error bila migrasi belum dijalankan di server.
            if (Schema::hasColumn('face_recognition_settings', 'liveness_registrasi_enabled')) {
                $setting->liveness_registrasi_enabled = $request->boolean('liveness_registrasi_enabled');
            }

            $setting->save();

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('success', 'Pengaturan Liveness Check berhasil disimpan.');
        } catch (\Throwable $e) {
            \Log::error('Gagal simpan liveness dari Pengaturan Presensi Wajah: ' . $e->getMessage());

            return redirect()
                ->route('face.attendance.settings.index')
                ->with('error', 'Gagal menyimpan pengaturan liveness: ' . $e->getMessage());
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
