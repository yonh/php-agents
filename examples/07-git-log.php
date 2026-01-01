<?php

// 示例 07：自然语言驱动的提交日志分析
// 用法：php examples/07-git-log.php
// 依赖：需要设置 PROVIDER/OPENAI_API_KEY/OPENAI_MODEL 环境变量或替换为本地可用的 LLM 配置

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAgent\Agent;
use PhpAgent\AgentConfig;
use PhpAgent\Tool\Builtin\CommitLogTool;
use PhpAgent\Tool\Builtin\GitTool;

$repo = realpath(__DIR__ . '/..');
$limit = 5;
$output = __DIR__ . '/commits.json';

// 1) 创建 Agent（使用环境变量配置 LLM）
$config = [
    'llm' => [
        'provider' => getenv('PROVIDER') ?: 'openai',
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
    ],
    'system_prompt' => '你是代码分析助手，收到请求时请自动调用可用的工具获取提交日志，并用中文简要说明项目在做什么。',
];
$agent = Agent::create($config);

// 2) 注册 Git 相关工具（直接注册到 Agent 内部的 ToolRegistry）
$agent->registerToolInstance(CommitLogTool::createTool($repo));
$agent->registerToolInstance(GitTool::createTool($repo));

// 3) 用自然语言请求，期望 LLM 自动调用 commit_log_export 工具
$userRequest = <<<TEXT
请帮我查看最近 {$limit} 条提交，输出：
1) 项目的核心目标/功能是什么（结合提交主题和正文）
2) 最近的改动重点（列出要点）
3) 建议的后续方向（1-3 条）
请确保先调用可用的工具获取提交日志（输出路径：{$output}），再做总结。
TEXT;

$response = $agent->chat([
    ['role' => 'system', 'content' => '如果需要代码上下文，请调用工具，不要凭空臆测。'],
    ['role' => 'user', 'content' => $userRequest],
], [
    // 默认参数，允许 Agent 根据需要多轮调用工具
]);

echo "=== 助手回复 ===\n";
echo $response->content . "\n";

echo "\n(提交日志已写入：{$output} ，如需查看原始 JSON 可自行打开)\n";