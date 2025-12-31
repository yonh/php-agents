# PHP Agent Library - 技术架构设计

## 文档概述

本文档详细描述 PHP Agent Library 的技术架构设计，包括：
- 系统架构
- 核心组件设计
- 接口定义
- 数据流
- 技术选型
- 设计模式
- 扩展机制

---

## 1. 系统架构

### 1.1 整体架构图

```
┌─────────────────────────────────────────────────────────────┐
│                      Application Layer                       │
│  (User's PHP Application using php-agent library)           │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                      Agent Core Layer                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │    Agent     │  │   Session    │  │   Config     │      │
│  │   Manager    │  │   Manager    │  │   Manager    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└────────────────────────┬────────────────────────────────────┘
                         │
         ┌───────────────┼───────────────┐
         ▼               ▼               ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│   LLM       │  │   Tool      │  │    MCP      │
│  Provider   │  │  Registry   │  │   Client    │
│   Layer     │  │   Layer     │  │   Layer     │
└─────────────┘  └─────────────┘  └─────────────┘
         │               │               │
         ▼               ▼               ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  OpenAI     │  │  Built-in   │  │   Stdio     │
│  Anthropic  │  │   Tools     │  │    HTTP     │
│   Azure     │  │  Custom     │  │   Server    │
│   Ollama    │  │   Tools     │  │             │
└─────────────┘  └─────────────┘  └─────────────┘
         │               │               │
         └───────────────┼───────────────┘
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                   Infrastructure Layer                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │  Logger  │  │ Telemetry│  │  Cache   │  │ Security │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 分层架构

#### 1.2.1 应用层 (Application Layer)
- **职责**: 用户应用代码
- **依赖**: Agent Core Layer
- **示例**: Web 应用、CLI 工具、API 服务

#### 1.2.2 核心层 (Agent Core Layer)
- **职责**: Agent 核心逻辑、会话管理、配置管理
- **组件**:
  - `Agent`: 主入口，协调各组件
  - `SessionManager`: 会话生命周期管理
  - `ConfigManager`: 配置加载和验证

#### 1.2.3 服务层 (Service Layer)
- **职责**: 提供具体功能服务
- **组件**:
  - `LlmProvider`: LLM API 调用
  - `ToolRegistry`: 工具注册和调用
  - `McpClient`: MCP 协议客户端

#### 1.2.4 基础设施层 (Infrastructure Layer)
- **职责**: 通用基础设施
- **组件**:
  - `Logger`: 日志记录
  - `Telemetry`: 性能监控
  - `Cache`: 缓存管理
  - `Security`: 安全控制

---

## 2. 核心组件设计

### 2.1 Agent 组件

**职责**: 
- Agent 实例管理
- 协调 LLM、Tool、MCP 组件
- 执行 ReAct 循环

**类图**:
```php
namespace PhpAgent;

class Agent
{
    private AgentConfig $config;
    private LlmProviderInterface $llmProvider;
    private ToolRegistry $toolRegistry;
    private SessionManager $sessionManager;
    private McpClientManager $mcpClientManager;
    private LoggerInterface $logger;
    private TelemetryInterface $telemetry;
    
    public function __construct(AgentConfig $config);
    public static function create(array|AgentConfig $config): self;
    
    // 消息发送
    public function chat(string|array $message, array $options = []): Response;
    public function stream(string|array $message, callable $callback, array $options = []): void;
    
    // 会话管理
    public function createSession(?string $id = null): Session;
    public function session(string $id): Session;
    
    // 工具管理
    public function registerTool(string $name, string $description, array $parameters, callable $handler): void;
    public function registerToolsFromClass(object $instance): void;
    public function hasTool(string $name): bool;
    public function getTool(string $name): Tool;
    public function callTool(string $name, array $arguments): mixed;
    
    // MCP 管理
    public function connectMcpServer(string $name, string $command, array $options = []): void;
    public function hasMcpServer(string $name): bool;
    
    // 配置
    public function getConfig(): AgentConfig;
    public function setLogger(LoggerInterface $logger): void;
    public function setTelemetry(TelemetryInterface $telemetry): void;
    public function setSecurityPolicy(SecurityPolicy $policy): void;
    
