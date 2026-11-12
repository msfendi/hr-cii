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
        // PERBAIKAN 4 (2026-11-06): 'is_npk_required_enabled' dipindahkan
        // ke FaceRecognitionSetting (table face_recognition_settings).
    ];

    protected $casts = [
        'clock_in_before_minutes'  => 'integer',
        'clock_in_allowance_minutes' => 'integer',
        'clock_out_after_minutes'  => 'integer',
        'clock_out_allowance_minutes' => 'integer',
        'is_saturday_enabled'      => 'boolean',
        'is_sunday_enabled'        => 'boolean',
        'is_holiday_enabled'       => 'boolean',
        // PERBAIKAN 4 (2026-11-06): 'is_npk_required_enabled' dipindahkan
        // ke FaceRecognitionSetting.
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

    // PERBAIKAN V33 (2026-11-09) -- cache request-scope untuk current()
    // supaya isAttendanceDayAllowed() + settingController index() tidak
    // query baris singleton yang sama berulang dalam satu request.
    protected static $cachedCurrent = null;

    public static function forgetCurrent(): void
    {
        static::$cachedCurrent = null;
    }

    /**
     * PERBAIKAN V33 (2026-11-05): cache daftar kolom di Laravel cache.
     *
     * Kenapa perlu: Schema::hasColumn() issue query ke INFORMATION_SCHEMA
     * tiap kali halaman dirender. Di SQL Server remote itu menambah
     * latency beberapa detik per request kalau koneksi lambat, sehingga
     * halaman login terasa hang.
     *
     * Cache disimpan 1 jam. Kalau admin baru menjalankan migrate, cache
     * bisa dibersihkan dengan "php artisan cache:clear" atau otomatis
     * kedaluwarsa dalam 1 jam.
     */
    public static function cachedColumns(): array
    {
        $cacheKey = 'face_attendance_settings_columns';

        try {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Cache tidak tersedia -> lanjut ke query langsung.
        }

        $columns = [];

        try {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('face_attendance_settings');
        } catch (\Throwable $e) {
            \Log::warning('Gagal membaca daftar kolom face_attendance_settings: ' . $e->getMessage());
            return [];
        }

        try {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $columns, now()->addHour());
        } catch (\Throwable $e) {
            // Abaikan kalau cache gagal ditulis.
        }

        return $columns;
    }

    // PERBAIKAN V34 HOTFIX (2026-11-09) -- hindari Schema::hasColumn()
    // fallback. Kalau cachedColumns() gagal baca kolom (mis. DB
    // timeout), jangan tembak lagi query INFORMATION_SCHEMA sys.columns
    // yang berat; langsung kembalikan false supaya halaman tidak 500.
    // Controller/model sudah punya fallback safe-default untuk kasus ini.
    // (Helper ini tetap ada demi kompatibilitas, tapi tidak lagi
    // memanggil Schema::hasColumn langsung.)
    public static function hasAllowanceColumn(string $kolom = 'clock_in_allowance_minutes'): bool
    {
        if (array_key_exists($kolom, static::$punyaKolomAllowance)) {
            return static::$punyaKolomAllowance[$kolom];
        }

        // PERBAIKAN V33 (2026-11-05): pakai daftar kolom yang di-cache
        // lebih dulu. Kalau cache tidak tersedia, jangan tembak
        // Schema::hasColumn lagi (V34 hotfix) -- langsung false.
        $cached = static::cachedColumns();
        if (!empty($cached)) {
            static::$punyaKolomAllowance[$kolom] = in_array($kolom, $cached, true);
            return static::$punyaKolomAllowance[$kolom];
        }

        static::$punyaKolomAllowance[$kolom] = false;
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
     * PERBAIKAN V14 (2026-11-06): method isNpkRequiredEnabled() DIHAPUS
     * dari model ini.
     *
     * Alasannya: flag "Input NPK Dulu" kini disimpan HANYA di tabel
     * `face_recognition_settings` (bukan `face_attendance_settings`),
     * dan dibaca lewat:
     *     FaceRecognitionSetting::isNpkRequiredEnabled()
     * atau
     *     FaceRecognitionService::isNpkRequiredEnabled()
     *
     * Menyinggirkan method ini supaya tidak ada dua sumber kebenaran
     * yang bisa berbeda nilainya (mis. Settings menyimpan ke tabel A
     * tapi halaman membaca dari tabel B).
     *
     * @see \App\Models\FaceRecognitionSetting::isNpkRequiredEnabled()
     * @see \App\Services\FaceRecognitionService::isNpkRequiredEnabled()
     */

    /**
     * Setting window presensi bersifat singleton (1 baris).
     */
    public static function current(): self
    {
        if (static::$cachedCurrent !== null) {
            return static::$cachedCurrent;
        }
        try {
            $setting = static::query()->first();

            if (!$setting) {
                $setting = new static();
                $setting->clock_in_before_minutes = 15;
                $setting->clock_out_after_minutes = 15;
                $setting->is_saturday_enabled = false;
                $setting->is_sunday_enabled = false;
                $setting->is_holiday_enabled = false;
                $setting->is_npk_required_enabled = true;

                try {
                    $setting->save();
                } catch (\Throwable $e) {
                    // Abaikan jika DB read-only
                }
            }

            static::$cachedCurrent = $setting;
            return $setting;
        } catch (\Throwable $e) {
            $fallback = new static();
            $fallback->clock_in_before_minutes = 15;
            $fallback->clock_out_after_minutes = 15;
            $fallback->is_saturday_enabled = false;
            $fallback->is_sunday_enabled = false;
            $fallback->is_holiday_enabled = false;
            $fallback->is_npk_required_enabled = true;

            return $fallback;
        }
    }

    public function save(array $options = [])
    {
        $result = parent::save($options);
        if ($result) {
            static::$cachedCurrent = $this;
        }
        return $result;
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
     * PERBAIKAN V34 HOTFIX: tidak lagi pakai cachedHasTable() jaga-jaga
     * kalau DB timeout, langsung DB::table()->exists() dalam try/catch.
     * Kalau DB gagal, return false (dianggap bukan hari libur) agar
     * halaman tidak 500.
     */
    public static function isHoliday(\Carbon\Carbon $date): bool
    {
        try {
            $dateStr = $date->format('Y-m-d');

            return \Illuminate\Support\Facades\DB::table('holidays')
                ->whereDate('holiday_date', $dateStr)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
