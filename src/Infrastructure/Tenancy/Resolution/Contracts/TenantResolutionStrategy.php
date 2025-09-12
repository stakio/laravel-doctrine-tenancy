<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\Contracts;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use Illuminate\Http\Request;

interface TenantResolutionStrategy
{
    /**
     * Resolve tenant from the given request.
     */
    public function resolve(Request $request): ?TenantIdentifier;

    /**
     * Get the priority of this strategy (higher = more priority).
     */
    public function getPriority(): int;

    /**
     * Check if this strategy is applicable for the given request.
     */
    public function isApplicable(Request $request): bool;
}
