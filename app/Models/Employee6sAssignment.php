<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee6sAssignment extends Model
{
    protected $table = 'employee_6s_assignments';

    protected $fillable = [
        'period_id',
        'npk',
        'section_id',
        'inspector',
        'inspection_date',
        'total_score',
        'percentage',
        'file_path',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'total_score' => 'decimal:2',
        'percentage' => 'decimal:2',
    ];

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }
}
