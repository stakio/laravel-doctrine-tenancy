<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Validation;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenancyConfigurationException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;

class InputValidator
{
    /**
     * Validate and sanitize tenant ID.
     */
    public static function validateTenantId(mixed $tenantId): string
    {
        if (empty($tenantId)) {
            throw TenancyConfigurationException::invalidConfiguration('Tenant ID cannot be empty');
        }

        $tenantId = (string) $tenantId;
        $tenantId = trim($tenantId);

        if (empty($tenantId)) {
            throw TenancyConfigurationException::invalidConfiguration('Tenant ID cannot be empty after trimming');
        }

        // Validate UUID format
        if (!self::isValidUuid($tenantId)) {
            TenancyLogger::securityEvent('invalid_tenant_id_format', [
                'tenant_id' => $tenantId,
                'length' => strlen($tenantId)
            ]);
            throw TenancyConfigurationException::invalidConfiguration('Invalid tenant ID format');
        }

        return $tenantId;
    }

    /**
     * Validate and sanitize domain name.
     */
    public static function validateDomain(string $domain): string
    {
        if (empty($domain)) {
            throw TenancyConfigurationException::invalidConfiguration('Domain cannot be empty');
        }

        $domain = trim($domain);
        $domain = strtolower($domain);

        if (empty($domain)) {
            throw TenancyConfigurationException::invalidConfiguration('Domain cannot be empty after sanitization');
        }

        // Validate domain format
        if (!self::isValidDomain($domain)) {
            TenancyLogger::securityEvent('invalid_domain_format', [
                'domain' => $domain,
                'length' => strlen($domain)
            ]);
            throw TenancyConfigurationException::invalidConfiguration('Invalid domain format');
        }

        // Check for suspicious patterns
        if (self::containsSuspiciousPatterns($domain)) {
            TenancyLogger::securityEvent('suspicious_domain_pattern', [
                'domain' => $domain
            ]);
            throw TenancyConfigurationException::invalidConfiguration('Domain contains suspicious patterns');
        }

        return $domain;
    }

    /**
     * Validate and sanitize entity class name.
     */
    public static function validateEntityClass(string $className): string
    {
        if (empty($className)) {
            throw TenancyConfigurationException::invalidConfiguration('Entity class name cannot be empty');
        }

        $className = trim($className);

        if (empty($className)) {
            throw TenancyConfigurationException::invalidConfiguration('Entity class name cannot be empty after trimming');
        }

        // Validate class name format
        if (!self::isValidClassName($className)) {
            TenancyLogger::securityEvent('invalid_entity_class_format', [
                'class_name' => $className
            ]);
            throw TenancyConfigurationException::invalidConfiguration('Invalid entity class name format');
        }

        return $className;
    }

    /**
     * Validate and sanitize header value.
     */
    public static function validateHeaderValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        // Check for suspicious patterns in header values
        if (self::containsSuspiciousPatterns($value)) {
            TenancyLogger::securityEvent('suspicious_header_value', [
                'value' => $value,
                'length' => strlen($value)
            ]);
            throw TenancyConfigurationException::invalidConfiguration('Header value contains suspicious patterns');
        }

        return $value;
    }

    /**
     * Validate UUID format.
     */
    private static function isValidUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Validate domain format.
     */
    private static function isValidDomain(string $domain): bool
    {
        // Basic domain validation
        if (strlen($domain) > 255) {
            return false;
        }

        // Check for valid domain characters
        if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
            return false;
        }

        // Check for valid domain structure
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }

        return true;
    }

    /**
     * Validate class name format.
     */
    private static function isValidClassName(string $className): bool
    {
        // Check for valid PHP class name format
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_\\\\]*$/', $className) === 1;
    }

    /**
     * Check for suspicious patterns that might indicate injection attempts.
     */
    private static function containsSuspiciousPatterns(string $input): bool
    {
        $suspiciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload/i',
            '/onerror/i',
            '/onclick/i',
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/proc_open/i',
            '/popen/i',
            '/file_get_contents/i',
            '/fopen/i',
            '/fwrite/i',
            '/fputs/i',
            '/include\s*\(/i',
            '/require\s*\(/i',
            '/include_once/i',
            '/require_once/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize string input by removing potentially dangerous characters.
     */
    public static function sanitizeString(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Remove control characters except newlines and tabs
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        return $input;
    }

    /**
     * Validate request input for tenant resolution.
     */
    public static function validateRequestInput(array $input): array
    {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            $sanitizedKey = self::sanitizeString($key);
            $sanitizedValue = is_string($value) ? self::sanitizeString($value) : $value;
            
            $sanitized[$sanitizedKey] = $sanitizedValue;
        }
        
        return $sanitized;
    }
}
