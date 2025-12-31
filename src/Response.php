<?php

declare(strict_types=1);

namespace PhpAgent;

use PhpAgent\Llm\Usage;

class Response
{
    public function __construct(
        public readonly string $content,
        public readonly string $role,
        public readonly string $finishReason,
        public readonly Usage $usage,
        public readonly int $iterations = 1,
        public readonly ?array $metadata = null
    ) {}
}
