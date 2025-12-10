<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Connection\OpenSwoole\SwooleEnvDetect;

/**
 * Test suite for SwooleEnvDetect class.
 * 
 * Tests all environment detection and configuration methods.
 */
class SwooleEnvDetectTest extends TestCase
{
    private SwooleEnvDetect $envDetect;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envDetect = new SwooleEnvDetect();
    }

    // Test isOpenSwooleServer detection
    public function testIsOpenSwooleServer(): void
    {
        // If SWOOLE_BASE is defined, should return true
        if (defined('SWOOLE_BASE')) {
            $this->assertTrue($this->envDetect->isOpenSwooleServer());
            return; // Early return after assertion
        }
        
        // If OpenSwoole\Server class exists, should return true
        if (class_exists('\OpenSwoole\Server')) {
            $this->assertTrue($this->envDetect->isOpenSwooleServer());
            return; // Early return after assertion
        }
        
        // If neither condition is met, verify it returns false (when in CLI)
        // The method checks: PHP_SAPI === 'cli' && (defined('SWOOLE_BASE') || class_exists('\OpenSwoole\Server'))
        if (PHP_SAPI === 'cli') {
            $this->assertFalse($this->envDetect->isOpenSwooleServer());
        } else {
            // Not in CLI, so should also return false
            $this->assertFalse($this->envDetect->isOpenSwooleServer());
        }
    }
    
    // Test isOpenSwooleServer returns false when conditions not met
    public function testIsOpenSwooleServerReturnsFalse(): void
    {
        // When not in OpenSwoole context, should return false
        // This is tested by checking that isCliContext() works correctly
        // If isOpenSwooleServer() returned true when it shouldn't, isCliContext() would fail
        if (PHP_SAPI === 'cli' && !defined('SWOOLE_BASE') && !class_exists('\OpenSwoole\Server')) {
            $this->assertFalse($this->envDetect->isOpenSwooleServer());
        }
    }

    // Test isCliContext detection
    public function testIsCliContext(): void
    {
        // In CLI but not OpenSwoole server
        if (PHP_SAPI === 'cli' && !$this->envDetect->isOpenSwooleServer()) {
            $this->assertTrue($this->envDetect->isCliContext());
        }
    }
    
    // Test isCliContext returns false when not in CLI
    public function testIsCliContextReturnsFalse(): void
    {
        // When in OpenSwoole server context, should return false
        if ($this->envDetect->isOpenSwooleServer()) {
            $this->assertFalse($this->envDetect->isCliContext());
        } else {
            // When not in OpenSwoole server and not in CLI, should return false
            if (PHP_SAPI !== 'cli') {
                $this->assertFalse($this->envDetect->isCliContext());
            } else {
                // In CLI but not OpenSwoole, should return true (not false)
                // So we verify the opposite: if we're in CLI and not OpenSwoole, isCliContext should be true
                $this->assertTrue($this->envDetect->isCliContext());
            }
        }
    }

    // Test isWebServerContext detection
    public function testIsWebServerContext(): void
    {
        if (PHP_SAPI !== 'cli') {
            $this->assertTrue($this->envDetect->isWebServerContext());
        } else {
            // When in CLI, should return false
            $this->assertFalse($this->envDetect->isWebServerContext());
        }
    }
    
    // Test isWebServerContext returns false when in CLI
    public function testIsWebServerContextReturnsFalse(): void
    {
        // When in CLI, should return false
        if (PHP_SAPI === 'cli') {
            $this->assertFalse($this->envDetect->isWebServerContext());
        }
    }

    // Test getExecutionContext (using property)
    public function testGetExecutionContext(): void
    {
        $context = $this->envDetect->executionContext;
        $this->assertContains($context, ['openswoole', 'cli', 'webserver']);
    }
    
    // Test getExecutionContext returns correct value for each context (using properties)
    public function testGetExecutionContextReturnsCorrectValue(): void
    {
        if ($this->envDetect->isOpenSwooleServer) {
            $this->assertEquals('openswoole', $this->envDetect->executionContext);
        } elseif ($this->envDetect->isCliContext) {
            $this->assertEquals('cli', $this->envDetect->executionContext);
        } else {
            $this->assertEquals('webserver', $this->envDetect->executionContext);
        }
    }
    
    // Test getExecutionContext web server branch using anonymous class
    // This ensures 100% coverage of the 'webserver' return statement
    public function testGetExecutionContextWebServerBranch(): void
    {
        // Create a testable subclass that forces the web server branch
        $testableClass = new class extends SwooleEnvDetect {
            protected function computeIsOpenSwooleServer(): bool
            {
                return false; // Force not OpenSwoole
            }
            
            protected function computeIsCliContext(bool $isOpenSwooleServer): bool
            {
                return false; // Force not CLI
            }
        };
        
        // Now executionContext property should be 'webserver'
        $this->assertEquals('webserver', $testableClass->executionContext);
    }

    // Test getDbHost in OpenSwoole context (using properties)
    public function testGetDbHostInOpenSwooleContext(): void
    {
        if ($this->envDetect->isOpenSwooleServer) {
            $_ENV['DB_HOST'] = 'swoole_db';
            // Create new instance to pick up the env var
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('swoole_db', $envDetect->dbHost);
            
            unset($_ENV['DB_HOST']);
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('db', $envDetect->dbHost);
        } else {
            // When not in OpenSwoole context, verify dbHost is still accessible
            $this->assertIsString($this->envDetect->dbHost);
            $this->assertNotEmpty($this->envDetect->dbHost);
        }
    }

    // Test getDbHost in CLI context (using properties)
    public function testGetDbHostInCliContext(): void
    {
        if ($this->envDetect->isCliContext) {
            $_ENV['DB_HOST_CLI_DEV'] = 'cli_db';
            // Create new instance to pick up the env var
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('cli_db', $envDetect->dbHost);
            
            unset($_ENV['DB_HOST_CLI_DEV']);
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('localhost', $envDetect->dbHost);
        }
    }

    // Test getDbHost in web server context (using properties)
    public function testGetDbHostInWebServerContext(): void
    {
        if ($this->envDetect->isWebServerContext) {
            $_ENV['DB_HOST'] = 'web_db';
            // Create new instance to pick up the env var
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('web_db', $envDetect->dbHost);
            
            unset($_ENV['DB_HOST']);
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('db', $envDetect->dbHost);
        } else {
            // When not in web server context, verify dbHost is still accessible
            $this->assertIsString($this->envDetect->dbHost);
            $this->assertNotEmpty($this->envDetect->dbHost);
        }
    }

    // Test isDevEnvironment (using property)
    public function testIsDevEnvironment(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $envDetect = new SwooleEnvDetect();
        $this->assertTrue($envDetect->isDevEnvironment);
        
        $_ENV['APP_ENV'] = 'production';
        $envDetect = new SwooleEnvDetect();
        $this->assertFalse($envDetect->isDevEnvironment);
        
        unset($_ENV['APP_ENV']);
        $envDetect = new SwooleEnvDetect();
        $this->assertFalse($envDetect->isDevEnvironment);
    }

    // Test getStringEnv using reflection
    public function testGetStringEnv(): void
    {
        $reflection = new \ReflectionClass($this->envDetect);
        $method = $reflection->getMethod('getStringEnv');
        $method->setAccessible(true);
        
        $_ENV['TEST_STRING'] = 'test_value';
        $this->assertEquals('test_value', $method->invoke($this->envDetect, 'TEST_STRING'));
        $this->assertEquals('default', $method->invoke($this->envDetect, 'NON_EXISTENT', 'default'));
        
        // Test with non-string value (should return default)
        $_ENV['TEST_STRING'] = 123;
        $this->assertEquals('default', $method->invoke($this->envDetect, 'TEST_STRING', 'default'));
        
        $_ENV['TEST_STRING'] = null;
        $this->assertEquals('default', $method->invoke($this->envDetect, 'TEST_STRING', 'default'));
        
        $_ENV['TEST_STRING'] = [];
        $this->assertEquals('default', $method->invoke($this->envDetect, 'TEST_STRING', 'default'));
        
        unset($_ENV['TEST_STRING']);
    }

    // Test getIntEnv using reflection
    public function testGetIntEnv(): void
    {
        $reflection = new \ReflectionClass($this->envDetect);
        $method = $reflection->getMethod('getIntEnv');
        $method->setAccessible(true);
        
        $_ENV['TEST_INT'] = '123';
        $this->assertEquals(123, $method->invoke($this->envDetect, 'TEST_INT'));
        $this->assertEquals(456, $method->invoke($this->envDetect, 'NON_EXISTENT', 456));
        
        $_ENV['TEST_INT'] = 'invalid';
        $this->assertEquals(0, $method->invoke($this->envDetect, 'TEST_INT', 0));
        
        // Test with numeric string
        $_ENV['TEST_INT'] = '456';
        $this->assertEquals(456, $method->invoke($this->envDetect, 'TEST_INT'));
        
        // Test with negative number
        $_ENV['TEST_INT'] = '-10';
        $this->assertEquals(-10, $method->invoke($this->envDetect, 'TEST_INT'));
        
        // Test with float string (should convert to int)
        $_ENV['TEST_INT'] = '123.45';
        $this->assertEquals(123, $method->invoke($this->envDetect, 'TEST_INT'));
        
        unset($_ENV['TEST_INT']);
    }

    // Test getFloatEnv using reflection
    public function testGetFloatEnv(): void
    {
        $reflection = new \ReflectionClass($this->envDetect);
        $method = $reflection->getMethod('getFloatEnv');
        $method->setAccessible(true);
        
        $_ENV['TEST_FLOAT'] = '123.45';
        $this->assertEquals(123.45, $method->invoke($this->envDetect, 'TEST_FLOAT'));
        $this->assertEquals(67.89, $method->invoke($this->envDetect, 'NON_EXISTENT', 67.89));
        
        $_ENV['TEST_FLOAT'] = 'invalid';
        $this->assertEquals(0.0, $method->invoke($this->envDetect, 'TEST_FLOAT', 0.0));
        
        // Test with integer string
        $_ENV['TEST_FLOAT'] = '100';
        $this->assertEquals(100.0, $method->invoke($this->envDetect, 'TEST_FLOAT'));
        
        // Test with negative float
        $_ENV['TEST_FLOAT'] = '-45.67';
        $this->assertEquals(-45.67, $method->invoke($this->envDetect, 'TEST_FLOAT'));
        
        // Test with scientific notation
        $_ENV['TEST_FLOAT'] = '1.5e2';
        $this->assertEquals(150.0, $method->invoke($this->envDetect, 'TEST_FLOAT'));
        
        unset($_ENV['TEST_FLOAT']);
    }

    // Test database configuration properties
    public function testDatabaseConfigurationGetters(): void
    {
        $_ENV['DB_DRIVER'] = 'pgsql';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals('pgsql', $envDetect->dbDriver);
        
        $_ENV['DB_PORT'] = '5432';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals(5432, $envDetect->dbPort);
        
        $_ENV['DB_NAME'] = 'test_db';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals('test_db', $envDetect->dbName);
        
        $_ENV['DB_USER'] = 'test_user';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals('test_user', $envDetect->dbUser);
        
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals('test_pass', $envDetect->dbPassword);
        
        $_ENV['DB_CHARSET'] = 'utf8';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals('utf8', $envDetect->dbCharset);
        
        $_ENV['DB_COLLATION'] = 'utf8_general_ci';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals('utf8_general_ci', $envDetect->dbCollation);
        
        // Clean up
        unset(
            $_ENV['DB_DRIVER'],
            $_ENV['DB_PORT'],
            $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_CHARSET'],
            $_ENV['DB_COLLATION']
        );
    }

    // Test pool configuration properties
    public function testPoolConfigurationGetters(): void
    {
        $_ENV['MIN_DB_CONNECTION_POOL'] = '10';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals(10, $envDetect->minConnectionPool);
        
        $_ENV['MAX_DB_CONNECTION_POOL'] = '20';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals(20, $envDetect->maxConnectionPool);
        
        $_ENV['DB_CONNECTION_TIME_OUT'] = '15.5';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals(15.5, $envDetect->connectionTimeout);
        
        $_ENV['DB_CONNECTION_EXPIER_TIME'] = '3.5';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals(3.5, $envDetect->waitTimeout);
        
        $_ENV['DB_HEARTBEAT'] = '30';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals(30, $envDetect->heartbeat);
        
        $_ENV['DB_CONNECTION_MAX_AGE'] = '120.0';
        $envDetect = new SwooleEnvDetect();
        $this->assertEquals(120.0, $envDetect->maxIdleTime);
        
        // Clean up
        unset(
            $_ENV['MIN_DB_CONNECTION_POOL'],
            $_ENV['MAX_DB_CONNECTION_POOL'],
            $_ENV['DB_CONNECTION_TIME_OUT'],
            $_ENV['DB_CONNECTION_EXPIER_TIME'],
            $_ENV['DB_HEARTBEAT'],
            $_ENV['DB_CONNECTION_MAX_AGE']
        );
    }

    // Test databaseConfig property returns complete structure
    public function testGetDatabaseConfigReturnsCompleteStructure(): void
    {
        $config = $this->envDetect->databaseConfig;
        
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('driver', $config['default']);
        $this->assertArrayHasKey('host', $config['default']);
        $this->assertArrayHasKey('port', $config['default']);
        $this->assertArrayHasKey('database', $config['default']);
        $this->assertArrayHasKey('username', $config['default']);
        $this->assertArrayHasKey('password', $config['default']);
        $this->assertArrayHasKey('charset', $config['default']);
        $this->assertArrayHasKey('collation', $config['default']);
        $this->assertArrayHasKey('pool', $config['default']);
        
        $pool = $config['default']['pool'];
        $this->assertArrayHasKey('min_connections', $pool);
        $this->assertArrayHasKey('max_connections', $pool);
        $this->assertArrayHasKey('connect_timeout', $pool);
        $this->assertArrayHasKey('wait_timeout', $pool);
        $this->assertArrayHasKey('heartbeat', $pool);
        $this->assertArrayHasKey('max_idle_time', $pool);
    }

    // Test databaseConfig property uses environment variables
    public function testGetDatabaseConfigUsesEnvironmentVariables(): void
    {
        $_ENV['DB_DRIVER'] = 'pgsql';
        $_ENV['DB_NAME'] = 'custom_db';
        $_ENV['DB_USER'] = 'custom_user';
        $_ENV['MIN_DB_CONNECTION_POOL'] = '5';
        $_ENV['MAX_DB_CONNECTION_POOL'] = '10';
        
        $envDetect = new SwooleEnvDetect();
        $config = $envDetect->databaseConfig;
        
        $this->assertEquals('pgsql', $config['default']['driver']);
        $this->assertEquals('custom_db', $config['default']['database']);
        $this->assertEquals('custom_user', $config['default']['username']);
        $this->assertEquals(5, $config['default']['pool']['min_connections']);
        $this->assertEquals(10, $config['default']['pool']['max_connections']);
        
        // Clean up
        unset(
            $_ENV['DB_DRIVER'],
            $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['MIN_DB_CONNECTION_POOL'],
            $_ENV['MAX_DB_CONNECTION_POOL']
        );
    }

    // Test databaseConfig property uses defaults when env vars not set
    public function testGetDatabaseConfigUsesDefaults(): void
    {
        // Unset all DB env vars
        unset(
            $_ENV['DB_DRIVER'],
            $_ENV['DB_NAME'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_CHARSET'],
            $_ENV['DB_COLLATION'],
            $_ENV['MIN_DB_CONNECTION_POOL'],
            $_ENV['MAX_DB_CONNECTION_POOL']
        );
        
        $envDetect = new SwooleEnvDetect();
        $config = $envDetect->databaseConfig;
        
        $this->assertEquals('mysql', $config['default']['driver']);
        $this->assertEquals('gemvc_db', $config['default']['database']);
        $this->assertEquals('root', $config['default']['username']);
        $this->assertEquals('', $config['default']['password']);
        $this->assertEquals('utf8mb4', $config['default']['charset']);
        $this->assertEquals('utf8mb4_unicode_ci', $config['default']['collation']);
        $this->assertEquals(8, $config['default']['pool']['min_connections']);
        $this->assertEquals(16, $config['default']['pool']['max_connections']);
    }
    
    // Test getStringEnv with empty string using reflection
    public function testGetStringEnvWithEmptyString(): void
    {
        $reflection = new \ReflectionClass($this->envDetect);
        $method = $reflection->getMethod('getStringEnv');
        $method->setAccessible(true);
        
        $_ENV['TEST_EMPTY'] = '';
        // Empty string is a valid string, so it should be returned
        $this->assertEquals('', $method->invoke($this->envDetect, 'TEST_EMPTY'));
        $this->assertEquals('', $method->invoke($this->envDetect, 'TEST_EMPTY', 'default'));
        
        unset($_ENV['TEST_EMPTY']);
    }
    
    // Test getIntEnv with zero using reflection
    public function testGetIntEnvWithZero(): void
    {
        $reflection = new \ReflectionClass($this->envDetect);
        $method = $reflection->getMethod('getIntEnv');
        $method->setAccessible(true);
        
        $_ENV['TEST_ZERO'] = '0';
        $this->assertEquals(0, $method->invoke($this->envDetect, 'TEST_ZERO'));
        $this->assertEquals(0, $method->invoke($this->envDetect, 'TEST_ZERO', 100));
        
        unset($_ENV['TEST_ZERO']);
    }
    
    // Test getFloatEnv with zero using reflection
    public function testGetFloatEnvWithZero(): void
    {
        $reflection = new \ReflectionClass($this->envDetect);
        $method = $reflection->getMethod('getFloatEnv');
        $method->setAccessible(true);
        
        $_ENV['TEST_ZERO'] = '0.0';
        $this->assertEquals(0.0, $method->invoke($this->envDetect, 'TEST_ZERO'));
        $this->assertEquals(0.0, $method->invoke($this->envDetect, 'TEST_ZERO', 100.0));
        
        unset($_ENV['TEST_ZERO']);
    }
    
    // Test getDbHost covers all branches (using properties)
    public function testGetDbHostCoversAllBranches(): void
    {
        // Test OpenSwoole branch
        if ($this->envDetect->isOpenSwooleServer) {
            $_ENV['DB_HOST'] = 'swoole_host';
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('swoole_host', $envDetect->dbHost);
            unset($_ENV['DB_HOST']);
        }
        
        // Test CLI branch
        if ($this->envDetect->isCliContext) {
            $_ENV['DB_HOST_CLI_DEV'] = 'cli_host';
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('cli_host', $envDetect->dbHost);
            unset($_ENV['DB_HOST_CLI_DEV']);
        }
        
        // Test web server branch
        if ($this->envDetect->isWebServerContext) {
            $_ENV['DB_HOST'] = 'web_host';
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('web_host', $envDetect->dbHost);
            unset($_ENV['DB_HOST']);
        }
    }
    
    // Test getDbHost OpenSwoole branch with SWOOLE_BASE (using properties)
    public function testGetDbHostOpenSwooleBranchWithSwooleBase(): void
    {
        // Ensure SWOOLE_BASE is defined to test OpenSwoole branch
        if (!defined('SWOOLE_BASE')) {
            define('SWOOLE_BASE', true);
        }
        
        $envDetect = new SwooleEnvDetect();
        if ($envDetect->isOpenSwooleServer) {
            // Test with DB_HOST set
            $_ENV['DB_HOST'] = 'swoole_base_host';
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('swoole_base_host', $envDetect->dbHost);
            
            // Test with DB_HOST not set (default)
            unset($_ENV['DB_HOST']);
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('db', $envDetect->dbHost);
        }
        
        unset($_ENV['DB_HOST']);
    }
    
    // Test getDbHost OpenSwoole branch with OpenSwoole\Server class (using properties)
    public function testGetDbHostOpenSwooleBranchWithServerClass(): void
    {
        // Create mock OpenSwoole\Server class if it doesn't exist
        if (!class_exists('\OpenSwoole\Server')) {
            eval('namespace OpenSwoole; class Server {}');
        }
        
        $envDetect = new SwooleEnvDetect();
        if ($envDetect->isOpenSwooleServer) {
            $_ENV['DB_HOST'] = 'server_class_host';
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('server_class_host', $envDetect->dbHost);
            
            unset($_ENV['DB_HOST']);
            $envDetect = new SwooleEnvDetect();
            $this->assertEquals('db', $envDetect->dbHost);
        }
        
        unset($_ENV['DB_HOST']);
    }
    
    // Test getDbHost CLI branch thoroughly (using properties)
    public function testGetDbHostCliBranchThoroughly(): void
    {
        // Ensure we're in CLI but not OpenSwoole
        // This means SWOOLE_BASE should not be defined and OpenSwoole\Server should not exist
        // But we can't control constants that were already defined, so we test when possible
        if (PHP_SAPI === 'cli') {
            // Create a new instance to test
            $envDetect = new SwooleEnvDetect();
            
            // Only test if we're actually in CLI context (not OpenSwoole)
            if ($envDetect->isCliContext) {
                // Test with DB_HOST_CLI_DEV set
                $_ENV['DB_HOST_CLI_DEV'] = 'cli_test_host';
                $envDetect = new SwooleEnvDetect();
                $this->assertEquals('cli_test_host', $envDetect->dbHost);
                
                // Test with DB_HOST_CLI_DEV not set (default to localhost)
                unset($_ENV['DB_HOST_CLI_DEV']);
                $envDetect = new SwooleEnvDetect();
                $this->assertEquals('localhost', $envDetect->dbHost);
            }
        }
        
        unset($_ENV['DB_HOST_CLI_DEV']);
    }
    
    // Test getDbHost web server branch using a testable subclass
    // Since we can't change PHP_SAPI, we create a subclass that forces the web server branch
    public function testGetDbHostWebServerBranchWithReflection(): void
    {
        // Set environment variable BEFORE creating instance
        $_ENV['DB_HOST'] = 'web_server_test_host';
        
        // Create a testable subclass that overrides compute methods
        $testableClass = new class extends SwooleEnvDetect {
            protected function computeIsOpenSwooleServer(): bool
            {
                return false; // Force not OpenSwoole
            }
            
            protected function computeIsCliContext(bool $isOpenSwooleServer): bool
            {
                return false; // Force not CLI
            }
        };
        
        // Now dbHost property should use web server branch
        $this->assertEquals('web_server_test_host', $testableClass->dbHost);
        
        // Test with DB_HOST not set (default) - need new instance
        unset($_ENV['DB_HOST']);
        $testableClass2 = new class extends SwooleEnvDetect {
            protected function computeIsOpenSwooleServer(): bool
            {
                return false; // Force not OpenSwoole
            }
            
            protected function computeIsCliContext(bool $isOpenSwooleServer): bool
            {
                return false; // Force not CLI
            }
        };
        $this->assertEquals('db', $testableClass2->dbHost);
        
        unset($_ENV['DB_HOST']);
    }

    // ============================================================================
    // Backward Compatibility Methods Tests
    // ============================================================================

    public function testBackwardCompatibilityIsOpenSwooleServer(): void
    {
        $this->assertEquals($this->envDetect->isOpenSwooleServer, $this->envDetect->isOpenSwooleServer());
    }

    public function testBackwardCompatibilityIsCliContext(): void
    {
        $this->assertEquals($this->envDetect->isCliContext, $this->envDetect->isCliContext());
    }

    public function testBackwardCompatibilityIsWebServerContext(): void
    {
        $this->assertEquals($this->envDetect->isWebServerContext, $this->envDetect->isWebServerContext());
    }

    public function testBackwardCompatibilityGetExecutionContext(): void
    {
        $this->assertEquals($this->envDetect->executionContext, $this->envDetect->getExecutionContext());
    }

    public function testBackwardCompatibilityGetDbHost(): void
    {
        $this->assertEquals($this->envDetect->dbHost, $this->envDetect->getDbHost());
    }

    public function testBackwardCompatibilityIsDevEnvironment(): void
    {
        $this->assertEquals($this->envDetect->isDevEnvironment, $this->envDetect->isDevEnvironment());
    }

    public function testBackwardCompatibilityGetDbDriver(): void
    {
        $this->assertEquals($this->envDetect->dbDriver, $this->envDetect->getDbDriver());
    }

    public function testBackwardCompatibilityGetDbPort(): void
    {
        $this->assertEquals($this->envDetect->dbPort, $this->envDetect->getDbPort());
    }

    public function testBackwardCompatibilityGetDbName(): void
    {
        $this->assertEquals($this->envDetect->dbName, $this->envDetect->getDbName());
    }

    public function testBackwardCompatibilityGetDbUser(): void
    {
        $this->assertEquals($this->envDetect->dbUser, $this->envDetect->getDbUser());
    }

    public function testBackwardCompatibilityGetDbPassword(): void
    {
        $this->assertEquals($this->envDetect->dbPassword, $this->envDetect->getDbPassword());
    }

    public function testBackwardCompatibilityGetDbCharset(): void
    {
        $this->assertEquals($this->envDetect->dbCharset, $this->envDetect->getDbCharset());
    }

    public function testBackwardCompatibilityGetDbCollation(): void
    {
        $this->assertEquals($this->envDetect->dbCollation, $this->envDetect->getDbCollation());
    }

    public function testBackwardCompatibilityGetMinConnectionPool(): void
    {
        $this->assertEquals($this->envDetect->minConnectionPool, $this->envDetect->getMinConnectionPool());
    }

    public function testBackwardCompatibilityGetMaxConnectionPool(): void
    {
        $this->assertEquals($this->envDetect->maxConnectionPool, $this->envDetect->getMaxConnectionPool());
    }

    public function testBackwardCompatibilityGetConnectionTimeout(): void
    {
        $this->assertEquals($this->envDetect->connectionTimeout, $this->envDetect->getConnectionTimeout());
    }

    public function testBackwardCompatibilityGetWaitTimeout(): void
    {
        $this->assertEquals($this->envDetect->waitTimeout, $this->envDetect->getWaitTimeout());
    }

    public function testBackwardCompatibilityGetHeartbeat(): void
    {
        $this->assertEquals($this->envDetect->heartbeat, $this->envDetect->getHeartbeat());
    }

    public function testBackwardCompatibilityGetMaxIdleTime(): void
    {
        $this->assertEquals($this->envDetect->maxIdleTime, $this->envDetect->getMaxIdleTime());
    }

    public function testBackwardCompatibilityGetDatabaseConfig(): void
    {
        $this->assertEquals($this->envDetect->databaseConfig, $this->envDetect->getDatabaseConfig());
    }
}

