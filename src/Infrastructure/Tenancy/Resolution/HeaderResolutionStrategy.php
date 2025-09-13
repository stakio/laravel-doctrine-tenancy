<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantResolutionException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\Contracts\TenantResolutionStrategy;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\Request;

class HeaderResolutionStrategy implements TenantResolutionStrategy
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function resolve(Request $request): ?TenantIdentifier
    {
        $headerName = TenancyConfig::getResolutionHeader();
        $tenantId = $request->header($headerName);

        if (!$tenantId) {
            return null;
        }

        // Basic validation
        if (empty($tenantId) || !is_string($tenantId)) {
            return null;
        }

        try {
            $uuid = \Ramsey\Uuid\Uuid::fromString($tenantId);
        } catch (\Exception $e) {
            return null;
        }

        $tenantEntityClass = TenancyConfig::getTenantEntityClass();
        $tenant = $this->entityManager->find($tenantEntityClass, $uuid);

        if (!$tenant) {
            throw \LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantException::notFound(
                "Tenant with ID '{$tenantId}' not found"
            );
        }

        $identifier = $tenant->toIdentifier();
        
        return $identifier;
    }

    public function getPriority(): int
    {
        return 100; // Highest priority
    }

    public function isApplicable(Request $request): bool
    {
        return $request->hasHeader(TenancyConfig::getResolutionHeader());
    }
}
