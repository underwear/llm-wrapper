<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\LlmResponse;

class ToolCall
{
    public function __construct(
        private readonly string $name,
        private readonly array $arguments
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    public function __get(string $key): mixed
    {
        return $this->arguments[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->arguments[$key]);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}
