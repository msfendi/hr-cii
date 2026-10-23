<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineJumperOperation extends Model
{
    use HasFactory;

    protected $table = 'line_jumper_operations';

    protected $fillable = [
        'period_id',
        'npk',
        'operation',
        'operation_key',
        'machine',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->operation_key = self::makeOperationKey($model->operation);
        });
    }

    /**
     * "1. ATTACH SLEEVE TO BODY " => "ATTACH SLEEVE TO BODY"
     * Nomor urut di depan dibuang, spasi dirapikan, uppercase.
     */
    public static function makeOperationKey(?string $operation): string
    {
        $operation = trim((string) $operation);
        $operation = preg_replace('/^\d+\s*[\.\)\-]\s*/', '', $operation);
        $operation = preg_replace('/\s+/', ' ', $operation);

        return mb_strtoupper(trim($operation));
    }
}
