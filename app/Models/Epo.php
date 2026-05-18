<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Epo extends Model
{
    protected $fillable = [
        'expat_name',
        'gender',
        'place',
        'date_of_birth',
        'nationality',
        'position',
        'department',
        'termination_date',
        'must_leave_date',
        'epo_cost',
        'rptka_cancellation_cost',
        'remarks',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'termination_date' => 'date',
        'must_leave_date' => 'date',
    ];
}
