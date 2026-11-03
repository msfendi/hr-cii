<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FaceAttendanceSetting extends Model
{
    protected $table = 'face_attendance_settings';

    protected $fillable = [
        'clock_in_before_minutes',
        'clock_in_allowance_minutes',
        'clock_out_after_minutes',
        'clock_out_allowance_minutes',
        'is_saturday_enabled',
        'is_sunday_enabled',
        'is_holiday_enabled',
    ];

    protected $casts = [
        'clock_in_before_minutes'  => 'integer',
        'clock_in_allowance_minutes' => 'integer',
        'clock_out_after_minutes'  => 'integer',
        'clock_out_allowance_minutes' => 'integer',
        'is_saturday_enabled'      => 'boolean',
        'is_sunday_enabled'        => 'boolean',
        'is_holiday_enabled'       => 'boolean',
    ];

    /**
     * PERBAIKAN V31.2: apakah kolom allowance sudah ada di database.
     *
     * Dipakai supaya halaman tetap jalan walau `php artisan migrate` belum
     * dijalankan. Di SQL Server, mengakses kolom yang belum ada akan
     * melempar error dan membuat seluruh halaman presensi gagal.
     *
     * @var array<string,bool>
     */
    protected static $punyaKolomAllowance = [];

    public static function hasAllowanceColumn(string $kolom = 'clock_in_allowance_minutes'): bool
    {
        if (array_key_exists($kolom, static::$punyaKolomAllowance)) {
            return static::$punyaKolomAllowance[$kolom];
        }

        try {
            static::$punyaKolomAllowance[$kolom] = Schema::hasColumn(
                'face_attendance_settings',
                $kolom
            );
        } catch (\Throwable $e) {
            static::$punyaKolomAllowance[$kolom] = false;
        }

        return static::$punyaKolomAllowance[$kolom];
    }

    /**
     * Nilai allowance clock in dalam menit. 0 bila kolom belum ada.
     */
    public function allowanceMinutes(): int
    {
        if (!static::hasAllowanceColumn()) {
            return 0;
        }

        return max(0, (int) ($this->clock_in_allowance_minutes ?? 0));
    }

    /**
     * PERBAIKAN V31.2: allowance window Clock Out, dalam menit.
     *
     * Memperpanjang BATAS AKHIR window Clock Out.
     * Contoh: shift pulang 17:00, after 15, allowance 10 -> 17:00 sampai 17:25.
     * Nilai 0 (default) = perilaku sama seperti sebelumnya.
     *
     * Mengembalikan 0 bila kolomnya belum ada (migrasi belum dijalankan),
     * supaya halaman tidak error.
     */
    public function allowanceOutMinutes(): int
    {
        if (!static::hasAllowanceColumn('clock_out_allowance_minutes')) {
            return 0;
        }

        return max(0, (int) ($this->clock_out_allowance_minutes ?? 0));
    }

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
