<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\TenantResolution;

use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class DomainBasedResolutionTest extends TestCase
{
    use TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    #[Test]
    public function it_resolves_tenant_by_domain()
    {
        $tenant = $this->createTenant('Domain Tenant', 'domain.com');
        $this->createDomainEntity($tenant->getId(), 'domain.com');
        
        $request = $this->createRequestWithDomain('domain.com');

        $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
            $this->assertTenantResolved($tenant->getId());
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_resolves_tenant_by_subdomain()
    {
        $tenant = $this->createTenant('Subdomain Tenant', 'sub.example.com');
        $this->createDomainEntity($tenant->getId(), 'sub.example.com');
        
        $request = $this->createRequestWithDomain('sub.example.com');

        $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
            $this->assertTenantResolved($tenant->getId());
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_resolves_tenant_by_top_level_domain()
    {
        $tenant = $this->createTenant('TLD Tenant', 'example.com');
        $this->createDomainEntity($tenant->getId(), 'example.com');
        
        $request = $this->createRequestWithDomain('example.com');

        $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
            $this->assertTenantResolved($tenant->getId());
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_nonexistent_domain()
    {
        $request = $this->createRequestWithDomain('nonexistent.com');

        $response = $this->runMiddlewareWithAssertions($request, function () {
            $this->assertNoTenantResolved();
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_inactive_domain()
    {
        $tenant = $this->createTenant('Inactive Tenant', 'inactive.com');
        $this->createDomainEntity($tenant->getId(), 'inactive.com', false);
        
        $request = $this->createRequestWithDomain('inactive.com');

        $response = $this->runMiddlewareWithAssertions($request, function () {
            $this->assertNoTenantResolved();
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_excluded_subdomains()
    {
        $excludedDomains = ['www', 'api', 'admin', 'app'];
        
        foreach ($excludedDomains as $subdomain) {
            $request = $this->createRequestWithDomain($subdomain . '.example.com');

            $response = $this->runMiddlewareWithAssertions($request, function () {
                $this->assertNoTenantResolved();
            });

            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    #[Test]
    public function it_handles_case_insensitive_domain_resolution()
    {
        $tenant = $this->createTenant('Case Tenant', 'case.com');
        $this->createDomainEntity($tenant->getId(), 'case.com');
        
        $testCases = [
            'case.com',
            'Case.com',
            'CASE.COM',
            'Case.COM'
        ];

        foreach ($testCases as $testDomain) {
            $request = $this->createRequestWithDomain($testDomain);

            $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
                $this->assertTenantResolved($tenant->getId());
            });

            $this->assertEquals(200, $response->getStatusCode());
        }
    }
}
