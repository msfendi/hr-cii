<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagKnowledgeChunk extends Model
{
    use HasFactory;

    protected $table = 'rag_knowledge_chunks';

    protected $fillable = [
        'knowledge_base_id',
        'chunk_index',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'chunk_index' => 'integer',
    ];

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(RagKnowledgeBase::class, 'knowledge_base_id');
    }
}
