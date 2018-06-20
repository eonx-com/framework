<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions\Stubs;

use EoneoPay\Externals\Logger\Interfaces\LoggerInterface;
use Exception;

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
     * Get log level
     *
     * @return string|null
     */
    public function getLogLevel(): ?string
    {
        return $this->logLevel;
    }

    /**
     * Adds a debug message to the log
     *
     * @param string $message The log message
     * @param mixed[] $context Additional log context
     *
     * @return bool
     */
    public function debug(string $message, ?array $context = null): bool
    {
        return $this->write('debug', $message, $context ?? []);
    }

    /**
     * Adds an error message to the log
     *
     * @param string $message The log message
     * @param mixed[] $context Additional log context
     *
     * @return bool
     */
    public function error(string $message, ?array $context = null): bool
    {
        return $this->write('error', $message, $context ?? []);
    }

    /**
     * Record a caught exception with backtrace
     *
     * @param \Exception $exception The exception to handle
     * @param string|null $level The log level for this exception
     *
     * @return bool
     */
    public function exception(Exception $exception, ?string $level = null): bool
    {
        return $this->write(
            $level ?? 'notice',
            \sprintf('Exception caught: %s', $exception->getMessage()),
            $exception->getTrace()
        );
    }

    /**
     * Adds an informational message to the log
     *
     * @param string $message The log message
     * @param mixed[] $context Additional log context
     *
     * @return bool
     */
    public function info(string $message, ?array $context = null): bool
    {
        return $this->write('info', $message, $context ?? []);
    }

    /**
     * Adds a notice to the log
     *
     * @param string $message The log message
     * @param mixed[] $context Additional log context
     *
     * @return bool
     */
    public function notice(string $message, ?array $context = null): bool
    {
        return $this->write('notice', $message, $context ?? []);
    }

    /**
     * Adds a warning to the log
     *
     * @param string $message The log message
     * @param mixed[] $context Additional log context
     *
     * @return bool
     */
    public function warning(string $message, ?array $context = null): bool
    {
        return $this->write('warning', $message, $context ?? []);
    }

    /**
     * 'Write' to log file
     *
     * @param string $type The log type
     * @param string $message The log message
     * @param mixed[] $context Additional log context
     *
     * @return bool
     */
    private function write(string $type, string $message, ?array $context = null): bool
    {
        $this->context = $context;
        $this->logLevel = $type;
        $this->message = $message;

        return true;
    }
}
