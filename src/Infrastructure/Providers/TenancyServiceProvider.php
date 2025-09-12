<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Providers;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantContext;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\SmartEntityManager;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Middleware\ResolveTenantMiddleware;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Configuration\ConfigurationValidator;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Caching\TenantCache;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventTracker;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventDispatcher;
use LaravelDoctrine\Tenancy\Console\Commands\TenantDatabaseCommand;
use LaravelDoctrine\Tenancy\Console\Commands\InstallTenancyCommand;
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

        // Register tenant cache
        $this->app->singleton(TenantCache::class);

        // Register event tracking services
        $this->app->singleton(EventTracker::class, function ($app) {
            return new EventTracker($app->make(EntityManagerInterface::class));
        });

        $this->app->singleton(EventDispatcher::class, function ($app) {
            return new EventDispatcher(
                $app->make(EventTracker::class),
                $app->make('queue')->connection()
            );
        });
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../../config/tenancy.php' => config_path('tenancy.php'),
        ], 'tenancy-config');

        // Load configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../../config/tenancy.php',
            'tenancy'
        );

        // Validate configuration
        if (config('tenancy.enabled', true)) {
            ConfigurationValidator::validateWithLogging();
        }

        // Register middleware
        $this->app['router']->aliasMiddleware('resolve.tenant', ResolveTenantMiddleware::class);

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantDatabaseCommand::class,
                InstallTenancyCommand::class,
            ]);
        }
    }
}
