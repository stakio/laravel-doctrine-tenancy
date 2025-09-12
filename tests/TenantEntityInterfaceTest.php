<?php

namespace LaravelDoctrine\Tenancy\Tests;

use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Domain\Tenant;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantName;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use Ramsey\Uuid\Uuid;

class TenantEntityInterfaceTest extends TestCase
{
    // Configuration is now handled by the base TestCase class

    public function test_default_tenant_implements_interface()
    {
        $tenant = new Tenant(
            Uuid::uuid4(),
            new TenantName('Test Tenant'),
            new Domain('test.example.com')
        );

        $this->assertInstanceOf(TenantEntityInterface::class, $tenant);
    }

    public function test_tenant_interface_methods()
    {
        $id = Uuid::uuid4();
        $name = new TenantName('Test Tenant');
        $domain = new Domain('test.example.com');
        
        $tenant = new Tenant($id, $name, $domain);

        // Test core methods
        $this->assertEquals($id, $tenant->getId());
        $this->assertEquals($id, $tenant->id());
        $this->assertEquals($name, $tenant->name());
        $this->assertEquals($domain, $tenant->domain());

        // Test status methods
        $this->assertTrue($tenant->isActive());
        $this->assertNull($tenant->getDeactivatedAt());

        // Test deactivation
        $tenant->deactivate();
        $this->assertFalse($tenant->isActive());
        $this->assertNotNull($tenant->getDeactivatedAt());

        // Test reactivation
        $tenant->activate();
        $this->assertTrue($tenant->isActive());
        $this->assertNull($tenant->getDeactivatedAt());

        // Test timestamps
        $this->assertInstanceOf(\DateTimeImmutable::class, $tenant->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $tenant->getUpdatedAt());

        // Test identifier conversion
        $identifier = $tenant->toIdentifier();
        $this->assertInstanceOf(TenantIdentifier::class, $identifier);
        $this->assertEquals($id, $identifier->value());
    }

    public function test_tenant_update_methods()
    {
        $tenant = new Tenant(
            Uuid::uuid4(),
            new TenantName('Original Name'),
            new Domain('original.example.com')
        );

        $originalUpdatedAt = $tenant->getUpdatedAt();

        // Test name update
        $newName = new TenantName('Updated Name');
        $tenant->updateName($newName);
        $this->assertEquals($newName, $tenant->name());
        $this->assertGreaterThan($originalUpdatedAt, $tenant->getUpdatedAt());

        // Test domain update
        $newDomain = new Domain('updated.example.com');
        $tenant->updateDomain($newDomain);
        $this->assertEquals($newDomain, $tenant->domain());
    }

    public function test_tenancy_config_returns_tenant_entity_class()
    {
        $tenantEntityClass = TenancyConfig::getTenantEntityClass();
        
        $this->assertEquals(Tenant::class, $tenantEntityClass);
    }

    public function test_entity_routing_includes_tenant_entity()
    {
        $routing = TenancyConfig::getEntityRouting();
        
        $this->assertArrayHasKey('central', $routing);
        $this->assertArrayHasKey('tenant', $routing);
        $this->assertContains(Tenant::class, $routing['central']);
    }

    public function test_custom_tenant_entity_configuration()
    {
        // Test with a custom tenant entity class
        $this->app['config']->set('tenancy.tenant_entity', 'App\Custom\Tenant');
        
        $tenantEntityClass = TenancyConfig::getTenantEntityClass();
        $this->assertEquals('App\Custom\Tenant', $tenantEntityClass);
        
        $routing = TenancyConfig::getEntityRouting();
        $this->assertContains('App\Custom\Tenant', $routing['central']);
    }

    public function test_tenant_identifier_equality()
    {
        $id = Uuid::uuid4();
        $tenant = new Tenant(
            $id,
            new TenantName('Test Tenant'),
            new Domain('test.example.com')
        );

        $identifier1 = $tenant->toIdentifier();
        $identifier2 = new TenantId($id);

        $this->assertTrue($identifier1->equals($identifier2));
        $this->assertEquals($id->toString(), $identifier1->__toString());
    }

    public function test_tenant_entity_works_with_repository_interface()
    {
        // This test verifies that the TenantEntityInterface works
        // with the TenantRepositoryInterface type hints
        $tenant = new Tenant(
            Uuid::uuid4(),
            new TenantName('Test Tenant'),
            new Domain('test.example.com')
        );

        // Mock repository that accepts TenantEntityInterface
        $repository = new class {
            public function save(TenantEntityInterface $tenant): void
            {
                // This should work without issues
            }
        };

        // This should not throw any type errors
        $repository->save($tenant);
        $this->assertTrue(true); // If we get here, the type hint worked
    }
}
