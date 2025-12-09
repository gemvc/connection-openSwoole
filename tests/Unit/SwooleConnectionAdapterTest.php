<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnectionAdapter;
use Gemvc\Database\Connection\Contracts\ConnectionInterface;
use Hyperf\DbConnection\Connection;
use PDO;
use PDOException;
use Exception;

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

    // Test constructor with null connection
    public function testConstructorWithNull(): void
    {
        $adapter = new SwooleConnectionAdapter(null);

        $this->assertFalse($adapter->isInitialized());
        $this->assertNull($adapter->getConnection());
        $this->assertNull($adapter->getHyperfConnection());
    }

    // Test successful PDO extraction from Hyperf Connection
    public function testConstructorExtractsPdoFromHyperfConnection(): void
    {
        // Create a real PDO in memory
        $pdo = new PDO('sqlite::memory:');

        // Create mock of Hyperf Connection - getPdo() is called via __call magic method
        $hyperfMock = $this->createMock(Connection::class);
        
        // Mock __call to handle getPdo() method call
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdo);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        $this->assertTrue($adapter->isInitialized());
        $this->assertSame($pdo, $adapter->getConnection());
        $this->assertSame($hyperfMock, $adapter->getHyperfConnection());
    }

    // Test connection release
    public function testReleaseConnectionCallsHyperfRelease(): void
    {
        $hyperfMock = $this->createMock(Connection::class);
        
        // Expect release method to be called exactly once
        $hyperfMock->expects($this->once())->method('release');

        $adapter = new SwooleConnectionAdapter($hyperfMock);
        
        // The input parameter is ignored in the code, so it doesn't matter what it is
        $adapter->releaseConnection(new \stdClass());

        // After release, state should be cleared
        $this->assertNull($adapter->getHyperfConnection());
        $this->assertNull($adapter->getConnection());
        $this->assertFalse($adapter->isInitialized());
    }

    // Test exception handling in release (covers lines 94-97)
    public function testReleaseConnectionHandlesExceptions(): void
    {
        $hyperfMock = $this->createMock(Connection::class);
        
        // Simulate error during release (e.g., connection lost)
        $hyperfMock->method('release')->willThrowException(new Exception('Connection lost'));

        $adapter = new SwooleConnectionAdapter($hyperfMock);
        
        // This method should not crash the program (should log error and continue)
        // Since error_log has no standard output, if test passes, catch worked
        $adapter->releaseConnection(null);

        // Despite the error, references should still be cleared (according to lines 98-100)
        $this->assertNull($adapter->getHyperfConnection());
        $this->assertNull($adapter->getConnection());
        $this->assertFalse($adapter->inTransaction());
    }

    // Test successful transactions
    public function testTransactionMethodsSuccess(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $hyperfMock = $this->createMock(Connection::class);
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdo);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        $this->assertTrue($adapter->beginTransaction());
        $this->assertTrue($adapter->inTransaction());
        
        $this->assertTrue($adapter->commit());
        $this->assertFalse($adapter->inTransaction());
        
        $adapter->beginTransaction();
        $this->assertTrue($adapter->rollback());
        $this->assertFalse($adapter->inTransaction());
    }

    // Test transaction methods with PDO exceptions
    public function testTransactionMethodsHandlePdoExceptions(): void
    {
        // Use PDO Mock that throws exceptions
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('beginTransaction')->willThrowException(new PDOException('Connection lost'));
        $pdoMock->method('commit')->willThrowException(new PDOException('Commit failed'));
        $pdoMock->method('rollBack')->willThrowException(new PDOException('Rollback failed'));

        $hyperfMock = $this->createMock(Connection::class);
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdoMock);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        // Test beginTransaction with exception
        $this->assertFalse($adapter->beginTransaction());
        $this->assertStringContainsString('Failed to begin transaction', $adapter->getError() ?? '');
        $this->assertFalse($adapter->inTransaction());

        // Reset for commit test - create new PDO mock that allows beginTransaction
        $pdoMock2 = $this->createMock(PDO::class);
        $pdoMock2->method('beginTransaction')->willReturn(true);
        $pdoMock2->method('commit')->willThrowException(new PDOException('Commit failed'));
        
        $hyperfMock2 = $this->createMock(Connection::class);
        $hyperfMock2->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdoMock2);
        
        $adapter2 = new SwooleConnectionAdapter($hyperfMock2);
        $adapter2->beginTransaction();

        // Test commit with exception
        $this->assertFalse($adapter2->commit());
        $this->assertStringContainsString('Failed to commit transaction', $adapter2->getError() ?? '');
        // inTransaction should still be true because commit failed
        $this->assertTrue($adapter2->inTransaction());

        // Reset for rollback test
        $pdoMock3 = $this->createMock(PDO::class);
        $pdoMock3->method('beginTransaction')->willReturn(true);
        $pdoMock3->method('rollBack')->willThrowException(new PDOException('Rollback failed'));
        
        $hyperfMock3 = $this->createMock(Connection::class);
        $hyperfMock3->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdoMock3);
        
        $adapter3 = new SwooleConnectionAdapter($hyperfMock3);
        $adapter3->beginTransaction();

        // Test rollback with exception
        $this->assertFalse($adapter3->rollback());
        $this->assertStringContainsString('Failed to rollback transaction', $adapter3->getError() ?? '');
    }

    // Test "already in transaction" scenario
    public function testBeginTransactionFailsWhenAlreadyInTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $hyperfMock = $this->createMock(Connection::class);
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdo);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        // Start a transaction
        $this->assertTrue($adapter->beginTransaction());
        $this->assertTrue($adapter->inTransaction());

        // Try to start another transaction
        $this->assertFalse($adapter->beginTransaction());
        $this->assertStringContainsString('Already in transaction', $adapter->getError() ?? '');
    }

    // Test commit without active transaction
    public function testCommitFailsWhenNoActiveTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $hyperfMock = $this->createMock(Connection::class);
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdo);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        // Try to commit without starting a transaction
        $this->assertFalse($adapter->commit());
        $this->assertStringContainsString('No active transaction to commit', $adapter->getError() ?? '');
    }

    // Test rollback without active transaction
    public function testRollbackFailsWhenNoActiveTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $hyperfMock = $this->createMock(Connection::class);
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdo);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        // Try to rollback without starting a transaction
        $this->assertFalse($adapter->rollback());
        $this->assertStringContainsString('No active transaction to rollback', $adapter->getError() ?? '');
    }

    // Test setError with context
    public function testSetErrorWithContext(): void
    {
        $adapter = new SwooleConnectionAdapter();
        $adapter->setError('Test error', ['key' => 'value', 'code' => 123]);
        
        $error = $adapter->getError();
        $this->assertNotNull($error);
        $this->assertStringContainsString('Test error', $error);
        $this->assertStringContainsString('Context:', $error);
        $this->assertStringContainsString('"key":"value"', $error);
    }

    // Test getHyperfConnection returns correct instance
    public function testGetHyperfConnectionReturnsCorrectInstance(): void
    {
        $hyperfMock = $this->createMock(Connection::class);
        $pdo = new PDO('sqlite::memory:');
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdo);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        $this->assertSame($hyperfMock, $adapter->getHyperfConnection());
    }

    // Test inTransaction with actual PDO transaction state
    public function testInTransactionChecksPdoState(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $hyperfMock = $this->createMock(Connection::class);
        $hyperfMock->method('__call')
            ->with($this->equalTo('getPdo'), $this->anything())
            ->willReturn($pdo);

        $adapter = new SwooleConnectionAdapter($hyperfMock);

        // Initially not in transaction
        $this->assertFalse($adapter->inTransaction());

        // Start transaction
        $adapter->beginTransaction();
        $this->assertTrue($adapter->inTransaction());

        // Commit transaction
        $adapter->commit();
        $this->assertFalse($adapter->inTransaction());
    }
}

