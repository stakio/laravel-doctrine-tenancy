<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\Facades;

use LaravelDoctrine\Tenancy\Facades\Tenancy;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantContext;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use Ramsey\Uuid\Uuid;
use PHPUnit\Framework\MockObject\MockObject;

class TenancyFacadeTest extends TestCase
{
    private TenantContext&MockObject $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->app->instance(TenantContext::class, $this->tenantContext);
    }

    public function test_set_tenant_sets_current_tenant()
    {
        $tenantId = new TenantId(Uuid::uuid4());

        $this->tenantContext
            ->expects($this->once())
            ->method('setCurrentTenant')
            ->with($tenantId);

        Tenancy::setCurrentTenant($tenantId);
    }

    public function test_get_tenant_returns_current_tenant()
    {
        $tenantId = new TenantId(Uuid::uuid4());

        $this->tenantContext
            ->expects($this->once())
            ->method('getCurrentTenant')
            ->willReturn($tenantId);

        $result = Tenancy::getCurrentTenant();

        $this->assertSame($tenantId, $result);
    }

    public function test_get_tenant_returns_null_when_no_tenant()
    {
        $this->tenantContext
            ->expects($this->once())
            ->method('getCurrentTenant')
            ->willReturn(null);

        $result = Tenancy::getCurrentTenant();

        $this->assertNull($result);
    }

    public function test_clear_tenant_clears_current_tenant()
    {
        $this->tenantContext
            ->expects($this->once())
            ->method('clearCurrentTenant');

        Tenancy::clearCurrentTenant();
    }

    public function test_has_tenant_returns_true_when_tenant_exists()
    {
        $this->tenantContext
            ->expects($this->once())
            ->method('hasCurrentTenant')
            ->willReturn(true);

        $result = Tenancy::hasCurrentTenant();

        $this->assertTrue($result);
    }

    public function test_has_tenant_returns_false_when_no_tenant()
    {
        $this->tenantContext
            ->expects($this->once())
            ->method('hasCurrentTenant')
            ->willReturn(false);

        $result = Tenancy::hasCurrentTenant();

        $this->assertFalse($result);
    }

    public function test_facade_resolves_correct_service()
    {
        $facade = Tenancy::getFacadeRoot();
        
        $this->assertInstanceOf(TenantContext::class, $facade);
    }

    public function test_database_method_returns_tenant_database_manager()
    {
        $manager = Tenancy::database();
        
        $this->assertInstanceOf(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager::class, $manager);
    }

}