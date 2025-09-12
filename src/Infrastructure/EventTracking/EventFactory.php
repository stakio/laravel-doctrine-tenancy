<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\EventTracking;

use LaravelDoctrine\Tenancy\Domain\Events\TenantCreated;
use LaravelDoctrine\Tenancy\Domain\Events\TenantCreationFailed;
use LaravelDoctrine\Tenancy\Domain\Events\TenantMigrationStarted;
use LaravelDoctrine\Tenancy\Domain\Events\TenantMigrationCompleted;
use LaravelDoctrine\Tenancy\Domain\Events\TenantMigrationFailed;
use LaravelDoctrine\Tenancy\Domain\Events\DomainCreated;
use LaravelDoctrine\Tenancy\Domain\Events\DomainActivated;
use LaravelDoctrine\Tenancy\Domain\Events\DomainDeactivated;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use Ramsey\Uuid\Uuid;

/**
 * Event Factory
 * 
 * Factory for creating tenant and domain events with proper UUIDs and metadata.
 * 
 * @package LaravelDoctrine\Tenancy\Infrastructure\EventTracking
 * @author Laravel Doctrine Tenancy Team
 * @since 1.2.0
 */
class EventFactory
{
    /**
     * Create tenant created event.
     */
    public static function createTenantCreated(TenantId $tenantId, array $metadata = []): TenantCreated
    {
        return new TenantCreated(
            Uuid::uuid4(),
            $tenantId,
            $metadata
        );
    }

    /**
     * Create tenant creation failed event.
     */
    public static function createTenantCreationFailed(TenantId $tenantId, string $reason, array $metadata = []): TenantCreationFailed
    {
        return new TenantCreationFailed(
            Uuid::uuid4(),
            $tenantId,
            $reason,
            $metadata
        );
    }

    /**
     * Create tenant migration started event.
     */
    public static function createTenantMigrationStarted(TenantId $tenantId, string $migrationVersion, array $metadata = []): TenantMigrationStarted
    {
        return new TenantMigrationStarted(
            Uuid::uuid4(),
            $tenantId,
            $migrationVersion,
            $metadata
        );
    }

    /**
     * Create tenant migration completed event.
     */
    public static function createTenantMigrationCompleted(TenantId $tenantId, string $migrationVersion, int $migratedCount, array $metadata = []): TenantMigrationCompleted
    {
        return new TenantMigrationCompleted(
            Uuid::uuid4(),
            $tenantId,
            $migrationVersion,
            $migratedCount,
            $metadata
        );
    }

    /**
     * Create tenant migration failed event.
     */
    public static function createTenantMigrationFailed(TenantId $tenantId, string $migrationVersion, string $reason, array $metadata = []): TenantMigrationFailed
    {
        return new TenantMigrationFailed(
            Uuid::uuid4(),
            $tenantId,
            $migrationVersion,
            $reason,
            $metadata
        );
    }

    /**
     * Create domain created event.
     */
    public static function createDomainCreated(TenantId $tenantId, Domain $domain, array $metadata = []): DomainCreated
    {
        return new DomainCreated(
            Uuid::uuid4(),
            $tenantId,
            $domain,
            $metadata
        );
    }

    /**
     * Create domain activated event.
     */
    public static function createDomainActivated(TenantId $tenantId, Domain $domain, array $metadata = []): DomainActivated
    {
        return new DomainActivated(
            Uuid::uuid4(),
            $tenantId,
            $domain,
            $metadata
        );
    }

    /**
     * Create domain deactivated event.
     */
    public static function createDomainDeactivated(TenantId $tenantId, Domain $domain, array $metadata = []): DomainDeactivated
    {
        return new DomainDeactivated(
            Uuid::uuid4(),
            $tenantId,
            $domain,
            $metadata
        );
    }
}
