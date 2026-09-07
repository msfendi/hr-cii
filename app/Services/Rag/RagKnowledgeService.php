<?php

namespace App\Services\Rag;

use App\Models\RagKnowledgeBase;
use App\Models\RagKnowledgeChunk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf as PdfToText;

class RagKnowledgeService
{
    /**
     * Ingest a document file or direct text into the RAG knowledge base.
     */
    public function ingest(
        string $title,
        string $category = 'general',
        ?UploadedFile $file = null,
        ?string $directText = null,
        ?int $userId = null
    ): RagKnowledgeBase {
        $filePath = null;
        $originalFilename = null;
        $extractedText = '';

        if ($file) {
            $originalFilename = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $filePath = $file->store('knowledge-bases', 'public');
            $fullPath = storage_path('app/public/' . $filePath);

            if ($extension === 'pdf') {
                $extractedText = $this->extractPdfText($fullPath);
            } else {
                $extractedText = file_get_contents($fullPath) ?: '';
            }
        } elseif (!empty($directText)) {
            $extractedText = $directText;
        }

        $knowledgeBase = RagKnowledgeBase::create([
            'title' => $title,
            'category' => $category,
            'source_type' => $file ? 'document' : 'text',
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'total_chunks' => 0,
            'created_by' => $userId,
        ]);

        if (!empty($extractedText)) {
            $chunks = $this->chunkText($extractedText, 600, 100);
            $totalChunks = 0;

            foreach ($chunks as $idx => $chunkContent) {
                if (trim($chunkContent) === '') {
                    continue;
                }

                RagKnowledgeChunk::create([
                    'knowledge_base_id' => $knowledgeBase->id,
                    'chunk_index' => $idx + 1,
                    'content' => $chunkContent,
                    'metadata' => [
                        'char_length' => strlen($chunkContent),
                        'source' => $title,
                    ],
                ]);
                $totalChunks++;
            }

            $knowledgeBase->update(['total_chunks' => $totalChunks]);
        }

        return $knowledgeBase;
    }

    /**
     * Search knowledge chunks relevant to the user query using keyword & relevance scoring.
     */
    public function searchRelevantChunks(string $query, int $topK = 5): array
    {
        $keywords = $this->extractKeywords($query);
        if (empty($keywords)) {
            return [];
        }

        // Query chunks that contain at least one keyword
        $queryBuilder = RagKnowledgeChunk::with('knowledgeBase');

        $queryBuilder->where(function ($q) use ($keywords) {
            foreach ($keywords as $kw) {
                $q->orWhere('content', 'LIKE', '%' . $kw . '%');
            }
        });

        $chunks = $queryBuilder->limit(50)->get();

        // Calculate relevance scores
        $scoredChunks = [];
        foreach ($chunks as $chunk) {
            $contentLower = strtolower($chunk->content);
            $score = 0;

            foreach ($keywords as $kw) {
                $count = substr_count($contentLower, strtolower($kw));
                if ($count > 0) {
                    $score += ($count * strlen($kw));
                }
            }

            $scoredChunks[] = [
                'id' => $chunk->id,
                'title' => $chunk->knowledgeBase->title ?? 'Document',
                'category' => $chunk->knowledgeBase->category ?? 'general',
                'chunk_index' => $chunk->chunk_index,
                'content' => $chunk->content,
                'score' => $score,
            ];
        }

        // Sort descending by score
        usort($scoredChunks, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scoredChunks, 0, $topK);
    }

    /**
     * Extract text from PDF using poppler-utils pdftotext.
     */
    protected function extractPdfText(string $absolutePath): string
    {
        $binaryPath = env('PDF_TO_TEXT_BINARY_PATH');

        try {
            $pdf = new PdfToText($binaryPath ?: null);
            return $pdf->setPdf($absolutePath)->setOptions(['layout'])->text();
        } catch (\Throwable $e) {
            Log::warning("PdfToText extraction failed: {$e->getMessage()}. Attempting basic read.");
            $content = @file_get_contents($absolutePath);
            return strip_tags($content ?: '');
        }
    }

    /**
     * Split text into overlapping chunks.
     */
    protected function chunkText(string $text, int $chunkSize = 600, int $overlap = 100): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $length = strlen($text);
        $chunks = [];

        if ($length <= $chunkSize) {
            return [$text];
        }

        $start = 0;
        while ($start < $length) {
            $chunk = substr($text, $start, $chunkSize);
            
            // Try to break on sentence/word boundary
            if ($start + $chunkSize < $length) {
                $lastPeriod = strrpos($chunk, '. ');
                $lastSpace = strrpos($chunk, ' ');
                
                if ($lastPeriod !== false && $lastPeriod > $chunkSize * 0.6) {
                    $chunk = substr($chunk, 0, $lastPeriod + 1);
                } elseif ($lastSpace !== false && $lastSpace > $chunkSize * 0.6) {
                    $chunk = substr($chunk, 0, $lastSpace);
                }
            }

            $chunks[] = trim($chunk);
            $start += (strlen($chunk) - $overlap);

            if ($start <= 0 || strlen($chunk) <= $overlap) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Extract keywords from user query.
     */
    protected function extractKeywords(string $query): array
    {
        $stopWords = [
            'yang', 'untuk', 'pada', 'ke', 'di', 'dari', 'ini', 'itu', 'dengan',
            'adalah', 'dan', 'atau', 'dalam', 'bisa', 'tolong', 'apakah', 'bagaimana',
            'apa', 'siapa', 'kapan', 'dimana', 'kenapa', 'mengapa', 'berapa', 'the',
            'is', 'at', 'which', 'on', 'in', 'to', 'for', 'a', 'an', 'of', 'and', 'or', 'saya', 'kami', 'anda'
        ];

        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', strtolower($query));
        $words = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

        $filtered = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) >= 3 && !in_array($word, $stopWords, true);
        });

        return array_values(array_unique($filtered));
    }
}
