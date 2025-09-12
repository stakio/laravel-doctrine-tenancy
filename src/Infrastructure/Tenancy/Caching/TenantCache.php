<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Caching;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use Illuminate\Support\Facades\Cache;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;

class TenantCache
{
    private string $prefix;
    private int $ttl;

    public function __construct()
    {
        $this->prefix = TenancyConfig::getCachePrefix();
        $this->ttl = TenancyConfig::getCacheTtl();
    }

    /**
     * Cache tenant resolution by domain.
     */
    public function cacheTenantByDomain(string $domain, TenantIdentifier $tenant): void
    {
        $key = $this->getDomainKey($domain);
        Cache::put($key, $tenant->value(), $this->ttl);
    }

    /**
     * Get cached tenant by domain.
     */
    public function getTenantByDomain(string $domain): ?TenantIdentifier
    {
        $key = $this->getDomainKey($domain);
        $tenantId = Cache::get($key);
        
        if (!$tenantId) {
            return null;
        }

        // Return a simple identifier wrapper
        return new class($tenantId) implements TenantIdentifier {
            public function __construct(private string $value) {}
            public function value(): string { return $this->value; }
            public function equals(TenantIdentifier $other): bool { return $this->value === $other->value(); }
            public function __toString(): string { return $this->value; }
        };
    }

    /**
     * Cache tenant entity data.
     */
    public function cacheTenantEntity(string $tenantId, array $data): void
    {
        $key = $this->getTenantKey($tenantId);
        Cache::put($key, $data, $this->ttl);
    }

    /**
     * Get cached tenant entity data.
     */
    public function getTenantEntity(string $tenantId): ?array
    {
        $key = $this->getTenantKey($tenantId);
        return Cache::get($key);
    }

    /**
     * Cache domain entity data.
     */
    public function cacheDomainEntity(string $domain, array $data): void
    {
        $key = $this->getDomainEntityKey($domain);
        Cache::put($key, $data, $this->ttl);
    }

    /**
     * Get cached domain entity data.
     */
    public function getDomainEntity(string $domain): ?array
    {
        $key = $this->getDomainEntityKey($domain);
        return Cache::get($key);
    }

    /**
     * Invalidate tenant cache.
     */
    public function invalidateTenant(string $tenantId): void
    {
        $key = $this->getTenantKey($tenantId);
        Cache::forget($key);
    }

    /**
     * Invalidate domain cache.
     */
    public function invalidateDomain(string $domain): void
    {
        $domainKey = $this->getDomainKey($domain);
        $domainEntityKey = $this->getDomainEntityKey($domain);
        
        Cache::forget($domainKey);
        Cache::forget($domainEntityKey);
    }

    /**
     * Clear all tenant-related cache.
     */
    public function clearAll(): void
    {
        $pattern = $this->prefix . '*';
        // Note: This is a simplified implementation. In production, you might want to use Redis SCAN
        // or implement a more sophisticated cache invalidation strategy
        Cache::flush();
    }

    /**
     * Get cache key for domain-based tenant resolution.
     */
    private function getDomainKey(string $domain): string
    {
        return $this->prefix . 'domain:' . md5($domain);
    }

    /**
     * Get cache key for tenant entity data.
     */
    private function getTenantKey(string $tenantId): string
    {
        return $this->prefix . 'tenant:' . $tenantId;
    }

    /**
     * Get cache key for domain entity data.
     */
    private function getDomainEntityKey(string $domain): string
    {
        return $this->prefix . 'domain_entity:' . md5($domain);
    }
}
