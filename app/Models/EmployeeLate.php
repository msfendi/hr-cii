<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLate extends Model
{
    use HasFactory;

    protected $table = 'employee_lates';

    protected $fillable = [
        'npk',
        'date',
        'arrival_time',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Relation to PayrollMaster via npk (optional, remove if not needed).
     * Adjust the related model/table name to match your actual employee/payroll model.
     */
    public function payrollMaster()
    {
        return $this->belongsTo(PayrollMaster::class, 'npk', 'npk');
    }
}
