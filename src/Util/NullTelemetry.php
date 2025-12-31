<?php

declare(strict_types=1);

namespace PhpAgent\Util;

use PhpAgent\Contract\TelemetryInterface;

class NullTelemetry implements TelemetryInterface
{
    public function recordIteration(int $iteration): void
    {
        // no-op
    }

    public function recordToolCall(string $toolName, float $durationMs, bool $success): void
    {
        // no-op
    }

    public function recordError(string $category, string $message, array $context = []): void
    {
        // no-op
    }
}
