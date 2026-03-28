<?php

namespace Underwear\LlmWrapper\ChatBuilder;

use Underwear\LlmWrapper\LlmClient;
use Underwear\LlmWrapper\LlmResponse\LlmResponse;

class ChatBuilder
{
    private array $messages = [];
    private ?string $model = null;
    private ?float $temperature = null;
    private ?int $maxTokens = null;
    private array $tools = [];
    private null|array|string $toolChoice = null;

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

    public function maxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
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

    public function tool(string|ToolBuilder $nameOrBuilder, ?callable $callback = null): self
    {
        if ($nameOrBuilder instanceof ToolBuilder) {
            $this->tools[] = $nameOrBuilder->toSchema();
            return $this;
        }

        $builder = new ToolBuilder($nameOrBuilder);
        if ($callback !== null) {
            $callback($builder);
        }
        $this->tools[] = $builder->toSchema();
        return $this;
    }

    public function toolChoice(null|string|array $mode): self
    {
        $this->toolChoice = $mode;
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

        if ($this->maxTokens !== null) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        if (!empty($this->tools)) {
            $payload['tools'] = $this->tools;
        }

        if ($this->toolChoice !== null) {
            $payload['tool_choice'] = $this->toolChoice;
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

    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function getToolChoice(): null|string|array
    {
        return $this->toolChoice;
    }
}
