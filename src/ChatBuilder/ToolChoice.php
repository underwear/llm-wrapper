<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\ChatBuilder;

class ToolChoice
{
    const AUTO = 'auto';
    const NONE = 'none';
    const REQUIRED = 'required';

    public static function auto(): string
    {
        return self::AUTO;
    }

    public static function none(): string
    {
        return self::NONE;
    }

    public static function required(): string
    {
        return self::REQUIRED;
    }

    public static function specific(string $toolName): array
    {
        return ['name' => $toolName];
    }
}
