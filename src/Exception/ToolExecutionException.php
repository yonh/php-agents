<?php

declare(strict_types=1);

namespace PhpAgent\Exception;

class ToolExecutionException extends ToolException
{
    public function __construct(
        string $message,
        public readonly ?string $toolName = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
