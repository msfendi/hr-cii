<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatisfactionResponse extends Model
{
    protected $table = 'satisfaction_responses';

    public $timestamps = true;

    protected $fillable = [
        'question_id',
        'score',
        'answer_text',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function question()
    {
        return $this->belongsTo(SatisfactionQuestion::class, 'question_id');
    }
}
