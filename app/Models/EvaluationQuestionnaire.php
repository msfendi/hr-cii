<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationQuestionnaire extends Model
{
    protected $table = 'evaluation_questionnaire';

    protected $fillable = [
        'jobscope_id',
        'question',
        'optiona',
        'optionb',
        'optionc',
        'optiond',
        'correct_answer'
    ];

    public function jobscope()
    {
        return $this->belongsTo(EvaluationJobscope::class, 'jobscope_id');
    }
}
