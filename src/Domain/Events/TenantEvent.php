<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

/**
 * Base Tenant Event
 * 
 * Abstract base class for all tenant-related events.
 * Provides common properties and structure for event tracking.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\Events
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
abstract class TenantEvent
{
    public function __construct(
        private UuidInterface $eventId,
        private TenantId $tenantId,
        private string $eventType,
        private string $status,
        private array $metadata = [],
        private ?\DateTimeImmutable $occurredAt = null
    ) {
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
    }

    public function getEventId(): UuidInterface
    {
        return $this->eventId;
    }

    public function getTenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function updateStatus(string $status): void
    {
        $this->status = $status;
    }
}
