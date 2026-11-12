<?php

namespace App\Services;

use App\Models\EmployeeFace;
use App\Models\FaceRecognitionSetting;

class FaceRecognitionService
{
    /**
     * Panjang descriptor face-api.js (faceRecognitionNet).
     */
    public const DESCRIPTOR_LENGTH = 128;

    /**
     * Jarak maksimum antar descriptor agar dianggap orang yang sama.
     * Nilai 0.5 adalah rekomendasi umum face-api.js (0.4 ketat - 0.6 longgar).
     */
    public const DEFAULT_THRESHOLD = 0.500;

    /**
     * Selisih minimum antara kandidat terbaik dan kandidat kedua.
     * Mencegah hasil ambigu (dua wajah yang sangat mirip).
     */
    public const MIN_MARGIN = 0.030;

    public static function settings(): FaceRecognitionSetting
    {
        try {
            return FaceRecognitionSetting::current();
        } catch (\Throwable $e) {
            $fallback = new FaceRecognitionSetting();
            $fallback->is_enabled = true;
            $fallback->match_threshold = 0.500;
            $fallback->is_qr_login_enabled   = true;
            $fallback->is_qr_payroll_enabled = true;
            $fallback->is_qr_thr_enabled     = true;
            $fallback->is_qr_cuti_enabled    = true;
            return $fallback;
        }
    }

