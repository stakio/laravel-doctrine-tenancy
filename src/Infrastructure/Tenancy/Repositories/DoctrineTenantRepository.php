<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Repositories;

use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantRepositoryInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

class DoctrineTenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function findById(string $id): ?TenantEntityInterface
    {
        $tenantEntityClass = TenancyConfig::getTenantEntityClass();
        
        try {
            $uuid = Uuid::fromString($id);
            return $this->entityManager->find($tenantEntityClass, $uuid);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findByDomain(string $domain): ?TenantEntityInterface
    {
        $tenantEntityClass = TenancyConfig::getTenantEntityClass();
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
           ->from($tenantEntityClass, 't')
           ->where('t.domain = :domain')
           ->setParameter('domain', $domain);
           
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function save(TenantEntityInterface $tenant): void
    {
        $this->entityManager->persist($tenant);
        $this->entityManager->flush();
    }

    public function delete(TenantEntityInterface $tenant): void
    {
        $this->entityManager->remove($tenant);
        $this->entityManager->flush();
    }
}
