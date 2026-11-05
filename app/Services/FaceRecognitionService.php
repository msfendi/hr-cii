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
        $faces = EmployeeFace::query()
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
