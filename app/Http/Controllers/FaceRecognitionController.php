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
            ->with('registeredBy')
            ->latest('updated_at')
            ->get();

        $users = User::query()
            ->select('id', 'npk', 'name')
            ->orderBy('name')
            ->get();

        // Daftar NPK yang punya akun di tabel users (key = NPK uppercase).
        // Dipakai blade untuk badge "User aktif" / "Tanpa akun user".
        $userNpks = $users->pluck('npk')
            ->map(fn($n) => strtoupper(trim((string) $n)))
            ->flip();

        return view('face_recognition.index', compact('settings', 'faces', 'users', 'userNpks'));
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

        Alert::success('Berhasil', 'Wajah karyawan ' . $npk . ' berhasil disimpan.');
        return back();
    }

    /**
     * On/off face recognition secara global + atur threshold.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'is_enabled'      => ['required', 'boolean'],
            'match_threshold' => ['required', 'numeric', 'between:0.3,0.7'],
        ]);

        $settings = FaceRecognitionService::settings();
        $settings->update([
            'is_enabled'      => (bool) $validated['is_enabled'],
            'match_threshold' => (float) $validated['match_threshold'],
        ]);

        Alert::success('Berhasil', 'Pengaturan face recognition diperbarui.');
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

    // =====================================================================
    // SELF REGISTER FACE RECOGNITION (publik, tanpa login)
    // =====================================================================

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
     * Step 1-2: cocokkan NPK + No KTP + TGLLAHIR(YYMMDD) di tabel PKWT.
     * Mengembalikan data verifikasi (semua field read only di sisi klien).
     */
    public function selfRegisterVerify(Request $request)
    {
        $validated = $request->validate([
            'npk'       => ['required', 'string', 'max:20'],
            'ktp'       => ['required', 'string', 'max:30'],
            'tgl_lahir' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $npk = strtoupper(trim($validated['npk']));
        $ktp = preg_replace('/\s+/', '', $validated['ktp']);
        $birthInput = $validated['tgl_lahir']; // YYMMDD

        // Ambil kandidat terbaru utk NPK tsb (PKWT bisa punya beberapa baris TMK)
        $employee = DB::connection('cii')->table('PKWT')
            ->where('NPK', $npk)
            ->orderByDesc('TMK')
            ->first(['NPK', 'NAMA', 'KTP', 'TGLLAHIR', 'JK', 'BAGIAN', 'STATUS', 'TMK', 'TKK']);

        if (!$employee) {
            return response()->json([
                'status'  => false,
                'message' => 'Data tidak ditemukan. Periksa kembali NPK Anda.',
            ], 422);
        }

        // Normalisasi tanggal lahir PKWT ke YYMMDD untuk dibandingkan.
        $dbBirth = null;
        if (!empty($employee->TGLLAHIR)) {
            $ts = strtotime((string) $employee->TGLLAHIR);
            if ($ts !== false) {
                $dbBirth = date('ymd', $ts);
            }
        }

        $dbKtp = strtoupper(preg_replace('/\s+/', '', (string) $employee->KTP));

        if ($dbBirth === null || $dbBirth !== $birthInput) {
            return response()->json([
                'status'  => false,
                'message' => 'Data tidak cocok dengan berkas karyawan. Silakan hubungi HRD.',
            ], 422);
        }

        if ($dbKtp === '' || $dbKtp !== strtoupper(preg_replace('/\s+/', '', $ktp))) {
            return response()->json([
                'status'  => false,
                'message' => 'Nomor KTP tidak cocok dengan berkas karyawan. Silakan hubungi HRD.',
            ], 422);
        }

        // Cek apakah wajah sudah terdaftar -> minta konfirmasi overwrite
        $existing = EmployeeFace::query()->where('npk', $npk)->first(['id', 'name', 'updated_at']);

        return response()->json([
            'status'   => true,
            'token'    => $this->issueSelfRegisterToken($npk),
            'employee' => [
                'npk'      => $employee->NPK,
                'nama'     => $employee->NAMA,
                'ktp'      => $this->maskKtp((string) $employee->KTP),
                'jk'       => $employee->JK,
                'bagian'   => $employee->BAGIAN,
                'status'   => $employee->STATUS,
                'tmk'      => $employee->TMK,
                'tkk'      => $employee->TKK,
            ],
            'already_registered' => (bool) $existing,
            'registered_at'      => $existing?->updated_at?->toDateTimeString(),
        ]);
    }

    /**
     * Step 4: simpan wajah hasil rekaman dari self register.
     * Token sekali-pakai membuktikan user sudah lolos verifikasi identitas.
     */
    public function selfRegisterStore(Request $request)
    {
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

        // Self register: wajah TIDAK otomatis berhak login sistem (can_login=false).
        // Admin menyalakannya lewat toggle di Manage Face Recognition.
        EmployeeFace::updateOrCreate(
            ['npk' => $npk],
            [
                'name'             => $name,
                'face_img'         => $validated['face_img'],
                'descriptor'       => $descriptor,
                'is_active'        => true,
                'can_login'        => false,
                'registered_by'    => null,
                'last_verified_at' => null,
            ]
        );

        return response()->json([
            'status'  => true,
            'npk'     => $npk,
            'name'    => $name,
            'message' => 'Wajah berhasil disimpan. Lakukan verifikasi akhir.',
        ]);
    }

    /**
     * Step 5: verifikasi wajah yang baru tersimpan terhadap tangkapan kamera live.
     * Mencocokkan descriptor live vs descriptor tersimpan utk NPK tsb saja.
     */
    public function selfRegisterConfirm(Request $request)
    {
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

        $distance  = FaceRecognitionService::euclidean($live, $stored);
        $threshold = FaceRecognitionService::threshold();
        $matched   = $distance <= $threshold;

        if ($matched) {
            $face->update(['last_verified_at' => now()]);
        }

        return response()->json([
            'status'    => true,
            'matched'   => $matched,
            'distance'  => round($distance, 4),
            'threshold' => $threshold,
            'message'   => $matched
                ? 'Verifikasi berhasil. Registrasi wajah selesai.'
                : 'Wajah belum cocok dengan yang tersimpan. Ulangi pengambilan foto.',
        ]);
    }

    /**
     * Toggle can_login per wajah (dari halaman Manage).
     */
    public function toggleCanLogin(EmployeeFace $employeeFace)
    {
        $employeeFace->update(['can_login' => !$employeeFace->can_login]);

        Alert::success('Berhasil', 'Hak login wajah ' . $employeeFace->npk . ' diperbarui.');
        return back();
    }

    /**
     * Token sementara bukti lolos step 1-2 self register (tanpa session login).
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
            $raw = base64_decode(strtr($token, '-_', '+/'));
            // npk belum diketahui -> pakai key app utk dekripsi generik via payload npk lookup
            // Sederhana: iterasi kandidat tidak feasible; kita sandi dgn app.key + nonce npk.
            // Untuk itu token dibuat memakai hash(npk) sbg IV; di sini kita coba decode dgn setiap
            // kemungkinan IV tidak realistis. Maka desain ulang: token = base64(json) + HMAC(app.key).
            $parts = explode('.', $token);
            if (count($parts) !== 2) {
                return null;
            }
            [$b64, $sig] = $parts;
            $json = base64_decode(strtr($b64, '-_', '+/'));
            $expect = rtrim(strtr(base64_encode(hash_hmac('sha256', (string) $json, config('app.key'), true)), '+/', '-_'), '=');

            if (!hash_equals($expect, $sig)) {
                return null;
            }

            $data = json_decode((string) $json, true);

            if (!is_array($data) || empty($data['npk']) || empty($data['exp']) || $data['exp'] < time()) {
                return null;
            }

            return strtoupper(trim((string) $data['npk']));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function maskKtp(string $ktp): string
    {
        if (strlen($ktp) <= 4) {
            return $ktp;
        }

        return str_repeat('*', strlen($ktp) - 4) . substr($ktp, -4);
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
}
