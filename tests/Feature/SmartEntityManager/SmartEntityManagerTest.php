<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\SmartEntityManager;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\SmartEntityManager;
use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mockery;

class SmartEntityManagerTest extends TestCase
{
    private SmartEntityManager $smartEntityManager;
    private TenantContextInterface $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenantContext = Mockery::mock(TenantContextInterface::class);
        $this->smartEntityManager = new SmartEntityManager($this->tenantContext);
    }

    public function test_implements_entity_manager_interface()
    {
        $this->assertInstanceOf(EntityManagerInterface::class, $this->smartEntityManager);
    }

    public function test_has_required_methods()
    {
        $this->assertTrue(method_exists($this->smartEntityManager, 'getRepository'));
        $this->assertTrue(method_exists($this->smartEntityManager, 'persist'));
        $this->assertTrue(method_exists($this->smartEntityManager, 'remove'));
        $this->assertTrue(method_exists($this->smartEntityManager, 'flush'));
        $this->assertTrue(method_exists($this->smartEntityManager, 'find'));
        $this->assertTrue(method_exists($this->smartEntityManager, 'getClassMetadata'));
    }

    public function test_constructor_accepts_tenant_context()
    {
        $tenantContext = Mockery::mock(TenantContextInterface::class);
        $smartEntityManager = new SmartEntityManager($tenantContext);
        
        $this->assertInstanceOf(SmartEntityManager::class, $smartEntityManager);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}