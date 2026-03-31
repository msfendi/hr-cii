<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsentifApproval extends Model
{
    protected $fillable = [
        'period_id',
        'payroll_component',
        'approval',
        'progress',
        'approved_at',
        'status'
    ];

    protected $casts = [
        'approval' => 'array',
        'progress' => 'array',
        'approved_at' => 'array'
    ];
}
