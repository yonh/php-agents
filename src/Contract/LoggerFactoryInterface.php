<?php

declare(strict_types=1);

namespace PhpAgent\Contract;

interface LoggerFactoryInterface
{
    /**
     * 创建日志记录器实例
     *
     * @param array $config 日志配置选项
     * @return LoggerInterface
     */
    public function createLogger(array $config = []): LoggerInterface;
}