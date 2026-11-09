<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    public const MODE_QUIZ       = 'quiz';
    public const MODE_POLLING    = 'polling';
    public const MODE_WORD_CLOUD = 'word_cloud';

    public const MODES = [
        self::MODE_QUIZ,
        self::MODE_POLLING,
        self::MODE_WORD_CLOUD,
    ];

    protected $fillable = [
        'title',
        'description',
        'qr_code',
        'status',
        'mode',
        'started_at',
        'closed_at',
        'created_by',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'closed_at'   => 'datetime',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'quiz_id')->orderBy('sort_order', 'asc');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class, 'quiz_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isQuiz(): bool      { return $this->mode === self::MODE_QUIZ; }
    public function isPolling(): bool   { return $this->mode === self::MODE_POLLING; }
    public function isWordCloud(): bool { return $this->mode === self::MODE_WORD_CLOUD; }

    /** Human-readable label for the current mode. */
    public function modeLabel(): string
    {
        return match ($this->mode) {
            self::MODE_POLLING    => 'Live Polling',
            self::MODE_WORD_CLOUD => 'Word Cloud',
            default               => 'Kuis',
        };
    }

    /** Allowed question types for this quiz mode. */
    public function allowedQuestionTypes(): array
    {
        return match ($this->mode) {
            self::MODE_POLLING    => ['single_choice'],
            self::MODE_WORD_CLOUD => ['open_text'],
            default               => ['single_choice', 'multiple_choice'],
        };
    }
}
