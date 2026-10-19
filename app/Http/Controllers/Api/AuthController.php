<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     *
     * Login karyawan menggunakan NPK dan password (tanggal lahir format ymd).
     * Mengembalikan Bearer token (Sanctum) untuk digunakan pada setiap request berikutnya.
     *
     * @bodyParam npk      string required  NPK karyawan. Example: C-00123
     * @bodyParam password string required  Tanggal lahir format ymd (2-digit year, month, day). Example: 950815
     */
    public function login(Request $request)
    {
        try {
            // ── Validasi input ──
            $validated = $request->validate([
                'npk'      => 'required|string',
                'password' => 'required|string',
            ], [
                'npk.required'      => 'NPK wajib diisi.',
                'password.required' => 'Password wajib diisi.',
            ]);

            // ── Cari data karyawan di tabel PKWT (connection cii) ──
            $employeePkwt = DB::connection('cii')
                ->table('PKWT')
                ->where('NPK', $validated['npk'])
                ->first();

            if (!$employeePkwt) {
                return response()->json([
                    'success' => false,
                    'message' => 'NPK tidak ditemukan. Pastikan NPK yang Anda masukkan sudah benar.',
                ], 404);
            }

            // ── Validasi tanggal lahir sebagai password ──
            $birth = $employeePkwt->TGLLAHIR;

            if (!$birth) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tanggal lahir tidak ditemukan untuk NPK ini. Silakan hubungi HR.',
                ], 400);
            }

            $expectedPassword = date('ymd', strtotime($birth));

            if ($validated['password'] !== $expectedPassword) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password salah. Password adalah tanggal lahir Anda dengan format ymd (contoh: 950815).',
                ], 401);
            }

            // ── Ambil data biodata untuk response ──
            $employee = DB::connection('cii')
                ->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->leftJoin('PKWT', 'BIODATA.NPK', '=', 'PKWT.NPK')
                ->where('BIODATA.NPK', $validated['npk'])
                ->select(
                    'BIODATA.NPK',
                    'BIODATA.NAMA_KARYAWAN',
                    'BIODATA.JENIS_KEL',
                    'BIODATA.SECTION',
                    'DEPT.DEPARTEMENT',
                    'PKWT.JK'
                )
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data biodata karyawan tidak ditemukan. Silakan hubungi HR.',
                ], 404);
            }

            // ── Cari atau buat user di tabel local `users` untuk keperluan Sanctum token ──
            $user = User::firstOrCreate(
                ['npk' => $validated['npk']],
                [
                    'name'     => $employee->NAMA_KARYAWAN ?? $validated['npk'],
                    'email'    => strtolower($validated['npk']) . '@internal.local',
                    'password' => Hash::make($expectedPassword),
                ]
            );

            // Hapus token lama milik device ini (opsional: batas 1 token aktif per user)
            $user->tokens()->delete();

            // ── Buat token baru ──
            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data'    => [
                    'npk'         => $employee->NPK,
                    'nama'        => $employee->NAMA_KARYAWAN,
                    'departement' => $employee->DEPARTEMENT ?? '-',
                    'token'       => $token,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal. Periksa kembali data yang Anda kirim.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat proses login. Silakan coba lagi.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Logout dan hapus token yang sedang digunakan.
     * Requires: Bearer Token di header Authorization.
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil. Token telah dihapus.',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat proses logout.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/v1/auth/me
     *
     * Mengembalikan profil karyawan yang sedang login berdasarkan token.
     * Requires: Bearer Token di header Authorization.
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            $employee = DB::connection('cii')
                ->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->leftJoin('PKWT', 'BIODATA.NPK', '=', 'PKWT.NPK')
                ->where('BIODATA.NPK', $user->npk)
                ->select(
                    'BIODATA.NPK',
                    'BIODATA.NAMA_KARYAWAN',
                    'BIODATA.JENIS_KEL',
                    'BIODATA.SECTION',
                    'DEPT.DEPARTEMENT',
                    'PKWT.JK'
                )
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data biodata karyawan tidak ditemukan untuk NPK ' . $user->npk . '.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data profil berhasil diambil.',
                'data'    => [
                    'npk'           => $employee->NPK,
                    'nama'          => $employee->NAMA_KARYAWAN,
                    'departement'   => $employee->DEPARTEMENT ?? '-',
                    'jenis_kelamin' => $employee->JK ?? $employee->JENIS_KEL ?? '-',
                    'section'       => $employee->SECTION ?? '-',
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mengambil data profil.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }
}
