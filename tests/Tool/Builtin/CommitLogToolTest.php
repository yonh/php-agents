<?php

declare(strict_types=1);

namespace PhpAgent\Tests\Tool\Builtin;

use PHPUnit\Framework\TestCase;
use PhpAgent\Tool\ToolRegistry;
use PhpAgent\Tool\Builtin\CommitLogTool;

/**
 * TDD 套件：为“导出最近 N 条提交到 JSON 文件”的新工具定义契约。
 * 该工具尚未实现，测试通过后才算完成。
 */
class CommitLogToolTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/php-agent-commit-log-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testExportsCommitLogsToJsonWithStubbedGit(): void
    {
        $capturedCmd = null;
        $stdout = implode("\x1e", [
            'hash1' . "\x1f" . 'Alice' . "\x1f" . '2024-12-30T10:00:00+00:00' . "\x1f" . 'Initial commit' . "\x1f",
            'hash2' . "\x1f" . 'Bob' . "\x1f" . '2024-12-31T11:11:11+00:00' . "\x1f" . 'Add feature' . "\x1f",
            ''
        ]);

        $runner = function (array $cmd) use (&$capturedCmd, $stdout) {
            $capturedCmd = $cmd;
            return ['stdout' => $stdout, 'error' => null];
        };

        $registry = new ToolRegistry();
        CommitLogTool::register($registry, '/repo/path', $runner);

        $outputPath = $this->tmpDir . '/commits.json';
        $result = $registry->call('commit_log_export', [
            'limit' => 5,
            'output' => $outputPath,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($outputPath, $result['path']);
        $this->assertSame(2, $result['count']);

        $this->assertIsArray($capturedCmd);
        $this->assertSame(['git', '-C', '/repo/path', 'log', '-n', '5', '--pretty=format:%H%x1f%an%x1f%ad%x1f%s%x1f%b%x1e', '--date=iso-strict'], $capturedCmd);

        $this->assertFileExists($outputPath);
        $json = json_decode(file_get_contents($outputPath), true);
        $this->assertCount(2, $json);
        $this->assertSame([
            'hash' => 'hash1',
            'author' => 'Alice',
            'date' => '2024-12-30T10:00:00+00:00',
            'subject' => 'Initial commit',
            'body' => '',
        ], $json[0]);
    }

    public function testExportsCommitBodyAndSubject(): void
    {
        $capturedCmd = null;
        // 使用记录分隔符 \x1e，字段分隔 \x1f，包含多行正文
        $stdout =
            'h1' . "\x1f" . 'Alice' . "\x1f" . '2024-12-30T10:00:00+00:00' . "\x1f" . 'Subject A' . "\x1f" . "Line1\nLine2" . "\x1e";

        $runner = function (array $cmd) use (&$capturedCmd, $stdout) {
            $capturedCmd = $cmd;
            return ['stdout' => $stdout, 'error' => null];
        };

        $registry = new ToolRegistry();
        CommitLogTool::register($registry, '/repo/path', $runner);

        $outputPath = $this->tmpDir . '/commits-body.json';
        $result = $registry->call('commit_log_export', [
            'limit' => 1,
            'output' => $outputPath,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(['git', '-C', '/repo/path', 'log', '-n', '1', '--pretty=format:%H%x1f%an%x1f%ad%x1f%s%x1f%b%x1e', '--date=iso-strict'], $capturedCmd);

        $json = json_decode(file_get_contents($outputPath), true);
        $this->assertSame('Subject A', $json[0]['subject']);
        $this->assertSame("Line1\nLine2", $json[0]['body']);
    }

    public function testRejectsInvalidLimit(): void
    {
        $registry = new ToolRegistry();
        CommitLogTool::register($registry);

        $this->expectException(\PhpAgent\Exception\ValidationException::class);
        $registry->call('commit_log_export', [
            'limit' => 0,
            'output' => $this->tmpDir . '/out.json',
        ]);
    }

    public function testRejectsNonIntegerLimit(): void
    {
        $registry = new ToolRegistry();
        CommitLogTool::register($registry);

        $this->expectException(\PhpAgent\Exception\ValidationException::class);
        $registry->call('commit_log_export', [
            'limit' => 'abc',
            'output' => $this->tmpDir . '/out.json',
        ]);
    }

    public function testRejectsNonStringRepo(): void
    {
        $registry = new ToolRegistry();
        CommitLogTool::register($registry);

        $this->expectException(\PhpAgent\Exception\ValidationException::class);
        $registry->call('commit_log_export', [
            'repo' => ['not', 'string'],
            'limit' => 1,
            'output' => $this->tmpDir . '/out.json',
        ]);
    }

    public function testHandlesGitError(): void
    {
        $runner = fn (array $cmd) => ['stdout' => '', 'error' => 'fatal: not a git repository'];

        $registry = new ToolRegistry();
        CommitLogTool::register($registry, '/invalid', $runner);

        $result = $registry->call('commit_log_export', [
            'limit' => 3,
            'output' => $this->tmpDir . '/out.json',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not a git repository', $result['error']);
    }

    public function testHandlesWriteFailure(): void
    {
        $runner = fn (array $cmd) => ['stdout' => "h1\x1fAlice\x1f2024-12-30T10:00:00+00:00\x1fMsg", 'error' => null];

        $registry = new ToolRegistry();
        CommitLogTool::register($registry, null, $runner);

        // 指定一个显然不可写的路径，模拟写文件失败
        $result = $registry->call('commit_log_export', [
            'limit' => 1,
            'output' => '/root/deny/out.json',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('write', strtolower((string)$result['error']));
    }

    public function testFailsWhenOutputDirectoryMissing(): void
    {
        $runner = fn (array $cmd) => ['stdout' => "h1\x1fAlice\x1f2024-12-30T10:00:00+00:00\x1fMsg", 'error' => null];

        $registry = new ToolRegistry();
        CommitLogTool::register($registry, '/repo', $runner);

        $missingDir = $this->tmpDir . '/missing/sub';
        $outputPath = $missingDir . '/commits.json'; // 目录不存在

        $result = $registry->call('commit_log_export', [
            'limit' => 1,
            'output' => $outputPath,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('write', strtolower((string)$result['error']));
    }

    public function testPartialResultsWhenLessThanLimit(): void
    {
        $runner = fn (array $cmd) => ['stdout' => "only1\x1fAlice\x1f2024-12-30T10:00:00+00:00\x1fSolo", 'error' => null];

        $registry = new ToolRegistry();
        CommitLogTool::register($registry, '/repo', $runner);

        $outputPath = $this->tmpDir . '/few.json';
        $result = $registry->call('commit_log_export', [
            'limit' => 5,
            'output' => $outputPath,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['count']);
        $json = json_decode(file_get_contents($outputPath), true);
        $this->assertCount(1, $json);
    }
}
