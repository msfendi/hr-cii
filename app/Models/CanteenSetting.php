<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CanteenSetting extends Model
{
    protected $table = 'canteen_settings';

    protected $fillable = [
        'is_enabled',
        'liveness_canteen_enabled',
        'is_random_challenge_enabled',
        'random_challenge_canteen_enabled',
        'require_geomap',
        'allowed_work_location_ids',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'liveness_canteen_enabled' => 'boolean',
        'is_random_challenge_enabled' => 'boolean',
        'random_challenge_canteen_enabled' => 'boolean',
        'require_geomap' => 'boolean',
        'allowed_work_location_ids' => 'array',
    ];

    public static function current(): self
    {
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

    public static function isLivenessEnabled(): bool
    {
        try {
            $s = static::current();
            if (!$s->is_liveness_enabled ?? false) {
                return false;
            }
            return (bool) ($s->liveness_canteen_enabled ?? true);
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
