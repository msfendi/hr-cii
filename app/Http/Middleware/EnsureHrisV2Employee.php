<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga Portal Karyawan HRIS v2.
 *
 * Satu kali login di /v2/karyawan/login (NPK + verifikasi wajah sesuai
 * mode yang diset admin) menyimpan NPK karyawan di session. Middleware ini
 * memastikan halaman-halaman karyawan (/v2/karyawan, slip gaji, dsb.)
 * hanya bisa dibuka kalau sesi itu ada.
 *
 * Sesi juga membawa batas waktu (default 8 jam, bisa diubah admin) supaya
 * login tidak menggantung selamanya di komputer bersama.
 */
class EnsureHrisV2Employee
{
    public const SESSION_NPK = 'hris_v2_npk';
    public const SESSION_NAME = 'hris_v2_nama';
    public const SESSION_AT = 'hris_v2_login_at';
    public const SESSION_MAX = 'hris_v2_max_minutes';

    public function handle(Request $request, Closure $next): Response
    {
        $npk = $request->session()->get(self::SESSION_NPK);

        if (! $npk) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi karyawan belum aktif. Silakan login ulang.',
                    'redirect_url' => route('v2.employee.login'),
                ], 401);
            }

            return redirect()->route('v2.employee.login')
                ->with('error', 'Silakan login terlebih dahulu untuk membuka Portal Karyawan.');
        }

        // Batas waktu sesi (menit). 0 = tanpa batas.
        $maxMinutes = (int) ($request->session()->get(self::SESSION_MAX) ?? 0);
        $loginAt = (int) ($request->session()->get(self::SESSION_AT) ?? 0);

        if ($maxMinutes > 0 && $loginAt > 0 && (time() - $loginAt) > ($maxMinutes * 60)) {
            $this->logout($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi karyawan sudah kedaluwarsa. Silakan login ulang.',
                    'redirect_url' => route('v2.employee.login'),
                ], 401);
            }

            return redirect()->route('v2.employee.login')
                ->with('error', 'Sesi karyawan sudah kedaluwarsa. Silakan login ulang.');
        }

        return $next($request);
    }

    /** Hapus seluruh kunci sesi karyawan (v2 + sesi subsistem lama). */
    public static function logout(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_NPK,
            self::SESSION_NAME,
            self::SESSION_AT,
            self::SESSION_MAX,
            'face_attendance_npk',
            'cuti_employee_npk',
        ]);
    }
}
