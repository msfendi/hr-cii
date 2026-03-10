<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    protected $fillable = [
        'period_id',
        'processed_at',
        'total_payroll',
        'employee_count'
    ];

    public function details()
    {
        return $this->hasMany(PayrollRunDetail::class, 'run_id');
    }
}
