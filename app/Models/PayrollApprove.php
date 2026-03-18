<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollApprove extends Model
{
    protected $table = 'payroll_approve';

    protected $fillable = [
        'payroll_run_id',
        'approval',
        'progress',
        'status'
    ];

    protected $casts = [
        'approval' => 'array',
        'progress' => 'array',
        'approved_at' => 'array',
    ];
}
