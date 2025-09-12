<?php

/**
 * Event Tracking Example
 * 
 * This example demonstrates how to use the event tracking system
 * for monitoring tenant and domain lifecycle events.
 */

use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventTracker;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventFactory;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventDispatcher;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;

class EventTrackingExample
{
    public function __construct(
        private EventTracker $eventTracker,
        private EventDispatcher $eventDispatcher
    ) {
    }

    /**
     * Example: Track tenant creation process
     */
    public function trackTenantCreation(TenantId $tenantId): void
    {
        try {
            // Create tenant in database
            $this->createTenantInDatabase($tenantId);
            
            // Track successful creation
            $event = EventFactory::createTenantCreated($tenantId, [
                'created_by' => auth()->id(),
                'source' => 'admin_panel'
            ]);
            
            $eventLog = $this->eventTracker->trackTenantEvent($event);
            $this->eventDispatcher->dispatchJobsForEvent($eventLog);
            
        } catch (\Exception $e) {
            // Track failed creation
            $event = EventFactory::createTenantCreationFailed(
                $tenantId, 
                $e->getMessage(),
                ['error_code' => 'TENANT_CREATE_FAILED']
            );
            
            $eventLog = $this->eventTracker->trackTenantEvent($event);
            $this->eventDispatcher->dispatchJobsForEvent($eventLog);
            
            throw $e;
        }
    }

    /**
     * Example: Track migration process
     */
    public function trackTenantMigration(TenantId $tenantId, string $migrationVersion): void
    {
        // Track migration start
        $startedEvent = EventFactory::createTenantMigrationStarted(
            $tenantId, 
            $migrationVersion,
            ['migration_count' => 5]
        );
        
        $eventLog = $this->eventTracker->trackTenantEvent($startedEvent);
        $this->eventDispatcher->dispatchJobsForEvent($eventLog);

        try {
            // Run migrations
            $migratedCount = $this->runTenantMigrations($tenantId, $migrationVersion);
            
            // Track successful completion
            $completedEvent = EventFactory::createTenantMigrationCompleted(
                $tenantId,
                $migrationVersion,
                $migratedCount,
                ['duration' => 120] // seconds
            );
            
            $eventLog = $this->eventTracker->trackTenantEvent($completedEvent);
            $this->eventDispatcher->dispatchJobsForEvent($eventLog);
            
        } catch (\Exception $e) {
            // Track migration failure
            $failedEvent = EventFactory::createTenantMigrationFailed(
                $tenantId,
                $migrationVersion,
                $e->getMessage(),
                ['error_code' => 'MIGRATION_FAILED']
            );
            
            $eventLog = $this->eventTracker->trackTenantEvent($failedEvent);
            $this->eventDispatcher->dispatchJobsForEvent($eventLog);
            
            throw $e;
        }
    }

    /**
     * Example: Track domain management
     */
    public function trackDomainManagement(TenantId $tenantId, string $domainName): void
    {
        $domain = new Domain($domainName);
        
        // Track domain creation
        $createdEvent = EventFactory::createDomainCreated(
            $tenantId,
            $domain,
            ['is_primary' => true, 'ssl_enabled' => true]
        );
        
        $eventLog = $this->eventTracker->trackDomainEvent($createdEvent);
        $this->eventDispatcher->dispatchJobsForEvent($eventLog);
        
        // Track domain activation
        $activatedEvent = EventFactory::createDomainActivated($tenantId, $domain);
        $eventLog = $this->eventTracker->trackDomainEvent($activatedEvent);
        $this->eventDispatcher->dispatchJobsForEvent($eventLog);
    }

    /**
     * Example: Monitor tenant health
     */
    public function monitorTenantHealth(TenantId $tenantId): array
    {
        $status = $this->eventTracker->getTenantStatus($tenantId);
        
        // Check if tenant needs attention
        if ($status['has_failures']) {
            $this->eventDispatcher->retryFailedEvents();
        }
        
        // Check if tenant is created but not migrated
        if ($status['is_created'] && !$status['is_migrated']) {
            $this->eventDispatcher->processTenantStatus($tenantId);
        }
        
        return $status;
    }

    /**
     * Example: Get audit trail for tenant
     */
    public function getTenantAuditTrail(TenantId $tenantId): array
    {
        return $this->eventTracker->getTenantEvents($tenantId);
    }

    /**
     * Example: Get failed events for retry
     */
    public function getFailedEventsForRetry(): array
    {
        return $this->eventTracker->getFailedEvents();
    }

    /**
     * Example: Update event status (e.g., from job processing)
     */
    public function updateEventStatus(string $eventId, string $status): void
    {
        $this->eventTracker->updateEventStatus(
            \Ramsey\Uuid\Uuid::fromString($eventId),
            $status
        );
    }

    /**
     * Example: Add metadata to event (e.g., processing metrics)
     */
    public function addEventMetrics(string $eventId, array $metrics): void
    {
        foreach ($metrics as $key => $value) {
            $this->eventTracker->addEventMetadata(
                \Ramsey\Uuid\Uuid::fromString($eventId),
                $key,
                $value
            );
        }
    }

    // Private helper methods (implementations would go here)
    private function createTenantInDatabase(TenantId $tenantId): void
    {
        // Implementation for creating tenant in database
    }

    private function runTenantMigrations(TenantId $tenantId, string $version): int
    {
        // Implementation for running migrations
        return 5; // Example return value
    }
}
