<?php

declare(strict_types=1);

namespace PhpAgent\Llm\Providers;

use PhpAgent\Exception\ApiException;
use PhpAgent\Exception\ConfigException;
use PhpAgent\Exception\NetworkException;
use PhpAgent\Exception\RateLimitException;
use PhpAgent\Llm\LlmProviderInterface;
use PhpAgent\Llm\LlmResponse;
use PhpAgent\Llm\Usage;

/**
 * Provider for Zhipu GLM models (e.g., GLM-4.6V), OpenAI-compatible API surface.
 */
class ZaiProvider extends OpenAiProvider
{
    public function __construct(array $config)
    {
        // 传入 glm 默认值，其他参数可被调用侧覆盖
        $config = [
            'api_key' => $config['api_key'] ?? '',
            'model' => $config['model'] ?? 'glm-4.6v',
            'base_url' => $config['base_url'] ?? 'https://open.bigmodel.cn/api/paas/v4',
            'timeout' => $config['timeout'] ?? 30,
            'max_retries' => $config['max_retries'] ?? 3,
        ];
        parent::__construct($config);
    }

    /**
     * Zhipu 视觉模型匹配：默认 glm-4.6v，或模型名包含“4.6v”/“vision”（大小写不敏感）。
     */
    public function supportsVision(): bool
    {
        $model = strtolower($this->getModel());
        return str_contains($model, '4.6v') || str_contains($model, 'vision');
    }
}