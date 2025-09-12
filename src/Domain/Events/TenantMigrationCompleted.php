<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

/**
 * Tenant Migration Completed Event
 * 
 * Represents the successful completion of a tenant database migration.
 * 
 * @package LaravelDoctrine\Tenancy\Domain\Events
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class TenantMigrationCompleted extends TenantEvent
{
    public function __construct(
        UuidInterface $eventId,
        TenantId $tenantId,
        string $migrationVersion,
        int $migratedCount,
        array $metadata = []
    ) {
        $metadata['migration_version'] = $migrationVersion;
        $metadata['migrated_count'] = $migratedCount;
        parent::__construct(
            $eventId,
            $tenantId,
            'tenant_migration_completed',
            'completed',
            $metadata
        );
    }

    public function getMigrationVersion(): string
    {
        return $this->getMetadata()['migration_version'] ?? 'unknown';
    }

    public function getMigratedCount(): int
    {
        return $this->getMetadata()['migrated_count'] ?? 0;
    }
}
