# PHP Agent Library - 实现指南（当前范围）

> 仅支持 Provider：openai、zai（智谱）。其他 Provider 未启用。

## 1. 代码结构（当前）
```
src/
  Agent.php
  AgentConfig.php
  Response.php
  Llm/
    LlmConfig.php        # provider/api_key/model/base_url/timeout 校验，支持 openai/zai
    LlmProviderFactory.php  # openai -> OpenAiProvider；zai -> ZaiProvider(继承 OpenAi)
    Providers/
      OpenAiProvider.php # chat + stream(SSE)，支持 tools/tool_choice/response_format
      ZaiProvider.php    # 预设 base_url=https://open.bigmodel.cn/api/paas/v4, model=glm-4.6v
  Tool/...
  Session/...
examples/
  01-04*.php            # 使用 env 变量，provider 默认 openai，可改 zai
docs/
  02-acceptance-criteria.md
  03-test-cases.md
```

## 2. 配置与环境
- `.env.example`：OPENAI_API_KEY 占位；可新增 ZAI 相关环境变量（自定义 base_url/model）。
- Agent 创建：
```php
$agent = Agent::create([
  'llm' => [
    'provider' => 'zai', // or 'openai'
    'api_key' => getenv('OPENAI_API_KEY'),
    'model' => 'glm-4.6v',
    'base_url' => 'https://open.bigmodel.cn/api/paas/v4', // 可覆盖
  ],
  'max_iterations' => 10,
  'system_prompt' => 'You are a helpful assistant.'
]);
```

## 3. Provider 行为
- OpenAI：base_url 默认 https://api.openai.com/v1，Authorization: Bearer。
- ZAI：继承 OpenAiProvider，预设 base_url/model；支持 chat、stream(SSE)。
- supportsVision：OpenAI 模型 gpt-4o/gpt-4o-mini/gpt-4-vision-preview 返回 true；ZAI 以模型名包含 4.6v/vision 判定。

## 4. 对话与流式
- chat 返回 Response：content / finishReason / usage(prompt, completion, total) / iterations。
- stream：SSE 解析 data: 行，支持 [DONE] 结束；回调收到解码后的增量 JSON。
- 错误处理：NetworkException（curl）、ApiException（非 200）、RateLimitException（429，含 retry_after）。
  - RateLimit（429）：Provider 内部按 retry_after 或指数退避重试，超出重试后上抛 RateLimitException；Agent 捕获后返回 content='Rate limit exceeded...'，finishReason='rate_limit'，metadata 携带 retry_after 便于上层展示/等待。

## 5. 工具与 ReAct
- ToolRegistry：registerTool/hasTool/getTool/callTool；重复注册抛异常。
- 参数 Schema 转换为 OpenAI function 调用格式；执行异常写入 tool 消息。
- ReAct：LLM tool_calls -> 执行工具 -> 再次 LLM，直到 finishReason 或 max_iterations（默认 10）。

## 6. 测试范围（见 03-test-cases）
- P0：配置校验、chat、错误处理、工具注册/执行、ReAct 单步/多步、Provider openai/zai 基础。
- P1：stream、JSON mode、vision 判定、提前终止等。
- 未覆盖：MCP、多模态文件、持久化存储、性能。

## 7. 示例运行
```bash
php examples/01-hello-world.php
php examples/02-custom-tools.php
php examples/03-multi-turn-chat.php
php examples/04-react-loop.php
```
可将 provider 改为 zai 并设置 base_url/model。

## 8. 后续计划
- 若启用更多 Provider（anthropic/azure/ollama），需：新增 Provider 实现 + Factory 注册 + LlmConfig 校验 + 文档与测试。
- 补充：MCP 支持、会话持久化存储、多模态处理、性能监控与日志脱敏策略。
