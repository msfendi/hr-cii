<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeFace extends Model
{
    protected $fillable = [
        'npk',
        'name',
        'face_img',
        'descriptor',
        'is_active',
        'registered_by',
        'last_verified_at',
        'can_login',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'descriptor'       => 'array',
        'last_verified_at' => 'datetime',
        'can_login'        => 'boolean',
    ];

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'npk', 'npk');
    }
}
