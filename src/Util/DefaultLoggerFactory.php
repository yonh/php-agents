<?php

declare(strict_types=1);

namespace PhpAgent\Util;

use PhpAgent\Contract\LoggerFactoryInterface;
use PhpAgent\Contract\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\NullLogger as PsrNullLogger;

class DefaultLoggerFactory implements LoggerFactoryInterface
{
    public function createLogger(array $config = []): LoggerInterface
    {
        // 如果 Monolog 可用，创建文件日志记录器
        if (class_exists(\Monolog\Logger::class) && class_exists(\Monolog\Handler\StreamHandler::class)) {
            $logDir = $config['log_dir'] ?? 'logs';
            $logFile = $config['log_file'] ?? 'agent.log';
            $logLevel = $config['log_level'] ?? \Monolog\Logger::INFO;
            
            // 确保日志目录存在
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            /** @var PsrLoggerInterface $psrLogger */
            $psrLogger = new \Monolog\Logger('php-agent', [
                new \Monolog\Handler\StreamHandler($logDir . '/' . $logFile, $logLevel),
            ]);
            
            return new PsrLoggerAdapter($psrLogger);
        }

        // 回退到空日志记录器
        return new NullLogger();
    }
}