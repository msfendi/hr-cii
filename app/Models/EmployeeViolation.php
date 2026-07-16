<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeViolation extends Model
{
    use HasFactory;

    protected $table = 'employee_violations';

    protected $fillable = [
        'period_id',
        'npk',
        'percentage',
    ];
}
