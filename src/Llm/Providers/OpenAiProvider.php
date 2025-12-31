<?php

declare(strict_types=1);

namespace PhpAgent\Llm\Providers;

use PhpAgent\Llm\LlmProviderInterface;
use PhpAgent\Llm\LlmResponse;
use PhpAgent\Llm\Usage;
use PhpAgent\Exception\NetworkException;
use PhpAgent\Exception\ApiException;
use PhpAgent\Exception\RateLimitException;

class OpenAiProvider implements LlmProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeout;
    private int $maxRetries;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->baseUrl = $config['base_url'] ?? 'https://api.openai.com/v1';
        $this->model = $config['model'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->maxRetries = $config['max_retries'] ?? 3;
    }

    public function chat(array $request): LlmResponse
    {
        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $payload = [
            'model' => $request['model'] ?? $this->model,
            'messages' => $request['messages'],
            'temperature' => $request['temperature'] ?? 0.7,
            'max_tokens' => $request['max_tokens'] ?? null,
            'tools' => $request['tools'] ?? null,
            'tool_choice' => $request['tool_choice'] ?? null,
            'response_format' => $request['response_format'] ?? null,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null);

        $response = $this->sendRequestWithRetry($url, $payload);

        return $this->parseResponse($response);
    }

    public function stream(array $request, callable $callback): void
    {
        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $payload = [
            'model' => $request['model'] ?? $this->model,
            'messages' => $request['messages'],
            'temperature' => $request['temperature'] ?? 0.7,
            'max_tokens' => $request['max_tokens'] ?? null,
            'tools' => $request['tools'] ?? null,
            'tool_choice' => $request['tool_choice'] ?? null,
            'response_format' => $request['response_format'] ?? null,
            'stream' => true,
        ];

        $payload = array_filter($payload, fn($v) => $v !== null);

        $this->sendStreamRequest($url, $payload, $callback);
    }

    public function supportsVision(): bool
    {
        return in_array($this->model, ['gpt-4-vision-preview', 'gpt-4o', 'gpt-4o-mini'], true);
    }

    public function supportsFunctionCalling(): bool
    {
        return true;
    }

    public function supportsJsonMode(): bool
    {
        return true;
    }

    private function sendStreamRequest(string $url, array $payload, callable $callback): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: text/event-stream'
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($callback) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || stripos($line, 'data:') !== 0) {
                        continue;
                    }
                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') {
                        return strlen($data);
                    }
                    $decoded = json_decode($json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $callback($decoded);
                    }
                }
                return strlen($data);
            },
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->closeHandle($ch);

        if ($error) {
            throw new NetworkException("cURL error: {$error}");
        }

        if ($httpCode !== 200 && $httpCode !== 0) {
            throw new ApiException("Stream HTTP {$httpCode}", $httpCode, ['response' => $response]);
        }
    }

    private function sendRequestWithRetry(string $url, array $payload): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                return $this->sendRequest($url, $payload);
            } catch (RateLimitException $e) {
                $lastException = $e;
                $retryAfter = 0;
                // 优先使用服务端提供的 retry_after，避免盲目重试
                if (is_array($e->details) && isset($e->details['retry_after'])) {
                    $retryAfter = (int) $e->details['retry_after'];
                }
                $waitTime = $retryAfter > 0 ? $retryAfter : min(2 ** $attempt, 60);
                sleep($waitTime);
            } catch (NetworkException $e) {
                $lastException = $e;
                sleep(1);
            } catch (ApiException $e) {
                throw $e;
            }
        }

        // 用原始异常抛出，方便上层识别速率限制或网络错误
        if ($lastException instanceof \Throwable) {
            throw $lastException;
        }

        throw new \RuntimeException("Max retries reached");
    }

    private function sendRequest(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $this->closeHandle($ch);

        if ($error) {
            throw new NetworkException("cURL error: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode === 429) {
            $retryAfter = $data['error']['retry_after'] ?? $data['retry_after'] ?? 0;
            throw new RateLimitException(
                "Rate limit exceeded",
                429,
                ['retry_after' => $retryAfter ?: null]
            );
        }

        if ($httpCode !== 200) {
            $message = $data['error']['message'] ?? 'Unknown error';
            throw new ApiException($message, $httpCode, $data);
        }

        return $data;
    }

    private function parseResponse(array $response): LlmResponse
    {
        $message = $response['choices'][0]['message'];
        $finishReason = $response['choices'][0]['finish_reason'];
        $usage = new Usage(
            promptTokens: $response['usage']['prompt_tokens'],
            completionTokens: $response['usage']['completion_tokens'],
            totalTokens: $response['usage']['total_tokens']
        );

        return new LlmResponse($message, $finishReason, $usage, $response['model']);
    }

    /**
     * CurlHandle will be closed automatically since PHP 8.0; avoid deprecated curl_close() in 8.5.
     */
    private function closeHandle($handle): void
    {
        if (PHP_VERSION_ID < 80000) {
            curl_close($handle);
            return;
        }

        // break reference so GC can release the handle without triggering deprecation warnings
        $handle = null;
    }

    protected function getModel(): string
    {
        return $this->model;
    }
}
