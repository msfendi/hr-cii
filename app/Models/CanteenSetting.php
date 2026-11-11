<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CanteenSetting extends Model
{
    protected $table = 'canteen_settings';

    // PERBAIKAN V33 (2026-11-09) -- cache request-scope untuk current().
    protected static $cachedCurrent = null;

    protected $fillable = [
        'is_enabled',
        // PERBAIKAN V32 (2026-11-09): kolom liveness_canteen_enabled,
        // is_random_challenge_enabled, random_challenge_canteen_enabled
        // sudah DEPRECATED. Mode canteen kini disimpan di
        // face_recognition_settings.canteen_verification_mode.
        // Kolom ini tetap ada di fillable agar tidak error bila masih
        // ada di database lama, tetapi tidak lagi ditulis controller.
        'liveness_canteen_enabled',
        'is_random_challenge_enabled',
        'random_challenge_canteen_enabled',
        'require_geomap',
        'allowed_work_location_ids',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        // Deprecated: lihat catatan di $fillable di atas.
        'liveness_canteen_enabled' => 'boolean',
        'is_random_challenge_enabled' => 'boolean',
        'random_challenge_canteen_enabled' => 'boolean',
        'require_geomap' => 'boolean',
        'allowed_work_location_ids' => 'array',
    ];

    // PERBAIKAN V33 (2026-11-09) -- forward helper cache kolom/tabel
    // ke FaceRecognitionSetting supaya controller tidak perlu memanggil
    // Schema::hasTable/hasColumn langsung (lambat di SQL Server remote).
    public static function cachedColumns(string $table): array
    {
        return \App\Models\FaceRecognitionSetting::cachedColumns($table);
    }

    public static function cachedHasTable(string $table): bool
    {
        return \App\Models\FaceRecognitionSetting::cachedHasTable($table);
    }

    public static function cachedHasColumn(string $table, string $column): bool
    {
        return \App\Models\FaceRecognitionSetting::cachedHasColumn($table, $column);
    }

    public static function current(): self
    {
        // PERBAIKAN V33 (2026-11-09) -- cache request-scope supaya
        // CanteenTransactionController::index() yang memanggil current()
        // 4x (setting, allowedLocationIds, isRequireGeomap, dll) tidak
        // query tabel yang sama berulang dalam satu request.
        if (static::$cachedCurrent !== null) {
            return static::$cachedCurrent;
        }
        try {
            $setting = static::query()->first();
            if (!$setting) {
                $setting = new static();
                $setting->is_enabled = true;
                $setting->liveness_canteen_enabled = true;
                $setting->is_random_challenge_enabled = false;
                $setting->random_challenge_canteen_enabled = false;
                $setting->require_geomap = false;
                $setting->allowed_work_location_ids = [];
                try {
                    $setting->save();
                } catch (\Throwable $e) {
                    \Log::warning('CanteenSetting::current() gagal membuat baris default: ' . $e->getMessage());
                }
            }
            static::$cachedCurrent = $setting;
            return $setting;
        } catch (\Throwable $e) {
            $fallback = new static();
            $fallback->is_enabled = true;
            $fallback->liveness_canteen_enabled = true;
            $fallback->is_random_challenge_enabled = false;
            $fallback->random_challenge_canteen_enabled = false;
            $fallback->require_geomap = false;
            $fallback->allowed_work_location_ids = [];
            return $fallback;
        }
    }

    public static function forgetCurrent(): void
    {
        static::$cachedCurrent = null;
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
     * PERBAIKAN V32 (2026-11-09): method ini DEPRECATED.
     * Mode canteen kini ditentukan oleh
     * FaceRecognitionSetting::canteenVerificationMode()
     * (face_recognition_settings.canteen_verification_mode),
     * BUKAN canteen_settings. Method ini dipertahankan hanya supaya
     * pemanggil lama tidak fatal error.
     */
    public static function isLivenessEnabled(): bool
    {
        try {
            return \App\Models\FaceRecognitionSetting::isLivenessCanteenMode();
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function isRequireGeomap(): bool
    {
        try {
            return (bool) static::current()->require_geomap;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function allowedLocationIds(): array
    {
        try {
            $ids = static::current()->allowed_work_location_ids ?? [];
            return array_values(array_filter(array_map('intval', (array) $ids)));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
