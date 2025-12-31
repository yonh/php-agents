# PHP Agent Library - 用户故事

## 项目概述

**目标**: 构建一个现代化的 PHP 8.5+ Agent 库，使第三方 PHP 项目能够轻松集成 LLM 能力，实现智能工具调用和 ReAct 推理循环。

**核心价值**: 让 PHP 开发者能够像使用普通库一样，为应用添加 AI Agent 能力，无需深入理解 MCP 协议或 LLM API 细节。

---

## 用户角色定义

### 1. PHP 应用开发者 (Primary User)
- **描述**: 使用 PHP 开发 Web 应用、CLI 工具或 API 服务的开发者
- **技能水平**: 熟悉 PHP 8.x、Composer、面向对象编程
- **目标**: 快速为应用添加 AI 能力，如智能客服、内容生成、数据分析等

### 2. MCP 工具提供者 (Tool Provider)
- **描述**: 开发和维护 MCP Server 的开发者
- **技能水平**: 了解 MCP 协议、JSON-RPC
- **目标**: 让自己的工具能被 PHP Agent 调用

### 3. 库维护者 (Library Maintainer)
- **描述**: 维护 php-agent 库的核心开发团队
- **技能水平**: 精通 PHP、设计模式、测试驱动开发
- **目标**: 提供稳定、高性能、易扩展的库

---

## Epic 1: 核心 Agent 功能

### US-1.1: 作为开发者，我希望能够快速创建一个 Agent 实例
**优先级**: P0 (Must Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要通过简单的配置创建一个 Agent 实例
以便快速开始使用 LLM 能力
```

**场景描述**:
```php
use PhpAgent\Agent;
use PhpAgent\Config\AgentConfig;

// 场景 1: 最简配置
$agent = Agent::create([
    'llm' => [
        'provider' => 'openai',
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => 'gpt-4'
    ]
]);

// 场景 2: 完整配置
$config = new AgentConfig(
    llm: new LlmConfig(
        provider: 'openai',
        apiKey: getenv('OPENAI_API_KEY'),
        model: 'gpt-4',
        baseUrl: 'https://api.openai.com/v1',
        timeout: 30
    ),
    maxIterations: 10,
    systemPrompt: 'You are a helpful assistant.'
);

$agent = new Agent($config);
```

**验收标准**:
- ✅ 支持数组配置和对象配置两种方式
- ✅ 必填参数: provider, api_key, model
- ✅ 可选参数有合理默认值
- ✅ 配置验证失败时抛出清晰的异常
- ✅ 支持环境变量自动加载

---

### US-1.2: 作为开发者，我希望能够发送消息并获得响应
**优先级**: P0 (Must Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要向 Agent 发送用户消息并获得 AI 响应
以便实现基本的对话功能
```

**场景描述**:
```php
// 场景 1: 简单问答
$response = $agent->chat('What is the capital of France?');
echo $response->content; // "The capital of France is Paris."

// 场景 2: 多轮对话
$session = $agent->createSession();
$session->send('My name is Alice');
$session->send('What is my name?'); // "Your name is Alice."

// 场景 3: 流式响应
$agent->stream('Write a story about a cat', function($chunk) {
    echo $chunk;
});
```

**验收标准**:
- ✅ 支持同步响应
- ✅ 支持流式响应
- ✅ 自动管理对话历史
- ✅ 返回结构化响应对象（包含 content, tokens, finish_reason 等）
- ✅ 错误处理友好（网络错误、API 错误、超时等）

---

### US-1.3: 作为开发者，我希望能够为 Agent 注册自定义工具
**优先级**: P0 (Must Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要注册自定义 PHP 函数作为 Agent 可调用的工具
以便 Agent 能够执行特定业务逻辑
```

**场景描述**:
```php
use PhpAgent\Tool\Tool;
use PhpAgent\Tool\Parameter;

// 场景 1: 使用闭包注册工具
$agent->registerTool(
    name: 'get_weather',
    description: 'Get current weather for a city',
    parameters: [
        Parameter::string('city', 'City name', required: true),
        Parameter::string('unit', 'Temperature unit', enum: ['celsius', 'fahrenheit'])
    ],
    handler: function(array $args): string {
        $city = $args['city'];
        $unit = $args['unit'] ?? 'celsius';
        // 调用天气 API
        return "Weather in {$city}: 20°{$unit}";
    }
);

// 场景 2: 使用类方法注册工具
class WeatherService {
    #[Tool(
        name: 'get_weather',
        description: 'Get current weather for a city'
    )]
    public function getWeather(
        #[Parameter(description: 'City name')] string $city,
        #[Parameter(enum: ['celsius', 'fahrenheit'])] string $unit = 'celsius'
    ): string {
        return "Weather in {$city}: 20°{$unit}";
    }
}

