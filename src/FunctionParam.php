<?php

namespace Underwear\LlmWrapper;

class FunctionParam
{
    private string $name;
    private string $type;
    private ?string $description = null;
    private bool $required = false;
    private array $enum = [];
    private ?int $min = null;
    private ?int $max = null;
    private array $properties = [];
    private ?FunctionParam $items = null;

    private function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public static function string(string $name): self
    {
        return new self($name, 'string');
    }

    public static function int(string $name): self
    {
        return new self($name, 'integer');
    }

    public static function float(string $name): self
    {
        return new self($name, 'number');
    }

    public static function bool(string $name): self
    {
        return new self($name, 'boolean');
    }

    public static function object(string $name): self
    {
        return new self($name, 'object');
    }

    public static function arrayOf(FunctionParam $itemType): self
    {
        $param = new self($itemType->name . '_array', 'array');
        $param->items = $itemType;
        return $param;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function required(): self
    {
        $this->required = true;
        return $this;
    }

    public function enum(array $values): self
    {
        $this->enum = $values;
        return $this;
    }

    public function min(int $min): self
    {
        $this->min = $min;
        return $this;
    }

    public function max(int $max): self
    {
        $this->max = $max;
        return $this;
    }

    public function props(array $properties): self
    {
        $this->properties = $properties;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function toArray(): array
    {
        $schema = ['type' => $this->type];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if (!empty($this->enum)) {
            $schema['enum'] = $this->enum;
        }

        if ($this->min !== null) {
            $schema['minimum'] = $this->min;
        }

        if ($this->max !== null) {
            $schema['maximum'] = $this->max;
        }

        if ($this->type === 'object' && !empty($this->properties)) {
            $schema['properties'] = [];
            $schema['required'] = [];

            foreach ($this->properties as $prop) {
                $schema['properties'][$prop->getName()] = $prop->toArray();
                if ($prop->isRequired()) {
                    $schema['required'][] = $prop->getName();
                }
            }

            if (empty($schema['required'])) {
                unset($schema['required']);
            }
        }

        if ($this->type === 'array' && $this->items !== null) {
            $schema['items'] = $this->items->toArray();
        }

        return $schema;
    }
}