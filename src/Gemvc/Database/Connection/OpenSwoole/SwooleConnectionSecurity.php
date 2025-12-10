<?php

declare(strict_types=1);

namespace Gemvc\Database\Connection\OpenSwoole;

/**
 * Security utility class for SwooleConnection
 * 
 * Provides fundamental security functions:
 * - Input validation (pool names, environment variables)
 * - DSN component sanitization
 * - Error message sanitization (removes sensitive data)
 * - Credential masking for logging
 * 
 * This class centralizes security concerns to ensure consistent
 * security practices across the library.
 */
class SwooleConnectionSecurity
{
    /**
     * Validate pool name
     * 
     * Pool names should be alphanumeric with underscores and hyphens only.
     * This prevents injection attacks and ensures safe pool name usage.
     * 
     * @param string $poolName The pool name to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidPoolName(string $poolName): bool
    {
        // Pool names should be: alphanumeric, underscore, hyphen, 1-64 chars
        return preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $poolName) === 1;
    }

    /**
     * Sanitize pool name
     * 
     * Removes invalid characters from pool name, keeping only safe characters.
     * Returns 'default' if sanitization results in empty string.
     * 
     * @param string $poolName The pool name to sanitize
     * @return string Sanitized pool name
     */
    public static function sanitizePoolName(string $poolName): string
    {
        // Remove all characters except alphanumeric, underscore, hyphen
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $poolName);
        
        // If empty after sanitization, return default
        if ($sanitized === '') {
            return 'default';
        }
        
        // If result contains only special characters (no alphanumeric), return default
        if (preg_match('/^[_-]+$/', $sanitized) === 1) {
            return 'default';
        }
        
