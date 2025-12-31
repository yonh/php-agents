<?php

declare(strict_types=1);

namespace PhpAgent\Llm;

class Usage
{
    public function __construct(
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens
    ) {}

    public function add(Usage $other): self
    {
        return new self(
            promptTokens: $this->promptTokens + $other->promptTokens,
            completionTokens: $this->completionTokens + $other->completionTokens,
            totalTokens: $this->totalTokens + $other->totalTokens
        );
    }
}