    // 内部方法
    private function executeReActLoop(array $messages, array $options): Response;
    private function handleToolCalls(array $toolCalls): array;
}
```

**关键方法实现**:

```php
public function chat(string|array $message, array $options = []): Response
{
    // 1. 规范化消息格式
    $normalizedMessage = $this->normalizeMessage($message);
    
    // 2. 创建或获取会话
    $session = $this->sessionManager->getOrCreateSession();
    
    // 3. 添加用户消息到历史
    $session->addMessage(['role' => 'user', 'content' => $normalizedMessage]);
    
    // 4. 执行 ReAct 循环
    $response = $this->executeReActLoop($session->getMessages(), $options);
    
    // 5. 添加助手响应到历史
    $session->addMessage(['role' => 'assistant', 'content' => $response->content]);
    
    // 6. 保存会话
    $this->sessionManager->saveSession($session);
    
    return $response;
}

private function executeReActLoop(array $messages, array $options): Response
{
    $iteration = 0;
    $maxIterations = $this->config->maxIterations;
    
    while ($iteration < $maxIterations) {
        $iteration++;
        
        // 记录迭代开始
        $this->logger->info("ReAct iteration {$iteration} started");
        $this->telemetry->recordIteration($iteration);
        
        // 调用 LLM
        $llmResponse = $this->llmProvider->chat([
            'messages' => $messages,
            'tools' => $this->toolRegistry->getOpenAiTools(),
            'tool_choice' => $options['tool_choice'] ?? 'auto',
            ...$options
        ]);
        
        // 添加助手消息到历史
        $messages[] = $llmResponse->message;
        
        // 检查是否需要调用工具
        if (!empty($llmResponse->message['tool_calls'])) {
            // 处理工具调用
            $toolResults = $this->handleToolCalls($llmResponse->message['tool_calls']);
            
            // 添加工具结果到历史
            foreach ($toolResults as $result) {
                $messages[] = $result;
            }
            
            // 继续循环
            continue;
        }
        
        // 检查完成原因
        if ($llmResponse->finishReason === 'stop') {
            // 正常完成
            return new Response(
                content: $llmResponse->message['content'] ?? '',
                role: 'assistant',
                finishReason: 'stop',
                usage: $llmResponse->usage,
                iterations: $iteration
            );
        }
        
        // 其他完成原因（length, content_filter 等）
        return new Response(
            content: $llmResponse->message['content'] ?? '',
            role: 'assistant',
            finishReason: $llmResponse->finishReason,
            usage: $llmResponse->usage,
            iterations: $iteration
        );
    }
    
    // 达到最大迭代次数
    throw new MaxIterationsException("Reached maximum iterations: {$maxIterations}");
}

private function handleToolCalls(array $toolCalls): array
{
    $results = [];
    
    foreach ($toolCalls as $toolCall) {
        $toolName = $toolCall['function']['name'];
        $arguments = json_decode($toolCall['function']['arguments'], true);
        
        $this->logger->info("Calling tool: {$toolName}", ['arguments' => $arguments]);
        
        try {
            // 安全检查
            $this->securityPolicy->validateToolCall($toolName, $arguments);
            
            // 调用工具
            $startTime = microtime(true);
            $result = $this->toolRegistry->call($toolName, $arguments);
            $duration = (microtime(true) - $startTime) * 1000;
            
            // 记录指标
            $this->telemetry->recordToolCall($toolName, $duration, true);
            
            $this->logger->info("Tool call succeeded", [
                'tool' => $toolName,
                'duration_ms' => $duration
            ]);
            
            // 格式化结果
            $resultContent = is_string($result) ? $result : json_encode($result);
            
        } catch (\Exception $e) {
            $this->telemetry->recordToolCall($toolName, 0, false);
            $this->logger->error("Tool call failed", [
                'tool' => $toolName,
                'error' => $e->getMessage()
            ]);
            
            $resultContent = "Error: " . $e->getMessage();
        }
        
        $results[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content' => $resultContent
        ];
    }
    
    return $results;
}
```

---

### 2.2 LLM Provider 组件

**职责**:
- 抽象不同 LLM Provider 的 API 差异
- 统一的请求/响应格式
- 错误处理和重试

**接口定义**:
```php
namespace PhpAgent\Llm;

interface LlmProviderInterface
{
    public function chat(array $request): LlmResponse;
    public function stream(array $request, callable $callback): void;
    public function supportsVision(): bool;
    public function supportsFunctionCalling(): bool;
    public function supportsJsonMode(): bool;
}

class LlmResponse
{
    public function __construct(
        public array $message,
        public string $finishReason,
        public Usage $usage,
        public ?string $model = null
    ) {}
}

class Usage
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens
    ) {}
}
```

**实现示例 - OpenAI Provider**:
```php
namespace PhpAgent\Llm\Providers;

