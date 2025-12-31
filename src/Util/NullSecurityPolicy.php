<?php

declare(strict_types=1);

namespace PhpAgent\Util;

use PhpAgent\Contract\SecurityPolicy;

class NullSecurityPolicy implements SecurityPolicy
{
    public function validateToolCall(string $toolName, array $arguments): void
    {
        // no-op
    }
}
