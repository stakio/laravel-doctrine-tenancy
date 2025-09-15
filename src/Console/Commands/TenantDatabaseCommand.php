<?php

namespace LaravelDoctrine\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Jobs\DeleteTenantDatabaseJob;
use LaravelDoctrine\Tenancy\Jobs\MigrateTenantDatabaseJob;

class TenantDatabaseCommand extends Command
{
    protected $signature = 'tenant:database 
                            {action : The action to perform (migrate, delete)}
                            {tenant-id : The tenant ID (UUID format recommended)}
                            {--sync : Run the job synchronously}
                            {--notify= : Email address to notify when job completes}';

    protected $description = 'Manage tenant databases via jobs (use --sync for synchronous execution)';

    public function handle(): int
    {
        $action = $this->argument('action');
        $tenantId = $this->argument('tenant-id');
        $isSync = $this->option('sync');
        $notifyEmail = $this->option('notify');

        try {
            $tenantIdentifier = TenantId::fromString($tenantId);

            return match ($action) {
                'delete' => $this->handleDelete($tenantIdentifier, $isSync, $notifyEmail),
                'migrate' => $this->handleMigrate($tenantIdentifier, $isSync, $notifyEmail),
                default => $this->error("Unknown action: {$action}. Use: migrate or delete") ?? 1,
            };
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function handleMigrate(TenantId $tenantId, bool $isSync, ?string $notifyEmail): int
    {
        if ($isSync) {
            $this->info("Starting migration for tenant: {$tenantId}");
            try {
                MigrateTenantDatabaseJob::dispatchSync($tenantId, $notifyEmail);
                $this->info('Tenant database migrated successfully!');
                $this->info("Migration completed for tenant: {$tenantId}");

                return 0;
            } catch (\InvalidArgumentException $e) {
                $this->error('Validation Error: '.$e->getMessage());

                return 1;
            } catch (\Exception $e) {
                $this->error('Migration Failed: '.$e->getMessage());
                $this->warn('Check the logs for detailed error information');

                return 1;
            }
        }

        // Async
        $this->info("Dispatching migrate job for tenant: {$tenantId}");
        try {
            MigrateTenantDatabaseJob::dispatch($tenantId, $notifyEmail);
            $this->info('Job dispatched successfully!');
            $this->info('Monitor progress with: php artisan queue:work');
            $this->info('Check failed jobs with: php artisan queue:failed');

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to dispatch job: {$e->getMessage()}");

            return 1;
        }
    }

    private function handleDelete(TenantId $tenantId, bool $isSync, ?string $notifyEmail): int
    {
        $this->info("Deleting database for tenant: {$tenantId}");

        if (! $this->confirm('Are you sure you want to delete this tenant database?')) {
            $this->info('Operation cancelled');

            return 0;
        }

        if ($isSync) {
            try {
                DeleteTenantDatabaseJob::dispatchSync($tenantId, $notifyEmail);
                $this->info('Tenant database deleted successfully');

                return 0;
            } catch (\Exception $e) {
                $this->error('Deletion Failed: '.$e->getMessage());

                return 1;
            }
        }

        try {
            $this->info("Dispatching delete job for tenant: {$tenantId}");
            DeleteTenantDatabaseJob::dispatch($tenantId, $notifyEmail);
            $this->info('Job dispatched successfully!');
            $this->info('Monitor progress with: php artisan queue:work');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch job: '.$e->getMessage());

            return 1;
        }
    }
}
