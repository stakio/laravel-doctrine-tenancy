<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Repositories;

use LaravelDoctrine\Tenancy\Contracts\DomainEntityInterface;
use LaravelDoctrine\Tenancy\Contracts\DomainRepositoryInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineDomainRepository implements DomainRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function findByDomain(string $domain): ?DomainEntityInterface
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

    public function findByTenantId(TenantId $tenantId): array
    {
        $domainEntityClass = TenancyConfig::getDomainEntityClass();
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
           ->from($domainEntityClass, 'd')
           ->where('d.tenantId = :tenantId')
           ->andWhere('d.isActive = :active')
           ->setParameter('tenantId', $tenantId->value())
           ->setParameter('active', true)
           ->orderBy('d.isPrimary', 'DESC');
           
        return $qb->getQuery()->getResult();
    }

    public function findPrimaryByTenantId(TenantId $tenantId): ?DomainEntityInterface
    {
        $domainEntityClass = TenancyConfig::getDomainEntityClass();
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
           ->from($domainEntityClass, 'd')
           ->where('d.tenantId = :tenantId')
           ->andWhere('d.isPrimary = :primary')
           ->andWhere('d.isActive = :active')
           ->setParameter('tenantId', $tenantId->value())
           ->setParameter('primary', true)
           ->setParameter('active', true);
           
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function save(DomainEntityInterface $domainEntity): void
    {
        $this->entityManager->persist($domainEntity);
        $this->entityManager->flush();
    }

    public function delete(DomainEntityInterface $domainEntity): void
    {
        $this->entityManager->remove($domainEntity);
        $this->entityManager->flush();
    }

    public function setPrimary(DomainEntityInterface $domainEntity): void
    {
        // First, unset all other primary domains for this tenant
        $tenantId = $domainEntity->tenantId();
        $existingDomains = $this->findByTenantId($tenantId);
        
        foreach ($existingDomains as $existingDomain) {
            if ($existingDomain->getId() !== $domainEntity->getId()) {
                $existingDomain->setPrimary(false);
                $this->entityManager->persist($existingDomain);
            }
        }
        
        // Set this domain as primary
        $domainEntity->setPrimary(true);
        $this->entityManager->persist($domainEntity);
        $this->entityManager->flush();
    }
}
