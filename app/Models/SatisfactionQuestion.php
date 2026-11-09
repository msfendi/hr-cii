<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SatisfactionQuestion extends Model
{
    protected $table = 'satisfaction_questions';

    protected $fillable = [
        'section',
        'question_text',
        'question_type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public const TYPE_LIKERT = 'likert';
    public const TYPE_OPEN = 'open';

    public function isLikert(): bool
    {
        return $this->question_type === self::TYPE_LIKERT;
    }

    public function responses()
    {
        return $this->hasMany(SatisfactionResponse::class, 'question_id');
    }
}
