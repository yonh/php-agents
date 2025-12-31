<?php

declare(strict_types=1);

namespace PhpAgent\Exception;

class ToolNotFoundException extends ToolException
{
    public function __construct(string $toolName)
    {
        parent::__construct("Tool not found: {$toolName}");
    }
}
