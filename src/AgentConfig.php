<?php

declare(strict_types=1);

namespace PhpAgent;

use PhpAgent\Exception\ConfigException;
use PhpAgent\Llm\LlmConfig;
use PhpAgent\Contract\LoggerFactoryInterface;
use PhpAgent\Util\DefaultLoggerFactory;

class AgentConfig
{
    public function __construct(
        public readonly LlmConfig $llm,
        public readonly int $maxIterations = 10,
        public readonly ?string $systemPrompt = null,
        public readonly int $timeout = 30,
        public readonly int $maxRetries = 3,
        public readonly LoggerFactoryInterface $loggerFactory = new DefaultLoggerFactory(),
        public readonly array $loggerConfig = []
    ) {
        $this->validate();
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['llm'])) {
            throw new ConfigException('LLM configuration is required');
        }

        return new self(
            llm: LlmConfig::fromArray($config['llm']),
            maxIterations: $config['max_iterations'] ?? 10,
            systemPrompt: $config['system_prompt'] ?? null,
            timeout: $config['timeout'] ?? 30,
            maxRetries: $config['max_retries'] ?? 3,
            loggerFactory: $config['logger_factory'] ?? new DefaultLoggerFactory(),
            loggerConfig: $config['logger_config'] ?? []
        );
    }

    private function validate(): void
    {
        if ($this->maxIterations < 1) {
            throw new ConfigException('max_iterations must be >= 1');
        }

        if ($this->timeout < 1) {
            throw new ConfigException('timeout must be >= 1');
        }

        if ($this->maxRetries < 0) {
            throw new ConfigException('max_retries must be >= 0');
        }
    }
}
