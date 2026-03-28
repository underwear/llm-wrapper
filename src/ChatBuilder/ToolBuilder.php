<?php

declare(strict_types=1);

namespace Underwear\LlmWrapper\ChatBuilder;

use InvalidArgumentException;

class ToolBuilder
{
    private string $name;
    private ?string $description = null;
    private array $parameters = [];

    public function __construct(string $name)
    {
        $this->validateToolName($name);
        $this->name = $name;
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function param(FunctionParam $param): self
    {
        $paramName = $param->getName();

        if (isset($this->parameters[$paramName])) {
            throw new InvalidArgumentException("Parameter '{$paramName}' already exists");
        }

        $this->parameters[$paramName] = $param;
        return $this;
    }

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

    public function hasParam(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    public function getParam(string $name): ?FunctionParam
    {
        return $this->parameters[$name] ?? null;
    }

    public function removeParam(string $name): self
    {
        unset($this->parameters[$name]);
        return $this;
    }

    public function getRequiredParams(): array
    {
        return array_filter($this->parameters, fn(FunctionParam $param) => $param->isRequired());
    }

    public function getOptionalParams(): array
    {
        return array_filter($this->parameters, fn(FunctionParam $param) => !$param->isRequired());
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->name)) {
            $errors[] = 'Tool name cannot be empty';
        }

        if (empty($this->parameters)) {
            $errors[] = 'Tool must have at least one parameter';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->validate());
    }

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

        if (empty($schema['parameters']['required'])) {
            unset($schema['parameters']['required']);
        }

        return $schema;
    }

    public function toArray(): array
    {
        return $this->toSchema();
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toSchema(), $flags);
    }

    public function cloneWithName(string $newName): self
    {
        $clone = new self($newName);
        $clone->description = $this->description;
        $clone->parameters = $this->parameters;

        return $clone;
    }

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
        return array_values($this->parameters);
    }

    public function getParametersAssoc(): array
    {
        return $this->parameters;
    }

    public function getParameterCount(): int
    {
        return count($this->parameters);
    }

    public function getParameterNames(): array
    {
        return array_keys($this->parameters);
    }

    private function validateToolName(string $name): void
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Tool name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Tool name must be a valid identifier');
        }

        if (strlen($name) > 64) {
            throw new InvalidArgumentException('Tool name cannot exceed 64 characters');
        }
    }

    public function __toString(): string
    {
        $paramCount = count($this->parameters);
        $requiredCount = count($this->getRequiredParams());

        return "ToolBuilder(name: {$this->name}, params: {$paramCount}, required: {$requiredCount})";
    }
}
