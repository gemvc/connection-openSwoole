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
 * Creates actual connection pools using Hyperf. Implements ConnectionManagerInterface.
 * Supports multiple concurrent connections from the same pool.
 * 
 * **⚠️ IMPORTANT: Always use `getInstance()`, never `new SwooleConnection()`**
 * 
 * **Environment Variables:**
 * - `DB_DRIVER`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` - Database connection
 * - `MIN_DB_CONNECTION_POOL=8` - Minimum pool size
 * - `MAX_DB_CONNECTION_POOL=16` - Maximum pool size
 * - `DB_CONNECTION_MAX_AGE=60.0` - Max idle time (seconds)
 * 
 * **Usage:**
 * ```php
 * $manager = SwooleConnection::getInstance();
 * $connection = $manager->getConnection();
 * // ... use connection ...
 * $manager->releaseConnection($connection);
 * ```
 */
class SwooleConnection implements ConnectionManagerInterface
{
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var Container|null The DI container from Hyperf */
    private ?Container $container = null;

    /** @var PoolFactory|null The factory for creating and managing connection pools */
    private ?PoolFactory $poolFactory = null;

    /** @var string|null Last error message */
    private ?string $error = null;

    /** @var bool Whether the manager is initialized */
    private bool $initialized = false;

    /** @var array<ConnectionInterface> Active connections (flat array, allows multiple per pool) */
    private array $activeConnections = [];

    /** @var SwooleEnvDetect Environment detection utility */
    private SwooleEnvDetect $envDetect;

    /** @var SwooleErrorLogLogger Logger instance for debug messages */
    private ?SwooleErrorLogLogger $logger = null;

    /**
     * Constructor
     * 
     * WARNING: Always use `getInstance()`, never `new SwooleConnection()`**
     * 
     * @internal Public for PHPUnit coverage only. Use getInstance() instead.
     */
    public function __construct()
    {
        $this->envDetect = new SwooleEnvDetect();
        $this->initialize();
    }

    /**
     * Get the singleton instance
     * 
     * Always use this method, never `new SwooleConnection()`**
     * 
     * @return self The singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        } else {
            if (self::$instance->envDetect->isDevEnvironment) {
                $logger = self::$instance->logger ?? new SwooleErrorLogLogger();
                $logger->info("SwooleConnection: Reusing existing pool [Worker PID: " . getmypid() . "]");
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
            foreach (self::$instance->activeConnections as $connection) {
                try {
                    $driver = $connection->getConnection();
                    if ($driver !== null) {
                        $connection->releaseConnection($driver);
                    } else {
                        $connection->releaseConnection(null);
                    }
                } catch (\Throwable $e) {
                    (self::$instance->logger ?? new SwooleErrorLogLogger())->handleException($e, 'Error releasing connection in resetInstance');
                }
            }
            self::$instance->activeConnections = [];
            self::$instance = null;
        }
    }

    /**
     * Initialize the database manager
     * 
     * @return void
     */
    private function initialize(): void
    {
        $containerCreated = false;

        try {
            $this->initializeLogger();
            $this->initializeContainer();
            $containerCreated = true;
            $this->initializeEventDispatcher();
            $this->initializePoolFactory();
            $this->initialized = true;
        } catch (\Throwable $e) {
            $this->handleInitializationFailure($e, $containerCreated);
        }
    }

    /**
     * Initialize logger instance
     * 
     * @return void
     */
    private function initializeLogger(): void
    {
        $this->logger = new SwooleErrorLogLogger();

        if ($this->envDetect->isDevEnvironment) {
            $this->logger->info("SwooleConnection: Creating new connection pool [Worker PID: " . getmypid() . "]");
        }
    }

    /**
     * Initialize Hyperf DI container with basic service bindings
     * 
     * @return void
     * @throws \RuntimeException If container creation or binding fails
     */
    private function initializeContainer(): void
    {
        if ($this->logger === null) {
            throw new \RuntimeException('Logger must be initialized before container');
        }

        $dbConfig = $this->envDetect->databaseConfig;
        if (empty($dbConfig)) {
            throw new \RuntimeException('Invalid database configuration: config must be a non-empty array');
        }
        if ($this->container) {
            $this->container = null;
        }   

        try {
            $this->container = new Container(new DefinitionSource([]));
            $this->container->set(\Hyperf\Contract\ConfigInterface::class,  new Config(['databases' => $dbConfig]));
            $this->container->set(ContainerInterface::class, $this->container);
            $this->container->set(StdoutLoggerInterface::class, $this->logger);
        } catch (\Throwable $e) {
            $this->container = null;
            $this->logger->logAndThrowException($e, 'container');
        }
    }

    /**
     * Initialize event dispatcher and bind it to the container
     * 
     * @return void
     * @throws \RuntimeException If event dispatcher setup fails
     */
    private function initializeEventDispatcher(): void
    {
        if ($this->container === null) {
            throw new \RuntimeException('Container must be initialized before event dispatcher');
        }

        try {
            $listenerProvider = new ListenerProvider();
            $logger = $this->container->get(StdoutLoggerInterface::class);
            /** @var \Psr\Log\LoggerInterface|null $logger */
            if ($logger === null) {
                throw new \RuntimeException('Logger not found in container after binding');
            }

            $eventDispatcher = new EventDispatcher($listenerProvider, $logger);
            $this->container->set(\Psr\EventDispatcher\ListenerProviderInterface::class, $listenerProvider);
            $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, $eventDispatcher);
        } catch (\Throwable $e) {
            ($this->logger ?? new SwooleErrorLogLogger())->logAndThrowException($e, 'event dispatcher');
        }
    }

    /**
     * Initialize pool factory for connection pool management
     * @return void
     * @throws \RuntimeException If pool factory creation fails
     */
    private function initializePoolFactory(): void
    {
        if ($this->container === null) {
            throw new \RuntimeException('Container must be initialized before pool factory');
        }
        if ($this->poolFactory) {
            $this->poolFactory = null;
        }

        try {
            $this->poolFactory = new PoolFactory($this->container);
        } catch (\Throwable $e) {
            $this->poolFactory = null;
            ($this->logger ?? new SwooleErrorLogLogger())->logAndThrowException($e, 'pool factory');
        }
    }

    /**
     * @param \Throwable $e The exception that caused initialization to fail
     * @param bool $containerCreated Whether the container was created before failure
     * @return void
     */
    private function handleInitializationFailure(\Throwable $e, bool $containerCreated): void
    {
        if ($containerCreated) {
            $this->container = null;
        }
        $errorMessage = 'Failed to initialize SwooleConnection: ' . $e->getMessage();
        ($this->logger ?? new SwooleErrorLogLogger())->handleException($e, 'Failed to initialize SwooleConnection');
        $this->setError($errorMessage);
        $this->initialized = false;
    }

    /**
     * Get a connection from the pool
     * 
     * Each call gets a new connection from the pool, allowing multiple concurrent connections.
     * The Hyperf pool handles connection reuse and health checks internally.
     * 
     * @param string $poolName Connection pool name (default: 'default')
     * @return ConnectionInterface|null The connection instance or null on failure
     */
    public function getConnection(string $poolName = 'default'): ?ConnectionInterface
    {
        $this->clearError();

        // Validate and sanitize pool name for security
        $poolName = SwooleConnectionSecurity::validateAndSanitizePoolName($poolName);

        try {
            if ($this->poolFactory === null) {
                throw new \RuntimeException('Connection pool factory not initialized');
            }

            /** @var Connection $hyperfConnection */
            $hyperfConnection = $this->poolFactory->getPool($poolName)->get();
            return $this->createAndStoreAdapter($hyperfConnection, $poolName);
        } catch (\Throwable $e) {
            $context = [
                'pool' => $poolName,
                'worker_pid' => getmypid(),
                'timestamp' => date('Y-m-d H:i:s'),
                'error_code' => $e->getCode()
            ];
            
            // Sanitize error message to remove sensitive data
            $errorMessage = SwooleConnectionSecurity::sanitizeErrorMessage(
                'Failed to get database connection: ' . $e->getMessage(),
                $this->envDetect->dbPassword
            );
            
            $this->setError($errorMessage, $context);
            ($this->logger ?? new SwooleErrorLogLogger())->handleException($e, "SwooleConnection::getConnection() [Pool: $poolName]", 'error', $context);
            return null;
        }
    }

    /**
     * Release a connection back to the pool
     * 
     * **Important:** Always call this when done with a connection to prevent memory leaks.
     * 
     * @param ConnectionInterface $connection The connection to release
     * @return void
     */
    public function releaseConnection(ConnectionInterface $connection): void
    {
        $key = array_search($connection, $this->activeConnections, true);
        $found = ($key !== false);

        if ($found) {
            unset($this->activeConnections[$key]);
        }

        $driver = $connection->getConnection();
        if ($driver !== null) {
            $connection->releaseConnection($driver);
        } else {
            $connection->releaseConnection(null);
        }

        if (!$found) {
            ($this->logger ?? new SwooleErrorLogLogger())->handleWarning('Attempted to release connection not found in activeConnections tracking');
        }
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
     * @return array<string, mixed> Pool statistics array
     */
    public function getPoolStats(): array
    {
        $stats = SwooleConnectionPoolStats::fromConnection($this, $this->envDetect);
        return $stats->toArray();
    }

    /**
     * Get pool statistics as typed object
     * 
     * @return SwooleConnectionPoolStats Typed pool statistics object
     */
    public function getPoolStatsObject(): SwooleConnectionPoolStats
    {
        return SwooleConnectionPoolStats::fromConnection($this, $this->envDetect);
    }

    /**
     * Get active connections array (for internal use)
     * 
     * @return array<ConnectionInterface> Active connections (flat array)
     * @internal Used by SwooleConnectionPoolStats factory method
     */
    public function getActiveConnections(): array
    {
        return $this->activeConnections;
    }

    /**
     * Create and store adapter for Hyperf Connection
     * 
     * @param Connection $hyperfConnection The Hyperf Connection instance
     * @param string $poolName The pool name for logging
     * @return ConnectionInterface The created adapter
     */
    private function createAndStoreAdapter(Connection $hyperfConnection, string $poolName): ConnectionInterface
    {
        $adapter = new SwooleConnectionAdapter($hyperfConnection);
        $this->activeConnections[] = $adapter;

        if ($this->envDetect->isDevEnvironment) {
            $logger = $this->logger ?? new SwooleErrorLogLogger();
            $logger->info("SwooleConnection: New connection retrieved from pool: {$poolName}");
        }

        return $adapter;
    }

    /**
     * Clean up resources on destruction
     * 
     * Releases all tracked connections back to the pool. Applications should still
     * call `releaseConnection()` explicitly when done with a connection.
     */
    public function __destruct()
    {
        foreach ($this->activeConnections as $connection) {
            try {
                $driver = $connection->getConnection();
                if ($driver !== null) {
                    $connection->releaseConnection($driver);
                } else {
                    $connection->releaseConnection(null);
                }
            } catch (\Throwable $e) {
                ($this->logger ?? new SwooleErrorLogLogger())->handleException($e, 'Error releasing connection in __destruct');
            }
        }
        $this->activeConnections = [];
    }
}

