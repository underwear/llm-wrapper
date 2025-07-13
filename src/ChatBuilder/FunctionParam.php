<?php

namespace Underwear\LlmWrapper\ChatBuilder;

class FunctionParam
{
    private string $name;
    private string $type;
    private ?string $description = null;
    private bool $required = false;
    private bool $nullable = false;
    private array $enum = [];
    private ?int $min = null;
    private ?int $max = null;
    private array $properties = [];
    private ?FunctionParam $arrayOf = null;
    private ?int $minItems = null;
    private ?int $maxItems = null;

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    // Базовые типы
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

    // Новый метод для создания массива
    public static function array(string $name): self
    {
        return new self($name, 'array');
    }

    // Основные методы конфигурации
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

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function enum(array $values): self
    {
        $this->enum = $values;
        return $this;
    }

    // Для строк и чисел
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

    // Методы для массивов
    public function arrayOf(FunctionParam $itemType): self
    {
        if ($this->type !== 'array') {
            throw new \InvalidArgumentException('arrayOf() can only be used with array type');
        }
        $this->arrayOf = $itemType;
        return $this;
    }

    public function minItems(int $minItems): self
    {
        if ($this->type !== 'array') {
            throw new \InvalidArgumentException('minItems() can only be used with array type');
        }
        $this->minItems = $minItems;
        return $this;
    }

    public function maxItems(int $maxItems): self
    {
        if ($this->type !== 'array') {
            throw new \InvalidArgumentException('maxItems() can only be used with array type');
        }
        $this->maxItems = $maxItems;
        return $this;
    }

    // Методы для объектов
    public function props(array $properties): self
    {
        if ($this->type !== 'object') {
            throw new \InvalidArgumentException('props() can only be used with object type');
        }
        foreach ($properties as $property) {
            $this->addProp($property);
        }
        return $this;
    }

    public function addProp(FunctionParam $property): self
    {
        if ($this->type !== 'object') {
            throw new \InvalidArgumentException('addProp() can only be used with object type');
        }
        $this->properties[$property->getName()] = $property;
        return $this;
    }

    // Удобные комбинации для часто используемых типов
    public static function stringArray(string $name): self
    {
        return self::array($name)->arrayOf(self::string('item'));
    }

    public static function intArray(string $name): self
    {
        return self::array($name)->arrayOf(self::int('item'));
    }

    public static function objectArray(string $name, array $objectProperties): self
    {
        $objectType = self::object('item')->props($objectProperties);
        return self::array($name)->arrayOf($objectType);
    }

    // Геттеры
    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getType(): string
    {
        return $this->type;
    }

    // Метод для генерации JSON Schema
    public function toSchema(): array
    {
        $schema = [
            'type' => $this->type,
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        if ($this->nullable) {
            $schema['type'] = [$this->type, 'null'];
        }

        if (!empty($this->enum)) {
            $schema['enum'] = $this->enum;
        }

        // Для строк и чисел
        if ($this->min !== null) {
            $schema[$this->type === 'string' ? 'minLength' : 'minimum'] = $this->min;
        }

        if ($this->max !== null) {
            $schema[$this->type === 'string' ? 'maxLength' : 'maximum'] = $this->max;
        }

        // Для массивов
        if ($this->type === 'array') {
            if ($this->arrayOf !== null) {
                $schema['items'] = $this->arrayOf->toSchema();
            }

            if ($this->minItems !== null) {
                $schema['minItems'] = $this->minItems;
            }

            if ($this->maxItems !== null) {
                $schema['maxItems'] = $this->maxItems;
            }
        }

        // Для объектов
        if ($this->type === 'object' && !empty($this->properties)) {
            $schema['properties'] = [];
            $required = [];

            foreach ($this->properties as $name => $property) {
                $schema['properties'][$name] = $property->toSchema();
                if ($property->isRequired()) {
                    $required[] = $name;
                }
            }

            if (!empty($required)) {
                $schema['required'] = $required;
            }
        }

        return $schema;
    }
}