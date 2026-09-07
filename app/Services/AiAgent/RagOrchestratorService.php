<?php

namespace App\Services\AiAgent;

use App\Models\RagChatMessage;
use App\Models\RagChatSession;
use App\Services\Ai\AiManager;
use App\Services\DatabaseAgent\SafeDbQueryService;
use App\Services\Document\DynamicDocumentService;
use App\Services\Rag\RagKnowledgeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RagOrchestratorService
{
    protected AiManager $ai;
    protected RagKnowledgeService $rag;
    protected SafeDbQueryService $dbAgent;
    protected DynamicDocumentService $docService;

    public function __construct(
        AiManager $ai,
        RagKnowledgeService $rag,
        SafeDbQueryService $dbAgent,
        DynamicDocumentService $docService
    ) {
        $this->ai = $ai;
        $this->rag = $rag;
        $this->dbAgent = $dbAgent;
        $this->docService = $docService;
    }

    /**
     * Process user message in the session and generate complete AI response.
     */
    public function handleUserMessage(RagChatSession $session, string $userMessage): RagChatMessage
    {
        // 1. Save user message
        $userMsgRecord = RagChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // 2. Classify intent
        $intentAnalysis = $this->classifyIntent($userMessage);
        $intent = $intentAnalysis['intent'] ?? 'general';
        $docFormat = $intentAnalysis['doc_format'] ?? null; // 'pdf', 'excel', or null

        $executedSql = null;
        $sqlData = null;
        $generatedDoc = null;
        $assistantContent = '';

        try {
            if ($intent === 'document_generation' || $docFormat !== null) {
                // Fetch data from DB first
                $dbResult = $this->dbAgent->queryFromPrompt($userMessage);
                if ($dbResult['success'] && !empty($dbResult['data'])) {
                    $executedSql = $dbResult['sql'];
                    $sqlData = array_slice($dbResult['data'], 0, 50); // store sample
                    $docTitle = $intentAnalysis['document_title'] ?? 'Laporan Data HR-CII';

                    if ($docFormat === 'excel' || str_contains(strtolower($userMessage), 'excel') || str_contains(strtolower($userMessage), 'xlsx')) {
                        $generatedDoc = $this->docService->generateExcel($docTitle, $dbResult['data']);
                    } else {
                        // Default to PDF (with AI adaptive document layout)
                        $summaryNote = "Laporan data otomatis berdasarkan permintaan: {$userMessage}";
                        $generatedDoc = $this->docService->generateAdaptivePdf($userMessage, $docTitle, $dbResult['data'], $summaryNote);
                    }

                    // Format conversational response
                    $assistantContent = $this->generateSummaryWithDocument(
                        $userMessage,
                        $dbResult['data'],
                        $generatedDoc,
                        $executedSql
                    );
                } else {
                    $assistantContent = "Maaf, sistem tidak menemukan data yang sesuai untuk dibuatkan dokumen atau terjadi kendala query: " . ($dbResult['error'] ?? 'Data kosong.');
                }
            } elseif ($intent === 'sql_query') {
                $dbResult = $this->dbAgent->queryFromPrompt($userMessage);
                $executedSql = $dbResult['sql'] ?? null;

                if ($dbResult['success']) {
                    $sqlData = array_slice($dbResult['data'], 0, 50);
                    $assistantContent = $this->generateSqlAnswer($userMessage, $dbResult['data'], $executedSql);
                } else {
                    $assistantContent = "Terjadi kendala saat mengambil data dari database: " . ($dbResult['error'] ?? 'Unknown error');
                }
            } elseif ($intent === 'rag') {
                $chunks = $this->rag->searchRelevantChunks($userMessage, 4);
                $assistantContent = $this->generateRagAnswer($userMessage, $chunks);
            } else {
                // General conversation
                $assistantContent = $this->generateGeneralAnswer($session, $userMessage);
            }
        } catch (\Throwable $e) {
            Log::error('RagOrchestrator error: ' . $e->getMessage());
            $assistantContent = "Mohon maaf, terjadi kendala teknis saat memproses permintaan: " . $e->getMessage();
        }

        if (empty(trim((string)$assistantContent))) {
            $assistantContent = "Maaf, sistem tidak menerima respon teks dari model AI (respon kosong). Harap periksa apakah server local model di endpoint tersebut sudah aktif dan mengembalikan output.";
        }

        // 3. Save assistant message
        return RagChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $assistantContent,
            'intent' => $intent,
            'executed_sql' => $executedSql,
            'sql_data' => $sqlData,
            'generated_document_type' => $generatedDoc['type'] ?? null,
            'generated_document_url' => $generatedDoc['url'] ?? null,
            'generated_document_filename' => $generatedDoc['filename'] ?? null,
        ]);
    }

    /**
     * Classify user intent: rag, sql_query, document_generation, general
     */
    protected function classifyIntent(string $prompt): array
    {
        $lower = strtolower(trim($prompt));

        // 1. Fast heuristic check (0ms response, saves API call & prevents timeout)
        if (str_contains($lower, 'pdf') || str_contains($lower, 'excel') || str_contains($lower, 'xlsx') || 
            str_contains($lower, 'unduh') || str_contains($lower, 'download') || str_contains($lower, 'export') || 
            str_contains($lower, 'buatkan laporan') || str_contains($lower, 'buatkan dokumen') || 
            str_contains($lower, 'buatkan surat') || str_contains($lower, 'cetak')) {
            return [
                'intent' => 'document_generation',
                'doc_format' => (str_contains($lower, 'excel') || str_contains($lower, 'xlsx')) ? 'excel' : 'pdf',
                'document_title' => 'Laporan Sistem HR-CII',
            ];
        }

        if (str_contains($lower, 'karyawan') || str_contains($lower, 'kontrak') || str_contains($lower, 'gaji') || 
            str_contains($lower, 'cuti') || str_contains($lower, 'absensi') || str_contains($lower, 'berapa') || 
            str_contains($lower, 'tampilkan') || str_contains($lower, 'daftar') || str_contains($lower, 'lembur') ||
            str_contains($lower, 'total') || str_contains($lower, 'jumlah') || str_contains($lower, 'data')) {
            return ['intent' => 'sql_query'];
        }

        if (str_contains($lower, 'sop') || str_contains($lower, 'kebijakan') || str_contains($lower, 'aturan') || 
            str_contains($lower, 'peraturan') || str_contains($lower, 'prosedur') || str_contains($lower, 'syarat')) {
            return ['intent' => 'rag'];
        }

        if (in_array($lower, ['halo', 'hai', 'hello', 'hi', 'selamat pagi', 'selamat siang', 'selamat sore', 'selamat malam', 'tes', 'test', 'ping'])) {
            return ['intent' => 'general'];
        }

        // 2. Fallback to AI classification for complex/ambiguous queries
        $system = <<<PROMPT
Analyze the user's prompt in Indonesian/English and classify into one of the categories:
- "document_generation": User explicitly wants to generate/download/create a PDF or Excel document/report/rekap/surat.
- "sql_query": User asks for specific live database data, statistics, employee info, counts, attendance, leave, payroll records.
- "rag": User asks about company policies, SOPs, rules, procedures, definitions, guidelines.
- "general": Greetings or general conversation.

Return ONLY a JSON object:
{
    "intent": "document_generation"|"sql_query"|"rag"|"general",
    "doc_format": "pdf"|"excel"|null,
    "document_title": "Short descriptive title in Indonesian if document_generation"
}
PROMPT;

        try {
            return $this->ai->chatJson([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ], ['temperature' => 0.1, 'timeout' => 15]);
        } catch (\Throwable $e) {
            return ['intent' => 'general'];
        }
    }

    /**
     * Formulate response for SQL query results.
     */
    protected function generateSqlAnswer(string $userPrompt, array $data, ?string $sql): string
    {
        $dataPreview = json_encode(array_slice($data, 0, 20), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $totalCount = count($data);

        $prompt = <<<PROMPT
User Question: "{$userPrompt}"
Executed SQL: {$sql}
Total Results: {$totalCount}
Data Sample (JSON):
{$dataPreview}

Instructions:
- Provide a polite, professional, and clear response in Indonesian.
- If data contains rows, summarize key metrics and format tabular data into Markdown tables where helpful.
- Highlight important conclusions.
PROMPT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'You are the intelligent HR-CII enterprise assistant.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    /**
     * Formulate response when a document (PDF/Excel) was generated.
     */
    protected function generateSummaryWithDocument(string $userPrompt, array $data, array $doc, ?string $sql): string
    {
        $totalCount = count($data);
        $typeUpper = strtoupper($doc['type'] ?? 'PDF');

        $prompt = <<<PROMPT
User Request: "{$userPrompt}"
Generated Document Type: {$typeUpper}
Filename: {$doc['filename']}
Total Rows Processed: {$totalCount}

Instructions:
- Inform the user enthusiastically in Indonesian that their {$typeUpper} document has been successfully created.
- Provide a brief summary of the contents and tell them they can preview or download it directly using the button below.
PROMPT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'You are the intelligent HR-CII enterprise assistant.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    /**
     * Formulate response using RAG retrieved chunks.
     */
    protected function generateRagAnswer(string $userPrompt, array $chunks): string
    {
        if (empty($chunks)) {
            return $this->ai->chat([
                ['role' => 'system', 'content' => 'You are the HR-CII assistant. No specific documents were found in the knowledge base, so answer based on standard HR best practices and note that official company documents may need to be uploaded.'],
                ['role' => 'user', 'content' => $userPrompt],
            ]);
        }

        $contextText = "";
        foreach ($chunks as $c) {
            $contextText .= "--- Document: {$c['title']} (Bagian {$c['chunk_index']}) ---\n{$c['content']}\n\n";
        }

        $prompt = <<<PROMPT
User Question: "{$userPrompt}"

Relevant Knowledge Base Chunks:
{$contextText}

Instructions:
- Answer the user's question accurately in Indonesian based strictly on the provided knowledge context.
- Cite the source document title if applicable.
PROMPT;

        return $this->ai->chat([
            ['role' => 'system', 'content' => 'You are the HR-CII assistant.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    /**
     * Formulate general conversational answer.
     */
    protected function generateGeneralAnswer(RagChatSession $session, string $userPrompt): string
    {
        return $this->ai->chat([
            ['role' => 'system', 'content' => 'You are the intelligent AI Assistant for Chutex International Indonesia (HR-CII). You can answer HR policy questions, query live database data, and generate PDF or Excel reports directly.'],
            ['role' => 'user', 'content' => $userPrompt],
        ]);
    }
}
