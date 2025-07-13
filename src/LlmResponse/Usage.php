<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\LlmResponse;

class Usage
{
    public function __construct(
        private readonly int $promptTokens,
        private readonly int $completionTokens,
        private readonly int $totalTokens,
    ) {
    }

    public function promptTokens(): int
    {
        return $this->promptTokens;
    }

    public function completionTokens(): int
    {
        return $this->completionTokens;
    }

    public function totalTokens(): int
    {
        return $this->totalTokens;
    }

    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    public function __toString(): string
    {
        return "Usage: {$this->totalTokens} tokens (prompt: {$this->promptTokens}, completion: {$this->completionTokens})";
    }
}