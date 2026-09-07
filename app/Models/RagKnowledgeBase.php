<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RagKnowledgeBase extends Model
{
    use HasFactory;

    protected $table = 'rag_knowledge_bases';

    protected $fillable = [
        'title',
        'category',
        'source_type',
        'file_path',
        'original_filename',
        'total_chunks',
        'created_by',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(RagKnowledgeChunk::class, 'knowledge_base_id');
    }
}
