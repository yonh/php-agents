<?php

declare(strict_types=1);

namespace PhpAgent\Llm;

use PhpAgent\Exception\ConfigException;

class LlmConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly string $apiKey,
        public readonly string $model,
        public readonly ?string $baseUrl = null,
        public readonly int $timeout = 30
    ) {
        $this->validate();
    }

    public static function fromArray(array $config): self
    {
        if (!isset($config['provider'])) {
            throw new ConfigException('LLM provider is required');
        }
        
        if (!isset($config['api_key'])) {
            throw new ConfigException('LLM api_key is required');
        }
        
        if (!isset($config['model'])) {
            throw new ConfigException('LLM model is required');
        }

        return new self(
            provider: $config['provider'],
            apiKey: $config['api_key'],
            model: $config['model'],
            baseUrl: $config['base_url'] ?? null,
            timeout: $config['timeout'] ?? 30
        );
    }

    private function validate(): void
    {
        $supportedProviders = ['openai', 'zai'];

        if (!in_array($this->provider, $supportedProviders, true)) {
            throw new ConfigException(
                "Unsupported provider: {$this->provider}. " .
                "Supported providers: " . implode(', ', $supportedProviders)
            );
        }

        if (empty($this->apiKey)) {
            throw new ConfigException('api_key cannot be empty');
        }

        if (empty($this->model)) {
            throw new ConfigException('model cannot be empty');
        }

        if ($this->timeout < 1) {
            throw new ConfigException('timeout must be >= 1');
        }
    }
}

