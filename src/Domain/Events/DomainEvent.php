<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use Ramsey\Uuid\UuidInterface;

/**
 * Base Domain Event
 * 
 * Abstract base class for all domain-related events.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\Events
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
abstract class DomainEvent
{
    public function __construct(
        private UuidInterface $eventId,
        private TenantId $tenantId,
        private Domain $domain,
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

    public function getDomain(): Domain
    {
        return $this->domain;
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
