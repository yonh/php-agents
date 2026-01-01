# PHP Agent Library
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%5E8.5-blue.svg)](https://www.php.net/)
[![PSR-12](https://img.shields.io/badge/PSR--12-compliant-brightgreen.svg)](https://www.php-fig.org/psr/psr-12/)
[![PHPUnit](https://github.com/yonh/php-agents/actions/workflows/phpunit.yml/badge.svg)](https://github.com/yonh/php-agents/actions/workflows/phpunit.yml)
[![Coverage](https://codecov.io/gh/yonh/php-agents/branch/main/graph/badge.svg)](https://codecov.io/gh/yonh/php-agents)

一个现代化的 PHP 8.5+ Agent 库，用于集成 LLM（大语言模型）能力，支持工具调用、多轮对话和 ReAct 推理循环。

## ✨ 特性

- 🚀 **简单易用** - 直观的 API 设计，几行代码即可开始使用
- 🔧 **灵活工具系统** - 支持自定义工具注册和参数验证
- 🔄 **ReAct 推理循环** - 自动多步推理和工具链调用
- 🎯 **多 LLM 支持** - OpenAI、智谱 AI、Azure、Anthropic、Ollama
- 📦 **可扩展架构** - 工厂模式、依赖注入、接口隔离
- 🔒 **类型安全** - 充分利用 PHP 8.5 类型系统
- 📝 **灵活日志** - 支持自定义日志工厂和配置
- 💾 **会话管理** - 支持多会话和持久化存储

## 📦 安装

```bash
composer require yonh/php-agents
```

## 🚀 快速开始

### 基础对话

```php
<?php
require_once 'vendor/autoload.php';

use PhpAgent\Agent;

$agent = Agent::create([
    'llm' => [
        'provider' => 'openai',
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => 'gpt-4'
    ]
]);

$response = $agent->chat('你好！请介绍一下你自己。');
echo $response->content;
```

### 自定义工具

```php
use PhpAgent\Tool\Parameter;

// 注册天气查询工具
$agent->registerTool(
    name: 'get_weather',
    description: '获取指定城市的天气信息',
    parameters: [
        Parameter::string('city', '城市名称', required: true),
        Parameter::enum('unit', '温度单位', ['celsius', 'fahrenheit'], default: 'celsius')
    ],
    handler: function($args) {
        // 这里可以调用真实的天气 API
        $city = $args['city'];
        $unit = $args['unit'];
        return "{$city}今天晴，温度 25°{$unit === 'celsius' ? 'C' : 'F'}";
    }
);

$response = $agent->chat('北京今天天气怎么样？');
echo $response->content;
```

### 多轮对话

```php
// 创建会话
$session = $agent->createSession();

$session->send('我叫张三，是一名程序员');
$session->send('我刚才说了什么职业？'); // AI 会记住上下文

// 或者直接使用 Agent（自动管理会话）
$agent->chat('我叫李四');
$agent->chat('我刚才叫什么名字？'); // 在同一会话中
```

### ReAct 推理循环

```php
// 注册多个工具，让 AI 自动组合使用
$agent->registerTool(
    name: 'search_web',
    description: '在网络上搜索信息',
    parameters: [Parameter::string('query', '搜索关键词', required: true)],
    handler: fn($args) => "搜索结果：关于 '{$args['query']}' 的相关信息..."
);

$agent->registerTool(
    name: 'calculate',
    description: '执行数学计算',
    parameters: [
        Parameter::string('expression', '数学表达式', required: true)
    ],
    handler: fn($args) => eval("return {$args['expression']};")
);

// AI 会自动决定使用哪些工具来回答复杂问题
$response = $agent->chat('计算 100 + 200，然后搜索这个结果的历史意义');
echo $response->content;
```

## 🔧 配置选项

```php
$agent = Agent::create([
    'llm' => [
        'provider' => 'openai',           // LLM 提供商
        'api_key' => 'sk-xxx',            // API Key
        'model' => 'gpt-4',               // 模型名称
        'base_url' => null,               // 自定义 API 地址
        'timeout' => 30,                  // 超时时间（秒）
    ],
    'max_iterations' => 10,               // 最大推理步数
    'system_prompt' => null,              // 系统提示
    'max_retries' => 3,                   // 最大重试次数
    'logger_config' => [                  // 日志配置
        'log_dir' => 'logs',
        'log_file' => 'agent.log',
        'log_level' => \Monolog\Logger::INFO
    ]
]);
```

### 支持的 LLM 提供商

| 提供商 | Provider 值 | 默认模型 | 说明 |
|--------|-------------|----------|------|
| OpenAI | `openai` | `gpt-3.5-turbo` | 完全支持 |
| 智谱 AI | `zai` | `glm-4.6v` | 支持聊天和工具调用 |
| Azure | `azure` | - | 需要配置 Azure 特定参数 |
| Anthropic | `anthropic` | - | 基础支持 |
| Ollama | `ollama` | - | 本地模型支持 |

## 📁 项目结构

```
src/
├── Agent.php                    # 核心 Agent 类
├── AgentConfig.php             # 配置类
├── Response.php                # 响应类
├── Contract/                   # 接口定义
│   ├── LoggerInterface.php
│   ├── LoggerFactoryInterface.php
│   ├── SecurityPolicy.php
│   └── TelemetryInterface.php
├── Exception/                  # 异常类 (11个)
├── Llm/                        # LLM Provider
│   ├── LlmProviderInterface.php
│   ├── LlmConfig.php
│   ├── LlmResponse.php
│   ├── Usage.php
│   ├── LlmProviderFactory.php
│   └── Providers/              # 各种 Provider 实现
├── Tool/                       # 工具系统
│   ├── Tool.php
│   ├── ToolRegistry.php
│   └── Parameter.php
├── Session/                    # 会话管理
│   ├── Session.php
│   ├── SessionManager.php
│   └── Storage/
└── Util/                       # 工具类
    ├── NullLogger.php
    ├── PsrLoggerAdapter.php
    └── DefaultLoggerFactory.php
```

## 📚 示例代码

查看 `examples/` 目录获取完整示例：

- [`01-hello-world.php`](examples/01-hello-world.php) - 基础对话示例
- [`02-custom-tools.php`](examples/02-custom-tools.php) - 自定义工具示例
- [`03-multi-turn-chat.php`](examples/03-multi-turn-chat.php) - 多轮对话示例
- [`04-react-loop.php`](examples/04-react-loop.php) - ReAct 推理循环示例

### 运行示例

```bash
# 设置环境变量
export OPENAI_API_KEY="your-api-key"

# 运行示例
php examples/01-hello-world.php
php examples/02-custom-tools.php
php examples/03-multi-turn-chat.php
php examples/04-react-loop.php
```

## 🔌 高级功能

### 自定义日志工厂

```php
use PhpAgent\Contract\LoggerFactoryInterface;

class CustomLoggerFactory implements LoggerFactoryInterface
{
    public function createLogger(array $config = []): LoggerInterface
    {
        // 实现你的自定义日志逻辑
        return new YourCustomLogger($config);
    }
}

$agent = Agent::create([
    'llm' => $llmConfig,
    'logger_factory' => new CustomLoggerFactory(),
    'logger_config' => $customConfig
]);
```

### 集成现有日志系统

```php
// Laravel 集成
class LaravelLoggerFactory implements LoggerFactoryInterface
{
    public function createLogger(array $config = []): LoggerInterface
    {
        return new class implements LoggerInterface {
            public function info(string $message, array $context = []): void
            {
                \Log::info($message, $context);
            }
            // ... 其他方法
        };
    }
}
```

### 自定义存储后端

```php
use PhpAgent\Session\Storage\StorageInterface;

class DatabaseStorage implements StorageInterface
{
    public function save(string $id, array $data): void
    {
        // 保存到数据库
    }
    
    public function load(string $id): ?array
    {
        // 从数据库加载
    }
    
    public function delete(string $id): void
    {
        // 从数据库删除
    }
}

$agent = new Agent($config);
$agent->setSessionStorage(new DatabaseStorage());
```

## 🧪 开发

### 环境要求

- PHP 8.5+
- Composer
- ext-json
- ext-curl

### 开发环境搭建

```bash
# 克隆仓库
git clone https://github.com/your-org/php-agent.git
cd php-agent

# 安装依赖
composer install

# 复制环境配置
cp .env.example .env
# 编辑 .env 文件设置 API Key
```

### 运行测试

```bash
# 运行所有测试
composer test

# 运行测试覆盖率
composer test-coverage

# 静态分析
composer phpstan

# 代码风格检查
composer cs-check

# 自动修复代码风格
composer cs-fix
```

## 📖 文档

详细文档请查看 [`docs/`](docs/) 目录：

- [实现状态](docs/implementation-status.md) - 当前实现进度和统计
- [开发指南](docs/development-guide.md) - 开发环境搭建和贡献指南
- [日志集成](docs/06-logging-integration.md) - 日志系统集成指南
- [用户故事](docs/01-user-stories.md) - 详细需求分析
- [技术架构](docs/04-technical-architecture.md) - 架构设计文档
- [API 文档](docs/api/) - 完整的 API 参考

## 🤝 贡献

我们欢迎所有形式的贡献！请查看 [贡献指南](CONTRIBUTING.md) 了解详细信息。

### 贡献流程

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'Add some amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 创建 Pull Request

## 📊 项目状态

- ✅ **核心功能** - Agent、工具系统、会话管理
- ✅ **LLM 集成** - OpenAI、智谱 AI 完全支持
- ✅ **类型安全** - PHP 8.5 严格类型
- ✅ **可扩展性** - 工厂模式、接口设计
- 🚧 **流式响应** - 开发中
- 🚧 **更多 Provider** - Azure、Anthropic 完善中
- 📋 **测试覆盖** - 持续改进中

## 🐛 问题反馈

如果你发现 bug 或有功能建议，请：

1. 查看 [已知问题](https://github.com/your-org/php-agent/issues)
2. 创建新的 [Issue](https://github.com/your-org/php-agent/issues/new)
3. 提供详细的复现步骤和环境信息

## 📄 许可证

本项目采用 [MIT 许可证](LICENSE)。

## 🙏 致谢

感谢所有为这个项目做出贡献的开发者！

---

**PHP Agent Library** - 让 PHP 拥有强大的 AI 能力 🚀