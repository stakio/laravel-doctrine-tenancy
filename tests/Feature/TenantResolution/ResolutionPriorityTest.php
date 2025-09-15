<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\TenantResolution;

use Illuminate\Http\Request;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use PHPUnit\Framework\Attributes\Test;

class ResolutionPriorityTest extends TestCase
{
    use TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    #[Test]
    public function it_prioritizes_header_over_domain_resolution()
    {
        // Create two tenants - one for header, one for domain
        $headerTenant = $this->createTenant('Header Tenant', 'header.com');
        $domainTenant = $this->createTenant('Domain Tenant', 'domain.com');
        $this->createDomainEntity($domainTenant->getId(), 'domain.com');

        // Request with both header and domain
        $request = $this->createRequestWithHeader($headerTenant->getId()->toString());
        $request->server->set('HTTP_HOST', 'domain.com');

        $response = $this->runMiddlewareWithAssertions($request, function () use ($headerTenant) {
            // Should resolve header tenant, not domain tenant
            $this->assertTenantResolved($headerTenant->getId());
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_falls_back_to_domain_when_header_fails()
    {
        $domainTenant = $this->createTenant('Domain Tenant', 'domain.com');
        $domainEntity = $this->createDomainEntity($domainTenant->getId(), 'domain.com');

        // Verify domain entity was created
        $this->assertTrue($domainEntity->isActive());
        $this->assertEquals('domain.com', $domainEntity->domain()->value());

        // Request with invalid header but valid domain
        $request = Request::create('http://domain.com/test', 'GET');
        $request->headers->set('X-Tenant-ID', 'invalid-uuid');

        $response = $this->runMiddlewareWithAssertions($request, function ($req) use ($domainTenant) {
            // Should fall back to domain resolution
            $currentTenant = $this->tenantContext->getCurrentTenant();
            if ($currentTenant === null) {
                // Debug: Let's see what's in the request
                $this->fail('Expected tenant to be resolved via domain fallback, but got null. Host: '.$req->getHost());
            }
            $this->assertTenantResolved($domainTenant->getId());
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_no_resolution_when_both_fail()
    {
        // Request with invalid header and invalid domain
        $request = $this->createRequestWithHeader('invalid-uuid');
        $request->server->set('HTTP_HOST', 'nonexistent.com');

        $response = $this->runMiddlewareWithAssertions($request, function () {
            $this->assertNoTenantResolved();
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
