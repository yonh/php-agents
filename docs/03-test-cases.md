# PHP Agent Library - 测试用例（当前范围）

> Provider 仅 openai、zai；暂不覆盖 MCP/多模态/持久化/性能。

## 1. Agent 配置
- TC-AGENT-001（P0，单测）最小配置：provider=openai，api_key、model 必填，base_url 可选。断言实例创建成功、默认 maxIterations=10。
- TC-AGENT-002（P0）必填字段缺失：缺 provider/api_key/model 抛 ConfigException。
- TC-AGENT-003（P0）无效 provider：传 invalid 抛 ConfigException，消息包含 supported providers。
- TC-AGENT-004（P1）环境变量：设置 PHPAGENT_LLM_*，Agent::create() 无参时加载；显式配置优先级高于 env。

## 2. 对话与流式
- TC-CHAT-001（P0，集成/Mock）简单对话：Mock LLM 响应，chat 返回 Response，content/finishReason/usage/role 正确。
- TC-CHAT-002（P0，集成/Mock）多轮对话：Session 历史累积，含 user/assistant 消息，context 记忆。
- TC-CHAT-003（P1，集成/Mock）流式响应：Mock SSE 数据行，回调收到完整 chunks，支持 [DONE] 结束。
- TC-CHAT-004（P0）网络错误：Mock curl error 或超时，抛 NetworkException。
- TC-CHAT-005（P0）API 错误：HTTP ≠200 抛 ApiException，401/429 校验 code，429 包含 retry_after。
- TC-CHAT-006（P1）JSON Mode：response_format=json_object，返回可解析 JSON。

## 3. Provider 行为
- TC-PROV-OPENAI-001（P0）默认 base_url=https://api.openai.com/v1；Authorization: Bearer；chat/stream 成功解析。
- TC-PROV-ZAI-001（P0）默认 model=glm-4.6v，base_url=https://open.bigmodel.cn/api/paas/v4；Authorization: Bearer；chat/stream 复用 OpenAiProvider。
- TC-PROV-VISION-001（P1）supportsVision：openai gpt-4o/gpt-4o-mini/gpt-4-vision-preview 返回 true；zai 模型名含 4.6v/vision 返回 true。

## 4. 工具系统
- TC-TOOL-001（P0，单测）闭包注册：registerTool 后 hasTool/getTool/getSchema 正确。
- TC-TOOL-002（P0，单测）参数校验失败：缺必填/类型错误/约束不满足抛 ValidationException。
- TC-TOOL-003（P0，单测）执行错误：handler 抛异常，捕获并通过 ToolExecutionException（或错误内容）写入 tool 消息。
- TC-TOOL-004（P0，集成/Mock）自动工具调用：LLM 返回 tool_calls，Agent 调用工具并将结果写入消息历史，最终响应包含工具结果。

## 5. ReAct 循环
- TC-REACT-001（P0）单步工具：1 次 tool_calls + 1 次最终响应，iterations=2。
- TC-REACT-002（P0）多步推理：多次 tool_calls，顺序与函数参数匹配，最终内容正确。
- TC-REACT-003（P0）最大迭代：max_iterations=5，超过抛 MaxIterationsException 或提前终止，metrics 记录 iterations。
- TC-REACT-004（P1）提前终止：LLM 返回终止响应后不再调用工具/迭代。

## 6. 安全与错误
- TC-SEC-001（P0）日志/输出不包含 api_key（需脱敏或不记录）。
- TC-SEC-002（P1）流式解析容错：异常或非法 JSON 时安全退出并抛异常/停止。

## 7. 示例与环境变量
- TC-SAMPLE-001（P1）示例运行：examples/01-04 使用 env 变量；provider=openai 时默认 gpt-4；provider=zai 时指向智谱 base_url。

## 8. 未覆盖范围（待实现后补测）
- MCP 协议、会话持久化存储、性能/并发、多模态文件处理、额外 Provider（anthropic/azure/ollama）。
