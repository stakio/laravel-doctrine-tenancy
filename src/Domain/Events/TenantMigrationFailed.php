<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

/**
 * Tenant Migration Failed Event
 * 
 * Represents a failed tenant database migration.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\Events
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class TenantMigrationFailed extends TenantEvent
{
    public function __construct(
        UuidInterface $eventId,
        TenantId $tenantId,
        string $migrationVersion,
        string $reason,
        array $metadata = []
    ) {
        $metadata['migration_version'] = $migrationVersion;
        $metadata['failure_reason'] = $reason;
        parent::__construct(
            $eventId,
            $tenantId,
            'tenant_migration_failed',
            'failed',
            $metadata
        );
    }

    public function getMigrationVersion(): string
    {
        return $this->getMetadata()['migration_version'] ?? 'unknown';
    }

    public function getFailureReason(): string
    {
        return $this->getMetadata()['failure_reason'] ?? 'Unknown error';
    }
}
