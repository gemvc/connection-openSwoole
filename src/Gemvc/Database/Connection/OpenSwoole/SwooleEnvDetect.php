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
 * - All values are pre-computed as typed properties for easy access
 * 
 * **Usage:**
 * ```php
 * $env = new SwooleEnvDetect();
 * // Access properties directly:
 * $host = $env->dbHost;
 * $driver = $env->dbDriver;
 * $config = $env->databaseConfig;
 * ```
 */
class SwooleEnvDetect
{
    // Context detection properties
    /** @var bool True if running in OpenSwoole server context */
    public readonly bool $isOpenSwooleServer;
    
    /** @var bool True if running in CLI but not OpenSwoole server */
    public readonly bool $isCliContext;
    
    /** @var bool True if running in web server context (non-CLI) */
    public readonly bool $isWebServerContext;
    
    /** @var string Execution context: 'openswoole', 'cli', or 'webserver' */
    public readonly string $executionContext;
    
    // Environment properties
    /** @var bool True if running in development environment */
    public readonly bool $isDevEnvironment;
    
    // Database configuration properties
    /** @var string Database host (context-aware) */
    public readonly string $dbHost;
    
    /** @var string Database driver (default: 'mysql') */
    public readonly string $dbDriver;
    
    /** @var int Database port (default: 3306) */
    public readonly int $dbPort;
    
    /** @var string Database name (default: 'gemvc_db') */
    public readonly string $dbName;
    
    /** @var string Database username (default: 'root') */
    public readonly string $dbUser;
    
    /** @var string Database password (default: '') */
    public readonly string $dbPassword;
    
    /** @var string Database charset (default: 'utf8mb4') */
    public readonly string $dbCharset;
    
    /** @var string Database collation (default: 'utf8mb4_unicode_ci') */
    public readonly string $dbCollation;
    
    // Connection pool configuration properties
    /** @var int Minimum connection pool size (default: 8) */
    public readonly int $minConnectionPool;
    
    /** @var int Maximum connection pool size (default: 16) */
    public readonly int $maxConnectionPool;
    
    /** @var float Connection timeout in seconds (default: 10.0) */
    public readonly float $connectionTimeout;
    
    /** @var float Wait timeout in seconds (default: 2.0) */
    public readonly float $waitTimeout;
    
    /** @var int Heartbeat interval (default: -1, disabled) */
    public readonly int $heartbeat;
    
    /** @var float Maximum idle time in seconds (default: 60.0) */
    public readonly float $maxIdleTime;
    
    // Complete database configuration
    /** @var array<string, mixed> Complete database configuration array for Hyperf */
    public readonly array $databaseConfig;

    /**
     * Constructor - Initializes all typed properties from environment variables.
     * 
     * All properties are computed once during construction for optimal performance.
     * Values are read from $_ENV with proper type validation and defaults.
     */
    public function __construct()
    {
        // Step 1: Compute context detection values (must be first, computed before assignment)
        $isOpenSwooleServer = $this->computeIsOpenSwooleServer();
        $isCliContext = $this->computeIsCliContext($isOpenSwooleServer);
        $isWebServerContext = $this->computeIsWebServerContext();
        
        // Step 2: Assign context properties (using readonly assignment)
        $this->isOpenSwooleServer = $isOpenSwooleServer;
        $this->isCliContext = $isCliContext;
        $this->isWebServerContext = $isWebServerContext;
        $this->executionContext = $this->computeExecutionContext($isOpenSwooleServer, $isCliContext);
        
        // Step 3: Initialize environment flags
        $this->isDevEnvironment = $this->computeIsDevEnvironment();
        
        // Step 4: Initialize database host (depends on context)
        $this->dbHost = $this->computeDbHost($isOpenSwooleServer, $isCliContext);
        
        // Step 5: Initialize database configuration properties
        $this->dbDriver = $this->getStringEnv('DB_DRIVER', 'mysql');
        $this->dbPort = $this->getIntEnv('DB_PORT', 3306);
        $this->dbName = $this->getStringEnv('DB_NAME', 'gemvc_db');
        $this->dbUser = $this->getStringEnv('DB_USER', 'root');
        $this->dbPassword = $this->getStringEnv('DB_PASSWORD', '');
        $this->dbCharset = $this->getStringEnv('DB_CHARSET', 'utf8mb4');
        $this->dbCollation = $this->getStringEnv('DB_COLLATION', 'utf8mb4_unicode_ci');
        
        // Step 6: Initialize connection pool configuration properties
        $this->minConnectionPool = $this->getIntEnv('MIN_DB_CONNECTION_POOL', 8);
        $this->maxConnectionPool = $this->getIntEnv('MAX_DB_CONNECTION_POOL', 16);
        $this->connectionTimeout = $this->getFloatEnv('DB_CONNECTION_TIME_OUT', 10.0);
        $this->waitTimeout = $this->getFloatEnv('DB_CONNECTION_EXPIER_TIME', 2.0);
        $this->heartbeat = $this->getIntEnv('DB_HEARTBEAT', -1);
        $this->maxIdleTime = $this->getFloatEnv('DB_CONNECTION_MAX_AGE', 60.0);
        
        // Step 7: Build complete database configuration array
        $this->databaseConfig = $this->buildDatabaseConfig();
    }

