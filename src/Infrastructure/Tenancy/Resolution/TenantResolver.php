<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution;

use Illuminate\Http\Request;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\Contracts\TenantResolutionStrategy;

class TenantResolver
{
    /**
     * @var TenantResolutionStrategy[]
     */
    private array $strategies = [];

    public function __construct(TenantResolutionStrategy ...$strategies)
    {
        $this->strategies = $strategies;
        $this->sortStrategiesByPriority();
    }

    /**
     * Resolve tenant using all available strategies in priority order.
     */
    public function resolve(Request $request): ?TenantIdentifier
    {
        foreach ($this->strategies as $strategy) {
            if (! $strategy->isApplicable($request)) {
                continue;
            }

            try {
                $tenant = $strategy->resolve($request);
                if ($tenant) {
                    return $tenant;
                }
            } catch (\Exception $e) {
                // Re-throw TenantException and TenantResolutionException
                if ($e instanceof \LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantException ||
                    $e instanceof \LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantResolutionException) {
                    throw $e;
                }
                // Continue to next strategy for other exceptions
            }
        }

        return null;
    }

    /**
     * Add a new resolution strategy.
     */
    public function addStrategy(TenantResolutionStrategy $strategy): void
    {
        $this->strategies[] = $strategy;
        $this->sortStrategiesByPriority();
    }

    /**
     * Sort strategies by priority (highest first).
     */
    private function sortStrategiesByPriority(): void
    {
        usort($this->strategies, function (TenantResolutionStrategy $a, TenantResolutionStrategy $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }
}
