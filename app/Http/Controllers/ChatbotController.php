<?php

namespace App\Http\Controllers;

use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChatbotController extends Controller
{
    /**
     * Batas jumlah file & ukuran per file untuk lampiran chat.
     */
    protected const MAX_ATTACHMENTS = 5;

    protected const MAX_ATTACHMENT_KB = 25600; // 25 MB per file

    /**
     * Whitelist ekstensi yang boleh diunggah. HANYA jenis file "aman" yang
     * tidak bisa dieksekusi oleh server maupun browser (dokumen, gambar,
     * audio, video) — TIDAK ADA html/htm/php/js/svg/exe/dll di daftar ini,
     * disengaja, supaya lampiran chat tidak bisa dipakai untuk upload shell
     * atau file yang bisa memicu XSS kalau dibuka langsung dari browser.
     */
    protected const ALLOWED_EXTENSIONS = [
        // gambar
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'bmp',
        // dokumen
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'csv',
        'rtf',
        'odt',
        'ods',
        'odp',
        // audio & video
        'mp4',
        'mov',
        'avi',
        'mkv',
        'webm',
        'mp3',
        'wav',
        'ogg',
    ];

    /**
     * Lapisan kedua: MIME type ASLI (dibaca dari isi file lewat fileinfo,
     * bukan dari nama file) yang dianggap valid untuk tiap ekstensi.
     * Ini mencegah trik rename file berbahaya (mis. shell.php ->
     * shell.png) lolos hanya karena namanya diubah.
     */
    protected const ALLOWED_MIME_MAP = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime'],
        'avi' => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
        'mkv' => ['video/x-matroska'],
        'webm' => ['video/webm', 'audio/webm'],
        'mp3' => ['audio/mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'ogg' => ['audio/ogg'],
    ];

    /**
     * MIME "berbahaya" yang ditolak mutlak walau lolos cek ekstensi/whitelist
     * di atas — jaga-jaga kalau isi file ternyata beda dari yang diharapkan
     * (mis. file diberi nama .txt tapi isinya markup HTML/script aktif).
     */
    protected const FORBIDDEN_MIMES = [
        'text/html',
        'application/xhtml+xml',
        'application/x-php',
        'application/x-httpd-php',
        'application/x-msdownload',
        'application/x-sh',
        'text/x-shellscript',
        'application/x-executable',
        'image/svg+xml',
    ];

    public function __construct(protected AiChatService $aiChatService) {}

    /**
     * Halaman utama chatbot: daftar sesi + panel chat.
     */
    public function index(Request $request): View
    {
        $sessions = ChatbotSession::ownedByCurrentUser()
            ->latest()
            ->get();

        $activeSession = null;

        if ($request->filled('session')) {
            $activeSession = ChatbotSession::with('messages')->find($request->integer('session'));
        }

        return view('chatbot.index', [
            'sessions' => $sessions,
            'activeSession' => $activeSession,
        ]);
    }

    /**
     * Membuat sesi chat baru (dipanggil lewat AJAX dari tombol "Chat Baru").
     */
    public function storeSession(Request $request): JsonResponse
    {
        $enabledProviders = collect(config('services.ai_providers', []))
            ->filter(fn($p) => (bool) ($p['enabled'] ?? false))
            ->keys()
            ->all();

        $validated = $request->validate([
            'ai_provider' => ['required', 'string', 'in:' . implode(',', $enabledProviders)],
            'mode' => ['nullable', 'string', 'in:' . ChatbotSession::MODE_GLOBAL . ',' . ChatbotSession::MODE_RTG],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $session = ChatbotSession::create([
            'user_id' => $request->user()?->id,
            'ai_provider' => $validated['ai_provider'],
            'mode' => $validated['mode'] ?? ChatbotSession::MODE_RTG,
            'title' => $validated['title'] ?? null,
        ]);

        return response()->json([
            'session' => $session,
        ], 201);
    }

    /**
     * Ambil semua pesan di sebuah sesi (dipakai saat user klik sesi di sidebar).
     */
    public function messages(ChatbotSession $chatbotSession): JsonResponse
    {
        return response()->json([
            'session' => $chatbotSession,
            'messages' => $chatbotSession->messages,
        ]);
    }

    /**
     * Kirim pesan user, panggil AI, simpan kedua pesan, kembalikan hasilnya.
     */
    public function send(Request $request, ChatbotSession $chatbotSession): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:4000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:' . self::MAX_ATTACHMENTS],
            'attachments.*' => [
                'file',
                'max:' . self::MAX_ATTACHMENT_KB,
                'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
            ],
        ], [
            'message.required_without' => 'Tulis pesan atau lampirkan minimal satu file.',
            'attachments.max' => 'Maksimal ' . self::MAX_ATTACHMENTS . ' file per pesan.',
            'attachments.*.mimes' => 'Salah satu file memiliki format yang tidak didukung/tidak aman untuk diunggah.',
            'attachments.*.max' => 'Ukuran file maksimal ' . (int) (self::MAX_ATTACHMENT_KB / 1024) . ' MB.',
        ]);

        $messageText = trim((string) ($validated['message'] ?? ''));
        $attachments = $this->storeAttachments($request->file('attachments', []), $chatbotSession);

        $userMessage = ChatbotMessage::create([
            'chatbot_session_id' => $chatbotSession->id,
            'role' => ChatbotMessage::ROLE_USER,
            'content' => $messageText,
            'meta' => $attachments ? ['attachments' => $attachments] : null,
        ]);

        try {
            $result = $this->aiChatService->reply($chatbotSession, $messageText, $attachments);

            $assistantMessage = ChatbotMessage::create([
                'chatbot_session_id' => $chatbotSession->id,
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'content' => $result['content'],
                'meta' => $result['meta'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $assistantMessage = ChatbotMessage::create([
                'chatbot_session_id' => $chatbotSession->id,
                'role' => ChatbotMessage::ROLE_ASSISTANT,
                'content' => 'Maaf, terjadi kendala saat menghubungi AI provider. Silakan coba lagi sebentar lagi.',
                'meta' => ['error' => $e->getMessage()],
            ]);
        }

        // Set judul sesi otomatis dari pesan pertama, kalau belum ada judul.
        if (empty($chatbotSession->title)) {
            $chatbotSession->update(['title' => $chatbotSession->resolveTitle()]);
        } else {
            $chatbotSession->touch();
        }

        return response()->json([
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'session' => $chatbotSession->fresh(),
        ]);
    }

    public function destroySession(ChatbotSession $chatbotSession): JsonResponse
    {
        $chatbotSession->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Validasi + simpan lampiran file yang diunggah lewat chat. Setiap file
     * melewati DUA lapis pengecekan sebelum disimpan:
     *   1. Ekstensi harus ada di whitelist ALLOWED_EXTENSIONS (juga sudah
     *      dicek oleh rule validasi 'mimes' di atas).
     *   2. MIME type ASLI (dibaca dari isi file, bukan dari nama file) harus
     *      cocok dengan ALLOWED_MIME_MAP untuk ekstensi tsb, dan tidak boleh
     *      termasuk FORBIDDEN_MIMES (mis. text/html, image/svg+xml).
     * File yang gagal lapis kedua ini (jarang terjadi karena rule 'mimes'
     * biasanya sudah menyaring duluan) DILEWATI diam-diam, bukan bikin
     * seluruh request gagal — supaya file lain yang valid tetap terkirim.
     *
     * Nama file asli TIDAK dipakai sebagai nama file di disk — diganti nama
     * acak supaya tidak bisa dipakai untuk path traversal atau menimpa file
     * lain, dan supaya file tidak bisa "dieksekusi" lewat URL langsung
     * meskipun namanya di-rename jadi ekstensi aman.
     */
    protected function storeAttachments(array $files, ChatbotSession $chatbotSession): array
    {
        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: ''));

            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $realMime = (string) $file->getMimeType();
            $allowedMimes = self::ALLOWED_MIME_MAP[$extension] ?? [];

            if (in_array($realMime, self::FORBIDDEN_MIMES, true)) {
                continue;
            }

            if (! empty($allowedMimes) && ! in_array($realMime, $allowedMimes, true)) {
                continue;
            }

            $safeName = Str::random(24) . '.' . $extension;
            $directory = $this->attachmentDirectory() . '/' . $chatbotSession->id;

            $path = $file->storeAs($directory, $safeName, $this->attachmentDisk());

            if (! $path) {
                continue;
            }

            $stored[] = [
                'original_name' => Str::limit($file->getClientOriginalName(), 150, ''),
                'filename' => $safeName,
                'path' => $path,
                'url' => Storage::disk($this->attachmentDisk())->url($path),
                'mime' => $realMime,
                'extension' => $extension,
                'size' => $file->getSize(),
                'kind' => $this->attachmentKind($extension),
            ];
        }

        return $stored;
    }

    protected function attachmentKind(string $extension): string
    {
        return match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) => 'image',
            in_array($extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'], true) => 'video',
            in_array($extension, ['mp3', 'wav', 'ogg'], true) => 'audio',
            default => 'document',
        };
    }

    protected function attachmentDisk(): string
    {
        return config('chatbot.export_disk', 'public');
    }

    protected function attachmentDirectory(): string
    {
        return trim(config('chatbot.attachment_directory', 'chatbot-attachments'), '/');
    }
}
