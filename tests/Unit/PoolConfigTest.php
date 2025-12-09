<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\PoolConfig;
use Gemvc\Database\Connection\OpenSwoole\SwooleEnvDetect;

class PoolConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV = [];
    }

    public function testConstructor(): void
    {
        $config = new PoolConfig(
            10,
            20,
            15.5,
            3.5,
            30,
            120.0
        );

        $this->assertEquals(10, $config->minConnections);
        $this->assertEquals(20, $config->maxConnections);
        $this->assertEquals(15.5, $config->connectTimeout);
        $this->assertEquals(3.5, $config->waitTimeout);
        $this->assertEquals(30, $config->heartbeat);
        $this->assertEquals(120.0, $config->maxIdleTime);
    }

    public function testFromEnvDetect(): void
    {
        $_ENV['MIN_DB_CONNECTION_POOL'] = '5';
        $_ENV['MAX_DB_CONNECTION_POOL'] = '10';
        $_ENV['DB_CONNECTION_TIME_OUT'] = '12.5';
        $_ENV['DB_CONNECTION_EXPIER_TIME'] = '2.5';
        $_ENV['DB_HEARTBEAT'] = '60';
        $_ENV['DB_CONNECTION_MAX_AGE'] = '90.0';

        $envDetect = new SwooleEnvDetect();
        $config = PoolConfig::fromEnvDetect($envDetect);

        $this->assertEquals(5, $config->minConnections);
        $this->assertEquals(10, $config->maxConnections);
        $this->assertEquals(12.5, $config->connectTimeout);
        $this->assertEquals(2.5, $config->waitTimeout);
        $this->assertEquals(60, $config->heartbeat);
        $this->assertEquals(90.0, $config->maxIdleTime);

        unset(
            $_ENV['MIN_DB_CONNECTION_POOL'],
            $_ENV['MAX_DB_CONNECTION_POOL'],
            $_ENV['DB_CONNECTION_TIME_OUT'],
            $_ENV['DB_CONNECTION_EXPIER_TIME'],
            $_ENV['DB_HEARTBEAT'],
            $_ENV['DB_CONNECTION_MAX_AGE']
        );
    }

    public function testFromEnvDetectUsesDefaults(): void
    {
        $envDetect = new SwooleEnvDetect();
        $config = PoolConfig::fromEnvDetect($envDetect);

        $this->assertEquals(8, $config->minConnections);
        $this->assertEquals(16, $config->maxConnections);
        $this->assertEquals(10.0, $config->connectTimeout);
        $this->assertEquals(2.0, $config->waitTimeout);
        $this->assertEquals(-1, $config->heartbeat);
        $this->assertEquals(60.0, $config->maxIdleTime);
    }

    public function testToArray(): void
    {
        $config = new PoolConfig(
            10,
            20,
            15.5,
            3.5,
            30,
            120.0
        );

        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('min_connections', $array);
        $this->assertArrayHasKey('max_connections', $array);
        $this->assertArrayHasKey('connect_timeout', $array);
        $this->assertArrayHasKey('wait_timeout', $array);
        $this->assertArrayHasKey('heartbeat', $array);
        $this->assertArrayHasKey('max_idle_time', $array);

        $this->assertEquals(10, $array['min_connections']);
        $this->assertEquals(20, $array['max_connections']);
        $this->assertEquals(15.5, $array['connect_timeout']);
        $this->assertEquals(3.5, $array['wait_timeout']);
        $this->assertEquals(30, $array['heartbeat']);
        $this->assertEquals(120.0, $array['max_idle_time']);
    }

    public function testToArrayWithNegativeHeartbeat(): void
    {
        $config = new PoolConfig(
            8,
            16,
            10.0,
            2.0,
            -1,
            60.0
        );

        $array = $config->toArray();
        $this->assertEquals(-1, $array['heartbeat']);
    }

    public function testImmutability(): void
    {
        $config = new PoolConfig(
            10,
            20,
            15.5,
            3.5,
            30,
            120.0
        );

        // Properties are readonly, so we can't modify them
        // This test verifies the object is created correctly
        $this->assertIsInt($config->minConnections);
        $this->assertIsInt($config->maxConnections);
        $this->assertIsFloat($config->connectTimeout);
        $this->assertIsFloat($config->waitTimeout);
        $this->assertIsInt($config->heartbeat);
        $this->assertIsFloat($config->maxIdleTime);
    }

    public function testZeroValues(): void
    {
        $config = new PoolConfig(
            0,
            0,
            0.0,
            0.0,
            0,
            0.0
        );

        $this->assertEquals(0, $config->minConnections);
        $this->assertEquals(0, $config->maxConnections);
        $this->assertEquals(0.0, $config->connectTimeout);
        $this->assertEquals(0.0, $config->waitTimeout);
        $this->assertEquals(0, $config->heartbeat);
        $this->assertEquals(0.0, $config->maxIdleTime);
    }
}

