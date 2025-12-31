# PHP Agent Library - 验收标准（当前范围）

> 当前仅支持 Provider：openai、zai（智谱）。其他 Provider 暂未启用。

## AC-1: Agent 创建与配置
- 必填字段：provider、api_key、model。
- provider 校验：仅 openai、zai；否则抛 ConfigException。
- timeout >= 1；api_key/model 非空。
- 支持从数组创建 LlmConfig / AgentConfig。

## AC-2: 对话与流式
- chat：返回 Response，包含 content、finishReason、usage(total/prompt/completion)、iterations。
- stream：SSE 模式，按 data: 行触发回调；支持 [DONE] 结束；网络/API 错误抛 NetworkException/ApiException。
- 支持 tool_choice、tools 透传（OpenAI function-calling 兼容）。

## AC-3: Provider 行为
- openai：base_url 默认为 https://api.openai.com/v1；Authorization: Bearer <api_key>。
- zai：默认 model=glm-4.6v，base_url=https://open.bigmodel.cn/api/paas/v4，使用 Bearer 头；直接复用 OpenAiProvider 能力（chat/stream）。
- supportsVision：OpenAI 支持 gpt-4o / gpt-4o-mini / gpt-4-vision-preview；zai 默认按模型名包含 4.6v/vision 判定。

## AC-4: 工具系统
- ToolRegistry 可注册/查询/调用工具；重复注册抛异常。
- 参数 Schema 转换为 OpenAI function 调用格式。
- 工具执行异常被捕获并返回到消息历史（role=tool）。

## AC-5: ReAct 循环
- 默认 max_iterations=10（AgentConfig）；当超过抛 MaxIterationsException 或提前返回 finishReason。
- LLM 返回 tool_calls 时自动调用工具并将结果写入消息历史；再次调用 LLM 直至结束或达迭代上限。

## AC-6: 安全与错误
- 网络错误抛 NetworkException；HTTP 非 200 抛 ApiException；429 抛 RateLimitException（包含 retry_after）。
- 配置不合法抛 ConfigException。
- 流式解析错误需安全退出，不泄露敏感信息（api_key 不写入日志）。

## AC-7: 示例与环境变量
- 示例使用 OPENAI_API_KEY / OPENAI_MODEL / OPENAI_API_BASE_URL；zai 使用同一键名但 provider=zai 且 base_url 指向智谱。
- .env.example 提供 OPENAI_API_KEY 占位。

## 不在当前范围
- Anthropic/Azure/Ollama 真实实现未启用。
- 多模态文件读取/编码、MCP 协议、会话持久化存储、性能监控文档待补充。
