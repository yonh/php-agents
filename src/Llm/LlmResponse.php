<?php

declare(strict_types=1);

namespace PhpAgent\Llm;

class LlmResponse
{
    public function __construct(
        public readonly array $message,
        public readonly string $finishReason,
        public readonly Usage $usage,
        public readonly ?string $model = null
    ) {}
}
