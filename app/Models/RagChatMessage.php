<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagChatMessage extends Model
{
    use HasFactory;

    protected $table = 'rag_chat_messages';

    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'intent',
        'executed_sql',
        'sql_data',
        'generated_document_type',
        'generated_document_url',
        'generated_document_filename',
    ];

    protected $casts = [
        'sql_data' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RagChatSession::class, 'chat_session_id');
    }
}
