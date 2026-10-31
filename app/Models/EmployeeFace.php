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
        'is_attendance_enabled',
        'location_scope',
        'allowed_location_ids',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'descriptor'            => 'array',
        'last_verified_at'      => 'datetime',
        'can_login'             => 'boolean',
        'is_attendance_enabled' => 'boolean',
    ];

    protected $attributes = [
        'is_attendance_enabled' => true,
        'location_scope'        => 'all',
    ];

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'npk', 'npk');
    }

    /**
     * Daftar ID lokasi yang diizinkan untuk karyawan ini.
     */
    public function getAllowedLocationIdListAttribute(): array
    {
        if (empty($this->allowed_location_ids)) {
            return [];
        }

        $decoded = json_decode($this->allowed_location_ids, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded)));
        }

        return array_values(array_filter(array_map('intval', explode(',', $this->allowed_location_ids))));
    }

    /**
     * Apakah karyawan boleh presensi pada lokasi tertentu.
     */
    public function canAttendAtLocation($locationId): bool
    {
        if (!$this->is_attendance_enabled) {
            return false;
        }

        if ($this->location_scope === 'all') {
            return true;
        }

        return in_array((int) $locationId, $this->allowed_location_id_list, true);
    }
}
