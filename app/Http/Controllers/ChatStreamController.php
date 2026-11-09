<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\ChatService;
use App\Services\AI\PromptGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat SSE endpoint. Streaming langsung dari PHP‑FPM (proxy_buffering off)
 * di Nginx, tanpa queue, sesuai PRD §15 (SSE preferred) & §49.
 *
 * Protokol event:
 *   start   -> {}
 *   delta   -> {"text": "..."}
 *   done    -> {"user_message_id":1,"assistant_message_id":2,"status":"done|failed"}
 *   error   -> {"type":"connection_error|hermes_error","fallback":"..."}
 */
class ChatStreamController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly PromptGuard $guard,
    ) {}

    public function __invoke(Request $request, Conversation $conversation): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $conversation);

        if (!config('hermes.streaming')) {
            return response()->json(['message' => 'Streaming tidak diaktifkan.'], 400);
        }

        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:' . config('hermes.max_prompt_length')],
            'regenerate' => ['sometimes', 'boolean'],
            'files' => ['sometimes', 'array', 'max:' . (int) config('hermes.attachments.max_files', 5)],
            'files.*' => [
                'file',
                'max:' . (int) config('hermes.attachments.max_kb', 5120),
                'mimes:' . config('hermes.attachments.mimes'),
                'extensions:' . config('hermes.attachments.mimes'),
            ],
        ], [], ['files.*' => 'file']);

        $user = $request->user();
        $content = (string) ($data['content'] ?? '');
        $files = $request->file('files', []);

        // Regenerate via SSE: reuse pesan user terakhir + lampirannya (file
        // sudah dihapus dari disk oleh cascade? tidak — hanya baris messages.
        // Attachment lama tetap utuh, historyFor/applyVision ambil dari DB).
        $reuseUser = null;
        if (!empty($data['regenerate'])) {
            $lastUser = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('role', 'user')
                ->where('status', Message::STATUS_DONE)
                ->latest('id')
                ->first();

            if (!$lastUser) {
                return response()->json(['message' => 'Tidak ada pesan untuk diulang.'], 422);
            }

            Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('id', '>', $lastUser->id)
                ->delete();

            $content = (string) $lastUser->content;
            $files = [];
            $reuseUser = $lastUser;
        }

        if (trim($content) === '' && $files === []) {
            return response()->json(['message' => 'Prompt atau file wajib diisi.'], 422);
        }

        if ($this->chatService->dailyMessagesToday($user) >= (int) config('hermes.max_daily_messages')) {
            return response()->json(['message' => 'Batas pemakaian harian tercapai. Silakan coba lagi besok.'], 429);
        }

        $guardResult = $this->guard->inspect(
            $content !== ''
                ? $content
                : trim(implode(' ', array_map(fn ($f) => (string) $f->getClientOriginalName(), $files)))
        );

        if (!$guardResult->allowed()) {
            $result = $this->chatService->respond($user, $conversation, $content, $files);
            return response()->json([
                'blocked' => true,
                'user_message' => $result['user_message'],
                'assistant_message' => $result['assistant_message'],
                'message' => PromptGuard::SAFE_REJECTION,
            ], 451);
        }

        // Ensure user message exists before placeholder.
        $userMessage = $reuseUser ?? Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $content,
            'status' => Message::STATUS_DONE,
        ]);

        // Placeholder assistant TIDAK boleh dipakai ulang lintas stream.
        // ignore_user_abort(true) membuat stream lama tetap menulis ke DB
        // setelah browser refresh; kalau stream baru (mis. Regenerate) mengambil
        // alih placeholder yang sama, keduanya berebut satu baris dan hasilnya
        // error atau jawaban tercampur. Tutup dulu semua pending lama: yang
        // sudah punya konten diselamatkan sebagai jawaban, yang kosong ditandai
        // gagal.
        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('status', Message::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('content')->orWhere('content', ''))
            ->update([
                'status' => Message::STATUS_FAILED,
                'content' => 'Maaf, Chutex AI sedang mengalami kendala. Silakan coba lagi beberapa saat lagi.',
            ]);

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('status', Message::STATUS_PENDING)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->update(['status' => Message::STATUS_DONE]);

        $placeholder = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'assistant',
            // Placeholder TANPA konten: UI menampilkan badge "sedang berpikir"
            // untuk status pending. Konten baru ditulis ChatService saat hasil
            // selesai atau gagal. Jangan diisi teks di sini supaya tak ada
            // bubble kosong ter-render sebelum jawaban siap — status pending
            // yang tanpa konten dianggap "belum selesai", bukan jawaban.
            'content' => '',
            'status' => Message::STATUS_PENDING,
        ]);

        return new StreamedResponse(function () use (
            $user,
            $conversation,
            $content,
            $files,
            $userMessage,
            $placeholder,
            $reuseUser
        ) {
            // PENTING: refresh/tutup tab memutus koneksi SSE. Tanpa ini PHP-FPM
            // mematikan proses di tengah stream sehingga placeholder `pending`
            // tidak pernah di-update → chat macet di "Chutex AI sedang berpikir".
            // Proses dibiarkan selesai menulis jawaban ke DB; echo ke klien yang
            // sudah pergi akan gagal diam-diam dan diabaikan.
            ignore_user_abort(true);
            set_time_limit(0);

            $send = function (string $event, array $payload) {
                echo "event: $event\n";
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $send('start', [
                'assistant_message_id' => $placeholder->id,
                'hermes_session_id' => $conversation->hermes_session_id,
            ]);

            try {
                $result = $this->chatService->respondStreaming(
                    $user,
                    $conversation,
                    $content,
                    onDelta: fn (string $delta) => $send('delta', ['text' => $delta]),
                    files: $files,
                    reuseUserMessage: $userMessage,
                    assistantPlaceholder: $placeholder,
                );

                /** @var Message $assistant */
                $assistant = $result['assistant_message'];
                $failed = $assistant->status === Message::STATUS_FAILED;

                $send($failed ? 'error' : 'done', [
                    'user_message_id' => $result['user_message']->id,
                    'assistant_message_id' => $assistant->id,
                    'user_attachments' => $result['user_message']->attachments()
                        ->get(['id', 'message_id', 'original_name', 'mime', 'size']),
                    'status' => $assistant->status,
                    'type' => $failed ? 'hermes_error' : null,
                    'fallback' => $failed ? $assistant->content : null,
                    'content' => $failed ? null : ($result['media']['content'] ?? null),
                    'attachments' => $failed ? [] : ($result['media']['attachments'] ?? []),
                    // Opsi klarifikasi interaktif (diklik user untuk mengarahkan).
                    'options' => $failed ? [] : ((array) ($assistant->options ?? [])),
                ]);
            } catch (\Throwable $e) {
                report($e);
                $placeholder->update([
                    'content' => 'Maaf, Chutex AI sedang mengalami kendala. Silakan coba lagi beberapa saat lagi.',
                    'status' => Message::STATUS_FAILED,
                ]);
                $send('error', [
                    'type' => 'internal_error',
                    'fallback' => 'Maaf, Chutex AI sedang mengalami kendala. Silakan coba lagi beberapa saat lagi.',
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
