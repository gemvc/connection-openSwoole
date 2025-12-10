<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Performance;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;

/**
 * Performance tests for SwooleConnection
 * 
 * These tests measure performance characteristics:
 * - Initialization time
 * - Connection acquisition overhead
 * - Multiple connection handling
 * - Memory usage
 * - Pool efficiency
 * 
 * Note: These tests measure the connection manager overhead.
 * Actual database connection performance depends on database server.
 * 
 * @group performance
 */
class SwooleConnectionPerformanceTest extends TestCase
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
     * Test: Singleton initialization performance
     * 
     * Measures time to initialize the connection manager singleton.
     * Should be fast (< 100ms typically).
     */
    public function testSingletonInitializationPerformance(): void
    {
        $iterations = 10;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            SwooleConnection::resetInstance();
            
            $start = microtime(true);
            $manager = SwooleConnection::getInstance();
            $end = microtime(true);
            
            $times[] = ($end - $start) * 1000; // Convert to milliseconds
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        // Log results
        echo "\n[Performance] Singleton Initialization:\n";
        echo "  Average: " . number_format($avgTime, 2) . " ms\n";
        echo "  Min: " . number_format($minTime, 2) . " ms\n";
        echo "  Max: " . number_format($maxTime, 2) . " ms\n";

        // Assertions: Initialization should be reasonably fast
        $this->assertLessThan(500, $avgTime, 'Average initialization time should be < 500ms');
        $this->assertLessThan(1000, $maxTime, 'Max initialization time should be < 1000ms');
    }

    /**
     * Test: Connection acquisition performance
     * 
     * Measures time to acquire connections from the pool.
     * Tests both successful and failed connection attempts.
     */
    public function testConnectionAcquisitionPerformance(): void
    {
        $manager = SwooleConnection::getInstance();
        $iterations = 50;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $connection = $manager->getConnection();
            $end = microtime(true);
            
            $times[] = ($end - $start) * 1000; // Convert to milliseconds
            
            if ($connection !== null) {
                $manager->releaseConnection($connection);
            }
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        // Log results
        echo "\n[Performance] Connection Acquisition:\n";
        echo "  Iterations: $iterations\n";
        echo "  Average: " . number_format($avgTime, 2) . " ms\n";
        echo "  Min: " . number_format($minTime, 2) . " ms\n";
        echo "  Max: " . number_format($maxTime, 2) . " ms\n";

        // Assertions: Connection acquisition should be fast
        $this->assertLessThan(100, $avgTime, 'Average connection acquisition should be < 100ms');
    }

    /**
     * Test: Multiple connection acquisition performance
     * 
     * Measures performance when acquiring multiple connections sequentially.
     */
    public function testMultipleConnectionAcquisitionPerformance(): void
    {
        $manager = SwooleConnection::getInstance();
        $connectionCount = 20;
        $connections = [];

        $start = microtime(true);
        for ($i = 0; $i < $connectionCount; $i++) {
            $connections[] = $manager->getConnection();
        }
        $end = microtime(true);

        $totalTime = ($end - $start) * 1000; // Convert to milliseconds
        $avgTimePerConnection = $totalTime / $connectionCount;

        // Release connections
        foreach ($connections as $connection) {
            if ($connection !== null) {
                $manager->releaseConnection($connection);
            }
        }

        // Log results
        echo "\n[Performance] Multiple Connection Acquisition:\n";
        echo "  Connections: $connectionCount\n";
        echo "  Total Time: " . number_format($totalTime, 2) . " ms\n";
        echo "  Average per Connection: " . number_format($avgTimePerConnection, 2) . " ms\n";

        // Assertions
        $this->assertLessThan(2000, $totalTime, 'Total time for 20 connections should be < 2000ms');
        $this->assertLessThan(100, $avgTimePerConnection, 'Average time per connection should be < 100ms');
    }

    /**
     * Test: Connection release performance
     * 
     * Measures time to release connections back to the pool.
     */
    public function testConnectionReleasePerformance(): void
    {
        $manager = SwooleConnection::getInstance();
        $iterations = 50;
        $connections = [];
        $times = [];

        // Acquire connections first
        for ($i = 0; $i < $iterations; $i++) {
            $connections[] = $manager->getConnection();
        }

        // Measure release time
        foreach ($connections as $connection) {
            if ($connection !== null) {
                $start = microtime(true);
                $manager->releaseConnection($connection);
                $end = microtime(true);
                
                $times[] = ($end - $start) * 1000; // Convert to milliseconds
            }
        }

        $avgTime = !empty($times) ? array_sum($times) / count($times) : 0;
        $minTime = !empty($times) ? min($times) : 0;
        $maxTime = !empty($times) ? max($times) : 0;

        // Log results
        echo "\n[Performance] Connection Release:\n";
        echo "  Iterations: $iterations\n";
        echo "  Average: " . number_format($avgTime, 2) . " ms\n";
        echo "  Min: " . number_format($minTime, 2) . " ms\n";
        echo "  Max: " . number_format($maxTime, 2) . " ms\n";

        // Assertions: Release should be very fast
        if (!empty($times)) {
            $this->assertLessThan(50, $avgTime, 'Average release time should be < 50ms');
        } else {
            // If no connections were acquired, verify manager still works
            $this->assertInstanceOf(ConnectionManagerInterface::class, $manager);
            $this->assertIsArray($times);
        }
    }

    /**
     * Test: Memory usage during connection operations
     * 
     * Measures memory usage when acquiring and releasing connections.
     */
    public function testMemoryUsageDuringOperations(): void
    {
        $manager = SwooleConnection::getInstance();
        
        $initialMemory = memory_get_usage(true);
        
        // Acquire multiple connections
        $connections = [];
        for ($i = 0; $i < 10; $i++) {
            $connections[] = $manager->getConnection();
        }
        
        $peakMemory = memory_get_usage(true);
        
        // Release all connections
        foreach ($connections as $connection) {
            if ($connection !== null) {
                $manager->releaseConnection($connection);
            }
        }
        
        $finalMemory = memory_get_usage(true);
        
        $memoryUsed = $peakMemory - $initialMemory;
        $memoryAfterRelease = $finalMemory - $initialMemory;

        // Log results
        echo "\n[Performance] Memory Usage:\n";
        echo "  Initial: " . number_format($initialMemory / 1024, 2) . " KB\n";
        echo "  Peak (10 connections): " . number_format($peakMemory / 1024, 2) . " KB\n";
        echo "  Final (after release): " . number_format($finalMemory / 1024, 2) . " KB\n";
        echo "  Memory Used: " . number_format($memoryUsed / 1024, 2) . " KB\n";
        echo "  Memory After Release: " . number_format($memoryAfterRelease / 1024, 2) . " KB\n";

        // Assertions: Memory should be reasonable
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Memory usage for 10 connections should be < 10MB');
    }

    /**
     * Test: Concurrent-like connection acquisition
     * 
     * Simulates concurrent connection requests by acquiring connections rapidly.
     */
    public function testConcurrentLikeConnectionAcquisition(): void
    {
        $manager = SwooleConnection::getInstance();
        $connectionCount = 30;
        $connections = [];

        $start = microtime(true);
        
        // Rapidly acquire connections (simulating concurrent requests)
        for ($i = 0; $i < $connectionCount; $i++) {
            $connections[] = $manager->getConnection('default');
        }
        
        $acquisitionTime = (microtime(true) - $start) * 1000;

        // Release all
        $start = microtime(true);
        foreach ($connections as $connection) {
            if ($connection !== null) {
                $manager->releaseConnection($connection);
            }
        }
        $releaseTime = (microtime(true) - $start) * 1000;

        // Log results
        echo "\n[Performance] Concurrent-like Acquisition:\n";
        echo "  Connections: $connectionCount\n";
        echo "  Acquisition Time: " . number_format($acquisitionTime, 2) . " ms\n";
        echo "  Release Time: " . number_format($releaseTime, 2) . " ms\n";
        echo "  Total Time: " . number_format($acquisitionTime + $releaseTime, 2) . " ms\n";
        echo "  Throughput: " . number_format($connectionCount / ($acquisitionTime / 1000), 2) . " connections/sec\n";

        // Assertions
        $this->assertLessThan(3000, $acquisitionTime, 'Acquisition time should be reasonable');
        $this->assertLessThan(1000, $releaseTime, 'Release time should be fast');
    }

    /**
     * Test: Pool statistics retrieval performance
     * 
     * Measures time to retrieve pool statistics.
     */
    public function testPoolStatisticsPerformance(): void
    {
        $manager = SwooleConnection::getInstance();
        $iterations = 100;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $stats = $manager->getPoolStats();
            $end = microtime(true);
            
            $times[] = ($end - $start) * 1000; // Convert to milliseconds
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        // Log results
        echo "\n[Performance] Pool Statistics Retrieval:\n";
        echo "  Iterations: $iterations\n";
        echo "  Average: " . number_format($avgTime, 2) . " ms\n";
        echo "  Min: " . number_format($minTime, 2) . " ms\n";
        echo "  Max: " . number_format($maxTime, 2) . " ms\n";

        // Assertions: Statistics retrieval should be very fast
        $this->assertLessThan(10, $avgTime, 'Average statistics retrieval should be < 10ms');
    }

    /**
     * Test: Repeated getInstance() calls performance
     * 
     * Measures performance of repeated singleton access.
     * Should be very fast since it's just returning existing instance.
     */
    public function testRepeatedGetInstancePerformance(): void
    {
        $manager = SwooleConnection::getInstance(); // Initial creation
        
        $iterations = 1000;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $instance = SwooleConnection::getInstance();
            $end = microtime(true);
            
            $times[] = ($end - $start) * 1000000; // Convert to microseconds
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        // Log results
        echo "\n[Performance] Repeated getInstance() Calls:\n";
        echo "  Iterations: $iterations\n";
        echo "  Average: " . number_format($avgTime, 2) . " μs\n";
        echo "  Min: " . number_format($minTime, 2) . " μs\n";
        echo "  Max: " . number_format($maxTime, 2) . " μs\n";

        // Assertions: Should be extremely fast (microseconds)
        $this->assertLessThan(100, $avgTime, 'Average getInstance() should be < 100μs');
    }

    /**
     * Test: Different pool names performance
     * 
     * Measures performance when using different pool names.
     */
    public function testDifferentPoolNamesPerformance(): void
    {
        $manager = SwooleConnection::getInstance();
        $poolNames = ['default', 'pool1', 'pool2', 'pool3', 'pool4'];
        $times = [];

        foreach ($poolNames as $poolName) {
            $start = microtime(true);
            $connection = $manager->getConnection($poolName);
            $end = microtime(true);
            
            $times[$poolName] = ($end - $start) * 1000; // Convert to milliseconds
            
            if ($connection !== null) {
                $manager->releaseConnection($connection);
            }
        }

        $avgTime = array_sum($times) / count($times);

        // Log results
        echo "\n[Performance] Different Pool Names:\n";
        foreach ($times as $poolName => $time) {
            echo "  $poolName: " . number_format($time, 2) . " ms\n";
        }
        echo "  Average: " . number_format($avgTime, 2) . " ms\n";

        // Assertions
        $this->assertLessThan(200, $avgTime, 'Average time for different pools should be < 200ms');
    }

    /**
     * Test: Full lifecycle performance
     * 
     * Measures complete lifecycle: initialization, acquisition, use, release.
     */
    public function testFullLifecyclePerformance(): void
    {
        $iterations = 20;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            SwooleConnection::resetInstance();
            
            $start = microtime(true);
            
            // Initialize
            $manager = SwooleConnection::getInstance();
            
            // Acquire connection
            $connection = $manager->getConnection();
            
            // Simulate usage (get underlying driver)
            if ($connection !== null) {
                $driver = $connection->getConnection();
            }
            
            // Release connection
            if ($connection !== null) {
                $manager->releaseConnection($connection);
            }
            
            $end = microtime(true);
            
            $times[] = ($end - $start) * 1000; // Convert to milliseconds
        }

        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);

        // Log results
        echo "\n[Performance] Full Lifecycle:\n";
        echo "  Iterations: $iterations\n";
        echo "  Average: " . number_format($avgTime, 2) . " ms\n";
        echo "  Min: " . number_format($minTime, 2) . " ms\n";
        echo "  Max: " . number_format($maxTime, 2) . " ms\n";

        // Assertions
        $this->assertLessThan(1000, $avgTime, 'Average full lifecycle should be < 1000ms');
    }
}

