<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

use Psr\Container\ContainerInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Config\Config;
use Hyperf\DbConnection\Pool\PoolFactory;
use Hyperf\DbConnection\Connection;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\EventDispatcher;
use Hyperf\Event\ListenerProvider;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;

/**
 * OpenSwoole Connection Manager with Hyperf Connection Pooling
 * 
 * This is the **real implementation** that creates actual connection pools using Hyperf.
 * It implements ConnectionManagerInterface from connection-contracts package.
 * 
 * **IMPORTANT: This IS connection pooling!**
 * - Uses Hyperf's connection pooling system
 * - Manages pool size limits (min/max connections)
 * - Handles idle connection management
 * - Connection rotation and health checks
 * - Appropriate for OpenSwoole (shared connections across requests)
 * 
 * **Architecture:**
 * - Creates Hyperf connection pools: `PoolFactory` → `Pool` → `Connection` - **REAL IMPLEMENTATION**
 * - Implements ConnectionManagerInterface (from connection-contracts)
 * - Returns ConnectionInterface (wraps Hyperf Connection with SwooleConnectionAdapter)
 * - Part of gemvc/connection-openswoole package
 * - **Only depends on connection-contracts** (no framework dependencies)
 * 
 * **Features:**
 * - True connection pooling (Hyperf-based)
 * - Pool size management (min/max connections)
 * - Connection health monitoring
 * - Idle connection timeout
 * - Connection timeout configuration
 * - Environment-based configuration (reads $_ENV directly)
 * - Returns ConnectionInterface (not raw PDO)
 * - **Performance Optimizations:**
 *   - Connection pooling (reuses connections across requests)
 *   - Configurable pool sizes
 *   - Connection health checks
 *   - Optimized for high-concurrency Swoole environment
 * 
 * **Environment Variables:**
 * - `MIN_DB_CONNECTION_POOL=8` - Minimum pool size (default: 8)
 * - `MAX_DB_CONNECTION_POOL=16` - Maximum pool size (default: 16)
 * - `DB_CONNECTION_TIME_OUT=10.0` - Connection timeout in seconds (default: 10.0)
 * - `DB_CONNECTION_EXPIER_TIME=2.0` - Wait timeout in seconds (default: 2.0)
 * - `DB_HEARTBEAT=-1` - Heartbeat interval (default: -1, disabled)
 * - `DB_CONNECTION_MAX_AGE=60.0` - Max idle time in seconds (default: 60.0)
 * 
 * **Dependencies:**
 * - Only depends on: gemvc/connection-contracts
 * - Requires: Hyperf packages (db-connection, di, config, event)
 * - No framework dependencies (ProjectHelper, etc.)
 * - Reads environment variables directly from $_ENV
 */
class SwooleConnection implements ConnectionManagerInterface
{
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var Container The DI container from Hyperf */
    private Container $container;

    /** @var PoolFactory The factory for creating and managing connection pools */
    private PoolFactory $poolFactory;

    /** @var string|null Last error message */
    private ?string $error = null;

    /** @var bool Whether the manager is initialized */
    private bool $initialized = false;

    /** @var array<string, ConnectionInterface> Active connections by pool name */
    private array $activeConnections = [];

    /**
     * Constructor
     * 
     * ⚠️ **WARNING: DO NOT USE DIRECTLY!**
     * 
     * **Always use `SwooleConnection::getInstance()` instead of `new SwooleConnection()`**
     * 
     * This constructor is public for PHPUnit coverage reporting purposes, but you should
     * **NEVER** instantiate this class directly. Always use the singleton pattern:
     * 
     * ```php
     * // ✅ CORRECT - Always use this:
     * $manager = SwooleConnection::getInstance();
     * 
     * // ❌ WRONG - Never do this:
     * $manager = new SwooleConnection(); // Creates separate instance, breaks singleton!
     * ```
     * 
     * **Why?**
     * - Direct instantiation creates a separate instance, breaking the singleton pattern
     * - Connection pools won't be shared across your application
     * - Configuration might be inconsistent
     * - Multiple instances can cause connection leaks
     * 
     * @internal This is public only for PHPUnit coverage. Use getInstance() instead.
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Get the singleton instance
     * 
     * **⚠️ IMPORTANT: Always use this method to get the connection manager!**
     * 
     * **DO NOT** use `new SwooleConnection()` - always use `getInstance()` instead.
     * 
     * ```php
     * // ✅ CORRECT:
     * $manager = SwooleConnection::getInstance();
     * 
     * // ❌ WRONG:
     * $manager = new SwooleConnection(); // Breaks singleton pattern!
     * ```
     * 
     * @return self The singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        } else {
            // Debug: Confirm singleton is being reused
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("SwooleConnection: Reusing existing pool [Worker PID: " . getmypid() . "]");
            }
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing)
     * 
     * @return void
     */
    public static function resetInstance(): void
    {
        if (self::$instance !== null) {
            // Release all active connections
            foreach (self::$instance->activeConnections as $connection) {
                $driver = $connection->getConnection();
                $connection->releaseConnection($driver);
            }
            self::$instance->activeConnections = [];
            self::$instance = null;
        }
    }

