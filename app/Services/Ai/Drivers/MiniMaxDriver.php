<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MiniMaxDriver implements AiDriverInterface
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
            throw new RuntimeException('MINIMAX_API_KEY is not configured in .env');
        }

        $baseUrl = rtrim($this->config['base_url'] ?? 'https://openrouter.ai/api/v1', '/');
        $model = $options['model'] ?? $this->config['model'] ?? 'MiniMax-Text-01';
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
        ];

        // Include GroupId if provided (some legacy MiniMax accounts)
        if (!empty($this->config['group_id'])) {
            $headers['GroupId'] = $this->config['group_id'];
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->post("{$baseUrl}/chat/completions", $payload);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message') 
                    ?? $response->json('base_resp.status_msg') 
                    ?? $response->body();
                Log::error("MiniMax API Error [{$response->status()}]: {$errorMsg}");
                throw new RuntimeException("MiniMax API Error: {$errorMsg}");
            }

            $result = $response->json();

            // Check standard OpenAI-format choices
            if (isset($result['choices'][0]['message']['content'])) {
                return $result['choices'][0]['message']['content'];
            }

            // Check if base_resp has error code
            if (isset($result['base_resp']) && ($result['base_resp']['status_code'] ?? 0) !== 0) {
                $msg = $result['base_resp']['status_msg'] ?? 'Unknown MiniMax error';
                throw new RuntimeException("MiniMax API Error: {$msg}");
            }

            return '';
        } catch (\Throwable $e) {
            Log::error('MiniMax call failed: ' . $e->getMessage());
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
        // Clean markdown backticks ```json ... ```
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));

        // Extract first JSON object or array if text has surrounding commentary
        if (preg_match('/(\{(?:[^{}]|(?R))*\}|\[(?:[^\[\]]|(?R))*\])/s', $clean, $matches)) {
            $clean = $matches[0];
        }

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse MiniMax JSON response: ' . $text);
            return [
                'raw' => $text,
                'error' => 'Invalid JSON structure returned by AI: ' . json_last_error_msg(),
            ];
        }

        return $decoded;
    }
}
