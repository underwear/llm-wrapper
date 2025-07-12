<?php

namespace Underwear\LlmWrapper;

class FunctionCallMode
{
    const AUTO = 'auto';
    const NONE = 'none';

    public static function auto(): string
    {
        return self::AUTO;
    }

    public static function none(): string
    {
        return self::NONE;
    }

    public static function specific(string $functionName): array
    {
        return ['name' => $functionName];
    }
}