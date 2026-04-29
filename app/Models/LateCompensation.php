<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LateCompensation extends Model
{
    protected $table = 'late_compensations';

    protected $fillable = [
        'npk',
        'date',
        'reason'
    ];
}