    public static function isEnabled(): bool
    {
        try {
            return (bool) static::settings()->is_enabled;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function threshold(): float
    {
        try {
            $value = static::settings()->match_threshold;

            if (!is_numeric($value) || $value <= 0) {
                return static::DEFAULT_THRESHOLD;
            }

            return (float) $value;
        } catch (\Throwable $e) {
            return static::DEFAULT_THRESHOLD;
        }
    }

    /**
     * Daftar menu yang punya sakelar liveness sendiri.
     * PERBAIKAN V31.3d: 'registrasi' ikut, supaya halaman
     * /self-register-face bisa diatur terpisah dari presensi.
     *
     * @var array<int,string>
     */
    public const MENU_LIVENESS = ['presensi', 'login', 'slip', 'cuti', 'registrasi'];

    /**
     * Menu yang punya sakelar random challenge sendiri.
     * Sama dengan MENU_LIVENESS supaya kedua fitur bisa dikontrol
     * di halaman yang persis sama.
     *
     * @var array<int,string>
     */
    public const MENU_RANDOM_CHALLENGE = ['presensi', 'login', 'slip', 'cuti', 'registrasi'];

    /**
     * Random Challenge (anti-kecurangan).
     *
     * Berbeda dengan liveness: liveness selalu challenge yang sama
     * (berkedip ATAU menoleh), sehingga bisa direkam sekali lalu dipakai
     * berulang. Random challenge MEMILIH tantangannya secara acak dari
     * daftar (hadap kiri / hadap kanan / buka mulut) sehingga rekaman
     * sebelumnya tidak bisa memenuhi tantangan yang sedang aktif.
     *
     * ORTogonal terhadap liveness: dua fitur ini berdiri sendiri. Menyalakan
     * random challenge tidak mengubah ambang/syarat liveness sama sekali.
     *
     * Aturan:
     *   - Master OFF  -> semua halaman OFF.
     *   - Master ON   -> ikut switch per menu.
     *   - $menu null  -> hanya master yang menentukan.
     *
     * Default: master FALSE (fitur baru belum diaktifkan), switch per menu TRUE.
     * Bila kolom belum dimigrasi / DB error -> FALSE, supaya fitur baru yang
     * belum diaktifkan secara sengaja tidak tiba-tiba aktif di produksi.
     *
     * @param string|null $menu  presensi|login|slip|cuti|registrasi
     */
    public static function isRandomChallengeEnabled(?string $menu = null): bool
    {
        try {
            $settings = static::settings();

            if (!$settings->is_random_challenge_enabled) {
                return false;
            }

            if ($menu === null) {
                return true;
            }

            $column = 'random_challenge_' . strtolower(trim($menu)) . '_enabled';

            $value = $settings->{$column};

            return $value !== null ? (bool) $value : true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Daftar challenge yang tersedia untuk random challenge.
     * Dipakai juga oleh server saat menerbitkan challenge token.
     *
     * @var array<int,string>
     */
    public const RANDOM_CHALLENGE_TYPES = ['left', 'right', 'mouth'];

    /**
     * Teks petunjuk (Bahasa Indonesia) untuk tiap jenis challenge.
     *
     * PERBAIKAN V38 (2026-11-10): challenge dulu hanya mengembalikan
     * type/token, sehingga banner instruksi di semua halaman hanya
     * menampilkan teks generik ("Ikuti tantangan yang diminta"). User
     * tidak tahu harus bergerak apa, jadi challenge mustahil di-trigger.
     * Petunjuk ini sekarang ikut dikirim ke view.
     *
     * CATATAN: teks memakai arah LAYAR (kiri/kanan yang terlihat user),
     * sama seperti RandomChallenge.HINTS di public/js/random-challenge.js,
     * karena preview kamera depan di-mirror.
     *
     * @var array<string,string>
     */
    public const RANDOM_CHALLENGE_HINTS = [
        'left'  => 'Hadapkan wajah ke KIRI layar',
        'right' => 'Hadapkan wajah ke KANAN layar',
        'mouth' => 'BUKA MULUT Anda',
    ];

    /**
     * Terbitkan challenge acak untuk satu sesi/percobaan.
     *
     * Challenge disimpan di cache ber-TTL pendek (default 5 menit) dengan
     * token acak. Frontend tidak bisa menebak challenge aktif, dan challenge
     * yang sama tidak bisa dipakai ulang di luar jendela cache-nya.
     *
     * @param string $scope  kunci unik per halaman/percobaan
     * @param int    $ttlSeconds
     * @return array{token:string,type:string,expires_in:int}
     */
    public static function issueRandomChallenge(string $scope, int $ttlSeconds = 300): array
    {
        $type = static::RANDOM_CHALLENGE_TYPES[array_rand(static::RANDOM_CHALLENGE_TYPES)];

        $token = bin2hex(random_bytes(16));

        \Illuminate\Support\Facades\Cache::put(
            'face_random_challenge:' . $scope,
            ['type' => $type, 'issued_at' => now()->toIso8601String()],
            now()->addSeconds($ttlSeconds)
        );

        return [
            'token'      => $token,
            'type'       => $type,
            // PERBAIKAN V38 (2026-11-10): kirim teks petunjuk supaya banner
            // instruksi di view menampilkan gerakan yang spesifik.
            'hint'       => static::RANDOM_CHALLENGE_HINTS[$type] ?? 'Ikuti tantangan yang diminta',
            'expires_in' => $ttlSeconds,
        ];
    }

    /**
     * Ambil challenge yang sedang aktif untuk sebuah scope.
     *
     * @return array{type:string,issued_at:string}|null
     */
    public static function getRandomChallenge(string $scope): ?array
    {
        $data = \Illuminate\Support\Facades\Cache::get('face_random_challenge:' . $scope);

        return is_array($data) && isset($data['type']) ? $data : null;
    }

    /**
     * Peta random challenge untuk semua menu, siap dikirim ke view.
     *
     * @return array<string,bool>
     */
    public static function randomChallengeMap(): array
    {
        $map = [];

        foreach (static::MENU_RANDOM_CHALLENGE as $menu) {
            $map[$menu] = static::isRandomChallengeEnabled($menu);
        }

        return $map;
    }

    /**
     * PERBAIKAN 25 -- Toggle Liveness Check.
     *
     * Titik tunggal yang dibaca SEMUA halaman face recognition supaya tidak
     * ada halaman yang lupa menghormati setting admin.
     *
     * Aturan:
     *   - Master OFF  -> semua halaman OFF, apa pun setting per menunya.
     *   - Master ON   -> ikut switch per menu ($menu).
     *   - $menu null  -> hanya master yang menentukan.
     *
     * Jika kolom belum dimigrasi / DB error, default TRUE (liveness tetap
     * aktif). Sengaja begitu supaya kegagalan migrasi tidak diam-diam
     * MELEMAHKAN keamanan presensi.
     *
     * @param string|null $menu  presensi|login|slip|cuti|registrasi
     */
    public static function isLivenessEnabled(?string $menu = null): bool
    {
        try {
            $settings = static::settings();

            if (!$settings->is_liveness_enabled) {
                return false;
            }

            if ($menu === null) {
                return true;
            }

            $column = 'liveness_' . strtolower(trim($menu)) . '_enabled';

            $value = $settings->{$column};

            return $value !== null ? (bool) $value : true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Peta liveness untuk semua menu, siap dikirim ke view sebagai JSON.
     *
     * @return array<string,bool>
     */
    public static function livenessMap(): array
    {
        $map = [];

        // PERBAIKAN V31.3d: pakai konstanta MENU_LIVENESS supaya daftar menu
        // hanya ada di SATU tempat. Dulu array ini ditulis ulang di sini,
        // jadi menambah menu baru mudah terlewat.
        foreach (static::MENU_LIVENESS as $menu) {
            $map[$menu] = static::isLivenessEnabled($menu);
        }

        return $map;
    }

    public static function isQrLoginEnabled(): bool
    {
        try {
            $settings = static::settings();
            return isset($settings->is_qr_login_enabled) ? (bool) $settings->is_qr_login_enabled : true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERBAIKAN V13 (2026-11-06): toggle "Input NPK Dulu"
    |--------------------------------------------------------------------------
    | Satu sumber kebenaran untuk semua halaman yang memakai face login:
    | presensi, slip gaji, dan pengajuan cuti.
    */
    public static function isNpkRequiredEnabled(): bool
    {
        try {
            return (bool) static::settings()->is_npk_required_enabled;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERBAIKAN V13 (2026-11-06): validasi NPK sebelum kamera menyala
    |--------------------------------------------------------------------------
    | Dipakai bersama oleh /face-attendance/login, /employee-payroll, dan
    | /pengajuan-cuti/login agar tidak ada duplikasi logika.
    |
    | Mengembalikan array:
    |   ok            => bool   NPK boleh lanjut ke kamera
    |   status        => int    HTTP status (200/403/404/422/500)
    |   npk, name     => string
    |   has_face      => bool
    |   inactive      => bool   karyawan sudah keluar (PKWT.TKK terisi)
    |   message       => string
    */
    public static function validateNpkForFace(string $npk): array
    {
        $npk = trim($npk);

        if ($npk === '') {
            return [
                'ok'       => false,
                'status'   => 422,
                'npk'      => '',
                'name'     => '',
                'has_face' => false,
                'inactive' => false,
                'message'  => 'NPK wajib diisi.',
            ];
        }

        try {
            $biodata = \Illuminate\Support\Facades\DB::table('BIODATA')
                ->where('NPK', $npk)
                ->select('NPK', 'NAMA_KARYAWAN')
                ->first();
        } catch (\Throwable $e) {
            \Log::warning('validateNpkForFace gagal baca BIODATA: ' . $e->getMessage());

            return [
                'ok'       => false,
                'status'   => 500,
                'npk'      => $npk,
                'name'     => '',
                'has_face' => false,
                'inactive' => false,
                'message'  => 'Gagal memuat data karyawan. Silakan coba lagi.',
            ];
        }

        if (!$biodata) {
            return [
                'ok'       => false,
                'status'   => 404,
                'npk'      => $npk,
                'name'     => '',
                'has_face' => false,
                'inactive' => false,
                'message'  => 'NPK ' . $npk . ' tidak ditemukan.',
            ];
        }

        $name = (string) ($biodata->NAMA_KARYAWAN ?? $npk);

        // Karyawan keluar (PKWT.TKK terisi) tidak boleh lanjut face attendance.
        $activeStatus = EmployeeActiveStatusService::check($npk);
        if ($activeStatus['found'] && !$activeStatus['active']) {
            return [
                'ok'       => false,
                'status'   => 403,
                'npk'      => $npk,
                'name'     => $name,
                'has_face' => false,
                'inactive' => true,
                'message'  => EmployeeActiveStatusService::inactiveMessage(
                    $activeStatus['nama'],
                    $activeStatus['tkk']
                ),
            ];
        }

        $hasFace = false;
        try {
            $hasFace = EmployeeFace::query()
                ->where('npk', $npk)
                ->where('is_active', true)
                ->whereNotNull('descriptor')
                ->exists();
        } catch (\Throwable $e) {
            \Log::warning('validateNpkForFace gagal baca employee_faces: ' . $e->getMessage());
        }

        if (!$hasFace) {
            return [
                'ok'       => false,
                'status'   => 422,
                'npk'      => $npk,
                'name'     => $name,
                'has_face' => false,
                'inactive' => false,
                'message'  => 'Wajah untuk NPK ' . $npk . ' belum terdaftar. Silakan hubungi admin.',
            ];
        }

        return [
            'ok'       => true,
            'status'   => 200,
            'npk'      => $npk,
            'name'     => $name,
            'has_face' => true,
            'inactive' => false,
            'message'  => 'NPK valid. Silakan posisikan wajah di kamera.',
        ];
    }

    public static function isQrPayrollEnabled(): bool
    {
        try {
            $settings = static::settings();
            return isset($settings->is_qr_payroll_enabled) ? (bool) $settings->is_qr_payroll_enabled : true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function isQrThrEnabled(): bool
    {
        try {
            $settings = static::settings();
            return isset($settings->is_qr_thr_enabled) ? (bool) $settings->is_qr_thr_enabled : true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function isQrCutiEnabled(): bool
    {
        try {
            $settings = static::settings();
            return isset($settings->is_qr_cuti_enabled) ? (bool) $settings->is_qr_cuti_enabled : true;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Ubah descriptor (array / JSON string) menjadi array float 128.
     */
    public static function normalizeDescriptor($descriptor): ?array
    {
        if (is_string($descriptor)) {
            $descriptor = json_decode($descriptor, true);
        }

        if (!is_array($descriptor) || count($descriptor) !== static::DESCRIPTOR_LENGTH) {
            return null;
        }

        $normalized = [];

        foreach ($descriptor as $value) {
            if (!is_numeric($value)) {
                return null;
            }

            $normalized[] = (float) $value;
        }

        return $normalized;
    }

    public static function euclidean(array $a, array $b): float
    {
        $length = min(count($a), count($b));
        $sum    = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $diff = (float) $a[$i] - (float) $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Cari wajah terdekat dari semua data aktif.
     *
     * @return array{face: EmployeeFace|null, distance: float|null, runner_up: float|null, margin: float|null}
     */
    public static function match(array $descriptor): array
    {
        // PERBAIKAN V33 (2026-11-09): select kolom ringan saja.
        // Query tanpa select() mengangkut face_img (base64 foto,
        // ratusan KB per karyawan) yang TIDAK dipakai di match --
        // hanya npk/name/descriptor yang dibutuhkan. Tanpa select,
        // 2.000 karyawan * 500KB = ~1GB diangkut per scan.
        $faces = EmployeeFace::query()
            ->select(['id', 'npk', 'name', 'descriptor', 'is_active'])
            ->where('is_active', true)
            ->whereNotNull('descriptor')
            ->get();

        $best         = null;
        $bestDistance = null;
        $runnerUp     = null;

        foreach ($faces as $face) {
            $stored = static::normalizeDescriptor($face->descriptor);

            if ($stored === null) {
                continue;
            }

            $distance = static::euclidean($descriptor, $stored);

            if ($bestDistance === null || $distance < $bestDistance) {
                $runnerUp     = $bestDistance;
                $bestDistance = $distance;
                $best         = $face;
            } elseif ($runnerUp === null || $distance < $runnerUp) {
                $runnerUp = $distance;
            }
        }

        $margin = ($bestDistance !== null && $runnerUp !== null)
            ? ($runnerUp - $bestDistance)
            : null;

        return [
            'face'       => $best,
            'distance'   => $bestDistance,
            'runner_up'  => $runnerUp,
            'margin'     => $margin,
        ];
    }

    /**
     * PERBAIKAN V33 (2026-11-05): pencocokan wajah TERBATAS pada satu NPK.
     *
     * Dipakai oleh halaman /face-attendance/login ketika setting
     * "input NPK dulu" aktif. User sudah mengetik NPK, jadi descriptor
     * wajahnya hanya perlu dibandingkan dengan data wajah karyawan
     * dengan NPK tersebut - bukan mencari ke seluruh karyawan.
     *
     * Keuntungan:
     *   1. Tidak mungkin salah mengambil wajah orang lain, karena kandidat
     *      lain tidak pernah dipertimbangkan sama sekali.
     *   2. Jauh lebih cepat (hanya 1 baris employee_faces yang dibaca),
     *      bukan seluruh tabel.
     *
     * @param array    $descriptor Descriptor 128 float.
     * @param string   $npk        NPK karyawan yang diinput user.
     * @return array{face: EmployeeFace|null, distance: float|null, runner_up: float|null, margin: float|null}
     */
    public static function matchByNpk(array $descriptor, string $npk): array
    {
        $npk = trim($npk);

        if ($npk === '') {
            return [
                'face'      => null,
                'distance'  => null,
                'runner_up' => null,
                'margin'    => null,
            ];
        }

        try {
            $face = EmployeeFace::query()
                ->select(['id', 'npk', 'name', 'descriptor', 'is_active'])
                ->where('npk', $npk)
                ->where('is_active', true)
                ->whereNotNull('descriptor')
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            \Log::warning('FaceRecognitionService::matchByNpk gagal: ' . $e->getMessage());

            return [
                'face'      => null,
                'distance'  => null,
                'runner_up' => null,
                'margin'    => null,
            ];
        }

        if (!$face) {
            return [
                'face'      => null,
                'distance'  => null,
                'runner_up' => null,
                'margin'    => null,
            ];
        }

        $stored = static::normalizeDescriptor($face->descriptor);

        if ($stored === null) {
            return [
                'face'      => null,
                'distance'  => null,
                'runner_up' => null,
                'margin'    => null,
            ];
        }

        $distance = static::euclidean($descriptor, $stored);

        // Runner-up tidak ada (hanya satu kandidat), jadi margin = null.
        // isConfidentMatch() memperlakukan margin null sebagai "lolos",
        // karena tidak ada kandidat lain yang bisa dibandingkan.
        return [
            'face'      => $face,
            'distance'  => $distance,
            'runner_up' => null,
            'margin'    => null,
        ];
    }

    /**
     * Apakah hasil pencocokan cukup yakin untuk dipakai.
     */
    public static function isConfidentMatch(array $result, ?float $threshold = null): bool
    {
        if (empty($result['face']) || $result['distance'] === null) {
            return false;
        }

        $threshold = $threshold ?? static::threshold();

        if ($result['distance'] > $threshold) {
            return false;
        }

        if ($result['margin'] !== null && $result['margin'] < static::MIN_MARGIN) {
            return false;
        }

        return true;
    }
}
