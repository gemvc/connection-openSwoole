<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnectionPoolStats;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;
use Gemvc\Database\Connection\OpenSwoole\SwooleEnvDetect;
use Gemvc\Database\Connection\OpenSwoole\PoolConfig;
use Gemvc\Database\Connection\OpenSwoole\DatabaseConfig;

class SwooleConnectionPoolStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SwooleConnection::resetInstance();
        $_ENV = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        SwooleConnection::resetInstance();
        $_ENV = [];
    }

    public function testConstructor(): void
    {
        $poolConfig = new PoolConfig(8, 16, 10.0, 2.0, -1, 60.0);
        $databaseConfig = new DatabaseConfig('mysql', 'localhost', 'test_db');

        $stats = new SwooleConnectionPoolStats(
            'Test Type',
            'Test Environment',
            'cli',
            5,
            true,
            $poolConfig,
            $databaseConfig
        );

        $this->assertEquals('Test Type', $stats->type);
        $this->assertEquals('Test Environment', $stats->environment);
        $this->assertEquals('cli', $stats->executionContext);
        $this->assertEquals(5, $stats->activeConnections);
        $this->assertTrue($stats->initialized);
        $this->assertInstanceOf(PoolConfig::class, $stats->poolConfig);
        $this->assertInstanceOf(DatabaseConfig::class, $stats->config);
    }

    public function testFromConnection(): void
    {
        $connection = SwooleConnection::getInstance();
        $envDetect = new SwooleEnvDetect();

        $stats = SwooleConnectionPoolStats::fromConnection($connection, $envDetect);

        $this->assertEquals('OpenSwoole Connection Manager (True Connection Pooling)', $stats->type);
        $this->assertEquals('OpenSwoole', $stats->environment);
        $this->assertContains($stats->executionContext, ['openswoole', 'cli', 'webserver']);
        $this->assertIsInt($stats->activeConnections);
        $this->assertIsBool($stats->initialized);
        $this->assertInstanceOf(PoolConfig::class, $stats->poolConfig);
        $this->assertInstanceOf(DatabaseConfig::class, $stats->config);
    }

    public function testFromConnectionUsesComposition(): void
    {
        $connection = SwooleConnection::getInstance();
        $envDetect = new SwooleEnvDetect();

        $stats = SwooleConnectionPoolStats::fromConnection($connection, $envDetect);

        // Verify composition - stats should have poolConfig and config objects
        $this->assertInstanceOf(PoolConfig::class, $stats->poolConfig);
        $this->assertInstanceOf(DatabaseConfig::class, $stats->config);

        // Verify poolConfig values match envDetect
        $this->assertEquals($envDetect->minConnectionPool, $stats->poolConfig->minConnections);
        $this->assertEquals($envDetect->maxConnectionPool, $stats->poolConfig->maxConnections);
        $this->assertEquals($envDetect->connectionTimeout, $stats->poolConfig->connectTimeout);
        $this->assertEquals($envDetect->waitTimeout, $stats->poolConfig->waitTimeout);
        $this->assertEquals($envDetect->heartbeat, $stats->poolConfig->heartbeat);
        $this->assertEquals($envDetect->maxIdleTime, $stats->poolConfig->maxIdleTime);

        // Verify databaseConfig values match envDetect
        $this->assertEquals($envDetect->dbDriver, $stats->config->driver);
        $this->assertEquals($envDetect->dbHost, $stats->config->host);
        $this->assertEquals($envDetect->dbName, $stats->config->database);
    }

    public function testFromConnectionReflectsConnectionState(): void
    {
        $connection = SwooleConnection::getInstance();
        $envDetect = new SwooleEnvDetect();

        $stats = SwooleConnectionPoolStats::fromConnection($connection, $envDetect);

        // Verify stats reflect connection state
        $this->assertEquals($connection->isInitialized(), $stats->initialized);
        $this->assertEquals(count($connection->getActiveConnections()), $stats->activeConnections);
        $this->assertEquals($envDetect->executionContext, $stats->executionContext);
    }

    public function testToArray(): void
    {
        $poolConfig = new PoolConfig(8, 16, 10.0, 2.0, -1, 60.0);
        $databaseConfig = new DatabaseConfig('mysql', 'localhost', 'test_db');

        $stats = new SwooleConnectionPoolStats(
            'Test Type',
            'Test Environment',
            'cli',
            5,
            true,
            $poolConfig,
            $databaseConfig
        );

        $array = $stats->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('environment', $array);
        $this->assertArrayHasKey('execution_context', $array);
        $this->assertArrayHasKey('active_connections', $array);
        $this->assertArrayHasKey('initialized', $array);
        $this->assertArrayHasKey('pool_config', $array);
        $this->assertArrayHasKey('config', $array);

        $this->assertEquals('Test Type', $array['type']);
        $this->assertEquals('Test Environment', $array['environment']);
        $this->assertEquals('cli', $array['execution_context']);
        $this->assertEquals(5, $array['active_connections']);
        $this->assertTrue($array['initialized']);
        $this->assertIsArray($array['pool_config']);
        $this->assertIsArray($array['config']);
    }

    public function testToArrayPoolConfigStructure(): void
    {
        $poolConfig = new PoolConfig(10, 20, 15.5, 3.5, 30, 120.0);
        $databaseConfig = new DatabaseConfig('mysql', 'localhost', 'test_db');

        $stats = new SwooleConnectionPoolStats(
            'Test',
            'Test',
            'cli',
            0,
            false,
            $poolConfig,
            $databaseConfig
        );

        $array = $stats->toArray();
        $poolConfigArray = $array['pool_config'];

        $this->assertArrayHasKey('min_connections', $poolConfigArray);
        $this->assertArrayHasKey('max_connections', $poolConfigArray);
        $this->assertArrayHasKey('connect_timeout', $poolConfigArray);
        $this->assertArrayHasKey('wait_timeout', $poolConfigArray);
        $this->assertArrayHasKey('heartbeat', $poolConfigArray);
        $this->assertArrayHasKey('max_idle_time', $poolConfigArray);

        $this->assertEquals(10, $poolConfigArray['min_connections']);
        $this->assertEquals(20, $poolConfigArray['max_connections']);
        $this->assertEquals(15.5, $poolConfigArray['connect_timeout']);
        $this->assertEquals(3.5, $poolConfigArray['wait_timeout']);
        $this->assertEquals(30, $poolConfigArray['heartbeat']);
        $this->assertEquals(120.0, $poolConfigArray['max_idle_time']);
    }

    public function testToArrayDatabaseConfigStructure(): void
    {
        $poolConfig = new PoolConfig(8, 16, 10.0, 2.0, -1, 60.0);
        $databaseConfig = new DatabaseConfig('pgsql', 'custom_host', 'custom_db');

        $stats = new SwooleConnectionPoolStats(
            'Test',
            'Test',
            'cli',
            0,
            false,
            $poolConfig,
            $databaseConfig
        );

        $array = $stats->toArray();
        $databaseConfigArray = $array['config'];

        $this->assertArrayHasKey('driver', $databaseConfigArray);
        $this->assertArrayHasKey('host', $databaseConfigArray);
        $this->assertArrayHasKey('database', $databaseConfigArray);

        $this->assertEquals('pgsql', $databaseConfigArray['driver']);
        $this->assertEquals('custom_host', $databaseConfigArray['host']);
        $this->assertEquals('custom_db', $databaseConfigArray['database']);
    }

    public function testImmutability(): void
    {
        $poolConfig = new PoolConfig(8, 16, 10.0, 2.0, -1, 60.0);
        $databaseConfig = new DatabaseConfig('mysql', 'localhost', 'test_db');

        $stats = new SwooleConnectionPoolStats(
            'Test Type',
            'Test Environment',
            'cli',
            5,
            true,
            $poolConfig,
            $databaseConfig
        );

        // Properties are readonly, so we can't modify them
        // This test verifies the object is created correctly
        $this->assertIsString($stats->type);
        $this->assertIsString($stats->environment);
        $this->assertIsString($stats->executionContext);
        $this->assertIsInt($stats->activeConnections);
        $this->assertIsBool($stats->initialized);
        $this->assertInstanceOf(PoolConfig::class, $stats->poolConfig);
        $this->assertInstanceOf(DatabaseConfig::class, $stats->config);
    }

    public function testZeroActiveConnections(): void
    {
        $poolConfig = new PoolConfig(8, 16, 10.0, 2.0, -1, 60.0);
        $databaseConfig = new DatabaseConfig('mysql', 'localhost', 'test_db');

        $stats = new SwooleConnectionPoolStats(
            'Test',
            'Test',
            'cli',
            0,
            false,
            $poolConfig,
            $databaseConfig
        );

        $this->assertEquals(0, $stats->activeConnections);
        $this->assertFalse($stats->initialized);
    }

    public function testDifferentExecutionContexts(): void
    {
        $poolConfig = new PoolConfig(8, 16, 10.0, 2.0, -1, 60.0);
        $databaseConfig = new DatabaseConfig('mysql', 'localhost', 'test_db');

        $contexts = ['openswoole', 'cli', 'webserver'];

        foreach ($contexts as $context) {
            $stats = new SwooleConnectionPoolStats(
                'Test',
                'Test',
                $context,
                0,
                false,
                $poolConfig,
                $databaseConfig
            );

            $this->assertEquals($context, $stats->executionContext);
        }
    }
}

