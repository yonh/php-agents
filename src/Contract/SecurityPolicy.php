<?php

declare(strict_types=1);

namespace PhpAgent\Contract;

interface SecurityPolicy
{
    public function validateToolCall(string $toolName, array $arguments): void;
}
