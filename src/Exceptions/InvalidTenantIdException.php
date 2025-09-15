<?php

namespace LaravelDoctrine\Tenancy\Exceptions;

/**
 * Exception thrown when an invalid tenant ID is provided.
 */
class InvalidTenantIdException extends TenancyException
{
    public function __construct(string $tenantId, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Invalid tenant ID '{$tenantId}': {$reason}",
            0,
            $previous
        );
    }
}
