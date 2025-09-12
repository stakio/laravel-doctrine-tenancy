<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use Ramsey\Uuid\UuidInterface;

/**
 * Domain Created Event
 * 
 * Represents the successful creation of a domain for a tenant.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\Events
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class DomainCreated extends DomainEvent
{
    public function __construct(
        UuidInterface $eventId,
        TenantId $tenantId,
        Domain $domain,
        array $metadata = []
    ) {
        parent::__construct(
            $eventId,
            $tenantId,
            $domain,
            'domain_created',
            'completed',
            $metadata
        );
    }
}
