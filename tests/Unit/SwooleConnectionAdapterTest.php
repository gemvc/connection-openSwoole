<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnectionAdapter;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;
use PDO;

/**
 * Unit tests for SwooleConnectionAdapter
 * 
 * Tests the adapter functionality with mocked Hyperf Connection.
 * 
 * @covers \Gemvc\Database\Connection\OpenSwoole\SwooleConnectionAdapter
 */
class SwooleConnectionAdapterTest extends TestCase
{
    public function testImplementsConnectionInterface(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $this->assertInstanceOf(ConnectionInterface::class, $adapter);
    }

    public function testGetConnectionReturnsNullWhenNotInitialized(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $this->assertNull($adapter->getConnection());
    }

    public function testIsInitializedReturnsFalseWhenNotInitialized(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $this->assertFalse($adapter->isInitialized());
    }

    public function testGetErrorReturnsNullInitially(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $this->assertNull($adapter->getError());
    }

    public function testSetError(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $adapter->setError('Test error');
        
        $this->assertEquals('Test error', $adapter->getError());
    }

    public function testClearError(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $adapter->setError('Test error');
        $adapter->clearError();
        
        $this->assertNull($adapter->getError());
    }

    public function testBeginTransactionFailsWhenNoConnection(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $result = $adapter->beginTransaction();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No connection available', $adapter->getError() ?? '');
    }

    public function testCommitFailsWhenNoConnection(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $result = $adapter->commit();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No connection available', $adapter->getError() ?? '');
    }

    public function testRollbackFailsWhenNoConnection(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $result = $adapter->rollback();
        
        $this->assertFalse($result);
        $this->assertStringContainsString('No connection available', $adapter->getError() ?? '');
    }

    public function testInTransactionReturnsFalseWhenNoConnection(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $this->assertFalse($adapter->inTransaction());
    }
}

