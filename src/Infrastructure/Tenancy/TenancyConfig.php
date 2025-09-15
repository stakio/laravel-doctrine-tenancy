<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy;

/**
 * @internal This class is not part of the public API and may change without notice.
 */
class TenancyConfig
{
    /**
     * Get tenant database prefix
     */
    public static function getDatabasePrefix(): string
    {
        return config('tenancy.database.prefix', 'tenant_');
    }

    public static function getDatabaseNaming(): array
    {
        return config('tenancy.database.naming', [
            'strategy' => 'prefix',
            'separator' => '_',
        ]);
    }

    /**
     * Get central database connection name
     */
    public static function getCentralConnection(): string
    {
        return config('tenancy.database.central_connection') ?? config('database.default', 'mysql');
    }

    /**
     * Get entity routing configuration
     */
    public static function getEntityRouting(): array
    {
        $centralEntities = config('tenancy.entity_routing.central', []);
        $tenantEntity = self::getTenantEntityClass();
        $domainEntity = self::getDomainEntityClass();

        // Automatically add the tenant entity to central entities if not already present
        if (! in_array($tenantEntity, $centralEntities)) {
            $centralEntities[] = $tenantEntity;
        }

        // Automatically add the domain entity to central entities if not already present
        if (! in_array($domainEntity, $centralEntities)) {
            $centralEntities[] = $domainEntity;
        }

        return [
            'central' => $centralEntities,
            'tenant' => config('tenancy.entity_routing.tenant', []),
        ];
    }

    /**
     * Get the configured tenant entity class
     */
    public static function getTenantEntityClass(): string
    {
        return config('tenancy.tenant_entity') ?? \LaravelDoctrine\Tenancy\Domain\Tenant::class;
    }

    /**
     * Get the configured domain entity class
     */
    public static function getDomainEntityClass(): string
    {
        return config('tenancy.domain_entity') ?? \LaravelDoctrine\Tenancy\Domain\DomainEntity::class;
    }

    /**
     * Get tenant resolution header name
     */
    public static function getResolutionHeader(): string
    {
        return config('tenancy.identification.header_name', 'X-Tenant-ID');
    }

    /**
     * Get original database default connection
     */
    public static function getOriginalDatabaseDefault(): string
    {
        return config('tenancy.original_database_default', 'mysql');
    }

    /**
     * Get default tenant seeder class
     */
    public static function getDefaultTenantSeeder(): string
    {
        return config('tenancy.seeders.default_tenant_seeder', 'TenantDatabaseSeeder');
    }

    /**
     * Get tenant migrations path
     */
    public static function getTenantMigrationsPath(): string
    {
        return config('tenancy.migrations.tenant_path', 'database/migrations/tenant');
    }

    /**
     * Get tenant seeders path
     */
    public static function getTenantSeedersPath(): string
    {
        return config('tenancy.seeders.tenant_path', 'database/seeders/tenant');
    }

    /**
     * Check if tenancy is enabled
     */
    public static function isEnabled(): bool
    {
        return config('tenancy.enabled', true);
    }

    /**
     * Get tenant cache prefix
     */
    public static function getCachePrefix(): string
    {
        return config('tenancy.cache.prefix', 'tenant_');
    }

    /**
     * Get tenant cache TTL
     */
    public static function getCacheTtl(): int
    {
        return config('tenancy.cache.ttl', 3600);
    }

    /**
     * Get excluded subdomains for tenant resolution
     */
    public static function getExcludedSubdomains(): array
    {
        return config('tenancy.identification.excluded_subdomains', ['www', 'api', 'admin']);
    }

    /**
     * Get optional logging channel name for tenancy logs
     */
    public static function getLogChannel(): ?string
    {
        $channel = config('tenancy.logging.channel');

        return $channel ? (string) $channel : null;
    }
}
