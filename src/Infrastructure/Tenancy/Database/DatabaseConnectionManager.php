<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantDatabaseException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use Doctrine\DBAL\Exception as DBALException;

class DatabaseConnectionManager
{
    public function __construct(
        private TenantContextInterface $tenantContext
    ) {
    }

    /**
     * Ensure tenant database connection is established.
     */
    public function ensureTenantConnection(): void
    {
        if (!$this->tenantContext->hasCurrentTenant()) {
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
            
        TenancyLogger::databaseConnectionChanged('central', 'tenant', $tenant);
    }

    /**
     * Handle database connection errors.
     */
    private function handleConnectionError(DBALException $e): void
    {
        $tenant = $this->tenantContext->getCurrentTenant();
        
        if ($this->isDatabaseNotFoundError($e) && $this->isAutoCreateEnabled()) {
            $this->createTenantDatabase($tenant);
            return;
        }

        TenancyLogger::tenantDatabaseCreationFailed($tenant, $e->getMessage());
        throw TenantDatabaseException::connectionFailed($tenant->value(), $e->getMessage());
    }

    /**
     * Check if the error indicates database not found.
     */
    private function isDatabaseNotFoundError(DBALException $e): bool
    {
        return str_contains($e->getMessage(), 'Unknown database') ||
               str_contains($e->getMessage(), 'database does not exist');
    }

    /**
     * Check if auto-creation is enabled.
     */
    private function isAutoCreateEnabled(): bool
    {
        return config('tenancy.database.auto_create', false);
    }

    /**
     * Create tenant database.
     */
    private function createTenantDatabase($tenant): void
    {
        try {
            app(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager::class)
                ->createTenantDatabase($tenant);
                
            $this->switchToTenant();
            
            TenancyLogger::tenantDatabaseCreated($tenant);
        } catch (\Exception $e) {
            TenancyLogger::tenantDatabaseCreationFailed($tenant, $e->getMessage());
            throw TenantDatabaseException::creationFailed($tenant->value(), $e->getMessage());
        }
    }

    /**
     * Reset to central database.
     */
    public function resetToCentral(): void
    {
        if ($this->tenantContext->hasCurrentTenant()) {
            app(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantConnectionWrapper::class)
                ->switchToCentral();
                
            TenancyLogger::databaseConnectionChanged('tenant', 'central', $this->tenantContext->getCurrentTenant());
        }
    }
}
