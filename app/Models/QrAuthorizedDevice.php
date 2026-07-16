<?php
// app/Models/QrAuthorizedDevice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrAuthorizedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'device_type',
        'platform',
        'browser',
        'assigned_by',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
