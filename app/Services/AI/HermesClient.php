<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HermesClient — satu-satunya titik Laravel bicara ke Hermes Agent.
 * Secret (API key) tidak pernah keluar dari server (PRD §4.3).
 */
class HermesClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout,
    ) {}

    /**
     * Kirim percakapan ke Hermes (OpenAI-compatible chat completions).
     *
     * @param array<int, array{role:string,content:string}> $messages
     * @return array{content:string,model:?string,provider:?string,input_tokens:int,output_tokens:int,ok:bool,error_type:?string,latency_ms:int}
     */
    public function chat(array $messages, ?string $sessionId = null): array
    {
        $startedAt = microtime(true);
        $payload = [
            'messages' => $messages,
            'stream' => false,
        ];

        try {
            $request = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->connectTimeout(10);

            // Session continuity: gateway /v1/chat/completions bersifat stateless
            // dan HANYA membaca X-Hermes-Session-Id dari HEADER. Field session_id
            // di body diabaikan, sehingga tiap request dianggap sesi baru.
            if ($sessionId !== null) {
                $request = $request->withHeaders(['X-Hermes-Session-Id' => $sessionId]);
            }

            $response = $request->post(rtrim($this->baseUrl, '/') . '/v1/chat/completions', $payload);
        } catch (ConnectionException $e) {
            return $this->failure('connection_error', $startedAt);
        }

        $latency = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            $status = $response->status();
            return [
                'content' => '',
                'model' => null,
                'provider' => null,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'ok' => false,
                'error_type' => $status >= 500 ? 'hermes_error' : ($status === 429 ? 'rate_limited' : 'provider_error'),
                'latency_ms' => $latency,
            ];
        }

        $data = $response->json() ?? [];
        $choice = $data['choices'][0] ?? [];

        return [
            'content' => $this->extractText($choice['message']['content'] ?? ''),
            'model' => $data['model'] ?? null,
            'provider' => null,
            'input_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'output_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            'ok' => true,
            'error_type' => null,
            'latency_ms' => $latency,
        ];
    }

    /**
     * Streaming SSE: meneruskan chunk dari Hermes ke browser sebagai SSE.
     * Callback dipanggil per delta teks; hasil akhir dikembalikan via $onDone.
     */
    public function streamChat(array $messages, ?string $sessionId, callable $onDelta, callable $onDone, callable $onError): void
    {
        $startedAt = microtime(true);
        $payload = ['messages' => $messages, 'stream' => true];

        $fullText = '';
        $model = null;

        // Penjaga waktu: koneksi ke gateway bisa tetap hidup tanpa mengirim
        // isi (insiden nyata: request menggantung 17,5 menit lalu berakhir
        // dengan jawaban setengah jadi + "[response interrupted]").
        $guard = new StreamGuard();
        $maxDuration = (int) config('hermes.stream_max_duration', 240);
        $idleTimeout = (int) config('hermes.stream_idle_timeout', 90);
        $lastActivity = $startedAt;

        try {
            $request = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->connectTimeout(10);

            // Session continuity via header — lihat catatan di chat().
            if ($sessionId !== null) {
                $request = $request->withHeaders(['X-Hermes-Session-Id' => $sessionId]);
            }

            $response = $request
                ->withOptions(['stream' => true])
                ->post(rtrim($this->baseUrl, '/') . '/v1/chat/completions', $payload);

            $stream = $response->toPsrResponse()->getBody();
            $buffer = '';

            while (!$stream->eof()) {
                // Cek batas waktu SEBELUM blocking read supaya stream yang
                // macet tidak menunggu selamanya.
                $now = microtime(true);
                $reason = $guard->abortReason($now - $startedAt, $now - $lastActivity, $maxDuration, $idleTimeout);
                if ($reason !== '') {
                    $onError('stream_timeout', (int) round(($now - $startedAt) * 1000));

                    return;
                }

                $buffer .= $stream->read(4096);
                if ($buffer !== '') {
                    $lastActivity = microtime(true);
                }
                while (($nl = mb_strpos($buffer, "\n")) !== false) {
                    $line = trim(mb_substr($buffer, 0, $nl));
                    $buffer = mb_substr($buffer, $nl + 1);
                    if (!str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') {
                        continue;
                    }
                    $data = json_decode($json, true);
                    if (!$data) {
                        continue;
                    }
                    $model ??= $data['model'] ?? null;
                    $delta = $this->extractText($data['choices'][0]['delta']['content'] ?? '');
                    if ($delta !== '') {
                        $fullText .= $delta;
                        $onDelta($delta);
                    }
                }
            }

            if ($response->failed() && $fullText === '') {
                $onError('hermes_error', (int) round((microtime(true) - $startedAt) * 1000));
                return;
            }
        } catch (\Throwable $e) {
            $onError('connection_error', (int) round((microtime(true) - $startedAt) * 1000));
            return;
        }

        $onDone([
            'content' => $fullText,
            'model' => $model,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    /** Ambil satu file hasil kerja Hermes (MEDIA:) dari host gateway. Null jika gagal. */
    public function fetchFile(string $absPath): ?string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->connectTimeout(10)
                ->get(rtrim($this->baseUrl, '/') . '/v1/files/download', ['path' => $absPath]);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function healthy(): bool
    {
        try {
            return Http::withToken($this->apiKey)
                ->timeout(5)
                ->connectTimeout(3)
                ->get(rtrim($this->baseUrl, '/') . '/health')
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Hapus Hermes session (best-effort). Dipanggil saat conversation dihapus
     * agar session tidak menumpuk di Hermes WebUI.
     */
    public function deleteSession(string $sessionId): void
    {
        try {
            Http::withToken($this->apiKey)
                ->timeout(5)
                ->connectTimeout(3)
                // Gateway hanya mengekspos DELETE /api/sessions/{id};
                // /v1/sessions/{id} mengembalikan 404 (gagal senyap).
                ->delete(rtrim($this->baseUrl, '/') . '/api/sessions/' . $sessionId);
        } catch (\Throwable) {
            // Best-effort — gagal hapus tidak kritis, data app sudah dihapus.
        }
    }

    /**
     * Daftar model yang tersedia di gateway Hermes (GET /v1/models).
     * Return [] bila gateway mati — admin panel menampilkan statusnya.
     *
     * @return array<int, array{id:string, label:?string, provider:?string, active:bool}>
     */
    public function listModels(): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(6)
                ->connectTimeout(3)
                ->get(rtrim($this->baseUrl, '/') . '/v1/models');
        } catch (\Throwable) {
            return [];
        }

        if ($response->failed()) {
            return [];
        }

        $active = (string) config('hermes.model', '');
        $out = [];
        foreach ((array) ($response->json('data') ?? []) as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'label' => $m['name'] ?? null,
                'provider' => $m['owned_by'] ?? $m['provider'] ?? null,
                'active' => $id === $active || $active === '',
            ];
        }

        return $out;
    }

    private function failure(string $type, float $startedAt): array
    {
        return [
            'content' => '', 'model' => null, 'provider' => null,
            'input_tokens' => 0, 'output_tokens' => 0,
            'ok' => false, 'error_type' => $type,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * Ambil teks dari delta/content provider yang bisa berupa string ATAU
     * array part (format content_parts OpenAI). Provider yang mengirim
     * tool_calls / array content sebelumnya berakhir sebagai "[object Object]"
     * di frontend karena JSON.stringify({}) — normalisasi di sini.
     */
    private function extractText(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $out = '';
            foreach ($content as $part) {
                if (is_string($part)) {
                    $out .= $part;
                } elseif (is_array($part) && isset($part['text'])) {
                    $out .= (string) $part['text'];
                }
            }

            return $out;
        }

        return '';
    }
}
