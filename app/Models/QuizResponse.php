<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'question_id',
        'option_id',
        'answer_text',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    /** Split free-text answer into normalized tokens (for word cloud). */
    public static function tokenize(?string $text): array
    {
        if ($text === null || trim($text) === '') return [];
        // Lowercase, strip punctuation, split on whitespace.
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '');
        $tokens = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // Drop stopwords (Indonesian + English short list) and 1-char tokens.
        $stopwords = [
            'yang','dan','di','ke','dari','untuk','dengan','pada','adalah','itu','ini','atau',
            'juga','akan','sudah','belum','tidak','bukan','ya','the','a','an','of','to','in',
            'is','are','was','were','be','and','or','but','if','on','at','by','for','it',
        ];
        return array_values(array_filter(
            $tokens,
            fn($w) => mb_strlen($w) >= 2 && !in_array($w, $stopwords, true)
        ));
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuizOption::class, 'option_id');
    }
}