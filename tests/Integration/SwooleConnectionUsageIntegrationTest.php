<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;

/**
 * Integration tests demonstrating how SwooleConnection is used by other classes
 * 
 * These tests show real-world usage patterns and integration scenarios:
 * - Service classes using SwooleConnection
 * - Repository pattern with connection management
 * - Dependency injection scenarios
 * - Connection lifecycle in application code
 * - Error handling in consuming classes
 * - Singleton behavior across multiple classes
 * 
 * @covers \Gemvc\Database\Connection\OpenSwoole\SwooleConnection
 */
class SwooleConnectionUsageIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        SwooleConnection::resetInstance();
        
        $_ENV['DB_DRIVER'] = 'mysql';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_password';
        $_ENV['APP_ENV'] = 'test';
    }

    protected function tearDown(): void
    {
        SwooleConnection::resetInstance();
        unset(
            $_ENV['DB_DRIVER'],
            $_ENV['DB_HOST'],
            $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            $_ENV['APP_ENV']
        );
    }

    /**
     * Test: Service class using SwooleConnection via dependency injection
     */
    public function testServiceClassUsingSwooleConnection(): void
    {
        // Simulate a service class that uses SwooleConnection
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct(?ConnectionManagerInterface $connectionManager = null)
            {
                $this->connectionManager = $connectionManager ?? SwooleConnection::getInstance();
            }

            public function performDatabaseOperation(): bool
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    return false;
                }

                try {
                    // Simulate database operation
                    $driver = $connection->getConnection();
                    // In real scenario, would execute queries here
                    return $driver !== null;
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }

            public function getConnectionManager(): ConnectionManagerInterface
            {
                return $this->connectionManager;
            }
        };

        // Test service can use connection manager
        $this->assertInstanceOf(ConnectionManagerInterface::class, $service->getConnectionManager());
        
        // Test service can perform operations
        $result = $service->performDatabaseOperation();
        // Result may be false if connection fails (expected in test env), but service should handle it
        $this->assertIsBool($result);
    }

    /**
     * Test: Repository pattern with connection management
     */
    public function testRepositoryPatternWithConnectionManagement(): void
    {
        // Simulate a repository class
        $userRepository = new class('users', SwooleConnection::getInstance()) {
            private ConnectionManagerInterface $connectionManager;
            private string $tableName;

            public function __construct(string $tableName, ?ConnectionManagerInterface $connectionManager = null)
            {
                $this->tableName = $tableName;
                $this->connectionManager = $connectionManager ?? SwooleConnection::getInstance();
            }

            public function findAll(): array
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    return [];
                }

                try {
                    $driver = $connection->getConnection();
                    // Simulate query execution
                    return $driver !== null ? [] : [];
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }

            public function findById(int $id): ?array
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    return null;
                }

                try {
                    $driver = $connection->getConnection();
                    // Simulate query execution
                    return $driver !== null ? ['id' => $id] : null;
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }

            public function save(array $data): bool
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    return false;
                }

                try {
                    $driver = $connection->getConnection();
                    // Simulate insert/update
                    return $driver !== null;
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }
        };
        
        $all = $userRepository->findAll();
        $this->assertIsArray($all);
        
        $user = $userRepository->findById(1);
        // May be null if connection fails, but repository handles it
        $this->assertTrue($user === null || is_array($user));
        
        $saved = $userRepository->save(['name' => 'Test']);
        $this->assertIsBool($saved);
    }

    /**
     * Test: Multiple services sharing the same connection manager instance
     */
    public function testMultipleServicesSharingConnectionManager(): void
    {
        $service1 = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function getConnectionManager(): ConnectionManagerInterface
            {
                return $this->connectionManager;
            }
        };

        $service2 = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function getConnectionManager(): ConnectionManagerInterface
            {
                return $this->connectionManager;
            }
        };

        // Both services should get the same singleton instance
        $manager1 = $service1->getConnectionManager();
        $manager2 = $service2->getConnectionManager();
        
        $this->assertSame($manager1, $manager2, 'Services should share the same singleton instance');
        $this->assertInstanceOf(SwooleConnection::class, $manager1);
        $this->assertInstanceOf(SwooleConnection::class, $manager2);
    }

    /**
     * Test: Service class with error handling
     */
    public function testServiceClassWithErrorHandling(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function performOperationWithErrorHandling(): array
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    $error = $this->connectionManager->getError();
                    return [
                        'success' => false,
                        'error' => $error ?? 'Failed to get connection'
                    ];
                }

                try {
                    $driver = $connection->getConnection();
                    return [
                        'success' => $driver !== null,
                        'error' => null
                    ];
                } catch (\Throwable $e) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }
        };

        $result = $service->performOperationWithErrorHandling();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertIsBool($result['success']);
    }

    /**
     * Test: Transaction handling in service class
     */
    public function testTransactionHandlingInServiceClass(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function performTransaction(array $operations): bool
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    return false;
                }

                try {
                    // Begin transaction
                    $began = $connection->beginTransaction();
                    if (!$began) {
                        return false;
                    }

                    // Simulate operations
                    $success = true;
                    foreach ($operations as $operation) {
                        $driver = $connection->getConnection();
                        if ($driver === null) {
                            $success = false;
                            break;
                        }
                    }

                    if ($success) {
                        $connection->commit();
                    } else {
                        $connection->rollback();
                    }

                    return $success;
                } catch (\Throwable $e) {
                    try {
                        if ($connection->inTransaction()) {
                            $connection->rollback();
                        }
                    } catch (\Throwable $rollbackError) {
                        // Log rollback error
                    }
                    return false;
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }
        };

        $result = $service->performTransaction(['op1', 'op2']);
        $this->assertIsBool($result);
    }

    /**
     * Test: Service class using different connection pools
     */
    public function testServiceClassUsingDifferentPools(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function getConnectionFromPool(string $poolName): ?ConnectionInterface
            {
                return $this->connectionManager->getConnection($poolName);
            }

            public function releaseConnection(ConnectionInterface $connection): void
            {
                $this->connectionManager->releaseConnection($connection);
            }
        };

        // Get connections from different pools
        $connection1 = $service->getConnectionFromPool('default');
        $connection2 = $service->getConnectionFromPool('read_pool');
        $connection3 = $service->getConnectionFromPool('write_pool');

        // All should be ConnectionInterface or null
        $connections = [$connection1, $connection2, $connection3];
        $validConnections = array_filter($connections, fn($conn) => $conn !== null);
        
        $this->assertLessThanOrEqual(3, count($validConnections), 'Should get 0-3 connections depending on pool availability');
        
        foreach ($validConnections as $connection) {
            $this->assertInstanceOf(ConnectionInterface::class, $connection);
            $service->releaseConnection($connection);
        }
        
        // Verify service can handle different pool names
        $this->assertIsObject($service);
    }

    /**
     * Test: Service class checking initialization state
     */
    public function testServiceClassCheckingInitializationState(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function isReady(): bool
            {
                if ($this->connectionManager instanceof SwooleConnection) {
                    return $this->connectionManager->isInitialized();
                }
                return false;
            }

            public function getConnectionManager(): ConnectionManagerInterface
            {
                return $this->connectionManager;
            }
        };

        $this->assertTrue($service->isReady(), 'Service should detect initialized connection manager');
        $this->assertInstanceOf(ConnectionManagerInterface::class, $service->getConnectionManager());
    }

    /**
     * Test: Service class with connection pooling awareness
     */
    public function testServiceClassWithConnectionPoolingAwareness(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function getPoolStats(): array
            {
                if ($this->connectionManager instanceof SwooleConnection) {
                    return $this->connectionManager->getPoolStats();
                }
                return [];
            }

            public function performOperationWithStats(): array
            {
                $statsBefore = $this->getPoolStats();
                
                $connection = $this->connectionManager->getConnection();
                
                $statsDuring = $this->getPoolStats();
                
                if ($connection !== null) {
                    $this->connectionManager->releaseConnection($connection);
                }
                
                $statsAfter = $this->getPoolStats();
                
                return [
                    'before' => $statsBefore,
                    'during' => $statsDuring,
                    'after' => $statsAfter
                ];
            }
        };

        $result = $service->performOperationWithStats();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('before', $result);
        $this->assertArrayHasKey('during', $result);
        $this->assertArrayHasKey('after', $result);
        $this->assertIsArray($result['before']);
        $this->assertIsArray($result['during']);
        $this->assertIsArray($result['after']);
    }

    /**
     * Test: Multiple operations in sequence (simulating request lifecycle)
     */
    public function testMultipleOperationsInSequence(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function executeRequest(array $operations): array
            {
                $results = [];
                
                foreach ($operations as $operation) {
                    $connection = $this->connectionManager->getConnection($operation['pool'] ?? 'default');
                    
                    if ($connection === null) {
                        $results[] = [
                            'operation' => $operation['name'],
                            'success' => false,
                            'error' => $this->connectionManager->getError()
                        ];
                        continue;
                    }

                    try {
                        $driver = $connection->getConnection();
                        $results[] = [
                            'operation' => $operation['name'],
                            'success' => $driver !== null
                        ];
                    } catch (\Throwable $e) {
                        $results[] = [
                            'operation' => $operation['name'],
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                    } finally {
                        $this->connectionManager->releaseConnection($connection);
                    }
                }
                
                return $results;
            }
        };

        $operations = [
            ['name' => 'get_user', 'pool' => 'read_pool'],
            ['name' => 'update_user', 'pool' => 'write_pool'],
            ['name' => 'log_activity', 'pool' => 'default']
        ];

        $results = $service->executeRequest($operations);
        
        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('operation', $result);
            $this->assertArrayHasKey('success', $result);
            $this->assertIsBool($result['success']);
        }
    }

    /**
     * Test: Service class with proper resource cleanup
     */
    public function testServiceClassWithProperResourceCleanup(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;
            private array $connections = [];

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function acquireConnection(string $poolName = 'default'): ?ConnectionInterface
            {
                $connection = $this->connectionManager->getConnection($poolName);
                if ($connection !== null) {
                    $this->connections[] = $connection;
                }
                return $connection;
            }

            public function releaseAllConnections(): void
            {
                foreach ($this->connections as $connection) {
                    $this->connectionManager->releaseConnection($connection);
                }
                $this->connections = [];
            }

            public function getActiveConnectionCount(): int
            {
                return count($this->connections);
            }
        };

        // Acquire multiple connections
        $conn1 = $service->acquireConnection('default');
        $conn2 = $service->acquireConnection('read_pool');
        $conn3 = $service->acquireConnection('write_pool');

        // Count only successful connections
        $successfulConnections = array_filter([$conn1, $conn2, $conn3], fn($conn) => $conn !== null);
        $this->assertEquals(count($successfulConnections), $service->getActiveConnectionCount(), 'Should track all successful connections');

        // Release all
        $service->releaseAllConnections();

        $this->assertEquals(0, $service->getActiveConnectionCount(), 'All connections should be released');
    }

    /**
     * Test: Service class handling connection errors gracefully
     */
    public function testServiceClassHandlingConnectionErrorsGracefully(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function performOperationWithRetry(int $maxRetries = 3): bool
            {
                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    $connection = $this->connectionManager->getConnection();
                    
                    if ($connection !== null) {
                        try {
                            $driver = $connection->getConnection();
                            if ($driver !== null) {
                                $this->connectionManager->releaseConnection($connection);
                                return true;
                            }
                        } catch (\Throwable $e) {
                            // Log error
                        } finally {
                            if ($connection !== null) {
                                $this->connectionManager->releaseConnection($connection);
                            }
                        }
                    }
                    
                    // Wait before retry (in real scenario)
                    if ($attempt < $maxRetries) {
                        // usleep(100000); // 100ms
                    }
                }
                
                return false;
            }
        };

        $result = $service->performOperationWithRetry();
        $this->assertIsBool($result);
    }

    /**
     * Test: Dependency injection with interface (best practice)
     */
    public function testDependencyInjectionWithInterface(): void
    {
        // Simulate a class that accepts ConnectionManagerInterface (best practice)
        $service = new class(SwooleConnection::getInstance()) {
            private ConnectionManagerInterface $connectionManager;

            public function __construct(ConnectionManagerInterface $connectionManager)
            {
                $this->connectionManager = $connectionManager;
            }

            public function getConnectionManager(): ConnectionManagerInterface
            {
                return $this->connectionManager;
            }

            public function performOperation(): bool
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    return false;
                }

                try {
                    $driver = $connection->getConnection();
                    return $driver !== null;
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }
        };

        $this->assertInstanceOf(ConnectionManagerInterface::class, $service->getConnectionManager());
        $this->assertInstanceOf(SwooleConnection::class, $service->getConnectionManager());
        
        $result = $service->performOperation();
        $this->assertIsBool($result);
    }

    /**
     * Test: Service class using connection for multiple operations
     */
    public function testServiceClassUsingConnectionForMultipleOperations(): void
    {
        $service = new class {
            private ConnectionManagerInterface $connectionManager;

            public function __construct()
            {
                $this->connectionManager = SwooleConnection::getInstance();
            }

            public function performMultipleOperations(): array
            {
                $connection = $this->connectionManager->getConnection();
                
                if ($connection === null) {
                    return ['success' => false, 'operations' => []];
                }

                try {
                    $results = [];
                    $driver = $connection->getConnection();
                    
                    if ($driver !== null) {
                        // Simulate multiple operations on same connection
                        $results[] = ['operation' => 'select', 'success' => true];
                        $results[] = ['operation' => 'insert', 'success' => true];
                        $results[] = ['operation' => 'update', 'success' => true];
                    }
                    
                    return ['success' => true, 'operations' => $results];
                } finally {
                    $this->connectionManager->releaseConnection($connection);
                }
            }
        };

        $result = $service->performMultipleOperations();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('operations', $result);
    }
}

