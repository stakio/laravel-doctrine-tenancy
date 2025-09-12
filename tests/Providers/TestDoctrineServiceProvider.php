<?php

namespace LaravelDoctrine\Tenancy\Tests\Providers;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Illuminate\Support\ServiceProvider;
use Ramsey\Uuid\Doctrine\UuidType;

class TestDoctrineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('doctrine.central.entity_manager', function ($app) {
            static $entityManager = null;
            if ($entityManager === null) {
                $entityManager = $this->createEntityManager();
            }
            return $entityManager;
        });

        $this->app->singleton('doctrine.tenant.entity_manager', function ($app) {
            static $entityManager = null;
            if ($entityManager === null) {
                $entityManager = $this->createEntityManager();
            }
            return $entityManager;
        });

        $this->app->singleton(\Doctrine\ORM\EntityManagerInterface::class, function ($app) {
            return $app->make('doctrine.central.entity_manager');
        });
    }

    public function boot(): void
    {
        // Set up Doctrine configuration
        $this->app->make('doctrine.central.entity_manager');
    }

    private function createEntityManager(): EntityManager
    {
        // Register UUID type
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
        
        // Create Doctrine configuration
        $config = new Configuration();
        
        // Set up metadata driver
        $driver = new AttributeDriver([
            realpath(__DIR__ . '/../src/Domain'),
        ]);
        $config->setMetadataDriverImpl($driver);
        
        // Set up proxy configuration
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Proxies');
        $config->setAutoGenerateProxyClasses(true);
        
        // Skip cache setup for testing - use default in-memory behavior
        
        // Create connection
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => ':memory:',
        ]);
        
        // Create entity manager
        $entityManager = new EntityManager($connection, $config);
        
        // Create schema
        $this->createSchema($entityManager);
        
        return $entityManager;
    }

    private function createSchema(EntityManager $entityManager): void
    {
        $schemaTool = new SchemaTool($entityManager);
        $classes = [
            $entityManager->getClassMetadata(\LaravelDoctrine\Tenancy\Domain\Tenant::class),
            $entityManager->getClassMetadata(\LaravelDoctrine\Tenancy\Domain\DomainEntity::class),
            $entityManager->getClassMetadata(\LaravelDoctrine\Tenancy\Domain\EventTracking\TenantEventLog::class),
        ];
        
        // Drop existing schema first to avoid conflicts
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }
}
