<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

use Hyperf\Contract\StdoutLoggerInterface;

/**
 * Simple logger implementation that uses PHP's error_log() function.
 * 
 * This logger implements Hyperf's StdoutLoggerInterface without requiring
 * Symfony Console, making it suitable for OpenSwoole environments.
 * 
 * All log messages are prefixed with their log level and written to the
 * PHP error log using error_log().
 */
class SwooleErrorLogLogger implements StdoutLoggerInterface
{
    /**
     * System is unusable.
     *
     * @param array<string, mixed> $context
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        error_log("[EMERGENCY] " . (string) $message);
    }

    /**
     * Action must be taken immediately.
     *
     * @param array<string, mixed> $context
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        error_log("[ALERT] " . (string) $message);
    }

    /**
     * Critical conditions.
     *
     * @param array<string, mixed> $context
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        error_log("[CRITICAL] " . (string) $message);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param array<string, mixed> $context
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        error_log("[ERROR] " . (string) $message);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param array<string, mixed> $context
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        error_log("[WARNING] " . (string) $message);
    }

    /**
     * Normal but significant events.
     *
     * @param array<string, mixed> $context
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        error_log("[NOTICE] " . (string) $message);
    }

    /**
     * Interesting events.
     *
     * @param array<string, mixed> $context
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        error_log("[INFO] " . (string) $message);
    }

    /**
     * Detailed debug information.
     *
     * @param array<string, mixed> $context
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        error_log("[DEBUG] " . (string) $message);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $levelStr = is_string($level) ? $level : 'UNKNOWN';
        error_log("[" . $levelStr . "] " . (string) $message);
    }

    /**
     * Handle and log an exception with context
     * 
     * Provides a standardized way to log exceptions with full details including
     * exception class, message, code, file, and line number. This method is
     * designed specifically for SwooleConnection exception handling.
     * 
     * @param \Throwable $e The exception to handle
     * @param string $context Additional context description (e.g., "Error releasing connection in resetInstance")
     * @param string $logLevel Log level: 'error', 'critical', 'warning' (default: 'error')
     * @param array<string, mixed> $contextData Additional context data (e.g., ['pool' => 'default', 'worker_pid' => 123])
     * @return void
     */
    public function handleException(\Throwable $e, string $context = '', string $logLevel = 'error', array $contextData = []): void
    {
        $contextStr = $context !== '' ? "$context: " : '';
        
        // Format context data if provided (sanitize to remove sensitive data)
        $contextDataStr = '';
        if (!empty($contextData)) {
            // Sanitize context data before JSON encoding
            $sanitizedContext = $contextData;
            if (isset($sanitizedContext['password'])) {
                $sanitizedContext['password'] = '***';
            }
            $contextDataStr = ' [Context: ' . json_encode($sanitizedContext) . ']';
        }
        
        // Sanitize exception message to remove sensitive data
        $sanitizedMessage = \Gemvc\Database\Connection\OpenSwoole\SwooleConnectionSecurity::sanitizeErrorMessage(
            $e->getMessage()
        );
        
        $message = sprintf(
            '%s%s (%d): %s [File: %s:%d]%s',
            $contextStr,
            get_class($e),
            $e->getCode(),
            $sanitizedMessage,
            $e->getFile(),
            $e->getLine(),
            $contextDataStr
        );
        
        match($logLevel) {
            'critical' => $this->critical($message),
            'warning' => $this->warning($message),
            default => $this->error($message),
        };
    }

    /**
     * Handle and log a warning message with context
     * 
     * Provides a standardized way to log warnings with optional context.
     * This method is designed specifically for SwooleConnection warning handling.
     * 
     * @param string $message The warning message to log
     * @param string $context Additional context description (optional)
     * @return void
     */
    public function handleWarning(string $message, string $context = ''): void
    {
        $logMessage = $context !== '' ? "$context: $message" : $message;
        $this->warning($logMessage);
    }

    /**
     * Handle exception by logging it and then throwing a wrapped RuntimeException
     * 
     * This method logs the exception with full details and then throws a new
     * RuntimeException that wraps the original exception. Useful for initialization
     * failures where we want both logging and exception propagation.
     * 
     * @param \Throwable $e The exception to handle
     * @param string $component The component name that failed (e.g., "container", "event dispatcher")
     * @param string $context Additional context description (optional)
     * @return never
     * @throws \RuntimeException Always throws a wrapped RuntimeException
     */
    public function logAndThrowException(\Throwable $e, string $component, string $context = ''): never
    {
        $logContext = $context !== '' ? "$context: " : '';
        $this->handleException($e, $logContext . "Failed to initialize $component");
        
        throw new \RuntimeException(
            "Failed to initialize $component: " . $e->getMessage(),
            0,
            $e
        );
    }
}

