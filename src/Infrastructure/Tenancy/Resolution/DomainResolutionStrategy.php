<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantResolutionException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\Contracts\TenantResolutionStrategy;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;

class DomainResolutionStrategy implements TenantResolutionStrategy
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function resolve(Request $request): ?TenantIdentifier
    {
        $host = $request->getHost();

        // Basic domain validation
        if (empty($host) || ! is_string($host)) {
            return null;
        }

        // Skip common non-tenant subdomains
        $excludedSubdomains = TenancyConfig::getExcludedSubdomains();

        if (in_array($host, $excludedSubdomains)) {
            return null;
        }

        // Try to find tenant by domain using EntityManager
        $domainEntity = $this->findDomainByDomain($host);
        if (! $domainEntity) {
            return null;
        }

        // Get the tenant entity using the domain's tenant ID
        $tenant = $this->findTenantById($domainEntity->tenantId()->value());
        if (! $tenant) {
            throw TenantResolutionException::domainNotFound($host);
        }

        $identifier = $tenant->toIdentifier();

        Log::info("Tenant resolved by domain: {$host} -> {$identifier->value()}");

        return $identifier;
    }

    public function getPriority(): int
    {
        return 50; // Lower priority than header
    }

    public function isApplicable(Request $request): bool
    {
        $host = $request->getHost();
        $excludedSubdomains = TenancyConfig::getExcludedSubdomains();

        return ! in_array($host, $excludedSubdomains) && ! empty($host);
    }

    /**
     * Find tenant by ID using EntityManager
     */
    private function findTenantById(string $tenantId): ?object
    {
        $tenantEntityClass = TenancyConfig::getTenantEntityClass();

        try {
            $uuid = \Ramsey\Uuid\Uuid::fromString($tenantId);

            return $this->entityManager->find($tenantEntityClass, $uuid);
        } catch (\Exception $e) {
            Log::warning('Invalid tenant ID in domain resolution', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find domain by domain name using EntityManager
     */
    private function findDomainByDomain(string $domain): ?object
    {
        $domainEntityClass = TenancyConfig::getDomainEntityClass();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
            ->from($domainEntityClass, 'd')
            ->where('d.domain = :domain')
            ->andWhere('d.isActive = :active')
            ->setParameter('domain', $domain)
            ->setParameter('active', true);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
