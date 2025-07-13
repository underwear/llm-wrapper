<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper;

use GuzzleHttp\ClientInterface;
use Underwear\LlmWrapper\ChatBuilder\ChatBuilder;
use Underwear\LlmWrapper\Drivers\AnthropicDriver;
use Underwear\LlmWrapper\Drivers\OpenAiDriver;
use Underwear\LlmWrapper\LlmResponse\LlmResponse;

class LlmClient
{
    public function __construct(
        private readonly LlmDriverInterface $driver,
    ) {
    }

    public static function make(
        string $provider,
        array $config = [],
        ?string $model = null,
        ?ClientInterface $customHttpClient = null,
    ): self {
        $driver = match ($provider) {
            'openai' => new OpenAiDriver(),
            'anthropic' => new AnthropicDriver(),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}")
        };

        $driver->setConfig($config);

        if ($model !== null) {
            $driver->setDefaultModel($model);
        }

        if ($customHttpClient !== null) {
            $driver->setHttpClient($customHttpClient);
        }

        return new self($driver);
    }

    public static function openai(array $config = [], ?string $model = null): self
    {
        return self::make('openai', $config, $model);
    }

    public static function anthropic(array $config = [], ?string $model = null): self
    {
        return self::make('anthropic', $config, $model);
    }

    public static function claude(array $config = [], ?string $model = null): self
    {
        return self::anthropic($config, $model);
    }

    public function chat(): ChatBuilder
    {
        return new ChatBuilder($this);
    }

    public function send(ChatBuilder $chatBuilder): LlmResponse
    {
        return $this->driver->sendRequest($chatBuilder);
    }
}