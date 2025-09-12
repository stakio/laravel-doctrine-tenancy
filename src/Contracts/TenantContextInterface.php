<?php

namespace LaravelDoctrine\Tenancy\Contracts;

interface TenantContextInterface
{
    public function getCurrentTenant(): ?TenantIdentifier;

    public function setCurrentTenant(TenantIdentifier $tenant): void;

    public function clearCurrentTenant(): void;

    public function hasCurrentTenant(): bool;
}
