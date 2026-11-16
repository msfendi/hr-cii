<?php

namespace App\Http\Controllers;

use App\Models\QrAuthorizedDevice;
use App\Models\QrScanLog;
use App\Models\User;
use App\Services\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RealRashid\SweetAlert\Facades\Alert;

class LoginController extends Controller
{
    public function login()
    {
        // Random challenge (anti-kecurangan) ORTogonal terhadap liveness.
        $rcEnabled = FaceRecognitionService::isRandomChallengeEnabled('login');
        $rcChallenge = $rcEnabled
            ? FaceRecognitionService::issueRandomChallenge('auth-login-' . session()->getId())
            : null;

        return view('auth.login', [
            'faceEnabled' => FaceRecognitionService::isEnabled(),
            'qrEnabled'   => FaceRecognitionService::isQrLoginEnabled(),
            // PERBAIKAN V31.3c (2026-11-04): status liveness dari Settings
            // untuk menu "login" DULU tidak pernah dikirim ke view, sehingga
            // walau toggle di Settings ON, halaman /login tidak tahu apa-apa
            // dan tidak pernah memeriksa liveness.
            'livenessEnabled' => FaceRecognitionService::isLivenessEnabled('login'),
            'randomChallengeEnabled' => $rcEnabled,
            'randomChallenge' => $rcChallenge,
        ]);
    }

    public function captchaImage(Request $request)
    {
        $width  = 150;
        $height = 50;

        // --- generate teks acak ---
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // hindari karakter mirip (0/O, 1/I)
        $length = 5;
        $text   = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // simpan jawaban di session (server-side, tidak terlihat di client)
        session(['captcha_answer' => $text]);

        // --- buat gambar ---
        $image = imagecreatetruecolor($width, $height);

        $bgColor = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        for ($i = 0; $i < 6; $i++) {
            $lineColor = imagecolorallocate($image, random_int(150, 200), random_int(150, 200), random_int(150, 200));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
        }

        for ($i = 0; $i < 150; $i++) {
            $dotColor = imagecolorallocate($image, random_int(150, 200), random_int(150, 200), random_int(150, 200));
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
        }

        $x = 15;
        foreach (str_split($text) as $char) {
            $y = random_int(10, 20);
            $charColor = imagecolorallocate($image, random_int(0, 100), random_int(0, 100), random_int(0, 100));
            imagestring($image, 5, $x, $y, $char, $charColor);
            $x += 22;
        }

        // ✅ tangkap output gambar ke buffer, JANGAN langsung echo + exit
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        // ✅ kembalikan sebagai Response biasa supaya session sempat di-save oleh Laravel
        return response($imageData, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'captcha'  => ['required', 'string'],
        ]);

        // cek captcha, case-insensitive, tidak peduli huruf besar/kecil
        if (strtoupper($request->captcha) !== strtoupper((string) session('captcha_answer'))) {
            session()->forget('captcha_answer');

            return back()->withErrors([
                'captcha' => 'Kode captcha salah, silakan coba lagi.',
            ])->onlyInput('email');
        }

        // captcha benar → hapus supaya tidak bisa dipakai ulang (replay)
        session()->forget('captcha_answer');

        // Jika kredensial benar tetapi akun dinonaktifkan → beri pesan khusus
        $existing = User::where('email', $credentials['email'])->first();
        if (
            $existing
            && (int) $existing->disabled === 1
            && Hash::check($credentials['password'], $existing->password)
        ) {
            return back()->withErrors([
                'email' => 'Akun Anda dinonaktifkan. Silakan hubungi admin HRIS.',
            ])->onlyInput('email');
        }

