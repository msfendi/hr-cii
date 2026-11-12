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
        // PERBAIKAN V32 & V44 (2026-11-11): kolom liveness_canteen_enabled,
        // is_random_challenge_enabled, random_challenge_canteen_enabled
        // sudah DEPRECATED dan telah di-drop dari tabel canteen_settings
        // oleh migration 2026_11_09_143000. Mode kantin kini disimpan di
        // face_recognition_settings.canteen_verification_mode.
        // Kolom deprecated ini ditiadakan dari fillable agar tidak memicu
        // error SQLSTATE[42S22] Invalid column name saat INSERT / UPDATE.
        'require_geomap',
        'allowed_work_location_ids',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'require_geomap' => 'boolean',
        'allowed_work_location_ids' => 'array',
    ];

    /**
     * Sanitasi attributes agar kolom deprecated tidak pernah dikirim ke SQL Server.
     *
     * PERBAIKAN V44 HOTFIX KEDUA (2026-11-11): kolom deprecated SELALU dihapus,
     * tidak lagi bergantung pada pembacaan skema fisik. Alasan: ada dua lapisan
     * cache kolom (Laravel Cache 'schema_columns_*' TTL 1 jam DAN static
     * request-scope FaceRecognitionSetting::$cachedColumnsCache) yang bisa
     * stale setelah migrasi drop kolom. Bila salah satu cache mengira kolom
     * masih ada, kolom deprecated ikut terkirim ke INSERT/UPDATE dan SQL Server
     * menolak dengan SQLSTATE[42S22] Invalid column name.
     * Fail-closed: lebih baik selalu hapus kolom deprecated (memang sudah tidak
     * dipakai; mode verifikasi kantin kini di face_recognition_settings).
     */
    public static function sanitizeAttributes(array $attributes): array
    {
        // Bersihkan kedua lapisan cache schema canteen_settings supaya tidak basi.
        try {
            \App\Models\FaceRecognitionSetting::forgetSchemaCache();
        } catch (\Throwable $e) {
            // Abaikan.
        }
        try {
            \Illuminate\Support\Facades\Cache::forget('schema_columns_canteen_settings');
        } catch (\Throwable $e) {
            // Abaikan.
        }

        $deprecatedCols = [
            'liveness_canteen_enabled',
            'is_random_challenge_enabled',
            'random_challenge_canteen_enabled',
        ];

        foreach ($deprecatedCols as $col) {
            unset($attributes[$col]);
        }

        return $attributes;
    }

    protected static function booted()
    {
        static::saving(function (self $model) {
            $model->attributes = static::sanitizeAttributes($model->attributes);
        });
    }

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
        $this->attributes = static::sanitizeAttributes($this->attributes);
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

    public function getLivenessCanteenEnabledAttribute(): bool
    {
        return static::isLivenessEnabled();
    }

    public function getIsRandomChallengeEnabledAttribute(): bool
    {
        try {
            return \App\Models\FaceRecognitionSetting::isRandomChallengeCanteenMode();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getRandomChallengeCanteenEnabledAttribute(): bool
    {
        return $this->getIsRandomChallengeEnabledAttribute();
    }

    public function setLivenessCanteenEnabledAttribute($value): void
    {
        // DEPRECATED: Jangan simpan ke attributes tabel canteen_settings
    }

    public function setIsRandomChallengeEnabledAttribute($value): void
    {
        // DEPRECATED: Jangan simpan ke attributes tabel canteen_settings
    }

    public function setRandomChallengeCanteenEnabledAttribute($value): void
    {
        // DEPRECATED: Jangan simpan ke attributes tabel canteen_settings
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
