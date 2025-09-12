<?php

namespace LaravelDoctrine\Tenancy\Tests;

use LaravelDoctrine\Tenancy\Infrastructure\Providers\TenancyServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up Doctrine configuration
        $this->setUpDoctrine();
    }

    protected function getPackageProviders($app)
    {
        return [
            \LaravelDoctrine\Tenancy\Tests\Providers\TestDoctrineServiceProvider::class,
            TenancyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Set up test database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up Doctrine configuration
        $app['config']->set('doctrine', [
            'default' => 'testing',
            'connections' => [
                'testing' => [
                    'driver' => 'pdo_sqlite',
                    'path' => ':memory:',
                ],
            ],
            'managers' => [
                'testing' => [
                    'connection' => 'testing',
                    'mappings' => [
                        'LaravelDoctrine\\Tenancy\\Domain' => [
                            'type' => 'attribute',
                            'dir' => realpath(__DIR__ . '/../src/Domain'),
                            'prefix' => 'LaravelDoctrine\\Tenancy\\Domain',
                        ],
                    ],
                ],
            ],
        ]);

        // Set up tenancy configuration
        $app['config']->set('tenancy.enabled', true);
        $app['config']->set('tenancy.database.prefix', 'tenant_');
        $app['config']->set('tenancy.cache.prefix', 'tenant_');
        $app['config']->set('tenancy.cache.ttl', 3600);
        $app['config']->set('tenancy.resolution.header', 'X-Tenant-ID');
        $app['config']->set('tenancy.migrations.tenant_path', 'database/migrations/tenant');
        $app['config']->set('tenancy.seeders.tenant_path', 'database/seeders/tenant');
        $app['config']->set('tenancy.seeders.default_tenant_seeder', 'TenantDatabaseSeeder');
        $app['config']->set('tenancy.database.auto_create', false);
        $app['config']->set('tenancy.original_database_default', 'mysql');
    }

    protected function setUpDoctrine()
    {
        // This method can be overridden by specific test classes
        // to set up additional Doctrine configuration if needed
    }

    protected function createEntityManager()
    {
        return $this->app->make(\Doctrine\ORM\EntityManagerInterface::class);
    }

    protected function createTenantContext()
    {
        return $this->app->make(\LaravelDoctrine\Tenancy\Contracts\TenantContextInterface::class);
    }

    protected function createMiddleware()
    {
        return $this->app->make(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Middleware\ResolveTenantMiddleware::class);
    }
}