        // 'disabled' => 0 ikut menjadi kondisi WHERE, jadi hanya user aktif yang bisa login
        if (Auth::attempt([
            'email'    => $credentials['email'],
            'password' => $credentials['password'],
            'disabled' => 0,
        ])) {
            $request->session()->regenerate();

            Alert::success('Login Successfully!', 'Welcome To Chutex HRIS Sistem');

            // Portal v2 (/v2/login): password login diarahkan ke /welcome (bukan /home).
            if ($request->input('redirect_to') === 'welcome') {
                return redirect()->intended(route('welcome'));
            }

            // Pemanggil lama yang masih mengirim redirect_to=home tetap dilayani.
            if ($request->input('redirect_to') === 'home') {
                return redirect()->intended(route('home'));
            }

            return redirect()->intended(route('welcome'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        // Hapus seluruh kunci sesi subsistem (Portal Karyawan v2, presensi wajah,
        // pengajuan cuti). Tanpa ini logout hanya melepas guard 'web', sedangkan
        // halaman yang membaca kunci sesi tetap menganggap user masih login.
        $request->session()->forget([
            'hris_v2_npk', 'hris_v2_nama', 'hris_v2_login_at', 'hris_v2_max_minutes',
            'hris_v2_pending_npk', 'hris_v2_pending_nama', 'hris_v2_npk_confirmed',
            'face_attendance_npk', 'cuti_employee_npk',
        ]);

        Auth::logout();

        // Sesi lama harus dibuang dan token CSRF diperbarui, kalau tidak sesi yang
        // sama masih bisa dipakai ulang setelah logout.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Alert::success('Logout Successfully!', 'See You Next Time');
        // Arahkan ke halaman utama publik, bukan ke /home atau halaman login v2.
        return redirect('/');
    }

    public function qrauth(Request $request)
    {
        if (!FaceRecognitionService::isQrLoginEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Login QR Code sedang dinonaktifkan oleh administrator.',
            ], 403);
        }

        $request->validate([
            'qrcode'      => ['required', 'string'],
            'device_uuid' => ['required', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', 'max:20'],
            'platform'    => ['nullable', 'string', 'max:100'],
            'browser'     => ['nullable', 'string', 'max:100'],
        ]);

        $npk        = trim($request->qrcode);
        $deviceUuid = $request->device_uuid;

        $log = [
            'npk_scanned' => $npk,
            'device_uuid' => $deviceUuid,
            'device_name' => $request->device_name,
            'device_type' => $request->device_type ?? 'unknown',
            'platform'    => $request->platform,
            'browser'     => $request->browser,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ];

        // 1. Validasi format NPK
        if (!preg_match('/^C-\d{5}$/', $npk)) {
            QrScanLog::create($log + ['status' => 'failed_invalid_format']);
            return back()->with('error', 'Format NPK salah');
        }

        // 2. Cari user
        $user = User::where('npk', $npk)->first();

        if (!$user) {
            QrScanLog::create($log + ['status' => 'failed_user_not_found']);
            return back()->with('error', 'User tidak ditemukan');
        }

        $log['user_id'] = $user->id;

        // 2b. Cek user aktif (users.disabled = 0)
        if ((int) $user->disabled === 1) {
            QrScanLog::create($log + ['status' => 'failed_user_disabled']);

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi admin HRIS.'
            ], 403);
        }

        // 3. Cek device sudah diassign admin untuk user ini
        $device = QrAuthorizedDevice::where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if (!$device) {
            QrScanLog::create($log + ['status' => 'failed_device_not_registered']);

            return response()->json([
                'success' => false,
                'message' => 'Device ini belum terdaftar untuk akun Anda. Silakan hubungi admin HRIS.'
            ], 403);
        }

        if (!$device->is_active) {
            QrScanLog::create($log + ['status' => 'failed_device_inactive']);

            return response()->json([
                'success' => false,
                'message' => 'Device ini dinonaktifkan oleh admin.'
            ], 403);
        }

        // 4. Sukses
        QrScanLog::create($log + ['status' => 'success']);
        $device->update(['last_used_at' => now()]);

        Auth::login($user);
        $request->session()->regenerate();

        Alert::success('Login Successfully!', 'Welcome To Chutex HRIS');

