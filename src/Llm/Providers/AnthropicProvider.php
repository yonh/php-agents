<?php

declare(strict_types=1);

namespace PhpAgent\Llm\Providers;

use PhpAgent\Llm\LlmProviderInterface;
use PhpAgent\Llm\LlmResponse;
use PhpAgent\Llm\Usage;
use PhpAgent\Exception\ConfigException;

/**
 * Stub provider for Anthropic. Currently throws for all methods.
 */
class AnthropicProvider implements LlmProviderInterface
{
    public function __construct(private array $config)
    {
    }

    public function chat(array $request): LlmResponse
    {
        throw new ConfigException('Anthropic provider not implemented yet');
    }

    public function stream(array $request, callable $callback): void
    {
        throw new ConfigException('Anthropic provider stream not implemented yet');
    }

    public function supportsVision(): bool
    {
        return false;
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
