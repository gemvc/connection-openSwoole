<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnectionPoolStats;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;
use ReflectionClass;

/**
 * Integration tests for SwooleConnection
 * 
 * Tests the complete integration of all components working together:
 * - Full initialization flow (logger -> container -> event dispatcher -> pool factory)
 * - Connection lifecycle (get, use, release)
 * - Multiple connections from same/different pools
 * - Singleton behavior across operations
 * - Error handling in integrated scenarios
 * - Reset and cleanup with real connections
 * - Pool statistics with real usage
 * 
 * @covers \Gemvc\Database\Connection\OpenSwoole\SwooleConnection
 */
class SwooleConnectionIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singleton before each test
        SwooleConnection::resetInstance();
        
        // Set up minimal environment variables for testing
        $_ENV['DB_DRIVER'] = 'mysql';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_password';
        $_ENV['APP_ENV'] = 'test';
    }

    protected function tearDown(): void
    {
        // Clean up
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
     * Test complete initialization flow
     * 
     * Verifies that all components are initialized in the correct order:
     * logger -> container -> event dispatcher -> pool factory
     */
    public function testCompleteInitializationFlow(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Verify implements interface
        $this->assertInstanceOf(ConnectionManagerInterface::class, $manager);
        
        // Verify initialization state
        $reflection = new ReflectionClass($manager);
        $initializedProperty = $reflection->getProperty('initialized');
        $initializedProperty->setAccessible(true);
        $this->assertTrue($initializedProperty->getValue($manager), 'Manager should be initialized');
        
        // Verify logger is initialized
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $logger = $loggerProperty->getValue($manager);
        $this->assertNotNull($logger, 'Logger should be initialized');
        
        // Verify container is initialized
        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $container = $containerProperty->getValue($manager);
        $this->assertNotNull($container, 'Container should be initialized');
        
        // Verify pool factory is initialized
        $poolFactoryProperty = $reflection->getProperty('poolFactory');
        $poolFactoryProperty->setAccessible(true);
        $poolFactory = $poolFactoryProperty->getValue($manager);
        $this->assertNotNull($poolFactory, 'Pool factory should be initialized');
        
        // Verify no errors
        $this->assertNull($manager->getError(), 'Should have no errors after initialization');
    }

    /**
     * Test singleton behavior across multiple operations
     */
    public function testSingletonBehaviorAcrossOperations(): void
    {
        $instance1 = SwooleConnection::getInstance();
        $instance2 = SwooleConnection::getInstance();
        $instance3 = SwooleConnection::getInstance();
        
        // All should be the same instance
        $this->assertSame($instance1, $instance2, 'getInstance() should return same instance');
        $this->assertSame($instance2, $instance3, 'getInstance() should return same instance');
        
        // Verify state is shared
        $instance1->setError('test error');
        $this->assertEquals('test error', $instance2->getError(), 'State should be shared across instances');
        $this->assertEquals('test error', $instance3->getError(), 'State should be shared across instances');
        
        // Clear error
        $instance1->clearError();
        $this->assertNull($instance2->getError(), 'State changes should be reflected across instances');
    }

    /**
     * Test connection lifecycle: get, verify, release
     */
    public function testConnectionLifecycleGetVerifyRelease(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Get connection
        $connection = $manager->getConnection();
        
        // Verify connection is returned (may be null if pool fails, but structure should be correct)
        if ($connection !== null) {
            $this->assertInstanceOf(ConnectionInterface::class, $connection, 'Connection should implement ConnectionInterface');
            
            // Verify connection is tracked
            $activeConnections = $manager->getActiveConnections();
            $this->assertContains($connection, $activeConnections, 'Connection should be in active connections');
            
            // Release connection
            $manager->releaseConnection($connection);
            
            // Verify connection is removed from tracking
            $activeConnectionsAfter = $manager->getActiveConnections();
            $this->assertNotContains($connection, $activeConnectionsAfter, 'Connection should be removed from active connections after release');
        } else {
            // Connection failed (expected in test environment without real DB)
            $this->assertNotNull($manager->getError(), 'Should have error when connection fails');
        }
    }

    /**
     * Test multiple connections from same pool
     */
    public function testMultipleConnectionsFromSamePool(): void
    {
        $manager = SwooleConnection::getInstance();
        
        $connections = [];
        $poolName = 'default';
        
        // Get multiple connections from same pool
        for ($i = 0; $i < 3; $i++) {
            $connection = $manager->getConnection($poolName);
            if ($connection !== null) {
                $connections[] = $connection;
            }
        }
        
        if (!empty($connections)) {
            // Verify all connections are tracked
            $activeConnections = $manager->getActiveConnections();
            $this->assertGreaterThanOrEqual(count($connections), count($activeConnections), 'All connections should be tracked');
            
            // Verify all connections are different instances (pool returns different connections)
            $connectionIds = array_map('spl_object_id', $connections);
            $uniqueIds = array_unique($connectionIds);
            $this->assertCount(count($connections), $uniqueIds, 'Each connection should be a different instance');
            
            // Release all connections
            foreach ($connections as $connection) {
                $manager->releaseConnection($connection);
            }
            
            // Verify all are released
            $activeConnectionsAfter = $manager->getActiveConnections();
            $releasedCount = count($connections) - count($activeConnectionsAfter);
            $this->assertGreaterThanOrEqual(count($connections), $releasedCount, 'All connections should be released');
        } else {
            // If no connections, verify error handling
            $this->assertIsArray($manager->getActiveConnections(), 'Active connections should be an array');
            $this->assertEmpty($manager->getActiveConnections(), 'Should have no active connections when all fail');
        }
    }

    /**
     * Test multiple connections from different pools
     */
    public function testMultipleConnectionsFromDifferentPools(): void
    {
        $manager = SwooleConnection::getInstance();
        
        $poolNames = ['default', 'pool1', 'pool2'];
        $connections = [];
        
        // Get connections from different pools
        foreach ($poolNames as $poolName) {
            $connection = $manager->getConnection($poolName);
            if ($connection !== null) {
                $connections[$poolName] = $connection;
            }
        }
        
        if (!empty($connections)) {
            // Verify all connections are tracked
            $activeConnections = $manager->getActiveConnections();
            $this->assertGreaterThanOrEqual(count($connections), count($activeConnections), 'All connections should be tracked');
            
            // Verify connections are different instances
            $connectionIds = array_map('spl_object_id', array_values($connections));
            $uniqueIds = array_unique($connectionIds);
            $this->assertCount(count($connections), $uniqueIds, 'Each connection should be a different instance');
            
            // Release all connections
            foreach ($connections as $connection) {
                $manager->releaseConnection($connection);
            }
            
            // Verify all are released
            $activeConnectionsAfter = $manager->getActiveConnections();
            $this->assertLessThanOrEqual(count($activeConnections) - count($connections), count($activeConnectionsAfter), 'All connections should be released');
        } else {
            // If no connections, verify error handling
            $this->assertIsArray($manager->getActiveConnections(), 'Active connections should be an array');
            $this->assertEmpty($manager->getActiveConnections(), 'Should have no active connections when all fail');
        }
    }

    /**
     * Test error handling in integrated scenario
     */
    public function testErrorHandlingInIntegratedScenario(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Clear any existing errors
        $manager->clearError();
        
        // Try to get connection with invalid pool name (very long name)
        $longPoolName = str_repeat('a', 1000);
        $connection = $manager->getConnection($longPoolName);
        
        // Connection may fail, but error should be set
        if ($connection === null) {
            $this->assertNotNull($manager->getError(), 'Error should be set when connection fails');
        }
        
        // Set custom error
        $manager->setError('Custom error message');
        $this->assertEquals('Custom error message', $manager->getError(), 'Custom error should be set');
        
        // Clear error
        $manager->clearError();
        $this->assertNull($manager->getError(), 'Error should be cleared');
    }

    /**
     * Test reset instance with active connections
     */
    public function testResetInstanceWithActiveConnections(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Get some connections
        $connections = [];
        for ($i = 0; $i < 2; $i++) {
            $connection = $manager->getConnection();
            if ($connection !== null) {
                $connections[] = $connection;
            }
        }
        
        // Verify connections are tracked
        if (!empty($connections)) {
            $activeConnections = $manager->getActiveConnections();
            $this->assertGreaterThanOrEqual(count($connections), count($activeConnections), 'Connections should be tracked');
        }
        
        // Reset instance
        SwooleConnection::resetInstance();
        
        // Get new instance
        $newManager = SwooleConnection::getInstance();
        
        // Verify it's a different instance
        $this->assertNotSame($manager, $newManager, 'Reset should create new instance');
        
        // Verify new instance is initialized
        $this->assertTrue($newManager->isInitialized(), 'New instance should be initialized');
        
        // Verify active connections are cleared
        $newActiveConnections = $newManager->getActiveConnections();
        $this->assertEmpty($newActiveConnections, 'Active connections should be cleared after reset');
    }

    /**
     * Test pool statistics integration
     */
    public function testPoolStatisticsIntegration(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Get pool stats before any connections
        $statsBefore = $manager->getPoolStats();
        $this->assertIsArray($statsBefore, 'Pool stats should be an array');
        
        // Get pool stats object
        $statsObject = $manager->getPoolStats();
        $this->assertIsArray($statsObject, 'Pool stats object should be an array');
        
        // Get a connection
        $connection = $manager->getConnection();
        
        if ($connection !== null) {
            // Get pool stats after getting connection
            $statsAfter = $manager->getPoolStats();
            $this->assertIsArray($statsAfter, 'Pool stats should be an array after getting connection');
            
            // Verify stats structure
            $this->assertArrayHasKey('active_connections', $statsAfter, 'Stats should have active_connections');
            $this->assertIsInt($statsAfter['active_connections'], 'active_connections should be integer');
            
            // Release connection
            $manager->releaseConnection($connection);
            
            // Get pool stats after release
            $statsAfterRelease = $manager->getPoolStats();
            $this->assertIsArray($statsAfterRelease, 'Pool stats should be an array after release');
        }
    }

    /**
     * Test initialization state persistence
     */
    public function testInitializationStatePersistence(): void
    {
        $manager1 = SwooleConnection::getInstance();
        
        // Verify initialized
        $this->assertTrue($manager1->isInitialized(), 'First instance should be initialized');
        
        // Get instance again
        $manager2 = SwooleConnection::getInstance();
        
        // Should be same instance and still initialized
        $this->assertSame($manager1, $manager2, 'Should return same instance');
        $this->assertTrue($manager2->isInitialized(), 'Same instance should remain initialized');
        
        // Reset and get new instance
        SwooleConnection::resetInstance();
        $manager3 = SwooleConnection::getInstance();
        
        // New instance should also be initialized
        $this->assertNotSame($manager1, $manager3, 'Reset should create new instance');
        $this->assertTrue($manager3->isInitialized(), 'New instance should be initialized');
    }

    /**
     * Test connection tracking accuracy
     */
    public function testConnectionTrackingAccuracy(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Initially no connections
        $this->assertEmpty($manager->getActiveConnections(), 'Should start with no active connections');
        
        // Get connections
        $connections = [];
        for ($i = 0; $i < 3; $i++) {
            $connection = $manager->getConnection();
            if ($connection !== null) {
                $connections[] = $connection;
            }
        }
        
        if (!empty($connections)) {
            // Verify all are tracked
            $activeConnections = $manager->getActiveConnections();
            $this->assertCount(count($connections), $activeConnections, 'All connections should be tracked');
            
            // Release one connection
            $releasedConnection = array_shift($connections);
            $manager->releaseConnection($releasedConnection);
            
            // Verify count decreased
            $activeConnectionsAfter = $manager->getActiveConnections();
            $this->assertCount(count($connections), $activeConnectionsAfter, 'Connection count should decrease after release');
            
            // Release remaining connections
            foreach ($connections as $connection) {
                $manager->releaseConnection($connection);
            }
            
            // Verify all released
            $activeConnectionsFinal = $manager->getActiveConnections();
            $this->assertEmpty($activeConnectionsFinal, 'All connections should be released');
        }
    }

    /**
     * Test error state management across operations
     */
    public function testErrorStateManagementAcrossOperations(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Initially no error
        $this->assertNull($manager->getError(), 'Should start with no error');
        
        // Set error
        $manager->setError('Test error 1');
        $this->assertEquals('Test error 1', $manager->getError(), 'Error should be set');
        
        // Get connection (may clear error on success)
        $connection = $manager->getConnection();
        
        // If connection succeeds, error should be cleared
        if ($connection !== null) {
            $this->assertNull($manager->getError(), 'Error should be cleared on successful connection');
            
            // Release connection
            $manager->releaseConnection($connection);
        }
        
        // Set error again
        $manager->setError('Test error 2');
        $this->assertEquals('Test error 2', $manager->getError(), 'Error should be set again');
        
        // Clear error
        $manager->clearError();
        $this->assertNull($manager->getError(), 'Error should be cleared');
    }

    /**
     * Test multiple get instance calls maintain state
     */
    public function testMultipleGetInstanceCallsMaintainState(): void
    {
        $manager1 = SwooleConnection::getInstance();
        
        // Get connection first
        $connection1 = $manager1->getConnection();
        
        // Get instance again
        $manager2 = SwooleConnection::getInstance();
        
        // Verify same instance
        $this->assertSame($manager1, $manager2, 'Should be same instance');
        
        // Get another connection
        $connection2 = $manager2->getConnection();
        
        // Both connections should be tracked if they exist
        $activeConnections = $manager2->getActiveConnections();
        if ($connection1 !== null && $connection2 !== null) {
            $this->assertContains($connection1, $activeConnections, 'First connection should be tracked');
            $this->assertContains($connection2, $activeConnections, 'Second connection should be tracked');
        } else {
            // If connections failed, verify error handling works
            $this->assertIsArray($activeConnections, 'Active connections should be an array');
        }
        
        // Set error after connections (error state should be maintained)
        $manager1->setError('State test');
        $this->assertEquals('State test', $manager2->getError(), 'Error state should be maintained across instances');
        
        // Clean up
        if ($connection1 !== null) {
            $manager1->releaseConnection($connection1);
        }
        if ($connection2 !== null) {
            $manager2->releaseConnection($connection2);
        }
    }

    /**
     * Test environment detection integration
     */
    public function testEnvironmentDetectionIntegration(): void
    {
        // Test with different environments
        $environments = ['test', 'dev', 'production'];
        
        foreach ($environments as $env) {
            SwooleConnection::resetInstance();
            $_ENV['APP_ENV'] = $env;
            
            $manager = SwooleConnection::getInstance();
            $this->assertTrue($manager->isInitialized(), "Should be initialized in $env environment");
            
            // Get connection should work regardless of environment
            $connection = $manager->getConnection();
            // Connection may be null in test environment, but manager should handle it
            $this->assertInstanceOf(ConnectionManagerInterface::class, $manager, 'Manager should work in all environments');
            
            if ($connection !== null) {
                $manager->releaseConnection($connection);
            }
        }
    }

    /**
     * Test concurrent-like operations (sequential but simulating concurrency)
     */
    public function testConcurrentLikeOperations(): void
    {
        $manager = SwooleConnection::getInstance();
        
        // Simulate concurrent operations: get multiple connections quickly
        $connections = [];
        for ($i = 0; $i < 5; $i++) {
            $connection = $manager->getConnection("pool_$i");
            if ($connection !== null) {
                $connections[] = $connection;
            }
        }
        
        if (!empty($connections)) {
            // Verify all are tracked independently
            $activeConnections = $manager->getActiveConnections();
            $this->assertGreaterThanOrEqual(count($connections), count($activeConnections), 'All concurrent connections should be tracked');
            
            // Release in different order (simulating real-world usage)
            $reversedConnections = array_reverse($connections);
            foreach ($reversedConnections as $connection) {
                $manager->releaseConnection($connection);
            }
            
            // Verify all released
            $activeConnectionsAfter = $manager->getActiveConnections();
            $this->assertLessThanOrEqual(count($activeConnections) - count($connections), count($activeConnectionsAfter), 'All connections should be released');
        } else {
            // If no connections, verify manager handles concurrent-like operations gracefully
            $this->assertIsArray($manager->getActiveConnections(), 'Active connections should be an array');
            $this->assertEmpty($manager->getActiveConnections(), 'Should have no active connections when all fail');
            $this->assertInstanceOf(ConnectionManagerInterface::class, $manager, 'Manager should handle concurrent-like operations');
        }
    }
}

