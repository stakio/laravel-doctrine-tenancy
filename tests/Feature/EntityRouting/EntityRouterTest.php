<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\EntityRouting;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\EntityRouting\EntityRouter;
use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use Doctrine\ORM\EntityManager;
use Mockery;

class EntityRouterTest extends TestCase
{
    private EntityRouter $entityRouter;
    private TenantContextInterface $tenantContext;
    private EntityManager $centralEntityManager;
    private EntityManager $tenantEntityManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenantContext = Mockery::mock(TenantContextInterface::class);
        $this->centralEntityManager = Mockery::mock(EntityManager::class);
        $this->tenantEntityManager = Mockery::mock(EntityManager::class);

        // Mock the TenancyConfig::getEntityRouting() method
        $this->app['config']->set('tenancy.entity_routing', [
            'central' => [
                'LaravelDoctrine\Tenancy\Domain\Tenant',
                'LaravelDoctrine\Tenancy\Domain\DomainEntity',
            ],
            'tenant' => [
                'App\Entities\Patient',
                'App\Entities\Appointment',
            ],
        ]);

        $this->entityRouter = new EntityRouter($this->tenantContext);
    }

    public function test_routes_central_entities_to_central_entity_manager()
    {
        $entityClass = 'LaravelDoctrine\Tenancy\Domain\Tenant';

        $result = $this->entityRouter->getEntityManagerForClass(
            $entityClass,
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        $this->assertSame($this->centralEntityManager, $result);
    }

    public function test_routes_tenant_entities_to_tenant_entity_manager_when_tenant_context_exists()
    {
        $entityClass = 'App\Entities\Patient';
        $tenantId = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $this->tenantContext
            ->shouldReceive('hasCurrentTenant')
            ->once()
            ->andReturn(true);

        $result = $this->entityRouter->getEntityManagerForClass(
            $entityClass,
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        $this->assertSame($this->tenantEntityManager, $result);
    }

    public function test_routes_tenant_entities_to_central_entity_manager_when_no_tenant_context()
    {
        $entityClass = 'App\Entities\Patient';

        $this->tenantContext
            ->shouldReceive('hasCurrentTenant')
            ->once()
            ->andReturn(false);

        $this->expectException(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenancyConfigurationException::class);
        $this->expectExceptionMessage('No tenant context available for tenant entity: App\Entities\Patient');

        $this->entityRouter->getEntityManagerForClass(
            $entityClass,
            $this->centralEntityManager,
            $this->tenantEntityManager
        );
    }

    public function test_routes_unknown_entities_to_central_entity_manager()
    {
        $entityClass = 'App\Entities\UnknownEntity';

        $result = $this->entityRouter->getEntityManagerForClass(
            $entityClass,
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        $this->assertSame($this->centralEntityManager, $result);
    }

    public function test_handles_empty_entity_routing_configuration()
    {
        // Set empty configuration
        $this->app['config']->set('tenancy.entity_routing', [
            'central' => [],
            'tenant' => [],
        ]);

        $entityRouter = new EntityRouter($this->tenantContext);

        $result = $entityRouter->getEntityManagerForClass(
            'AnyEntity',
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        $this->assertSame($this->centralEntityManager, $result);
    }

    public function test_handles_missing_entity_routing_configuration()
    {
        // Remove entity routing configuration
        $this->app['config']->set('tenancy.entity_routing', null);

        $entityRouter = new EntityRouter($this->tenantContext);

        $result = $entityRouter->getEntityManagerForClass(
            'AnyEntity',
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        $this->assertSame($this->centralEntityManager, $result);
    }

    public function test_case_sensitive_entity_class_matching()
    {
        $entityClass = 'LaravelDoctrine\Tenancy\Domain\TENANT'; // Different case

        $result = $this->entityRouter->getEntityManagerForClass(
            $entityClass,
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        // Should not match due to case sensitivity
        $this->assertSame($this->centralEntityManager, $result);
    }

    public function test_handles_namespace_variations()
    {
        // Test with leading backslash
        $entityClass = '\LaravelDoctrine\Tenancy\Domain\Tenant';

        $result = $this->entityRouter->getEntityManagerForClass(
            $entityClass,
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        // Should not match due to leading backslash
        $this->assertSame($this->centralEntityManager, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
