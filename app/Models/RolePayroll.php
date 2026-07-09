<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePayroll extends Model
{
    protected $fillable = [
        'user_id',
        'payroll_role',
        'created_by',
    ];

    public const ROLES = [
        'Payroll_STAFF'     => 'Staff',
        'Payroll_NONSTAFF'  => 'Non Staff',
        'Payroll_SEWING'    => 'Sewing',
        'Payroll_NONSEWING' => 'Non Sewing',
        'Payroll_ALL'       => 'Semua (Tidak Difilter)',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
