<?php

namespace App\Http\Controllers;

use App\Models\FaceRecognitionSetting;
use App\Models\HrisV2Menu;
use App\Models\HrisV2Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Pengelola Portal HRIS v2 (admin).
 *
 * Admin bisa: mengaktifkan/menonaktifkan menu portal karyawan, mengubah
 * label/deskripsi/ikon/warna/urutan/route tujuannya, mengatur lama sesi
 * login, dan memilih mode verifikasi wajah (liveness | random challenge |
 * none) — mode yang sama juga bisa diatur di /face-attendance/settings.
 */
class HrisV2AdminController extends Controller
{
    public function index()
    {
        $mode = FaceRecognitionSetting::hrisV2VerificationMode();

        return view('hris_v2.admin', [
            'menus' => HrisV2Menu::semua(),
            'mode' => $mode,
            'modeLabel' => $this->modeLabel($mode),
            'sessionMinutes' => HrisV2Setting::sessionMinutes(),
            'portalTitle' => HrisV2Setting::ambil('portal_title', 'Portal Karyawan HRIS v2'),
            'loginNote' => HrisV2Setting::ambil('employee_login_note', ''),
            'routeTersedia' => $this->daftarRouteTersedia(),
        ]);
    }

    /** Ubah satu menu (label, deskripsi, ikon, warna, urutan, aktif, route). */
    public function updateMenu(Request $request, $id)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'route_name' => 'required|string|max:150',
            'icon' => 'required|string|max:60',
            'color' => 'required|string|max:30',
            'is_enabled' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        try {
            $menu = HrisV2Menu::findOrFail($id);

            if (! Route::has($validated['route_name'])) {
                return redirect()->route('v2.admin.index')
                    ->with('error', 'Route "' . $validated['route_name'] . '" tidak terdaftar. Menu tidak disimpan.');
            }

            $menu->update([
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
                'route_name' => $validated['route_name'],
                'icon' => $validated['icon'],
                'color' => $validated['color'],
                'is_enabled' => (bool) $request->input('is_enabled', false),
                'sort_order' => (int) ($validated['sort_order'] ?? $menu->sort_order),
            ]);

            return redirect()->route('v2.admin.index')
                ->with('success', 'Menu "' . $menu->label . '" berhasil diperbarui.');
        } catch (\Throwable $e) {
            Log::error('HRIS v2 admin updateMenu gagal: ' . $e->getMessage());

            return redirect()->route('v2.admin.index')
                ->with('error', 'Gagal memperbarui menu: ' . $e->getMessage());
        }
    }

    /** Tambah menu baru untuk portal karyawan. */
    public function storeMenu(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:60|alpha_dash',
            'label' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'route_name' => 'required|string|max:150',
            'icon' => 'required|string|max:60',
            'color' => 'required|string|max:30',
            'is_enabled' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        try {
            if (! Route::has($validated['route_name'])) {
                return redirect()->route('v2.admin.index')
                    ->with('error', 'Route "' . $validated['route_name'] . '" tidak terdaftar. Menu tidak dibuat.');
            }

            if (HrisV2Menu::where('key', $validated['key'])->exists()) {
                return redirect()->route('v2.admin.index')
                    ->with('error', 'Menu dengan key "' . $validated['key'] . '" sudah ada.');
            }

            HrisV2Menu::create([
                'key' => $validated['key'],
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
                'route_name' => $validated['route_name'],
                'icon' => $validated['icon'],
                'color' => $validated['color'],
                'is_enabled' => (bool) $request->input('is_enabled', true),
                'sort_order' => (int) ($validated['sort_order'] ?? 99),
            ]);

            return redirect()->route('v2.admin.index')
                ->with('success', 'Menu "' . $validated['label'] . '" berhasil ditambahkan.');
        } catch (\Throwable $e) {
            Log::error('HRIS v2 admin storeMenu gagal: ' . $e->getMessage());

            return redirect()->route('v2.admin.index')
                ->with('error', 'Gagal menambah menu: ' . $e->getMessage());
        }
    }

    public function destroyMenu($id)
    {
        try {
            $menu = HrisV2Menu::findOrFail($id);
            $label = $menu->label;
            $menu->delete();

            return redirect()->route('v2.admin.index')
                ->with('success', 'Menu "' . $label . '" berhasil dihapus.');
        } catch (\Throwable $e) {
            Log::error('HRIS v2 admin destroyMenu gagal: ' . $e->getMessage());

            return redirect()->route('v2.admin.index')
                ->with('error', 'Gagal menghapus menu: ' . $e->getMessage());
        }
    }

    /**
     * Simpan setelan portal: mode verifikasi wajah, lama sesi, judul, catatan.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'hris_v2_verification_mode' => 'required|string|in:liveness,random_challenge,none',
            'session_minutes' => 'required|integer|min:0|max:10080',
            'portal_title' => 'nullable|string|max:150',
            'employee_login_note' => 'nullable|string|max:255',
        ]);

        try {
            if (FaceRecognitionSetting::cachedHasColumn('face_recognition_settings', 'hris_v2_verification_mode')) {
                $setting = FaceRecognitionSetting::current();
                $setting->hris_v2_verification_mode = FaceRecognitionSetting::normalizeHrisV2VerificationMode(
                    $validated['hris_v2_verification_mode']
                );
                $setting->save();
            } else {
                return redirect()->route('v2.admin.index')
                    ->with('error', 'Kolom hris_v2_verification_mode belum ada. Jalankan "php artisan migrate" lebih dulu.');
            }

            HrisV2Setting::simpan('session_minutes', (string) (int) $validated['session_minutes']);
            HrisV2Setting::simpan('portal_title', (string) ($validated['portal_title'] ?? ''));
            HrisV2Setting::simpan('employee_login_note', (string) ($validated['employee_login_note'] ?? ''));

            return redirect()->route('v2.admin.index')
                ->with('success', 'Setelan Portal HRIS v2 berhasil disimpan.');
        } catch (\Throwable $e) {
            Log::error('HRIS v2 admin updateSettings gagal: ' . $e->getMessage());

            return redirect()->route('v2.admin.index')
                ->with('error', 'Gagal menyimpan setelan portal: ' . $e->getMessage());
        }
    }

    protected function modeLabel(string $mode): string
    {
        switch ($mode) {
            case FaceRecognitionSetting::HRIS_V2_MODE_RANDOM:
                return 'Tantangan Gerakan (Random Challenge)';
            case FaceRecognitionSetting::HRIS_V2_MODE_NONE:
                return 'Tanpa Verifikasi Gerakan';
            default:
                return 'Liveness (Wajah Hidup)';
        }
    }

    /** Route yang boleh dipakai sebagai tujuan kartu menu (whitelist). */
    protected function daftarRouteTersedia(): array
    {
        $kandidat = [
            'v2.employee.payslip' => 'Slip Gaji (Portal v2)',
            'face.attendance.index' => 'Presensi Wajah',
            'pengajuan-cuti.form' => 'Pengajuan Cuti',
            'pengajuan-cuti.riwayat' => 'Riwayat Cuti',
            'pengajuan-cuti.get-leave-balance' => 'Saldo Cuti',
            'job-vacancy.public' => 'Lowongan (publik)',
            'portal.recruitment-status.index' => 'Cek Status Lamaran',
        ];

        return array_filter($kandidat, fn ($label, $name) => Route::has($name), ARRAY_FILTER_USE_BOTH);
    }
}
