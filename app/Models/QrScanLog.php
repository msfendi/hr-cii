<?php
// app/Models/QrScanLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrScanLog extends Model
{
    protected $fillable = [
        'user_id',
        'npk_scanned',
        'device_uuid',
        'device_name',
        'device_type',
        'platform',
        'browser',
        'ip_address',
        'user_agent',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
