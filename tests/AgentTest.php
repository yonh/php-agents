<?php

declare(strict_types=1);

namespace PhpAgent\Tests;

use PHPUnit\Framework\TestCase;
use PhpAgent\Agent;
use PhpAgent\AgentConfig;
use PhpAgent\Llm\LlmProviderInterface;
use PhpAgent\Llm\LlmProviderFactory;
use PhpAgent\Response;
use PhpAgent\Llm\Usage;

class AgentTest extends TestCase
{
    public function testChatWithMultipleMessages(): void
    {
        $config = AgentConfig::fromArray([
            'llm' => [
                'provider' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4'
            ]
        ]);

        $agent = Agent::create($config);
        
        // 使用反射获取私有的 llmProvider 并替换为 Mock
        $reflection = new \ReflectionClass($agent);
        $property = $reflection->getProperty('llmProvider');
        $property->setAccessible(true);
        
        $mockProvider = $this->createMock(LlmProviderInterface::class);
        $property->setValue($agent, $mockProvider);
        
        $messages = [
            ['role' => 'system', 'content' => 'System prompt'],
            ['role' => 'user', 'content' => 'User prompt']
        ];
        
        // 预期：Provider 应该收到 3 条消息（System prompt, User prompt, 加上 Agent 自动加的消息，如果有的话）
        // 但实际上现在的实现会将 $messages 整个塞进一个 content 里。
        $mockProvider->expects($this->once())
            ->method('chat')
            ->with($this->callback(function($args) {
                $sentMessages = $args['messages'];
                // 验证是否发生了错误的嵌套：content 不应该是数组（包含 role 的数组）
                foreach ($sentMessages as $msg) {
                    if (is_array($msg['content']) && isset($msg['content'][0]['role'])) {
                        return false; // 发生了错误的嵌套
                    }
                }
                return true;
            }))
            ->willReturn(new \PhpAgent\Llm\LlmResponse(
                message: ['role' => 'assistant', 'content' => 'Response'],
                usage: new Usage(10, 10, 20),
                finishReason: 'stop'
            ));
        
        $agent->chat($messages);
    }
}
