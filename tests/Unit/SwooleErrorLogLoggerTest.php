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
}

