<?php

namespace LaravelDoctrine\Tenancy\Exceptions;

/**
 * Exception thrown when package configuration is invalid.
 */
class ConfigurationException extends TenancyException
{
    public function __construct(string $configKey, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Configuration error for '{$configKey}': {$reason}",
            0,
            $previous
        );
    }
}
