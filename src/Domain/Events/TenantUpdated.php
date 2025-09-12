<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;

class TenantUpdated
{
    public function __construct(
        public readonly TenantEntityInterface $tenant
    ) {}
}
