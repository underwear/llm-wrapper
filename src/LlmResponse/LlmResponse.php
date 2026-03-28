<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\LlmResponse;

class LlmResponse
{
    /**
     * @param ToolCall[] $toolCalls Indexed array of tool calls
     */
    public function __construct(
        private string $content,
        private array $toolCalls,
        private Usage $usage,
        private string $model,
        private string $rawResponse,
        private string $stopReason = '',
    ) {}

    // ===== Content =====
    public function text(): string
    {
        return $this->content;
    }

    public function hasText(): bool
    {
        return !empty(trim($this->content));
    }

    // ===== Tool Calls =====
    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    public function called(string $toolName): bool
    {
        foreach ($this->toolCalls as $call) {
            if ($call->getName() === $toolName) {
                return true;
            }
        }
        return false;
    }

    public function tool(string $toolName): ?ToolCall
    {
        foreach ($this->toolCalls as $call) {
            if ($call->getName() === $toolName) {
                return $call;
            }
        }
        return null;
    }

    /**
     * @return ToolCall[]
     */
    public function tools(?string $toolName = null): array
    {
        if ($toolName === null) {
            return $this->toolCalls;
        }

        return array_values(array_filter(
            $this->toolCalls,
            fn(ToolCall $call) => $call->getName() === $toolName,
        ));
    }

    // ===== Stop Reason =====
    public function stopReason(): string
    {
        return $this->stopReason;
    }

    // ===== JSON Helper with dot notation =====
    public function json(?string $path = null): mixed
    {
        $data = [
            'content' => $this->content,
            'tools' => $this->getToolCallsAsArray(),
            'usage' => $this->usage->toArray(),
            'model' => $this->model,
            'stop_reason' => $this->stopReason,
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
    private function getToolCallsAsArray(): array
    {
        $result = [];
        foreach ($this->toolCalls as $call) {
            $result[] = $call->toArray();
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
