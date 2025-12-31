<?php

declare(strict_types=1);

namespace PhpAgent\Llm;

interface LlmProviderInterface
{
    public function chat(array $request): LlmResponse;
    
    public function stream(array $request, callable $callback): void;
    
    public function supportsVision(): bool;
    
    public function supportsFunctionCalling(): bool;
    
    public function supportsJsonMode(): bool;
}
