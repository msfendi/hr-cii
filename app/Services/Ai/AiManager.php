<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiDriverInterface;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\ChutexDriver;
use App\Services\Ai\Drivers\DeepSeekDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\MiniMaxDriver;
use App\Services\Ai\Drivers\MistralDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\Ai\Drivers\OpenRouterDriver;
use InvalidArgumentException;

class AiManager
{
    protected array $drivers = [];

    public function driver(?string $name = null): AiDriverInterface
    {
        $name = $name ?: config('ai.default', 'openrouter');

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    protected function resolve(string $name): AiDriverInterface
    {
        $config = config("ai.providers.{$name}", []);

        return match ($name) {
            'openrouter' => new OpenRouterDriver($config),
            'gemini', 'google' => new GeminiDriver($config),
            'openai' => new OpenAiDriver($config),
            'deepseek' => new DeepSeekDriver($config),
            'anthropic', 'claude' => new AnthropicDriver($config),
            'minimax' => new MiniMaxDriver($config),
            'mistral' => new MistralDriver($config),
            'chutex' => new ChutexDriver($config),
            default => throw new InvalidArgumentException("AI Driver [{$name}] is not supported."),
        };
    }

    /**
     * Proxy call directly to the default driver
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
