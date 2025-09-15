<?php

namespace LaravelDoctrine\Tenancy\Tests;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantContext;
use Orchestra\Testbench\TestCase;

class TenancyTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \LaravelDoctrine\Tenancy\Infrastructure\Providers\TenancyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('tenancy.enabled', true);
    }

    public function test_tenant_context_can_be_resolved()
    {
        $context = $this->app->make(TenantContextInterface::class);

        $this->assertInstanceOf(TenantContext::class, $context);
    }

    public function test_tenant_context_manages_tenant()
    {
        $context = $this->app->make(TenantContextInterface::class);
        $tenantId = TenantId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $this->assertFalse($context->hasCurrentTenant());

        $context->setCurrentTenant($tenantId);

        $this->assertTrue($context->hasCurrentTenant());
        $this->assertEquals($tenantId, $context->getCurrentTenant());

        $context->clearCurrentTenant();

        $this->assertFalse($context->hasCurrentTenant());
    }
}
