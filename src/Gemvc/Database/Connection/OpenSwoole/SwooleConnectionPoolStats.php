<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

/**
 * Immutable value object representing connection pool statistics.
 * 
 * This class encapsulates all pool statistics in a type-safe, immutable structure.
 * Follows SOLID principles and provides better OOP design than plain arrays.
 * 
 * **Design Pattern: Composition**
 * - Uses composition (HAS-A relationship) with PoolConfig and DatabaseConfig
 * - Created via factory method from SwooleConnection (composition, not inheritance)
 * - Stats represent data ABOUT the connection, not IS-A connection
 * 
 * **Features:**
 * - All properties are typed and readonly (immutable)
 * - Type-safe access to all statistics
 * - Can be converted to array for interface compatibility
 * - Better testability and maintainability
 * 
 * **Usage:**
 * ```php
 * $stats = $connection->getPoolStatsObject();
 * echo $stats->type;
 * echo $stats->poolConfig->minConnections;
 * echo $stats->config->driver;
 * ```
 */
class SwooleConnectionPoolStats
{
    /** @var string Connection manager type description */
    public readonly string $type;
    
    /** @var string Environment name */
    public readonly string $environment;
    
    /** @var string Execution context: 'openswoole', 'cli', or 'webserver' */
    public readonly string $executionContext;
    
    /** @var int Number of active connections */
    public readonly int $activeConnections;
    
    /** @var bool Whether the pool is initialized */
    public readonly bool $initialized;
    
    /** @var PoolConfig Pool configuration values (composition) */
    public readonly PoolConfig $poolConfig;
    
    /** @var DatabaseConfig Database configuration values (composition) */
    public readonly DatabaseConfig $config;

    /**
     * Constructor
     * 
     * @param string $type Connection manager type description
     * @param string $environment Environment name
     * @param string $executionContext Execution context
     * @param int $activeConnections Number of active connections
     * @param bool $initialized Whether the pool is initialized
     * @param PoolConfig $poolConfig Pool configuration (composition)
     * @param DatabaseConfig $config Database configuration (composition)
     */
    public function __construct(
        string $type,
        string $environment,
        string $executionContext,
        int $activeConnections,
        bool $initialized,
        PoolConfig $poolConfig,
        DatabaseConfig $config
    ) {
        $this->type = $type;
        $this->environment = $environment;
        $this->executionContext = $executionContext;
        $this->activeConnections = $activeConnections;
        $this->initialized = $initialized;
        $this->poolConfig = $poolConfig;
        $this->config = $config;
    }

    /**
     * Create instance from SwooleConnection and SwooleEnvDetect.
     * 
     * Factory method that creates a stats object from the connection manager
     * and environment detector instances. Uses composition to build the stats.
     * 
     * **Design: Composition Pattern**
     * - Stats is created FROM connection (composition, not inheritance)
     * - Uses factory methods from PoolConfig and DatabaseConfig
     * - No code duplication - delegates to composed objects
     * 
     * @param SwooleConnection $connection The connection manager instance
     * @param SwooleEnvDetect $envDetect The environment detector instance
     * @return self The created stats instance
     */
    public static function fromConnection(
        SwooleConnection $connection,
        SwooleEnvDetect $envDetect
    ): self {
        // Use factory methods from composed objects (no code duplication)
        $poolConfig = PoolConfig::fromEnvDetect($envDetect);
        $databaseConfig = DatabaseConfig::fromEnvDetect($envDetect);

        $activeConnections = $connection->getActiveConnections();

        return new self(
            'OpenSwoole Connection Manager (True Connection Pooling)',
            'OpenSwoole',
            $envDetect->executionContext,
            count($activeConnections),
            $connection->isInitialized(),
            $poolConfig,
            $databaseConfig
        );
    }

    /**
     * Convert to array for interface compatibility.
     * 
     * This method allows the stats object to be converted to an array
     * format that matches the original interface requirement.
     * 
     * @return array<string, mixed> Array representation of the stats
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'environment' => $this->environment,
            'execution_context' => $this->executionContext,
            'active_connections' => $this->activeConnections,
            'initialized' => $this->initialized,
            'pool_config' => $this->poolConfig->toArray(),
            'config' => $this->config->toArray(),
        ];
    }
}
