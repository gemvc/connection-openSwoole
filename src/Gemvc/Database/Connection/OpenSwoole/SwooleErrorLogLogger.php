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
}

