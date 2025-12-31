<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAgent\Agent;
use PhpAgent\AgentConfig;

/**
 * Example 05: Using Built-in ChangeNotesTools
 * 
 * This example demonstrates how to use the built-in git diff summary 
 * and commit notes generation tools.
 */

// 1. Setup Agent with environment variables
$config = [
    'llm' => [
        'provider' => getenv('PROVIDER') ?: 'openai',
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-4o',
    ],
    'system_prompt' => '你是一个代码助手，负责分析代码变更并生成规范的提交说明。'
];

$agent = Agent::create($config);
$repoRoot = realpath(__DIR__ . '/..');

//$agent 之前已创建，下面演示如何动态注册一个工具以及工具调用
$tool = \PhpAgent\Tool\Builtin\GitTool::createTool($repoRoot);
$agent->registerToolInstance($tool);

// 直接工具调用(为了灵活一般不直接调用)
// $gitResult = $agent->callTool('git_diff', []);
// if ($gitResult['success']) {
//     echo "git diff output:\n";
//     echo "----------------------------------------\n";
//     echo $gitResult['stdout'] . "\n";
//     echo "----------------------------------------\n";
// } else {
//     echo "git diff error: " . $gitResult['error'] . "\n";
// }

// 通过自然语言
// echo "User:请分析当前代码的改动，为我说明一下改动了什么。\n";
// $response = $agent->chat('请分析当前代码的改动，为我说明一下改动了什么。');
// echo "AI: " . $response->content . "\n";
// echo "User: 请以当前代码变动为基础，生成一份Git 提交说明。\n";

// 加载示例模板（把角色/提交模板放在 examples/templates/roles.php 中以保持示例文件精简）
$templates = require __DIR__ . '/templates/roles.php';

// 一次性 system prompt：把角色模板与提交模板组合成一个系统消息注入 LLM 的上下文
$role = $templates['role_template'] . "\n使用以下模板生成提交说明：\n" . $templates['commit_template'];

// 使用一次性的 system + user 消息调用 LLM
$response = $agent->chat([
    ['role' => 'system', 'content' => $role],
    ['role' => 'user', 'content' => '请基于当前仓库改动，生成符合上述模板的提交说明。']
]);

echo "\n=== 生成的受控提交说明 ===\n";
echo $response->content . "\n";
