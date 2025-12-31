<?php

declare(strict_types=1);

namespace PhpAgent\Tool\Builtin;

use PhpAgent\Tool\Tool;
use PhpAgent\Tool\ToolRegistry;

final class GitTool
{
    /**
     * Register a minimal git_diff tool.
     *
     * @param ToolRegistry $registry
     * @param string|null $repoPath Path to git repo root; defaults to getcwd().
     * @param callable|null $commandRunner Optional runner for git commands. Signature: fn(array $cmd): array{stdout: string, error: ?string}
     */
    public static function register(ToolRegistry $registry, ?string $repoPath = null, ?callable $commandRunner = null): void
    {
        $registry->register(self::createTool($repoPath, $commandRunner));
    }

    /**
     * Create a Tool instance for registration. This lets callers construct the Tool object
     * and register it via Agent::registerToolInstance() or ToolRegistry directly.
     */
    public static function createTool(?string $repoPath = null, ?callable $commandRunner = null): Tool
    {
        $repo = $repoPath ?? getcwd();
        $runner = $commandRunner ?? self::runCommand(...);

        return new Tool(
            'git_diff',
            // Original description kept here as a comment for history:
            // 'Run git diff for the given range and return raw stdout. Example param: range="HEAD~1..HEAD"',
            'Execute git diff in the repository and return raw stdout (no parsing). Note: this tool currently does not accept a range parameter.',
            [
                // new \PhpAgent\Tool\Parameter(
                //     name: 'range',
                //     type: 'string',
                //     description: 'Git diff range, e.g. HEAD~1..HEAD',
                //     required: false,
                // ),
                new \PhpAgent\Tool\Parameter(
                    name: 'repo',
                    type: 'string',
                    description: 'Optional path to git repository',
                    required: false,
                ),
            ],
            function (array $args) use ($repo, $runner) {
                $targetRepo = $args['repo'] ?? $repo;

                if (is_array($targetRepo)) {
                    throw new \PhpAgent\Exception\ValidationException('repo must be a string');
                }

                // 1. 获取状态概览（包含未跟踪文件 ??）
                $statusCmd = ['git', '-C', (string)$targetRepo, 'status', '--porcelain'];
                $status = $runner($statusCmd);

                // 2. 获取具体差异内容（已跟踪文件的改动）
                $diffCmd = ['git', '-C', (string)$targetRepo, 'diff', 'HEAD'];
                $diff = $runner($diffCmd);

                if ($status['error'] !== null) {
                    return [
                        'success' => false,
                        'error' => $status['error'],
                    ];
                }

                $output = "Git Status:\n" . ($status['stdout'] ?: "(No changes)\n") . "\n" .
                          "Git Diff Contents:\n" . ($diff['stdout'] ?: "(No diff content)\n");

                return [
                    'success' => true,
                    'stdout' => $output,
                ];
            }
        );
    }

    private static function runCommand(array $cmd): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return ['stdout' => '', 'error' => 'Failed to start git process'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'error' => $exitCode === 0 ? null : trim($stderr ?: 'git command failed'),
        ];
    }
}
