<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\LlmResponse;

class LlmResponse
{
    public function __construct(
        private string $content,
        private array $functionCalls,
        private Usage $usage,
        private string $model,
        private string $rawResponse
    ) {}

    // ===== Основные методы =====
    public function text(): string
    {
        return $this->content;
    }

    public function hasText(): bool
    {
        return !empty(trim($this->content));
    }

    // ===== Function Calls =====
    public function hasFunctionCalls(): bool
    {
        return !empty($this->functionCalls);
    }

    public function called(string $functionName): bool
    {
        return isset($this->functionCalls[$functionName]);
    }

    public function function(string $functionName): ?FunctionCall
    {
        return $this->functionCalls[$functionName] ?? null;
    }

    public function functions(): array
    {
        return $this->functionCalls;
    }

    // ===== JSON Helper с dot notation =====
    public function json(string $path = null): mixed
    {
        $data = [
            'content' => $this->content,
            'functions' => $this->getFunctionCallsAsArray(),
            'usage' => $this->usage->toArray(),
            'model' => $this->model,
        ];

        if ($path === null) {
            return $data;
        }

        return $this->getValueByPath($data, $path);
    }

    // ===== Usage & Meta =====
    public function usage(): Usage
    {
        return $this->usage;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function raw(): string
    {
        return $this->rawResponse;
    }

    // ===== Private helpers =====
    private function getFunctionCallsAsArray(): array
    {
        $result = [];
        foreach ($this->functionCalls as $name => $call) {
            $result[$name] = [
                'name' => $call->getName(),
                'arguments' => $call->getArguments(),
            ];
        }
        return $result;
    }

    private function getValueByPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}