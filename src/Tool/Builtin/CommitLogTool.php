<?php

declare(strict_types=1);

namespace PhpAgent\Tool\Builtin;

use PhpAgent\Tool\Parameter;
use PhpAgent\Tool\Tool;
use PhpAgent\Tool\ToolRegistry;
use PhpAgent\Tool\Builtin\Git\AbstractGitTool;

/**
 * Extract latest N git commits and export to a JSON file.
 */
final class CommitLogTool extends AbstractGitTool
{
    /**
     * Register the tool into a registry.
     *
     * @param ToolRegistry $registry
     * @param string|null $repoPath
     * @param callable|null $commandRunner fn(array $cmd): array{stdout: string, error: ?string}
     */
    public static function register(ToolRegistry $registry, ?string $repoPath = null, ?callable $commandRunner = null): void
    {
        $registry->register(self::createTool($repoPath, $commandRunner));
    }

    public static function createTool(?string $repoPath = null, ?callable $commandRunner = null): Tool
    {
        $repo = $repoPath ?? getcwd();
        $runner = (new self())->normalizeRunner($commandRunner);

        return new Tool(
            'commit_log_export',
            'Export latest git commits to a JSON file',
            [
                Parameter::integer('limit', 'Number of commits to export', required: true),
                Parameter::string('output', 'Output JSON file path', required: true),
                Parameter::string('repo', 'Optional git repository path', required: false),
            ],
            function (array $args) use ($repo, $runner) {
                $self = new self();

                $targetRepo = $self->validateRepo($args['repo'] ?? $repo);
                $limit = $self->validatePositiveInt($args['limit'] ?? null, 'limit');
                $output = $self->validateNonEmptyString($args['output'] ?? '', 'output');

                $cmd = [
                    'git',
                    '-C',
                    (string)$targetRepo,
                    'log',
                    '-n',
                    (string)$limit,
                    // record fields separated by \x1f, records separated by \x1e
                    '--pretty=format:%H%x1f%an%x1f%ad%x1f%s%x1f%b%x1e',
                    '--date=iso-strict',
                ];

                $result = $runner($cmd);
                if ($result['error'] !== null) {
                    return ['success' => false, 'error' => $result['error']];
                }

                $entries = [];
                $records = explode("\x1e", $result['stdout']);
                foreach ($records as $record) {
                    if ($record === null) {
                        continue;
                    }
                    $record = trim($record, "\r\n");
                    if ($record === '') {
                        continue;
                    }
                    $parts = explode("\x1f", $record);
                    if (count($parts) < 4) {
                        continue;
                    }
                    // parts: hash, author, date, subject, body (optional)
                    $entries[] = [
                        'hash' => $parts[0],
                        'author' => $parts[1],
                        'date' => $parts[2],
                        'subject' => $parts[3],
                        'body' => $parts[4] ?? '',
                    ];
                }

                $json = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($json === false) {
                    return ['success' => false, 'error' => 'Failed to encode JSON'];
                }

                $written = @file_put_contents($output, $json);
                if ($written === false) {
                    return ['success' => false, 'error' => 'Failed to write output file'];
                }

                return [
                    'success' => true,
                    'path' => $output,
                    'count' => count($entries),
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
