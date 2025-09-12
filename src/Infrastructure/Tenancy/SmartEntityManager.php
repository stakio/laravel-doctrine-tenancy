<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy;

use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenantException;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Facades\Log;

/**
 * A simplified facade EntityManager that routes operations to central or tenant EntityManager
 * based on entity type, using singletons from DoctrineServiceProvider.
 */
class SmartEntityManager implements EntityManagerInterface
{
    private EntityManager $centralEntityManager;

    private EntityManager $tenantEntityManager;

    public function __construct(
        private TenantContextInterface $tenantContext
    ) {
        $this->centralEntityManager = app('doctrine.central.entity_manager');
        $this->tenantEntityManager = app('doctrine.tenant.entity_manager');
    }

    /**
     * Get the appropriate EntityManager based on class name.
     */
    private function getEntityManagerForClassName(string $className): EntityManager
    {
        $routing = \LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig::getEntityRouting();
        $centralEntities = $routing['central'] ?? [];
        $tenantEntities = $routing['tenant'] ?? [];

        if (in_array($className, $tenantEntities)) {
            if (!$this->tenantContext->hasCurrentTenant()) {
                throw new \RuntimeException('No tenant context for entity: ' . $className);
            }
            $this->ensureTenantDatabaseConnection();

            return $this->tenantEntityManager;
        }

        if (!in_array($className, $centralEntities)) {
            Log::warning("Unknown entity {$className} not in tenancy.entity_routing; defaulting to central EM");
        }

        return $this->centralEntityManager;
    }

    /**
     * Get the appropriate EntityManager based on entity instance.
     */
    private function getEntityManagerForEntity($entity): EntityManager
    {
        return $this->getEntityManagerForClassName(get_class($entity));
    }

    /**
     * Ensure tenant database connection is established with auto-creation if needed.
     */
    private function ensureTenantDatabaseConnection(): void
    {
        try {
            $this->tenantEntityManager->getConnection()->switchToTenant($this->tenantContext->getCurrentTenant());
            // TenantSwitched::dispatch($this->tenantContext->getCurrentTenant());
        } catch (DBALException $e) {
            if (str_contains($e->getMessage(), 'Unknown database') && config('tenancy.database.auto_create')) {
                app(\LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager::class)
                    ->createTenantDatabase($this->tenantContext->getCurrentTenant());
                $this->tenantEntityManager->getConnection()->switchToTenant($this->tenantContext->getCurrentTenant());
                // TenantSwitched::dispatch($this->tenantContext->getCurrentTenant());
            } else {
                Log::error('Failed to switch to tenant DB', [
                    'error' => $e->getMessage(),
                    'tenant_id' => $this->tenantContext->getCurrentTenant()->value(),
                ]);
                throw new TenantException('Tenant database not found.');
            }
        }
    }

    /**
     * Reset tenant connection to central database.
     */
    public function resetConnection(): void
    {
        if ($this->tenantContext->hasCurrentTenant()) {
            $this->tenantEntityManager->getConnection()->switchToCentral();
        }
    }

    /**
     * Execute operation on tenant EntityManager if tenant context exists.
     */
    private function executeOnTenantIfAvailable(callable $operation, $defaultValue = null)
    {
        if ($this->tenantContext->hasCurrentTenant()) {
            $this->ensureTenantDatabaseConnection();

            return $operation($this->tenantEntityManager);
        }

        return $defaultValue;
    }

    /**
     * Execute operation on both EntityManagers if tenant context exists.
     */
    private function executeOnBothIfTenantAvailable(callable $centralOperation, callable $tenantOperation): void
    {
        $centralOperation($this->centralEntityManager);
        if ($this->tenantContext->hasCurrentTenant()) {
            $this->ensureTenantDatabaseConnection();
            $tenantOperation($this->tenantEntityManager);
        }
    }

