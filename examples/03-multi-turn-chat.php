<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAgent\Agent;

$agent = Agent::create([
    'llm' => [
        // 与 LlmConfig 支持列表一致，示例默认使用 OpenAI
        'provider' => getenv('PROVIDER'),
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => getenv('OPENAI_MODEL')
    ],
    'system_prompt' => '你是一个友好的助手，记住用户告诉你的信息。'
]);

echo "=== 多轮对话示例 ===\n\n";

echo "User: 我叫张三\n";
$response = $agent->chat('我叫张三');
echo "AI: " . $response->content . "\n\n";

echo "User: 我今年25岁\n";
$response = $agent->chat('我今年25岁');
echo "AI: " . $response->content . "\n\n";

echo "User: 我的名字是什么？\n";
$response = $agent->chat('我的名字是什么？');
echo "AI: " . $response->content . "\n\n";

echo "User: 我多大了？\n";
$response = $agent->chat('我多大了？');
echo "AI: " . $response->content . "\n\n";

echo "=== 会话信息 ===\n";
echo "所有对话都在同一个会话中进行，Agent 会自动记住上下文。\n";
