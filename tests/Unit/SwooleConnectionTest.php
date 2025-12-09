<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;
use Gemvc\Database\Connection\Contracts\ConnectionManagerInterface;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;

/**
 * Unit tests for SwooleConnection
 * 
 * Tests the connection manager functionality without actual database connections.
 * Uses mocks for Hyperf components.
 * 
 * @covers \Gemvc\Database\Connection\OpenSwoole\SwooleConnection
 */
class SwooleConnectionTest extends TestCase
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

    public function testImplementsConnectionManagerInterface(): void
    {
        $manager = SwooleConnection::getInstance();
        $this->assertInstanceOf(ConnectionManagerInterface::class, $manager);
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance1 = SwooleConnection::getInstance();
        $instance2 = SwooleConnection::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    public function testResetInstance(): void
    {
        $instance1 = SwooleConnection::getInstance();
        SwooleConnection::resetInstance();
        $instance2 = SwooleConnection::getInstance();
        
        $this->assertNotSame($instance1, $instance2);
    }

    public function testIsInitialized(): void
    {
        $manager = SwooleConnection::getInstance();
        // Note: This may fail if Hyperf dependencies aren't available
        // In a real test environment, you'd mock the Hyperf components
        $this->assertIsBool($manager->isInitialized());
    }

    public function testGetPoolStats(): void
    {
        $manager = SwooleConnection::getInstance();
        $stats = $manager->getPoolStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('type', $stats);
        $this->assertArrayHasKey('environment', $stats);
        $this->assertEquals('OpenSwoole', $stats['environment']);
    }

    public function testGetErrorReturnsNullInitially(): void
    {
        $manager = SwooleConnection::getInstance();
        $this->assertNull($manager->getError());
    }

    public function testSetError(): void
    {
        $manager = SwooleConnection::getInstance();
        $manager->setError('Test error');
        
        $this->assertEquals('Test error', $manager->getError());
    }

    public function testClearError(): void
    {
        $manager = SwooleConnection::getInstance();
        $manager->setError('Test error');
        $manager->clearError();
        
        $this->assertNull($manager->getError());
    }

    public function testSetErrorWithContext(): void
    {
        $manager = SwooleConnection::getInstance();
        $manager->setError('Test error', ['key' => 'value']);
        
        $error = $manager->getError();
        $this->assertStringContainsString('Test error', $error ?? '');
        $this->assertStringContainsString('Context', $error ?? '');
    }
}

