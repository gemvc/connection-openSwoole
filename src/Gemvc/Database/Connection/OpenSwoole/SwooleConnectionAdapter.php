<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

use Gemvc\Database\Connection\Contracts\ConnectionInterface;
use Hyperf\DbConnection\Connection;
use PDO;
use Throwable;

/**
 * Swoole Connection Adapter
 * 
 * Adapts a Hyperf Connection to implement ConnectionInterface from gemvc/connection-contracts.
 * This allows Swoole connections to work with the new contracts system.
 * 
 * **Purpose:**
 * - Wraps Hyperf Connection objects for use with connection contracts
 * - Implements ConnectionInterface (from connection-contracts package)
 * - Provides transaction management (on Connection, not Manager)
 * - Handles connection state and error management
 * - Properly releases connections back to Hyperf pool
 * 
 * **Architecture:**
 * - Part of gemvc/connection-openswoole package
 * - Depends on gemvc/connection-contracts (ConnectionInterface)
 * - Used by `SwooleConnection` to wrap Hyperf connections
 * - Manages PDO extraction from Hyperf Connection
 * 
 * **Usage:**
 * ```php
 * use Gemvc\Database\Connection\OpenSwoole\SwooleConnectionAdapter;
 * use Hyperf\DbConnection\Connection;
 * 
 * $hyperfConnection = $pool->get(); // Get from Hyperf pool
 * $adapter = new SwooleConnectionAdapter($hyperfConnection);
 * 
 * // Now implements ConnectionInterface
 * $adapter->beginTransaction();
 * $adapter->commit();
 * ```
 */
class SwooleConnectionAdapter implements ConnectionInterface
{
    private ?Connection $hyperfConnection = null;
    private ?PDO $pdo = null;
    private ?string $error = null;
    private bool $initialized = false;
    private bool $inTransaction = false;

    /**
     * Constructor
     * 
     * @param Connection|null $hyperfConnection The Hyperf connection instance
     */
    public function __construct(?Connection $hyperfConnection = null)
    {
        $this->hyperfConnection = $hyperfConnection;
        if ($hyperfConnection !== null) {
            // Extract PDO from Hyperf connection
            // @phpstan-ignore-next-line - getPdo() is available via __call magic method
            $this->pdo = $hyperfConnection->getPdo();
            // getPdo() returns \PDO (non-nullable per stub), so initialized is always true here
            $this->initialized = true;
        }
    }

    /**
     * Get the underlying database connection object
     * 
     * Returns the PDO instance extracted from the Hyperf Connection.
     * 
     * @return object|null The PDO connection object or null on failure
     */
    public function getConnection(): ?object
    {
        return $this->pdo;
    }

    /**
     * Release the connection back to the pool
     * 
     * Releases the Hyperf connection back to the pool.
     * 
     * @param object|null $connection The connection object to release (ignored, uses internal Hyperf connection)
     * @return void
     */
    public function releaseConnection(?object $connection): void
    {
        if ($this->hyperfConnection !== null) {
            try {
                $this->hyperfConnection->release();
            } catch (Throwable $e) {
                // Best-effort release; log if needed
                error_log('SwooleConnectionAdapter release failed: ' . $e->getMessage());
            }
            $this->hyperfConnection = null;
            $this->pdo = null;
            $this->inTransaction = false;
        }
    }

    /**
     * Begin a database transaction
     * 
     * @return bool True on success, false on failure
     */
    public function beginTransaction(): bool
    {
        if ($this->pdo === null) {
            $this->setError('No connection available');
            return false;
        }

        if ($this->inTransaction) {
            $this->setError('Already in transaction');
            return false;
        }

        try {
            $result = $this->pdo->beginTransaction();
            $this->inTransaction = $result;
            return $result;
        } catch (\PDOException $e) {
            $this->setError('Failed to begin transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Commit the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        if ($this->pdo === null) {
            $this->setError('No connection available');
            return false;
        }

        if (!$this->inTransaction) {
            $this->setError('No active transaction to commit');
            return false;
        }

        try {
            $result = $this->pdo->commit();
            $this->inTransaction = false;
            return $result;
        } catch (\PDOException $e) {
            $this->setError('Failed to commit transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback the current transaction
     * 
     * @return bool True on success, false on failure
     */
    public function rollback(): bool
    {
        if ($this->pdo === null) {
            $this->setError('No connection available');
            return false;
        }

        if (!$this->inTransaction) {
            $this->setError('No active transaction to rollback');
            return false;
        }

        try {
            $result = $this->pdo->rollBack();
            $this->inTransaction = false;
            return $result;
        } catch (\PDOException $e) {
            $this->setError('Failed to rollback transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if currently in a transaction
     * 
     * @return bool True if in transaction, false otherwise
     */
    public function inTransaction(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        // Use both our tracking and PDO's native check
        return $this->inTransaction || $this->pdo->inTransaction();
    }

    /**
     * Get the last error message
     * 
     * @return string|null Error message or null if no error
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Set an error message
     * 
     * @param string|null $error Error message
     * @param array<string, mixed> $context Additional error context
     * @return void
     */
    public function setError(?string $error, array $context = []): void
    {
        $this->error = $error;
        if ($error !== null && !empty($context)) {
            $this->error .= ' | Context: ' . json_encode($context);
        }
    }

    /**
     * Clear the current error state
     * 
     * @return void
     */
    public function clearError(): void
    {
        $this->error = null;
    }

    /**
     * Check if the connection is initialized
     * 
     * @return bool True if initialized, false otherwise
     */
    public function isInitialized(): bool
    {
        return $this->initialized && $this->pdo !== null && $this->hyperfConnection !== null;
    }

    /**
     * Get the underlying Hyperf connection (for internal use)
     * 
     * @return Connection|null The Hyperf connection instance
     */
    public function getHyperfConnection(): ?Connection
    {
        return $this->hyperfConnection;
    }
}

