<?php

declare(strict_types=1);

namespace PhpAgent\Tool;

class Parameter
{
    public function __construct(
        public string $name,
        public string $type,
        public string $description = '',
        public bool $required = false,
        public mixed $default = null,
        public ?array $enum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?float $minimum = null,
        public ?float $maximum = null,
        public ?self $items = null
    ) {}

    public static function string(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'string', $description, $required);
    }

    public static function integer(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'integer', $description, $required);
    }

    public static function number(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'number', $description, $required);
    }

    public static function boolean(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'boolean', $description, $required);
    }

    public static function array(string $name, ?self $items = null, string $description = '', bool $required = false): self
    {
        return new self($name, 'array', $description, $required, items: $items);
    }

    public function min(float $value): self
    {
        $this->minimum = $value;
        return $this;
    }

    public function max(float $value): self
    {
        $this->maximum = $value;
        return $this;
    }

    public function minLength(int $value): self
    {
        $this->minLength = $value;
        return $this;
    }

    public function maxLength(int $value): self
    {
        $this->maxLength = $value;
        return $this;
    }

    public function toSchema(): array
    {
        $schema = [
            'type' => $this->type,
            'description' => $this->description
        ];

        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }

        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        if ($this->items !== null) {
            $schema['items'] = $this->items->toSchema();
        }

        return $schema;
    }
}
