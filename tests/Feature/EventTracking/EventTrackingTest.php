<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\EventTracking;

use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventTracker;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventFactory;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventDispatcher;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use PHPUnit\Framework\Attributes\Test;

class EventTrackingTest extends TestCase
{
    use TenancyTestHelpers;

    private EventTracker $eventTracker;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        $this->eventTracker = app(EventTracker::class);
        $this->eventDispatcher = app(EventDispatcher::class);
    }

    #[Test]
    public function it_can_track_tenant_created_event()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $event = EventFactory::createTenantCreated($tenantId, ['source' => 'test']);

        $eventLog = $this->eventTracker->trackTenantEvent($event);

        $this->assertNotNull($eventLog);
        $this->assertEquals($tenantId->value(), $eventLog->getTenantId()->value());
        $this->assertEquals('tenant_created', $eventLog->getEventType());
        $this->assertEquals('completed', $eventLog->getStatus());
        $this->assertEquals(['source' => 'test'], $eventLog->getMetadata());
    }

    #[Test]
    public function it_can_track_tenant_creation_failed_event()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $reason = 'Database connection failed';
        $event = EventFactory::createTenantCreationFailed($tenantId, $reason, ['error_code' => 'DB001']);

        $eventLog = $this->eventTracker->trackTenantEvent($event);

        $this->assertNotNull($eventLog);
        $this->assertEquals($tenantId->value(), $eventLog->getTenantId()->value());
        $this->assertEquals('tenant_creation_failed', $eventLog->getEventType());
        $this->assertEquals('failed', $eventLog->getStatus());
        $this->assertEquals($reason, $eventLog->getFailureReason());
        $expectedMetadata = ['error_code' => 'DB001', 'failure_reason' => 'Database connection failed'];
        $this->assertEquals($expectedMetadata, $eventLog->getMetadata());
    }

    #[Test]
    public function it_can_track_migration_events()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        
        // Track migration started
        $startedEvent = EventFactory::createTenantMigrationStarted($tenantId, '2024_01_01_000001', ['migration_count' => 5]);
        $startedLog = $this->eventTracker->trackTenantEvent($startedEvent);

        $this->assertEquals('tenant_migration_started', $startedLog->getEventType());
        $this->assertEquals('in_progress', $startedLog->getStatus());

        // Track migration completed
        $completedEvent = EventFactory::createTenantMigrationCompleted($tenantId, '2024_01_01_000001', 5, ['duration' => 120]);
        $completedLog = $this->eventTracker->trackTenantEvent($completedEvent);

        $this->assertEquals('tenant_migration_completed', $completedLog->getEventType());
        $this->assertEquals('completed', $completedLog->getStatus());
    }

    #[Test]
    public function it_can_track_domain_events()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $domain = new Domain('example.com');
        
        // Track domain created
        $createdEvent = EventFactory::createDomainCreated($tenantId, $domain, ['is_primary' => true]);
        $createdLog = $this->eventTracker->trackDomainEvent($createdEvent);

        $this->assertEquals('domain_created', $createdLog->getEventType());
        $this->assertEquals('completed', $createdLog->getStatus());
        $this->assertEquals('example.com', $createdLog->getDomain());

        // Track domain activated
        $activatedEvent = EventFactory::createDomainActivated($tenantId, $domain);
        $activatedLog = $this->eventTracker->trackDomainEvent($activatedEvent);

        $this->assertEquals('domain_activated', $activatedLog->getEventType());
        $this->assertEquals('completed', $activatedLog->getStatus());
    }

    #[Test]
    public function it_can_update_event_status()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $event = EventFactory::createTenantMigrationStarted($tenantId, '2024_01_01_000001');
        $eventLog = $this->eventTracker->trackTenantEvent($event);

        $this->assertEquals('in_progress', $eventLog->getStatus());

        // Update status to completed
        $this->eventTracker->updateEventStatus(\Ramsey\Uuid\Uuid::fromString($eventLog->getId()->toString()), 'completed');
        
        $updatedLog = $this->entityManager->find(\LaravelDoctrine\Tenancy\Domain\EventTracking\TenantEventLog::class, $eventLog->getId());
        $this->assertEquals('completed', $updatedLog->getStatus());
    }

    #[Test]
    public function it_can_add_metadata_to_event()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $event = EventFactory::createTenantCreated($tenantId);
        $eventLog = $this->eventTracker->trackTenantEvent($event);

        $this->eventTracker->addEventMetadata(\Ramsey\Uuid\Uuid::fromString($eventLog->getId()->toString()), 'processing_time', 150);
        $this->eventTracker->addEventMetadata(\Ramsey\Uuid\Uuid::fromString($eventLog->getId()->toString()), 'worker_id', 'worker-123');

        $updatedLog = $this->entityManager->find(\LaravelDoctrine\Tenancy\Domain\EventTracking\TenantEventLog::class, $eventLog->getId());
        $this->assertEquals(150, $updatedLog->getMetadata()['processing_time']);
        $this->assertEquals('worker-123', $updatedLog->getMetadata()['worker_id']);
    }

    #[Test]
    public function it_can_mark_event_as_failed()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $event = EventFactory::createTenantMigrationStarted($tenantId, '2024_01_01_000001');
        $eventLog = $this->eventTracker->trackTenantEvent($event);

        $this->eventTracker->markEventAsFailed(\Ramsey\Uuid\Uuid::fromString($eventLog->getId()->toString()), 'Migration timeout after 300 seconds');

        $updatedLog = $this->entityManager->find(\LaravelDoctrine\Tenancy\Domain\EventTracking\TenantEventLog::class, $eventLog->getId());
        $this->assertEquals('failed', $updatedLog->getStatus());
        $this->assertEquals('Migration timeout after 300 seconds', $updatedLog->getFailureReason());
    }

    #[Test]
    public function it_can_get_tenant_events()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        
        // Create multiple events
        $createdEvent = EventFactory::createTenantCreated($tenantId);
        $this->eventTracker->trackTenantEvent($createdEvent);
        
        $migrationEvent = EventFactory::createTenantMigrationStarted($tenantId, '2024_01_01_000001');
        $this->eventTracker->trackTenantEvent($migrationEvent);

        // Get all events for tenant
        $events = $this->eventTracker->getTenantEvents($tenantId);
        $this->assertCount(2, $events);

        // Get only creation events
        $creationEvents = $this->eventTracker->getTenantEvents($tenantId, 'tenant_created');
        $this->assertCount(1, $creationEvents);
        $this->assertEquals('tenant_created', $creationEvents[0]->getEventType());
    }

    #[Test]
    public function it_can_get_failed_events()
    {
        $tenantId1 = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $tenantId2 = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        
        // Create successful event
        $successEvent = EventFactory::createTenantCreated($tenantId1);
        $this->eventTracker->trackTenantEvent($successEvent);
        
        // Create failed events
        $failedEvent1 = EventFactory::createTenantCreationFailed($tenantId1, 'DB Error');
        $failedLog1 = $this->eventTracker->trackTenantEvent($failedEvent1);
        
        $failedEvent2 = EventFactory::createTenantMigrationFailed($tenantId2, '2024_01_01_000001', 'Timeout');
        $failedLog2 = $this->eventTracker->trackTenantEvent($failedEvent2);

        $failedEvents = $this->eventTracker->getFailedEvents();
        $this->assertCount(2, $failedEvents);
        
        $eventTypes = array_map(fn($event) => $event->getEventType(), $failedEvents);
        $this->assertContains('tenant_creation_failed', $eventTypes);
        $this->assertContains('tenant_migration_failed', $eventTypes);
    }

    #[Test]
    public function it_can_get_tenant_status()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        
        // Create tenant
        $createdEvent = EventFactory::createTenantCreated($tenantId);
        $this->eventTracker->trackTenantEvent($createdEvent);
        
        // Start migration
        $migrationEvent = EventFactory::createTenantMigrationStarted($tenantId, '2024_01_01_000001');
        $this->eventTracker->trackTenantEvent($migrationEvent);
        
        // Complete migration
        $completedEvent = EventFactory::createTenantMigrationCompleted($tenantId, '2024_01_01_000001', 5);
        $this->eventTracker->trackTenantEvent($completedEvent);
        
        // Add domain
        $domain = new Domain('example.com');
        $domainEvent = EventFactory::createDomainCreated($tenantId, $domain);
        $this->eventTracker->trackDomainEvent($domainEvent);

        $status = $this->eventTracker->getTenantStatus($tenantId);
        
        $this->assertTrue($status['is_created']);
        $this->assertTrue($status['is_migrated']);
        $this->assertFalse($status['has_failures']);
        $this->assertCount(1, $status['creation_events']);
        $this->assertCount(2, $status['migration_events']); // started + completed
        $this->assertCount(1, $status['domain_events']);
    }

    #[Test]
    public function it_can_get_events_by_type_and_status()
    {
        $tenantId1 = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $tenantId2 = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        
        // Create multiple events of same type
        $event1 = EventFactory::createTenantCreated($tenantId1);
        $this->eventTracker->trackTenantEvent($event1);
        
        $event2 = EventFactory::createTenantCreated($tenantId2);
        $this->eventTracker->trackTenantEvent($event2);
        
        // Create failed event
        $failedEvent = EventFactory::createTenantCreationFailed($tenantId1, 'Error');
        $this->eventTracker->trackTenantEvent($failedEvent);

        $completedEvents = $this->eventTracker->getEventsByTypeAndStatus('tenant_created', 'completed');
        $this->assertCount(2, $completedEvents);
        
        $failedEvents = $this->eventTracker->getEventsByTypeAndStatus('tenant_creation_failed', 'failed');
        $this->assertCount(1, $failedEvents);
    }

    #[Test]
    public function it_can_dispatch_jobs_for_events()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        $event = EventFactory::createTenantCreated($tenantId);
        $eventLog = $this->eventTracker->trackTenantEvent($event);

        // Mock the queue to verify jobs are dispatched
        $queue = $this->createMock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->expects($this->exactly(2))
              ->method('push')
              ->with($this->isType('string'), $this->isType('array'));

        $dispatcher = new EventDispatcher($this->eventTracker, $queue);
        $dispatcher->dispatchJobsForEvent($eventLog);
    }

    #[Test]
    public function it_can_retry_failed_events()
    {
        $tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
        
        // Create failed event
        $failedEvent = EventFactory::createTenantCreationFailed($tenantId, 'DB Error');
        $this->eventTracker->trackTenantEvent($failedEvent);

        // Mock the queue
        $queue = $this->createMock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->expects($this->exactly(2))
              ->method('push');

        $dispatcher = new EventDispatcher($this->eventTracker, $queue);
        $dispatcher->retryFailedEvents();
    }
}
