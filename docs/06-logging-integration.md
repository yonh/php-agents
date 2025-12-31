# 日志集成指南

PHP Agent 提供了灵活的日志接口，允许第三方开发者自定义日志实现。

## 概述

PHP Agent 使用工厂模式来创建日志记录器，通过 `LoggerFactoryInterface` 接口，你可以：

- 使用默认的文件日志实现
- 集成自定义的日志系统
- 配置不同的日志级别和存储位置
- 实现日志聚合、远程日志等高级功能

## 使用默认日志工厂

```php
use PhpAgent\Agent;
use PhpAgent\AgentConfig;

// 使用默认配置（日志写入 logs/agent.log）
$config = AgentConfig::fromArray([
    'llm' => [
        'provider' => 'openai',
        'api_key' => 'your-api-key',
        'model' => 'gpt-3.5-turbo'
    ]
]);

$agent = new Agent($config);
```

## 自定义日志配置

```php
use PhpAgent\AgentConfig;

// 自定义日志文件和级别
$config = AgentConfig::fromArray([
    'llm' => [
        'provider' => 'openai',
        'api_key' => 'your-api-key',
        'model' => 'gpt-3.5-turbo'
    ],
    'logger_config' => [
        'log_dir' => 'custom-logs',
        'log_file' => 'my-app.log',
        'log_level' => \Monolog\Logger::DEBUG
    ]
]);

$agent = new Agent($config);
```

## 实现自定义日志工厂

### 1. 创建自定义日志工厂

```php
use PhpAgent\Contract\LoggerFactoryInterface;
use PhpAgent\Contract\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;

class CustomLoggerFactory implements LoggerFactoryInterface
{
    public function createLogger(array $config = []): LoggerInterface
    {
        $logger = new Logger('php-agent');
        
        // 添加轮转文件处理器
        $logger->pushHandler(new RotatingFileHandler(
            $config['log_file'] ?? 'logs/agent.log',
            $config['max_files'] ?? 7,
            $config['log_level'] ?? Logger::INFO
        ));
        
        // 添加 Slack 通知（仅错误级别）
        if (!empty($config['slack_webhook'])) {
            $logger->pushHandler(new SlackWebhookHandler(
                $config['slack_webhook'],
                '#alerts',
                $config['slack_username'] ?? 'PHP Agent',
                true, // use attachment
                $config['slack_level'] ?? Logger::ERROR
            ));
        }
        
        return new PsrLoggerAdapter($logger);
    }
}
```

### 2. 使用自定义日志工厂

```php
use PhpAgent\AgentConfig;

$config = new AgentConfig(
    llm: $llmConfig,
    loggerFactory: new CustomLoggerFactory(),
    loggerConfig: [
        'log_file' => 'logs/app.log',
        'max_files' => 30,
        'slack_webhook' => 'https://hooks.slack.com/services/...',
        'slack_level' => \Monolog\Logger::ERROR
    ]
);

$agent = new Agent($config);
```

## 集成现有日志系统

### 集成 Laravel 日志系统

```php
use Illuminate\Support\Facades\Log;
use PhpAgent\Contract\LoggerFactoryInterface;
use PhpAgent\Contract\LoggerInterface;

class LaravelLoggerFactory implements LoggerFactoryInterface
{
    public function createLogger(array $config = []): LoggerInterface
    {
        return new class implements LoggerInterface {
            public function info(string $message, array $context = []): void
            {
                Log::info($message, $context);
            }
            
            public function error(string $message, array $context = []): void
            {
                Log::error($message, $context);
            }
            
            public function debug(string $message, array $context = []): void
            {
                Log::debug($message, $context);
            }
            
            public function warning(string $message, array $context = []): void
            {
                Log::warning($message, $context);
            }
        };
    }
}
```

### 集成 Symfony 日志系统

```php
use Psr\Log\LoggerInterface as SymfonyLoggerInterface;
use PhpAgent\Contract\LoggerFactoryInterface;
use PhpAgent\Contract\LoggerInterface;

class SymfonyLoggerFactory implements LoggerFactoryInterface
{
    public function __construct(private SymfonyLoggerInterface $symfonyLogger)
    {
    }
    
    public function createLogger(array $config = []): LoggerInterface
    {
        return new class ($this->symfonyLogger) implements LoggerInterface {
            public function __construct(private SymfonyLoggerInterface $logger)
            {
            }
            
            public function info(string $message, array $context = []): void
            {
                $this->logger->info($message, $context);
            }
            
            public function error(string $message, array $context = []): void
            {
                $this->logger->error($message, $context);
            }
            
            public function debug(string $message, array $context = []): void
            {
                $this->logger->debug($message, $context);
            }
            
            public function warning(string $message, array $context = []): void
            {
                $this->logger->warning($message, $context);
            }
        };
    }
}
```

## 高级用例

### 远程日志服务

```php
class RemoteLoggerFactory implements LoggerFactoryInterface
{
    public function createLogger(array $config = []): LoggerInterface
    {
        return new class ($config) implements LoggerInterface {
            private array $config;
            
            public function __construct(array $config)
            {
                $this->config = $config;
            }
            
            public function info(string $message, array $context = []): void
            {
                $this->sendToRemote('info', $message, $context);
            }
            
            public function error(string $message, array $context = []): void
            {
                $this->sendToRemote('error', $message, $context);
            }
            
            public function debug(string $message, array $context = []): void
            {
                $this->sendToRemote('debug', $message, $context);
            }
            
            public function warning(string $message, array $context = []): void
            {
                $this->sendToRemote('warning', $message, $context);
            }
            
            private function sendToRemote(string $level, string $message, array $context): void
            {
                $data = [
                    'level' => $level,
                    'message' => $message,
                    'context' => $context,
                    'timestamp' => date('c'),
                    'service' => 'php-agent'
                ];
                
                // 发送到远程日志服务
                file_get_contents($this->config['endpoint'], false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => 'Content-Type: application/json',
                        'content' => json_encode($data)
                    ]
                ]));
            }
        };
    }
}
```

## 最佳实践

1. **性能考虑**：避免在生产环境中使用 DEBUG 级别日志
2. **安全性**：确保日志中不包含敏感信息（API 密钥、密码等）
3. **轮转策略**：使用日志轮转防止日志文件过大
4. **结构化日志**：使用结构化格式（JSON）便于日志分析
5. **错误处理**：在自定义日志工厂中添加适当的错误处理

## 总结

通过工厂模式，PHP Agent 提供了完全可定制的日志系统，让第三方开发者可以：

- 集成现有的日志基础设施
- 实现自定义的日志处理逻辑
- 配置不同的日志级别和存储方式
- 支持远程日志、日志聚合等高级功能

这种设计确保了 PHP Agent 作为一个库的灵活性和可扩展性。