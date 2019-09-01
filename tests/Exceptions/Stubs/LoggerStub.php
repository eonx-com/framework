<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions\Stubs;

use EoneoPay\Externals\Logger\Interfaces\LoggerInterface;
use Throwable;

class LoggerStub implements LoggerInterface
{
    /**
     * Log context
     *
     * @var mixed[]|null
     */
    private $context;

    /**
     * Log level
     *
     * @var string
     */
    private $logLevel;

    /**
     * Log message
     *
     * @var string
     */
    private $message;

    /**
     * {@inheritdoc}
     */
    public function alert($message, ?array $context = null): void
    {
        $this->log('alert', $message, $context ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function critical($message, ?array $context = null): void
    {
        $this->log('critical', $message, $context ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, ?array $context = null): void
    {
        $this->log('debug', $message, $context ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function emergency($message, ?array $context = null): void
    {
        $this->log('emergency', $message, $context ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, ?array $context = null): void
    {
        $this->log('error', $message, $context ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function exception(Throwable $exception, ?string $level = null, ?array $context = null): void
    {
        $this->log(
            $level ?? 'notice',
            \sprintf('Exception caught: %s', $exception->getMessage()),
            $exception->getTrace()
        );
    }

    /**
     * Get log level
     *
     * @return string|null
     */
    public function getLogLevel(): ?string
    {
        return $this->logLevel;
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, ?array $context = null): void
    {
        $this->log('info', $message, $context ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, ?array $context = null): void
    {
        $this->context = $context;
        $this->logLevel = $level;
        $this->message = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function notice($message, ?array $context = null): void
    {
        $this->log('notice', $message, $context ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, ?array $context = null): void
    {
        $this->log('warning', $message, $context ?? []);
    }
}
