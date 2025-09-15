<?php

namespace LaravelDoctrine\Tenancy\Infrastructure\Tenancy\EntityRouting;

use Doctrine\ORM\EntityManager;
use LaravelDoctrine\Tenancy\Contracts\TenantContextInterface;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\TenancyConfigurationException;

/**
 * Entity Router
 *
 * Handles routing of entity operations to the appropriate EntityManager
 * (central or tenant) based on entity configuration and tenant context.
 *
 * @internal This class is not part of the public API and may change without notice.
 *
 * This class provides a clean separation of concerns for entity routing logic,
 * making it easier to test and maintain the routing behavior.
 *
 * @author Laravel Doctrine Tenancy Team
 *
 * @since 1.2.0
 */
class EntityRouter
{
    /**
     * @var array List of entities that should be routed to the central database
     */
    private array $centralEntities;

    /**
     * @var array List of entities that should be routed to tenant databases
     */
    private array $tenantEntities;

    /**
     * Constructor.
     *
     * @param  TenantContextInterface  $tenantContext  The tenant context service
     */
    public function __construct(
        private TenantContextInterface $tenantContext
    ) {
        $this->loadEntityRouting();
    }

    /**
     * Load entity routing configuration from the tenancy config.
     *
     * This method loads the entity routing configuration and caches it
     * for performance. The configuration determines which entities
     * should be routed to central vs tenant databases.
     */
    private function loadEntityRouting(): void
    {
        $routing = \LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig::getEntityRouting();
        $this->centralEntities = $routing['central'] ?? [];
        $this->tenantEntities = $routing['tenant'] ?? [];
    }

    /**
     * Get the appropriate EntityManager for the given entity class.
     */
    public function getEntityManagerForClass(string $className, EntityManager $centralEm, EntityManager $tenantEm): EntityManager
    {
        if (in_array($className, $this->tenantEntities)) {
            return $this->getTenantEntityManager($className, $tenantEm);
        }

        if (in_array($className, $this->centralEntities)) {
            return $centralEm;
        }

        // Default to central for unknown entities
        return $centralEm;
    }

    /**
     * Get the appropriate EntityManager for the given entity instance.
     */
    public function getEntityManagerForEntity($entity, EntityManager $centralEm, EntityManager $tenantEm): EntityManager
    {
        return $this->getEntityManagerForClass(get_class($entity), $centralEm, $tenantEm);
    }

    /**
     * Check if an entity class is a tenant entity.
     */
    public function isTenantEntity(string $className): bool
    {
        return in_array($className, $this->tenantEntities);
    }

    /**
     * Check if an entity class is a central entity.
     */
    public function isCentralEntity(string $className): bool
    {
        return in_array($className, $this->centralEntities);
    }

    /**
     * Get tenant EntityManager with validation.
     */
    private function getTenantEntityManager(string $className, EntityManager $tenantEm): EntityManager
    {
        if (! $this->tenantContext->hasCurrentTenant()) {
            throw TenancyConfigurationException::invalidRouting(
                "No tenant context available for tenant entity: {$className}"
            );
        }

        return $tenantEm;
    }

    /**
     * Reload entity routing configuration.
     */
    public function reloadConfiguration(): void
    {
        $this->loadEntityRouting();
    }
}
