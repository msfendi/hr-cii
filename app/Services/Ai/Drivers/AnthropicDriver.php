<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicDriver implements AiDriverInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function chat(array $messages, array $options = []): string
    {
        $apiKey = $this->config['api_key'] ?? '';
        if (empty($apiKey)) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured in .env');
        }

        $baseUrl = rtrim($this->config['base_url'] ?? 'https://api.anthropic.com/v1', '/');
        $model = $options['model'] ?? $this->config['model'] ?? 'claude-3-5-sonnet-20241022';
        $timeout = $this->config['timeout'] ?? 60;
        $maxTokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 4096;

        // Anthropic Messages API separates system prompt from messages array
        $systemPrompt = null;
        $anthropicMessages = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

            if ($role === 'system') {
                $systemPrompt = $content;
            } else {
                $anthropicMessages[] = [
                    'role' => $role === 'assistant' ? 'assistant' : 'user',
                    'content' => $content,
                ];
            }
        }

        // Ensure at least one message exists
        if (empty($anthropicMessages)) {
            $anthropicMessages[] = ['role' => 'user', 'content' => 'Hello'];
        }

        $payload = [
            'model' => $model,
            'messages' => $anthropicMessages,
            'max_tokens' => (int) $maxTokens,
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }

        $endpoint = str_ends_with($baseUrl, '/messages') ? $baseUrl : "{$baseUrl}/messages";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout($timeout)->post($endpoint, $payload);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? $response->body();
                Log::error("Anthropic API Error [{$response->status()}]: {$errorMsg}");
                throw new RuntimeException("Anthropic API Error [{$response->status()}]: {$errorMsg}");
            }

            $result = $response->json();
            return $result['content'][0]['text'] ?? '';
        } catch (\Throwable $e) {
            Log::error('Anthropic call failed: ' . $e->getMessage());
            throw $e;
        }
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
            Log::warning('Failed to parse Anthropic JSON response: ' . $text);
            return [
                'raw' => $text,
                'error' => 'Invalid JSON structure returned by AI: ' . json_last_error_msg(),
            ];
        }

        return $decoded;
    }
}
