<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\Performance;

use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class LargeDatasetTest extends TestCase
{
    use TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    #[Test]
    public function it_handles_large_number_of_tenants_efficiently()
    {
        $tenantCount = 100;
        $tenants = [];

        // Create many tenants
        for ($i = 0; $i < $tenantCount; $i++) {
            $tenant = $this->createTenant("Tenant {$i}", "tenant{$i}.com");
            $tenants[] = $tenant;
        }

        // Test resolution for each tenant
        foreach ($tenants as $tenant) {
            $request = $this->createRequestWithHeader($tenant->getId()->toString());
            
            $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
                $this->assertTenantResolved($tenant->getId());
            });
            
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    #[Test]
    public function it_handles_mixed_tenant_and_domain_resolution_efficiently()
    {
        $tenantCount = 50;
        $tenants = [];

        // Create tenants with both header and domain resolution
        for ($i = 0; $i < $tenantCount; $i++) {
            $tenant = $this->createTenant("Mixed Tenant {$i}", "mixed{$i}.com");
            $this->createDomainEntity($tenant->getId(), "mixed{$i}.com");
            $tenants[] = $tenant;
        }

        // Test header resolution
        foreach ($tenants as $tenant) {
            $request = $this->createRequestWithHeader($tenant->getId()->toString());
            
            $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
                $this->assertTenantResolved($tenant->getId());
            });
            
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Test domain resolution
        foreach ($tenants as $index => $tenant) {
            $request = $this->createRequestWithDomain("mixed{$index}.com");
            
            $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
                $this->assertTenantResolved($tenant->getId());
            });
            
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    #[Test]
    public function it_handles_rapid_successive_requests()
    {
        $tenant = $this->createTenant('Rapid Tenant', 'rapid.com');
        $this->createDomainEntity($tenant->getId(), 'rapid.com');

        // Simulate rapid successive requests
        for ($i = 0; $i < 50; $i++) {
            $request = $this->createRequestWithHeader($tenant->getId()->toString());
            
            $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
                $this->assertTenantResolved($tenant->getId());
            });
            
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    #[Test]
    public function it_handles_memory_usage_efficiently()
    {
        $initialMemory = memory_get_usage();
        
        // Create and resolve many tenants
        for ($i = 0; $i < 100; $i++) {
            $tenant = $this->createTenant("Memory Tenant {$i}", "memory{$i}.com");
            $request = $this->createRequestWithHeader($tenant->getId()->toString());
            
            $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
                $this->assertTenantResolved($tenant->getId());
            });
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
    }
}
