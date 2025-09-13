<?php

namespace LaravelDoctrine\Tenancy\Tests\Traits;

use LaravelDoctrine\Tenancy\Domain\Tenant;
use LaravelDoctrine\Tenancy\Domain\DomainEntity;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantName;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Middleware\ResolveTenantMiddleware;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

trait TenancyTestHelpers
{
    protected EntityManagerInterface $entityManager;
    protected TenantContext $tenantContext;
    protected ResolveTenantMiddleware $middleware;

    protected function setUpTenancy(): void
    {
        $this->entityManager = $this->app->make(EntityManagerInterface::class);
        $this->tenantContext = $this->app->make(\LaravelDoctrine\Tenancy\Contracts\TenantContextInterface::class);
        $this->middleware = $this->app->make(ResolveTenantMiddleware::class);
    }

    protected function createTenant(string $name = 'Test Tenant', string $domain = 'test.com'): Tenant
    {
        $tenantId = Uuid::uuid4();
        $tenant = new Tenant(
            $tenantId,
            new TenantName($name),
        );
        
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();
        
        return $tenant;
    }

    protected function createDomainEntity($tenantId, string $domain, bool $isActive = true): DomainEntity
    {
        // Convert UUID to TenantId if needed
        if (!$tenantId instanceof TenantId) {
            $tenantId = new TenantId($tenantId);
        }
        
        $domainEntity = new DomainEntity(
            Uuid::uuid4(),
            new Domain($domain),
            $tenantId,
            false, // isPrimary
            $isActive
        );
        
        $this->entityManager->persist($domainEntity);
        $this->entityManager->flush();
        
        return $domainEntity;
    }

    protected function createRequestWithHeader(string $tenantId, string $path = '/test'): Request
    {
        $request = Request::create($path, 'GET');
        $request->headers->set('X-Tenant-ID', $tenantId);
        return $request;
    }

    protected function createRequestWithDomain(string $domain, string $path = '/test'): Request
    {
        return Request::create("http://{$domain}{$path}", 'GET');
    }

    protected function assertTenantResolved($expectedTenantId): void
    {
        // Convert UUID to string if needed
        $expectedId = $expectedTenantId instanceof TenantId ? $expectedTenantId->value() : $expectedTenantId->toString();
        
        $this->assertNotNull($this->tenantContext->getCurrentTenant());
        $this->assertEquals($expectedId, $this->tenantContext->getCurrentTenant()->value());
    }

    protected function assertNoTenantResolved(): void
    {
        $this->assertNull($this->tenantContext->getCurrentTenant());
    }

    protected function runMiddlewareWithAssertions(Request $request, callable $assertions): \Symfony\Component\HttpFoundation\Response
    {
        return $this->middleware->handle($request, function ($req) use ($assertions) {
            $assertions($req);
            return response('OK');
        });
    }
}
