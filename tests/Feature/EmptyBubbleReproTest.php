<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\AI\ChatService;
use App\Services\AI\HermesClient;
use App\Services\AI\PromptGuard;
use App\Services\AI\ResponseSanitizer;
use App\Services\AI\ClarificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Repro untuk bug "bubble kosong": user kirim "tampilkan data dimas galang ramadhan."
 * dan bot tidak membalas sama sekali (bubble putih kosong, tanpa teks, tanpa error).
 * Jalur streaming (SSE) — jalur yang dipakai browser — dicek langsung lewat respondStreaming
 * dengan Http::fake agar crash/empty di titik ini ketahuan.
 */
class EmptyBubbleReproTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => Role::USER]);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'username' => 'repro-user',
            'email' => 'repro@test.local',
        ]);
        $this->conversation = Conversation::create(['user_id' => $this->user->id, 'title' => 'New chat']);
    }

    private function chatService(): ChatService
    {
        $hermes = new HermesClient(config('hermes.url'), config('hermes.api_key'), 120);
        return new ChatService(
            $hermes,
            new PromptGuard(),
            null,
            null,
            new ClarificationService(),
            new ResponseSanitizer(),
        );
    }

    private function placeholder(): Message
    {
        return Message::create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'role' => 'assistant',
            'content' => '',
            'status' => Message::STATUS_PENDING,
        ]);
    }

    public function test_streaming_tabel_datas_query_tersimpan(): void
    {
        $tableAnswer = "| Nama | NPK | Jabatan |\n|---|---|---|\n| Dimas Galang Ramadhan | C-00827 | IT HOD |";

        Http::fake([
            rtrim(config('hermes.url'), '/') . '/v1/chat/completions' => Http::response([
                'model' => 'chutex-test-model',
                'choices' => [['message' => ['role' => 'assistant', 'content' => $tableAnswer]]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ], 200),
        ]);

        $placeholder = $this->placeholder();

        $result = $this->chatService()->respondStreaming(
            $this->user,
            $this->conversation,
            'tampilkan data dimas galang ramadhan.',
            onDelta: fn (string $d) => null,
            assistantPlaceholder: $placeholder,
        );

        $assistant = $result['assistant_message'];
        $this->assertSame(Message::STATUS_DONE, $assistant->status);
        $this->assertNotEmpty(trim((string) $assistant->content));
        $this->assertStringContainsString('Dimas Galang Ramadhan', (string) $assistant->content);
    }

    public function test_streaming_delta_array_content_tidak_hilang(): void
    {
        // Provider mengirim content sebagai array part (OpenAI content_parts).
        Http::fake([
            rtrim(config('hermes.url'), '/') . '/v1/chat/completions' => Http::response([
                'model' => 'chutex-test-model',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'text', 'text' => 'Nama: Dimas Galang Ramadhan'],
                            ['type' => 'text', 'text' => ', NPK C-00827.'],
                        ],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5],
            ], 200),
        ]);

        $placeholder = $this->placeholder();

        $result = $this->chatService()->respondStreaming(
            $this->user,
            $this->conversation,
            'tampilkan data dimas galang ramadhan.',
            onDelta: fn (string $d) => null,
            assistantPlaceholder: $placeholder,
        );

        $assistant = $result['assistant_message'];
        $this->assertSame(Message::STATUS_DONE, $assistant->status);
        $this->assertNotEmpty(trim((string) $assistant->content));
        $this->assertStringContainsString('Dimas Galang Ramadhan', (string) $assistant->content);
    }

    public function test_streaming_gateway_gagal_menyimpan_pesan_error_bukan_kosong(): void
    {
        Http::fake([
            rtrim(config('hermes.url'), '/') . '/v1/chat/completions' => Http::response('', 500),
        ]);

        $placeholder = $this->placeholder();

        $result = $this->chatService()->respondStreaming(
            $this->user,
            $this->conversation,
            'tampilkan data dimas galang ramadhan.',
            onDelta: fn (string $d) => null,
            assistantPlaceholder: $placeholder,
        );

        $assistant = $result['assistant_message'];
        $this->assertSame(Message::STATUS_FAILED, $assistant->status);
        $this->assertNotEmpty(trim((string) $assistant->content), 'Pesan gagal harus punya isi fallback, bukan bubble kosong.');
    }

    public function test_streaming_empty_string_response_menyimpan_fallback_bukan_kosong(): void
    {
        Http::fake([
            rtrim(config('hermes.url'), '/') . '/v1/chat/completions' => Http::response([
                'model' => 'chutex-test-model',
                'choices' => [['message' => ['role' => 'assistant', 'content' => '']]],
            ], 200),
        ]);

        $placeholder = $this->placeholder();

        $result = $this->chatService()->respondStreaming(
            $this->user,
            $this->conversation,
            'tampilkan data dimas galang ramadhan.',
            onDelta: fn (string $d) => null,
            assistantPlaceholder: $placeholder,
        );

        $assistant = $result['assistant_message'];
        // Jawaban kosong dari provider harus jadi fallback yang jelas, bukan bubble kosong.
        $this->assertNotEmpty(trim((string) $assistant->content));
    }

    public function test_streaming_answer_options_only_masih_tersimpan_sebagai_konten(): void
    {
        // Model membalas HANYA blok OPTIONS (tanpa kalimat pengantar).
        Http::fake([
            rtrim(config('hermes.url'), '/') . '/v1/chat/completions' => Http::response([
                'model' => 'chutex-test-model',
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => "OPTIONS:\n- Tampilkan isi dokumen\n- Ringkas dokumen\n- Unduh PDF"],
                ]],
            ], 200),
        ]);

        $placeholder = $this->placeholder();

        $result = $this->chatService()->respondStreaming(
            $this->user,
            $this->conversation,
            'tampilkan dokumen retur',
            onDelta: fn (string $d) => null,
            assistantPlaceholder: $placeholder,
        );

        $assistant = $result['assistant_message'];
        $this->assertNotEmpty($assistant->options);
        // Opsi tersimpan; konten boleh kosong hanya jika opsi ada.
        $this->assertTrue(
            trim((string) $assistant->content) !== '' || !empty($assistant->options),
            'Konten dan opsi tidak boleh kosong bersamaan (bubble kosong).'
        );
    }
}
