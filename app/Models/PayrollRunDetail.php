<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRunDetail extends Model
{
    protected $fillable = [
        'run_id',
        'employee_npk',
        'employee_name',
        'components',
        'total_salary'
    ];

    protected $casts = [
        'components' => 'array'
    ];
}