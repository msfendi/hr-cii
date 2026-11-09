<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MessageController extends Controller
{
    public function __construct(private readonly ChatService $chatService) {}

    /** Pesan lama→baru dengan pagination; hanya milik pemilik conversation. */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        // Placeholder `pending` yatim: stream yang terputus (refresh, tab ditutup,
        // PHP-FPM restart) meninggalkan baris pending tanpa proses yang akan
        // menutupnya. Tanpa rekonsiliasi ini UI macet di "sedang berpikir" dan
        // tombol kirim tetap tersembunyi selamanya.
        //
        // Basis waktunya `updated_at`, bukan `created_at`: respondStreaming
        // mem-flush konten ke placeholder tiap ±500 char / ±500 ms sehingga
        // updated_at bergerak selama stream benar-benar hidup. Pending yang
        // updated_at-nya sudah membeku berarti tidak ada proses yang menulis
        // lagi — stream selesai (browser sudah pergi) atau mati. Ambang 90 detik
        // masih cukup longgar untuk model yang berpikir lama tanpa delta, tapi
        // jauh lebih cepat dari 3 menit sehingga refresh tidak menggantung.
        $staleBefore = now()->subSeconds(90);

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('status', Message::STATUS_PENDING)
            ->where('updated_at', '<', $staleBefore)
            ->where(fn ($q) => $q->whereNull('content')->orWhere('content', ''))
            ->update([
                'status' => Message::STATUS_FAILED,
                'content' => 'Maaf, Chutex AI sedang mengalami kendala. Silakan coba lagi beberapa saat lagi.',
            ]);

        // Pending yang sudah terisi sebagian tetap diselamatkan sebagai jawaban.
        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('status', Message::STATUS_PENDING)
            ->where('updated_at', '<', $staleBefore)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->update(['status' => Message::STATUS_DONE]);

        $messages = Message::query()
            // 'id' WAJIB ikut select: tanpa itu a.id = null dan kartu file
            // menghasilkan URL /api/attachments/null (tidak bisa diunduh).
            ->with('attachments:id,message_id,original_name,mime,size')
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->paginate($perPage, [
                'id', 'conversation_id', 'role', 'content', 'status',
                'model', 'created_at', 'options',
            ]);

        return response()->json($messages);
    }

    /** Kiriman non-stream (fallback bila streaming dimatikan).
     *  Content-Type bisa JSON ATAU multipart/form-data (upload lampiran, max 5 file). */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:' . config('hermes.max_prompt_length')],
            'files' => ['sometimes', 'array', 'max:' . (int) config('hermes.attachments.max_files', 5)],
            'files.*' => [
                'file', 'max:' . (int) config('hermes.attachments.max_kb', 5120),
                'mimes:' . config('hermes.attachments.mimes', 'pdf,docx,xlsx,pptx,txt,md,csv,json'),
                'extensions:' . config('hermes.attachments.mimes', 'pdf,docx,xlsx,pptx,txt,md,csv,json'),
            ],
        ]);

        $content = (string) ($data['content'] ?? '');
        $files = $request->file('files', []);
        if (trim($content) === '' && $files === []) {
            return response()->json(['message' => 'Prompt atau file wajib diisi.'], 422);
        }

        try {
            $result = $this->chatService->respond($request->user(), $conversation, $content, $files);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $request);
        }

        return response()->json([
            'blocked' => $result['blocked'],
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
        ], $result['blocked'] ? 451 : 201);
    }

    /** Regenerate respons assistant terakhir. */
    public function regenerate(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $lastUser = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('status', Message::STATUS_DONE)
            ->latest('id')
            ->first();

        if (!$lastUser) {
            return response()->json(['message' => 'Tidak ada pesan untuk diulang.'], 422);
        }

        // Hapus respons terakhir setelah pesan user tsb, lalu kirim ulang.
        // Pesan user TIDAK dihapus/dibuat lagi: direuse supaya tidak ada
        // bubble user duplikat saat regenerate (bug response 3x).
        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $lastUser->id)
            ->delete();

        try {
            $result = $this->chatService->respond(
                $request->user(), $conversation, $lastUser->content, [], $lastUser);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $request);
        }

        return response()->json([
            'blocked' => $result['blocked'],
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
        ]);
    }

    /** Simpan status ketika user menekan Stop saat streaming. */
    public function stop(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->role === 'assistant', 404);

        if ($message->status === Message::STATUS_PENDING) {
            $message->update(['status' => Message::STATUS_STOPPED]);
        }

        return response()->json(['stopped' => true]);
    }

    private function errorResponse(string $code, Request $request): JsonResponse
    {
        return match ($code) {
            'DAILY_QUOTA_EXCEEDED' => response()->json([
                'message' => 'Batas pemakaian harian tercapai. Silakan coba lagi besok.',
            ], 429),
            default => response()->json([
                'message' => 'Prompt tidak valid.',
            ], 422),
        };
    }
}
