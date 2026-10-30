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
    ];

    protected $casts = [
        'is_enabled'            => 'boolean',
        'match_threshold'       => 'float',
        'is_qr_login_enabled'   => 'boolean',
        'is_qr_payroll_enabled' => 'boolean',
        'is_qr_thr_enabled'     => 'boolean',
        'is_qr_cuti_enabled'    => 'boolean',
    ];

    /**
     * Setting face recognition bersifat singleton (1 baris).
     */
    public static function current(): self
    {
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
            $setting->save();
        }

        return $setting;
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
}
