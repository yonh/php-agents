<?php

declare(strict_types=1);

namespace PhpAgent\Tool;

use PhpAgent\Exception\ToolNotFoundException;
use PhpAgent\Exception\ToolAlreadyRegisteredException;
use PhpAgent\Exception\ValidationException;

class ToolRegistry
{
    private array $tools = [];

    public function register(Tool $tool): void
    {
        if (isset($this->tools[$tool->getName()])) {
            throw new ToolAlreadyRegisteredException($tool->getName());
        }

        $this->tools[$tool->getName()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): Tool
    {
        if (!$this->has($name)) {
            throw new ToolNotFoundException($name);
        }

        return $this->tools[$name];
    }

    public function call(string $name, array $arguments): mixed
    {
        $tool = $this->get($name);

        $this->validateArguments($tool, $arguments);

        return $tool->call($arguments);
    }

    public function getOpenAiTools(): array
    {
        return array_map(
            fn(Tool $tool) => $tool->toOpenAiFormat(),
            array_values($this->tools)
        );
    }

    private function validateArguments(Tool $tool, array $arguments): void
    {
        $schema = $tool->getSchema();

        foreach ($schema['required'] ?? [] as $required) {
            if (!isset($arguments[$required])) {
                throw new ValidationException("Missing required parameter: {$required}");
            }
        }

        foreach ($arguments as $key => $value) {
            if (!isset($schema['properties'][$key])) {
                throw new ValidationException("Unknown parameter: {$key}");
            }

            $paramSchema = $schema['properties'][$key];
            $this->validateValue($value, $paramSchema, $key);
        }
    }

    private function validateValue(mixed $value, array $schema, string $path): void
    {
        $type = $schema['type'];

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    throw new ValidationException("{$path}: Expected string, got " . gettype($value));
                }
                if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
                    throw new ValidationException("{$path}: String too short");
                }
                if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
                    throw new ValidationException("{$path}: String too long");
                }
                if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
                    throw new ValidationException("{$path}: Invalid enum value");
                }
                break;

            case 'integer':
                if (!is_int($value)) {
                    throw new ValidationException("{$path}: Expected integer");
                }
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    throw new ValidationException("{$path}: Value too small");
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    throw new ValidationException("{$path}: Value too large");
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    throw new ValidationException("{$path}: Expected number");
                }
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    throw new ValidationException("{$path}: Value too small");
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    throw new ValidationException("{$path}: Value too large");
                }
                break;

            case 'boolean':
                if (!is_bool($value)) {
                    throw new ValidationException("{$path}: Expected boolean");
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    throw new ValidationException("{$path}: Expected array");
                }
                if (isset($schema['items'])) {
                    foreach ($value as $i => $item) {
                        $this->validateValue($item, $schema['items'], "{$path}[{$i}]");
                    }
                }
                break;

            case 'object':
                if (!is_array($value)) {
                    throw new ValidationException("{$path}: Expected object");
                }
                if (isset($schema['properties'])) {
                    foreach ($value as $key => $val) {
                        if (isset($schema['properties'][$key])) {
                            $this->validateValue($val, $schema['properties'][$key], "{$path}.{$key}");
                        }
                    }
                }
                break;
        }
    }
}
