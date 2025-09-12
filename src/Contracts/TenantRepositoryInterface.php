<?php

namespace LaravelDoctrine\Tenancy\Contracts;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?TenantEntityInterface;

    public function findByDomain(string $domain): ?TenantEntityInterface;

    public function save(TenantEntityInterface $tenant): void;

    public function delete(TenantEntityInterface $tenant): void;
}
