<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThrRun extends Model
{
    protected $fillable = [
        'period_id',
        'processed_at',
        'total_thr',
        'employee_count',
        'progress',
        'status'
    ];
}
