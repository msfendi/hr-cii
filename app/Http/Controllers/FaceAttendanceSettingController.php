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

        return view('face_attendance.settings', compact(
            'setting',
            'locations',
            'holidays',
            'employeeFaces'
        ));
    }

    /**
     * Simpan pengaturan window presensi.
     */
    public function updateWindow(Request $request)
    {
        $request->validate([
            'clock_in_before_minutes' => 'required|integer|min:0|max:240',
            'clock_out_after_minutes' => 'required|integer|min:0|max:240',
        ]);

        $setting = FaceAttendanceSetting::current();

        $setting->clock_in_before_minutes = (int) $request->input('clock_in_before_minutes');
        $setting->clock_out_after_minutes = (int) $request->input('clock_out_after_minutes');
        $setting->is_saturday_enabled = $request->boolean('is_saturday_enabled');
        $setting->is_sunday_enabled = $request->boolean('is_sunday_enabled');
        $setting->is_holiday_enabled = $request->boolean('is_holiday_enabled');
        $setting->save();

        return redirect()
            ->route('face.attendance.settings.index')
            ->with('success', 'Pengaturan window presensi wajah berhasil disimpan.');
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
