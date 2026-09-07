<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagChatSession extends Model
{
    use HasFactory;

    protected $table = 'rag_chat_sessions';

    protected $fillable = [
        'session_id',
        'title',
        'user_id',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(RagChatMessage::class, 'chat_session_id')->orderBy('created_at', 'asc');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
