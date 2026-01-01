<?php

declare(strict_types=1);

namespace PhpAgent\Tool\Builtin\Git;

use PhpAgent\Exception\ValidationException;
use PhpAgent\Tool\Builtin\Git\GitRunner;

abstract class AbstractGitTool
{
    protected function validateRepo(string|int|float|bool|array|null $repo): string
    {
        if (!is_string($repo)) {
            throw new ValidationException('repo must be a string');
        }
        return $repo;
    }

    protected function validatePositiveInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new ValidationException("{$name} must be a positive integer");
        }
        return $value;
    }

    protected function validateNonEmptyString(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new ValidationException("{$name} must be a non-empty string");
        }
        return $value;
    }

    /**
     * Normalize runner: if null use GitRunner::run.
     *
     * @return callable(array): array{stdout:string,error:?string}
     */
    protected function normalizeRunner(?callable $runner): callable
    {
        return $runner ?? GitRunner::run(...);
    }
}
