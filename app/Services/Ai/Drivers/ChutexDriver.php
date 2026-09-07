<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChutexDriver implements AiDriverInterface
{
    protected array $config;
    protected static ?string $resolvedModel = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function chat(array $messages, array $options = []): string
    {
        $apiKey = $this->config['api_key'] ?? '';
        $baseUrl = rtrim($this->config['base_url'] ?? 'http://192.168.1.253/v1', '/');
        $model = $this->resolveModel($options['model'] ?? null, $baseUrl, $apiKey);
        $timeout = $options['timeout'] ?? $this->config['timeout'] ?? 90;
        $maxTokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 4096;
        $budgetTier = $options['budget_tier'] ?? $this->config['budget_tier'] ?? null;

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => (int) $maxTokens,
            // Requested for providers that honor it. Some combos behind 9Router
            // (e.g. Claude via Kiro) stream regardless of this flag — handled
            // below via parseSseStream() as a fallback.
            'stream' => false,
        ];

        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        // 9Router: only meaningful when $model === 'auto'. Sent both as a header
        // and in the body since the router's own docs show it both ways —
        // harmless either way if only one is actually honored.
        if (!empty($budgetTier) && $model === 'auto') {
            $payload['prompt_budget'] = $budgetTier;
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        if (!empty($budgetTier) && $model === 'auto') {
            $headers['prompt_budget'] = $budgetTier;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->post("{$baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? $response->body();
                Log::error("Chutex/9Router API Error [{$response->status()}]: {$errorMsg}");
                throw new RuntimeException("Chutex/9Router API Error [{$response->status()}]: {$errorMsg}");
            }

            $rawBody = $response->body();

            // Some combos behind 9Router (e.g. Claude via Kiro) always respond
            // with Server-Sent Events regardless of "stream": false. Detect and
            // parse that case separately from a normal single JSON completion.
            $contentType = $response->header('Content-Type') ?? '';
            $looksLikeSse = str_contains($contentType, 'text/event-stream')
                || str_starts_with(ltrim($rawBody), 'data:');

            if ($looksLikeSse) {
                $content = $this->parseSseStream($rawBody);
            } else {
                $result = $response->json();

                // Fallback for older/incompatible 9Router instances that don't
                // support "model": "auto"/combos and instead reply with a model
                // list. Not expected when routing is configured correctly, but
                // kept as a safety net.
                if (isset($result['object']) && $result['object'] === 'list' && !empty($result['data'])) {
                    $firstModel = $result['data'][0]['id'] ?? 'gemini/gemini-3.7-flash';

                    self::$resolvedModel = $firstModel;
                    Log::warning("9Router returned a model list instead of a completion for model '{$model}'. Falling back to: {$firstModel}. Check the 9Router dashboard/combo config.");

                    $payload['model'] = $firstModel;
                    $retryResponse = Http::withHeaders($headers)
                        ->timeout($timeout)
                        ->post("{$baseUrl}/chat/completions", $payload);

                    if ($retryResponse->successful()) {
                        $rawBody = $retryResponse->body();
                        $retryContentType = $retryResponse->header('Content-Type') ?? '';
                        if (str_contains($retryContentType, 'text/event-stream') || str_starts_with(ltrim($rawBody), 'data:')) {
                            $content = $this->parseSseStream($rawBody);
                            $result = null;
                        } else {
                            $result = $retryResponse->json();
                        }
                    } else {
                        $errorMsg = $retryResponse->json('error.message') ?? $retryResponse->body();
                        throw new RuntimeException("Chutex/9Router API Error [{$retryResponse->status()}]: {$errorMsg}");
                    }
                }

                if (!isset($content)) {
                    // Some 9Router error responses come back with an HTTP 200
                    // status but an {"error": "..."} envelope instead of a
                    // completion (e.g. missing/invalid API key, dead credentials
                    // for a combo's provider). Treat that as a real failure
                    // instead of silently falling through to empty content.
                    if (isset($result['error']) && !isset($result['choices']) && !isset($result['response']) && !isset($result['content'])) {
                        $errorMsg = is_array($result['error']) ? ($result['error']['message'] ?? json_encode($result['error'])) : $result['error'];
                        Log::error("Chutex/9Router returned an error envelope (HTTP {$response->status()}): {$errorMsg}");
                        throw new RuntimeException("Chutex/9Router API Error: {$errorMsg}");
                    }

                    // Support standard OpenAI content, reasoning_content (for R1/thinking models), completion text, or direct response
                    $content = $result['choices'][0]['message']['content']
                        ?? $result['choices'][0]['message']['reasoning_content']
                        ?? $result['choices'][0]['text']
                        ?? $result['response']
                        ?? $result['content']
                        ?? '';
                }
            }

            if (empty(trim((string)$content))) {
                Log::warning('Chutex/9Router returned empty content. Response body: ' . $rawBody);
            }

            return (string) $content;
        } catch (\Throwable $e) {
            Log::error('Chutex AI call failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse a Server-Sent Events response body (one or more "data: {json}"
     * lines) into the concatenated assistant text. Used when a combo/provider
     * behind 9Router streams regardless of the "stream": false request flag.
     */
    protected function parseSseStream(string $rawBody): string
    {
        $content = '';

        foreach (preg_split('/\r\n|\r|\n/', $rawBody) as $line) {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, 'data:')) {
                continue;
            }

            $jsonPart = trim(substr($line, 5));
            if ($jsonPart === '' || $jsonPart === '[DONE]') {
                continue;
            }

            $chunk = json_decode($jsonPart, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($chunk)) {
                continue;
            }

            $delta = $chunk['choices'][0]['delta']['content']
                ?? $chunk['choices'][0]['message']['content']
                ?? null;

            if ($delta !== null) {
                $content .= $delta;
            }
        }

        return $content;
    }

    /**
     * Decide which "model" string to send to 9Router.
     *
     * Priority:
     *  1. A model explicitly passed for THIS call ($options['model']) — lets a
     *     specific feature force a specific model/combo when it needs to.
     *  2. Whatever is configured (CHUTEX_MODEL) — sent to 9Router AS-IS. This is
     *     normally your "Chutex" combo name (a named routing/vision-adapter
     *     preset configured in the 9Router dashboard), but can be a concrete
     *     model id too. 9Router itself resolves combos, so the driver must not
     *     special-case or reject the string here.
     *  3. If nothing at all is configured and auto_route is enabled, fall back
     *     to "auto" and let 9Router pick a live model with no preset guidance.
     *  4. Last-resort legacy discovery via /models, only if auto_route is off
     *     and truly nothing else is configured.
     */
    protected function resolveModel(?string $explicitModel, string $baseUrl, string $apiKey): string
    {
        if (!empty($explicitModel)) {
            return $explicitModel;
        }

        $configuredModel = $this->config['model'] ?? null;
        if (!empty($configuredModel)) {
            return $configuredModel;
        }

        if ($this->config['auto_route'] ?? false) {
            return 'auto';
        }

        if (!empty(self::$resolvedModel)) {
            return self::$resolvedModel;
        }

        return Cache::remember('9router_resolved_model', 3600, function () use ($baseUrl, $apiKey) {
            try {
                $headers = ['Content-Type' => 'application/json'];
                if (!empty($apiKey)) {
                    $headers['Authorization'] = 'Bearer ' . $apiKey;
                }

                $res = Http::withHeaders($headers)->timeout(4)->get("{$baseUrl}/models");
                if ($res->successful()) {
                    $models = $res->json('data') ?? [];
                    if (!empty($models)) {
                        foreach (['gemini/gemini-3.7-flash', 'gemini/gemini-3.6-flash', 'groq/llama-3.3-70b-versatile', 'groq/qwen/qwen3-32b'] as $preferred) {
                            foreach ($models as $m) {
                                if (isset($m['id']) && $m['id'] === $preferred) {
                                    return $m['id'];
                                }
                            }
                        }
                        if (isset($models[0]['id'])) {
                            return $models[0]['id'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Could not auto-fetch 9Router models: ' . $e->getMessage());
            }

            return 'gemini/gemini-3.7-flash';
        });
    }

    public function chatJson(array $messages, array $options = []): array
    {
        $options['temperature'] = $options['temperature'] ?? 0.1;
        $rawResponse = $this->chat($messages, $options);
        return $this->parseJson($rawResponse);
    }

    protected function parseJson(string $text): array
    {
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));

        if (preg_match('/(\{(?:[^{}]|(?R))*\}|\[(?:[^\[\]]|(?R))*\])/s', $clean, $matches)) {
            $clean = $matches[0];
        }

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse Chutex JSON response: ' . $text);
            return [
                'raw' => $text,
                'error' => 'Invalid JSON structure returned by AI: ' . json_last_error_msg(),
            ];
        }

        return $decoded;
    }
}