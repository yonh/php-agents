# 开发指南

本文档为 PHP Agent Library 的开发者提供详细的开发环境搭建、贡献指南和最佳实践。

## 📋 目录

- [环境要求](#环境要求)
- [开发环境搭建](#开发环境搭建)
- [项目结构](#项目结构)
- [开发流程](#开发流程)
- [代码规范](#代码规范)
- [测试指南](#测试指南)
- [贡献指南](#贡献指南)
- [发布流程](#发布流程)

## 🔧 环境要求

### 必需环境
- **PHP**: 8.5 或更高版本
- **Composer**: 2.0 或更高版本
- **Git**: 2.0 或更高版本

### PHP 扩展
- `ext-json` - JSON 处理
- `ext-curl` - HTTP 请求
- `ext-mbstring` - 多字节字符串处理（推荐）

### 开发工具（推荐）
- **PHPUnit**: 单元测试
- **PHPStan**: 静态分析
- **PHP-CS-Fixer**: 代码风格检查
- **PHPMD**: 代码质量检测

## 🚀 开发环境搭建

### 1. 克隆仓库

```bash
git clone https://github.com/your-org/php-agent.git
cd php-agent
```

### 2. 安装依赖

```bash
# 安装生产依赖
composer install --no-dev

# 安装开发依赖
composer install
```

### 3. 环境配置

```bash
# 复制环境配置文件
cp .env.example .env

# 编辑配置文件
nano .env
```

在 `.env` 文件中设置你的 API 密钥：

```env
# OpenAI
OPENAI_API_KEY=sk-your-openai-api-key

# 智谱 AI
ZAI_API_KEY=your-zai-api-key

# Azure（如果使用）
AZURE_OPENAI_API_KEY=your-azure-api-key
AZURE_OPENAI_ENDPOINT=https://your-resource.openai.azure.com/
AZURE_OPENAI_DEPLOYMENT=gpt-4
```

### 4. 验证安装

```bash
# 运行基础示例
php examples/01-hello-world.php

# 检查代码质量
composer cs-check

# 运行静态分析
composer phpstan
```

## 📁 项目结构详解

```
php-agent/
├── src/                        # 源代码目录
│   ├── Agent.php              # 核心 Agent 类
│   ├── AgentConfig.php        # 配置管理
│   ├── Response.php           # 响应封装
│   ├── Contract/              # 接口定义
│   │   ├── LoggerInterface.php
│   │   ├── LoggerFactoryInterface.php
│   │   ├── SecurityPolicy.php
│   │   └── TelemetryInterface.php
│   ├── Exception/             # 异常类
│   │   ├── AgentException.php
│   │   ├── ApiException.php
│   │   └── ...
│   ├── Llm/                   # LLM Provider
│   │   ├── LlmProviderInterface.php
│   │   ├── LlmProviderFactory.php
│   │   ├── LlmConfig.php
│   │   ├── LlmResponse.php
│   │   ├── Usage.php
│   │   └── Providers/
│   │       ├── OpenAiProvider.php
│   │       ├── ZaiProvider.php
│   │       └── ...
│   ├── Session/               # 会话管理
│   │   ├── Session.php
│   │   ├── SessionManager.php
│   │   └── Storage/
│   │       ├── StorageInterface.php
│   │       └── MemoryStorage.php
│   ├── Tool/                  # 工具系统
│   │   ├── Tool.php
│   │   ├── ToolRegistry.php
│   │   └── Parameter.php
│   └── Util/                  # 工具类
│       ├── NullLogger.php
│       ├── PsrLoggerAdapter.php
│       ├── DefaultLoggerFactory.php
│       ├── NullSecurityPolicy.php
│       └── NullTelemetry.php
├── examples/                   # 示例代码
│   ├── 01-hello-world.php
│   ├── 02-custom-tools.php
│   ├── 03-multi-turn-chat.php
│   └── 04-react-loop.php
├── tests/                      # 测试代码
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
├── docs/                       # 文档
│   ├── implementation-status.md
│   ├── development-guide.md
│   └── ...
├── .github/                    # GitHub 配置
│   ├── workflows/
│   ├── ISSUE_TEMPLATE/
│   └── PULL_REQUEST_TEMPLATE.md
├── composer.json              # 依赖配置
├── .env.example               # 环境变量示例
├── .gitignore                 # Git 忽略文件
├── phpunit.xml.dist          # PHPUnit 配置
├── phpstan.neon              # PHPStan 配置
└── .php-cs-fixer.php         # 代码风格配置
```

## 🔄 开发流程

### 1. 创建功能分支

```bash
# 从 main 分支创建新分支
git checkout main
git pull origin main
git checkout -b feature/your-feature-name

# 或者修复 bug
git checkout -b fix/bug-description
```

### 2. 开发代码

遵循以下原则：
- **单一职责**: 每个类只负责一个功能
- **开闭原则**: 对扩展开放，对修改关闭
- **依赖倒置**: 依赖抽象而不是具体实现
- **接口隔离**: 使用小而专一的接口

### 3. 运行测试

```bash
# 运行所有测试
composer test

# 运行特定测试
./vendor/bin/phpunit tests/Unit/AgentTest.php

# 生成覆盖率报告
composer test-coverage
```

### 4. 代码质量检查

```bash
# 代码风格检查
composer cs-check

# 自动修复代码风格
composer cs-fix

# 静态分析
composer phpstan

# 代码质量检测
composer phpmd
```

### 5. 提交代码

```bash
# 添加文件
git add .

# 提交（使用有意义的提交信息）
git commit -m "feat: add Anthropic provider support"

# 推送到远程分支
git push origin feature/your-feature-name
```

## 📝 代码规范

### 1. PSR 标准

本项目严格遵循以下 PSR 标准：
- **PSR-1**: 基本编码标准
- **PSR-12**: 编码风格指南
- **PSR-3**: 日志接口
- **PSR-4**: 自动加载

### 2. 命名约定

#### 类名
```php
// 使用 PascalCase
class AgentConfig
class LlmProviderFactory
class ToolRegistry
```

#### 方法名
```php
// 使用 camelCase
public function createLogger()
public function registerTool()
public function handleToolCalls()
```

#### 常量名
```php
// 使用 UPPER_SNAKE_CASE
const MAX_ITERATIONS = 10;
const DEFAULT_TIMEOUT = 30;
```

#### 变量名
```php
// 使用 camelCase
$agentConfig = new AgentConfig();
$toolRegistry = new ToolRegistry();
$llmResponse = $provider->chat($messages);
```

### 3. 文档注释

```php
/**
 * 创建 Agent 实例
 *
 * @param array|AgentConfig $config 配置数组或配置对象
 * @return self Agent 实例
 * @throws ConfigException 当配置无效时
 * 
 * @example
 * $agent = Agent::create([
 *     'llm' => [
 *         'provider' => 'openai',
 *         'api_key' => 'sk-xxx',
 *         'model' => 'gpt-4'
 *     ]
 * ]);
 */
public static function create(array|AgentConfig $config): self
{
    // 实现...
}
```

### 4. 类型声明

```php
// 严格类型声明
declare(strict_types=1);

// 方法参数和返回值类型
public function chat(string|array $message, array $options = []): Response
{
    // 实现...
}

// 属性类型
private readonly AgentConfig $config;
private LoggerInterface $logger;
private ?Session $currentSession = null;
```

### 5. 异常处理

```php
try {
    $response = $this->llmProvider->chat($messages);
} catch (RateLimitException $e) {
    $this->logger->error('Rate limit exceeded', ['error' => $e->getMessage()]);
    throw new AgentException('Service temporarily unavailable', 0, $e);
} catch (NetworkException $e) {
    $this->logger->error('Network error', ['error' => $e->getMessage()]);
    throw new AgentException('Network connection failed', 0, $e);
}
```

## 🧪 测试指南

### 1. 测试结构

```
tests/
├── Unit/                   # 单元测试
│   ├── AgentTest.php
│   ├── ToolRegistryTest.php
│   └── SessionManagerTest.php
├── Integration/            # 集成测试
│   ├── OpenAiProviderTest.php
│   └── ZaiProviderTest.php
├── Feature/                # 功能测试
│   ├── ChatFlowTest.php
│   └── ToolExecutionTest.php
└── fixtures/               # 测试数据
    ├── responses/
    └── configs/
```

### 2. 编写单元测试

```php
<?php

namespace PhpAgent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpAgent\Agent;
use PhpAgent\AgentConfig;
use PhpAgent\Llm\LlmConfig;

class AgentTest extends TestCase
{
    private Agent $agent;
    
    protected function setUp(): void
    {
        $config = new AgentConfig(
            llm: new LlmConfig(
                provider: 'openai',
                apiKey: 'test-key',
                model: 'gpt-4'
            )
        );
        
        $this->agent = new Agent($config);
    }
    
    public function testCreateAgent(): void
    {
        $this->assertInstanceOf(Agent::class, $this->agent);
    }
    
    public function testRegisterTool(): void
    {
        $this->agent->registerTool(
            name: 'test_tool',
            description: 'Test tool',
            parameters: [],
            handler: fn() => 'test result'
        );
        
        $this->assertTrue($this->agent->hasTool('test_tool'));
    }
}
```

### 3. Mock 外部依赖

```php
use PHPUnit\Framework\MockObject\MockObject;
use PhpAgent\Llm\LlmProviderInterface;
use PhpAgent\Llm\LlmResponse;

public function testChatWithMockProvider(): void
{
    // 创建 Mock Provider
    /** @var LlmProviderInterface|MockObject $provider */
    $provider = $this->createMock(LlmProviderInterface::class);
    
    // 设置预期行为
    $provider->expects($this->once())
        ->method('chat')
        ->willReturn(new LlmResponse(
            message: ['content' => 'Hello!'],
            finishReason: 'stop',
            usage: new Usage(10, 20, 30)
        ));
    
    // 注入 Mock
    $agent = new Agent($config);
    $agent->setLlmProvider($provider);
    
    // 测试
    $response = $agent->chat('Hello');
    $this->assertEquals('Hello!', $response->content);
}
```

### 4. 集成测试

```php
/**
 * @group integration
 */
class OpenAiProviderIntegrationTest extends TestCase
{
    private ?string $apiKey;
    
    protected function setUp(): void
    {
        $this->apiKey = getenv('OPENAI_API_KEY');
        if (!$this->apiKey) {
            $this->markTestSkipped('No OpenAI API key provided');
        }
    }
    
    public function testRealApiCall(): void
    {
        $provider = new OpenAiProvider([
            'api_key' => $this->apiKey,
            'model' => 'gpt-3.5-turbo'
        ]);
        
        $response = $provider->chat([
            'messages' => [['role' => 'user', 'content' => 'Say "Hello World"']]
        ]);
        
        $this->assertNotEmpty($response->message['content']);
        $this->assertNotNull($response->usage);
    }
}
```

## 🤝 贡献指南

### 1. 贡献类型

我们欢迎以下类型的贡献：

- 🐛 **Bug 修复**: 修复现有问题
- ✨ **新功能**: 添加新的功能特性
- 📚 **文档**: 改进文档和示例
- 🧪 **测试**: 增加测试覆盖率
- 🔧 **工具**: 改进开发工具和流程
- 🎨 **代码质量**: 重构和优化

### 2. Pull Request 流程

#### 创建 PR 前检查清单

- [ ] 代码通过所有测试
- [ ] 代码风格符合 PSR-12
- [ ] 静态分析无错误
- [ ] 添加了必要的测试
- [ ] 更新了相关文档
- [ ] 提交信息符合规范

#### PR 模板

```markdown
## 变更描述
简要描述这个 PR 的变更内容。

## 变更类型
- [ ] Bug 修复
- [ ] 新功能
- [ ] 文档更新
- [ ] 代码重构
- [ ] 性能优化
- [ ] 其他

## 测试
- [ ] 单元测试通过
- [ ] 集成测试通过
- [ ] 手动测试完成

## 检查清单
- [ ] 代码遵循项目规范
- [ ] 添加了必要的注释
- [ ] 更新了相关文档
- [ ] 没有引入新的警告

## 相关 Issue
Closes #123
```

### 3. 提交信息规范

使用 [Conventional Commits](https://www.conventionalcommits.org/) 规范：

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

#### 类型说明
- `feat`: 新功能
- `fix`: Bug 修复
- `docs`: 文档更新
- `style`: 代码格式调整
- `refactor`: 代码重构
- `test`: 测试相关
- `chore`: 构建过程或辅助工具的变动

#### 示例
```
feat(llm): add Anthropic provider support

- Implement AnthropicProvider class
- Add Claude model support
- Include streaming response capability

Closes #45
```

## 🚀 发布流程

### 1. 版本号规范

遵循 [Semantic Versioning](https://semver.org/)：
- `MAJOR.MINOR.PATCH`
- `1.0.0` - 主要版本（不兼容的 API 变更）
- `1.1.0` - 次要版本（向后兼容的功能性新增）
- `1.1.1` - 修订版本（向后兼容的问题修正）

### 2. 发布检查清单

#### 代码质量
- [ ] 所有测试通过
- [ ] 测试覆盖率 > 80%
- [ ] PHPStan Level 8 通过
- [ ] 代码风格 100% 符合 PSR-12

#### 文档完整性
- [ ] README.md 更新
- [ ] CHANGELOG.md 更新
- [ ] API 文档生成
- [ ] 示例代码验证

#### 版本准备
- [ ] 更新版本号
- [ ] 创建 Git 标签
- [ ] 生成 Release Notes
- [ ] 发布到 Packagist

### 3. 发布命令

```bash
# 更新版本号
composer version patch  # 或 minor, major

# 生成 changelog
git log --pretty=format:"- %s" $(git describe --tags --abbrev=0)..HEAD > CHANGELOG.md

# 创建标签
git tag -a v1.1.0 -m "Release version 1.1.0"

# 推送标签
git push origin v1.1.0

# 发布到 Packagist（自动或手动）
```

## 📊 质量指标

### 目标指标

| 指标 | 目标值 | 当前值 | 状态 |
|------|--------|--------|------|
| 测试覆盖率 | > 80% | 0% | 🔴 |
| PHPStan Level | Level 8 | 未检查 | 🔴 |
| PSR-12 合规性 | 100% | 未检查 | 🔴 |
| 文档覆盖率 | 100% | 70% | 🟡 |
| CI/CD 通过率 | 100% | 100% | ✅ |

### 监控工具

- **GitHub Actions**: 自动化 CI/CD
- **Codecov**: 测试覆盖率监控
- **PHPStan**: 静态分析
- **PHP-CS-Fixer**: 代码风格检查
- **SonarQube**: 代码质量分析

## 🆘 故障排除

### 常见问题

#### 1. 依赖安装失败
```bash
# 清理缓存
composer clear-cache

# 重新安装
composer install --no-cache

# 检查 PHP 版本
php --version  # 需要 >= 8.5
```

#### 2. 测试失败
```bash
# 检查环境变量
env | grep API_KEY

# 重新生成自动加载
composer dump-autoload

# 运行特定测试
./vendor/bin/phpunit --verbose tests/Unit/AgentTest.php
```

#### 3. 代码风格问题
```bash
# 自动修复
composer cs-fix

# 检查具体问题
./vendor/bin/php-cs-fixer fix --dry-run --diff src/Agent.php
```

### 获取帮助

- 📖 查看 [文档](../README.md)
- 🐛 提交 [Issue](https://github.com/your-org/php-agent/issues)
- 💬 参与 [Discussion](https://github.com/your-org/php-agent/discussions)
- 📧 联系维护者

---

**维护者**: PHP Agent Team  
**最后更新**: 2024-12-31  
**文档版本**: 1.0.0