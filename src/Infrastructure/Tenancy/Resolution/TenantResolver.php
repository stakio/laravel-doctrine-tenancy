<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Resolution\Contracts\TenantResolutionStrategy;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use Illuminate\Http\Request;

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
            if (!$strategy->isApplicable($request)) {
                continue;
            }

            try {
                $tenant = $strategy->resolve($request);
                if ($tenant) {
                    TenancyLogger::tenantResolved($tenant, get_class($strategy), [
                        'strategy' => get_class($strategy),
                        'tenant_id' => $tenant->value()
                    ]);
                    return $tenant;
                }
            } catch (\Exception $e) {
                // Re-throw TenantException and TenantResolutionException
                if ($e instanceof \LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantException ||
                    $e instanceof \LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantResolutionException) {
                    throw $e;
                }
                
                TenancyLogger::tenantResolutionFailed('Strategy failed to resolve tenant', [
                    'strategy' => get_class($strategy),
                    'error' => $e->getMessage()
                ]);
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
