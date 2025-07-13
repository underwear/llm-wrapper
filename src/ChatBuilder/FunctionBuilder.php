<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\ChatBuilder;

use InvalidArgumentException;

class FunctionBuilder
{
    private string $name;
    private ?string $description = null;
    private array $parameters = []; // Ассоциативный массив: name => FunctionParam

    public function __construct(string $name)
    {
        $this->validateFunctionName($name);
        $this->name = $name;
    }

    /**
     * Статический конструктор для более читаемого API
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Устанавливает описание функции
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Добавляет параметр к функции
     */
    public function param(FunctionParam $param): self
    {
        $paramName = $param->getName();

        if (isset($this->parameters[$paramName])) {
            throw new InvalidArgumentException("Parameter '{$paramName}' already exists");
        }

        $this->parameters[$paramName] = $param;
        return $this;
    }

    /**
     * Добавляет массив параметров
     */
    public function params(array $params): self
    {
        foreach ($params as $param) {
            if (!$param instanceof FunctionParam) {
                throw new InvalidArgumentException('All items in params array must be FunctionParam instances');
            }
            $this->param($param);
        }
        return $this;
    }

    /**
     * Удобные методы для добавления параметров разных типов
     */
    public function stringParam(string $name): FunctionParam
    {
        $param = FunctionParam::string($name);
        $this->param($param);
        return $param;
    }

    public function intParam(string $name): FunctionParam
    {
        $param = FunctionParam::int($name);
        $this->param($param);
        return $param;
    }

    public function floatParam(string $name): FunctionParam
    {
        $param = FunctionParam::float($name);
        $this->param($param);
        return $param;
    }

    public function boolParam(string $name): FunctionParam
    {
        $param = FunctionParam::bool($name);
        $this->param($param);
        return $param;
    }

    public function objectParam(string $name): FunctionParam
    {
        $param = FunctionParam::object($name);
        $this->param($param);
        return $param;
    }

    public function arrayParam(string $name): FunctionParam
    {
        $param = FunctionParam::array($name);
        $this->param($param);
        return $param;
    }

    /**
     * Проверяет существование параметра
     */
    public function hasParam(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    /**
     * Получает параметр по имени
     */
    public function getParam(string $name): ?FunctionParam
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * Удаляет параметр
     */
    public function removeParam(string $name): self
    {
        unset($this->parameters[$name]);
        return $this;
    }

    /**
     * Получает все обязательные параметры
     */
    public function getRequiredParams(): array
    {
        return array_filter($this->parameters, fn(FunctionParam $param) => $param->isRequired());
    }

    /**
     * Получает все необязательные параметры
     */
    public function getOptionalParams(): array
    {
        return array_filter($this->parameters, fn(FunctionParam $param) => !$param->isRequired());
    }

    /**
     * Validates the function definition
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->name)) {
            $errors[] = 'Function name cannot be empty';
        }

        if (empty($this->parameters)) {
            $errors[] = 'Function must have at least one parameter';
        }

        return $errors;
    }

    /**
     * Проверяет валидность функции
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * Генерирует JSON Schema для функции
     */
    public function toSchema(): array
    {
        $schema = [
            'name' => $this->name,
            'parameters' => [
                'type' => 'object',
                'properties' => [],
                'required' => []
            ]
        ];

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        foreach ($this->parameters as $name => $param) {
            $schema['parameters']['properties'][$name] = $param->toSchema();

            if ($param->isRequired()) {
                $schema['parameters']['required'][] = $name;
            }
        }

        // Убираем пустой required array
        if (empty($schema['parameters']['required'])) {
            unset($schema['parameters']['required']);
        }

        return $schema;
    }

    /**
     * Генерирует массив для передачи в LLM API
     */
    public function toArray(): array
    {
        return $this->toSchema();
    }

    /**
     * Конвертирует в JSON строку
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toSchema(), $flags);
    }

    /**
     * Clones the function builder with a new name
     */
    public function cloneWithName(string $newName): self
    {
        $clone = new self($newName);
        $clone->description = $this->description;
        $clone->parameters = $this->parameters;

        return $clone;
    }

    // Геттеры
    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getParameters(): array
    {
        return array_values($this->parameters); // Возвращаем только значения для обратной совместимости
    }

    /**
     * Получает параметры как ассоциативный массив
     */
    public function getParametersAssoc(): array
    {
        return $this->parameters;
    }

    /**
     * Получает количество параметров
     */
    public function getParameterCount(): int
    {
        return count($this->parameters);
    }

    /**
     * Получает имена параметров
     */
    public function getParameterNames(): array
    {
        return array_keys($this->parameters);
    }

    /**
     * Validates function name according to common naming conventions
     */
    private function validateFunctionName(string $name): void
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Function name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Function name must be a valid identifier');
        }

        if (strlen($name) > 64) {
            throw new InvalidArgumentException('Function name cannot exceed 64 characters');
        }
    }

    /**
     * Magic method for debugging
     */
    public function __toString(): string
    {
        $paramCount = count($this->parameters);
        $requiredCount = count($this->getRequiredParams());

        return "FunctionBuilder(name: {$this->name}, params: {$paramCount}, required: {$requiredCount})";
    }
}