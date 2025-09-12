<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging;

use Illuminate\Support\Facades\Log;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;

class TenancyLogger
{
    /**
     * Log tenant resolution success.
     */
    public static function tenantResolved(TenantIdentifier $tenant, string $strategy, array $context = []): void
    {
        Log::info('Tenant resolved successfully', array_merge([
            'tenant_id' => $tenant->value(),
            'strategy' => $strategy,
            'event' => 'tenant_resolved'
        ], $context));
    }

    /**
     * Log tenant resolution failure.
     */
    public static function tenantResolutionFailed(string $reason, array $context = []): void
    {
        Log::warning('Tenant resolution failed', array_merge([
            'reason' => $reason,
            'event' => 'tenant_resolution_failed'
        ], $context));
    }

    /**
     * Log tenant context changes.
     */
    public static function tenantContextChanged(?TenantIdentifier $previous, ?TenantIdentifier $current): void
    {
        Log::info('Tenant context changed', [
            'previous_tenant_id' => $previous?->value(),
            'current_tenant_id' => $current?->value(),
            'event' => 'tenant_context_changed'
        ]);
    }

    /**
     * Log database connection events.
     */
    public static function databaseConnectionChanged(string $from, string $to, ?TenantIdentifier $tenant = null): void
    {
        Log::info('Database connection changed', [
            'from' => $from,
            'to' => $to,
            'tenant_id' => $tenant?->value(),
            'event' => 'database_connection_changed'
        ]);
    }

    /**
     * Log tenant database creation.
     */
    public static function tenantDatabaseCreated(TenantIdentifier $tenant, array $context = []): void
    {
        Log::info('Tenant database created', array_merge([
            'tenant_id' => $tenant->value(),
            'event' => 'tenant_database_created'
        ], $context));
    }

    /**
     * Log tenant database creation failure.
     */
    public static function tenantDatabaseCreationFailed(TenantIdentifier $tenant, string $reason, array $context = []): void
    {
        Log::error('Tenant database creation failed', array_merge([
            'tenant_id' => $tenant->value(),
            'reason' => $reason,
            'event' => 'tenant_database_creation_failed'
        ], $context));
    }

    /**
     * Log entity routing decisions.
     */
    public static function entityRouted(string $entityClass, string $target, ?TenantIdentifier $tenant = null): void
    {
        Log::debug('Entity routed', [
            'entity_class' => $entityClass,
            'target' => $target,
            'tenant_id' => $tenant?->value(),
            'event' => 'entity_routed'
        ]);
    }

    /**
     * Log configuration validation events.
     */
    public static function configurationValidated(bool $success, array $errors = []): void
    {
        if ($success) {
            Log::info('Tenancy configuration validated successfully', [
                'event' => 'configuration_validated'
            ]);
        } else {
            Log::error('Tenancy configuration validation failed', [
                'errors' => $errors,
                'event' => 'configuration_validation_failed'
            ]);
        }
    }

    /**
     * Log performance metrics.
     */
    public static function performanceMetric(string $operation, float $duration, array $context = []): void
    {
        Log::info('Performance metric', array_merge([
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'event' => 'performance_metric'
        ], $context));
    }

    /**
     * Log security events.
     */
    public static function securityEvent(string $event, array $context = []): void
    {
        Log::warning('Tenancy security event', array_merge([
            'security_event' => $event,
            'event' => 'security_event'
        ], $context));
    }
}