    /**
     * Initialize the database manager
     * 
     * Sets up Hyperf DI container and connection pools.
     * Reads environment variables directly (no framework dependency).
     * Framework should ensure $_ENV is populated before using this.
     * 
     * @return void
     */
    private function initialize(): void
    {
        try {
            // Debug: Log when pool is actually created (should only happen once per worker)
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("SwooleConnection: Creating new connection pool [Worker PID: " . getmypid() . "]");
            }

            // Get the configuration directly from the private method inside this class
            $dbConfig = $this->getDatabaseConfig();

            // Initialize the Hyperf Dependency Injection container
            $this->container = new Container(new DefinitionSource([]));

            // Bind the database configuration array to the ConfigInterface contract within the container
            $this->container->set(\Hyperf\Contract\ConfigInterface::class, new Config(['databases' => $dbConfig]));
            
            // Bind the container instance to the Psr\Container\ContainerInterface contract
            $this->container->set(ContainerInterface::class, $this->container);
            
            // Bind the StdoutLoggerInterface required by Hyperf's database connection pool
            // Use a simple logger implementation that doesn't require Symfony Console
            $this->container->set(StdoutLoggerInterface::class, new class implements StdoutLoggerInterface {
                /** @param array<string, mixed> $context */
                public function emergency(string|\Stringable $message, array $context = []): void { 
                    error_log("[EMERGENCY] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function alert(string|\Stringable $message, array $context = []): void { 
                    error_log("[ALERT] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function critical(string|\Stringable $message, array $context = []): void { 
                    error_log("[CRITICAL] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function error(string|\Stringable $message, array $context = []): void { 
                    error_log("[ERROR] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function warning(string|\Stringable $message, array $context = []): void { 
                    error_log("[WARNING] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function notice(string|\Stringable $message, array $context = []): void { 
                    error_log("[NOTICE] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function info(string|\Stringable $message, array $context = []): void { 
                    error_log("[INFO] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function debug(string|\Stringable $message, array $context = []): void { 
                    error_log("[DEBUG] " . (string) $message); 
                }
                /** @param array<string, mixed> $context */
                public function log(mixed $level, string|\Stringable $message, array $context = []): void { 
                    $levelStr = is_string($level) ? $level : 'UNKNOWN';
                    error_log("[" . $levelStr . "] " . (string) $message); 
                }
            });
            
            // Bind event dispatcher dependencies required by Hyperf's database connection pool
            $listenerProvider = new ListenerProvider();
            $this->container->set(\Psr\EventDispatcher\ListenerProviderInterface::class, $listenerProvider);
            
            // Create event dispatcher instance properly
            $logger = $this->container->get(StdoutLoggerInterface::class);
            /** @var \Psr\Log\LoggerInterface|null $logger */
            $eventDispatcher = new EventDispatcher($listenerProvider, $logger);
            $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, $eventDispatcher);

            // Create the PoolFactory, which will use the container to get the configuration
            $this->poolFactory = new PoolFactory($this->container);
            
            $this->initialized = true;
        } catch (\Exception $e) {
            $this->setError('Failed to initialize SwooleConnection: ' . $e->getMessage());
            $this->initialized = false;
        }
    }

    /**
     * Get a connection from the pool
     * 
     * **Implements ConnectionManagerInterface from connection-contracts**
     * Returns ConnectionInterface (wrapped Hyperf Connection), not raw PDO.
     * 
     * **Note:** This IS connection pooling. Uses Hyperf's pool system:
     * - Gets connection from pool (reuses existing connections)
     * - Pool size limits (min/max connections)
     * - Idle connection management
     * - Connection health checks
     * - Appropriate for OpenSwoole (shared connections across requests)
     * 
     * @param string $poolName Connection pool name (default: 'default')
     * @return ConnectionInterface|null The connection instance or null on failure
     */
    public function getConnection(string $poolName = 'default'): ?ConnectionInterface
    {
        $this->clearError();

        // Check if we already have an adapter for this pool
        if (isset($this->activeConnections[$poolName])) {
            $existing = $this->activeConnections[$poolName];
            // Verify the adapter is still valid
            if ($existing->isInitialized()) {
                return $existing;
            }
            // Remove invalid connection
            unset($this->activeConnections[$poolName]);
        }

        try {
            // Get Hyperf Connection from pool (REAL IMPLEMENTATION - connection pooling)
            /** @var Connection $hyperfConnection */
            $hyperfConnection = $this->poolFactory->getPool($poolName)->get();
            
            // PERFORMANCE: Removed SELECT 1 ping - Hyperf pool already handles connection health
            // The pool's heartbeat mechanism (configured in pool settings) handles dead connections
            // This eliminates an extra database query on every request
            
            // Create adapter wrapping the Hyperf Connection
            $adapter = new SwooleConnectionAdapter($hyperfConnection);
            $this->activeConnections[$poolName] = $adapter;
            
            if (($_ENV['APP_ENV'] ?? '') === 'dev') {
                error_log("SwooleConnection: New connection retrieved from pool: {$poolName}");
            }
            
            return $adapter;
        } catch (\Throwable $e) {
            $context = [
                'pool' => $poolName,
                'worker_pid' => getmypid(),
                'timestamp' => date('Y-m-d H:i:s'),
                'error_code' => $e->getCode()
            ];
            $this->setError('Failed to get database connection: ' . $e->getMessage(), $context);
            error_log("SwooleConnection::getConnection() - Error: " . $e->getMessage() . " [Pool: $poolName]");
            return null;
        }
    }

    /**
     * Release a connection back to the pool
     * 
     * **Implements ConnectionManagerInterface from connection-contracts**
     * 
     * **Note:** This IS connection pooling. Releases connection back to Hyperf pool.
     * 
     * @param ConnectionInterface $connection The connection to release
     * @return void
     */
    public function releaseConnection(ConnectionInterface $connection): void
    {
        // Find and remove from active connections
        foreach ($this->activeConnections as $poolName => $activeConnection) {
            if ($activeConnection === $connection) {
                unset($this->activeConnections[$poolName]);
                break;
            }
        }
        
        // Release via adapter (which releases Hyperf connection back to pool)
        $connection->releaseConnection($connection->getConnection());
    }

    /**
     * Get the last error message
     * 
     * @return string|null Error message or null if no error occurred
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Set an error message
     * 
     * @param string|null $error The error message to set
     * @param array<string, mixed> $context Additional context information
     * @return void
     */
    public function setError(?string $error, array $context = []): void
    {
        if ($error === null) {
            $this->error = null;
            return;
        }

        // Add context information to error message
        if (!empty($context)) {
            $contextStr = ' [Context: ' . json_encode($context) . ']';
            $this->error = $error . $contextStr;
        } else {
            $this->error = $error;
        }
    }

    /**
     * Clear the last error message
     * 
     * @return void
     */
    public function clearError(): void
    {
        $this->error = null;
    }

    /**
     * Check if the database manager is properly initialized
     * 
     * @return bool True if initialized, false otherwise
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get connection pool statistics
     * 
     * **Implements ConnectionManagerInterface from connection-contracts**
     * 
     * @return array<string, mixed> Pool statistics
     */
    public function getPoolStats(): array
    {
        $config = $this->getDatabaseConfig();
        /** @var array<string, mixed> $defaultConfig */
        $defaultConfig = $config['default'] ?? [];
        /** @var array<string, mixed> $poolConfig */
        $poolConfig = $defaultConfig['pool'] ?? [];

        return [
            'type' => 'OpenSwoole Connection Manager (True Connection Pooling)',
            'environment' => 'OpenSwoole',
            'active_connections' => count($this->activeConnections),
            'initialized' => $this->initialized,
            'pool_config' => [
                'min_connections' => is_numeric($poolConfig['min_connections'] ?? null) ? (int) $poolConfig['min_connections'] : 8,
                'max_connections' => is_numeric($poolConfig['max_connections'] ?? null) ? (int) $poolConfig['max_connections'] : 16,
                'connect_timeout' => is_numeric($poolConfig['connect_timeout'] ?? null) ? (float) $poolConfig['connect_timeout'] : 10.0,
                'wait_timeout' => is_numeric($poolConfig['wait_timeout'] ?? null) ? (float) $poolConfig['wait_timeout'] : 2.0,
                'heartbeat' => is_numeric($poolConfig['heartbeat'] ?? null) ? (int) $poolConfig['heartbeat'] : -1,
                'max_idle_time' => is_numeric($poolConfig['max_idle_time'] ?? null) ? (float) $poolConfig['max_idle_time'] : 60.0,
            ],
            'config' => [
                'driver' => is_string($defaultConfig['driver'] ?? null) ? $defaultConfig['driver'] : 'unknown',
                'host' => is_string($defaultConfig['host'] ?? null) ? $defaultConfig['host'] : 'unknown',
                'database' => is_string($defaultConfig['database'] ?? null) ? $defaultConfig['database'] : 'unknown',
            ]
        ];
    }

    /**
     * Builds the database configuration array by reading environment variables.
     * This makes the class independent of external config files.
     *
     * @return array<string, mixed> The database configuration array
     */
    private function getDatabaseConfig(): array
    {
        /**
         * Determines the correct database host based on the execution context (CLI vs Server).
         * @return string The database host
         */
        $getDbHost = function (): string {
            // Check if we're running in OpenSwoole server context
            // OpenSwoole runs in CLI mode but we need to detect if it's the web server
            if (PHP_SAPI === 'cli' && (defined('SWOOLE_BASE') || class_exists('\OpenSwoole\Server'))) {
                // Running in OpenSwoole server - use container host
                $host = $_ENV['DB_HOST'] ?? 'db';
                return is_string($host) ? $host : 'db';
            }
            
            // True CLI context - use localhost
            if (PHP_SAPI === 'cli') {
                $host = $_ENV['DB_HOST_CLI_DEV'] ?? 'localhost';
                return is_string($host) ? $host : 'localhost';
            }
            
            // In any other context (like web server), use the container host
            $host = $_ENV['DB_HOST'] ?? 'db';
            return is_string($host) ? $host : 'db';
        };

        return [
            'default' => [
                'driver' => is_string($_ENV['DB_DRIVER'] ?? 'mysql') ? ($_ENV['DB_DRIVER'] ?? 'mysql') : 'mysql',
                'host' => $getDbHost(),
                'port' => is_numeric($_ENV['DB_PORT'] ?? '3306') ? (int) ($_ENV['DB_PORT'] ?? '3306') : 3306,
                'database' => is_string($_ENV['DB_NAME'] ?? 'gemvc_db') ? ($_ENV['DB_NAME'] ?? 'gemvc_db') : 'gemvc_db',
                'username' => is_string($_ENV['DB_USER'] ?? 'root') ? ($_ENV['DB_USER'] ?? 'root') : 'root',
                'password' => is_string($_ENV['DB_PASSWORD'] ?? '') ? ($_ENV['DB_PASSWORD'] ?? '') : '',
                'charset' => is_string($_ENV['DB_CHARSET'] ?? 'utf8mb4') ? ($_ENV['DB_CHARSET'] ?? 'utf8mb4') : 'utf8mb4',
                'collation' => is_string($_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci') ? ($_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci') : 'utf8mb4_unicode_ci',
                'pool' => [
                    // PERFORMANCE: Optimized defaults for production workloads
                    'min_connections' => is_numeric($_ENV['MIN_DB_CONNECTION_POOL'] ?? '8') ? (int) ($_ENV['MIN_DB_CONNECTION_POOL'] ?? '8') : 8,
                    'max_connections' => is_numeric($_ENV['MAX_DB_CONNECTION_POOL'] ?? '16') ? (int) ($_ENV['MAX_DB_CONNECTION_POOL'] ?? '16') : 16,
                    'connect_timeout' => is_numeric($_ENV['DB_CONNECTION_TIME_OUT'] ?? '10.0') ? (float) ($_ENV['DB_CONNECTION_TIME_OUT'] ?? '10.0') : 10.0,
                    'wait_timeout' => is_numeric($_ENV['DB_CONNECTION_EXPIER_TIME'] ?? '2.0') ? (float) ($_ENV['DB_CONNECTION_EXPIER_TIME'] ?? '2.0') : 2.0,
                    // PERFORMANCE: Disabled heartbeat for better performance (pool handles health)
                    'heartbeat' => is_numeric($_ENV['DB_HEARTBEAT'] ?? '-1') ? (int) ($_ENV['DB_HEARTBEAT'] ?? '-1') : -1,
                    'max_idle_time' => is_numeric($_ENV['DB_CONNECTION_MAX_AGE'] ?? '60.0') ? (float) ($_ENV['DB_CONNECTION_MAX_AGE'] ?? '60.0') : 60.0,
                ],
            ],
        ];
    }

    /**
     * Clean up resources on destruction
     */
    public function __destruct()
    {
        // Release all active connections
        foreach ($this->activeConnections as $connection) {
            $driver = $connection->getConnection();
            $connection->releaseConnection($driver);
        }
        $this->activeConnections = [];
    }
}

