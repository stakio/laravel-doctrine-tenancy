<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

class TenantDatabaseManager
{
    private const TENANT_MIGRATIONS_PATH = 'database/migrations/tenant';

    public function createTenantDatabase(TenantIdentifier $tenant): bool
    {
        try {
            $databaseName = $this->buildDatabaseName($tenant);

            if ($this->databaseExists($databaseName)) {
                Log::info("Database already exists for tenant: {$tenant->value()}");
                return true;
            }

            $this->createDatabase($databaseName);
            $this->runTenantMigrations($tenant);

            Log::info("Successfully created database for tenant: {$tenant->value()}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create database for tenant {$tenant->value()}: " . $e->getMessage());
            return false;
        }
    }

    public function deleteTenantDatabase(TenantIdentifier $tenant): bool
    {
        try {
            $databaseName = $this->buildDatabaseName($tenant);
            $this->dropDatabase($databaseName);

            Log::info("Successfully deleted database for tenant: {$tenant->value()}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete database for tenant {$tenant->value()}: " . $e->getMessage());
            return false;
        }
    }

    public function migrateTenantDatabase(TenantIdentifier $tenant): bool
    {
        try {
            $this->runTenantMigrations($tenant);
            Log::info("Successfully migrated database for tenant: {$tenant->value()}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to migrate database for tenant {$tenant->value()}: " . $e->getMessage());
            return false;
        }
    }

    public function getTenantMigrationStatus(TenantIdentifier $tenant): array
    {
        try {
            $databaseName = $this->buildDatabaseName($tenant);
            $this->setTenantDatabaseConnection($tenant);

            $output = new BufferedOutput;
            Artisan::call('migrate:status', [
                '--database' => 'tenant',
                '--path' => self::TENANT_MIGRATIONS_PATH,
            ], $output);

            $result = $output->fetch();
            $this->resetDatabaseConnection();

            return [
                'status' => 'migrated',
                'migrations' => $this->parseMigrationStatus($result),
                'total_migrations' => substr_count($result, '|'),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function buildDatabaseName(TenantIdentifier $tenant): string
    {
        $prefix = TenancyConfig::getDatabasePrefix();
        $naming = TenancyConfig::getDatabaseNaming();

        return match ($naming['strategy']) {
            'prefix' => $prefix . $tenant->value(),
            'suffix' => $tenant->value() . $naming['separator'] . $prefix,
            default => $prefix . $tenant->value(),
        };
    }

    private function databaseExists(string $databaseName): bool
    {
        $connection = $this->getCentralConnection();
        
        try {
            $result = $connection->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            return !empty($result);
        } catch (\Exception $e) {
            Log::error("Error checking if database exists: " . $e->getMessage());
            return false;
        }
    }

    private function createDatabase(string $databaseName): void
    {
        $connection = $this->getCentralConnection();
        $connection->statement("CREATE DATABASE `{$databaseName}`");
    }

    private function dropDatabase(string $databaseName): void
    {
        $connection = $this->getCentralConnection();
        $connection->statement("DROP DATABASE IF EXISTS `{$databaseName}`");
    }

    private function runTenantMigrations(TenantIdentifier $tenant): void
    {
        $this->setTenantDatabaseConnection($tenant);

        try {
            $output = new BufferedOutput;
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => self::TENANT_MIGRATIONS_PATH,
                '--force' => true,
            ], $output);

            $result = $output->fetch();
            Log::info("Migration result for tenant {$tenant->value()}: " . $result);
        } catch (\Exception $e) {
            Log::error("Failed to run migrations for tenant {$tenant->value()}: " . $e->getMessage());
            throw $e;
        } finally {
            $this->resetDatabaseConnection();
        }
    }

    private function setTenantDatabaseConnection(TenantIdentifier $tenant): void
    {
        $databaseName = $this->buildDatabaseName($tenant);
        $centralConnection = TenancyConfig::getCentralConnection();
        
        Config::set("database.connections.tenant", [
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

    private function parseMigrationStatus(string $result): array
    {
        $lines = explode("\n", $result);
        $migrations = [];
        
        foreach ($lines as $line) {
            if (strpos($line, '|') !== false) {
                $parts = explode('|', $line);
                if (count($parts) >= 2) {
                    $migrations[] = (object) [
                        'migration' => trim($parts[0]),
                        'batch' => (int) trim($parts[1]),
                    ];
                }
            }
        }
        
        return $migrations;
    }
}