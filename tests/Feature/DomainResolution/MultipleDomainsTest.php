<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\DomainResolution;

use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class MultipleDomainsTest extends TestCase
{
    use TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    #[Test]
    public function it_handles_multiple_domains_per_tenant()
    {
        $tenant = $this->createTenant('Multi-Domain Tenant', 'primary.com');
        
        // Add multiple domains for the same tenant
        $domains = ['primary.com', 'secondary.com', 'tertiary.com'];
        
        foreach ($domains as $domain) {
            $this->createDomainEntity($tenant->getId(), $domain);
        }

        // Test each domain resolves to the same tenant
        foreach ($domains as $domain) {
            $request = $this->createRequestWithDomain($domain);

            $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
                $this->assertTenantResolved($tenant->getId());
            });

            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    #[Test]
    public function it_handles_different_tenants_with_similar_domains()
    {
        $tenant1 = $this->createTenant('Tenant 1', 'tenant1.com');
        $tenant2 = $this->createTenant('Tenant 2', 'tenant2.com');
        
        $this->createDomainEntity($tenant1->getId(), 'tenant1.com');
        $this->createDomainEntity($tenant2->getId(), 'tenant2.com');

        // Test tenant1 domain
        $request1 = $this->createRequestWithDomain('tenant1.com');
        $response1 = $this->runMiddlewareWithAssertions($request1, function () use ($tenant1) {
            $this->assertTenantResolved($tenant1->getId());
        });
        $this->assertEquals(200, $response1->getStatusCode());

        // Test tenant2 domain
        $request2 = $this->createRequestWithDomain('tenant2.com');
        $response2 = $this->runMiddlewareWithAssertions($request2, function () use ($tenant2) {
            $this->assertTenantResolved($tenant2->getId());
        });
        $this->assertEquals(200, $response2->getStatusCode());
    }

    #[Test]
    public function it_handles_mixed_active_and_inactive_domains()
    {
        $tenant = $this->createTenant('Mixed Tenant', 'active.com');
        
        // Create active domain
        $this->createDomainEntity($tenant->getId(), 'active.com', true);
        // Create inactive domain
        $this->createDomainEntity($tenant->getId(), 'inactive.com', false);

        // Active domain should work
        $activeRequest = $this->createRequestWithDomain('active.com');
        $activeResponse = $this->runMiddlewareWithAssertions($activeRequest, function () use ($tenant) {
            $this->assertTenantResolved($tenant->getId());
        });
        $this->assertEquals(200, $activeResponse->getStatusCode());

        // Inactive domain should not work
        $inactiveRequest = $this->createRequestWithDomain('inactive.com');
        $inactiveResponse = $this->runMiddlewareWithAssertions($inactiveRequest, function () {
            $this->assertNoTenantResolved();
        });
        $this->assertEquals(200, $inactiveResponse->getStatusCode());
    }

    #[Test]
    public function it_handles_domain_with_special_characters()
    {
        $tenant = $this->createTenant('Special Tenant', 'special-domain.com');
        $this->createDomainEntity($tenant->getId(), 'special-domain.com');
        
        $request = $this->createRequestWithDomain('special-domain.com');

        $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
            $this->assertTenantResolved($tenant->getId());
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
