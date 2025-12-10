# Refactoring Plan: Exception Handling in SwooleErrorLogLogger

## Current State Analysis

### Exception Handling Patterns Found in SwooleConnection:

1. **resetInstance()** (lines 116-119):
   ```php
   catch (\Throwable $e) {
       if (self::$instance->logger !== null) {
           self::$instance->logger->error('Error releasing connection in resetInstance: ' . $e->getMessage());
       }
   }
   ```

2. **handleInitializationFailure()** (lines 268-269):
   ```php
   if ($this->logger !== null) {
       $this->logger->error($errorMessage);
   }
   ```

3. **getConnection()** (lines 296-305):
   ```php
   catch (\Throwable $e) {
       // ... context building ...
       $logger = $this->logger ?? new SwooleErrorLogLogger();
       $logger->error("SwooleConnection::getConnection() - Error: " . $e->getMessage() . " [Pool: $poolName]");
   }
   ```

4. **releaseConnection()** (lines 334-336):
   ```php
   if (!$found && $this->logger !== null) {
       $this->logger->warning('Attempted to release connection not found in activeConnections tracking');
   }
   ```

5. **__destruct()** (lines 459-462):
   ```php
   catch (\Throwable $e) {
       if ($this->logger !== null) {
           $this->logger->error('Error releasing connection in __destruct: ' . $e->getMessage());
       }
   }
   ```

### Problems Identified:

1. **Code Duplication**: Same pattern repeated 5+ times
2. **Inconsistent Formatting**: Different message formats across locations
3. **Null Checks**: Repeated `if ($this->logger !== null)` checks
4. **Manual Message Building**: String concatenation for error messages
5. **Missing Exception Details**: Only message is logged, not class, code, file, line
6. **Test Coverage**: Each try-catch block needs separate test coverage

## Proposed Solution

### Phase 1: Extend SwooleErrorLogLogger

Add new methods to `SwooleErrorLogLogger`:

1. **`handleException(\Throwable $e, string $context = '', string $logLevel = 'error'): void`**
   - Logs exception with full details (class, message, code, file, line)
   - Includes optional context information
   - Supports different log levels (error, critical, warning)
   - Format: `[LEVEL] {context}: {ExceptionClass} ({code}): {message} [File: {file}:{line}]`

2. **`handleWarning(string $message, string $context = ''): void`**
   - Standardized warning logging with context
   - Format: `[WARNING] {context}: {message}`

### Phase 2: Refactor SwooleConnection

Replace all exception handling blocks:

**Before:**
```php
catch (\Throwable $e) {
    if ($this->logger !== null) {
        $this->logger->error('Error message: ' . $e->getMessage());
    }
}
```

**After:**
```php
catch (\Throwable $e) {
    ($this->logger ?? new SwooleErrorLogLogger())->handleException($e, 'Context description');
}
```

### Phase 3: Add Tests

Create comprehensive tests for:
- `handleException()` with different exception types
- `handleException()` with context
- `handleException()` with different log levels
- `handleWarning()` with context
- Edge cases (null context, empty messages)

## Benefits

1. **Code Reduction**: ~15-20 lines of duplicate code removed
2. **Consistency**: All exceptions logged in same format
3. **Better Debugging**: Full exception details (class, file, line, code)
4. **Easier Testing**: Test logger methods once, not each try-catch
5. **Better Coverage**: Single method to test vs multiple try-catch blocks
6. **Maintainability**: Change logging format in one place
7. **No Null Checks**: Use `??` operator for fallback logger

## Implementation Details

### Method Signature:

```php
/**
 * Handle and log an exception with context
 * 
 * @param \Throwable $e The exception to handle
 * @param string $context Additional context description (e.g., "Error releasing connection in resetInstance")
 * @param string $logLevel Log level: 'error', 'critical', 'warning' (default: 'error')
 * @return void
 */
public function handleException(\Throwable $e, string $context = '', string $logLevel = 'error'): void
{
    $contextStr = $context !== '' ? "$context: " : '';
    $message = sprintf(
        '%s%s (%d): %s [File: %s:%d]',
        $contextStr,
        get_class($e),
        $e->getCode(),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    
    match($logLevel) {
        'critical' => $this->critical($message),
        'warning' => $this->warning($message),
        default => $this->error($message),
    };
}
```

### Refactoring Locations:

1. `resetInstance()` - line 116-119
2. `handleInitializationFailure()` - line 268-269
3. `getConnection()` - line 296-305
4. `releaseConnection()` - line 334-336 (use handleWarning)
5. `__destruct()` - line 459-462

## Testing Strategy

1. **Unit Test for handleException():**
   - Test with RuntimeException
   - Test with different exception types
   - Test with context
   - Test without context
   - Test with different log levels
   - Verify output format

2. **Unit Test for handleWarning():**
   - Test with context
   - Test without context
   - Verify warning level

3. **Integration Test:**
   - Verify all refactored try-catch blocks still work
   - Verify exception details are logged correctly

## Risk Assessment

- **Low Risk**: Logger is already well-tested
- **Backward Compatible**: New methods don't break existing code
- **Easy Rollback**: Can revert if needed
- **No Breaking Changes**: All existing functionality preserved

## Estimated Impact

- **Lines Removed**: ~15-20 lines of duplicate code
- **Lines Added**: ~30-40 lines (methods + tests)
- **Coverage Improvement**: +5-10% (single method vs multiple try-catch blocks)
- **Code Quality**: Significantly improved (DRY principle)

