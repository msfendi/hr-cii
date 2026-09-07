<?php

namespace App\Services\Ai\Contracts;

interface AiDriverInterface
{
    /**
     * Send chat messages to the AI model and return standard text response.
     *
     * @param array $messages Array of ['role' => 'user'|'assistant'|'system', 'content' => '...']
     * @param array $options Additional options (model, temperature, etc.)
     * @return string
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Send chat messages and expect a parsed JSON array response.
     *
     * @param array $messages Array of ['role' => 'user'|'assistant'|'system', 'content' => '...']
     * @param array $options
     * @return array
     */
    public function chatJson(array $messages, array $options = []): array;
}
