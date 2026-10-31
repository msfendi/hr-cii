<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FaceAttendanceSetting extends Model
{
    protected $table = 'face_attendance_settings';

    protected $fillable = [
        'clock_in_before_minutes',
        'clock_out_after_minutes',
        'is_saturday_enabled',
        'is_sunday_enabled',
        'is_holiday_enabled',
    ];

    protected $casts = [
        'clock_in_before_minutes'  => 'integer',
        'clock_out_after_minutes'  => 'integer',
        'is_saturday_enabled'      => 'boolean',
        'is_sunday_enabled'        => 'boolean',
        'is_holiday_enabled'       => 'boolean',
    ];

    /**
     * Setting window presensi bersifat singleton (1 baris).
     */
    public static function current(): self
    {
        try {
            $setting = static::query()->first();

            if (!$setting) {
                $setting = new static();
                $setting->clock_in_before_minutes = 15;
                $setting->clock_out_after_minutes = 15;
                $setting->is_saturday_enabled = false;
                $setting->is_sunday_enabled = false;
                $setting->is_holiday_enabled = false;

                try {
                    $setting->save();
                } catch (\Throwable $e) {
                    // Abaikan jika DB read-only
                }
            }

            return $setting;
        } catch (\Throwable $e) {
            $fallback = new static();
            $fallback->clock_in_before_minutes = 15;
            $fallback->clock_out_after_minutes = 15;
            $fallback->is_saturday_enabled = false;
            $fallback->is_sunday_enabled = false;
            $fallback->is_holiday_enabled = false;

            return $fallback;
        }
    }

    /**
     * Cek apakah hari ini merupakan hari kerja yang diizinkan untuk presensi wajah.
     * Senin-Jumat selalu aktif. Sabtu/Minggu dan hari libur mengikuti toggle.
     */
    public static function isAttendanceDayAllowed(?\Carbon\Carbon $date = null): bool
    {
        $date = $date ?: \Carbon\Carbon::today();
        $setting = static::current();

        // Senin (1) s.d Jumat (5) selalu diizinkan
        if ($date->dayOfWeekIso >= 1 && $date->dayOfWeekIso <= 5) {
            return true;
        }

        // Sabtu (6)
        if ($date->dayOfWeekIso === 6) {
            return (bool) $setting->is_saturday_enabled;
        }

        // Minggu (7)
        if ($date->dayOfWeekIso === 7) {
            return (bool) $setting->is_sunday_enabled;
        }

        return false;
    }

    /**
     * Cek apakah tanggal tersebut adalah hari libur (dari tabel holidays).
     */
    public static function isHoliday(\Carbon\Carbon $date): bool
    {
        try {
            if (!Schema::hasTable('holidays')) {
                return false;
            }

            $dateStr = $date->format('Y-m-d');

            return \Illuminate\Support\Facades\DB::table('holidays')
                ->whereDate('holiday_date', $dateStr)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
