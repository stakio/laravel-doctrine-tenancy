<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\EventTracking;

use LaravelDoctrine\Tenancy\Domain\EventTracking\TenantEventLog;
use LaravelDoctrine\Tenancy\Domain\Events\TenantEvent;
use LaravelDoctrine\Tenancy\Domain\Events\DomainEvent;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Event Tracker
 * 
 * Handles tracking and persistence of tenant and domain events.
 * Provides methods for logging events and querying event history.
 * 
 * @package LaravelDoctrine\Tenancy\Infrastructure\EventTracking
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class EventTracker
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Track a tenant event.
     */
    public function trackTenantEvent(TenantEvent $event): TenantEventLog
    {
        $eventLog = new TenantEventLog(
            $event->getEventId(),
            $event->getTenantId(),
            $event->getEventType(),
            $event->getStatus(),
            $event->getMetadata(),
            null,
            $event instanceof \LaravelDoctrine\Tenancy\Domain\Events\TenantCreationFailed ? 
                $event->getFailureReason() : null,
            $event->getOccurredAt()
        );

        $this->entityManager->persist($eventLog);
        $this->entityManager->flush();

        TenancyLogger::tenantResolved($event->getTenantId(), 'event_tracked', [
            'event_type' => $event->getEventType(),
            'status' => $event->getStatus(),
            'event_id' => $event->getEventId()->toString()
        ]);

        return $eventLog;
    }

    /**
     * Track a domain event.
     */
    public function trackDomainEvent(DomainEvent $event): TenantEventLog
    {
        $eventLog = new TenantEventLog(
            $event->getEventId(),
            $event->getTenantId(),
            $event->getEventType(),
            $event->getStatus(),
            $event->getMetadata(),
            $event->getDomain()->value(),
            null,
            $event->getOccurredAt()
        );

        $this->entityManager->persist($eventLog);
        $this->entityManager->flush();

        TenancyLogger::tenantResolved($event->getTenantId(), 'domain_event_tracked', [
            'event_type' => $event->getEventType(),
            'status' => $event->getStatus(),
            'domain' => $event->getDomain()->value(),
            'event_id' => $event->getEventId()->toString()
        ]);

        return $eventLog;
    }

    /**
     * Update event status.
     */
    public function updateEventStatus(UuidInterface $eventId, string $status): void
    {
        $eventLog = $this->entityManager->find(TenantEventLog::class, $eventId);
        
        if ($eventLog) {
            $eventLog->updateStatus($status);
            $this->entityManager->flush();
        }
    }

    /**
     * Add metadata to an event.
     */
    public function addEventMetadata(UuidInterface $eventId, string $key, mixed $value): void
    {
        $eventLog = $this->entityManager->find(TenantEventLog::class, $eventId);
        
        if ($eventLog) {
            $eventLog->addMetadata($key, $value);
            $this->entityManager->flush();
        }
    }

    /**
     * Mark event as failed with reason.
     */
    public function markEventAsFailed(UuidInterface $eventId, string $reason): void
    {
        $eventLog = $this->entityManager->find(TenantEventLog::class, $eventId);
        
        if ($eventLog) {
            $eventLog->updateStatus('failed');
            $eventLog->setFailureReason($reason);
            $this->entityManager->flush();
        }
    }

    /**
     * Get events for a tenant.
     */
    public function getTenantEvents(TenantId $tenantId, ?string $eventType = null, ?string $status = null): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
           ->from(TenantEventLog::class, 'e')
           ->where('e.tenantId = :tenantId')
           ->setParameter('tenantId', $tenantId->value())
           ->orderBy('e.occurredAt', 'DESC');

        if ($eventType) {
            $qb->andWhere('e.eventType = :eventType')
               ->setParameter('eventType', $eventType);
        }

        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get failed events that need retry.
     */
    public function getFailedEvents(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
           ->from(TenantEventLog::class, 'e')
           ->where('e.status = :status')
           ->setParameter('status', 'failed')
           ->orderBy('e.occurredAt', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get events by type and status.
     */
    public function getEventsByTypeAndStatus(string $eventType, string $status): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
           ->from(TenantEventLog::class, 'e')
           ->where('e.eventType = :eventType')
           ->andWhere('e.status = :status')
           ->setParameter('eventType', $eventType)
           ->setParameter('status', $status)
           ->orderBy('e.occurredAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get tenant status based on events.
     */
    public function getTenantStatus(TenantId $tenantId): array
    {
        $events = $this->getTenantEvents($tenantId);
        
        $status = [
            'tenant_id' => $tenantId->value(),
            'is_created' => false,
            'is_migrated' => false,
            'has_failures' => false,
            'last_event' => null,
            'creation_events' => [],
            'migration_events' => [],
            'domain_events' => []
        ];

        foreach ($events as $event) {
            $status['last_event'] = $event->getOccurredAt();
            
            switch ($event->getEventType()) {
                case 'tenant_created':
                    $status['is_created'] = true;
                    $status['creation_events'][] = $event;
                    break;
                case 'tenant_creation_failed':
                    $status['has_failures'] = true;
                    $status['creation_events'][] = $event;
                    break;
                case 'tenant_migration_started':
                    $status['migration_events'][] = $event;
                    break;
                case 'tenant_migration_completed':
                    $status['is_migrated'] = true;
                    $status['migration_events'][] = $event;
                    break;
                case 'tenant_migration_failed':
                    $status['has_failures'] = true;
                    $status['migration_events'][] = $event;
                    break;
                case 'domain_created':
                case 'domain_activated':
                case 'domain_deactivated':
                    $status['domain_events'][] = $event;
                    break;
            }
        }

        return $status;
    }
}
