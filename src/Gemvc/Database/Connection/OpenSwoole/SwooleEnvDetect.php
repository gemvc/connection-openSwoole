<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

/**
 * Environment detection and configuration utility for OpenSwoole environments.
 * 
 * This class centralizes all environment detection logic and environment variable
 * reading, making the code more testable and maintainable.
 * 
 * **Features:**
 * - Detects execution context (OpenSwoole server, CLI, web server)
 * - Reads and validates environment variables with type safety
 * - Provides database configuration helpers
 * - Environment-aware host detection
 */
class SwooleEnvDetect
{
    /**
     * Check if running in OpenSwoole server context.
     * 
     * OpenSwoole runs in CLI mode but we need to detect if it's the web server
     * by checking for SWOOLE_BASE constant or OpenSwoole\Server class.
     * 
     * @return bool True if running in OpenSwoole server context
     */
    public function isOpenSwooleServer(): bool
    {
        return PHP_SAPI === 'cli' && (defined('SWOOLE_BASE') || class_exists('\OpenSwoole\Server'));
    }

    /**
     * Check if running in true CLI context (not OpenSwoole server).
     * 
     * @return bool True if running in CLI but not OpenSwoole server
     */
    public function isCliContext(): bool
    {
        return PHP_SAPI === 'cli' && !$this->isOpenSwooleServer();
    }

    /**
     * Check if running in web server context (non-CLI).
     * 
     * @return bool True if running in web server context
     */
    public function isWebServerContext(): bool
    {
        return PHP_SAPI !== 'cli';
    }

    /**
     * Get the execution context type.
     * 
     * @return string One of: 'openswoole', 'cli', 'webserver'
     */
    public function getExecutionContext(): string
    {
        if ($this->isOpenSwooleServer()) {
            return 'openswoole';
        }
        if ($this->isCliContext()) {
            return 'cli';
        }
        return 'webserver';
    }

    /**
     * Determines the correct database host based on the execution context.
     * 
     * Logic:
     * 1. If running in OpenSwoole server context:
     *    - Uses DB_HOST environment variable (defaults to 'db')
     * 2. If running in true CLI context:
     *    - Uses DB_HOST_CLI_DEV environment variable (defaults to 'localhost')
     * 3. If running in web server context:
     *    - Uses DB_HOST environment variable (defaults to 'db')
     * 
     * @return string The database host
     */
    public function getDbHost(): string
    {
        if ($this->isOpenSwooleServer()) {
            // Running in OpenSwoole server - use container host
            return $this->getStringEnv('DB_HOST', 'db');
        }
        
        if ($this->isCliContext()) {
            // True CLI context - use localhost
            return $this->getStringEnv('DB_HOST_CLI_DEV', 'localhost');
        }
        
        // In any other context (like web server), use the container host
        return $this->getStringEnv('DB_HOST', 'db');
    }

    /**
     * Check if running in development environment.
     * 
     * @return bool True if APP_ENV is set to 'dev'
     */
    public function isDevEnvironment(): bool
    {
        return ($this->getStringEnv('APP_ENV', '') === 'dev');
    }

