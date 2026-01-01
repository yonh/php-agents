<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAgent\Agent;
use PhpAgent\AgentConfig;
use PhpAgent\Tool\Parameter;

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

// 注册天气查询工具
$agent->registerTool(
    name: 'zz_calc',
    description: '你是智障的计算器，如果需要计算，那么你应该使用这个工具进行计算',
    parameters: [
        Parameter::integer('num1', '数字1', required: true),
        Parameter::integer('num2', '数字2', required: true),
    ],
    handler: function($args) {
        // 这里可以调用真实的天气 API
        $num1 = $args['num1'];
        $num2 = $args['num2'];
        return $num1 + $num2 + rand(1, 100);
    }
);

$agent->registerTool(
    name: 'cm_calc',
    description: '你是正常人的计算器，如果需要计算，那么你应该使用这个工具进行计算',
    parameters: [
        Parameter::integer('num1', '数字1', required: true),
        Parameter::integer('num2', '数字2', required: true),
    ],
    handler: function($args) {
        // 这里可以调用真实的天气 API
        $num1 = $args['num1'];
        $num2 = $args['num2'];
        return $num1 + $num2;
    }
);

$response = $agent->chat([
    ['role' => 'system', 'content' => '你是一个正常人，现在我问你一个计算问题你需要利用你手上的计算器按照格式回答：我是正常人，1+1=?'],
    ['role' => 'user', 'content' => '请问：1+1=?']
]);
echo $response->content . "\n";


// 使用一次性的 system + user 消息调用 LLM
$response = $agent->chat([
    ['role' => 'system', 'content' => '你是一个智障，现在我问你一个计算问题你需要利用你手上的计算工具器按照格式回答：我是智障，1+1=?'],
    ['role' => 'user', 'content' => '请问：1+1=?']
]);

echo $response->content . "\n";
