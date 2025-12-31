<?php

declare(strict_types=1);

namespace PhpAgent\Llm;

use PhpAgent\Llm\Providers\OpenAiProvider;
use PhpAgent\Llm\Providers\ZaiProvider;
use PhpAgent\Exception\ConfigException;

class LlmProviderFactory
{
    public static function create(string $provider, array $config): LlmProviderInterface
    {
        return match($provider) {
            'openai' => new OpenAiProvider($config),
            'zai' => new ZaiProvider($config),
            default => throw new ConfigException("Unsupported provider: {$provider}. Supported providers: openai, zai")
        };
    }
}