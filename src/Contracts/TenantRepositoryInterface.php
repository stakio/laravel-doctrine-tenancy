<?php

namespace LaravelDoctrine\Tenancy\Contracts;

use LaravelDoctrine\Tenancy\Domain\Tenant;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;

    public function findByDomain(string $domain): ?Tenant;

    public function save(Tenant $tenant): void;

    public function delete(Tenant $tenant): void;
}
