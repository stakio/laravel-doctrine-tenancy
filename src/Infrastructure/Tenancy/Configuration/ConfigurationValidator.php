<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Configuration;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenancyConfigurationException;

class ConfigurationValidator
{
    /**
     * Validate essential tenancy configuration.
     */
    public static function validate(): void
    {
        self::validateTenantEntity();
        self::validateDomainEntity();
    }

    private static function validateTenantEntity(): void
    {
        $tenantEntityClass = config('tenancy.tenant_entity');

        if (! $tenantEntityClass || ! class_exists($tenantEntityClass)) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.tenant_entity');
        }

        if (! is_subclass_of($tenantEntityClass, \LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface::class)) {
            throw TenancyConfigurationException::invalidEntityClass(
                "Tenant entity '{$tenantEntityClass}' must implement TenantEntityInterface"
            );
        }
    }

    private static function validateDomainEntity(): void
    {
        $domainEntityClass = config('tenancy.domain_entity');

        if (! $domainEntityClass || ! class_exists($domainEntityClass)) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.domain_entity');
        }

        if (! is_subclass_of($domainEntityClass, \LaravelDoctrine\Tenancy\Contracts\DomainEntityInterface::class)) {
            throw TenancyConfigurationException::invalidEntityClass(
                "Domain entity '{$domainEntityClass}' must implement DomainEntityInterface"
            );
        }
    }
}
