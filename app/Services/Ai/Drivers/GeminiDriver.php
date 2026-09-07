<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiDriver implements AiDriverInterface
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
            throw new RuntimeException('GEMINI_API_KEY is not configured in .env');
        }

        $model = $options['model'] ?? $this->config['model'] ?? 'gemini-3.6-flash';
        $baseUrl = rtrim($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta', '/');
        $timeout = $options['timeout'] ?? $this->config['timeout'] ?? 60;

        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $text = $msg['content'] ?? '';

            if ($role === 'system') {
                $systemInstruction = ['parts' => [['text' => $text]]];
            } else {
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $text]],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.2,
            ],
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = Http::timeout($timeout)->post($url, $payload);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? $response->body();
                Log::error("Gemini API Error [{$response->status()}]: {$errorMsg}");
                throw new RuntimeException("Gemini API Error: {$errorMsg}");
            }

            $result = $response->json();
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } catch (\Throwable $e) {
            Log::error('Gemini call failed: ' . $e->getMessage());
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
