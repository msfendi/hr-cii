<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatisfactionSurveyAiAnalysis extends Model
{
    protected $table = 'satisfaction_survey_ai_analyses';

    protected $fillable = [
        'scope_key', 'source_count', 'provider', 'model', 'analysis_json', 'generated_at',
    ];

    protected $casts = [
        'analysis_json' => 'array',
        'generated_at' => 'datetime',
    ];
}