$agent->registerToolsFromClass(new WeatherService());

// 场景 3: 批量注册工具
$agent->registerTools([
    Tool::create('tool1', 'Description 1', [], fn() => 'result1'),
    Tool::create('tool2', 'Description 2', [], fn() => 'result2'),
]);
```

**验收标准**:
- ✅ 支持闭包、类方法、静态方法作为工具处理器
- ✅ 支持 PHP 8 Attributes 声明工具元数据
- ✅ 自动生成符合 OpenAI Function Calling 规范的 JSON Schema
- ✅ 参数类型自动推断（基于 PHP 类型提示）
- ✅ 工具执行错误能被捕获并返回给 LLM
- ✅ 支持异步工具（返回 Promise）

---

### US-1.4: 作为开发者，我希望 Agent 能够自动执行 ReAct 循环
**优先级**: P0 (Must Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要 Agent 自动进行"思考-行动-观察"循环
以便处理需要多步推理和工具调用的复杂任务
```

**场景描述**:
```php
// 用户请求: "查询北京天气，如果温度高于30度，发送高温预警邮件"

$agent->registerTool('get_weather', ...);
$agent->registerTool('send_email', ...);

$response = $agent->chat('查询北京天气，如果温度高于30度，发送高温预警邮件');

// Agent 自动执行:
// Step 1: 思考 -> 决定调用 get_weather('北京')
// Step 2: 行动 -> 执行工具，获得 "35°C"
// Step 3: 观察 -> 分析结果，温度 > 30
// Step 4: 思考 -> 决定调用 send_email(...)
// Step 5: 行动 -> 发送邮件
// Step 6: 完成 -> 返回最终响应
```

**验收标准**:
- ✅ 自动检测 LLM 返回的 tool_calls
- ✅ 按顺序执行多个工具调用
- ✅ 将工具结果反馈给 LLM 继续推理
- ✅ 支持最大迭代次数限制（防止死循环）
- ✅ 每步执行都有清晰的日志输出
- ✅ 支持中断和恢复执行

---

## Epic 2: MCP 协议集成

