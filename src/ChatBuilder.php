<?php

namespace Underwear\LlmWrapper;

class ChatBuilder
{
    private array $messages = [];
    private ?string $model = null;
    private ?float $temperature = null;
    private array $functions = [];
    private ?string $functionCallMode = null;

    public static function make(): self
    {
        return new self();
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

    public function functionCall(string $mode): self
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
}