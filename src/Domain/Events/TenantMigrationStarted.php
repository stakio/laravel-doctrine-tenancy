<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

/**
 * Tenant Migration Started Event
 * 
 * Represents the start of a tenant database migration.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\Events
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class TenantMigrationStarted extends TenantEvent
{
    public function __construct(
        UuidInterface $eventId,
        TenantId $tenantId,
        string $migrationVersion,
        array $metadata = []
    ) {
        $metadata['migration_version'] = $migrationVersion;
        parent::__construct(
            $eventId,
            $tenantId,
            'tenant_migration_started',
            'in_progress',
            $metadata
        );
    }

    public function getMigrationVersion(): string
    {
        return $this->getMetadata()['migration_version'] ?? 'unknown';
    }
}
