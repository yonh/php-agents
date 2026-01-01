<?php

declare(strict_types=1);

namespace PhpAgent\Tool;

use PhpAgent\Exception\ToolExecutionException;

class Tool
{
    private string $name;
    private string $description;
    private array $schema;
    private \Closure $handler;

    public function __construct(
        string $name,
        string $description,
        array $parameters,
        callable $handler
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->schema = $this->buildSchema($parameters);
        $this->handler = \Closure::fromCallable($handler);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function call(array $arguments): mixed
    {
        try {
            return ($this->handler)($arguments);
        } catch (\PhpAgent\Exception\ValidationException $e) {
            // 直接透传参数校验异常，便于调用方捕获
            throw $e;
        } catch (\Exception $e) {
            throw new ToolExecutionException(
                "Tool '{$this->name}' execution failed: " . $e->getMessage(),
                $this->name,
                $e
            );
        }
    }

    public function toOpenAiFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->schema
            ]
        ];
    }

    private function buildSchema(array $parameters): array
    {
        $properties = [];
        $required = [];

        foreach ($parameters as $param) {
            $properties[$param->name] = $param->toSchema();
            if ($param->required) {
                $required[] = $param->name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required
        ];
    }
}
