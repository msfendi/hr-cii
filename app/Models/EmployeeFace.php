<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFace extends Model
{
    protected $table = 'employee_faces';

    protected $fillable = [
        'npk',
        'name',
        'face_img',
        'descriptor',
        'is_active',
        'can_login',
        'registered_by',
        'last_verified_at',
    ];

    protected $casts = [
        'descriptor'       => 'array',
        'is_active'        => 'boolean',
        'can_login'        => 'boolean',
        'last_verified_at' => 'datetime',
    ];

    /**
     * User yang mendaftarkan wajah ini (FK → users.id).
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'registered_by');
    }
}
