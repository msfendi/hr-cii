<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollMaster extends Model
{
    use HasFactory;

    protected $table = 'payroll_masters';

    protected $fillable = [
        'npk',
        'salary',
        'allowance'
    ];
}
