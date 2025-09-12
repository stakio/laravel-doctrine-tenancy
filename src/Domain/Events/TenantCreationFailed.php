<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

/**
 * Tenant Creation Failed Event
 * 
 * Represents a failed attempt to create a tenant.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\Events
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class TenantCreationFailed extends TenantEvent
{
    public function __construct(
        UuidInterface $eventId,
        TenantId $tenantId,
        string $reason,
        array $metadata = []
    ) {
        $metadata['failure_reason'] = $reason;
        parent::__construct(
            $eventId,
            $tenantId,
            'tenant_creation_failed',
            'failed',
            $metadata
        );
    }

    public function getFailureReason(): string
    {
        return $this->getMetadata()['failure_reason'] ?? 'Unknown error';
    }
}
