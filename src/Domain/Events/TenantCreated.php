<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;

class TenantCreated
{
    public function __construct(
        public readonly TenantEntityInterface $tenant
    ) {}
}