        // Limit length to 64 characters
        return substr($sanitized, 0, 64);
    }

    /**
     * Validate database host
     * 
     * Validates that host is safe for use in DSN.
     * Allows: hostname, IP address, localhost
     * 
     * @param string $host The database host to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidDatabaseHost(string $host): bool
    {
        // Empty host is invalid
        if ($host === '') {
            return false;
        }
        
        // Check for SQL injection patterns
        if (self::containsSqlInjectionPattern($host)) {
            return false;
        }
        
        // Check for path traversal patterns
        if (strpos($host, '..') !== false || strpos($host, '/') !== false) {
            return false;
        }
        
        // Basic validation: hostname, IP, or localhost
        // This is a basic check - PDO will handle actual connection
        return strlen($host) <= 255 && !preg_match('/[<>"\']/', $host);
    }

    /**
     * Validate database name
     * 
     * Validates that database name is safe for use in DSN.
     * 
     * @param string $databaseName The database name to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidDatabaseName(string $databaseName): bool
    {
        // Empty database name is invalid
        if ($databaseName === '') {
            return false;
        }
        
        // Check for SQL injection patterns
        if (self::containsSqlInjectionPattern($databaseName)) {
            return false;
        }
        
        // Check for path traversal patterns
        if (strpos($databaseName, '..') !== false || strpos($databaseName, '/') !== false) {
            return false;
        }
        
        // Basic validation: alphanumeric, underscore, hyphen, dot
        // MySQL allows dots in database names
        return strlen($databaseName) <= 64 && preg_match('/^[a-zA-Z0-9_.-]+$/', $databaseName) === 1;
    }

    /**
     * Sanitize error message to remove sensitive data
     * 
     * Removes passwords and connection strings from error messages
     * to prevent information disclosure.
     * 
     * @param string $message The error message to sanitize
     * @param string|null $password Optional password to mask (if known)
     * @return string Sanitized error message
     */
    public static function sanitizeErrorMessage(string $message, ?string $password = null): string
    {
        $sanitized = $message;
        
        // Mask password if provided
        if ($password !== null && $password !== '') {
            $sanitized = str_replace($password, '***', $sanitized);
        }
        
        // Remove common password patterns
        $sanitized = preg_replace('/password\s*[=:]\s*[^\s;]+/i', 'password=***', $sanitized);
        $sanitized = preg_replace('/pwd\s*[=:]\s*[^\s;]+/i', 'pwd=***', $sanitized);
        $sanitized = preg_replace('/pass\s*[=:]\s*[^\s;]+/i', 'pass=***', $sanitized);
        
        // Remove full connection string patterns
        $sanitized = preg_replace('/mysql:[^;]+password[^;]+/i', 'mysql:***', $sanitized);
        $sanitized = preg_replace('/pgsql:[^;]+password[^;]+/i', 'pgsql:***', $sanitized);
        
        return $sanitized;
    }

    /**
     * Mask password in string
     * 
     * Replaces password with asterisks for safe logging.
     * 
     * @param string $text The text that may contain password
     * @param string $password The password to mask
     * @return string Text with password masked
     */
    public static function maskPassword(string $text, string $password): string
    {
        if ($password === '') {
            return $text;
        }
        
        return str_replace($password, '***', $text);
    }

    /**
     * Sanitize environment variable value
     * 
     * Basic sanitization for environment variables used in DSN.
     * Removes null bytes and trims whitespace.
     * 
     * @param string $value The environment variable value
     * @return string Sanitized value
     */
    public static function sanitizeEnvValue(string $value): string
    {
        // Remove null bytes (security risk)
        $sanitized = str_replace("\0", '', $value);
        
        // Trim whitespace
        $sanitized = trim($sanitized);
        
        return $sanitized;
    }

    /**
     * Check if string contains SQL injection patterns
     * 
     * Basic check for common SQL injection patterns.
     * This is a basic check - PDO prepared statements provide the real protection.
     * 
     * @param string $value The value to check
     * @return bool True if contains suspicious patterns
     */
    private static function containsSqlInjectionPattern(string $value): bool
    {
        $patterns = [
            '/;\s*(DROP|DELETE|TRUNCATE|ALTER|CREATE|INSERT|UPDATE|EXEC|EXECUTE)/i',
            '/\'\s*OR\s*\'/i',
            '/\'\s*AND\s*\'/i',
            '/UNION\s+SELECT/i',
            '/--/',
            '/\/\*/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Validate and sanitize pool name
     * 
     * Validates pool name and sanitizes if invalid.
     * Returns sanitized version or throws exception if too dangerous.
     * 
     * @param string $poolName The pool name to validate and sanitize
     * @return string Validated and sanitized pool name
     * @throws \InvalidArgumentException If pool name is too dangerous
     */
    public static function validateAndSanitizePoolName(string $poolName): string
    {
        // First try validation
        if (self::isValidPoolName($poolName)) {
            return $poolName;
        }
        
        // If invalid, sanitize
        $sanitized = self::sanitizePoolName($poolName);
        
        // If sanitization results in default, log warning but allow
        // (This prevents breaking existing code while improving security)
        if ($sanitized === 'default' && $poolName !== 'default') {
            // Could log warning here if logger available
        }
        
        return $sanitized;
    }

    /**
     * Validate database configuration values
     * 
     * Validates all database configuration values for security.
     * 
     * @param string $host Database host
     * @param string $database Database name
     * @param string $user Database user
     * @return array{valid: bool, errors: array<string>} Validation result
     */
    public static function validateDatabaseConfig(string $host, string $database, string $user): array
    {
        $errors = [];
        
        if (!self::isValidDatabaseHost($host)) {
            $errors[] = 'Invalid database host';
        }
        
        if (!self::isValidDatabaseName($database)) {
            $errors[] = 'Invalid database name';
        }
        
        // Basic user validation (alphanumeric, underscore, hyphen, dot, @)
        if ($user !== '' && !preg_match('/^[a-zA-Z0-9_.@-]+$/', $user)) {
            $errors[] = 'Invalid database user';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