class OpenAiProvider implements LlmProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeout;
    private int $maxRetries;
    
    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1';
        $this->model = $config['model'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->maxRetries = $config['max_retries'] ?? 3;
    }
    
    public function chat(array $request): LlmResponse
    {
        $url = rtrim($this->baseUrl, '/') . '/chat/completions';
        
        $payload = [
            'model' => $request['model'] ?? $this->model,
            'messages' => $request['messages'],
            'temperature' => $request['temperature'] ?? 0.7,
            'max_tokens' => $request['max_tokens'] ?? null,
            'tools' => $request['tools'] ?? null,
            'tool_choice' => $request['tool_choice'] ?? null,
            'response_format' => $request['response_format'] ?? null,
        ];
        
        // 移除 null 值
        $payload = array_filter($payload, fn($v) => $v !== null);
        
        // 发送请求（带重试）
        $response = $this->sendRequestWithRetry($url, $payload);
        
        // 解析响应
        return $this->parseResponse($response);
    }
    
    private function sendRequestWithRetry(string $url, array $payload): array
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $this->maxRetries) {
            $attempt++;
            
            try {
                return $this->sendRequest($url, $payload);
            } catch (RateLimitException $e) {
                // 速率限制，等待后重试
                $lastException = $e;
                $waitTime = min(2 ** $attempt, 60); // 指数退避，最多60秒
                sleep($waitTime);
            } catch (NetworkException $e) {
                // 网络错误，重试
                $lastException = $e;
                sleep(1);
            } catch (ApiException $e) {
                // API 错误，不重试
                throw $e;
            }
        }
        
        throw new MaxRetriesException("Max retries reached", 0, $lastException);
    }
    
    private function sendRequest(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new NetworkException("cURL error: {$error}");
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode === 429) {
            throw new RateLimitException("Rate limit exceeded");
        }
        
        if ($httpCode !== 200) {
            $message = $data['error']['message'] ?? 'Unknown error';
            throw new ApiException($message, $httpCode);
        }
        
        return $data;
    }
    
    private function parseResponse(array $response): LlmResponse
    {
        $message = $response['choices'][0]['message'];
        $finishReason = $response['choices'][0]['finish_reason'];
        $usage = new Usage(
            promptTokens: $response['usage']['prompt_tokens'],
            completionTokens: $response['usage']['completion_tokens'],
            totalTokens: $response['usage']['total_tokens']
        );
        
        return new LlmResponse($message, $finishReason, $usage, $response['model']);
    }
    
    public function supportsVision(): bool
    {
        return in_array($this->model, ['gpt-4-vision-preview', 'gpt-4o', 'gpt-4o-mini']);
    }
    
    public function supportsFunctionCalling(): bool
    {
        return true;
    }
    
    public function supportsJsonMode(): bool
    {
        return true;
    }
}
```

---

### 2.3 Tool Registry 组件

**职责**:
- 工具注册和管理
- 参数验证
- 工具调用路由

**类设计**:
```php
namespace PhpAgent\Tool;

class ToolRegistry
{
    private array $tools = [];
    
    public function register(Tool $tool): void
    {
        if (isset($this->tools[$tool->getName()])) {
            throw new ToolAlreadyRegisteredException($tool->getName());
        }
        
        $this->tools[$tool->getName()] = $tool;
    }
    
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
    
    public function get(string $name): Tool
    {
        if (!$this->has($name)) {
            throw new ToolNotFoundException($name);
        }
        
        return $this->tools[$name];
    }
    
    public function call(string $name, array $arguments): mixed
    {
        $tool = $this->get($name);
        
        // 验证参数
        $this->validateArguments($tool, $arguments);
        
        // 调用处理器
        return $tool->call($arguments);
    }
    
    public function getOpenAiTools(): array
    {
        return array_map(
            fn(Tool $tool) => $tool->toOpenAiFormat(),
            array_values($this->tools)
        );
    }
    
    private function validateArguments(Tool $tool, array $arguments): void
    {
        $schema = $tool->getSchema();
        
        // 验证必填参数
        foreach ($schema['required'] ?? [] as $required) {
            if (!isset($arguments[$required])) {
                throw new ValidationException("Missing required parameter: {$required}");
            }
        }
        
        // 验证参数类型和约束
        foreach ($arguments as $key => $value) {
            if (!isset($schema['properties'][$key])) {
                throw new ValidationException("Unknown parameter: {$key}");
            }
            
            $paramSchema = $schema['properties'][$key];
            $this->validateValue($value, $paramSchema, $key);
        }
    }
    
