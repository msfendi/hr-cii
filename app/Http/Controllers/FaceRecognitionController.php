<?php

namespace App\Http\Controllers;

use App\Models\EmployeeFace;
use App\Models\FaceRecognitionSetting;
use App\Models\User;
use App\Services\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class FaceRecognitionController extends Controller
{
    public function index()
    {
        $settings = FaceRecognitionService::settings();

        $faces = EmployeeFace::query()
            ->with(['registeredBy'])
            ->latest('updated_at')
            ->get();

        $users = User::query()
            ->select('id', 'npk', 'name', 'disabled')
            ->orderBy('name')
            ->get();

        $usersByNpk = $users->whereNotNull('npk')->keyBy(function ($u) {
            return strtoupper(trim($u->npk));
        });

        return view('face_recognition.index', compact('settings', 'faces', 'users', 'usersByNpk'));
    }

    /**
     * Simpan / perbarui wajah karyawan (register).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'npk'       => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'name'      => ['nullable', 'string', 'max:150'],
            'face_img'  => ['required', 'string', 'max:2000000'],
            'descriptor' => ['required'],
        ]);

        $descriptor = FaceRecognitionService::normalizeDescriptor($validated['descriptor']);

        if ($descriptor === null) {
            Alert::error('Gagal', 'Descriptor wajah tidak valid (harus 128 nilai).');
            return back();
        }

        if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $validated['face_img'])) {
            Alert::error('Gagal', 'Format foto wajah tidak valid.');
            return back();
        }

        $npk  = strtoupper(trim($validated['npk']));
        $name = trim((string) ($validated['name'] ?? ''));

        if ($name === '') {
            $name = $this->resolveEmployeeName($npk) ?? $npk;
        }

        EmployeeFace::updateOrCreate(
            ['npk' => $npk],
            [
                'name'             => $name,
                'face_img'         => $validated['face_img'],
                'descriptor'       => $descriptor,
                'is_active'        => true,
                'registered_by'    => Auth::id(),
                'last_verified_at' => null,
            ]
        );

        // Jika karyawan memiliki akun di tabel users, pastikan hak login aktif (disabled = 0)
        $user = User::whereRaw("UPPER(TRIM(npk)) = ?", [$npk])->first();
        if ($user && (int) $user->disabled === 1) {
            $user->disabled = 0;
            $user->save();
        }

        Alert::success('Berhasil', 'Wajah karyawan ' . $npk . ' berhasil disimpan.');
        return back();
    }

    /**
     * On/off face recognition dan QR login per menu + atur threshold.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'is_enabled'            => ['required', 'boolean'],
            'match_threshold'       => ['required', 'numeric', 'between:0.3,0.7'],
            'is_qr_login_enabled'   => ['nullable', 'boolean'],
            'is_qr_payroll_enabled' => ['nullable', 'boolean'],
            'is_qr_thr_enabled'     => ['nullable', 'boolean'],
            'is_qr_cuti_enabled'    => ['nullable', 'boolean'],
        ]);

        $settings = FaceRecognitionService::settings();

        $updateData = [
            'is_enabled'      => (bool) $validated['is_enabled'],
            'match_threshold' => (float) $validated['match_threshold'],
        ];

        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('face_recognition_settings', 'is_qr_login_enabled')) {
                $updateData['is_qr_login_enabled']   = (bool) ($validated['is_qr_login_enabled'] ?? true);
                $updateData['is_qr_payroll_enabled'] = (bool) ($validated['is_qr_payroll_enabled'] ?? true);
                $updateData['is_qr_thr_enabled']     = (bool) ($validated['is_qr_thr_enabled'] ?? true);
                $updateData['is_qr_cuti_enabled']    = (bool) ($validated['is_qr_cuti_enabled'] ?? true);
            }
        } catch (\Throwable $e) {
            // Abaikan jika pengecekan skema gagal / belum dimigrasi
        }

        $settings->update($updateData);

        Alert::success('Berhasil', 'Pengaturan face recognition & QR login diperbarui.');
        return back();
    }

    /**
     * Aktif / nonaktifkan satu data wajah.
     */
    public function toggle(EmployeeFace $employeeFace)
    {
        $employeeFace->update(['is_active' => !$employeeFace->is_active]);

        Alert::success('Berhasil', 'Status wajah ' . $employeeFace->npk . ' diperbarui.');
        return back();
    }

    public function destroy(EmployeeFace $employeeFace)
    {
        $npk = $employeeFace->npk;
        $employeeFace->delete();

        Alert::success('Berhasil', 'Data wajah ' . $npk . ' berhasil dihapus.');
        return back();
    }

    /**
     * Cek atau perbarui izin login akun untuk data wajah.
     */
     public function canLogin(Request $request, ?EmployeeFace $employeeFace = null)
     {
         if ($employeeFace) {
             $newVal = $employeeFace->can_login ? 0 : 1;
             $employeeFace->can_login = $newVal;
             $employeeFace->save();

             $user = User::whereRaw("UPPER(TRIM(npk)) = ?", [strtoupper(trim($employeeFace->npk))])->first();
             if ($user && $newVal === 1 && (int)$user->disabled === 1) {
                 $user->disabled = 0;
                 $user->save();
             }

             $statusLabel = $newVal ? 'BISA LOGIN' : 'TIDAK BISA LOGIN';

             if ($request->ajax() || $request->wantsJson()) {
                 return response()->json([
                     'success'   => true,
                     'can_login' => (bool)$newVal,
                     'message'   => 'Status izin login untuk NPK ' . $employeeFace->npk . ' berhasil diubah menjadi: ' . $statusLabel . '.'
                 ]);
             }

             \RealRashid\SweetAlert\Facades\Alert::success('Berhasil', 'Status izin login NPK ' . $employeeFace->npk . ' diubah menjadi: ' . $statusLabel . '.');
             return redirect()->back();
         }

         $npk  = $request->input('npk');
         $face = EmployeeFace::whereRaw("UPPER(TRIM(npk)) = ?", [strtoupper(trim($npk))])->first();
         $user = User::whereRaw("UPPER(TRIM(npk)) = ?", [strtoupper(trim($npk))])->first();
         $can  = $face ? (bool)$face->can_login : ($user && (int)$user->disabled === 0);

         return response()->json([
             'can_login' => $can
         ]);
     }

    /**
     * Test face recognition dari halaman admin.
     */
    public function identify(Request $request)
    {
        $descriptor = FaceRecognitionService::normalizeDescriptor($request->input('descriptor'));

        if ($descriptor === null) {
            return response()->json([
                'status'  => false,
                'message' => 'Descriptor wajah tidak valid.',
            ], 422);
        }

        $result    = FaceRecognitionService::match($descriptor);
        $threshold = FaceRecognitionService::threshold();
        $face      = $result['face'];

        if (!$face) {
            return response()->json([
                'status'    => true,
                'matched'   => false,
                'message'   => 'Belum ada data wajah yang terdaftar.',
                'threshold' => $threshold,
            ]);
        }

        $matched = FaceRecognitionService::isConfidentMatch($result, $threshold);
        $user    = User::query()->where('npk', $face->npk)->first();

        return response()->json([
            'status'     => true,
            'matched'    => $matched,
            'npk'        => $face->npk,
            'name'       => $face->name,
            'distance'   => round((float) $result['distance'], 4),
            'runner_up'  => $result['runner_up'] !== null ? round((float) $result['runner_up'], 4) : null,
            'margin'     => $result['margin'] !== null ? round((float) $result['margin'], 4) : null,
            'threshold'  => $threshold,
            'message'    => $matched
                ? 'Wajah dikenali.'
                : 'Wajah paling mirip masih di atas threshold / terlalu ambigu.',
            'user'       => $user ? [
                'id'       => $user->id,
                'name'     => $user->name,
                'npk'      => $user->npk,
                'disabled' => (bool) $user->disabled,
            ] : null,
            'can_login'  => $matched && $user && (int) $user->disabled === 0,
        ]);
    }

    /**
     * Cari nama karyawan berdasarkan NPK (untuk auto-fill form register).
     */
    public function lookup(Request $request)
    {
        $npk = strtoupper(trim((string) $request->query('npk')));

        if ($npk === '') {
            return response()->json([
                'status' => false,
                'message' => 'NPK wajib diisi.',
            ], 422);
        }

        $user = User::query()->where('npk', $npk)->first();

        return response()->json([
            'status'    => true,
            'npk'       => $npk,
            'name'      => $this->resolveEmployeeName($npk),
            'user_id'   => $user?->id,
            'has_user'  => (bool) $user,
            'disabled'  => $user ? (bool) $user->disabled : null,
            'face'      => EmployeeFace::query()->where('npk', $npk)->first(['id', 'npk', 'name', 'is_active']),
        ]);
    }

    private function resolveEmployeeName(string $npk): ?string
    {
        try {
            $employee = DB::connection('cii')
                ->table('BIODATA')
                ->where('NPK', $npk)
                ->value('NAMA_KARYAWAN');

            if ($employee) {
                return trim((string) $employee);
            }

            $employee = DB::connection('cii')
                ->table('BIODATA_KELUAR')
                ->where('NPK', $npk)
                ->value('NAMA_KARYAWAN');

            if ($employee) {
                return trim((string) $employee);
            }
        } catch (\Throwable $e) {
            // koneksi CII tidak tersedia -> fallback ke tabel users
        }

        return User::query()->where('npk', $npk)->value('name');
    }

    /**
     * Halaman wizard self register wajah.
     */
    public function selfRegisterPage()
    {
        if (!FaceRecognitionService::isEnabled()) {
            Alert::error('Nonaktif', 'Fitur face recognition sedang dinonaktifkan oleh admin.');
            return redirect('/');
        }

        return view('face_recognition.self_register');
    }

    /**
     * Step 1: cocokkan NPK + TGLLAHIR (YYMMDD) di tabel PKWT (tanpa NIK/KTP).
     */
    public function selfRegisterVerify(Request $request)
    {
        $validated = $request->validate([
            'npk'       => ['required', 'string', 'max:20'],
            'tgl_lahir' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $npk = strtoupper(trim($validated['npk']));
        $birthInput = $validated['tgl_lahir']; // YYMMDD

        $employee = DB::connection('cii')->table('PKWT')
            ->where('NPK', $npk)
            ->orderByDesc('TMK')
            ->first(['NPK', 'NAMA', 'TGLLAHIR', 'JK', 'BAGIAN', 'STATUS', 'TMK', 'TKK']);

        if (!$employee) {
            // Coba fallback ke tabel BIODATA jika ada
            try {
                $biodata = DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->first();
                if ($biodata) {
                    $employee = (object) [
                        'NPK'      => $biodata->NPK,
                        'NAMA'     => $biodata->NAMA_KARYAWAN ?? $biodata->NAMA ?? $npk,
                        'TGLLAHIR' => $biodata->TGL_LAHIR ?? null,
                        'JK'       => $biodata->JENIS_KELAMIN ?? '-',
                        'BAGIAN'   => $biodata->BAGIAN ?? '-',
                        'STATUS'   => 'AKTIF',
                    ];
                }
            } catch (\Throwable $e) {}
        }

        if (!$employee) {
            return response()->json([
                'status'  => false,
                'message' => 'Data tidak ditemukan. Periksa kembali NPK Anda.',
            ], 422);
        }

        $dbBirth = null;
        if (!empty($employee->TGLLAHIR)) {
            $ts = strtotime((string) $employee->TGLLAHIR);
            if ($ts !== false) {
                $dbBirth = date('ymd', $ts);
            }
        }

        if ($dbBirth === null || $dbBirth !== $birthInput) {
            return response()->json([
                'status'  => false,
                'message' => 'Tanggal lahir tidak cocok. Format harus YYMMDD (contoh: 980512).',
            ], 422);
        }

        $existing = EmployeeFace::query()
            ->where('npk', $npk)
            ->whereNotNull('descriptor')
            ->first(['id', 'name', 'updated_at']);

        if ($existing) {
            return response()->json([
                'status'             => false,
                'already_registered' => true,
                'message'            => 'Wajah Anda sudah terdaftar di sistem. Harap hubungi HRD jika ada kesalahan data atau ingin memperbarui wajah.',
            ], 422);
        }

        return response()->json([
            'status'   => true,
            'token'    => $this->issueSelfRegisterToken($npk),
            'employee' => [
                'npk'      => $employee->NPK,
                'nama'     => $employee->NAMA,
                'jk'       => $employee->JK ?? '-',
                'bagian'   => $employee->BAGIAN ?? '-',
                'status'   => $employee->STATUS ?? '-',
            ],
        ]);
    }

    /**
     * Step 2: Simpan foto & descriptor wajah.
     */
    public function selfRegisterStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'token'      => ['required', 'string'],
                'face_img'   => ['required', 'string', 'max:2000000'],
                'descriptor' => ['required'],
            ]);

            $npk = $this->consumeSelfRegisterToken($validated['token']);

            if ($npk === null) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Sesi verifikasi kedaluwarsa. Ulangi dari awal.',
                ], 419);
            }

            $descriptor = FaceRecognitionService::normalizeDescriptor($validated['descriptor']);

            if ($descriptor === null) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Descriptor wajah tidak valid (harus 128 nilai).',
                ], 422);
            }

            if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,/i', $validated['face_img'])) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Format foto wajah tidak valid.',
                ], 422);
            }

            $name = $this->resolveEmployeeName($npk) ?? $npk;

            $facePayload = [
                'name'             => $name,
                'face_img'         => $validated['face_img'],
                'descriptor'       => $descriptor,
                'is_active'        => true,
                'registered_by'    => null,
                'last_verified_at' => null,
            ];

            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('employee_faces', 'can_login')) {
                    $facePayload['can_login'] = 1;
                }
            } catch (\Throwable $e) {}

            EmployeeFace::updateOrCreate(
                ['npk' => $npk],
                $facePayload
            );

            return response()->json([
                'status'  => true,
                'message' => 'Foto wajah berhasil disimpan. Lanjut verifikasi wajah.',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('selfRegisterStore error: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan foto wajah: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3: Verifikasi konfirmasi wajah real-time.
     */
    public function selfRegisterConfirm(Request $request)
    {
        try {
            $validated = $request->validate([
                'npk'        => ['required', 'string', 'max:20'],
                'descriptor' => ['required'],
            ]);

            $npk = strtoupper(trim($validated['npk']));
            $live = FaceRecognitionService::normalizeDescriptor($validated['descriptor']);

            if ($live === null) {
                return response()->json(['status' => false, 'matched' => false, 'message' => 'Descriptor tidak valid.'], 422);
            }

            $face = EmployeeFace::query()->where('npk', $npk)->whereNotNull('descriptor')->first();

            if (!$face) {
                return response()->json(['status' => false, 'matched' => false, 'message' => 'Data wajah belum tersimpan.'], 404);
            }

            $stored = FaceRecognitionService::normalizeDescriptor($face->descriptor);

            if ($stored === null) {
                return response()->json(['status' => false, 'matched' => false, 'message' => 'Data wajah tersimpan rusak. Ulangi registrasi.'], 422);
            }

            $distance = FaceRecognitionService::euclidean($live, $stored);

            $threshold = 0.55;
            try {
                $threshold = FaceRecognitionService::threshold();
                if ($threshold < 0.50) {
                    $threshold = 0.55;
                }
            } catch (\Throwable $e) {
                $threshold = 0.55;
            }

            $matched = $distance <= $threshold;

            if ($matched) {
                $updateData = ['last_verified_at' => now()];
                try {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('employee_faces', 'can_login')) {
                        $updateData['can_login'] = 1;
                    }
                } catch (\Throwable $e) {}

                $face->update($updateData);

                // Jika NPK karyawan ada di tabel users, secara default langsung beri hak login (disabled = 0)
                try {
                    $user = User::whereRaw("UPPER(TRIM(npk)) = ?", [$npk])->first();
                    if ($user && (int) $user->disabled === 1) {
                        $user->disabled = 0;
                        $user->save();
                    }
                } catch (\Throwable $e) {}
            }

            return response()->json([
                'status'    => true,
                'matched'   => $matched,
                'distance'  => round($distance, 4),
                'threshold' => $threshold,
                'message'   => $matched
                    ? 'Verifikasi berhasil. Registrasi wajah selesai.'
                    : 'Wajah belum cocok dengan yang tersimpan. Hadapkan wajah ke kamera lebih jelas.',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('selfRegisterConfirm error: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'matched' => false,
                'message' => 'Terjadi kesalahan sistem saat konfirmasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Token sementara verifikasi identitas (900 detik).
     */
    private function issueSelfRegisterToken(string $npk): string
    {
        $payload = json_encode(['npk' => $npk, 'exp' => time() + 900]);
        $b64 = rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', (string) $payload, config('app.key'), true)), '+/', '-_'), '=');

        return $b64 . '.' . $sig;
    }

    private function consumeSelfRegisterToken(string $token): ?string
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 2) return null;

            [$b64, $sig] = $parts;
            $raw = base64_decode(strtr($b64, '-_', '+/'));
            $expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', (string) $raw, config('app.key'), true)), '+/', '-_'), '=');

            if (!hash_equals($expectedSig, $sig)) return null;

            $data = json_decode($raw, true);
            if (!$data || !isset($data['npk'], $data['exp']) || time() > $data['exp']) {
                return null;
            }

            return $data['npk'];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
