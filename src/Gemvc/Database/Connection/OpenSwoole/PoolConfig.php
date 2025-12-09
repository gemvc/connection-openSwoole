<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

/**
 * Immutable value object for pool configuration.
 * 
 * Encapsulates connection pool configuration values in a type-safe, immutable structure.
 * Used by SwooleConnectionPoolStats for better OOP design.
 */
class PoolConfig
{
    /** @var int Minimum connection pool size */
    public readonly int $minConnections;
    
    /** @var int Maximum connection pool size */
    public readonly int $maxConnections;
    
    /** @var float Connection timeout in seconds */
    public readonly float $connectTimeout;
    
    /** @var float Wait timeout in seconds */
    public readonly float $waitTimeout;
    
    /** @var int Heartbeat interval (-1 if disabled) */
    public readonly int $heartbeat;
    
    /** @var float Maximum idle time in seconds */
    public readonly float $maxIdleTime;

    /**
     * Constructor
     * 
     * @param int $minConnections Minimum connection pool size
     * @param int $maxConnections Maximum connection pool size
     * @param float $connectTimeout Connection timeout in seconds
     * @param float $waitTimeout Wait timeout in seconds
     * @param int $heartbeat Heartbeat interval (-1 if disabled)
     * @param float $maxIdleTime Maximum idle time in seconds
     */
    public function __construct(
        int $minConnections,
        int $maxConnections,
        float $connectTimeout,
        float $waitTimeout,
        int $heartbeat,
        float $maxIdleTime
    ) {
        $this->minConnections = $minConnections;
        $this->maxConnections = $maxConnections;
        $this->connectTimeout = $connectTimeout;
        $this->waitTimeout = $waitTimeout;
        $this->heartbeat = $heartbeat;
        $this->maxIdleTime = $maxIdleTime;
    }

    /**
     * Create instance from SwooleEnvDetect.
     * 
     * Factory method that creates a PoolConfig from environment detector.
     * Uses composition to extract pool configuration values.
     * 
     * @param SwooleEnvDetect $envDetect The environment detector instance
     * @return self The created PoolConfig instance
     */
    public static function fromEnvDetect(SwooleEnvDetect $envDetect): self
    {
        return new self(
            $envDetect->minConnectionPool,
            $envDetect->maxConnectionPool,
            $envDetect->connectionTimeout,
            $envDetect->waitTimeout,
            $envDetect->heartbeat,
            $envDetect->maxIdleTime
        );
    }

    /**
     * Convert to array.
     * 
     * @return array<string, int|float> Array representation
     */
    public function toArray(): array
    {
        return [
            'min_connections' => $this->minConnections,
            'max_connections' => $this->maxConnections,
            'connect_timeout' => $this->connectTimeout,
            'wait_timeout' => $this->waitTimeout,
            'heartbeat' => $this->heartbeat,
            'max_idle_time' => $this->maxIdleTime,
        ];
    }
}

