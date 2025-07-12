<?php

namespace Underwear\LlmWrapper;

class FunctionBuilder
{
    private string $name;
    private ?string $description = null;
    private array $parameters = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function param(FunctionParam $param): self
    {
        $this->parameters[] = $param;
        return $this;
    }

    public function toArray(): array
    {
        $function = [
            'name' => $this->name,
            'parameters' => [
                'type' => 'object',
                'properties' => [],
                'required' => []
            ]
        ];

        if ($this->description !== null) {
            $function['description'] = $this->description;
        }

        foreach ($this->parameters as $param) {
            $paramName = $param->getName();
            $function['parameters']['properties'][$paramName] = $param->toArray();

            if ($param->isRequired()) {
                $function['parameters']['required'][] = $paramName;
            }
        }

        if (empty($function['parameters']['required'])) {
            unset($function['parameters']['required']);
        }

        return $function;
    }
}
