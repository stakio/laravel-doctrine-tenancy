<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database;

use Doctrine\DBAL\Exception as DBALException;
use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantDatabaseException;

class DatabaseConnectionManager
{
    public function __construct(
        private TenantContextInterface $tenantContext
    ) {}

    /**
     * Ensure tenant database connection is established.
     */
    public function ensureTenantConnection(): void
    {
        if (! $this->tenantContext->hasCurrentTenant()) {
            return;
        }

        try {
            $this->switchToTenant();
        } catch (DBALException $e) {
            $this->handleConnectionError($e);
        }
    }

    /**
     * Switch to tenant database.
     */
    private function switchToTenant(): void
    {
        $tenant = $this->tenantContext->getCurrentTenant();

        app(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantConnectionWrapper::class)
            ->switchToTenant($tenant);

    }

    /**
     * Handle database connection errors.
     */
    private function handleConnectionError(DBALException $e): void
    {
        $tenant = $this->tenantContext->getCurrentTenant();

        throw TenantDatabaseException::connectionFailed($tenant->value(), $e->getMessage());
    }

    /**
     * Reset to central database.
     */
    public function resetToCentral(): void
    {
        if ($this->tenantContext->hasCurrentTenant()) {
            app(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantConnectionWrapper::class)
                ->switchToCentral();

        }
    }
}