    private function validateValue(mixed $value, array $schema, string $path): void
    {
        $type = $schema['type'];
        
        // 类型检查
        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    throw new ValidationException("{$path}: Expected string, got " . gettype($value));
                }
                if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
                    throw new ValidationException("{$path}: String too short");
                }
                if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
                    throw new ValidationException("{$path}: String too long");
                }
                if (isset($schema['enum']) && !in_array($value, $schema['enum'])) {
                    throw new ValidationException("{$path}: Invalid enum value");
                }
                break;
                
            case 'integer':
                if (!is_int($value)) {
                    throw new ValidationException("{$path}: Expected integer");
                }
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    throw new ValidationException("{$path}: Value too small");
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    throw new ValidationException("{$path}: Value too large");
                }
                break;
                
            case 'number':
                if (!is_numeric($value)) {
                    throw new ValidationException("{$path}: Expected number");
                }
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    throw new ValidationException("{$path}: Value too small");
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    throw new ValidationException("{$path}: Value too large");
                }
                break;
                
            case 'boolean':
                if (!is_bool($value)) {
                    throw new ValidationException("{$path}: Expected boolean");
                }
                break;
                
            case 'array':
                if (!is_array($value)) {
                    throw new ValidationException("{$path}: Expected array");
                }
                if (isset($schema['items'])) {
                    foreach ($value as $i => $item) {
                        $this->validateValue($item, $schema['items'], "{$path}[{$i}]");
                    }
                }
                break;
                
            case 'object':
                if (!is_array($value)) {
                    throw new ValidationException("{$path}: Expected object");
                }
                if (isset($schema['properties'])) {
                    foreach ($value as $key => $val) {
                        if (isset($schema['properties'][$key])) {
                            $this->validateValue($val, $schema['properties'][$key], "{$path}.{$key}");
                        }
                    }
                }
                break;
        }
    }
}

class Tool
{
    private string $name;
    private string $description;
    private array $schema;
    private \Closure $handler;
    
    public function __construct(
        string $name,
        string $description,
        array $parameters,
        callable $handler
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->schema = $this->buildSchema($parameters);
        $this->handler = \Closure::fromCallable($handler);
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getDescription(): string
    {
        return $this->description;
    }
    
    public function getSchema(): array
    {
        return $this->schema;
    }
    
    public function call(array $arguments): mixed
    {
        try {
            return ($this->handler)($arguments);
        } catch (\Exception $e) {
            throw new ToolExecutionException(
                "Tool '{$this->name}' execution failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    public function toOpenAiFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->schema
            ]
        ];
    }
    
    private function buildSchema(array $parameters): array
    {
        $properties = [];
        $required = [];
        
        foreach ($parameters as $param) {
            $properties[$param->name] = $param->toSchema();
            if ($param->required) {
                $required[] = $param->name;
            }
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required
        ];
    }
}

class Parameter
{
    public function __construct(
        public string $name,
        public string $type,
        public string $description = '',
        public bool $required = false,
        public mixed $default = null,
        public ?array $enum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?float $minimum = null,
        public ?float $maximum = null,
        public ?self $items = null
    ) {}
    
    public static function string(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'string', $description, $required);
    }
    
    public static function integer(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'integer', $description, $required);
    }
    
    public static function number(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'number', $description, $required);
    }
    
    public static function boolean(string $name, string $description = '', bool $required = false): self
    {
        return new self($name, 'boolean', $description, $required);
    }
    
    public static function array(string $name, ?self $items = null, string $description = '', bool $required = false): self
    {
        return new self($name, 'array', $description, $required, items: $items);
    }
    
    public function min(float $value): self
    {
        $this->minimum = $value;
        return $this;
    }
    
    public function max(float $value): self
    {
        $this->maximum = $value;
        return $this;
    }
    
    public function minLength(int $value): self
    {
        $this->minLength = $value;
        return $this;
    }
    
    public function maxLength(int $value): self
    {
        $this->maxLength = $value;
        return $this;
    }
    
    public function toSchema(): array
    {
        $schema = [
            'type' => $this->type,
            'description' => $this->description
        ];
        
        if ($this->enum !== null) {
            $schema['enum'] = $this->enum;
        }
        
        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }
        
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }
        
        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }
        
        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }
        
        if ($this->items !== null) {
            $schema['items'] = $this->items->toSchema();
        }
        
        return $schema;
    }
}
```

---

### 2.4 MCP Client 组件

**职责**:
- MCP 协议实现
- 与 MCP Server 通信
- 工具自动注册

**类设计**:
```php
namespace PhpAgent\Mcp;

class McpClient
{
    private string $serverName;
    private TransportInterface $transport;
    private int $requestId = 0;
    private array $tools = [];
    
    public function __construct(string $serverName, TransportInterface $transport)
    {
        $this->serverName = $serverName;
        $this->transport = $transport;
    }
    
