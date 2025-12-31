<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAgent\Agent;
use PhpAgent\Tool\Parameter;

$agent = Agent::create([
    'llm' => [
        'provider' => getenv('PROVIDER'),
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => getenv('OPENAI_MODEL')
    ],
    'max_iterations' => 10
]);

$agent->registerTool(
    name: 'search_database',
    description: '搜索数据库中的用户信息',
    parameters: [
        Parameter::string('user_id', '用户ID', required: true)
    ],
    handler: function($args) {
        $users = [
            '001' => ['name' => '张三', 'age' => 25, 'city' => '北京'],
            '002' => ['name' => '李四', 'age' => 30, 'city' => '上海'],
            '003' => ['name' => '王五', 'age' => 28, 'city' => '广州']
        ];
        
        $userId = $args['user_id'];
        if (isset($users[$userId])) {
            return json_encode($users[$userId], JSON_UNESCAPED_UNICODE);
        }
        
        return "用户不存在";
    }
);

$agent->registerTool(
    name: 'send_email',
    description: '发送邮件',
    parameters: [
        Parameter::string('to', '收件人', required: true),
        Parameter::string('subject', '主题', required: true),
        Parameter::string('body', '邮件内容', required: true)
    ],
    handler: function($args) {
        echo "\n[邮件已发送]\n";
        echo "收件人: {$args['to']}\n";
        echo "主题: {$args['subject']}\n";
        echo "内容: {$args['body']}\n\n";
        
        return "邮件发送成功";
    }
);

echo "=== ReAct 循环示例 ===\n\n";
echo "任务: 查询用户 001 的信息，如果年龄大于 20，给他发送一封生日祝福邮件\n\n";

$response = $agent->chat(
    '查询用户 001 的信息，如果年龄大于 20，给他发送一封生日祝福邮件，' .
    '邮件主题是"生日快乐"，内容包含他的名字和年龄'
);

echo "\n最终响应:\n";
echo "AI: " . $response->content . "\n";
echo "\n执行统计:\n";
echo "迭代次数: {$response->iterations}\n";
echo "总 Token 数: {$response->usage->totalTokens}\n";
