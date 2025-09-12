<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;

class TenantContext implements TenantContextInterface
{
    private ?TenantIdentifier $currentTenant = null;

    public function getCurrentTenant(): ?TenantIdentifier
    {
        return $this->currentTenant;
    }

    public function setCurrentTenant(TenantIdentifier $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    public function clearCurrentTenant(): void
    {
        $this->currentTenant = null;
    }

    public function hasCurrentTenant(): bool
    {
        return $this->currentTenant !== null;
    }
}
