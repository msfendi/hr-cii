<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationJobscope extends Model
{
    protected $table = 'evaluation_jobscope';

    protected $fillable = [
        'job_code',
        'job_name',
        'description',
        'qr_code',
    ];

    public function questionnaires()
    {
        return $this->hasMany(EvaluationQuestionnaire::class, 'jobscope_id');
    }
}
