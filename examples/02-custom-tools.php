<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAgent\Agent;
use PhpAgent\Tool\Parameter;

$agent = Agent::create([
    'llm' => [
        'provider' => getenv('PROVIDER'),
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => getenv('OPENAI_MODEL')
    ]
]);

$agent->registerTool(
    name: 'get_weather',
    description: '获取指定城市的天气信息',
    parameters: [
        Parameter::string('city', '城市名称', required: true)
    ],
    handler: function($args) {
        $city = $args['city'];
        $temperatures = [
            '北京' => 25,
            '上海' => 28,
            '广州' => 32,
            '深圳' => 30
        ];
        
        $temp = $temperatures[$city] ?? 20;
        return "{$city}今天晴，温度 {$temp}°C";
    }
);

$agent->registerTool(
    name: 'calculate',
    description: '执行数学计算',
    parameters: [
        Parameter::string('expression', '数学表达式，如 "2+3*4"', required: true)
    ],
    handler: function($args) {
        $expr = $args['expression'];
        $expr = preg_replace('/[^0-9+\-*\/().]/', '', $expr);
        
        try {
            $result = eval("return {$expr};");
            return "计算结果: {$result}";
        } catch (\Throwable $e) {
            return "计算错误: " . $e->getMessage();
        }
    }
);

echo "=== 示例 1: 查询天气 ===\n";
$response = $agent->chat('北京今天天气怎么样？');
echo "AI: " . $response->content . "\n\n";

echo "=== 示例 2: 数学计算 ===\n";
$response = $agent->chat('帮我计算 (10 + 20) * 3');
echo "AI: " . $response->content . "\n\n";

echo "=== 示例 3: 组合任务 ===\n";
$response = $agent->chat('查询上海的天气，如果温度高于25度，计算 30 - 温度值');
echo "AI: " . $response->content . "\n";
