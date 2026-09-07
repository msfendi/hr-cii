<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiDriver implements AiDriverInterface
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
            throw new RuntimeException('AI API Key is not configured.');
        }

        $baseUrl = rtrim($this->config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $model = $options['model'] ?? $this->config['model'] ?? 'gpt-4o-mini';
        $timeout = $options['timeout'] ?? $this->config['timeout'] ?? 60;

        $maxTokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 4096;

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => (int) $maxTokens,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($timeout)->post("{$baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? $response->body();
                throw new RuntimeException("AI API Error [{$response->status()}]: {$errorMsg}");
            }

            $result = $response->json();
            return $result['choices'][0]['message']['content'] ?? '';
        } catch (\Throwable $e) {
            Log::error('AI call failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function chatJson(array $messages, array $options = []): array
    {
        $rawResponse = $this->chat($messages, $options);
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($rawResponse)));

        if (preg_match('/(\{(?:[^{}]|(?R))*\}|\[(?:[^\[\]]|(?R))*\])/s', $clean, $matches)) {
            $clean = $matches[0];
        }

        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : ['raw' => $rawResponse];
    }
}
