<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAgent\Agent;

$agent = Agent::create([
    'llm' => [
        'provider' => getenv('PROVIDER'),
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => getenv('OPENAI_MODEL')
    ]
]);

$response = $agent->chat('你好，请用一句话介绍你自己');

echo "AI: " . $response->content . "\n";
echo "Tokens: {$response->usage->totalTokens}\n";
echo "Iterations: {$response->iterations}\n";
