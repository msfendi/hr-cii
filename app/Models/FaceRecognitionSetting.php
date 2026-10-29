<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceRecognitionSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'is_qr_enabled',
        'match_threshold',
    ];

    protected $casts = [
        'is_enabled'      => 'boolean',
        'is_qr_enabled'   => 'boolean',
        'match_threshold' => 'float',
    ];

    /**
     * Setting face recognition & QR login bersifat singleton (1 baris).
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'is_enabled'      => true,
            'is_qr_enabled'   => true,
            'match_threshold' => 0.500,
        ]);
    }
}
