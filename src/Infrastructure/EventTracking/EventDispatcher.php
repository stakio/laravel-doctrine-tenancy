<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\EventTracking;

use LaravelDoctrine\Tenancy\Domain\EventTracking\TenantEventLog;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Facades\Log;

/**
 * Event Dispatcher
 * 
 * Handles dispatching of jobs based on tenant events and their status.
 * Provides retry mechanisms and failure handling.
 * 
 * @package LaravelDoctrine\Tenancy\Infrastructure\EventTracking
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class EventDispatcher
{
    public function __construct(
        private EventTracker $eventTracker,
        private Queue $queue
    ) {
    }

    /**
     * Dispatch jobs based on event type and status.
     */
    public function dispatchJobsForEvent(TenantEventLog $eventLog): void
    {
        $tenantId = $eventLog->getTenantId();
        $eventType = $eventLog->getEventType();
        $status = $eventLog->getStatus();

        TenancyLogger::tenantResolved($tenantId, 'dispatching_jobs', [
            'event_type' => $eventType,
            'status' => $status,
            'event_id' => $eventLog->getId()->toString()
        ]);

        switch ($eventType) {
            case 'tenant_created':
                if ($status === 'completed') {
                    $this->dispatchTenantCreatedJobs($eventLog);
                }
                break;

            case 'tenant_creation_failed':
                if ($status === 'failed') {
                    $this->dispatchTenantCreationFailedJobs($eventLog);
                }
                break;

            case 'tenant_migration_started':
                if ($status === 'in_progress') {
                    $this->dispatchMigrationStartedJobs($eventLog);
                }
                break;

            case 'tenant_migration_completed':
                if ($status === 'completed') {
                    $this->dispatchMigrationCompletedJobs($eventLog);
                }
                break;

            case 'tenant_migration_failed':
                if ($status === 'failed') {
                    $this->dispatchMigrationFailedJobs($eventLog);
                }
                break;

            case 'domain_created':
            case 'domain_activated':
            case 'domain_deactivated':
                if ($status === 'completed') {
                    $this->dispatchDomainEventJobs($eventLog);
                }
                break;
        }
    }

    /**
     * Dispatch jobs for successful tenant creation.
     */
    private function dispatchTenantCreatedJobs(TenantEventLog $eventLog): void
    {
        $tenantId = $eventLog->getTenantId();
        
        // Dispatch tenant setup jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\SetupTenantJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString()
        ]);

        // Dispatch notification jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\NotifyTenantCreatedJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString()
        ]);

        TenancyLogger::tenantResolved($tenantId, 'tenant_created_jobs_dispatched', [
            'event_id' => $eventLog->getId()->toString()
        ]);
    }

    /**
     * Dispatch jobs for failed tenant creation.
     */
    private function dispatchTenantCreationFailedJobs(TenantEventLog $eventLog): void
    {
        $tenantId = $eventLog->getTenantId();
        
        // Dispatch cleanup jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\CleanupFailedTenantJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'failure_reason' => $eventLog->getFailureReason()
        ]);

        // Dispatch notification jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\NotifyTenantCreationFailedJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'failure_reason' => $eventLog->getFailureReason()
        ]);

        TenancyLogger::tenantResolved($tenantId, 'tenant_creation_failed_jobs_dispatched', [
            'event_id' => $eventLog->getId()->toString(),
            'failure_reason' => $eventLog->getFailureReason()
        ]);
    }

    /**
     * Dispatch jobs for migration started.
     */
    private function dispatchMigrationStartedJobs(TenantEventLog $eventLog): void
    {
        $tenantId = $eventLog->getTenantId();
        $metadata = $eventLog->getMetadata();
        
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\RunTenantMigrationJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest'
        ]);

        TenancyLogger::tenantResolved($tenantId, 'migration_started_jobs_dispatched', [
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest'
        ]);
    }

    /**
     * Dispatch jobs for completed migration.
     */
    private function dispatchMigrationCompletedJobs(TenantEventLog $eventLog): void
    {
        $tenantId = $eventLog->getTenantId();
        $metadata = $eventLog->getMetadata();
        
        // Dispatch post-migration jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\PostMigrationJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest',
            'migrated_count' => $metadata['migrated_count'] ?? 0
        ]);

        // Dispatch notification jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\NotifyMigrationCompletedJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest'
        ]);

        TenancyLogger::tenantResolved($tenantId, 'migration_completed_jobs_dispatched', [
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest'
        ]);
    }

    /**
     * Dispatch jobs for failed migration.
     */
    private function dispatchMigrationFailedJobs(TenantEventLog $eventLog): void
    {
        $tenantId = $eventLog->getTenantId();
        $metadata = $eventLog->getMetadata();
        
        // Dispatch retry jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\RetryMigrationJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest',
            'failure_reason' => $eventLog->getFailureReason()
        ]);

        // Dispatch notification jobs
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\NotifyMigrationFailedJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest',
            'failure_reason' => $eventLog->getFailureReason()
        ]);

        TenancyLogger::tenantResolved($tenantId, 'migration_failed_jobs_dispatched', [
            'event_id' => $eventLog->getId()->toString(),
            'migration_version' => $metadata['migration_version'] ?? 'latest',
            'failure_reason' => $eventLog->getFailureReason()
        ]);
    }

    /**
     * Dispatch jobs for domain events.
     */
    private function dispatchDomainEventJobs(TenantEventLog $eventLog): void
    {
        $tenantId = $eventLog->getTenantId();
        $eventType = $eventLog->getEventType();
        $domain = $eventLog->getDomain();
        
        $this->queue->push('LaravelDoctrine\Tenancy\Jobs\HandleDomainEventJob', [
            'tenant_id' => $tenantId->value(),
            'event_id' => $eventLog->getId()->toString(),
            'event_type' => $eventType,
            'domain' => $domain
        ]);

        TenancyLogger::tenantResolved($tenantId, 'domain_event_jobs_dispatched', [
            'event_id' => $eventLog->getId()->toString(),
            'event_type' => $eventType,
            'domain' => $domain
        ]);
    }

    /**
     * Retry failed events.
     */
    public function retryFailedEvents(): void
    {
        $failedEvents = $this->eventTracker->getFailedEvents();
        
        foreach ($failedEvents as $eventLog) {
            $this->dispatchJobsForEvent($eventLog);
        }

        TenancyLogger::performanceMetric('failed_events_retry', 0, [
            'retry_count' => count($failedEvents)
        ]);
    }

    /**
     * Get tenant status and dispatch appropriate jobs.
     */
    public function processTenantStatus(TenantId $tenantId): void
    {
        $status = $this->eventTracker->getTenantStatus($tenantId);
        
        // If tenant is created but not migrated, start migration
        if ($status['is_created'] && !$status['is_migrated']) {
            $migrationEvent = EventFactory::createTenantMigrationStarted(
                $tenantId,
                'latest',
                ['triggered_by' => 'status_check']
            );
            
            $eventLog = $this->eventTracker->trackTenantEvent($migrationEvent);
            $this->dispatchJobsForEvent($eventLog);
        }

        // If there are failures, retry them
        if ($status['has_failures']) {
            $this->retryFailedEvents();
        }
    }
}
