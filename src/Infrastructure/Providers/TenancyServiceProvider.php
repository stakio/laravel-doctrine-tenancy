<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Providers;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantContext;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\SmartEntityManager;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Middleware\ResolveTenantMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register tenant context
        $this->app->singleton(TenantContextInterface::class, TenantContext::class);

        // Bind the SmartEntityManager that automatically routes to central or tenant databases
        $this->app->singleton(EntityManagerInterface::class, function ($app) {
            return new SmartEntityManager(
                $app->make(TenantContextInterface::class)
            );
        });

        // Register tenant database manager
        $this->app->singleton(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager::class);

        // Register tenant connection wrapper
        $this->app->singleton(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantConnectionWrapper::class);
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../../config/tenancy.php' => config_path('tenancy.php'),
        ], 'tenancy-config');

        // Register middleware
        $this->app['router']->aliasMiddleware('resolve.tenant', ResolveTenantMiddleware::class);

        // Load configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../../config/tenancy.php',
            'tenancy'
        );
    }
}
