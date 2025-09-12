<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Middleware;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantMiddleware
{
    public function __construct(
        private TenantContextInterface $tenantContext
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tenant = $this->resolveTenantFromHeader($request);

            if ($tenant) {
                $this->tenantContext->setCurrentTenant($tenant);
                Log::info("Tenant resolved: {$tenant->value()}");
            } else {
                Log::warning('No tenant could be resolved from request');
            }
        } catch (\Exception $e) {
            Log::error('Error resolving tenant: ' . $e->getMessage());
        }

        $response = $next($request);

        $this->tenantContext->clearCurrentTenant();

        return $response;
    }

    private function resolveTenantFromHeader(Request $request): ?TenantIdentifier
    {
        $headerName = TenancyConfig::getResolutionHeader();
        $tenantId = $request->header($headerName);

        if ($tenantId) {
            try {
                $uuid = \Ramsey\Uuid\Uuid::fromString($tenantId);

                $tenantIdentifier = new \LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId($uuid);

                // Note: In a real implementation, you would need to inject the repository
                // For now, we'll assume the tenant exists
                // $tenantRepository = app(\LaravelDoctrine\Tenancy\Contracts\TenantRepositoryInterface::class);
                // $tenant = $tenantRepository->findById($uuid);

                // if (!$tenant) {
                //     throw TenantException::notFound(
                //         "Tenant with ID '{$tenantId}' not found"
                //     );
                // }

                return $tenantIdentifier;
            } catch (\Exception $e) {
                if ($e instanceof TenantException) {
                    throw $e;
                }
                Log::error('Error resolving tenant: ' . $e->getMessage());

                return null;
            }
        }

        return null;
    }
}
