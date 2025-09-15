<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Providers;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\ServiceProvider;
use LaravelDoctrine\Tenancy\Console\Commands\InstallTenancyCommand;
use LaravelDoctrine\Tenancy\Console\Commands\TenantDatabaseCommand;
use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Configuration\ConfigurationValidator;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Middleware\ResolveTenantMiddleware;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\SmartEntityManager;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantContext;

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
            __DIR__.'/../../../config/tenancy.php' => config_path('tenancy.php'),
        ], 'tenancy-config');

        // Publish migration stubs
        $this->publishes([
            __DIR__.'/../../../stubs/migrations/' => database_path('migrations'),
        ], 'tenancy-migrations-stub');

        // Load configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/tenancy.php',
            'tenancy'
        );

        // Validate configuration
        if (config('tenancy.enabled', true)) {
            ConfigurationValidator::validate();
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