    public function initialize(array $capabilities = []): array
    {
        $response = $this->sendRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => $capabilities,
            'clientInfo' => [
                'name' => 'php-agent',
                'version' => '1.0.0'
            ]
        ]);
        
        // 发送 initialized 通知
        $this->sendNotification('notifications/initialized');
        
        return $response;
    }
    
    public function listTools(): array
    {
        $response = $this->sendRequest('tools/list');
        $this->tools = $response['tools'] ?? [];
        return $this->tools;
    }
    
    public function callTool(string $name, array $arguments): mixed
    {
        $response = $this->sendRequest('tools/call', [
            'name' => $name,
            'arguments' => $arguments
        ]);
        
        // 解析 content
        $content = $response['content'] ?? [];
        $result = '';
        
        foreach ($content as $item) {
            if ($item['type'] === 'text') {
                $result .= $item['text'];
            }
        }
        
        return $result;
    }
    
    public function getTools(): array
    {
        return $this->tools;
    }
    
    private function sendRequest(string $method, array $params = []): array
    {
        $id = $this->requestId++;
        
        $request = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params
        ];
        
        $this->transport->send(json_encode($request));
        
        $response = $this->transport->receive();
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new McpException(
                $data['error']['message'] ?? 'Unknown error',
                $data['error']['code'] ?? -1
            );
        }
        
        return $data['result'] ?? [];
    }
    
    private function sendNotification(string $method, array $params = []): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ];
        
        $this->transport->send(json_encode($notification));
    }
}

interface TransportInterface
{
    public function send(string $message): void;
    public function receive(): string;
    public function close(): void;
}

class StdioTransport implements TransportInterface
{
    private $process;
    private array $pipes;
    
    public function __construct(string $command)
    {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];
        
        $this->process = proc_open($command, $descriptors, $this->pipes);
        
        if (!is_resource($this->process)) {
            throw new McpException("Failed to start MCP server: {$command}");
        }
    }
    
    public function send(string $message): void
    {
        fwrite($this->pipes[0], $message . "\n");
        fflush($this->pipes[0]);
    }
    
    public function receive(): string
    {
        return fgets($this->pipes[1]);
    }
    
    public function close(): void
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
    }
}

class HttpTransport implements TransportInterface
{
    private string $url;
    private int $timeout;
    
    public function __construct(string $url, int $timeout = 30)
    {
        $this->url = $url;
        $this->timeout = $timeout;
    }
    
    public function send(string $message): void
    {
        // HTTP 传输中，send 和 receive 合并为一次请求
        $this->lastMessage = $message;
    }
    
    public function receive(): string
    {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->lastMessage,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $this->timeout
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new McpException("HTTP error: {$error}");
        }
        
        return $response;
    }
    
    public function close(): void
    {
        // HTTP 无需关闭
    }
}
```

---

### 2.5 Session Manager 组件

**职责**:
- 会话生命周期管理
- 会话持久化
- 历史消息管理

**类设计**:
```php
namespace PhpAgent\Session;

class SessionManager
{
    private StorageInterface $storage;
    private array $sessions = [];
    
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }
    
    public function create(?string $id = null): Session
    {
        $id = $id ?? $this->generateId();
        
        $session = new Session($id);
        $this->sessions[$id] = $session;
        
        return $session;
    }
    
    public function get(string $id): Session
    {
        // 先从内存查找
        if (isset($this->sessions[$id])) {
            return $this->sessions[$id];
        }
        
        // 从存储加载
        $data = $this->storage->load($id);
        
        if ($data === null) {
            // 会话不存在，创建新会话
            return $this->create($id);
        }
        
        $session = Session::fromArray($data);
        $this->sessions[$id] = $session;
        
        return $session;
    }
    
    public function save(Session $session): void
    {
        $this->storage->save($session->getId(), $session->toArray());
    }
    
    public function delete(string $id): void
    {
        unset($this->sessions[$id]);
        $this->storage->delete($id);
    }
    
    public function pruneOldSessions(int $days): int
    {
        return $this->storage->pruneOld($days);
    }
    
    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}

class Session
{
    private string $id;
    private array $messages = [];
    private array $metadata = [];
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    
    public function __construct(string $id)
    {
        $this->id = $id;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function addMessage(array $message): void
    {
        $this->messages[] = $message;
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    public function getMessages(): array
    {
        return $this->messages;
    }
    
    public function clear(): void
    {
        $this->messages = [];
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
    
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'messages' => $this->messages,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c')
        ];
    }
    
    public static function fromArray(array $data): self
    {
        $session = new self($data['id']);
        $session->messages = $data['messages'] ?? [];
        $session->metadata = $data['metadata'] ?? [];
        $session->createdAt = new \DateTimeImmutable($data['created_at']);
        $session->updatedAt = new \DateTimeImmutable($data['updated_at']);
        
        return $session;
    }
}

interface StorageInterface
{
    public function save(string $id, array $data): void;
    public function load(string $id): ?array;
    public function delete(string $id): void;
    public function pruneOld(int $days): int;
}

class FileStorage implements StorageInterface
{
    private string $directory;
    private int $ttl;
    
