<?php

declare(strict_types=1);

namespace PhpAgent\Exception;

class ToolAlreadyRegisteredException extends ToolException
{
    public function __construct(string $toolName)
    {
        parent::__construct("Tool already registered: {$toolName}");
    }
}
