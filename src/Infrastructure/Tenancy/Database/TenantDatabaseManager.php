<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use Illuminate\Database\Capsule\Manager as CapsuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

class TenantDatabaseManager
{
    private const TENANT_MIGRATIONS_PATH = 'database/migrations/tenant';

    private const TENANT_SEEDERS_PATH = 'database/seeders/tenant';

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

    public function seedTenantDatabase(TenantIdentifier $tenant): bool
    {
        try {
            $this->runTenantSeeders($tenant);

            Log::info("Successfully seeded database for tenant: {$tenant->value()}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to seed database for tenant {$tenant->value()}: " . $e->getMessage());

            return false;
        }
    }

    public function rollbackTenantMigrations(TenantIdentifier $tenant, int $steps = 1): bool
    {
        try {
            $this->rollbackTenantMigrations($tenant, $steps);

            Log::info("Successfully rolled back {$steps} migration(s) for tenant: {$tenant->value()}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to rollback migrations for tenant {$tenant->value()}: " . $e->getMessage());

            return false;
        }
    }

    public function getTenantMigrationStatus(TenantIdentifier $tenant): array
    {
        try {
            $databaseName = $this->buildDatabaseName($tenant);

            if (!$this->databaseExists($databaseName)) {
                return ['error' => 'Database does not exist'];
            }

            $connection = $this->createTenantConnection($tenant);

            if (!$this->migrationsTableExists($connection)) {
                return ['status' => 'No migrations table found'];
            }

            $migrations = $connection->table('migrations')->get();

            return [
                'status' => 'success',
                'total_migrations' => $migrations->count(),
                'migrations' => $migrations->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get migration status for tenant {$tenant->value()}: " . $e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Build database name for a tenant
     */
    private function buildDatabaseName(TenantIdentifier $tenant): string
    {
        $prefix = $this->getTenantDatabasePrefix();

        return $prefix . $tenant->value();
    }

    /**
     * Get tenant database prefix from config
     */
    private function getTenantDatabasePrefix(): string
    {
        return TenancyConfig::getDatabasePrefix();
    }

    /**
     * Create a temporary database connection for administrative operations
     */
    private function createTempConnection(): CapsuleManager
    {
        $baseConfig = Config::get('database.connections.' . Config::get('database.default'));
        $driver = $baseConfig['driver'];

        $tempConnection = [
            'driver' => $driver,
            'host' => $baseConfig['host'],
            'port' => $baseConfig['port'],
            // For PostgreSQL, connect to maintenance database to run CREATE DATABASE and checks
            'database' => $driver === 'pgsql' ? 'postgres' : null,
            'username' => $baseConfig['username'],
            'password' => $baseConfig['password'],
            'charset' => $baseConfig['charset'] ?? 'utf8mb4',
        ];

        // Propagate SSL/options if present (common for managed PG providers)
        if (!empty($baseConfig['sslmode'])) {
            $tempConnection['sslmode'] = $baseConfig['sslmode'];
        }
        if (!empty($baseConfig['sslrootcert'])) {
            $tempConnection['sslrootcert'] = $baseConfig['sslrootcert'];
        }
        if (!empty($baseConfig['options'])) {
            $tempConnection['options'] = $baseConfig['options'];
        }

        $tempDb = new CapsuleManager;
        $tempDb->addConnection($tempConnection);
        $tempDb->setAsGlobal();
        $tempDb->bootEloquent();

        return $tempDb;
    }

    /**
     * Check if database exists
     */
    private function databaseExists(string $databaseName): bool
    {
        try {
            $tempDb = $this->createTempConnection();
            $driver = Config::get('database.connections.' . Config::get('database.default') . '.driver');

            return $this->checkDatabaseExistsByDriver($tempDb, $driver, $databaseName);
        } catch (\Exception $e) {
            Log::error('Error checking if database exists: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Check database existence based on driver type
     */
    private function checkDatabaseExistsByDriver(CapsuleManager $tempDb, string $driver, string $databaseName): bool
    {
        return match ($driver) {
            'mysql' => $this->checkMySQLDatabaseExists($tempDb, $databaseName),
            'pgsql' => $this->checkPostgreSQLDatabaseExists($tempDb, $databaseName),
            'sqlite' => $this->checkSQLiteDatabaseExists($databaseName),
            default => false,
        };
    }

    /**
     * Check MySQL database existence
     */
    private function checkMySQLDatabaseExists(CapsuleManager $tempDb, string $databaseName): bool
    {
        $result = $tempDb->connection()->select(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$databaseName]
        );

        return !empty($result);
    }

    /**
     * Check PostgreSQL database existence
     */
    private function checkPostgreSQLDatabaseExists(CapsuleManager $tempDb, string $databaseName): bool
    {
        $result = $tempDb->connection()->select(
            'SELECT datname FROM pg_database WHERE datname = ?',
            [$databaseName]
        );

        return !empty($result);
    }

    /**
     * Check SQLite database existence
     */
    private function checkSQLiteDatabaseExists(string $databaseName): bool
    {
        $databasePath = database_path($databaseName . '.sqlite');

        return file_exists($databasePath);
    }

    /**
     * Create database
     */
    private function createDatabase(string $databaseName): void
    {
        try {
            $tempDb = $this->createTempConnection();
            $driver = Config::get('database.connections.' . Config::get('database.default') . '.driver');

            $this->createDatabaseByDriver($tempDb, $driver, $databaseName);
        } catch (\Exception $e) {
            Log::error("Failed to create database {$databaseName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create database based on driver type
     */
    private function createDatabaseByDriver(CapsuleManager $tempDb, string $driver, string $databaseName): void
    {
        match ($driver) {
            'mysql' => $this->createMySQLDatabase($tempDb, $databaseName),
            'pgsql' => $this->createPostgreSQLDatabase($tempDb, $databaseName),
            'sqlite' => $this->createSQLiteDatabase($databaseName),
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Create MySQL database
     */
    private function createMySQLDatabase(CapsuleManager $tempDb, string $databaseName): void
    {
        $tempDb->connection()->statement(
            "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    /**
     * Create PostgreSQL database
     */
    private function createPostgreSQLDatabase(CapsuleManager $tempDb, string $databaseName): void
    {
        $tempDb->connection()->statement("CREATE DATABASE \"{$databaseName}\"");
    }

    /**
     * Create SQLite database
     */
    private function createSQLiteDatabase(string $databaseName): void
    {
        $databasePath = database_path($databaseName . '.sqlite');
        touch($databasePath);
    }

    /**
     * Drop database
     */
    private function dropDatabase(string $databaseName): void
    {
        try {
            $tempDb = $this->createTempConnection();
            $driver = Config::get('database.connections.' . Config::get('database.default') . '.driver');

            $this->dropDatabaseByDriver($tempDb, $driver, $databaseName);
        } catch (\Exception $e) {
            Log::error("Failed to drop database {$databaseName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Drop database based on driver type
     */
    private function dropDatabaseByDriver(CapsuleManager $tempDb, string $driver, string $databaseName): void
    {
        match ($driver) {
            'mysql' => $this->dropMySQLDatabase($tempDb, $databaseName),
            'pgsql' => $this->dropPostgreSQLDatabase($tempDb, $databaseName),
            'sqlite' => $this->dropSQLiteDatabase($databaseName),
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Drop MySQL database
     */
    private function dropMySQLDatabase(CapsuleManager $tempDb, string $databaseName): void
    {
        $tempDb->connection()->statement("DROP DATABASE IF EXISTS `{$databaseName}`");
    }

    /**
     * Drop PostgreSQL database
     */
    private function dropPostgreSQLDatabase(CapsuleManager $tempDb, string $databaseName): void
    {
        $tempDb->connection()->statement("DROP DATABASE IF EXISTS \"{$databaseName}\"");
    }

    /**
     * Drop SQLite database
     */
    private function dropSQLiteDatabase(string $databaseName): void
    {
        $databasePath = database_path($databaseName . '.sqlite');
        if (file_exists($databasePath)) {
            unlink($databasePath);
        }
    }

    private function runTenantMigrations(TenantIdentifier $tenant): void
    {
        $databaseName = $this->buildDatabaseName($tenant);
        $originalDefault = TenancyConfig::getOriginalDatabaseDefault();

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

    private function runTenantSeeders(TenantIdentifier $tenant): void
    {
        $databaseName = $this->buildDatabaseName($tenant);
        $defaultSeeder = TenancyConfig::getDefaultTenantSeeder();

        $this->setTenantDatabaseConnection($tenant);

        try {
            $output = new BufferedOutput;

            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => $this->getTenantSeederClass($tenant),
                '--force' => true,
            ], $output);

            $result = $output->fetch();
            Log::info("Seeding result for tenant {$tenant->value()}: " . $result);

        } catch (\Exception $e) {
            Log::error("Failed to run seeders for tenant {$tenant->value()}: " . $e->getMessage());
            throw $e;
        } finally {
            $this->resetDatabaseConnection();
        }
    }

    private function setTenantDatabaseConnection(TenantIdentifier $tenant): void
    {
        $databaseName = $this->buildDatabaseName($tenant);
        $baseConfig = Config::get('database.connections.' . Config::get('database.default'));

        $tenantConnection = [
            'driver' => $baseConfig['driver'],
            'host' => $baseConfig['host'],
            'port' => $baseConfig['port'],
            'database' => $databaseName,
            'username' => $baseConfig['username'],
            'password' => $baseConfig['password'],
            'charset' => $baseConfig['charset'] ?? 'utf8mb4',
            'collation' => $baseConfig['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => $baseConfig['strict'] ?? true,
            'engine' => $baseConfig['engine'] ?? null,
        ];

        Config::set('database.connections.tenant', $tenantConnection);

        Config::set('database.default', 'tenant');
    }

    private function resetDatabaseConnection(): void
    {
        $originalDefault = TenancyConfig::getOriginalDatabaseDefault();
        Config::set('database.default', $originalDefault);
    }

    private function createTenantConnection(TenantIdentifier $tenant): \Illuminate\Database\Connection
    {
        $databaseName = $this->buildDatabaseName($tenant);
        $baseConfig = Config::get('database.connections.' . Config::get('database.default'));

        $tenantConnection = [
            'driver' => $baseConfig['driver'],
            'host' => $baseConfig['host'],
            'port' => $baseConfig['port'],
            'database' => $databaseName,
            'username' => $baseConfig['username'],
            'password' => $baseConfig['password'],
            'charset' => $baseConfig['charset'] ?? 'utf8mb4',
        ];

        $capsule = new CapsuleManager;
        $capsule->addConnection($tenantConnection);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule->connection();
    }

    private function migrationsTableExists(\Illuminate\Database\Connection $connection): bool
    {
        try {
            return (bool) $connection->getSchemaBuilder()->hasTable('migrations');
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTenantSeederClass(TenantIdentifier $tenant): string
    {
        $defaultSeeder = TenancyConfig::getDefaultTenantSeeder();

        $tenantSeeder = "Tenant{$tenant->value()}Seeder";
        $seederPath = app_path("Database/Seeders/Tenant/{$tenantSeeder}.php");

        if (file_exists($seederPath)) {
            return "Database\\Seeders\\Tenant\\{$tenantSeeder}";
        }

        return "Database\\Seeders\\{$defaultSeeder}";
    }
}