    /**
     * Check if running in OpenSwoole server context.
     * 
     * OpenSwoole runs in CLI mode but we need to detect if it's the web server
     * by checking for SWOOLE_BASE constant or OpenSwoole\Server class.
     * 
     * @return bool True if running in OpenSwoole server context
     */
    protected function computeIsOpenSwooleServer(): bool
    {
        return PHP_SAPI === 'cli' && (defined('SWOOLE_BASE') || class_exists('\OpenSwoole\Server'));
    }

    /**
     * Compute if running in true CLI context (not OpenSwoole server).
     * 
     * @param bool $isOpenSwooleServer Whether running in OpenSwoole server context
     * @return bool True if running in CLI but not OpenSwoole server
     */
    protected function computeIsCliContext(bool $isOpenSwooleServer): bool
    {
        return PHP_SAPI === 'cli' && !$isOpenSwooleServer;
    }

    /**
     * Compute if running in web server context (non-CLI).
     * 
     * @return bool True if running in web server context
     */
    protected function computeIsWebServerContext(): bool
    {
        return PHP_SAPI !== 'cli';
    }

    /**
     * Compute the execution context type.
     * 
     * @param bool $isOpenSwooleServer Whether running in OpenSwoole server context
     * @param bool $isCliContext Whether running in CLI context
     * @return string One of: 'openswoole', 'cli', 'webserver'
     */
    protected function computeExecutionContext(bool $isOpenSwooleServer, bool $isCliContext): string
    {
        if ($isOpenSwooleServer) {
            return 'openswoole';
        }
        if ($isCliContext) {
            return 'cli';
        }
        return 'webserver';
    }

    /**
     * Compute the correct database host based on the execution context.
     * 
     * Logic:
     * 1. If running in OpenSwoole server context:
     *    - Uses DB_HOST environment variable (defaults to 'db')
     * 2. If running in true CLI context:
     *    - Uses DB_HOST_CLI_DEV environment variable (defaults to 'localhost')
     * 3. If running in web server context:
     *    - Uses DB_HOST environment variable (defaults to 'db')
     * 
     * @param bool $isOpenSwooleServer Whether running in OpenSwoole server context
     * @param bool $isCliContext Whether running in CLI context
     * @return string The database host
     */
    protected function computeDbHost(bool $isOpenSwooleServer, bool $isCliContext): string
    {
        if ($isOpenSwooleServer) {
            // Running in OpenSwoole server - use container host
            return $this->getStringEnv('DB_HOST', 'db');
        }
        
        if ($isCliContext) {
            // True CLI context - use localhost
            return $this->getStringEnv('DB_HOST_CLI_DEV', 'localhost');
        }
        
        // In any other context (like web server), use the container host
        return $this->getStringEnv('DB_HOST', 'db');
    }

    /**
     * Compute if running in development environment.
     * 
     * @return bool True if APP_ENV is set to 'dev'
     */
    protected function computeIsDevEnvironment(): bool
    {
        return ($this->getStringEnv('APP_ENV', '') === 'dev');
    }

    /**
     * Build complete database configuration array.
     * 
     * Uses the pre-computed properties to build the configuration array.
     * 
     * @return array<string, mixed> The database configuration array
     */
    private function buildDatabaseConfig(): array
    {
        return [
            'default' => [
                'driver' => $this->dbDriver,
                'host' => $this->dbHost,
                'port' => $this->dbPort,
                'database' => $this->dbName,
                'username' => $this->dbUser,
                'password' => $this->dbPassword,
                'charset' => $this->dbCharset,
                'collation' => $this->dbCollation,
                'pool' => [
                    // PERFORMANCE: Optimized defaults for production workloads
                    'min_connections' => $this->minConnectionPool,
                    'max_connections' => $this->maxConnectionPool,
                    'connect_timeout' => $this->connectionTimeout,
                    'wait_timeout' => $this->waitTimeout,
                    // PERFORMANCE: Disabled heartbeat for better performance (pool handles health)
                    'heartbeat' => $this->heartbeat,
                    'max_idle_time' => $this->maxIdleTime,
                ],
            ],
        ];
    }

    /**
     * Get a string environment variable with default value.
     * 
     * @param string $key The environment variable key
     * @param string $default The default value if not set or invalid
     * @return string The environment variable value or default
     */
    protected function getStringEnv(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $default;
        if (!is_string($value)) {
            return $default;
        }
        
        // Sanitize environment variable value for security
        return SwooleConnectionSecurity::sanitizeEnvValue($value);
    }

    /**
     * Get a numeric environment variable as integer with default value.
     * 
     * @param string $key The environment variable key
     * @param int $default The default value if not set or invalid
     * @return int The environment variable value as integer or default
     */
    protected function getIntEnv(string $key, int $default = 0): int
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
    protected function getFloatEnv(string $key, float $default = 0.0): float
    {
        $value = $_ENV[$key] ?? (string) $default;
        return is_numeric($value) ? (float) $value : $default;
    }

