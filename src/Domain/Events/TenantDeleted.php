<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;

class TenantDeleted
{
    public function __construct(
        public readonly TenantEntityInterface $tenant
    ) {}
}
