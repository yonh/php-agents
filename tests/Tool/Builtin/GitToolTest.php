<?php

declare(strict_types=1);

namespace PhpAgent\Tests\Tool\Builtin;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PhpAgent\Tool\ToolRegistry;
use PhpAgent\Tool\Builtin\GitTool;

#[CoversClass(GitTool::class)]
class GitToolTest extends TestCase
{
    public function testGitDiffReturnsRawStdoutWithStub(): void
    {
        $stubOutput = "diff --git a/file.php b/file.php\nindex 000..111\n--- a/file.php\n+++ b/file.php\n";
        $capturedCmd = null;

        $commandRunnerStub = function (array $cmd) use ($stubOutput, &$capturedCmd) {
            $capturedCmd = $cmd;
            return ['stdout' => $stubOutput, 'error' => null];
        };

        $registry = new ToolRegistry();
        GitTool::register($registry, '/test/repo', $commandRunnerStub);

        $result = $registry->call('git_diff', []);

        $this->assertTrue($result['success']);
        $this->assertEquals($stubOutput, $result['stdout']);
        // 关键改进：验证生成的命令是否正确
        $this->assertEquals(['git', '-C', '/test/repo', 'diff'], $capturedCmd);
    }

    public function testGitDiffOverridesRepoPath(): void
    {
        $capturedCmd = null;
        $commandRunnerStub = function (array $cmd) use (&$capturedCmd) {
            $capturedCmd = $cmd;
            return ['stdout' => 'changes', 'error' => null];
        };

        $registry = new ToolRegistry();
        // 注册时使用默认路径 A
        GitTool::register($registry, '/path/A', $commandRunnerStub);

        // 调用时覆盖为路径 B
        $registry->call('git_diff', ['repo' => '/path/B']);

        $this->assertEquals(['git', '-C', '/path/B', 'diff'], $capturedCmd);
    }

    public function testGitDiffHandlesGitError(): void
    {
        $commandRunnerStub = function (array $cmd) {
            return ['stdout' => '', 'error' => 'fatal: not a git repository'];
        };

        $registry = new ToolRegistry();
        GitTool::register($registry, '/invalid/path', $commandRunnerStub);

        $result = $registry->call('git_diff', []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not a git repository', $result['error']);
    }

    public function testGitDiffValidatesRepoMustBeString(): void
    {
        $registry = new ToolRegistry();
        GitTool::register($registry, '/default/path');

        $this->expectException(\PhpAgent\Exception\ValidationException::class);
        $registry->call('git_diff', ['repo' => ['bad' => 'value']]);
    }
}
