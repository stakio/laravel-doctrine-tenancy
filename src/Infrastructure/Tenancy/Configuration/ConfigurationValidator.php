<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Configuration;

use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenancyConfigurationException;
use Illuminate\Support\Facades\Log;

class ConfigurationValidator
{
    /**
     * Validate the tenancy configuration.
     */
    public static function validate(): void
    {
        self::validateTenantEntity();
        self::validateDomainEntity();
        self::validateEntityRouting();
        self::validateDatabaseConfiguration();
    }

    /**
     * Validate tenant entity configuration.
     */
    private static function validateTenantEntity(): void
    {
        $tenantEntityClass = config('tenancy.tenant_entity');
        
        if (!$tenantEntityClass) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.tenant_entity');
        }

        if (!class_exists($tenantEntityClass)) {
            throw TenancyConfigurationException::invalidEntityClass($tenantEntityClass);
        }

        if (!is_subclass_of($tenantEntityClass, \LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface::class)) {
            throw TenancyConfigurationException::invalidEntityClass(
                "Tenant entity '{$tenantEntityClass}' must implement TenantEntityInterface"
            );
        }
    }

    /**
     * Validate domain entity configuration.
     */
    private static function validateDomainEntity(): void
    {
        $domainEntityClass = config('tenancy.domain_entity');
        
        if (!$domainEntityClass) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.domain_entity');
        }

        if (!class_exists($domainEntityClass)) {
            throw TenancyConfigurationException::invalidEntityClass($domainEntityClass);
        }

        if (!is_subclass_of($domainEntityClass, \LaravelDoctrine\Tenancy\Contracts\DomainEntityInterface::class)) {
            throw TenancyConfigurationException::invalidEntityClass(
                "Domain entity '{$domainEntityClass}' must implement DomainEntityInterface"
            );
        }
    }

    /**
     * Validate entity routing configuration.
     */
    private static function validateEntityRouting(): void
    {
        $routing = config('tenancy.entity_routing');
        
        if (!$routing) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.entity_routing');
        }

        if (!isset($routing['central']) || !is_array($routing['central'])) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.entity_routing.central');
        }

        if (!isset($routing['tenant']) || !is_array($routing['tenant'])) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.entity_routing.tenant');
        }

        // Validate that all configured entities exist
        $allEntities = array_merge($routing['central'], $routing['tenant']);
        foreach ($allEntities as $entity) {
            if (!class_exists($entity)) {
                throw TenancyConfigurationException::invalidEntityClass($entity);
            }
        }
    }

    /**
     * Validate database configuration.
     */
    private static function validateDatabaseConfiguration(): void
    {
        // Skip database validation in testing environment
        if (app()->environment('testing')) {
            return;
        }

        $centralConnection = config('tenancy.database.central_connection');
        
        if (!$centralConnection) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.database.central_connection');
        }

        $databasePrefix = config('tenancy.database.prefix');
        if (!$databasePrefix) {
            throw TenancyConfigurationException::missingConfiguration('tenancy.database.prefix');
        }

        if (!is_string($databasePrefix) || empty(trim($databasePrefix))) {
            throw TenancyConfigurationException::invalidConfiguration(
                'Database prefix must be a non-empty string'
            );
        }
    }

    /**
     * Validate configuration with logging.
     */
    public static function validateWithLogging(): void
    {
        try {
            self::validate();
            Log::info('Tenancy configuration validation passed');
        } catch (TenancyConfigurationException $e) {
            Log::error('Tenancy configuration validation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw $e;
        }
    }
}
