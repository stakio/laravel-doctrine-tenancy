<?php

namespace LaravelDoctrine\Tenancy\Jobs;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventTracker;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventFactory;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunTenantMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $tenantId,
        private string $eventId,
        private string $migrationVersion = 'latest'
    ) {}

    public function handle(
        TenantDatabaseManager $databaseManager,
        EventTracker $eventTracker
    ): void {
        $tenantId = TenantId::fromString($this->tenantId);
        
        TenancyLogger::tenantResolved($tenantId, 'migration_job_started', [
            'event_id' => $this->eventId,
            'migration_version' => $this->migrationVersion
        ]);

        try {
            // Run tenant migrations
            $success = $databaseManager->migrateTenantDatabase($tenantId);
            
            if ($success) {
                // Track migration completed event
                $completedEvent = EventFactory::createTenantMigrationCompleted(
                    $tenantId,
                    $this->migrationVersion,
                    0,
                    ['migrated_count' => 0, 'event_id' => $this->eventId]
                );
                
                $eventTracker->trackTenantEvent($completedEvent);

                TenancyLogger::tenantResolved($tenantId, 'migration_job_completed', [
                    'event_id' => $this->eventId,
                    'migration_version' => $this->migrationVersion
                ]);
            } else {
                throw new \Exception('Migration command returned false');
            }

        } catch (\Exception $e) {
            // Track migration failed event
            $failedEvent = EventFactory::createTenantMigrationFailed(
                $tenantId,
                $this->migrationVersion,
                $e->getMessage(),
                ['event_id' => $this->eventId, 'job' => 'RunTenantMigrationJob']
            );
            
            $eventTracker->trackTenantEvent($failedEvent);

            TenancyLogger::tenantResolved($tenantId, 'migration_job_failed', [
                'event_id' => $this->eventId,
                'migration_version' => $this->migrationVersion,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $tenantId = TenantId::fromString($this->tenantId);
        
        TenancyLogger::tenantResolved($tenantId, 'migration_job_failed', [
            'event_id' => $this->eventId,
            'migration_version' => $this->migrationVersion,
            'error' => $exception->getMessage()
        ]);
    }
}
