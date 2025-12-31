# PHP Agent Library - 项目总览

## 项目简介

**PHP Agent Library** 是一个现代化的 PHP 8.5+ 库，旨在为 PHP 应用提供强大的 AI Agent 能力。通过简洁的 API，开发者可以轻松集成 LLM（大语言模型），实现智能对话、工具调用、ReAct 推理循环等功能。

### 核心特性（当前可用）

- 🚀 **简单易用**: 5 分钟快速上手，API 设计直观
- 🔧 **工具系统**: 灵活的工具注册和调用机制
- 🔄 **ReAct 循环**: 自动执行"思考-行动-观察"推理循环
- 🎯 **LLM Provider**: 支持 OpenAI、智谱（zai），其他 Provider 规划中
- 📦 **可扩展**: 强大的扩展系统，易于定制
- 🔒 **安全可靠**: 内置错误处理（网络/API/速率限制）

---

## 快速开始

### 安装

```bash
composer require php-agent/agent
```

### Hello World

```php
<?php
require 'vendor/autoload.php';

use PhpAgent\Agent;

$agent = Agent::create([
    'llm' => [
        'provider' => 'openai',
        'api_key' => getenv('OPENAI_API_KEY'),
        'model' => 'gpt-4'
    ]
]);

$response = $agent->chat('你好，请介绍一下自己');
echo $response->content;
```

---

## 文档结构

### 1. [用户故事](./01-user-stories.md)
定义用户需求和使用场景

### 2. [验收标准](./02-acceptance-criteria.md)
明确每个功能的验收标准

### 3. [测试用例](./03-test-cases.md)
提供具体的测试用例

### 4. [技术架构](./04-technical-architecture.md)
定义系统的技术架构

### 5. [实现指南](./05-implementation-guide.md)
指导开发团队实现系统

---

## 核心概念

### Agent（智能体）
Agent 是库的核心类，负责协调 LLM、工具、会话等组件。

### Tool（工具）
工具是 Agent 可以调用的 PHP 函数，用于执行特定任务。

### ReAct 循环
自动执行"思考-行动-观察"推理循环，直到得出最终答案。

### MCP（Model Context Protocol）
标准协议，用于 Agent 与外部服务通信。

### Session（会话）
管理对话历史，支持多轮对话和上下文记忆。

---

## 许可证

MIT License
