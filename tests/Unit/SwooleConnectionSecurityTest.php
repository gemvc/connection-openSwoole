<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleConnectionSecurity;

/**
 * Unit tests for SwooleConnectionSecurity
 */
class SwooleConnectionSecurityTest extends TestCase
{
    public function testIsValidPoolName(): void
    {
        // Valid pool names
        $this->assertTrue(SwooleConnectionSecurity::isValidPoolName('default'));
        $this->assertTrue(SwooleConnectionSecurity::isValidPoolName('pool1'));
        $this->assertTrue(SwooleConnectionSecurity::isValidPoolName('pool_1'));
        $this->assertTrue(SwooleConnectionSecurity::isValidPoolName('pool-1'));
        $this->assertTrue(SwooleConnectionSecurity::isValidPoolName('a'));
        $this->assertTrue(SwooleConnectionSecurity::isValidPoolName(str_repeat('a', 64)));
        
        // Invalid pool names
        $this->assertFalse(SwooleConnectionSecurity::isValidPoolName(''));
        $this->assertFalse(SwooleConnectionSecurity::isValidPoolName('pool; DROP TABLE users;--'));
        $this->assertFalse(SwooleConnectionSecurity::isValidPoolName('pool space'));
        $this->assertFalse(SwooleConnectionSecurity::isValidPoolName('pool@name'));
        $this->assertFalse(SwooleConnectionSecurity::isValidPoolName('pool.name'));
        $this->assertFalse(SwooleConnectionSecurity::isValidPoolName(str_repeat('a', 65)));
    }

    public function testSanitizePoolName(): void
    {
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('default'));
        $this->assertEquals('pool1', SwooleConnectionSecurity::sanitizePoolName('pool1'));
        // Sanitization removes spaces and special chars, keeps alphanumeric, underscore, hyphen
        $this->assertEquals('poolDROPTABLEusers--', SwooleConnectionSecurity::sanitizePoolName('pool; DROP TABLE users;--'));
        $this->assertEquals('pool1', SwooleConnectionSecurity::sanitizePoolName('pool1@#$'));
        // Only special chars (dashes) should return default
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName(';--'));
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('---'));
        $this->assertEquals('poolscriptalert1script', SwooleConnectionSecurity::sanitizePoolName('pool<script>alert(1)</script>'));
        
        // Test lines 51-53: Empty string after sanitization should return default
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName(''), 'Empty string should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('@#$'), 'Only special chars that get removed should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('!@#$%^&*()'), 'Only special chars should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('   '), 'Only spaces should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('.;[]{}'), 'Only punctuation should return default');
        
        // Test lines 56-58: Only underscores and hyphens (no alphanumeric) should return default
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('___'), 'Only underscores should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('---'), 'Only hyphens should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('___---'), 'Mix of underscores and hyphens should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('_-_'), 'Alternating underscores and hyphens should return default');
        $this->assertEquals('default', SwooleConnectionSecurity::sanitizePoolName('@#$___'), 'Special chars + underscores should return default after sanitization');
        
        // Test length limit
        $longName = str_repeat('a', 100);
        $this->assertEquals(str_repeat('a', 64), SwooleConnectionSecurity::sanitizePoolName($longName));
    }

    public function testIsValidDatabaseHost(): void
    {
        // Valid hosts
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseHost('localhost'));
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseHost('127.0.0.1'));
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseHost('db.example.com'));
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseHost('192.168.1.1'));
        
        // Invalid hosts
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseHost(''));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseHost("localhost'; DROP TABLE users;--"));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseHost('../../etc/passwd'));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseHost('host/path'));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseHost('host<script>'));
    }

    public function testIsValidDatabaseName(): void
    {
        // Valid database names
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseName('test_db'));
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseName('test-db'));
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseName('test.db'));
        $this->assertTrue(SwooleConnectionSecurity::isValidDatabaseName('test123'));
        
        // Invalid database names
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseName(''));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseName("test'; DROP TABLE users;--"));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseName('../../etc/passwd'));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseName('test/path'));
        $this->assertFalse(SwooleConnectionSecurity::isValidDatabaseName('test<script>'));
    }

    public function testSanitizeErrorMessage(): void
    {
        $password = 'my_secret_password';
        
        // Test password masking
        $message = "Connection failed with password: $password";
        $sanitized = SwooleConnectionSecurity::sanitizeErrorMessage($message, $password);
        $this->assertStringNotContainsString($password, $sanitized);
        $this->assertStringContainsString('***', $sanitized);
        
        // Test password pattern removal
        $message = "Error: password=secret123";
        $sanitized = SwooleConnectionSecurity::sanitizeErrorMessage($message);
        $this->assertStringContainsString('password=***', $sanitized);
        $this->assertStringNotContainsString('secret123', $sanitized);
        
        // Test connection string removal
        $message = "mysql:host=localhost;dbname=test;password=secret";
        $sanitized = SwooleConnectionSecurity::sanitizeErrorMessage($message);
        $this->assertStringNotContainsString('password=secret', $sanitized);
    }

    public function testMaskPassword(): void
    {
        $password = 'secret123';
        $text = "Connection with password: $password failed";
        
        $masked = SwooleConnectionSecurity::maskPassword($text, $password);
        $this->assertStringNotContainsString($password, $masked);
        $this->assertStringContainsString('***', $masked);
        
        // Empty password should not change text
        $this->assertEquals($text, SwooleConnectionSecurity::maskPassword($text, ''));
    }

    public function testSanitizeEnvValue(): void
    {
        // Remove null bytes
        $value = "test\0value";
        $this->assertEquals('testvalue', SwooleConnectionSecurity::sanitizeEnvValue($value));
        
        // Trim whitespace
        $this->assertEquals('test', SwooleConnectionSecurity::sanitizeEnvValue('  test  '));
        
        // Normal value unchanged
        $this->assertEquals('test_value', SwooleConnectionSecurity::sanitizeEnvValue('test_value'));
    }

    public function testValidateAndSanitizePoolName(): void
    {
        // Valid name unchanged
        $this->assertEquals('default', SwooleConnectionSecurity::validateAndSanitizePoolName('default'));
        $this->assertEquals('pool1', SwooleConnectionSecurity::validateAndSanitizePoolName('pool1'));
        
        // Invalid name sanitized (removes special chars, keeps alphanumeric, underscore, hyphen)
        $this->assertEquals('pool1DROPTABLE--', SwooleConnectionSecurity::validateAndSanitizePoolName('pool1; DROP TABLE;--'));
        $this->assertEquals('default', SwooleConnectionSecurity::validateAndSanitizePoolName(';--'));
    }

    public function testValidateDatabaseConfig(): void
    {
        // Valid config
        $result = SwooleConnectionSecurity::validateDatabaseConfig('localhost', 'test_db', 'test_user');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        
        // Invalid host
        $result = SwooleConnectionSecurity::validateDatabaseConfig("localhost'; DROP TABLE;--", 'test_db', 'test_user');
        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid database host', $result['errors']);
        
        // Invalid database name
        $result = SwooleConnectionSecurity::validateDatabaseConfig('localhost', "test'; DROP TABLE;--", 'test_user');
        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid database name', $result['errors']);
        
        // Invalid user
        $result = SwooleConnectionSecurity::validateDatabaseConfig('localhost', 'test_db', "user'; DROP TABLE;--");
        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid database user', $result['errors']);
    }
}

