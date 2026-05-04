<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMutation extends Model
{
    protected $table = 'employee_mutations';

    protected $fillable = [
        'npk',
        'from_dept',
        'to_dept',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
