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
use Illuminate\Support\Facades\Log;

class SetupTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $tenantId,
        private string $eventId
    ) {}

    public function handle(
        TenantDatabaseManager $databaseManager,
        EventTracker $eventTracker
    ): void {
        $tenantId = TenantId::fromString($this->tenantId);
        
        TenancyLogger::tenantResolved($tenantId, 'setup_tenant_job_started', [
            'event_id' => $this->eventId
        ]);

        try {
            // Check if auto-creation is enabled
            if (!config('tenancy.database.auto_create', true)) {
                TenancyLogger::tenantResolved($tenantId, 'auto_creation_disabled', [
                    'event_id' => $this->eventId
                ]);
                return;
            }

            // Create tenant database
            $success = $databaseManager->createTenantDatabase($tenantId);
            
            if ($success) {
                // Track migration started event if auto-migrate is enabled
                if (config('tenancy.database.auto_migrate', true)) {
                    $migrationEvent = EventFactory::createTenantMigrationStarted(
                        $tenantId,
                        'latest',
                        ['triggered_by' => 'auto_setup', 'event_id' => $this->eventId]
                    );
                    
                    $eventTracker->trackTenantEvent($migrationEvent);
                }

                TenancyLogger::tenantResolved($tenantId, 'tenant_setup_completed', [
                    'event_id' => $this->eventId,
                    'auto_migrate' => config('tenancy.database.auto_migrate', true)
                ]);
            } else {
                throw new \Exception('Failed to create tenant database');
            }

        } catch (\Exception $e) {
            // Track creation failed event
            $failedEvent = EventFactory::createTenantCreationFailed(
                $tenantId,
                $e->getMessage(),
                ['event_id' => $this->eventId, 'job' => 'SetupTenantJob']
            );
            
            $eventTracker->trackTenantEvent($failedEvent);

            TenancyLogger::tenantResolved($tenantId, 'tenant_setup_failed', [
                'event_id' => $this->eventId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $tenantId = TenantId::fromString($this->tenantId);
        
        TenancyLogger::tenantResolved($tenantId, 'setup_tenant_job_failed', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage()
        ]);
    }
}
