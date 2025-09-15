<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\DatabaseConnectionManager;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\EntityRouting\EntityRouter;

/**
 * Smart Entity Manager
 *
 * A sophisticated facade EntityManager that automatically routes operations to the
 * appropriate EntityManager (central or tenant) based on entity type and tenant context.
 *
 * @internal This class is not part of the public API and may change without notice.
 *
 * This class implements the EntityManagerInterface and provides transparent routing
 * of database operations, ensuring that:
 * - Central entities (like Tenant, DomainEntity) are always routed to the central database
 * - Tenant entities are routed to the current tenant's database when a tenant context exists
 * - Database connections are automatically managed and switched as needed
 * - Transaction handling works across both central and tenant databases
 *
 * Key Features:
 * - Automatic entity routing based on configuration
 * - Intelligent database connection management
 * - Cross-database transaction support
 * - Performance monitoring and logging
 * - Error handling and recovery
 *
 * @author Laravel Doctrine Tenancy Team
 *
 * @since 1.0.0
 *
 * @implements EntityManagerInterface
 */
class SmartEntityManager implements EntityManagerInterface
{
    private EntityManager $centralEntityManager;

    private EntityManager $tenantEntityManager;

    private EntityRouter $entityRouter;

    private DatabaseConnectionManager $connectionManager;

    public function __construct(
        private TenantContextInterface $tenantContext
    ) {
        $this->centralEntityManager = app('doctrine.central.entity_manager');
        $this->tenantEntityManager = app('doctrine.tenant.entity_manager');
        $this->entityRouter = new EntityRouter($tenantContext);
        $this->connectionManager = new DatabaseConnectionManager($tenantContext);
    }

    /**
     * Get the appropriate EntityManager based on class name.
     */
    private function getEntityManagerForClassName(string $className): EntityManager
    {
        $entityManager = $this->entityRouter->getEntityManagerForClass(
            $className,
            $this->centralEntityManager,
            $this->tenantEntityManager
        );

        // Ensure tenant connection if needed
        if ($this->entityRouter->isTenantEntity($className)) {
            $this->connectionManager->ensureTenantConnection();
        }

        return $entityManager;
    }

    /**
     * Get the appropriate EntityManager based on entity instance.
     */
    private function getEntityManagerForEntity($entity): EntityManager
    {
        return $this->getEntityManagerForClassName(get_class($entity));
    }

    /**
     * Reset tenant connection to central database.
     */
    public function resetConnection(): void
    {
        $this->connectionManager->resetToCentral();
    }

    /**
     * Execute operation on tenant EntityManager if tenant context exists.
     */
    private function executeOnTenantIfAvailable(callable $operation, $defaultValue = null)
    {
        if ($this->tenantContext->hasCurrentTenant()) {
            $this->connectionManager->ensureTenantConnection();

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
            $this->connectionManager->ensureTenantConnection();
            $tenantOperation($this->tenantEntityManager);
        }
    }

    public function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->executeOnTenantIfAvailable(
            fn ($em) => $em->getConnection(),
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
            fn ($em) => $em->getUnitOfWork()->size() > 0 ? $em->flush() : null,
            fn ($em) => $em->getUnitOfWork()->size() > 0 ? $em->flush() : null
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
            fn ($em) => $em->getUnitOfWork()->size() > 0 ? $em->beginTransaction() : null,
            fn ($em) => $em->getUnitOfWork()->size() > 0 ? $em->beginTransaction() : null
        );
    }

    public function commit(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn ($em) => $em->getConnection()->isTransactionActive() ? $em->commit() : null,
            fn ($em) => $em->getConnection()->isTransactionActive() ? $em->commit() : null
        );
    }

    public function rollback(): void
    {
        $this->executeOnBothIfTenantAvailable(
            fn ($em) => $em->getConnection()->isTransactionActive() ? $em->rollback() : null,
            fn ($em) => $em->getConnection()->isTransactionActive() ? $em->rollback() : null
        );
    }

    public function wrapInTransaction(callable $func): mixed
    {
        $startTime = microtime(true);

        try {
            $this->centralEntityManager->beginTransaction();

            if ($this->tenantContext->hasCurrentTenant()) {
                $this->connectionManager->ensureTenantConnection();
                $this->tenantEntityManager->beginTransaction();
            }

            $result = $func($this->centralEntityManager, $this->tenantEntityManager);

            $this->centralEntityManager->commit();
            if ($this->tenantContext->hasCurrentTenant()) {
                $this->tenantEntityManager->commit();
            }

            return $result;
        } catch (\Exception $e) {
            $this->centralEntityManager->rollback();
            if ($this->tenantContext->hasCurrentTenant()) {
                $this->tenantEntityManager->rollback();
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
            fn ($em) => $em->getCache(),
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
            fn ($em) => $em->createQuery($dql),
            $this->centralEntityManager->createQuery($dql)
        );
    }

    public function createQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->executeOnTenantIfAvailable(
            fn ($em) => $em->createQueryBuilder(),
            $this->centralEntityManager->createQueryBuilder()
        );
    }

    public function createNativeQuery($sql, \Doctrine\ORM\Query\ResultSetMapping $rsm): \Doctrine\ORM\NativeQuery
    {
        return $this->executeOnTenantIfAvailable(
            fn ($em) => $em->createNativeQuery($sql, $rsm),
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
            fn ($em) => $em->clear(),
            fn ($em) => $em->clear()
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
            fn ($em) => $em->close(),
            fn ($em) => $em->close()
        );
    }

    public function __call($method, $arguments)
    {

        return $this->centralEntityManager->$method(...$arguments);
    }
}
