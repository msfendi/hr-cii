<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompensationApprove extends Model
{
    protected $table = 'compensation_approve';

    protected $fillable = [
        'run_id',
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
