<?php

declare(strict_types=1);

namespace PhpAgent\Tool\Builtin\Git;

/**
 * Lightweight git command runner.
 */
final class GitRunner
{
    /**
     * @param array<int, string> $cmd
     * @return array{stdout:string,error:?string}
     */
    public static function run(array $cmd): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($cmd, $descriptorSpec, $pipes);
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
            'stdout' => $stdout !== false ? $stdout : '',
            'error' => $exitCode === 0 ? null : trim($stderr ?: 'git command failed'),
        ];
    }
}