        return response()->json([
            'success' => true,
            'redirect' => url('/home')
        ]);
    }

    /**
     * Login menggunakan Face Recognition.
     *
     * NPK hasil pengenalan wajah dicocokkan dengan kolom npk di tabel users.
     */
    public function faceAuth(Request $request)
    {
        return $this->prosesFaceAuth($request, 'id');
    }

    /**
     * Inti login wajah, dipakai dua halaman:
     *  - v1 /login          -> faceAuth()      (bahasa Indonesia, tanpa NPK)
     *  - v2 /v2/login       -> staffFaceAuth() (bahasa Inggris, NPK wajib)
     *
     * Bila pemanggil mengirim "npk", descriptor HANYA dicocokkan dengan data
     * wajah milik NPK tersebut (FaceRecognitionService::matchByNpk). Ini jauh
     * lebih akurat dan tidak mungkin mengambil wajah karyawan lain.
     *
     * @param string $bahasa 'id' atau 'en'
     */
    public function prosesFaceAuth(Request $request, string $bahasa = 'id')
    {
        $en = $bahasa === 'en';

        $pesan = [
            'disabled'    => $en ? 'Face recognition is disabled by admin. Use password login.' : 'Face recognition sedang dinonaktifkan oleh admin.',
            'descriptor'  => $en ? 'Invalid face data. Please try again.' : 'Data wajah tidak valid. Silakan coba lagi.',
            'no_face_npk' => $en ? 'No face data is registered for NPK %s. Register your face first or use password login.' : 'Belum ada data wajah untuk NPK %s. Daftarkan wajah Anda dulu atau gunakan login password.',
            'not_match'   => $en ? 'Face does not match NPK %s. Make sure the NPK is yours and look straight at the camera.' : 'Wajah tidak cocok dengan NPK %s. Pastikan NPK milik Anda dan hadap lurus ke kamera.',
            'unknown'     => $en ? 'Face not recognized. Make sure your face is registered in Manage Face Recognition.' : 'Wajah tidak dikenali. Pastikan wajah sudah terdaftar di menu Manage Face Recognition.',
            'no_login'    => $en ? 'Face login is disabled for your account. Please contact the admin.' : 'Izin login menggunakan wajah dinonaktifkan untuk akun Anda. Harap hubungi admin.',
            'no_user'     => $en ? 'Face recognized as %s, but that NPK is not registered as an HRIS user.' : 'Wajah dikenali sebagai %s, tetapi NPK tersebut belum terdaftar sebagai user HRIS.',
            'account_off' => $en ? 'Your account is disabled. Please contact the HRIS admin.' : 'Akun Anda dinonaktifkan. Silakan hubungi admin HRIS.',
        ];

        if (!FaceRecognitionService::isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => $pesan['disabled'],
            ], 403);
        }

        $descriptor = FaceRecognitionService::normalizeDescriptor($request->input('descriptor'));

        if ($descriptor === null) {
            return response()->json([
                'success' => false,
                'message' => $pesan['descriptor'],
            ], 422);
        }

        $npk = strtoupper(trim((string) $request->input('npk', '')));

        if ($npk !== '') {
            $result = FaceRecognitionService::matchByNpk($descriptor, $npk);

            if (empty($result['face'])) {
                // Belum ada baris employee_faces untuk NPK ini.
                return response()->json([
                    'success' => false,
                    'message' => sprintf($pesan['no_face_npk'], $npk),
                ], 404);
            }

            if (!FaceRecognitionService::isConfidentMatch($result)) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf($pesan['not_match'], $npk),
                ], 401);
            }
        } else {
            $result = FaceRecognitionService::match($descriptor);

            if (!FaceRecognitionService::isConfidentMatch($result)) {
                return response()->json([
                    'success' => false,
                    'message' => $pesan['unknown'],
                ], 401);
            }
        }

        $face = $result['face'];

        // Pengaman tambahan: kalau NPK dikirim, hasil wajib milik NPK itu.
        if ($npk !== '' && strtoupper((string) $face->npk) !== $npk) {
            return response()->json([
                'success' => false,
                'message' => sprintf($pesan['not_match'], $npk),
            ], 401);
        }

        if (isset($face->can_login) && (int) $face->can_login === 0) {
            return response()->json([
                'success' => false,
                'message' => $pesan['no_login'],
            ], 403);
        }

        $user = User::where('npk', $face->npk)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => sprintf($pesan['no_user'], $face->npk),
            ], 403);
        }

        if ((int) $user->disabled === 1) {
            return response()->json([
                'success' => false,
                'message' => $pesan['account_off'],
            ], 403);
        }

        $face->update(['last_verified_at' => now()]);

        Auth::login($user);
        $request->session()->regenerate();

        Alert::success('Login Successfully!', 'Welcome To Chutex HRIS');

        // Portal v2: login wajah juga diarahkan ke /welcome (bukan /home), kecuali
        // pemanggil lama yang secara eksplisit masih meminta /home.
        $tujuan = $request->input('redirect_to') === 'home' ? route('home') : route('welcome');

        return response()->json([
            'success'  => true,
            'npk'      => $user->npk,
            'name'     => $user->name,
            'redirect' => $tujuan,
        ]);
    }
}
