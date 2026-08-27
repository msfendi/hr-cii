<?php

namespace App\Http\Controllers;

use App\Models\QrAuthorizedDevice;
use App\Models\QrScanLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;

class LoginController extends Controller
{
    public function login()
    {
        return view('auth.login');
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

        if (Auth::attempt([
            'email'    => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            $request->session()->regenerate();

            Alert::success('Login Successfully!', 'Welcome To Chutex HRIS Sistem');
            return redirect()->intended('/welcome');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout()
    {
        Auth::logout();
        Alert::success('Logout Successfully!', 'See You Next Time');
        return redirect('/login');
    }

    public function qrauth(Request $request)
    {
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
}
