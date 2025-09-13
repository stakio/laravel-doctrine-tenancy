<?php

namespace LaravelDoctrine\Tenancy\Console\Commands;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Illuminate\Console\Command;

class TenantDatabaseCommand extends Command
{
    protected $signature = 'tenant:database 
                            {action : The action to perform (create, delete, migrate)}
                            {tenant-id : The tenant ID}';

    protected $description = 'Manage tenant databases';

    public function handle(TenantDatabaseManager $databaseManager): int
    {
        $action = $this->argument('action');
        $tenantId = $this->argument('tenant-id');

        try {
            $tenantIdentifier = TenantId::fromString($tenantId);

            return match ($action) {
                'create' => $this->createTenantDatabase($databaseManager, $tenantIdentifier),
                'delete' => $this->deleteTenantDatabase($databaseManager, $tenantIdentifier),
                'migrate' => $this->migrateTenantDatabase($databaseManager, $tenantIdentifier),
                default => $this->error("Unknown action: {$action}. Use: create, delete, or migrate") ?? 1,
            };
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function createTenantDatabase(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Creating database for tenant: {$tenantId}");

        $databaseManager->createTenantDatabase($tenantId);
        $this->info("✅ Tenant database created successfully");
        return 0;
    }

    private function deleteTenantDatabase(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Deleting database for tenant: {$tenantId}");

        if ($this->confirm('Are you sure you want to delete this tenant database?')) {
            $databaseManager->deleteTenantDatabase($tenantId);
            $this->info("✅ Tenant database deleted successfully");
            return 0;
        }

        $this->info("Operation cancelled");
        return 0;
    }

    private function migrateTenantDatabase(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Migrating database for tenant: {$tenantId}");

        $databaseManager->migrateTenantDatabase($tenantId);
        $this->info("✅ Tenant database migrated successfully");
        return 0;
    }

}
