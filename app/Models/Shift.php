<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'name',
        'work_start',
        'work_end',
        'gender'
    ];

    public function employeeShifts()
    {
        return $this->hasMany(EmployeeShift::class);
    }
}
