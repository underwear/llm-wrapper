<?php

namespace Underwear\LlmWrapper\ChatBuilder;

use Underwear\LlmWrapper\LlmClient;
use Underwear\LlmWrapper\LlmResponse\LlmResponse;

class ChatBuilder
{
    private array $messages = [];
    private ?string $model = null;
    private ?float $temperature = null;
    private array $functions = [];
    private null|array|string $functionCallMode = null;

    public function __construct(
        private readonly LlmClient $llmClient,
    ) {
    }

    public function model(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function temperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function system(string $content): self
    {
        $this->messages[] = ['role' => 'system', 'content' => $content];
        return $this;
    }

    public function user(string $content): self
    {
        $this->messages[] = ['role' => 'user', 'content' => $content];
        return $this;
    }

    public function assistant(string $content): self
    {
        $this->messages[] = ['role' => 'assistant', 'content' => $content];
        return $this;
    }

    public function function(string $name, callable $callback): self
    {
        $builder = new FunctionBuilder($name);
        $callback($builder);
        $this->functions[] = $builder->toArray();
        return $this;
    }

    public function functionCall(null|string|array $mode): self
    {
        $this->functionCallMode = $mode;
        return $this;
    }

    public function toArray(): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->messages,
        ];

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        if (!empty($this->functions)) {
            $payload['functions'] = $this->functions;
        }

        if ($this->functionCallMode !== null) {
            $payload['function_call'] = $this->functionCallMode;
        }

        return $payload;
    }

    public function send(): LlmResponse
    {
        return $this->llmClient->send($this);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function getFunctionCallMode(): null|string|array
    {
        return $this->functionCallMode;
    }
}