    public function __construct(string $directory, int $ttl = 86400)
    {
        $this->directory = $directory;
        $this->ttl = $ttl;
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
    
    public function save(string $id, array $data): void
    {
        $path = $this->getPath($id);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public function load(string $id): ?array
    {
        $path = $this->getPath($id);
        
        if (!file_exists($path)) {
            return null;
        }
        
        // 检查是否过期
        if ($this->ttl > 0 && time() - filemtime($path) > $this->ttl) {
            $this->delete($id);
            return null;
        }
        
        $content = file_get_contents($path);
        return json_decode($content, true);
    }
    
    public function delete(string $id): void
    {
        $path = $this->getPath($id);
        if (file_exists($path)) {
            unlink($path);
        }
    }
    
    public function pruneOld(int $days): int
    {
        $count = 0;
        $cutoff = time() - ($days * 86400);
        
        foreach (glob($this->directory . '/*.json') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    private function getPath(string $id): string
    {
        return $this->directory . '/' . $id . '.json';
    }
}
```

---

## 3. 数据流设计

### 3.1 简单对话流程

```
User Application
    │
    ├─> Agent::chat("Hello")
    │       │
    │       ├─> SessionManager::getOrCreateSession()
    │       │
    │       ├─> Session::addMessage(['role' => 'user', 'content' => 'Hello'])
    │       │
    │       ├─> executeReActLoop()
    │       │       │
    │       │       ├─> LlmProvider::chat([messages, tools])
    │       │       │       │
    │       │       │       └─> OpenAI API
    │       │       │               │
    │       │       │               └─> Response: "Hi there!"
    │       │       │
    │       │       └─> Return Response
    │       │
    │       ├─> Session::addMessage(['role' => 'assistant', 'content' => 'Hi there!'])
    │       │
    │       └─> SessionManager::save(session)
    │
    └─> Return Response to User
```

### 3.2 工具调用流程

```
User Application
    │
    ├─> Agent::chat("What time is it?")
    │       │
    │       ├─> executeReActLoop()
    │       │       │
    │       │       ├─> [Iteration 1] LlmProvider::chat()
    │       │       │       │
    │       │       │       └─> Response: tool_calls=[{name: 'get_time', args: {}}]
    │       │       │
    │       │       ├─> handleToolCalls()
    │       │       │       │
    │       │       │       ├─> SecurityPolicy::validateToolCall('get_time', {})
    │       │       │       │
    │       │       │       ├─> ToolRegistry::call('get_time', {})
    │       │       │       │       │
    │       │       │       │       └─> Tool Handler Execution
    │       │       │       │               │
    │       │       │       │               └─> Return "2024-01-01 12:00:00"
    │       │       │       │
    │       │       │       └─> Return [{role: 'tool', content: '2024-01-01 12:00:00'}]
    │       │       │
    │       │       ├─> [Iteration 2] LlmProvider::chat()
    │       │       │       │
    │       │       │       └─> Response: "The current time is 2024-01-01 12:00:00"
    │       │       │
    │       │       └─> Return Response
    │       │
    │       └─> Save Session
    │
    └─> Return Response to User
```

---

## 4. 技术选型

### 4.1 核心技术栈

| 组件 | 技术选型 | 理由 |
|------|---------|------|
| PHP 版本 | PHP 8.5+ | 利用最新特性（Attributes, Union Types, Named Arguments） |
| HTTP Client | cURL | 内置，性能好，广泛支持 |
| JSON 处理 | ext-json | 内置，性能最优 |
| 日志 | PSR-3 | 标准接口，兼容性好 |
| 缓存 | PSR-6/PSR-16 | 标准接口 |
| 测试框架 | PHPUnit | 事实标准 |
| 静态分析 | PHPStan Level 8 | 最严格的类型检查 |
| 代码风格 | PSR-12 | PHP 标准 |

### 4.2 依赖管理

**Composer 依赖**:
```json
{
    "require": {
        "php": "^8.5",
        "ext-json": "*",
        "ext-curl": "*",
        "psr/log": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^1.10",
        "friendsofphp/php-cs-fixer": "^3.0"
    },
    "suggest": {
        "ext-redis": "For Redis cache support",
        "ext-memcached": "For Memcached cache support",
        "monolog/monolog": "For advanced logging"
    }
}
```

---

## 5. 设计模式

### 5.1 使用的设计模式

#### 5.1.1 Factory Pattern (工厂模式)
- **用途**: 创建 LLM Provider 实例
- **实现**:
```php
class LlmProviderFactory
{
    public static function create(string $provider, array $config): LlmProviderInterface
    {
        return match($provider) {
            'openai' => new OpenAiProvider($config),
            'anthropic' => new AnthropicProvider($config),
            'azure' => new AzureProvider($config),
            'ollama' => new OllamaProvider($config),
            default => throw new InvalidArgumentException("Unknown provider: {$provider}")
        };
    }
}
```

#### 5.1.2 Strategy Pattern (策略模式)
- **用途**: 不同的存储策略（文件、数据库、Redis）
- **实现**: `StorageInterface` 及其实现类

#### 5.1.3 Observer Pattern (观察者模式)
- **用途**: 事件系统
- **实现**:
```php
class EventDispatcher
{
    private array $listeners = [];
    
    public function on(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }
    
    public function dispatch(string $event, mixed $data): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }
}
```

#### 5.1.4 Decorator Pattern (装饰器模式)
- **用途**: 为 LLM Provider 添加缓存、重试等功能
- **实现**:
```php
class CachedLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private LlmProviderInterface $provider,
        private CacheInterface $cache
    ) {}
    
    public function chat(array $request): LlmResponse
    {
        $key = $this->getCacheKey($request);
        
        if ($cached = $this->cache->get($key)) {
            return $cached;
        }
        
        $response = $this->provider->chat($request);
        $this->cache->set($key, $response);
        
        return $response;
    }
}
```

#### 5.1.5 Builder Pattern (建造者模式)
- **用途**: 构建复杂的 Agent 配置
- **实现**:
```php
class AgentBuilder
{
    private array $config = [];
    
