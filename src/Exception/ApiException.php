<?php

declare(strict_types=1);

namespace PhpAgent\Exception;

class ApiException extends AgentException
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?array $details = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
