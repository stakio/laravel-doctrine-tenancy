<?php

namespace LaravelDoctrine\Tenancy\Domain\EventTracking;

use Doctrine\ORM\Mapping as ORM;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

/**
 * Tenant Event Log Entity
 * 
 * Tracks all tenant and domain-related events for audit, monitoring,
 * and job dispatching purposes.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\EventTracking
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant_event_logs')]
#[ORM\Index(columns: ['tenant_id', 'event_type'])]
#[ORM\Index(columns: ['status', 'occurred_at'])]
#[ORM\Index(columns: ['event_type', 'status'])]
class TenantEventLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'tenant_id', type: 'uuid')]
    private UuidInterface $tenantId;

    #[ORM\Column(name: 'event_type', type: 'string', length: 100)]
    private string $eventType;

    #[ORM\Column(name: 'status', type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(name: 'metadata', type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'domain', type: 'string', length: 255, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(name: 'failure_reason', type: 'string', length: 100, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        UuidInterface $id,
        TenantId $tenantId,
        string $eventType,
        string $status,
        ?array $metadata = null,
        ?string $domain = null,
        ?string $failureReason = null,
        ?\DateTimeImmutable $occurredAt = null
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId->value();
        $this->eventType = $eventType;
        $this->status = $status;
        $this->metadata = $metadata;
        $this->domain = $domain;
        $this->failureReason = $failureReason;
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getTenantId(): TenantId
    {
        return new TenantId($this->tenantId);
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addMetadata(string $key, mixed $value): void
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setFailureReason(string $reason): void
    {
        $this->failureReason = $reason;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
