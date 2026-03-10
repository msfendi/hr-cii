<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollComponent extends Model
{
    protected $connection = 'cii';
    protected $table = 'payroll_components';
    protected $fillable = [
        'name',
        'code',
        'type',
        'calculation_method',
        'value',
        'formula',
        'description',
        'category',
        'priority',
        'is_taxable',
        'is_active',
        'created_by',
        'updated_by',
    ];
}
