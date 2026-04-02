<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThrApprove extends Model
{
    protected $table = 'thr_approve';
    protected $fillable = [
        'thr_run_id',
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
