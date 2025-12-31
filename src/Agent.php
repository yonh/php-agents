<?php

declare(strict_types=1);

namespace PhpAgent;

use PhpAgent\Contract\LoggerInterface;
use PhpAgent\Contract\SecurityPolicy;
use PhpAgent\Contract\TelemetryInterface;
use PhpAgent\Llm\LlmProviderInterface;
use PhpAgent\Llm\LlmProviderFactory;
use PhpAgent\Llm\Usage;
use PhpAgent\Tool\Tool;
use PhpAgent\Tool\ToolRegistry;
use PhpAgent\Session\Session;
use PhpAgent\Session\SessionManager;
use PhpAgent\Session\Storage\MemoryStorage;
use PhpAgent\Exception\MaxIterationsException;
use PhpAgent\Exception\RateLimitException;
use PhpAgent\Util\NullLogger;
use PhpAgent\Util\PsrLoggerAdapter;
use Psr\Log\NullLogger as PsrNullLogger;
use PhpAgent\Util\NullTelemetry;
use PhpAgent\Util\NullSecurityPolicy;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class Agent
{
    private AgentConfig $config;
    private LlmProviderInterface $llmProvider;
    private ToolRegistry $toolRegistry;
    private SessionManager $sessionManager;
    private LoggerInterface $logger;
    private TelemetryInterface $telemetry;
    private SecurityPolicy $securityPolicy;
    private ?Session $currentSession = null;

    public function __construct(AgentConfig $config)
    {
        $this->config = $config;
        $this->llmProvider = LlmProviderFactory::create(
            $config->llm->provider,
            [
                'api_key' => $config->llm->apiKey,
                'model' => $config->llm->model,
                'base_url' => $config->llm->baseUrl,
                'timeout' => $config->llm->timeout,
                'max_retries' => $config->maxRetries
            ]
        );
        $this->toolRegistry = new ToolRegistry();
        $this->sessionManager = new SessionManager(new MemoryStorage());
        $this->logger = $this->createDefaultLogger();
        $this->telemetry = new NullTelemetry();
        $this->securityPolicy = new NullSecurityPolicy();
    }

    public static function create(array|AgentConfig $config): self
    {
        if (is_array($config)) {
            $config = AgentConfig::fromArray($config);
        }

        return new self($config);
    }

    public function chat(string|array $message, array $options = []): Response
    {
        $normalizedMessage = $this->normalizeMessage($message);

        if ($this->currentSession === null) {
            $this->currentSession = $this->sessionManager->create();
        }

        if (count($this->currentSession->getMessages()) === 0 && $this->config->systemPrompt !== null) {
            $this->currentSession->addMessage([
                'role' => 'system',
                'content' => $this->config->systemPrompt
            ]);
        }

        $this->currentSession->addMessage([
            'role' => 'user',
            'content' => $normalizedMessage
        ]);

        $response = $this->executeReActLoop($this->currentSession->getMessages(), $options);

        $this->currentSession->addMessage([
            'role' => 'assistant',
            'content' => $response->content
        ]);

        $this->sessionManager->save($this->currentSession);

        return $response;
    }

    public function registerTool(
        string $name,
        string $description,
        array $parameters,
        callable $handler
    ): void {
        $tool = new Tool($name, $description, $parameters, $handler);
        $this->toolRegistry->register($tool);
    }

    public function createSession(?string $id = null): Session
    {
        return $this->sessionManager->create($id);
    }

    public function session(string $id): Session
    {
        return $this->sessionManager->get($id);
    }

    public function getConfig(): AgentConfig
    {
        return $this->config;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setTelemetry(TelemetryInterface $telemetry): void
    {
        $this->telemetry = $telemetry;
    }

    public function setSecurityPolicy(SecurityPolicy $securityPolicy): void
    {
        $this->securityPolicy = $securityPolicy;
    }

    public function hasTool(string $name): bool
    {
        return $this->toolRegistry->has($name);
    }

    public function getTool(string $name): Tool
    {
        return $this->toolRegistry->get($name);
    }

    public function callTool(string $name, array $arguments): mixed
    {
        return $this->toolRegistry->call($name, $arguments);
    }

    private function normalizeMessage(string|array $message): string|array
    {
        if (is_string($message)) {
            return $message;
        }

        return $message;
    }

    private function executeReActLoop(array $messages, array $options): Response
    {
        $iteration = 0;
        $totalUsage = null;

        while ($iteration < $this->config->maxIterations) {
            $iteration++;

            $this->logger->info("ReAct iteration {$iteration} started");
            $this->telemetry->recordIteration($iteration);

            try {
                $llmResponse = $this->llmProvider->chat([
                    'messages' => $messages,
                    'tools' => $this->toolRegistry->getOpenAiTools(),
                    'tool_choice' => $options['tool_choice'] ?? 'auto',
                    ...$options
                ]);
            } catch (RateLimitException $e) {
                $this->logger->error('LLM rate limit exceeded', ['error' => $e->getMessage()]);
                $this->telemetry->recordError('rate_limit', $e->getMessage());

                return new Response(
                    content: 'Rate limit exceeded. Please retry after a short wait.',
                    role: 'assistant',
                    finishReason: 'rate_limit',
                    usage: $totalUsage ?? new Usage(0, 0, 0),
                    iterations: $iteration,
                    metadata: $e->details ?? null
                );
            }

            $totalUsage = $totalUsage === null
                ? $llmResponse->usage
                : $totalUsage->add($llmResponse->usage);

            $messages[] = $llmResponse->message;

            if (!empty($llmResponse->message['tool_calls'])) {
                $toolResults = $this->handleToolCalls($llmResponse->message['tool_calls']);
                $messages = array_merge($messages, $toolResults);
                continue;
            }

            return new Response(
                content: $llmResponse->message['content'] ?? '',
                role: 'assistant',
                finishReason: $llmResponse->finishReason,
                usage: $totalUsage,
                iterations: $iteration
            );
        }

        throw new MaxIterationsException("Reached maximum iterations: {$this->config->maxIterations}");
    }

    private function handleToolCalls(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);

            $this->logger->info("Calling tool: {$toolName}", ['arguments' => $arguments]);

            try {
                $this->securityPolicy->validateToolCall($toolName, $arguments);
                $result = $this->toolRegistry->call($toolName, $arguments);
                $resultContent = is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE);

                $this->logger->info("Tool call succeeded", ['tool' => $toolName]);
                $this->telemetry->recordToolCall($toolName, 0.0, true);
            } catch (\Exception $e) {
                $this->logger->error("Tool call failed", [
                    'tool' => $toolName,
                    'error' => $e->getMessage()
                ]);
                $this->telemetry->recordError('tool_call', $e->getMessage(), ['tool' => $toolName]);
                $this->telemetry->recordToolCall($toolName, 0.0, false);

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

    private function createDefaultLogger(): LoggerInterface
    {
        // 使用配置的日志工厂创建日志记录器
        return $this->config->loggerFactory->createLogger($this->config->loggerConfig);
    }
}
