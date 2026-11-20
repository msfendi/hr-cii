<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeWageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'batch_id',
        'npk',
        'contract_id',
        'effective_date',
        'policy',
        'percent',
        'umr_reference',
        'old_salary',
        'new_salary',
        'old_daily_salary',
        'new_daily_salary',
        'old_allowance',
        'new_allowance',
        'note',
        'created_by',
    ];

    protected $casts = [
        'effective_date'   => 'date',
        'percent'          => 'decimal:3',
        'umr_reference'    => 'decimal:2',
        'old_salary'       => 'decimal:2',
        'new_salary'       => 'decimal:2',
        'old_daily_salary' => 'decimal:2',
        'new_daily_salary' => 'decimal:2',
        'old_allowance'    => 'decimal:2',
        'new_allowance'    => 'decimal:2',
    ];
}
