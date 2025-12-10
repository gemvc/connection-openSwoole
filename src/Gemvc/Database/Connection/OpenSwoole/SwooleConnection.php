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
 * **Memory Leak Prevention:**
 *   - Pool timeout mechanism (`max_idle_time`, default: 60.0s) automatically closes idle connections
 *   - Destructor releases all connections on shutdown
 *   - Pool size limits (`max_connections`, default: 16) prevent unbounded growth
 *   - Best practice: Always call `releaseConnection()` when done with a connection
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

    /**
     * Active connections (flat array, allows multiple per pool)
     * 
     * REFACTORED (Phase 5): Memory leak prevention
     * 
     * This array tracks active connections for statistics and cleanup purposes.
     * While it could theoretically grow if connections aren't released, we rely on:
     * 
     * 1. **Hyperf Pool Timeout** - The pool's `max_idle_time` setting (default: 60.0 seconds)
     *    automatically closes idle connections, preventing unbounded growth.
     * 
     * 2. **Connection Release** - Applications should call `releaseConnection()` when done,
     *    which removes the connection from this array and returns it to the pool.
     * 
     * 3. **Destructor Cleanup** - `__destruct()` releases all connections on shutdown,
     *    ensuring no leaks in long-running processes.
     * 
     * 4. **Pool Size Limits** - The pool's `max_connections` setting (default: 16) limits
     *    the maximum number of connections, preventing unbounded growth.
     * 
     * **Best Practice:** Always call `releaseConnection()` when done with a connection.
     * The pool timeout is a safety net, not a replacement for proper resource management.
     * 
     * @var array<ConnectionInterface>
     */
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
            // Release all active connections with null safety and error handling
            foreach (self::$instance->activeConnections as $connection) {
                try {
                    $driver = $connection->getConnection();
                    // REFACTORED: Added null check - getConnection() can return null
                    if ($driver !== null) {
                        $connection->releaseConnection($driver);
                    } else {
                        // Log warning if driver is null (connection may be in invalid state)
                        if (self::$instance->logger !== null) {
                            self::$instance->logger->warning('Connection driver is null during resetInstance cleanup');
                        }
                        // Still attempt release (adapter handles null internally)
                        $connection->releaseConnection(null);
                    }
                } catch (\Throwable $e) {
                    // Best-effort cleanup - log but don't fail
                    // This ensures cleanup continues even if one connection fails
                    if (self::$instance->logger !== null) {
                        self::$instance->logger->error('Error releasing connection in resetInstance: ' . $e->getMessage());
                    }
                }
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
        // databaseConfig is typed as array, so we only need to check if empty
        if (empty($dbConfig)) {
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
            // Defensive cleanup check (may be null if exception before assignment)
            if ($listenerProvider !== null) { // @phpstan-ignore-line
                $listenerProvider = null;
            }
            // Defensive cleanup check (may be null if exception before assignment)
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
            // @phpstan-ignore-next-line - Defensive cleanup check (may be null if exception before assignment)
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

        // REFACTORED (Phase 2): Removed pool name caching - always get new connection from pool
        // This allows multiple concurrent connections from the same pool, which is the
        // correct behavior for connection pooling. The Hyperf pool handles connection
        // reuse and health checks internally.
        //
        // REFACTORED (Phase 4): Race condition eliminated - removed check-then-act pattern.
        // Previously: if (isset($activeConnections[$poolName])) { return existing; }
        // This created a race condition where multiple coroutines could check, then act
        // on stale state. Now we directly call the pool factory, which handles
        // concurrency internally. No synchronization needed in our code.

        try {
            // Ensure poolFactory is initialized
            if ($this->poolFactory === null) {
                throw new \RuntimeException('Connection pool factory not initialized');
            }
            
            // Get Hyperf Connection from pool (REAL IMPLEMENTATION - connection pooling)
            // Each call gets a NEW connection from the pool, allowing true concurrent access
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
     * REFACTORED (Phase 5): Memory leak prevention
     * 
     * **Important:** Always call this method when done with a connection to prevent
     * memory leaks. While the pool timeout mechanism provides automatic cleanup,
     * explicit release is the best practice for optimal resource management.
     * 
     * The connection is:
     * 1. Removed from `$activeConnections` tracking array
     * 2. Released back to the Hyperf pool for reuse
     * 3. Pool handles connection health checks and timeout management
     * 
     * @param ConnectionInterface $connection The connection to release
     * @return void
     */
    public function releaseConnection(ConnectionInterface $connection): void
    {
        // REFACTORED (Phase 2): Search by connection object (not pool name) since we now use flat array
        // Find and remove from active connections using strict comparison
        $key = array_search($connection, $this->activeConnections, true);
        $found = ($key !== false);
        
        if ($found) {
            unset($this->activeConnections[$key]);
        }
        
        // REFACTORED (Phase 6): Added validation and logging
        // Only release if found in tracking (or log warning if not)
        if ($found) {
            // Connection was tracked - release normally
            $driver = $connection->getConnection();
            if ($driver !== null) {
                $connection->releaseConnection($driver);
            } else {
                // Driver is null, but still attempt release (adapter handles null)
                $connection->releaseConnection(null);
            }
        } else {
            // Connection not found in tracking - log warning but still attempt release
            // This might happen if:
            // - Connection was already released
            // - Connection was never tracked (edge case)
            // - Connection tracking was cleared
            // Still attempt release as it might be valid (just not tracked)
            if ($this->logger !== null) {
                $this->logger->warning('Attempted to release connection not found in activeConnections tracking');
            }
            
            $driver = $connection->getConnection();
            if ($driver !== null) {
                $connection->releaseConnection($driver);
            } else {
                $connection->releaseConnection(null);
            }
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
     * REFACTORED: Returns flat array of ConnectionInterface objects (not keyed by pool name).
     * This allows multiple connections from the same pool to be tracked.
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
     * Creates a SwooleConnectionAdapter wrapping the Hyperf Connection,
     * stores it in activeConnections (flat array), and logs in dev environment.
     * 
     * REFACTORED: Appends to array instead of keying by pool name, allowing
     * multiple connections from the same pool to be tracked.
     * 
     * @param Connection $hyperfConnection The Hyperf Connection instance
     * @param string $poolName The pool name for logging (not used for storage)
     * @return ConnectionInterface The created adapter
     */
    private function createAndStoreAdapter(Connection $hyperfConnection, string $poolName): ConnectionInterface
    {
        // Create adapter wrapping the Hyperf Connection
        $adapter = new SwooleConnectionAdapter($hyperfConnection);
        
        // REFACTORED: Append to flat array instead of keying by pool name
        // This allows multiple connections from the same pool
        $this->activeConnections[] = $adapter;
        
        // Log in dev environment
        if ($this->envDetect->isDevEnvironment) {
            $logger = $this->logger ?? new SwooleErrorLogLogger();
            $logger->info("SwooleConnection: New connection retrieved from pool: {$poolName}");
        }
        
        return $adapter;
    }

    /**
     * Clean up resources on destruction
     * 
     * REFACTORED (Phase 3): Added null safety and error handling to prevent crashes during cleanup.
     * Uses best-effort approach - continues cleanup even if individual connections fail.
     * 
     * REFACTORED (Phase 5): Memory leak prevention
     * 
     * This destructor ensures all tracked connections are released back to the pool,
     * preventing memory leaks in long-running processes. This is especially important
     * in OpenSwoole where the process may run for hours or days.
     * 
     * **Note:** While this provides cleanup on shutdown, applications should still
     * call `releaseConnection()` explicitly when done with a connection for optimal
     * resource management. The destructor is a safety net, not a replacement for
     * proper resource management.
     */
    public function __destruct()
    {
        // Release all active connections with null safety and error handling
        foreach ($this->activeConnections as $connection) {
            try {
                $driver = $connection->getConnection();
                // REFACTORED: Added null check - getConnection() can return null
                if ($driver !== null) {
                    $connection->releaseConnection($driver);
                } else {
                    // Log warning if driver is null (connection may be in invalid state)
                    if ($this->logger !== null) {
                        $this->logger->warning('Connection driver is null during __destruct cleanup');
                    }
                    // Still attempt release (adapter handles null internally)
                    $connection->releaseConnection(null);
                }
            } catch (\Throwable $e) {
                // Best-effort cleanup - log but don't fail
                // This ensures cleanup continues even if one connection fails
                // Important in long-running processes where connections may be in various states
                if ($this->logger !== null) {
                    $this->logger->error('Error releasing connection in __destruct: ' . $e->getMessage());
                }
            }
        }
        $this->activeConnections = [];
    }
}

