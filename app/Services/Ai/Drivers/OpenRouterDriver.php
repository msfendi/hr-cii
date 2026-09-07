<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenRouterDriver implements AiDriverInterface
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
            throw new RuntimeException('OPENROUTER_API_KEY is not configured in .env');
        }

        $baseUrl = rtrim($this->config['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
        $model = $options['model'] ?? $this->config['model'] ?? 'minimax/minimax-01';
        $timeout = $options['timeout'] ?? $this->config['timeout'] ?? 90;

        $maxTokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 4096;

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => (int) $maxTokens,
        ];

        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => $this->config['site_url'] ?? 'http://localhost',
            'X-Title' => $this->config['site_name'] ?? 'HR-CII Assistant',
        ];

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->post("{$baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') ?? $response->body();
                Log::error("OpenRouter API Error [{$response->status()}]: {$errorMsg}");
                throw new RuntimeException("OpenRouter API Error: {$errorMsg}");
            }

            $result = $response->json();
            return $result['choices'][0]['message']['content'] ?? '';
        } catch (\Throwable $e) {
            Log::error('OpenRouter call failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function chatJson(array $messages, array $options = []): array
    {
        // Enforce JSON instructions in system message if not present
        $options['temperature'] = $options['temperature'] ?? 0.1;

        $rawResponse = $this->chat($messages, $options);
        return $this->parseJson($rawResponse);
    }

    protected function parseJson(string $text): array
    {
        // Clean markdown backticks ```json ... ```
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));

        // Extract first JSON object or array if text has surrounding commentary
        if (preg_match('/(\{(?:[^{}]|(?R))*\}|\[(?:[^\[\]]|(?R))*\])/s', $clean, $matches)) {
            $clean = $matches[0];
        }

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI JSON response: ' . $text);
            return [
                'raw' => $text,
                'error' => 'Invalid JSON structure returned by AI: ' . json_last_error_msg(),
            ];
        }

        return $decoded;
    }
}
