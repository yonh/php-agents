<?php

declare(strict_types=1);

namespace PhpAgent\Tool\Builtin\Tests;

use PHPUnit\Framework\TestCase;
use PhpAgent\Tool\ToolRegistry;
use PhpAgent\Tool\Builtin\GitTool;

class GitToolTest extends TestCase
{
    public function testGitDiffReturnsRawStdoutWithStub(): void
    {
        $statusOutput = "M\tsrc/Agent.php\n";
        $diffOutput = "diff --git a/file.php b/file.php\nindex 000..111\n--- a/file.php\n+++ b/file.php\n";

        $captured = [];
        $commandRunnerStub = function (array $cmd) use ($statusOutput, $diffOutput, &$captured) {
            $captured[] = $cmd;
            // Determine which command is being run by checking the 3rd index
            if (isset($cmd[3]) && $cmd[3] === 'status') {
                return ['stdout' => $statusOutput, 'error' => null];
            }
            return ['stdout' => $diffOutput, 'error' => null];
        };

        $registry = new ToolRegistry();
        GitTool::register($registry, '/test/repo', $commandRunnerStub);

        $result = $registry->call('git_diff', []);

        $this->assertTrue($result['success']);

        $expectedOutput = "Git Status:\n" . ($statusOutput ?: "(No changes)\n") . "\n" .
                          "Git Diff Contents:\n" . ($diffOutput ?: "(No diff content)\n");

        $this->assertEquals($expectedOutput, $result['stdout']);

        // 验证两次调用的命令：status 和 diff
        $this->assertCount(2, $captured);
        $this->assertEquals(['git', '-C', '/test/repo', 'status', '--porcelain'], $captured[0]);
        $this->assertEquals(['git', '-C', '/test/repo', 'diff', 'HEAD'], $captured[1]);
    }

    public function testGitDiffOverridesRepoPath(): void
    {
        $captured = [];
        $commandRunnerStub = function (array $cmd) use (&$captured) {
            $captured[] = $cmd;
            return ['stdout' => 'changes', 'error' => null];
        };

        $registry = new ToolRegistry();
        // 注册时使用默认路径 A
        GitTool::register($registry, '/path/A', $commandRunnerStub);

        // 调用时覆盖为路径 B
        $registry->call('git_diff', ['repo' => '/path/B']);

        // 最后一条命令应该是 diff HEAD，且使用覆盖后的路径
        $this->assertEquals(['git', '-C', '/path/B', 'diff', 'HEAD'], $captured[1]);
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
