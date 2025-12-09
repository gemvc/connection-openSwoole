<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\DatabaseConfig;
use Gemvc\Database\Connection\OpenSwoole\SwooleEnvDetect;

class DatabaseConfigTest extends TestCase
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
        $config = new DatabaseConfig(
            'pgsql',
            'localhost',
            'test_db'
        );

        $this->assertEquals('pgsql', $config->driver);
        $this->assertEquals('localhost', $config->host);
        $this->assertEquals('test_db', $config->database);
    }

    public function testFromEnvDetect(): void
    {
        $_ENV['DB_DRIVER'] = 'pgsql';
        $_ENV['DB_NAME'] = 'custom_db';
        
        // Host is context-aware, so set the appropriate env var based on context
        if (PHP_SAPI === 'cli' && !(defined('SWOOLE_BASE') || class_exists('\OpenSwoole\Server'))) {
            // CLI context uses DB_HOST_CLI_DEV
            $_ENV['DB_HOST_CLI_DEV'] = 'custom_host';
        } else {
            // OpenSwoole or web server context uses DB_HOST
            $_ENV['DB_HOST'] = 'custom_host';
        }

        $envDetect = new SwooleEnvDetect();
        $config = DatabaseConfig::fromEnvDetect($envDetect);

        $this->assertEquals('pgsql', $config->driver);
        $this->assertEquals('custom_host', $config->host);
        $this->assertEquals('custom_db', $config->database);

        unset(
            $_ENV['DB_DRIVER'],
            $_ENV['DB_HOST'],
            $_ENV['DB_HOST_CLI_DEV'],
            $_ENV['DB_NAME']
        );
    }

    public function testFromEnvDetectUsesDefaults(): void
    {
        $envDetect = new SwooleEnvDetect();
        $config = DatabaseConfig::fromEnvDetect($envDetect);

        $this->assertEquals('mysql', $config->driver);
        $this->assertNotEmpty($config->host); // Host depends on context
        $this->assertEquals('gemvc_db', $config->database);
    }

    public function testFromEnvDetectWithContextAwareHost(): void
    {
        // Test that host is context-aware (uses SwooleEnvDetect logic)
        $envDetect = new SwooleEnvDetect();
        $config = DatabaseConfig::fromEnvDetect($envDetect);

        // Host should match what SwooleEnvDetect determines based on context
        $this->assertEquals($envDetect->dbHost, $config->host);
    }

    public function testToArray(): void
    {
        $config = new DatabaseConfig(
            'pgsql',
            'localhost',
            'test_db'
        );

        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('driver', $array);
        $this->assertArrayHasKey('host', $array);
        $this->assertArrayHasKey('database', $array);

        $this->assertEquals('pgsql', $array['driver']);
        $this->assertEquals('localhost', $array['host']);
        $this->assertEquals('test_db', $array['database']);
    }

    public function testImmutability(): void
    {
        $config = new DatabaseConfig(
            'mysql',
            'db',
            'gemvc_db'
        );

        // Properties are readonly, so we can't modify them
        // This test verifies the object is created correctly
        $this->assertIsString($config->driver);
        $this->assertIsString($config->host);
        $this->assertIsString($config->database);
    }

    public function testEmptyStrings(): void
    {
        $config = new DatabaseConfig(
            '',
            '',
            ''
        );

        $this->assertEquals('', $config->driver);
        $this->assertEquals('', $config->host);
        $this->assertEquals('', $config->database);
    }

    public function testSpecialCharacters(): void
    {
        $config = new DatabaseConfig(
            'mysql',
            'host-name_123',
            'database_name'
        );

        $this->assertEquals('mysql', $config->driver);
        $this->assertEquals('host-name_123', $config->host);
        $this->assertEquals('database_name', $config->database);
    }
}

