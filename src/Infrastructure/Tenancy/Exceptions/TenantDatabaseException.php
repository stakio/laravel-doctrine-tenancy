<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions;

class TenantDatabaseException extends TenancyException
{
    public static function connectionFailed(string $tenantId, string $reason = ''): self
    {
        $message = "Failed to connect to tenant database for tenant: {$tenantId}";
        if ($reason) {
            $message .= " - {$reason}";
        }
        return new self($message, 500);
    }

    public static function creationFailed(string $tenantId, string $reason = ''): self
    {
        $message = "Failed to create tenant database for tenant: {$tenantId}";
        if ($reason) {
            $message .= " - {$reason}";
        }
        return new self($message, 500);
    }

    public static function notFound(string $tenantId): self
    {
        return new self("Tenant database not found for tenant: {$tenantId}", 404);
    }
}
