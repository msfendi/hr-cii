<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Setelan portal HRIS v2 (key/value) yang dikelola admin di /v2/admin.
 *
 * Contoh kunci: session_minutes, portal_title, employee_login_note.
 */
class HrisV2Setting extends Model
{
    protected $table = 'hris_v2_settings';

    protected $fillable = ['key', 'value'];

    protected static $cache = null;

    protected static function semuaCache(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        try {
            if (! Schema::hasTable('hris_v2_settings')) {
                return static::$cache = [];
            }

            static::$cache = static::query()->pluck('value', 'key')->toArray();
        } catch (\Throwable $e) {
            static::$cache = [];
        }

        return static::$cache;
    }

    public static function ambil(string $key, $default = null)
    {
        $all = static::semuaCache();

        return (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '')
            ? $all[$key]
            : $default;
    }

    public static function simpan(string $key, $value): void
    {
        try {
            if (! Schema::hasTable('hris_v2_settings')) {
                return;
            }

            static::updateOrCreate(['key' => $key], ['value' => $value]);
            static::$cache = null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('HrisV2Setting::simpan gagal: ' . $e->getMessage());
        }
    }

    /** Versi halaman awal HRIS di route '/'. */
    public const LANDING_V1 = 'v1';
    public const LANDING_V2 = 'v2';

    /**
     * Versi halaman awal route '/': 'v1' = resources/views/hris.blade.php,
     * 'v2' = resources/views/hris_v2/portal.blade.php.
     * Nilai tak dikenal / belum diisi selalu jatuh ke v1 (perilaku lama).
     */
    public static function landingVersion(): string
    {
        $value = strtolower(trim((string) static::ambil('landing_version', static::LANDING_V1)));

        return $value === static::LANDING_V2 ? static::LANDING_V2 : static::LANDING_V1;
    }

    /** Lama sesi karyawan (menit). 0 = tanpa batas. */
    public static function sessionMinutes(): int
    {
        $value = static::ambil('session_minutes', null);

        if ($value === null) {
            return (int) config('hris_v2.session_minutes', 480);
        }

        return max(0, (int) $value);
    }
}
