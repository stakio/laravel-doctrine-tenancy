<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy;

use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * A Doctrine DBAL Connection wrapper that handles runtime database switching
 * for multi-tenant applications (MySQL: USE db; Postgres: reconnect).
 */
class TenantConnectionWrapper extends Connection
{
    private ?string $currentTenantId = null;

    private ?string $currentDatabase = null;

    private array $baseConnectionParams;

    private LoggerInterface $logger;

    public function __construct(
        array $connectionParams,
        private string $driverName
    ) {
        $this->baseConnectionParams = $connectionParams;
        $this->logger = app()->bound(LoggerInterface::class) ? app(LoggerInterface::class) : new NullLogger;

        // Create the driver instance based on driver name
        $driver = match ($driverName) {
            'pdo_mysql' => new \Doctrine\DBAL\Driver\PDO\MySQL\Driver,
            'pdo_pgsql' => new \Doctrine\DBAL\Driver\PDO\PgSQL\Driver,
            'pdo_sqlite' => new \Doctrine\DBAL\Driver\PDO\SQLite\Driver,
            default => throw new \InvalidArgumentException("Unsupported driver: {$driverName}")
        };

        // Call parent constructor with params and driver
        parent::__construct($connectionParams, $driver);
    }

    /**
     * Switch to a specific tenant database at runtime.
     */
    public function switchToTenant(TenantIdentifier $tenant): void
    {
        $tenantId = $tenant->value();
        if ($this->currentTenantId === $tenantId) {
            return;
        }

        try {
            $this->switchDatabase($tenantId);
            $this->currentTenantId = $tenantId;
            $this->currentDatabase = $this->getDatabase();
            $this->logger->info('Switched to tenant database', [
                'tenant_id' => $tenantId,
                'database' => $this->currentDatabase,
                'driver' => $this->driverName,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to switch to tenant database', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Switch back to the central database.
     */
    public function switchToCentral(): void
    {
        if ($this->currentTenantId === null) {
            return;
        }

        try {
            $this->switchDatabase(null);
            $this->currentTenantId = null;
            $this->currentDatabase = $this->getDatabase();
            $this->logger->info('Switched to central database', [
                'database' => $this->currentDatabase,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to switch to central database', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the current tenant ID if connected to a tenant database.
     */
    public function getCurrentTenantId(): ?string
    {
        return $this->currentTenantId;
    }

    /**
     * Check if currently connected to a tenant database.
     */
    public function isConnectedToTenant(): bool
    {
        return $this->currentTenantId !== null;
    }

    /**
     * Execute database switch based on driver.
     */
    private function switchDatabase(?string $tenantId): void
    {
        $databaseName = $this->getTargetDatabaseName($tenantId);
        if ($databaseName === $this->currentDatabase) {
            return;
        }

        if ($this->driverName === 'pdo_mysql') {
            $this->executeStatement("USE `$databaseName`");
        } elseif ($this->driverName === 'pdo_pgsql') {
            $this->reconnect($this->getTargetConnectionParams($tenantId));
        } else {
            throw new \RuntimeException("Database switching not supported for driver: {$this->driverName}");
        }
        $this->currentDatabase = $databaseName;
    }

    /**
     * Get the target database name for switching.
     */
    private function getTargetDatabaseName(?string $tenantId): string
    {
        if ($tenantId === null) {
            return $this->baseConnectionParams['dbname'];
        }

        try {
            return TenancyConfig::getDatabasePrefix() . $tenantId;
        } catch (\Exception $e) {
            return 'tenant-' . $tenantId;
        }
    }

    /**
     * Get connection parameters for the target database.
     */
    private function getTargetConnectionParams(?string $tenantId): array
    {
        $params = $this->baseConnectionParams;
        if ($tenantId !== null) {
            $params['dbname'] = $this->getTargetDatabaseName($tenantId);
        }

        return $params;
    }

    /**
     * Reconnect with new connection parameters (for Postgres).
     */
    private function reconnect(array $connectionParams): void
    {
        // Fully close the current connection
        if ($this->isConnected()) {
            $this->close();
        }

        // Update params and reinitialize connection
        $this->_params = $connectionParams;
        $this->_conn = $this->getDriver()->connect($connectionParams);
        $this->_isConnected = true;
    }

    /**
     * Override executeStatement to ensure correct database.
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        $this->ensureConnection();

        return parent::executeStatement($sql, $params, $types);
    }

    /**
     * Override executeQuery to ensure correct database.
     */
    public function executeQuery(string $sql, array $params = [], array $types = [], ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null): \Doctrine\DBAL\Result
    {
        $this->ensureConnection();

        return parent::executeQuery($sql, $params, $types, $qcp);
    }

    /**
     * Ensure connection is established and on correct database.
     */
    private function ensureConnection(): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }
        // Validate current database
        $expectedDb = $this->currentDatabase ?? $this->baseConnectionParams['dbname'];
        $currentDb = $this->getDatabase();
        if ($currentDb !== $expectedDb) {
            $this->switchDatabase($this->currentTenantId);
        }
    }

    /**
     * Get current database name.
     */
    public function getDatabase(): string
    {
        try {
            if ($this->driverName === 'pdo_mysql') {
                $result = parent::executeQuery('SELECT DATABASE() as db')->fetchAssociative();

                return $result['db'] ?? 'unknown';
            } elseif ($this->driverName === 'pdo_pgsql') {
                $result = parent::executeQuery('SELECT current_database() as db')->fetchAssociative();

                return $result['db'] ?? 'unknown';
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not determine current database', ['error' => $e->getMessage()]);
        }

        return $this->baseConnectionParams['dbname'] ?? 'unknown';
    }
}
