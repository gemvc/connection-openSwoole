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
        }
        
        // If OpenSwoole\Server class exists, should return true
        if (class_exists('\OpenSwoole\Server')) {
            $this->assertTrue($this->envDetect->isOpenSwooleServer());
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

    // Test isWebServerContext detection
    public function testIsWebServerContext(): void
    {
        if (PHP_SAPI !== 'cli') {
            $this->assertTrue($this->envDetect->isWebServerContext());
        }
    }

    // Test getExecutionContext
    public function testGetExecutionContext(): void
    {
        $context = $this->envDetect->getExecutionContext();
        $this->assertContains($context, ['openswoole', 'cli', 'webserver']);
    }

    // Test getDbHost in OpenSwoole context
    public function testGetDbHostInOpenSwooleContext(): void
    {
        if ($this->envDetect->isOpenSwooleServer()) {
            $_ENV['DB_HOST'] = 'swoole_db';
            $host = $this->envDetect->getDbHost();
            $this->assertEquals('swoole_db', $host);
            
            unset($_ENV['DB_HOST']);
            $host = $this->envDetect->getDbHost();
            $this->assertEquals('db', $host);
        }
    }

    // Test getDbHost in CLI context
    public function testGetDbHostInCliContext(): void
    {
        if ($this->envDetect->isCliContext()) {
            $_ENV['DB_HOST_CLI_DEV'] = 'cli_db';
            $host = $this->envDetect->getDbHost();
            $this->assertEquals('cli_db', $host);
            
            unset($_ENV['DB_HOST_CLI_DEV']);
            $host = $this->envDetect->getDbHost();
            $this->assertEquals('localhost', $host);
        }
    }

    // Test getDbHost in web server context
    public function testGetDbHostInWebServerContext(): void
    {
        if ($this->envDetect->isWebServerContext()) {
            $_ENV['DB_HOST'] = 'web_db';
            $host = $this->envDetect->getDbHost();
            $this->assertEquals('web_db', $host);
            
            unset($_ENV['DB_HOST']);
            $host = $this->envDetect->getDbHost();
            $this->assertEquals('db', $host);
        }
    }

    // Test isDevEnvironment
    public function testIsDevEnvironment(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $this->assertTrue($this->envDetect->isDevEnvironment());
        
        $_ENV['APP_ENV'] = 'production';
        $this->assertFalse($this->envDetect->isDevEnvironment());
        
        unset($_ENV['APP_ENV']);
        $this->assertFalse($this->envDetect->isDevEnvironment());
    }

    // Test getStringEnv
    public function testGetStringEnv(): void
    {
        $_ENV['TEST_STRING'] = 'test_value';
        $this->assertEquals('test_value', $this->envDetect->getStringEnv('TEST_STRING'));
        $this->assertEquals('default', $this->envDetect->getStringEnv('NON_EXISTENT', 'default'));
        
        unset($_ENV['TEST_STRING']);
    }

    // Test getIntEnv
    public function testGetIntEnv(): void
    {
        $_ENV['TEST_INT'] = '123';
        $this->assertEquals(123, $this->envDetect->getIntEnv('TEST_INT'));
        $this->assertEquals(456, $this->envDetect->getIntEnv('NON_EXISTENT', 456));
        
        $_ENV['TEST_INT'] = 'invalid';
        $this->assertEquals(0, $this->envDetect->getIntEnv('TEST_INT', 0));
        
        unset($_ENV['TEST_INT']);
    }

    // Test getFloatEnv
    public function testGetFloatEnv(): void
    {
        $_ENV['TEST_FLOAT'] = '123.45';
        $this->assertEquals(123.45, $this->envDetect->getFloatEnv('TEST_FLOAT'));
        $this->assertEquals(67.89, $this->envDetect->getFloatEnv('NON_EXISTENT', 67.89));
        
        $_ENV['TEST_FLOAT'] = 'invalid';
        $this->assertEquals(0.0, $this->envDetect->getFloatEnv('TEST_FLOAT', 0.0));
        
        unset($_ENV['TEST_FLOAT']);
    }

    // Test database configuration getters
    public function testDatabaseConfigurationGetters(): void
    {
        $_ENV['DB_DRIVER'] = 'pgsql';
        $this->assertEquals('pgsql', $this->envDetect->getDbDriver());
        
        $_ENV['DB_PORT'] = '5432';
        $this->assertEquals(5432, $this->envDetect->getDbPort());
        
        $_ENV['DB_NAME'] = 'test_db';
        $this->assertEquals('test_db', $this->envDetect->getDbName());
        
        $_ENV['DB_USER'] = 'test_user';
        $this->assertEquals('test_user', $this->envDetect->getDbUser());
        
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $this->assertEquals('test_pass', $this->envDetect->getDbPassword());
        
        $_ENV['DB_CHARSET'] = 'utf8';
        $this->assertEquals('utf8', $this->envDetect->getDbCharset());
        
        $_ENV['DB_COLLATION'] = 'utf8_general_ci';
        $this->assertEquals('utf8_general_ci', $this->envDetect->getDbCollation());
        
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

    // Test pool configuration getters
    public function testPoolConfigurationGetters(): void
    {
        $_ENV['MIN_DB_CONNECTION_POOL'] = '10';
        $this->assertEquals(10, $this->envDetect->getMinConnectionPool());
        
        $_ENV['MAX_DB_CONNECTION_POOL'] = '20';
        $this->assertEquals(20, $this->envDetect->getMaxConnectionPool());
        
        $_ENV['DB_CONNECTION_TIME_OUT'] = '15.5';
        $this->assertEquals(15.5, $this->envDetect->getConnectionTimeout());
        
        $_ENV['DB_CONNECTION_EXPIER_TIME'] = '3.5';
        $this->assertEquals(3.5, $this->envDetect->getWaitTimeout());
        
        $_ENV['DB_HEARTBEAT'] = '30';
        $this->assertEquals(30, $this->envDetect->getHeartbeat());
        
        $_ENV['DB_CONNECTION_MAX_AGE'] = '120.0';
        $this->assertEquals(120.0, $this->envDetect->getMaxIdleTime());
        
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

    // Test getDatabaseConfig returns complete structure
    public function testGetDatabaseConfigReturnsCompleteStructure(): void
    {
        $config = $this->envDetect->getDatabaseConfig();
        
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

    // Test getDatabaseConfig uses environment variables
    public function testGetDatabaseConfigUsesEnvironmentVariables(): void
    {
        $_ENV['DB_DRIVER'] = 'pgsql';
        $_ENV['DB_NAME'] = 'custom_db';
        $_ENV['DB_USER'] = 'custom_user';
        $_ENV['MIN_DB_CONNECTION_POOL'] = '5';
        $_ENV['MAX_DB_CONNECTION_POOL'] = '10';
        
        $config = $this->envDetect->getDatabaseConfig();
        
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

    // Test getDatabaseConfig uses defaults when env vars not set
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
        
        $config = $this->envDetect->getDatabaseConfig();
        
        $this->assertEquals('mysql', $config['default']['driver']);
        $this->assertEquals('gemvc_db', $config['default']['database']);
        $this->assertEquals('root', $config['default']['username']);
        $this->assertEquals('', $config['default']['password']);
        $this->assertEquals('utf8mb4', $config['default']['charset']);
        $this->assertEquals('utf8mb4_unicode_ci', $config['default']['collation']);
        $this->assertEquals(8, $config['default']['pool']['min_connections']);
        $this->assertEquals(16, $config['default']['pool']['max_connections']);
    }
}

