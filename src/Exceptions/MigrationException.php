<?php

namespace LaravelDoctrine\Tenancy\Exceptions;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;

/**
 * Exception thrown when tenant database migrations fail.
 */
class MigrationException extends TenancyException
{
    public function __construct(
        TenantIdentifier $tenantId,
        string $reason,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Migration failed for tenant {$tenantId->value()}: {$reason}",
            0,
            $previous
        );
    }
}
