<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantResolutionException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\Contracts\TenantResolutionStrategy;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Validation\InputValidator;
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

        // Validate and sanitize input
        try {
            $tenantId = InputValidator::validateHeaderValue($tenantId);
            if (!$tenantId) {
                return null;
            }
            
            $tenantId = InputValidator::validateTenantId($tenantId);
        } catch (\Exception $e) {
            TenancyLogger::tenantResolutionFailed('Invalid tenant ID in header', [
                'header' => $headerName,
                'value' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }

        try {
            $uuid = \Ramsey\Uuid\Uuid::fromString($tenantId);
        } catch (\Exception $e) {
            TenancyLogger::tenantResolutionFailed('Invalid tenant ID format in header', [
                'header' => $headerName,
                'value' => $tenantId,
                'error' => $e->getMessage()
            ]);
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
        TenancyLogger::tenantResolved($identifier, 'header', [
            'header' => $headerName,
            'tenant_id' => $tenantId
        ]);

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
