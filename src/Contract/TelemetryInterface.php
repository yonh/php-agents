<?php

declare(strict_types=1);

namespace PhpAgent\Contract;

interface TelemetryInterface
{
    public function recordIteration(int $iteration): void;

    public function recordToolCall(string $toolName, float $durationMs, bool $success): void;

    public function recordError(string $category, string $message, array $context = []): void;
}
