<?php

namespace LaravelDoctrine\Tenancy\Contracts;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;

interface DomainRepositoryInterface
{
    public function findByDomain(string $domain): ?DomainEntityInterface;
    public function findByTenantId(TenantId $tenantId): array;
    public function findPrimaryByTenantId(TenantId $tenantId): ?DomainEntityInterface;
    public function save(DomainEntityInterface $domainEntity): void;
    public function delete(DomainEntityInterface $domainEntity): void;
    public function setPrimary(DomainEntityInterface $domainEntity): void;
}
