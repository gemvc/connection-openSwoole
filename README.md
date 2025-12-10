
![openswoole-connection-poot](https://github.com/user-attachments/assets/8ea19c6c-6411-49ba-80a0-61dcf18c70ae)
# gemvc/connection-openswoole

![PHPStan](https://img.shields.io/badge/PHPStan-Level%209-8E5CF9?style=flat-square&logo=php)
![Unit Tests](https://img.shields.io/badge/Unit%20Tests-100%25-28A745?style=flat-square&logo=phpunit)
![Integration Tests](https://img.shields.io/badge/Integration%20Tests-Passing-28A745?style=flat-square&logo=phpunit)
![Class Coverage](https://img.shields.io/badge/Class%20Coverage-100%25-28A745?style=flat-square&logo=phpunit)

OpenSwoole connection library implementation package for GEMVC framework.

## Package Information

- **Package Name:** `gemvc/connection-openswoole`
- **Namespace:** `Gemvc\Database\Connection\OpenSwoole\`
- **Type:** OpenSwoole connection implementation package
- **Environment:** OpenSwoole (connection pooling)
- **Framework-Specific:** ⚠️ No - This package is framework-agnostic (only depends on `connection-contracts`)
- **Depends On:** `gemvc/connection-contracts: ^1.0`

## Purpose

This package provides OpenSwoole connection implementation for GEMVC framework:

1. **`SwooleConnection`** - Real implementation that creates actual connection pools
   - Creates: Hyperf connection pools - **REAL IMPLEMENTATION**
   - Implements: `ConnectionManagerInterface` (from `connection-contracts` package)
   - Used by: Framework's connection management system
   - **True connection pooling** (not simple caching)
   - **Supports multiple database drivers:**
     - **MySQL** (default, primary) - Optimized with MySQL-specific features
     - **PostgreSQL** - Supported via standard PDO DSN format
     - **Other PDO drivers** - Via standard DSN format

2. **`SwooleConnectionAdapter`** - Adapter that wraps Hyperf Connection instances
   - Wraps: Hyperf Connection instances (from connection pool)
   - Implements: `ConnectionInterface` (from `connection-contracts` package)
   - Used by: `SwooleConnection` to wrap Hyperf connections
   - Provides transaction management and connection release

It's designed for OpenSwoole environments where connections are shared across requests via connection pooling.

## Features

-  **Real Implementation:** Creates actual connection pools using Hyperf (`SwooleConnection`)
-  **Adapter:** Wraps Hyperf Connection instances for contracts (`SwooleConnectionAdapter`)
-  **True Connection Pooling:** Hyperf-based connection pooling (not simple caching)
-  **Multiple Connections Per Pool:** Supports concurrent connections from the same pool
-  **Pool Management:** Min/max connections, idle timeout, health checks
-  **Multi-Driver Support:** MySQL (default), PostgreSQL, and other PDO drivers
-  **MySQL Optimizations:** Charset/collation setup, buffered queries, strict SQL mode
-  **Null Safety:** Comprehensive null checks in cleanup methods
-  **Validation:** Connection tracking validation with logging
-  **Memory Leak Prevention:** Multi-layered protection (pool timeout, destructor, size limits)
-  **Concurrency Safe:** No race conditions, thread-safe operations
-  **Security:** Input validation, credential masking, error message sanitization
-  Transaction support (begin, commit, rollback)
-  Error handling
-  Connection state tracking
-  Implements `ConnectionManagerInterface` (real implementation)
-  Implements `ConnectionInterface` (adapter)

## Installation

```bash
composer require gemvc/connection-openswoole
```

## Dependencies

### Required
- `php >= 8.2`
- `gemvc/connection-contracts: ^1.0` - For `ConnectionInterface`
- `hyperf/db-connection: ^3.0` - For connection pooling
- `hyperf/di: ^3.0` - For dependency injection
- `hyperf/config: ^3.0` - For configuration management
- `hyperf/event: ^3.0` - For event dispatching
- `psr/container: ^2.0` - PSR container interface
- `psr/event-dispatcher: ^1.0` - PSR event dispatcher interface
- `psr/log: ^3.0` - PSR logger interface

### Framework Dependencies (Runtime)
- None - This package only depends on `connection-contracts` and Hyperf packages
- Reads environment variables directly from `$_ENV` (no framework helpers needed)

**Note:** This package is framework-agnostic and only depends on `connection-contracts` and Hyperf. The framework should ensure `$_ENV` is populated before using this package.

## Usage

### Using the Real Implementation

#### MySQL (Default)

```php
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;

// Set environment variables (or use defaults)
$_ENV['DB_DRIVER'] = 'mysql';
$_ENV['DB_HOST'] = 'db';
$_ENV['DB_NAME'] = 'my_database';
$_ENV['DB_USER'] = 'my_user';
$_ENV['DB_PASSWORD'] = 'my_password';
$_ENV['MIN_DB_CONNECTION_POOL'] = '8';
$_ENV['MAX_DB_CONNECTION_POOL'] = '16';

// Get singleton instance (creates connection pool)
$manager = SwooleConnection::getInstance();

// Get connection from pool (real implementation - connection pooling)
$connection = $manager->getConnection();

// Get underlying PDO instance
$pdo = $connection->getConnection();

// Use PDO directly
$stmt = $pdo->prepare("SELECT * FROM users");
$stmt->execute();

// Or use connection interface methods
$connection->beginTransaction();
$connection->commit();

// Release connection back to pool
$manager->releaseConnection($connection);
```

#### PostgreSQL

```php
use Gemvc\Database\Connection\OpenSwoole\SwooleConnection;

// Set environment for PostgreSQL
$_ENV['DB_DRIVER'] = 'pgsql';
$_ENV['DB_HOST'] = 'postgres';
$_ENV['DB_NAME'] = 'my_database';
$_ENV['DB_USER'] = 'my_user';
$_ENV['DB_PASSWORD'] = 'my_password';

// Get connection
$manager = SwooleConnection::getInstance();
$connection = $manager->getConnection();
$pdo = $connection->getConnection();

// Use PostgreSQL connection
$pdo->exec('SELECT * FROM users');
```

### Using the Adapter

```php
use Gemvc\Database\Connection\OpenSwoole\SwooleConnectionAdapter;
use Hyperf\DbConnection\Connection;

// Get Hyperf Connection from pool (or create manually)
$hyperfConnection = $pool->get();

// Wrap in adapter for contracts
$adapter = new SwooleConnectionAdapter($hyperfConnection);

// Now implements ConnectionInterface
$adapter->beginTransaction();
$adapter->commit();
```

## Architecture

This package provides **two components** that work together with `connection-contracts`:

### 1. `SwooleConnection` - Real Implementation

**Creates actual connection pools:**
- Creates: Hyperf connection pools - **REAL IMPLEMENTATION**
- Implements: `ConnectionManagerInterface` (from `connection-contracts` package)
- Manages connection pool lifecycle
- Handles configuration from environment variables
- Returns: `ConnectionInterface` (wrapped Hyperf Connection via `SwooleConnectionAdapter`)
- **True connection pooling** (not simple caching)
- No framework dependencies - reads `$_ENV` directly

**Database Driver Support:**
- **MySQL** (default): Primary driver with optimizations (charset, collation, strict mode)
- **PostgreSQL**: Supported via standard PDO DSN format
- **Other PDO drivers**: Via standard DSN format

**Configuration Methods:**
1. **Environment Variables** (default): Reads from `$_ENV`

**Environment Variables:**
- `DB_DRIVER` - Database driver (default: `mysql`, supports: `mysql`, `pgsql`, etc.)
- `DB_HOST` - Database host (default: `db` in Swoole, `localhost` in CLI)
- `DB_HOST_CLI_DEV` - Database host for CLI context (default: `localhost`)
- `DB_PORT` - Database port (default: `3306`)
- `DB_NAME` - Database name (default: `gemvc_db`)
- `DB_USER` - Database username (default: `root`)
- `DB_PASSWORD` - Database password (default: empty)
- `DB_CHARSET` - Database charset (default: `utf8mb4`, MySQL only)
- `DB_COLLATION` - Database collation (default: `utf8mb4_unicode_ci`, MySQL only)
- `MIN_DB_CONNECTION_POOL` - Minimum pool size (default: `8`)
- `MAX_DB_CONNECTION_POOL` - Maximum pool size (default: `16`)
- `DB_CONNECTION_TIME_OUT` - Connection timeout in seconds (default: `10.0`)
- `DB_CONNECTION_EXPIER_TIME` - Wait timeout in seconds (default: `2.0`)
- `DB_HEARTBEAT` - Heartbeat interval (default: `-1`, disabled)
- `DB_CONNECTION_MAX_AGE` - Max idle time in seconds (default: `60.0`)
- `APP_ENV` - Application environment (optional, used for dev logging)

### 2. `SwooleConnectionAdapter` - Adapter

**Wraps existing Hyperf Connection instances:**
- Wraps: Hyperf Connection instances (from connection pool)
- Implements: `ConnectionInterface` (from `connection-contracts` package)
- Provides transaction management (on Connection, not Manager)
- Error handling and state tracking
- Used by: `SwooleConnection` to wrap Hyperf connections

### Complete Flow

```
Application/Framework:
  SwooleConnection::getInstance()
    └─> Returns: SwooleConnection (singleton)
        └─> getConnection() gets from: Hyperf Pool → Connection  ← REAL IMPLEMENTATION (POOLING)
            └─> Wraps Hyperf Connection with: SwooleConnectionAdapter
                └─> Returns: ConnectionInterface

Package Structure:
  SwooleConnection (ConnectionManagerInterface)
    └─> Uses: Hyperf PoolFactory → Pool → Connection
    └─> Wraps with: SwooleConnectionAdapter
        └─> Returns: ConnectionInterface

Contracts Package:
  ConnectionManagerInterface (from connection-contracts)
    └─> Implemented by: SwooleConnection
    └─> Returns: ConnectionInterface (from connection-contracts)
```

### Integration with connection-contracts

- **`SwooleConnection`** implements `ConnectionManagerInterface` (from contracts)
- **`SwooleConnectionAdapter`** implements `ConnectionInterface` (from contracts)
- **Result:** Complete implementation of connection contracts, framework-agnostic

## Documentation

- **[CODE_REVIEW.md](CODE_REVIEW.md)** - Complete code review with detailed explanation of how the class works, architecture, and implementation details
- **[ASSESSMENT.md](ASSESSMENT.md)** - Comprehensive library assessment and quality analysis
- **[PERFORMANCE_REPORT.md](PERFORMANCE_REPORT.md)** - Performance benchmarks and metrics
- For detailed refactoring history, see `REFACTORING_COMPLETE.md` (if available)

## Refactoring & Improvements

The `SwooleConnection` class has undergone comprehensive refactoring to fix critical issues and improve code quality. See [CODE_REVIEW.md](CODE_REVIEW.md) for detailed explanation of how the class works.

### Key Improvements

-  **Multiple Connections Per Pool** - Fixed design flaw allowing true concurrent connections
-  **Null Safety** - Comprehensive null checks in cleanup methods prevent crashes
-  **Race Condition Elimination** - Thread-safe operations, no synchronization needed
-  **Memory Leak Prevention** - Multi-layered protection documented and implemented
-  **Validation** - Connection tracking validation with logging for debugging
-  **Error Handling** - Robust exception handling throughout
-  **Security** - Input validation, credential masking, error sanitization via `SwooleConnectionSecurity`
-  **Code Quality** - PHPStan Level 9 passes, all tests passing

### Refactoring Metrics

- **Tests:** 269 tests, 873 assertions (219 unit + 27 integration + 10 performance + 12 security)
- **PHPStan:** Level 9 passes (no errors)
- **Performance:** Excellent metrics (see [PERFORMANCE_REPORT.md](PERFORMANCE_REPORT.md))
- **Breaking Changes:** Zero - 100% backward compatible
- **Issues Fixed:** All 5 critical issues resolved

## Testing

### Running Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --level 9
```

### Test Results

-  **269 tests** passing (873 assertions: 219 unit + 27 integration + 10 performance + 12 security)
-  **PHPStan Level 9** passes (no errors)
-  **97.32% code coverage** (436/448 lines)
-  **Performance tests** - All benchmarks passing with excellent metrics
-  **Security tests** - All security tests passing
-  **No breaking changes** - all refactoring maintains backward compatibility

### Test Coverage

The package includes comprehensive test coverage:

- **Overall Coverage:** 97.32% lines (436/448), 94.68% methods (89/94)
- **SwooleConnection:** 80.00% methods (16/20)
- **SwooleConnectionAdapter:** 100.00% lines, 100.00% methods
- **SwooleErrorLogLogger:** 100.00% lines, 100.00% methods
- **SwooleConnectionSecurity:** 90.00% methods (9/10)
- **Total Tests:** 269 tests (219 unit + 27 integration + 10 performance + 12 security), 873 assertions
- **PHPStan:** Level 9 passes (no errors)
- **Performance:** See [PERFORMANCE_REPORT.md](PERFORMANCE_REPORT.md) for detailed benchmarks
- **Status:** All tests passing

### Test Suites

#### Unit Tests (219 tests)
- **SwooleConnectionTest** - Unit tests for `SwooleConnection` (isolated testing with mocks)
- **SwooleConnectionAdapterTest** - Unit tests for `SwooleConnectionAdapter`
- **SwooleErrorLogLoggerTest** - Unit tests for `SwooleErrorLogLogger`
- **SwooleEnvDetectTest** - Unit tests for `SwooleEnvDetect`
- **SwooleConnectionSecurityTest** - Unit tests for `SwooleConnectionSecurity`
- **DatabaseConfigTest** - Unit tests for `DatabaseConfig`
- **PoolConfigTest** - Unit tests for `PoolConfig`
- **SwooleConnectionPoolStatsTest** - Unit tests for `SwooleConnectionPoolStats`

#### Integration Tests (27 tests)
- **SwooleConnectionIntegrationTest** - Integration tests for component interactions (14 tests)
  - Complete initialization flow
  - Singleton behavior
  - Connection lifecycle
  - Multiple connections and pools
  - Error handling scenarios
  - Reset and cleanup
  - Pool statistics
  
- **SwooleConnectionUsageIntegrationTest** - Integration tests demonstrating real-world usage patterns (13 tests)
  - Service class integration
  - Repository pattern usage
  - Dependency injection patterns
  - Transaction handling
  - Error handling strategies
  - Resource cleanup patterns
  - Multiple pool usage

#### Performance Tests (10 tests)
- **SwooleConnectionPerformanceTest** - Performance benchmarks and metrics (10 tests)
  - Singleton initialization performance
  - Connection acquisition/release performance
  - Multiple connection handling
  - Memory usage analysis
  - Concurrent operations throughput
  - Pool statistics retrieval performance
  - Full lifecycle performance
  - See [PERFORMANCE_REPORT.md](PERFORMANCE_REPORT.md) for detailed results

#### Security Tests (12 tests)
- **SwooleConnectionSecurityTest** - Security tests for `SwooleConnection` (12 tests)
  - Credential protection (passwords not logged)
  - Input validation (pool name validation)
  - DSN injection prevention
  - Error message sanitization
  - Environment variable sanitization
  - Connection pool exhaustion protection
  - Timeout protection
  - Information disclosure prevention
  - Resource cleanup verification

### Generating Coverage Report

```bash
# Generate HTML coverage report
vendor/bin/phpunit --coverage-html coverage-report --coverage-filter src

# View text coverage summary
vendor/bin/phpunit --coverage-text --coverage-filter src
```

The HTML report will be generated in the `coverage-report/` directory.

## License

MIT

---

## Made with ❤️ by Ali Khorsandfard

This package is part of the [GEMVC Repository](https://github.com/gemvc) framework ecosystem.

[GEMVC is PHP framework built for Microservice.](https://www.gemvc.de)