    public function withLlm(string $provider, string $apiKey, string $model): self
    {
        $this->config['llm'] = compact('provider', 'apiKey', 'model');
        return $this;
    }
    
    public function withMaxIterations(int $max): self
    {
        $this->config['max_iterations'] = $max;
        return $this;
    }
    
    public function withSystemPrompt(string $prompt): self
    {
        $this->config['system_prompt'] = $prompt;
        return $this;
    }
    
    public function build(): Agent
    {
        return Agent::create($this->config);
    }
}

// 使用
$agent = (new AgentBuilder())
    ->withLlm('openai', 'sk-xxx', 'gpt-4')
    ->withMaxIterations(20)
    ->withSystemPrompt('You are a helpful assistant')
    ->build();
```

---

## 6. 扩展机制

### 6.1 Extension 系统

**接口定义**:
```php
namespace PhpAgent\Extension;

interface ExtensionInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function register(Agent $agent): void;
    public function boot(): void;
}

abstract class AbstractExtension implements ExtensionInterface
{
    protected Agent $agent;
    
    public function register(Agent $agent): void
    {
        $this->agent = $agent;
    }
    
    public function boot(): void
    {
        // 默认空实现
    }
}
```

**示例 - Database Extension**:
```php
namespace PhpAgent\Extension\Database;

class DatabaseExtension extends AbstractExtension
{
    private \PDO $pdo;
    
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function getName(): string
    {
        return 'database';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function boot(): void
    {
        // 注册工具
        $this->agent->registerTool(
            'db_query',
            'Execute SQL query',
            [Parameter::string('sql', required: true)],
            fn($args) => $this->executeQuery($args['sql'])
        );
        
        $this->agent->registerTool(
            'db_schema',
            'Get database schema',
            [],
            fn() => $this->getSchema()
        );
    }
    
    private function executeQuery(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function getSchema(): array
    {
        // 返回数据库结构
    }
}

// 使用
$agent = Agent::create([...]);
$agent->use(new DatabaseExtension($pdo));
```

---

## 7. 性能优化

### 7.1 优化策略

#### 7.1.1 连接池
```php
class ConnectionPool
{
    private array $connections = [];
    private int $maxConnections = 10;
    
    public function getConnection(string $key, callable $factory): mixed
    {
        if (!isset($this->connections[$key])) {
            if (count($this->connections) >= $this->maxConnections) {
                // 移除最旧的连接
                array_shift($this->connections);
            }
            
            $this->connections[$key] = $factory();
        }
        
        return $this->connections[$key];
    }
}
```

#### 7.1.2 响应缓存
```php
class ResponseCache
{
    private CacheInterface $cache;
    private int $ttl = 3600;
    
    public function get(array $request): ?LlmResponse
    {
        $key = $this->generateKey($request);
        return $this->cache->get($key);
    }
    
    public function set(array $request, LlmResponse $response): void
    {
        $key = $this->generateKey($request);
        $this->cache->set($key, $response, $this->ttl);
    }
    
    private function generateKey(array $request): string
    {
        // 只缓存确定性请求（temperature = 0）
        if (($request['temperature'] ?? 0.7) > 0) {
            return '';
        }
        
        return 'llm:' . md5(json_encode($request));
    }
}
```

#### 7.1.3 并行工具调用
```php
private function handleToolCallsParallel(array $toolCalls): array
{
    // 分析依赖关系
    $groups = $this->groupByDependency($toolCalls);
    
    $results = [];
    
    foreach ($groups as $group) {
        // 并行执行同一组的工具
        $promises = [];
        foreach ($group as $toolCall) {
            $promises[] = $this->callToolAsync($toolCall);
        }
        
        // 等待所有工具完成
        $groupResults = Promise::all($promises)->wait();
        $results = array_merge($results, $groupResults);
    }
    
    return $results;
}
```

---

## 8. 安全设计

### 8.1 安全策略

```php
namespace PhpAgent\Security;

class SecurityPolicy
{
    private array $allowedTools = [];
    private array $deniedTools = [];
    private array $toolValidators = [];
    private RateLimiter $rateLimiter;
    private ContentFilter $contentFilter;
    
    public function validateToolCall(string $toolName, array $arguments): void
    {
        // 检查黑名单
        if (in_array($toolName, $this->deniedTools)) {
            throw new SecurityException("Tool '{$toolName}' is denied");
        }
        
        // 检查白名单
        if (!empty($this->allowedTools) && !in_array($toolName, $this->allowedTools)) {
            throw new SecurityException("Tool '{$toolName}' is not in allowed list");
        }
        
        // 自定义验证器
        if (isset($this->toolValidators[$toolName])) {
            $validator = $this->toolValidators[$toolName];
            if (!$validator($arguments)) {
                throw new SecurityException("Tool '{$toolName}' validation failed");
            }
        }
        
        // 速率限制
        if (!$this->rateLimiter->allow("tool:{$toolName}")) {
            throw new RateLimitException("Rate limit exceeded for tool '{$toolName}'");
        }
    }
    
    public function filterContent(string $content): string
    {
        return $this->contentFilter->filter($content);
    }
}

class RateLimiter
{
    private CacheInterface $cache;
    
    public function allow(string $key, int $maxAttempts = 100, int $window = 3600): bool
    {
        $attempts = (int)$this->cache->get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        $this->cache->set($key, $attempts + 1, $window);
        return true;
    }
}
```

---

## 9. 部署架构

### 9.1 单机部署

```
┌─────────────────────────────────────┐
│      PHP Application Server         │
│  ┌───────────────────────────────┐  │
│  │      php-agent Library        │  │
│  └───────────────────────────────┘  │
│  ┌───────────────────────────────┐  │
│  │    File-based Session Store   │  │
│  └───────────────────────────────┘  │
└─────────────────────────────────────┘
              │
              ▼
    ┌──────────────────┐
    │   OpenAI API     │
    └──────────────────┘
```

### 9.2 分布式部署

```
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  App Server │  │  App Server │  │  App Server │
│   + Agent   │  │   + Agent   │  │   + Agent   │
└──────┬──────┘  └──────┬──────┘  └──────┬──────┘
       │                │                │
       └────────────────┼────────────────┘
                        │
         ┌──────────────┴──────────────┐
         │                             │
    ┌────▼────┐                  ┌────▼────┐
    │  Redis  │                  │  MySQL  │
    │ (Cache) │                  │(Session)│
    └─────────┘                  └─────────┘
         │
         ▼
  ┌─────────────┐
  │  LLM API    │
  │  (OpenAI)   │
  └─────────────┘
```

---

## 10. 总结

本技术架构设计文档提供了 PHP Agent Library 的完整技术方案，包括：

1. **清晰的分层架构**: 应用层、核心层、服务层、基础设施层
2. **模块化设计**: 每个组件职责单一，易于测试和维护
3. **可扩展性**: 通过 Extension 系统支持功能扩展
4. **高性能**: 连接池、缓存、并行执行等优化
5. **安全性**: 完善的安全策略和权限控制
6. **标准化**: 遵循 PSR 标准，兼容性好

**下一步**: 基于本架构设计，开始实现各个组件（见 `05-implementation-guide.md`）
