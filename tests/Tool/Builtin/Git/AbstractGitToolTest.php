<?php

declare(strict_types=1);

namespace PhpAgent\Tests\Tool\Builtin\Git;

use PhpAgent\Exception\ValidationException;
use PhpAgent\Tool\Builtin\Git\AbstractGitTool;
use PHPUnit\Framework\TestCase;

class AbstractGitToolTest extends TestCase
{
    private DummyTool $tool;

    protected function setUp(): void
    {
        $this->tool = new DummyTool();
    }

    public function testValidateRepoThrowsOnNonString(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool->validateRepoPublic(['array']);
    }

    public function testValidateRepoAcceptsString(): void
    {
        $this->assertSame('/repo', $this->tool->validateRepoPublic('/repo'));
    }

    public function testValidatePositiveInt(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool->validatePositiveIntPublic(0, 'limit');
    }

    public function testValidatePositiveIntOk(): void
    {
        $this->assertSame(5, $this->tool->validatePositiveIntPublic(5, 'limit'));
    }

    public function testValidateNonEmptyString(): void
    {
        $this->expectException(ValidationException::class);
        $this->tool->validateNonEmptyStringPublic('', 'output');
    }

    public function testValidateNonEmptyStringOk(): void
    {
        $this->assertSame('ok', $this->tool->validateNonEmptyStringPublic('ok', 'output'));
    }

    public function testNormalizeRunnerUsesDefault(): void
    {
        $runner = $this->tool->normalizeRunnerPublic(null);
        $this->assertIsCallable($runner);
    }
}

/**
 * @internal helper for testing protected methods
 */
class DummyTool extends AbstractGitTool
{
    public function validateRepoPublic($repo): string
    {
        return $this->validateRepo($repo);
    }

    public function validatePositiveIntPublic($value, string $name): int
    {
        return $this->validatePositiveInt($value, $name);
    }

    public function validateNonEmptyStringPublic($value, string $name): string
    {
        return $this->validateNonEmptyString($value, $name);
    }

    public function normalizeRunnerPublic(?callable $runner): callable
    {
        return $this->normalizeRunner($runner);
    }
}