    // ============================================================================
    // Backward compatibility methods - delegate to properties
    // ============================================================================

    /**
     * Check if running in OpenSwoole server context.
     * 
     * @return bool True if running in OpenSwoole server context
     * @deprecated Use property $isOpenSwooleServer instead
     */
    public function isOpenSwooleServer(): bool
    {
        return $this->isOpenSwooleServer;
    }

    /**
     * Check if running in true CLI context (not OpenSwoole server).
     * 
     * @return bool True if running in CLI but not OpenSwoole server
     * @deprecated Use property $isCliContext instead
     */
    public function isCliContext(): bool
    {
        return $this->isCliContext;
    }

    /**
     * Check if running in web server context (non-CLI).
     * 
     * @return bool True if running in web server context
     * @deprecated Use property $isWebServerContext instead
     */
    public function isWebServerContext(): bool
    {
        return $this->isWebServerContext;
    }

    /**
     * Get the execution context type.
     * 
     * @return string One of: 'openswoole', 'cli', 'webserver'
     * @deprecated Use property $executionContext instead
     */
    public function getExecutionContext(): string
    {
        return $this->executionContext;
    }

    /**
     * Determines the correct database host based on the execution context.
     * 
     * @return string The database host
     * @deprecated Use property $dbHost instead
     */
    public function getDbHost(): string
    {
        return $this->dbHost;
    }

    /**
     * Check if running in development environment.
     * 
     * @return bool True if APP_ENV is set to 'dev'
     * @deprecated Use property $isDevEnvironment instead
     */
    public function isDevEnvironment(): bool
    {
        return $this->isDevEnvironment;
    }

    /**
     * Get database driver from environment.
     * 
     * @return string The database driver (default: 'mysql')
     * @deprecated Use property $dbDriver instead
     */
    public function getDbDriver(): string
    {
        return $this->dbDriver;
    }

    /**
     * Get database port from environment.
     * 
     * @return int The database port (default: 3306)
     * @deprecated Use property $dbPort instead
     */
    public function getDbPort(): int
    {
        return $this->dbPort;
    }

    /**
     * Get database name from environment.
     * 
     * @return string The database name (default: 'gemvc_db')
     * @deprecated Use property $dbName instead
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * Get database username from environment.
     * 
     * @return string The database username (default: 'root')
     * @deprecated Use property $dbUser instead
     */
    public function getDbUser(): string
    {
        return $this->dbUser;
    }

    /**
     * Get database password from environment.
     * 
     * @return string The database password (default: '')
     * @deprecated Use property $dbPassword instead
     */
    public function getDbPassword(): string
    {
        return $this->dbPassword;
    }

    /**
     * Get database charset from environment.
     * 
     * @return string The database charset (default: 'utf8mb4')
     * @deprecated Use property $dbCharset instead
     */
    public function getDbCharset(): string
    {
        return $this->dbCharset;
    }

    /**
     * Get database collation from environment.
     * 
     * @return string The database collation (default: 'utf8mb4_unicode_ci')
     * @deprecated Use property $dbCollation instead
     */
    public function getDbCollation(): string
    {
        return $this->dbCollation;
    }

    /**
     * Get minimum connection pool size from environment.
     * 
     * @return int Minimum connections (default: 8)
     * @deprecated Use property $minConnectionPool instead
     */
    public function getMinConnectionPool(): int
    {
        return $this->minConnectionPool;
    }

    /**
     * Get maximum connection pool size from environment.
     * 
     * @return int Maximum connections (default: 16)
     * @deprecated Use property $maxConnectionPool instead
     */
    public function getMaxConnectionPool(): int
    {
        return $this->maxConnectionPool;
    }

    /**
     * Get connection timeout from environment.
     * 
     * @return float Connection timeout in seconds (default: 10.0)
     * @deprecated Use property $connectionTimeout instead
     */
    public function getConnectionTimeout(): float
    {
        return $this->connectionTimeout;
    }

    /**
     * Get wait timeout from environment.
     * 
     * @return float Wait timeout in seconds (default: 2.0)
     * @deprecated Use property $waitTimeout instead
     */
    public function getWaitTimeout(): float
    {
        return $this->waitTimeout;
    }

    /**
     * Get heartbeat interval from environment.
     * 
     * @return int Heartbeat interval (default: -1, disabled)
     * @deprecated Use property $heartbeat instead
     */
    public function getHeartbeat(): int
    {
        return $this->heartbeat;
    }

    /**
     * Get maximum idle time from environment.
     * 
     * @return float Maximum idle time in seconds (default: 60.0)
     * @deprecated Use property $maxIdleTime instead
     */
    public function getMaxIdleTime(): float
    {
        return $this->maxIdleTime;
    }

    /**
     * Build complete database configuration array.
     * 
     * @return array<string, mixed> The database configuration array
     * @deprecated Use property $databaseConfig instead
     */
    public function getDatabaseConfig(): array
    {
        return $this->databaseConfig;
    }
}
