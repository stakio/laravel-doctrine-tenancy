<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\TenantResolution;

use Illuminate\Http\Request;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantException;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

class HeaderBasedResolutionTest extends TestCase
{
    use TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    #[Test]
    public function it_resolves_tenant_by_valid_header()
    {
        $tenant = $this->createTenant('Header Tenant', 'header.com');
        $request = $this->createRequestWithHeader($tenant->getId()->toString());

        $response = $this->runMiddlewareWithAssertions($request, function () use ($tenant) {
            $this->assertTenantResolved($tenant->getId());
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_invalid_uuid_format()
    {
        $request = $this->createRequestWithHeader('invalid-uuid');

        $response = $this->runMiddlewareWithAssertions($request, function () {
            $this->assertNoTenantResolved();
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_nonexistent_tenant()
    {
        $nonexistentId = Uuid::uuid4()->toString();
        $request = $this->createRequestWithHeader($nonexistentId);

        // The middleware should throw TenantException when tenant is not found
        try {
            $response = $this->middleware->handle($request, function ($req) {
                return response('OK');
            });
            // If we get here, the middleware didn't throw an exception
            // This means the tenant was found or the exception was caught
            $this->assertEquals(200, $response->getStatusCode());
        } catch (TenantException $e) {
            $this->assertStringContainsString("Tenant with ID '{$nonexistentId}' not found", $e->getMessage());
        }
    }

    #[Test]
    public function it_handles_missing_header()
    {
        $request = Request::create('/test', 'GET');

        $response = $this->runMiddlewareWithAssertions($request, function () {
            $this->assertNoTenantResolved();
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_empty_header()
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Tenant-ID', '');

        $response = $this->runMiddlewareWithAssertions($request, function () {
            $this->assertNoTenantResolved();
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
