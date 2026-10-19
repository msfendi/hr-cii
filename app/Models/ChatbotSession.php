<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatbotSession extends Model
{
    use HasFactory;

    public const MODE_GLOBAL = 'global';
    public const MODE_RTG = 'rtg';

    protected $fillable = [
        'user_id',
        'title',
        'mode',
        'ai_provider',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class)->orderBy('created_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function isRtg(): bool
    {
        return $this->mode === self::MODE_RTG;
    }

    public function resolveTitle(): string
    {
        if (! empty($this->title)) {
            return $this->title;
        }

        $firstUserMessage = $this->messages()->where('role', 'user')->first();

        if ($firstUserMessage) {
            return str($firstUserMessage->content)->limit(40)->toString();
        }

        return 'Chat baru';
    }

    /**
     * Route-model binding di-scope ke user yang login, sehingga user A
     * tidak bisa buka/edit/hapus session milik user B lewat URL manapun
     * (mis. /chatbot/sessions/5). Kalau bukan pemiliknya -> 404 (ModelNotFoundException),
     * bukan 403, supaya tidak membocorkan keberadaan ID tersebut.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }

    /**
     * Query scope kalau dipakai manual (mis. index / listing sesi).
     */
    public function scopeOwnedByCurrentUser($query)
    {
        return $query->where('user_id', auth()->id());
    }
}
