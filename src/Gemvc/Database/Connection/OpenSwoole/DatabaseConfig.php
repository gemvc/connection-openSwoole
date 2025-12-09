<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

/**
 * Immutable value object for database configuration.
 * 
 * Encapsulates database configuration values in a type-safe, immutable structure.
 * Used by SwooleConnectionPoolStats for better OOP design.
 */
class DatabaseConfig
{
    /** @var string Database driver */
    public readonly string $driver;
    
    /** @var string Database host */
    public readonly string $host;
    
    /** @var string Database name */
    public readonly string $database;

    /**
     * Constructor
     * 
     * @param string $driver Database driver
     * @param string $host Database host
     * @param string $database Database name
     */
    public function __construct(
        string $driver,
        string $host,
        string $database
    ) {
        $this->driver = $driver;
        $this->host = $host;
        $this->database = $database;
    }

    /**
     * Create instance from SwooleEnvDetect.
     * 
     * Factory method that creates a DatabaseConfig from environment detector.
     * Uses composition to extract database configuration values.
     * 
     * @param SwooleEnvDetect $envDetect The environment detector instance
     * @return self The created DatabaseConfig instance
     */
    public static function fromEnvDetect(SwooleEnvDetect $envDetect): self
    {
        return new self(
            $envDetect->dbDriver,
            $envDetect->dbHost,
            $envDetect->dbName
        );
    }

    /**
     * Convert to array.
     * 
     * @return array<string, string> Array representation
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'database' => $this->database,
        ];
    }
}

