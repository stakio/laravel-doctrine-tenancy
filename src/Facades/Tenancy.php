<?php

namespace LaravelDoctrine\Tenancy\Facades;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TenantIdentifier|null getCurrentTenant()
 * @method static void setCurrentTenant(TenantIdentifier $tenant)
 * @method static void clearCurrentTenant()
 * @method static bool hasCurrentTenant()
 * @method static bool createTenantDatabase(TenantIdentifier $tenant)
 * @method static bool deleteTenantDatabase(TenantIdentifier $tenant)
 * @method static bool migrateTenantDatabase(TenantIdentifier $tenant)
 * @method static bool seedTenantDatabase(TenantIdentifier $tenant)
 * @method static array getTenantMigrationStatus(TenantIdentifier $tenant)
 */
class Tenancy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantContextInterface::class;
    }

    public static function database(): TenantDatabaseManager
    {
        return app(TenantDatabaseManager::class);
    }
}
