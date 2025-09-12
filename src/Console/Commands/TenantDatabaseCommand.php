<?php

namespace LaravelDoctrine\Tenancy\Console\Commands;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Illuminate\Console\Command;

class TenantDatabaseCommand extends Command
{
    protected $signature = 'tenant:database 
                            {action : The action to perform (create, delete, migrate, seed, status)}
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
                'seed' => $this->seedTenantDatabase($databaseManager, $tenantIdentifier),
                'status' => $this->getTenantStatus($databaseManager, $tenantIdentifier),
                default => $this->error("Unknown action: {$action}"),
            };
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }

    private function createTenantDatabase(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Creating database for tenant: {$tenantId}");

        if ($databaseManager->createTenantDatabase($tenantId)) {
            $this->info("✅ Tenant database created successfully");
            return 0;
        }

        $this->error("❌ Failed to create tenant database");
        return 1;
    }

    private function deleteTenantDatabase(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Deleting database for tenant: {$tenantId}");

        if ($this->confirm('Are you sure you want to delete this tenant database?')) {
            if ($databaseManager->deleteTenantDatabase($tenantId)) {
                $this->info("✅ Tenant database deleted successfully");
                return 0;
            }

            $this->error("❌ Failed to delete tenant database");
            return 1;
        }

        $this->info("Operation cancelled");
        return 0;
    }

    private function migrateTenantDatabase(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Migrating database for tenant: {$tenantId}");

        if ($databaseManager->migrateTenantDatabase($tenantId)) {
            $this->info("✅ Tenant database migrated successfully");
            return 0;
        }

        $this->error("❌ Failed to migrate tenant database");
        return 1;
    }

    private function seedTenantDatabase(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Seeding database for tenant: {$tenantId}");

        if ($databaseManager->seedTenantDatabase($tenantId)) {
            $this->info("✅ Tenant database seeded successfully");
            return 0;
        }

        $this->error("❌ Failed to seed tenant database");
        return 1;
    }

    private function getTenantStatus(TenantDatabaseManager $databaseManager, TenantId $tenantId): int
    {
        $this->info("Getting status for tenant: {$tenantId}");

        $status = $databaseManager->getTenantMigrationStatus($tenantId);

        if (isset($status['error'])) {
            $this->error("❌ Error: {$status['error']}");
            return 1;
        }

        $this->info("✅ Tenant database status:");
        $this->line("Status: {$status['status']}");
        $this->line("Total migrations: {$status['total_migrations']}");

        if (isset($status['migrations'])) {
            $this->table(
                ['Migration', 'Batch'],
                collect($status['migrations'])->map(fn($migration) => [
                    $migration->migration,
                    $migration->batch,
                ])
            );
        }

        return 0;
    }
}