### US-2.1: 作为开发者，我希望能够连接到 MCP Server
**优先级**: P0 (Must Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要连接到标准 MCP Server 并使用其提供的工具
以便复用现有的 MCP 生态工具
```

**场景描述**:
```php
use PhpAgent\Mcp\McpClient;

// 场景 1: 连接到 Stdio MCP Server
$agent->connectMcpServer(
    name: 'time-server',
    command: 'php services/time-server.php'
);

// 场景 2: 连接到 HTTP MCP Server
$agent->connectMcpServer(
    name: 'weather-server',
    transport: 'http',
    url: 'http://localhost:8080/mcp'
);

// 场景 3: 手动管理 MCP Client
$mcpClient = new McpClient('php services/time-server.php');
$mcpClient->initialize();
$tools = $mcpClient->listTools();
$agent->registerMcpClient($mcpClient);
```

**验收标准**:
- ✅ 支持 Stdio 和 HTTP 两种传输方式
- ✅ 自动完成 MCP 握手（initialize -> initialized）
- ✅ 自动获取并注册 MCP Server 提供的工具
- ✅ 工具调用自动路由到正确的 MCP Server
- ✅ 连接失败时有清晰的错误提示
- ✅ 支持连接多个 MCP Server

---

### US-2.2: 作为开发者，我希望能够创建自己的 MCP Server
**优先级**: P1 (Should Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要将现有的 PHP 服务封装为 MCP Server
以便其他 Agent 能够调用我的服务
```

**场景描述**:
```php
use PhpAgent\Mcp\McpServer;

$server = new McpServer('my-service', '1.0.0');

$server->registerTool(
    name: 'calculate',
    description: 'Perform calculations',
    schema: [
        'type' => 'object',
        'properties' => [
            'expression' => ['type' => 'string']
        ],
        'required' => ['expression']
    ],
    handler: function($args) {
        return eval("return {$args['expression']};");
    }
);

// 启动 Stdio Server
$server->runStdio();

// 或启动 HTTP Server
$server->runHttp(port: 8080);
```

**验收标准**:
- ✅ 符合 MCP 2024-11-05 协议规范
- ✅ 支持 Stdio 和 HTTP 传输
- ✅ 自动处理 initialize/initialized 握手
- ✅ 支持 tools/list 和 tools/call
- ✅ 错误处理符合 JSON-RPC 规范
- ✅ 支持 Resources 和 Prompts（可选）

---

## Epic 3: 多模态支持

### US-3.1: 作为开发者，我希望 Agent 能够处理图像输入
**优先级**: P1 (Should Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要让 Agent 分析图像内容
以便实现图像识别、OCR、视觉问答等功能
```

**场景描述**:
```php
use PhpAgent\Message\ImageMessage;

// 场景 1: 本地图像文件
$response = $agent->chat([
    'text' => 'What is in this image?',
    'images' => ['/path/to/image.jpg']
]);

// 场景 2: URL 图像
$response = $agent->chat([
    'text' => 'Describe this screenshot',
    'images' => ['https://example.com/screenshot.png']
]);

// 场景 3: Base64 图像
$imageData = base64_encode(file_get_contents('image.jpg'));
$response = $agent->chat([
    'text' => 'Extract text from this image',
    'images' => ["data:image/jpeg;base64,{$imageData}"]
]);

// 场景 4: 使用 Message 对象
$message = new ImageMessage(
    text: 'What color is the car?',
    images: [
        Image::fromFile('car.jpg'),
        Image::fromUrl('https://example.com/car2.jpg')
    ]
);
$response = $agent->send($message);
```

**验收标准**:
- ✅ 支持本地文件路径、URL、Base64 三种图像输入方式
- ✅ 自动检测图像格式（JPEG, PNG, GIF, WebP）
- ✅ 自动处理图像大小限制（压缩或拒绝）
- ✅ 支持多图像输入
- ✅ 兼容 OpenAI Vision API 格式

---

### US-3.2: 作为开发者，我希望 Agent 能够控制鼠标和键盘
**优先级**: P2 (Nice to Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要让 Agent 能够操作 GUI 界面
以便实现 RPA（机器人流程自动化）功能
```

**场景描述**:
```php
use PhpAgent\Extension\ComputerControl;

$agent->use(new ComputerControl());

// Agent 现在可以调用:
// - vision_screenshot(): 截取屏幕
// - vision_analyze(query): 分析屏幕内容
// - vision_click(x, y): 点击坐标
// - vision_move(x, y): 移动鼠标
// - vision_type(text): 输入文本
// - vision_get_screen_size(): 获取屏幕尺寸

$agent->chat('打开浏览器，访问 google.com，搜索 PHP Agent');
```

**验收标准**:
- ✅ 跨平台支持（macOS, Linux, Windows）
- ✅ 坐标系统正确处理（考虑 HiDPI）
- ✅ 截图自动编码为 Base64 发送给 LLM
- ✅ 操作有安全限制（防止误操作）
- ✅ 支持模拟模式（不实际执行，仅返回模拟结果）

---

## Epic 4: 高级特性

### US-4.1: 作为开发者，我希望能够自定义 Agent 的行为
**优先级**: P1 (Should Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要通过 System Prompt 和配置来定制 Agent 的行为
以便适应不同的应用场景
```

**场景描述**:
```php
// 场景 1: 角色扮演
$agent = Agent::create([
    'llm' => [...],
    'system_prompt' => '你是一个专业的小说作家，擅长写悬疑推理小说。'
]);

// 场景 2: 输出格式控制
$agent = Agent::create([
    'llm' => [...],
    'system_prompt' => 'Always respond in JSON format.',
    'response_format' => ['type' => 'json_object']
]);

// 场景 3: 工具使用策略
$agent = Agent::create([
    'llm' => [...],
    'tool_choice' => 'required', // 强制使用工具
    // 或 'tool_choice' => ['name' => 'specific_tool'] // 强制使用特定工具
]);

// 场景 4: 温度和采样控制
$agent = Agent::create([
    'llm' => [...],
    'temperature' => 0.7,
    'top_p' => 0.9,
    'max_tokens' => 2000
]);
```

**验收标准**:
- ✅ 支持自定义 System Prompt
- ✅ 支持 JSON Mode
- ✅ 支持工具选择策略（auto, required, none, specific）
- ✅ 支持温度、top_p、max_tokens 等参数
- ✅ 支持 stop sequences

---

### US-4.2: 作为开发者，我希望能够持久化对话历史
**优先级**: P1 (Should Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要保存和恢复对话历史
以便实现多会话管理和对话恢复
```

**场景描述**:
```php
use PhpAgent\Session\SessionManager;
use PhpAgent\Session\Storage\DatabaseStorage;

// 场景 1: 使用 Session ID
$session = $agent->session('user-123');
$session->send('Hello');
// ... 稍后 ...
$session = $agent->session('user-123'); // 自动恢复历史
$session->send('Continue our conversation');

// 场景 2: 自定义存储
$storage = new DatabaseStorage($pdo);
$sessionManager = new SessionManager($storage);
$agent->setSessionManager($sessionManager);

// 场景 3: 导出和导入
$history = $session->export();
file_put_contents('session.json', json_encode($history));
// ... 稍后 ...
$history = json_decode(file_get_contents('session.json'), true);
$session->import($history);

// 场景 4: 清理历史
$session->clear(); // 清空当前会话
$sessionManager->deleteSession('user-123'); // 删除指定会话
$sessionManager->pruneOldSessions(days: 30); // 清理30天前的会话
```

**验收标准**:
- ✅ 支持内存、文件、数据库三种存储方式
- ✅ Session 自动过期机制
- ✅ 支持导出/导入 JSON 格式
- ✅ 线程安全（支持并发访问）
- ✅ 支持历史消息截断（避免 token 超限）

---

### US-4.3: 作为开发者，我希望能够监控 Agent 的执行过程
**优先级**: P1 (Should Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要获取 Agent 执行的详细日志和指标
以便调试问题和优化性能
```

**场景描述**:
```php
use PhpAgent\Logging\Logger;
use PhpAgent\Telemetry\Telemetry;

// 场景 1: 日志记录
$logger = new Logger('agent.log', level: Logger::DEBUG);
$agent->setLogger($logger);

// 场景 2: 事件监听
$agent->on('tool_call', function($event) {
    echo "Tool called: {$event->toolName}\n";
});

$agent->on('llm_request', function($event) {
    echo "LLM request: {$event->tokens} tokens\n";
});

$agent->on('error', function($event) {
    error_log("Agent error: {$event->message}");
});

// 场景 3: 性能指标
$telemetry = new Telemetry();
$agent->setTelemetry($telemetry);

$response = $agent->chat('Hello');

$metrics = $telemetry->getMetrics();
echo "Total time: {$metrics['total_time']}ms\n";
echo "LLM calls: {$metrics['llm_calls']}\n";
echo "Tool calls: {$metrics['tool_calls']}\n";
echo "Total tokens: {$metrics['total_tokens']}\n";
echo "Total cost: \${$metrics['total_cost']}\n";

// 场景 4: 调试模式
$agent->setDebug(true); // 打印所有请求/响应
```

**验收标准**:
- ✅ 支持 PSR-3 日志接口
- ✅ 事件系统支持常见事件（tool_call, llm_request, error 等）
- ✅ 记录 token 使用量和成本
- ✅ 记录执行时间
- ✅ 支持自定义 Telemetry 后端（如 Prometheus）

---

### US-4.4: 作为开发者，我希望能够并行执行多个工具调用
**优先级**: P2 (Nice to Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要 Agent 能够并行执行独立的工具调用
以便提高执行效率
```

**场景描述**:
```php
// LLM 返回多个独立的工具调用:
// 1. get_weather('Beijing')
// 2. get_weather('Shanghai')
// 3. get_weather('Guangzhou')

// 默认行为: 顺序执行（安全但慢）
$agent->chat('查询北京、上海、广州的天气');

// 启用并行执行
$agent->setParallelToolCalls(true);
$agent->chat('查询北京、上海、广州的天气'); // 三个调用并行执行
```

**验收标准**:
- ✅ 自动检测工具调用之间的依赖关系
- ✅ 无依赖的工具调用并行执行
- ✅ 有依赖的工具调用按顺序执行
- ✅ 支持最大并发数限制
- ✅ 错误隔离（一个工具失败不影响其他工具）

---

## Epic 5: 生态系统和扩展

### US-5.1: 作为开发者，我希望能够使用预构建的 Agent 模板
**优先级**: P2 (Nice to Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要使用预配置的 Agent 模板
以便快速实现常见场景
```

**场景描述**:
```php
use PhpAgent\Templates;

// 场景 1: 客服 Agent
$agent = Templates::customerService([
    'knowledge_base' => '/path/to/kb',
    'fallback_email' => 'support@example.com'
]);

// 场景 2: 代码审查 Agent
$agent = Templates::codeReviewer([
    'languages' => ['php', 'javascript'],
    'rules' => '/path/to/rules.yaml'
]);

// 场景 3: 数据分析 Agent
$agent = Templates::dataAnalyst([
    'database' => $pdo,
    'allowed_tables' => ['users', 'orders']
]);

// 场景 4: 内容生成 Agent
$agent = Templates::contentWriter([
    'style' => 'professional',
    'language' => 'zh-CN'
]);
```

**验收标准**:
- ✅ 提供至少 5 个常用模板
- ✅ 模板可定制化
- ✅ 模板包含预配置的工具和 System Prompt
- ✅ 模板有完整的文档和示例

---

### US-5.2: 作为开发者，我希望能够使用社区扩展
**优先级**: P2 (Nice to Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要安装和使用社区开发的扩展
以便快速添加新功能
```

**场景描述**:
```php
// 通过 Composer 安装扩展
// composer require php-agent/extension-database

use PhpAgent\Extension\Database\DatabaseExtension;

$extension = new DatabaseExtension($pdo);
$agent->use($extension);

// Agent 现在可以调用:
// - db_query(sql): 执行 SQL 查询
// - db_schema(): 获取数据库结构
// - db_explain(sql): 解释查询计划
```

**验收标准**:
- ✅ 扩展有标准接口（ExtensionInterface）
- ✅ 扩展可通过 Composer 安装
- ✅ 扩展可配置和定制
- ✅ 扩展有版本兼容性检查
- ✅ 官方维护常用扩展（Database, HTTP, Email 等）

---

## Epic 6: 企业级特性

### US-6.1: 作为开发者，我希望能够实现多 Agent 协作
**优先级**: P2 (Nice to Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要创建多个专门化的 Agent 并让它们协作
以便处理复杂的多步骤任务
```

**场景描述**:
```php
use PhpAgent\Team\AgentTeam;

// 创建专门化的 Agent
$researcher = Agent::create([...]);
$researcher->setRole('研究员', '负责收集和分析信息');

$writer = Agent::create([...]);
$writer->setRole('作家', '负责撰写内容');

$reviewer = Agent::create([...]);
$reviewer->setRole('审稿人', '负责审核和改进内容');

// 创建 Agent 团队
$team = new AgentTeam([$researcher, $writer, $reviewer]);

// 定义工作流
$team->workflow([
    'researcher' => '研究主题: {topic}',
    'writer' => '基于研究结果撰写文章',
    'reviewer' => '审核文章并提出改进建议',
    'writer' => '根据建议修改文章'
]);

// 执行任务
$result = $team->execute(['topic' => 'PHP 8.5 新特性']);
```

**验收标准**:
- ✅ 支持 Agent 之间的消息传递
- ✅ 支持工作流定义（顺序、并行、条件）
- ✅ 支持 Agent 角色和权限管理
- ✅ 支持共享上下文和知识库
- ✅ 支持团队执行的可视化和监控

---

### US-6.2: 作为开发者，我希望能够实现 Agent 的安全控制
**优先级**: P1 (Should Have)

**用户故事**:
```
作为 PHP 应用开发者
我想要限制 Agent 的权限和行为
以便确保系统安全
```

**场景描述**:
```php
use PhpAgent\Security\SecurityPolicy;

$policy = new SecurityPolicy([
    // 工具白名单
    'allowed_tools' => ['get_weather', 'search_web'],
    
    // 工具黑名单
    'denied_tools' => ['execute_code', 'delete_file'],
    
    // 参数验证
    'tool_validators' => [
        'send_email' => function($args) {
            // 只允许发送到公司域名
            return str_ends_with($args['to'], '@company.com');
        }
    ],
    
    // 速率限制
    'rate_limits' => [
        'llm_calls' => ['max' => 100, 'window' => 3600], // 每小时100次
        'tool_calls' => ['max' => 50, 'window' => 3600]
    ],
    
    // 成本限制
    'cost_limit' => ['max' => 10.0, 'currency' => 'USD'], // 最多花费$10
    
    // 内容过滤
    'content_filter' => true, // 启用敏感内容过滤
]);

$agent->setSecurityPolicy($policy);
```

**验收标准**:
- ✅ 支持工具白名单/黑名单
- ✅ 支持参数验证
- ✅ 支持速率限制
- ✅ 支持成本限制
- ✅ 支持内容过滤
- ✅ 违规行为有清晰的日志和告警

---

## 非功能性需求

### NFR-1: 性能
- 单次 LLM 调用延迟 < 5s（不含 LLM API 响应时间）
- 工具调用开销 < 10ms
- 支持 1000+ 并发 Agent 实例
- 内存占用 < 50MB per Agent

### NFR-2: 可靠性
- 自动重试机制（网络错误、API 限流）
- 优雅降级（LLM 不可用时的 fallback）
- 错误恢复（中断后可恢复执行）
- 99.9% 可用性

### NFR-3: 可维护性
- 代码覆盖率 > 80%
- 符合 PSR-12 编码规范
- 完整的 PHPDoc 注释
- 详细的开发者文档

### NFR-4: 兼容性
- PHP 8.5+
- 支持主流 LLM Provider（OpenAI, Anthropic, Azure, 本地模型）
- 支持主流框架（Laravel, Symfony, Slim）
- 跨平台（Linux, macOS, Windows）

### NFR-5: 安全性
- API Key 加密存储
- 敏感数据脱敏
- 防止 Prompt Injection
- 符合 GDPR/CCPA 数据保护要求

---

## 用户旅程地图

### Journey 1: 新手开发者首次使用
1. **发现**: 通过 Packagist 或 GitHub 发现库
2. **安装**: `composer require php-agent/agent`
3. **快速开始**: 复制文档中的 Hello World 示例
4. **第一次成功**: 5 分钟内实现第一个对话
5. **探索**: 阅读文档，尝试注册自定义工具
6. **集成**: 将 Agent 集成到现有项目

### Journey 2: 高级开发者构建复杂应用
1. **需求分析**: 确定需要哪些工具和能力
2. **架构设计**: 设计 Agent 工作流和工具链
3. **开发**: 实现自定义工具和 MCP Server
4. **测试**: 编写单元测试和集成测试
5. **部署**: 部署到生产环境
6. **监控**: 使用 Telemetry 监控性能和成本
7. **优化**: 根据监控数据优化 Prompt 和工具

---

## 成功指标

### 采用指标
- GitHub Stars > 1000 (6个月内)
- Packagist 下载量 > 10,000 (6个月内)
- 活跃贡献者 > 20

### 使用指标
- 平均首次成功时间 < 10 分钟
- 文档满意度 > 4.5/5
- Issue 响应时间 < 24 小时
- Bug 修复时间 < 7 天

### 技术指标
- 测试覆盖率 > 80%
- 性能基准测试通过率 100%
- 安全漏洞数 = 0
- API 稳定性（Breaking Changes < 1 per year）
