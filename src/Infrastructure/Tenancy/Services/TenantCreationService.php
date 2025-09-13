<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Services;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventTracker;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventFactory;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;

/**
 * Tenant Creation Service
 * 
 * Handles tenant creation with optional auto-database setup.
 * 
 * @package LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Services
 * @author Laravel Doctrine Tenancy Team
 * @since 1.4.0
 */
class TenantCreationService
{
    public function __construct(
        private EventTracker $eventTracker,
        private TenantDatabaseManager $databaseManager
    ) {
    }

    /**
     * Create a tenant with optional auto-database setup.
     */
    public function createTenant(TenantId $tenantId, bool $autoSetup = null): array
    {
        $autoSetup = $autoSetup ?? TenancyConfig::isAutoCreateEnabled();
        
        TenancyLogger::tenantResolved($tenantId, 'tenant_creation_started', [
            'auto_setup' => $autoSetup
        ]);

        try {
            // Track tenant created event
            $createdEvent = EventFactory::createTenantCreated($tenantId, [
                'auto_setup' => $autoSetup,
                'created_at' => now()->toISOString()
            ]);
            
            $eventLog = $this->eventTracker->trackTenantEvent($createdEvent);

            // Auto-setup if enabled
            if ($autoSetup) {
                $this->setupTenantDatabase($tenantId, $eventLog->getId()->toString());
            }

            TenancyLogger::tenantResolved($tenantId, 'tenant_creation_completed', [
                'auto_setup' => $autoSetup,
                'event_id' => $eventLog->getId()->toString()
            ]);

            return [
                'success' => true,
                'tenant_id' => $tenantId->value(),
                'event_id' => $eventLog->getId()->toString(),
                'auto_setup' => $autoSetup
            ];

        } catch (\Exception $e) {
            // Track creation failed event
            $failedEvent = EventFactory::createTenantCreationFailed(
                $tenantId,
                $e->getMessage(),
                ['auto_setup' => $autoSetup, 'error' => $e->getMessage()]
            );
            
            $this->eventTracker->trackTenantEvent($failedEvent);

            TenancyLogger::tenantResolved($tenantId, 'tenant_creation_failed', [
                'auto_setup' => $autoSetup,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Setup tenant database and run migrations.
     */
    private function setupTenantDatabase(TenantId $tenantId, string $eventId): void
    {
        TenancyLogger::tenantResolved($tenantId, 'tenant_database_setup_started', [
            'event_id' => $eventId
        ]);

        try {
            // Create database
            $success = $this->databaseManager->createTenantDatabase($tenantId);
            
            if (!$success) {
                throw new \Exception('Failed to create tenant database');
            }

            // Run migrations if auto-migrate is enabled
            if (TenancyConfig::isAutoMigrateEnabled()) {
                $migrationEvent = EventFactory::createTenantMigrationStarted(
                    $tenantId,
                    'latest',
                    ['triggered_by' => 'auto_setup', 'event_id' => $eventId]
                );
                
                $this->eventTracker->trackTenantEvent($migrationEvent);

                $migrationSuccess = $this->databaseManager->migrateTenantDatabase($tenantId);
                
                if ($migrationSuccess) {
                    $completedEvent = EventFactory::createTenantMigrationCompleted(
                        $tenantId,
                        'latest',
                        0,
                        ['migrated_count' => 0, 'event_id' => $eventId]
                    );
                    
                    $this->eventTracker->trackTenantEvent($completedEvent);
                } else {
                    throw new \Exception('Failed to run tenant migrations');
                }
            }

            TenancyLogger::tenantResolved($tenantId, 'tenant_database_setup_completed', [
                'event_id' => $eventId,
                'auto_migrate' => TenancyConfig::isAutoMigrateEnabled()
            ]);

        } catch (\Exception $e) {
            // Track migration failed event
            $failedEvent = EventFactory::createTenantMigrationFailed(
                $tenantId,
                'latest',
                $e->getMessage(),
                ['event_id' => $eventId, 'triggered_by' => 'auto_setup']
            );
            
            $this->eventTracker->trackTenantEvent($failedEvent);

            TenancyLogger::tenantResolved($tenantId, 'tenant_database_setup_failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
