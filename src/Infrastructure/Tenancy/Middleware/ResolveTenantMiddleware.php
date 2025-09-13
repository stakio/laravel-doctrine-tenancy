<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Middleware;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\TenantResolver;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\HeaderResolutionStrategy;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\DomainResolutionStrategy;
use Doctrine\ORM\EntityManagerInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantMiddleware
{
    private TenantResolver $resolver;

    public function __construct(
        private TenantContextInterface $tenantContext,
        EntityManagerInterface $entityManager
    ) {
        $this->resolver = new TenantResolver(
            new HeaderResolutionStrategy($entityManager),
            new DomainResolutionStrategy($entityManager)
        );
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tenant = $this->resolver->resolve($request);

            if ($tenant) {
                $this->tenantContext->setCurrentTenant($tenant);
                Log::info("Tenant resolved: {$tenant->value()}");
            } else {
                Log::warning('No tenant could be resolved from request');
            }
        } catch (\Exception $e) {
            Log::error('Error resolving tenant: ' . $e->getMessage());
            // Re-throw TenantException so it can be handled by the application
            if ($e instanceof TenantException) {
                throw $e;
            }
        }

        $response = $next($request);

        $this->tenantContext->clearCurrentTenant();

        return $response;
    }
}
