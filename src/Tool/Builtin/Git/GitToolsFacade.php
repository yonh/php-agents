<?php

declare(strict_types=1);

namespace PhpAgent\Tool\Builtin\Git;

use PhpAgent\Tool\ToolRegistry;
use PhpAgent\Tool\Builtin\CommitLogTool;
use PhpAgent\Tool\Builtin\GitTool;

/**
 * Convenience facade to register multiple git-related tools at once.
 */
final class GitToolsFacade
{
    /**
     * Register all available git tools into the registry.
     *
     * @param ToolRegistry $registry
     * @param string|null $repoPath Default repo path
     * @param callable|null $runner Shared runner for git commands
     */
    public static function registerAll(
        ToolRegistry $registry,
        ?string $repoPath = null,
        ?callable $runner = null
    ): void {
        // commit log export
        CommitLogTool::register($registry, $repoPath, $runner);
        // git diff (legacy)
        GitTool::register($registry, $repoPath, $runner);
    }
}
