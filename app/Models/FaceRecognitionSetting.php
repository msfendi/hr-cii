<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FaceRecognitionSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'match_threshold',
        'is_qr_login_enabled',
        'is_qr_payroll_enabled',
        'is_qr_thr_enabled',
        'is_qr_cuti_enabled',
        // PERBAIKAN 25: toggle liveness check
        'is_liveness_enabled',
        'liveness_presensi_enabled',
        'liveness_login_enabled',
        'liveness_slip_enabled',
        'liveness_cuti_enabled',
        // PERBAIKAN 4 (2026-11-06): toggle input NPK dulu
        'is_npk_required_enabled',
        // Random challenge (anti-kecurangan): master + per menu
        'is_random_challenge_enabled',
        'random_challenge_presensi_enabled',
        'random_challenge_login_enabled',
        'random_challenge_slip_enabled',
        'random_challenge_cuti_enabled',
        'random_challenge_registrasi_enabled',
    ];

    protected $casts = [
        'is_enabled'            => 'boolean',
        'match_threshold'       => 'float',
        'is_qr_login_enabled'   => 'boolean',
        'is_qr_payroll_enabled' => 'boolean',
        'is_qr_thr_enabled'     => 'boolean',
        'is_qr_cuti_enabled'    => 'boolean',
        // PERBAIKAN 25
        'is_liveness_enabled'       => 'boolean',
        'liveness_presensi_enabled' => 'boolean',
        'liveness_login_enabled'    => 'boolean',
        'liveness_slip_enabled'     => 'boolean',
        'liveness_cuti_enabled'     => 'boolean',
        // PERBAIKAN 4
        'is_npk_required_enabled'   => 'boolean',
        // Random challenge (anti-kecurangan)
        'is_random_challenge_enabled'         => 'boolean',
        'random_challenge_presensi_enabled'   => 'boolean',
        'random_challenge_login_enabled'      => 'boolean',
        'random_challenge_slip_enabled'       => 'boolean',
        'random_challenge_cuti_enabled'       => 'boolean',
        'random_challenge_registrasi_enabled' => 'boolean',
    ];

    /**
     * Setting face recognition bersifat singleton (1 baris).
     */
    public static function current(): self
    {
        try {
            $setting = static::query()->first();

            if (!$setting) {
                $setting = new static();
                $setting->is_enabled = true;
                $setting->match_threshold = 0.500;
                try {
                    if (Schema::hasColumn('face_recognition_settings', 'is_qr_login_enabled')) {
                        $setting->is_qr_login_enabled   = true;
                        $setting->is_qr_payroll_enabled = true;
                        $setting->is_qr_thr_enabled     = true;
                        $setting->is_qr_cuti_enabled    = true;
                    }
                } catch (\Throwable $e) {
                    // Skip jika kolom belum termigrasi
                }
                try {
                    if (Schema::hasColumn('face_recognition_settings', 'is_liveness_enabled')) {
                        $setting->is_liveness_enabled       = true;
                        $setting->liveness_presensi_enabled = true;
                        $setting->liveness_login_enabled    = true;
                        $setting->liveness_slip_enabled     = true;
                        $setting->liveness_cuti_enabled     = true;
                    }
                } catch (\Throwable $e) {
                    // Skip jika kolom belum termigrasi
                }
                try {
                    if (Schema::hasColumn('face_recognition_settings', 'is_npk_required_enabled')) {
                        $setting->is_npk_required_enabled = true;
                    }
                } catch (\Throwable $e) {
                    // Skip jika kolom belum termigrasi
                }
                try {
                    if (Schema::hasColumn('face_recognition_settings', 'is_random_challenge_enabled')) {
                        $setting->is_random_challenge_enabled = false;
                    }
                } catch (\Throwable $e) {
                    // Skip jika kolom belum termigrasi
                }
                try {
                    $setting->save();
                } catch (\Throwable $e) {
                    // PERBAIKAN 25 (2026-11-05): DULU error ditelan diam-diam,
                    // sehingga objek TIDAK tersimpan (exists=false) dikembalikan
                    // dan updateSettings() melakukan update pada objek hantu ->
                    // toggle liveness tampak "berhasil" tapi TIDAK tersimpan.
                    // Sekarang dicatat supaya kelihatan di log.
                    \Illuminate\Support\Facades\Log::warning(
                        'FaceRecognitionSetting::current() gagal membuat baris default: ' . $e->getMessage()
                    );
                }
            }

            return $setting;
        } catch (\Throwable $e) {
            $fallback = new static();
            $fallback->is_enabled = true;
            $fallback->match_threshold = 0.500;
            $fallback->is_qr_login_enabled   = true;
            $fallback->is_qr_payroll_enabled = true;
            $fallback->is_qr_thr_enabled     = true;
            $fallback->is_qr_cuti_enabled    = true;
            $fallback->is_liveness_enabled       = true;
            $fallback->liveness_presensi_enabled = true;
            $fallback->liveness_login_enabled    = true;
            $fallback->liveness_slip_enabled     = true;
            $fallback->liveness_cuti_enabled     = true;
            $fallback->is_npk_required_enabled   = true;
            $fallback->is_random_challenge_enabled = false;
            return $fallback;
        }
    }

    public function getIsQrLoginEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsQrPayrollEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsQrThrEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsQrCutiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    /*
    |--------------------------------------------------------------------------
    | PERBAIKAN 25 -- Accessor Liveness Check
    |--------------------------------------------------------------------------
    | Semua accessor null-safe (default TRUE). Jadi kalau migrasi belum jalan,
    | sistem TETAP berperilaku seperti sekarang (liveness aktif), tidak
    | tiba-tiba mematikan keamanan presensi.
    */

    public function getIsLivenessEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsNpkRequiredEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    /*
    |--------------------------------------------------------------------------
    | Static helper -- dipakai controller & view
    |--------------------------------------------------------------------------
    | FaceAttendanceController memanggil FaceRecognitionSetting::isNpkRequiredEnabled()
    | secara statik. Tanpa method ini akan fatal error:
    | "Call to undefined method App\Models\FaceRecognitionSetting::isNpkRequiredEnabled()".
    | Default TRUE supaya bila kolom belum termigrasi, langkah input NPK tetap aman.
    */

    public static function isNpkRequiredEnabled(): bool
    {
        try {
            return (bool) static::current()->is_npk_required_enabled;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'FaceRecognitionSetting::isNpkRequiredEnabled() gagal: ' . $e->getMessage()
            );
            return true;
        }
    }

    public function getLivenessPresensiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getLivenessLoginEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getLivenessSlipEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getLivenessCutiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor Random Challenge (anti-kecurangan)
    |--------------------------------------------------------------------------
    |
    | ORTogonal terhadap liveness: nilai default kolom master adalah FALSE
    | (fitur baru, belum diaktifkan sampai admin menyalakannya), sedangkan
    | switch per menu default TRUE supaya begitu master dinyalakan, semua
    | menu yang relevan langsung ikut aktif.
    |
    */

    public function getIsRandomChallengeEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : false;
    }

    public function getRandomChallengePresensiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeLoginEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeSlipEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeCutiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeRegistrasiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }
}
