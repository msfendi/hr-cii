<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationEmployee extends Model
{
    protected $table = 'evaluation_employee';

    protected $fillable = [
        'npk',
        'jobscope_id',
        'score',
        'evaluation_date'
    ];
}
