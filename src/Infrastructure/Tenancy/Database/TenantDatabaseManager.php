<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Exceptions\ConfigurationException;
use LaravelDoctrine\Tenancy\Exceptions\InvalidTenantIdException;
use LaravelDoctrine\Tenancy\Exceptions\MigrationException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantDatabaseException;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal This class is not part of the public API and may change without notice.
 */
class TenantDatabaseManager
{
    public function deleteTenantDatabase(TenantIdentifier $tenant): bool
    {
        try {
            $databaseName = $this->buildDatabaseName($tenant);
            $this->dropDatabase($databaseName);

            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->info("Successfully deleted database for tenant: {$tenant->value()}");
            } else {
                Log::info("Successfully deleted database for tenant: {$tenant->value()}");
            }

            return true;
        } catch (\Exception $e) {
            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->error("Failed to delete database for tenant {$tenant->value()}: ".$e->getMessage());
            } else {
                Log::error("Failed to delete database for tenant {$tenant->value()}: ".$e->getMessage());
            }

            return false;
        }
    }

    public function migrateTenantDatabase(TenantIdentifier $tenant): bool
    {
        $databaseName = $this->buildDatabaseName($tenant);
        $wasDatabaseCreated = false;

        try {
            // Validate tenant ID format
            $this->validateTenantId($tenant);

            // Check if database exists, create if not
            if (! $this->databaseExists($databaseName)) {
                $this->createDatabase($databaseName);
                $wasDatabaseCreated = true;
                Log::info("Created database for tenant: {$tenant->value()}");
            }

            // Run migrations with rollback capability
            $this->runTenantMigrationsWithRollback($tenant, $wasDatabaseCreated);

            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->info("Successfully migrated database for tenant: {$tenant->value()}");
            } else {
                Log::info("Successfully migrated database for tenant: {$tenant->value()}");
            }

            return true;

        } catch (\Exception $e) {
            // If we created the database and migration failed, clean it up
            if ($wasDatabaseCreated) {
                try {
                    $this->dropDatabase($databaseName);
                    if ($channel = TenancyConfig::getLogChannel()) {
                        Log::channel($channel)->info("Cleaned up failed database for tenant: {$tenant->value()}");
                    } else {
                        Log::info("Cleaned up failed database for tenant: {$tenant->value()}");
                    }
                } catch (\Exception $cleanupException) {
                    if ($channel = TenancyConfig::getLogChannel()) {
                        Log::channel($channel)->error("Failed to cleanup database for tenant {$tenant->value()}: ".$cleanupException->getMessage());
                    } else {
                        Log::error("Failed to cleanup database for tenant {$tenant->value()}: ".$cleanupException->getMessage());
                    }
                }
            }

            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->error("Failed to migrate database for tenant {$tenant->value()}: ".$e->getMessage());
            } else {
                Log::error("Failed to migrate database for tenant {$tenant->value()}: ".$e->getMessage());
            }
            throw TenantDatabaseException::connectionFailed((string) $tenant->value(), $e->getMessage());
        }
    }

    private function buildDatabaseName(TenantIdentifier $tenant): string
    {
        $prefix = TenancyConfig::getDatabasePrefix();
        $naming = TenancyConfig::getDatabaseNaming();

        return match ($naming['strategy']) {
            'prefix' => $prefix.$tenant->value(),
            'suffix' => $tenant->value().$naming['separator'].$prefix,
            default => $prefix.$tenant->value(),
        };
    }

    private function databaseExists(string $databaseName): bool
    {
        $connection = $this->getCentralConnection();

        try {
            $driver = $connection->getDriverName();
            if ($driver === 'mysql') {
                $result = $connection->select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);

                return ! empty($result);
            }
            if ($driver === 'pgsql') {
                $result = $connection->select('SELECT 1 FROM pg_database WHERE datname = ?', [$databaseName]);

                return ! empty($result);
            }
            if ($driver === 'sqlite') {
                // For sqlite, the 'database' is a file path; existence is whether file exists, but we assume non-empty string means ok.
                return ! empty($databaseName);
            }
            // Default fallback
            $result = $connection->select('SELECT 1');

            return (bool) $result;
        } catch (\Exception $e) {
            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->error('Error checking if database exists: '.$e->getMessage());
            } else {
                Log::error('Error checking if database exists: '.$e->getMessage());
            }

            return false;
        }
    }

    private function createDatabase(string $databaseName): void
    {
        $connection = $this->getCentralConnection();
        $driver = $connection->getDriverName();
        if ($driver === 'mysql') {
            $connection->statement("CREATE DATABASE `{$databaseName}`");

            return;
        }
        if ($driver === 'pgsql') {
            $connection->statement("CREATE DATABASE \"{$databaseName}\"");

            return;
        }
        if ($driver === 'sqlite') {
            // sqlite DB is created on first connection use
            return;
        }
    }

    private function dropDatabase(string $databaseName): void
    {
        $connection = $this->getCentralConnection();
        $driver = $connection->getDriverName();
        if ($driver === 'mysql') {
            $connection->statement("DROP DATABASE IF EXISTS `{$databaseName}`");

            return;
        }
        if ($driver === 'pgsql') {
            // Terminate connections and drop
            $connection->statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()', [$databaseName]);
            $connection->statement("DROP DATABASE IF EXISTS \"{$databaseName}\"");

            return;
        }
        if ($driver === 'sqlite') {
            return;
        }
    }

    private function runTenantMigrationsWithRollback(TenantIdentifier $tenant, bool $wasDatabaseCreated): void
    {
        $this->setTenantDatabaseConnection($tenant);
        $migrationBatch = null;

        try {
            // Get current migration status
            $migrationStatus = $this->getMigrationStatus();
            $migrationBatch = $migrationStatus['batch'] ?? 0;

            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->info("Starting migrations for tenant {$tenant->value()} (batch: ".($migrationBatch + 1).')');
            } else {
                Log::info("Starting migrations for tenant {$tenant->value()} (batch: ".($migrationBatch + 1).')');
            }

            $output = new BufferedOutput;
            $exitCode = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => TenancyConfig::getTenantMigrationsPath(),
                '--force' => true,
            ], $output);

            $result = $output->fetch();

            if ($exitCode !== 0) {
                throw new MigrationException($tenant, "Migration command failed with exit code {$exitCode}: ".$result);
            }

            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->info("Migration completed for tenant {$tenant->value()}: ".$result);
            } else {
                Log::info("Migration completed for tenant {$tenant->value()}: ".$result);
            }

        } catch (\Exception $e) {
            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->error("Migration failed for tenant {$tenant->value()}: ".$e->getMessage());
            } else {
                Log::error("Migration failed for tenant {$tenant->value()}: ".$e->getMessage());
            }

            // Attempt rollback if we have a migration batch
            if ($migrationBatch !== null && $migrationBatch > 0) {
                $this->attemptRollback($tenant, $migrationBatch);
            }

            throw $e;
        } finally {
            $this->resetDatabaseConnection();
        }
    }

    private function validateTenantId(TenantIdentifier $tenant): void
    {
        $tenantValue = $tenant->value();

        // Basic validation - ensure it's not empty and has reasonable length
        if (empty($tenantValue) || strlen($tenantValue) < 3) {
            throw new InvalidTenantIdException($tenantValue, 'must be at least 3 characters long');
        }

        // Check for potentially dangerous characters that could cause SQL injection
        if (preg_match('/[^a-zA-Z0-9\-_]/', $tenantValue)) {
            throw new InvalidTenantIdException($tenantValue, 'contains invalid characters. Only alphanumeric, hyphens, and underscores are allowed');
        }

        // Check if tenant migrations directory exists (config-driven)
        $migrationsPath = base_path(TenancyConfig::getTenantMigrationsPath());
        if (! is_dir($migrationsPath)) {
            throw new ConfigurationException('tenancy.migrations.tenant_path', "Directory not found: {$migrationsPath}. Please create the directory and add migration files.");
        }

        // Check if there are any migration files
        $migrationFiles = glob($migrationsPath.'/*.php');
        if (empty($migrationFiles)) {
            throw new ConfigurationException('tenancy.migrations.tenant_path', "No migration files found in: {$migrationsPath}. Please add migration files to proceed.");
        }
    }

    private function getMigrationStatus(): array
    {
        try {
            $output = new BufferedOutput;
            Artisan::call('migrate:status', [
                '--database' => 'tenant',
                '--path' => TenancyConfig::getTenantMigrationsPath(),
            ], $output);

            $result = $output->fetch();

            // Parse the migration status to get the current batch
            $lines = explode("\n", $result);
            $batch = 0;

            foreach ($lines as $line) {
                if (preg_match('/\|\s*(\d+)\s*\|/', $line, $matches)) {
                    $batch = max($batch, (int) $matches[1]);
                }
            }

            return ['batch' => $batch];
        } catch (\Exception $e) {
            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->warning('Could not determine migration status: '.$e->getMessage());
            } else {
                Log::warning('Could not determine migration status: '.$e->getMessage());
            }

            return ['batch' => 0];
        }
    }

    private function attemptRollback(TenantIdentifier $tenant, int $batch): void
    {
        try {
            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->info("Attempting rollback for tenant {$tenant->value()} (batch: {$batch})");
            } else {
                Log::info("Attempting rollback for tenant {$tenant->value()} (batch: {$batch})");
            }

            $output = new BufferedOutput;
            $exitCode = Artisan::call('migrate:rollback', [
                '--database' => 'tenant',
                '--path' => TenancyConfig::getTenantMigrationsPath(),
                '--step' => 1,
                '--force' => true,
            ], $output);

            $result = $output->fetch();

            if ($exitCode === 0) {
                if ($channel = TenancyConfig::getLogChannel()) {
                    Log::channel($channel)->info("Rollback successful for tenant {$tenant->value()}: ".$result);
                } else {
                    Log::info("Rollback successful for tenant {$tenant->value()}: ".$result);
                }
            } else {
                if ($channel = TenancyConfig::getLogChannel()) {
                    Log::channel($channel)->error("Rollback failed for tenant {$tenant->value()}: ".$result);
                } else {
                    Log::error("Rollback failed for tenant {$tenant->value()}: ".$result);
                }
            }
        } catch (\Exception $e) {
            if ($channel = TenancyConfig::getLogChannel()) {
                Log::channel($channel)->error("Rollback attempt failed for tenant {$tenant->value()}: ".$e->getMessage());
            } else {
                Log::error("Rollback attempt failed for tenant {$tenant->value()}: ".$e->getMessage());
            }
        }
    }

    private function setTenantDatabaseConnection(TenantIdentifier $tenant): void
    {
        $databaseName = $this->buildDatabaseName($tenant);
        $centralConnection = TenancyConfig::getCentralConnection();

        Config::set('database.connections.tenant', [
            'driver' => Config::get("database.connections.{$centralConnection}.driver"),
            'host' => Config::get("database.connections.{$centralConnection}.host"),
            'port' => Config::get("database.connections.{$centralConnection}.port"),
            'database' => $databaseName,
            'username' => Config::get("database.connections.{$centralConnection}.username"),
            'password' => Config::get("database.connections.{$centralConnection}.password"),
            'charset' => Config::get("database.connections.{$centralConnection}.charset"),
            'collation' => Config::get("database.connections.{$centralConnection}.collation"),
        ]);
    }

    private function resetDatabaseConnection(): void
    {
        // Reset to central connection
        $centralConnection = TenancyConfig::getCentralConnection();
        Config::set('database.default', $centralConnection);
    }

    private function getCentralConnection()
    {
        $centralConnection = TenancyConfig::getCentralConnection();

        return app('db')->connection($centralConnection);
    }
}
