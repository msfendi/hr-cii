<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveAccessLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'npk',
        'ip_address',
        'method',
        'path',
        'route_name',
        'status',
        'user_agent',
        'duration_ms',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    /**
     * Akses aktif dalam N menit terakhir dianggap "sedang online".
     */
    public static function activeSince(int $minutes = 5)
    {
        return static::where('accessed_at', '>=', now()->subMinutes($minutes));
    }
}
