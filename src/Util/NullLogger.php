<?php

declare(strict_types=1);

namespace PhpAgent\Util;

use PhpAgent\Contract\LoggerInterface;

class NullLogger implements LoggerInterface
{
    public function info(string $message, array $context = []): void
    {
        // Do nothing
    }

    public function error(string $message, array $context = []): void
    {
        // Do nothing
    }

    public function debug(string $message, array $context = []): void
    {
        // Do nothing
    }

    public function warning(string $message, array $context = []): void
    {
        // Do nothing
    }
}
