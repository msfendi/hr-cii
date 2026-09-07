<?php

namespace App\Http\Controllers;

use App\Models\RagChatSession;
use App\Models\RagKnowledgeBase;
use App\Services\AiAgent\RagOrchestratorService;
use App\Services\Rag\RagKnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class RagChatController extends Controller
{
    protected RagOrchestratorService $orchestrator;
    protected RagKnowledgeService $ragKnowledge;

    public function __construct(
        RagOrchestratorService $orchestrator,
        RagKnowledgeService $ragKnowledge
    ) {
        $this->orchestrator = $orchestrator;
        $this->ragKnowledge = $ragKnowledge;
    }

    /**
     * Display the main AI Assistant / RAG Chat interface.
     */
    public function index(Request $request)
    {
        $sessionId = session('rag_chat_session_id');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            session(['rag_chat_session_id' => $sessionId]);
        }

        $session = RagChatSession::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => Auth::id(),
                'title' => 'Percakapan ' . date('d M Y H:i'),
            ]
        );

        $messages = $session->messages;
        $knowledgeBases = RagKnowledgeBase::latest()->get();

        return view('rag_chat.index', compact('session', 'messages', 'knowledgeBases'));
    }

    /**
     * Send a message and process via orchestrator.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:4000',
            'session_id' => 'required|string',
        ]);

        $session = RagChatSession::where('session_id', $request->input('session_id'))->first();

        if (!$session) {
            $session = RagChatSession::create([
                'session_id' => $request->input('session_id'),
                'user_id' => Auth::id(),
                'title' => Str::limit($request->input('message'), 40),
            ]);
        }

        $message = $this->orchestrator->handleUserMessage($session, $request->input('message'));

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'intent' => $message->intent,
                'executed_sql' => $message->executed_sql,
                'generated_document_type' => $message->generated_document_type,
                'generated_document_url' => $message->generated_document_url,
                'generated_document_filename' => $message->generated_document_filename,
                'created_at' => $message->created_at->format('H:i'),
            ],
        ]);
    }

    /**
     * Upload document or text into Knowledge Base.
     */
    public function uploadKnowledge(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'file' => 'nullable|file|mimes:pdf,txt,doc,docx|max:15360', // max 15MB
            'direct_text' => 'nullable|string',
        ]);

        if (!$request->hasFile('file') && empty($request->input('direct_text'))) {
            return response()->json([
                'success' => false,
                'message' => 'Harap unggah file dokumen atau masukkan teks pengetahuan.',
            ], 422);
        }

        try {
            $kb = $this->ragKnowledge->ingest(
                $request->input('title'),
                $request->input('category', 'general'),
                $request->file('file'),
                $request->input('direct_text'),
                Auth::id()
            );

            return response()->json([
                'success' => true,
                'message' => "Dokumen '{$kb->title}' berhasil diproses ({$kb->total_chunks} bagian pengetahuan).",
                'knowledge_base' => $kb,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses dokumen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download generated PDF / Excel document.
     */
    public function downloadDocument(string $filename)
    {
        // Sanitize filename to prevent directory traversal
        $cleanFilename = basename($filename);
        $filePath = storage_path("app/public/ai-generated/{$cleanFilename}");

        if (!File::exists($filePath)) {
            abort(404, 'Dokumen yang diminta tidak ditemukan.');
        }

        return Response::download($filePath, $cleanFilename);
    }

    /**
     * Reset / clear chat session messages.
     */
    public function clearSession(Request $request): JsonResponse
    {
        $sessionId = session('rag_chat_session_id');
        if ($sessionId) {
            $session = RagChatSession::where('session_id', $sessionId)->first();
            if ($session) {
                $session->messages()->delete();
            }
        }

        return response()->json(['success' => true, 'message' => 'Riwayat obrolan berhasil dibersihkan.']);
    }

    /**
     * Delete a knowledge base item.
     */
    public function deleteKnowledge(int $id): JsonResponse
    {
        $kb = RagKnowledgeBase::findOrFail($id);
        $kb->delete();

        return response()->json(['success' => true, 'message' => 'Knowledge base berhasil dihapus.']);
    }
}