    /**
     * Get a string environment variable with default value.
     * 
     * @param string $key The environment variable key
     * @param string $default The default value if not set or invalid
     * @return string The environment variable value or default
     */
    public function getStringEnv(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /**
     * Get a numeric environment variable as integer with default value.
     * 
     * @param string $key The environment variable key
     * @param int $default The default value if not set or invalid
     * @return int The environment variable value as integer or default
     */
    public function getIntEnv(string $key, int $default = 0): int
    {
        $value = $_ENV[$key] ?? (string) $default;
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get a numeric environment variable as float with default value.
     * 
     * @param string $key The environment variable key
     * @param float $default The default value if not set or invalid
     * @return float The environment variable value as float or default
     */
    public function getFloatEnv(string $key, float $default = 0.0): float
    {
        $value = $_ENV[$key] ?? (string) $default;
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * Get database driver from environment.
     * 
     * @return string The database driver (default: 'mysql')
     */
    public function getDbDriver(): string
    {
        return $this->getStringEnv('DB_DRIVER', 'mysql');
    }

    /**
     * Get database port from environment.
     * 
     * @return int The database port (default: 3306)
     */
    public function getDbPort(): int
    {
        return $this->getIntEnv('DB_PORT', 3306);
    }

    /**
     * Get database name from environment.
     * 
     * @return string The database name (default: 'gemvc_db')
     */
    public function getDbName(): string
    {
        return $this->getStringEnv('DB_NAME', 'gemvc_db');
    }

    /**
     * Get database username from environment.
     * 
     * @return string The database username (default: 'root')
     */
    public function getDbUser(): string
    {
        return $this->getStringEnv('DB_USER', 'root');
    }

    /**
     * Get database password from environment.
     * 
     * @return string The database password (default: '')
     */
    public function getDbPassword(): string
    {
        return $this->getStringEnv('DB_PASSWORD', '');
    }

    /**
     * Get database charset from environment.
     * 
     * @return string The database charset (default: 'utf8mb4')
     */
    public function getDbCharset(): string
    {
        return $this->getStringEnv('DB_CHARSET', 'utf8mb4');
    }

    /**
     * Get database collation from environment.
     * 
     * @return string The database collation (default: 'utf8mb4_unicode_ci')
     */
    public function getDbCollation(): string
    {
        return $this->getStringEnv('DB_COLLATION', 'utf8mb4_unicode_ci');
    }

    /**
     * Get minimum connection pool size from environment.
     * 
     * @return int Minimum connections (default: 8)
     */
    public function getMinConnectionPool(): int
    {
        return $this->getIntEnv('MIN_DB_CONNECTION_POOL', 8);
    }

    /**
     * Get maximum connection pool size from environment.
     * 
     * @return int Maximum connections (default: 16)
     */
    public function getMaxConnectionPool(): int
    {
        return $this->getIntEnv('MAX_DB_CONNECTION_POOL', 16);
    }

    /**
     * Get connection timeout from environment.
     * 
     * @return float Connection timeout in seconds (default: 10.0)
     */
    public function getConnectionTimeout(): float
    {
        return $this->getFloatEnv('DB_CONNECTION_TIME_OUT', 10.0);
    }

    /**
     * Get wait timeout from environment.
     * 
     * @return float Wait timeout in seconds (default: 2.0)
     */
    public function getWaitTimeout(): float
    {
        return $this->getFloatEnv('DB_CONNECTION_EXPIER_TIME', 2.0);
    }

    /**
     * Get heartbeat interval from environment.
     * 
     * @return int Heartbeat interval (default: -1, disabled)
     */
    public function getHeartbeat(): int
    {
        return $this->getIntEnv('DB_HEARTBEAT', -1);
    }

    /**
     * Get maximum idle time from environment.
     * 
     * @return float Maximum idle time in seconds (default: 60.0)
     */
    public function getMaxIdleTime(): float
    {
        return $this->getFloatEnv('DB_CONNECTION_MAX_AGE', 60.0);
    }

    /**
     * Build complete database configuration array.
     * 
     * Reads all database-related environment variables and returns
     * a configuration array suitable for Hyperf's database connection pool.
     * 
     * @return array<string, mixed> The database configuration array
     */
    public function getDatabaseConfig(): array
    {
        return [
            'default' => [
                'driver' => $this->getDbDriver(),
                'host' => $this->getDbHost(),
                'port' => $this->getDbPort(),
                'database' => $this->getDbName(),
                'username' => $this->getDbUser(),
                'password' => $this->getDbPassword(),
                'charset' => $this->getDbCharset(),
                'collation' => $this->getDbCollation(),
                'pool' => [
                    // PERFORMANCE: Optimized defaults for production workloads
                    'min_connections' => $this->getMinConnectionPool(),
                    'max_connections' => $this->getMaxConnectionPool(),
                    'connect_timeout' => $this->getConnectionTimeout(),
                    'wait_timeout' => $this->getWaitTimeout(),
                    // PERFORMANCE: Disabled heartbeat for better performance (pool handles health)
                    'heartbeat' => $this->getHeartbeat(),
                    'max_idle_time' => $this->getMaxIdleTime(),
                ],
            ],
        ];
    }
}

