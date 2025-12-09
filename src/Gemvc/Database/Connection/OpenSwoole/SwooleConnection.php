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

    /** @var Container|null The DI container from Hyperf */
    private ?Container $container = null;

    /** @var PoolFactory|null The factory for creating and managing connection pools */
    private ?PoolFactory $poolFactory = null;

    /** @var string|null Last error message */
    private ?string $error = null;

    /** @var bool Whether the manager is initialized */
    private bool $initialized = false;

    /** @var array<string, ConnectionInterface> Active connections by pool name */
    private array $activeConnections = [];

    /** @var SwooleEnvDetect Environment detection utility */
    private SwooleEnvDetect $envDetect;

    /** @var SwooleErrorLogLogger Logger instance for debug messages */
    private ?SwooleErrorLogLogger $logger = null;

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
        $this->envDetect = new SwooleEnvDetect();
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
            // Use envDetect property instead of direct $_ENV access for consistency
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
     * Orchestrates the initialization process by calling specialized methods.
     * Each step is isolated for better testability and error handling.
     * 
     * @return void
     */
    private function initialize(): void
    {
        // Track initialization state to ensure proper cleanup on failure
        $containerCreated = false;
        
        try {
            // Step 1: Initialize logger (needed for error reporting)
            $this->initializeLogger();
            
            // Step 2: Initialize container with basic bindings
            $this->initializeContainer();
            $containerCreated = true;
            
            // Step 3: Initialize event dispatcher (depends on container)
            $this->initializeEventDispatcher();
            
            // Step 4: Initialize pool factory (depends on container)
            $this->initializePoolFactory();
            
            $this->initialized = true;
        } catch (\Throwable $e) {
            // Catch both Exception and Error for robustness
            $this->handleInitializationFailure($e, $containerCreated);
        }
    }

    /**
     * Initialize logger instance.
     * 
     * Creates and configures the logger for error reporting and debug messages.
     * This is done early so errors can be logged during initialization.
     * 
     * @return void
     * @throws \RuntimeException If logger creation fails
     */
    private function initializeLogger(): void
    {
        $this->logger = new SwooleErrorLogLogger();
        
        // Debug: Log when pool is actually created (should only happen once per worker)
        if ($this->envDetect->isDevEnvironment) {
            $this->logger->info("SwooleConnection: Creating new connection pool [Worker PID: " . getmypid() . "]");
        }
    }

    /**
     * Initialize Hyperf DI container with basic service bindings.
     * 
     * Creates the container and binds essential services:
     * - Database configuration
     * - Container self-reference (for dependency injection)
     * - Logger interface
     * 
     * Uses atomic initialization pattern: only assigns to instance property
     * after all operations succeed, preventing partial initialization state.
     * 
     * @return void
     * @throws \RuntimeException If container creation or binding fails
     */
    private function initializeContainer(): void
    {
        // Validate prerequisites FIRST (before creating expensive objects)
        if ($this->logger === null) {
            throw new \RuntimeException('Logger must be initialized before container');
        }
        
        // Validate database config before using it
        $dbConfig = $this->envDetect->databaseConfig;
        if (empty($dbConfig) || !is_array($dbConfig)) {
            throw new \RuntimeException('Invalid database configuration: config must be a non-empty array');
        }
        
        // Use local variable first to ensure atomicity
        // Only assign to $this->container if ALL operations succeed
        $container = null;
        try {
            // Initialize the Hyperf Dependency Injection container
            $container = new Container(new DefinitionSource([]));
            
            // Bind the database configuration array to the ConfigInterface contract within the container
            $config = new Config(['databases' => $dbConfig]);
            $container->set(\Hyperf\Contract\ConfigInterface::class, $config);
            
            // Bind the container instance to the Psr\Container\ContainerInterface contract
            // NOTE: This creates a circular reference. PHP 7.4+ GC handles this, but monitor
            // memory usage in long-running processes (OpenSwoole). If memory issues occur,
            // consider explicit cleanup in __destruct() to break the cycle.
            $container->set(ContainerInterface::class, $container);
            
            // Bind the StdoutLoggerInterface required by Hyperf's database connection pool
            // Use a simple logger implementation that doesn't require Symfony Console
            $container->set(StdoutLoggerInterface::class, $this->logger);
            
            // Only assign to instance property if ALL operations succeeded
            // This ensures atomicity: either fully initialized or not at all
            $this->container = $container;
        } catch (\Throwable $e) {
            // Clean up container if it was created but bindings failed
            // Set to null to help GC (container may hold references)
            if ($container !== null) {
                $container = null;
            }
            
            // Re-throw with more context, preserving original exception
            throw new \RuntimeException(
                'Failed to initialize container: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Initialize event dispatcher and bind it to the container.
     * 
     * Sets up the event dispatcher system required by Hyperf's connection pool.
     * This includes creating the listener provider and event dispatcher.
     * 
     * Uses atomic initialization pattern: only binds to container after all
     * objects are successfully created, preventing partial initialization state.
     * 
     * @return void
     * @throws \RuntimeException If event dispatcher setup fails
     */
    private function initializeEventDispatcher(): void
    {
        // Validate prerequisites FIRST
        if ($this->container === null) {
            throw new \RuntimeException('Container must be initialized before event dispatcher');
        }
        
        // Use local variables first to ensure atomicity
        // Only bind to container if ALL operations succeed
        $listenerProvider = null;
        $eventDispatcher = null;
        try {
            // Create listener provider
            $listenerProvider = new ListenerProvider();
            
            // Get logger from container (must exist from initializeContainer)
            $logger = $this->container->get(StdoutLoggerInterface::class);
            /** @var \Psr\Log\LoggerInterface|null $logger */
            if ($logger === null) {
                throw new \RuntimeException('Logger not found in container after binding');
            }
            
            // Create event dispatcher instance
            $eventDispatcher = new EventDispatcher($listenerProvider, $logger);
            
            // Only bind to container if ALL operations succeeded
            // This ensures atomicity: either fully initialized or not at all
            $this->container->set(\Psr\EventDispatcher\ListenerProviderInterface::class, $listenerProvider);
            $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, $eventDispatcher);
        } catch (\Throwable $e) {
            // Clean up objects if they were created but binding failed
            // Set to null to help GC
            if ($listenerProvider !== null) {
                $listenerProvider = null;
            }
            if ($eventDispatcher !== null) {
                $eventDispatcher = null;
            }
            
            // Re-throw with more context, preserving original exception
            throw new \RuntimeException(
                'Failed to initialize event dispatcher: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Initialize pool factory for connection pool management.
     * 
     * Creates the PoolFactory which manages database connection pools.
     * The factory uses the container to resolve dependencies.
     * 
     * Uses atomic initialization pattern: only assigns to instance property
     * after successful creation, preventing partial initialization state.
     * 
     * @return void
     * @throws \RuntimeException If pool factory creation fails
     */
    private function initializePoolFactory(): void
    {
        // Validate prerequisites FIRST
        if ($this->container === null) {
            throw new \RuntimeException('Container must be initialized before pool factory');
        }
        
        // Use local variable first to ensure atomicity
        // Only assign to $this->poolFactory if creation succeeds
        $poolFactory = null;
        try {
            // Create the PoolFactory, which will use the container to get the configuration
            $poolFactory = new PoolFactory($this->container);
            
            // Only assign to instance property if creation succeeded
            // This ensures atomicity: either fully initialized or not at all
            $this->poolFactory = $poolFactory;
        } catch (\Throwable $e) {
            // Clean up pool factory if it was created but assignment failed
            // Set to null to help GC
            if ($poolFactory !== null) {
                $poolFactory = null;
            }
            
            // Re-throw with more context, preserving original exception
            throw new \RuntimeException(
                'Failed to initialize pool factory: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Handle initialization failure with proper cleanup.
     * 
     * Cleans up partially initialized state and sets error information.
     * Ensures no memory leaks from partially created resources.
     * 
     * @param \Throwable $e The exception that caused initialization to fail
     * @param bool $containerCreated Whether the container was created before failure
     * @return void
     */
    private function handleInitializationFailure(\Throwable $e, bool $containerCreated): void
    {
        // Clean up partially initialized state
        if ($containerCreated) {
            // Container was created but initialization failed
            // Set to null to allow GC to clean up (container holds references)
            $this->container = null;
        }
        // poolFactory is already null if not created (default value)
        
        // Set error state (logger might be null if exception occurred very early)
        $errorMessage = 'Failed to initialize SwooleConnection: ' . $e->getMessage();
        if ($this->logger !== null) {
            $this->logger->error($errorMessage);
        }
        $this->setError($errorMessage);
        $this->initialized = false;
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
            // Ensure poolFactory is initialized
            if ($this->poolFactory === null) {
                throw new \RuntimeException('Connection pool factory not initialized');
            }
            
            // Get Hyperf Connection from pool (REAL IMPLEMENTATION - connection pooling)
            /** @var Connection $hyperfConnection */
            $hyperfConnection = $this->poolFactory->getPool($poolName)->get();
            
            // PERFORMANCE: Removed SELECT 1 ping - Hyperf pool already handles connection health
            // The pool's heartbeat mechanism (configured in pool settings) handles dead connections
            // This eliminates an extra database query on every request
            
            // Create and store adapter wrapping the Hyperf Connection
            return $this->createAndStoreAdapter($hyperfConnection, $poolName);
        } catch (\Throwable $e) {
            $context = [
                'pool' => $poolName,
                'worker_pid' => getmypid(),
                'timestamp' => date('Y-m-d H:i:s'),
                'error_code' => $e->getCode()
            ];
            $this->setError('Failed to get database connection: ' . $e->getMessage(), $context);
            $logger = $this->logger ?? new SwooleErrorLogLogger();
            $logger->error("SwooleConnection::getConnection() - Error: " . $e->getMessage() . " [Pool: $poolName]");
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
     * Uses typed SwooleConnectionPoolStats internally for clean OOP design,
     * then converts to array for interface compatibility.
     * 
     * @return array<string, mixed> Pool statistics array
     */
    public function getPoolStats(): array
    {
        $stats = SwooleConnectionPoolStats::fromConnection($this, $this->envDetect);
        return $stats->toArray();
    }

    /**
     * Get pool statistics as typed object (for better OOP usage).
     * 
     * This method returns a typed, immutable value object instead of an array.
     * Use this when you want type-safe access to pool statistics.
     * 
     * @return SwooleConnectionPoolStats Typed pool statistics object
     */
    public function getPoolStatsObject(): SwooleConnectionPoolStats
    {
        return SwooleConnectionPoolStats::fromConnection($this, $this->envDetect);
    }

    /**
     * Get active connections array (for internal use).
     * 
     * @return array<string, ConnectionInterface> Active connections by pool name
     * @internal Used by SwooleConnectionPoolStats factory method
     */
    public function getActiveConnections(): array
    {
        return $this->activeConnections;
    }

    /**
     * Create and store adapter for Hyperf Connection
     * 
     * Creates a SwooleConnectionAdapter wrapping the Hyperf Connection,
     * stores it in activeConnections, and logs in dev environment.
     * 
     * @param Connection $hyperfConnection The Hyperf Connection instance
     * @param string $poolName The pool name for storage and logging
     * @return ConnectionInterface The created adapter
     */
    private function createAndStoreAdapter(Connection $hyperfConnection, string $poolName): ConnectionInterface
    {
        // Create adapter wrapping the Hyperf Connection
        $adapter = new SwooleConnectionAdapter($hyperfConnection);
        $this->activeConnections[$poolName] = $adapter;
        
        // Log in dev environment
        if ($this->envDetect->isDevEnvironment) {
            $logger = $this->logger ?? new SwooleErrorLogLogger();
            $logger->info("SwooleConnection: New connection retrieved from pool: {$poolName}");
        }
        
        return $adapter;
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

