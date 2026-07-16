<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLineAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'npk',
        'period_id',
        'line_number',
        'role',
        'start_date',
        'end_date',
        'work_hours',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date'
    ];
}