    public function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->executeOnTenantIfAvailable(
            fn($em) => $em->getConnection(),
            $this->centralEntityManager->getConnection()
        );
    }

    public function find(string $className, mixed $id, $lockMode = null, $lockVersion = null): ?object
    {
        return $this->getEntityManagerForClassName($className)->find($className, $id, $lockMode, $lockVersion);
    }

    public function persist($entity): void
    {
        $this->getEntityManagerForEntity($entity)->persist($entity);
    }

    public function remove($entity): void
    {
        $this->getEntityManagerForEntity($entity)->remove($entity);
    }

    public function flush(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn($em) => $em->getUnitOfWork()->size() > 0 ? $em->flush() : null,
            fn($em) => $em->getUnitOfWork()->size() > 0 ? $em->flush() : null
        );
    }

    public function getRepository(string $className): \Doctrine\ORM\EntityRepository
    {
        return $this->getEntityManagerForClassName($className)->getRepository($className);
    }

    public function getClassMetadata(string $className): \Doctrine\ORM\Mapping\ClassMetadata
    {
        return $this->getEntityManagerForClassName($className)->getClassMetadata($className);
    }

    public function beginTransaction(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn($em) => $em->getUnitOfWork()->size() > 0 ? $em->beginTransaction() : null,
            fn($em) => $em->getUnitOfWork()->size() > 0 ? $em->beginTransaction() : null
        );
    }

    public function commit(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn($em) => $em->getConnection()->isTransactionActive() ? $em->commit() : null,
            fn($em) => $em->getConnection()->isTransactionActive() ? $em->commit() : null
        );
    }

    public function rollback(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn($em) => $em->getConnection()->isTransactionActive() ? $em->rollback() : null,
            fn($em) => $em->getConnection()->isTransactionActive() ? $em->rollback() : null
        );
    }

    public function wrapInTransaction(callable $func): mixed
    {
        $result = null;
        $centralActive = $this->centralEntityManager->getUnitOfWork()->size() > 0;
        if ($centralActive) {
            $this->centralEntityManager->beginTransaction();
        }

        try {
            if ($this->tenantContext->hasCurrentTenant() && $this->tenantEntityManager->getUnitOfWork()->size() > 0) {
                $this->ensureTenantDatabaseConnection();
                $this->tenantEntityManager->beginTransaction();
                try {
                    $result = $func($this->centralEntityManager, $this->tenantEntityManager);
                    $this->tenantEntityManager->commit();
                } catch (\Exception $e) {
                    $this->tenantEntityManager->rollback();
                    throw $e;
                }
            } else {
                $result = $func($this->centralEntityManager, null);
            }

            if ($centralActive) {
                $this->centralEntityManager->commit();
            }

            return $result;
        } catch (\Exception $e) {
            if ($centralActive) {
                $this->centralEntityManager->rollback();
            }
            throw $e;
        } finally {
            $this->resetConnection();
        }
    }

    public function getConfiguration(): \Doctrine\ORM\Configuration
    {
        return $this->centralEntityManager->getConfiguration();
    }

    public function getEventManager(): \Doctrine\Common\EventManager
    {
        return $this->centralEntityManager->getEventManager();
    }

    public function getMetadataFactory(): \Doctrine\ORM\Mapping\ClassMetadataFactory
    {
        return $this->centralEntityManager->getMetadataFactory();
    }

    public function getFilters(): \Doctrine\ORM\Query\FilterCollection
    {
        return $this->centralEntityManager->getFilters();
    }

    public function isFiltersStateClean(): bool
    {
        return $this->centralEntityManager->isFiltersStateClean();
    }

    public function hasFilters(): bool
    {
        return $this->centralEntityManager->hasFilters();
    }

    public function getCache(): ?\Doctrine\ORM\Cache
    {
        return $this->executeOnTenantIfAvailable(
            fn($em) => $em->getCache(),
            $this->centralEntityManager->getCache()
        );
    }

    public function isOpen(): bool
    {
        return $this->centralEntityManager->isOpen() ||
            ($this->tenantContext->hasCurrentTenant() && $this->tenantEntityManager->isOpen());
    }

    public function getUnitOfWork(): \Doctrine\ORM\UnitOfWork
    {
        return $this->centralEntityManager->getUnitOfWork();
    }

    public function newHydrator($hydrationMode): \Doctrine\ORM\Internal\Hydration\AbstractHydrator
    {
        return $this->centralEntityManager->newHydrator($hydrationMode);
    }

    public function getProxyFactory(): \Doctrine\ORM\Proxy\ProxyFactory
    {
        return $this->centralEntityManager->getProxyFactory();
    }

    public function createQuery($dql = ''): \Doctrine\ORM\Query
    {
        return $this->executeOnTenantIfAvailable(
            fn($em) => $em->createQuery($dql),
            $this->centralEntityManager->createQuery($dql)
        );
    }

    public function createQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->executeOnTenantIfAvailable(
            fn($em) => $em->createQueryBuilder(),
            $this->centralEntityManager->createQueryBuilder()
        );
    }

    public function createNativeQuery($sql, \Doctrine\ORM\Query\ResultSetMapping $rsm): \Doctrine\ORM\NativeQuery
    {
        return $this->executeOnTenantIfAvailable(
            fn($em) => $em->createNativeQuery($sql, $rsm),
            $this->centralEntityManager->createNativeQuery($sql, $rsm)
        );
    }

    public function merge($entity): object
    {
        $this->persist($entity);

        return $entity;
    }

    public function getReference($className, $id): object
    {
        return $this->getEntityManagerForClassName($className)->getReference($className, $id);
    }

    public function getPartialReference($className, $identifier): object
    {
        return $this->getReference($className, $identifier);
    }

    public function createNamedQuery($name): \Doctrine\ORM\Query
    {
        return $this->createQuery($name);
    }

    public function copy($entity, $deep = false): object
    {
        return $entity;
    }

    public function lock($entity, $lockMode, $lockVersion = null): void
    {
        $this->getEntityManagerForEntity($entity)->lock($entity, $lockMode, $lockVersion);
    }

    public function refresh($entity, $lockMode = null): void
    {
        $this->getEntityManagerForEntity($entity)->refresh($entity, $lockMode);
    }

    public function detach($entity): void
    {
        $this->getEntityManagerForEntity($entity)->detach($entity);
    }

    public function clear(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn($em) => $em->clear(),
            fn($em) => $em->clear()
        );
    }

    public function contains($entity): bool
    {
        return $this->getEntityManagerForEntity($entity)->contains($entity);
    }

    public function initializeObject($obj): void
    {
        $this->getEntityManagerForEntity($obj)->initializeObject($obj);
    }

    public function isUninitializedObject(mixed $value): bool
    {
        return $this->centralEntityManager->isUninitializedObject($value);
    }

    public function getExpressionBuilder(): \Doctrine\ORM\Query\Expr
    {
        return $this->centralEntityManager->getExpressionBuilder();
    }

    public function close(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn($em) => $em->close(),
            fn($em) => $em->close()
        );
    }

    public function __call($method, $arguments)
    {
        Log::warning("Unknown method {$method} called on SmartEntityManager; defaulting to central EM");

        return $this->centralEntityManager->$method(...$arguments);
    }
}
