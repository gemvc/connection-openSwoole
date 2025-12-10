<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleErrorLogLogger;

/**
 * Test suite for SwooleErrorLogLogger class.
 * 
 * Tests all logging methods to ensure they properly format and log messages
 * using PHP's error_log() function.
 */
class SwooleErrorLogLoggerTest extends TestCase
{
    private SwooleErrorLogLogger $logger;
    private string $originalErrorLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new SwooleErrorLogLogger();
        
        // Capture original error_log configuration
        $this->originalErrorLog = ini_get('error_log');
    }

    protected function tearDown(): void
    {
        // Restore original error_log configuration
        if ($this->originalErrorLog !== false) {
            ini_set('error_log', $this->originalErrorLog);
        }
        parent::tearDown();
    }

    // Test emergency logging
    public function testEmergencyLogsWithCorrectPrefix(): void
    {
        $message = 'System is down';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->emergency($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[EMERGENCY]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test alert logging
    public function testAlertLogsWithCorrectPrefix(): void
    {
        $message = 'Immediate action required';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->alert($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[ALERT]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test critical logging
    public function testCriticalLogsWithCorrectPrefix(): void
    {
        $message = 'Critical error occurred';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->critical($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[CRITICAL]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test error logging
    public function testErrorLogsWithCorrectPrefix(): void
    {
        $message = 'Runtime error detected';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->error($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test warning logging
    public function testWarningLogsWithCorrectPrefix(): void
    {
        $message = 'Warning condition';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->warning($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test notice logging
    public function testNoticeLogsWithCorrectPrefix(): void
    {
        $message = 'Notice event';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->notice($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[NOTICE]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test info logging
    public function testInfoLogsWithCorrectPrefix(): void
    {
        $message = 'Information message';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->info($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test debug logging
    public function testDebugLogsWithCorrectPrefix(): void
    {
        $message = 'Debug information';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->debug($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[DEBUG]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test log method with string level
    public function testLogWithStringLevel(): void
    {
        $level = 'CUSTOM';
        $message = 'Custom log message';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->log($level, $message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[' . $level . ']', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test log method with non-string level (should use UNKNOWN)
    public function testLogWithNonStringLevel(): void
    {
        $level = 123; // Non-string level
        $message = 'Message with numeric level';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->log($level, $message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[UNKNOWN]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test log method with null level (should use UNKNOWN)
    public function testLogWithNullLevel(): void
    {
        $level = null;
        $message = 'Message with null level';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->log($level, $message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[UNKNOWN]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test Stringable message object
    public function testLogsStringableMessage(): void
    {
        $message = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };
        
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        ini_set('error_log', $tempFile);
        
        $this->logger->info($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString('Stringable message', $content);
        
        unlink($tempFile);
    }

    // Test context parameter is accepted (even though not used)
    public function testAcceptsContextParameter(): void
    {
        $message = 'Message with context';
        $context = ['key' => 'value', 'number' => 42];
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        // Should not throw exception
        $this->logger->info($message, $context);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test all log levels in sequence
    public function testAllLogLevelsInSequence(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        ini_set('error_log', $tempFile);
        
        $this->logger->emergency('emergency');
        $this->logger->alert('alert');
        $this->logger->critical('critical');
        $this->logger->error('error');
        $this->logger->warning('warning');
        $this->logger->notice('notice');
        $this->logger->info('info');
        $this->logger->debug('debug');
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        
        $this->assertStringContainsString('[EMERGENCY] emergency', $content);
        $this->assertStringContainsString('[ALERT] alert', $content);
        $this->assertStringContainsString('[CRITICAL] critical', $content);
        $this->assertStringContainsString('[ERROR] error', $content);
        $this->assertStringContainsString('[WARNING] warning', $content);
        $this->assertStringContainsString('[NOTICE] notice', $content);
        $this->assertStringContainsString('[INFO] info', $content);
        $this->assertStringContainsString('[DEBUG] debug', $content);
        
        unlink($tempFile);
    }

    // Test implements StdoutLoggerInterface
    public function testImplementsStdoutLoggerInterface(): void
    {
        $this->assertInstanceOf(\Hyperf\Contract\StdoutLoggerInterface::class, $this->logger);
    }

    // Test handleException with RuntimeException and context
    public function testHandleExceptionWithContext(): void
    {
        $exception = new \RuntimeException('Test error message', 123);
        $context = 'Error releasing connection in resetInstance';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleException($exception, $context);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString($context, $content);
        $this->assertStringContainsString('RuntimeException', $content);
        $this->assertStringContainsString('Test error message', $content);
        $this->assertStringContainsString('(123)', $content);
        $this->assertStringContainsString('[File:', $content);
        
        unlink($tempFile);
    }

    // Test handleException without context
    public function testHandleExceptionWithoutContext(): void
    {
        $exception = new \InvalidArgumentException('Invalid argument', 456);
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleException($exception);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString('InvalidArgumentException', $content);
        $this->assertStringContainsString('Invalid argument', $content);
        $this->assertStringContainsString('(456)', $content);
        
        unlink($tempFile);
    }

    // Test handleException with critical log level
    public function testHandleExceptionWithCriticalLevel(): void
    {
        $exception = new \Error('Critical error', 999);
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleException($exception, 'Critical failure', 'critical');
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[CRITICAL]', $content);
        $this->assertStringContainsString('Critical failure', $content);
        $this->assertStringContainsString('Error', $content);
        $this->assertStringContainsString('Critical error', $content);
        
        unlink($tempFile);
    }

    // Test handleException with warning log level
    public function testHandleExceptionWithWarningLevel(): void
    {
        $exception = new \Exception('Warning condition', 0);
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleException($exception, 'Non-critical issue', 'warning');
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString('Non-critical issue', $content);
        $this->assertStringContainsString('Exception', $content);
        $this->assertStringContainsString('Warning condition', $content);
        
        unlink($tempFile);
    }

    // Test handleException with different exception types
    public function testHandleExceptionWithDifferentExceptionTypes(): void
    {
        $exceptions = [
            new \RuntimeException('Runtime error', 1),
            new \InvalidArgumentException('Invalid argument', 2),
            new \LogicException('Logic error', 3),
            new \Error('Fatal error', 4),
        ];
        
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        ini_set('error_log', $tempFile);
        
        foreach ($exceptions as $exception) {
            $this->logger->handleException($exception, 'Test context');
        }
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        
        $this->assertStringContainsString('RuntimeException', $content);
        $this->assertStringContainsString('InvalidArgumentException', $content);
        $this->assertStringContainsString('LogicException', $content);
        $this->assertStringContainsString('Error', $content);
        
        unlink($tempFile);
    }

    // Test handleWarning with context
    public function testHandleWarningWithContext(): void
    {
        $message = 'Connection not found in tracking';
        $context = 'Release connection validation';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleWarning($message, $context);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString($context, $content);
        $this->assertStringContainsString($message, $content);
        
        unlink($tempFile);
    }

    // Test handleWarning without context
    public function testHandleWarningWithoutContext(): void
    {
        $message = 'Simple warning message';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleWarning($message);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString($message, $content);
        // Should not contain ":" if no context
        $this->assertStringNotContainsString(': ' . $message, $content);
        
        unlink($tempFile);
    }

    // Test handleException includes file and line information
    public function testHandleExceptionIncludesFileAndLine(): void
    {
        $exception = new \RuntimeException('Test', 0);
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleException($exception);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        // Should contain file path and line number
        $this->assertStringContainsString('[File:', $content);
        $this->assertStringContainsString(':', $content); // Line number separator
        
        unlink($tempFile);
    }

    // Test handleException with contextData array
    public function testHandleExceptionWithContextData(): void
    {
        $exception = new \RuntimeException('Connection failed', 123);
        $contextData = [
            'pool' => 'default',
            'worker_pid' => 12345,
            'timestamp' => '2024-01-01 12:00:00',
            'error_code' => 123
        ];
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleException($exception, 'SwooleConnection::getConnection()', 'error', $contextData);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString('SwooleConnection::getConnection()', $content);
        $this->assertStringContainsString('RuntimeException', $content);
        $this->assertStringContainsString('Connection failed', $content);
        $this->assertStringContainsString('[Context:', $content);
        $this->assertStringContainsString('"pool":"default"', $content);
        $this->assertStringContainsString('"worker_pid":12345', $content);
        
        unlink($tempFile);
    }

    // Test handleException with empty contextData (backward compatibility)
    public function testHandleExceptionWithEmptyContextData(): void
    {
        $exception = new \RuntimeException('Test', 0);
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        $this->logger->handleException($exception, 'Test context', 'error', []);
        
        $this->assertFileExists($tempFile);
        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString('Test context', $content);
        // Should not contain [Context: when contextData is empty
        $this->assertStringNotContainsString('[Context:', $content);
        
        unlink($tempFile);
    }

    // Test logAndThrowException logs and throws
    public function testLogAndThrowExceptionLogsAndThrows(): void
    {
        $exception = new \RuntimeException('Original error', 123);
        $component = 'container';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        try {
            $this->logger->logAndThrowException($exception, $component);
            $this->fail('Expected RuntimeException to be thrown');
        } catch (\RuntimeException $e) {
            // Verify the exception was thrown
            $this->assertStringContainsString('Failed to initialize container', $e->getMessage());
            $this->assertStringContainsString('Original error', $e->getMessage());
            $this->assertSame($exception, $e->getPrevious());
            $this->assertEquals(0, $e->getCode());
            
            // Verify it was logged
            $this->assertFileExists($tempFile);
            $content = file_get_contents($tempFile);
            $this->assertStringContainsString('[ERROR]', $content);
            $this->assertStringContainsString('Failed to initialize container', $content);
            $this->assertStringContainsString('RuntimeException', $content);
            $this->assertStringContainsString('Original error', $content);
        }
        
        unlink($tempFile);
    }

    // Test logAndThrowException with context
    public function testLogAndThrowExceptionWithContext(): void
    {
        $exception = new \InvalidArgumentException('Invalid config', 456);
        $component = 'event dispatcher';
        $context = 'During initialization';
        $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
        
        ini_set('error_log', $tempFile);
        
        try {
            $this->logger->logAndThrowException($exception, $component, $context);
            $this->fail('Expected RuntimeException to be thrown');
        } catch (\RuntimeException $e) {
            // Verify the exception was thrown
            $this->assertStringContainsString('Failed to initialize event dispatcher', $e->getMessage());
            $this->assertSame($exception, $e->getPrevious());
            
            // Verify it was logged with context
            $this->assertFileExists($tempFile);
            $content = file_get_contents($tempFile);
            $this->assertStringContainsString('[ERROR]', $content);
            $this->assertStringContainsString('During initialization', $content);
            $this->assertStringContainsString('Failed to initialize event dispatcher', $content);
        }
        
        unlink($tempFile);
    }

    // Test logAndThrowException with different exception types
    public function testLogAndThrowExceptionWithDifferentExceptionTypes(): void
    {
        $exceptions = [
            new \RuntimeException('Runtime error', 1),
            new \LogicException('Logic error', 2),
            new \Error('Fatal error', 3),
        ];
        
        foreach ($exceptions as $exception) {
            $tempFile = sys_get_temp_dir() . '/test_error_log_' . uniqid() . '.log';
            ini_set('error_log', $tempFile);
            
            try {
                $this->logger->logAndThrowException($exception, 'test component');
                $this->fail('Expected RuntimeException to be thrown');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Failed to initialize test component', $e->getMessage());
                $this->assertSame($exception, $e->getPrevious());
                
                // Verify it was logged
                $content = file_get_contents($tempFile);
                $this->assertStringContainsString('[ERROR]', $content);
                $this->assertStringContainsString(get_class($exception), $content);
            }
            
            unlink($tempFile);
        }
    }
}

