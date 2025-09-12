<?php

namespace LaravelDoctrine\Tenancy\Domain\Events;

use LaravelDoctrine\Tenancy\Domain\Tenant;

class TenantDeleted
{
    public function __construct(
        public readonly Tenant $tenant
    ) {}
}
