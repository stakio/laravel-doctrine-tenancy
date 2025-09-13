<?php

namespace LaravelDoctrine\Tenancy\Contracts;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

interface DomainEntityInterface
{
    /**
     * Get the domain's unique identifier.
     */
    public function getId(): UuidInterface;

    /**
     * Get the domain value object.
     */
    public function domain(): Domain;

    /**
     * Get the associated tenant ID.
     */
    public function tenantId(): TenantId;

    /**
     * Check if this is the primary domain for the tenant.
     */
    public function isPrimary(): bool;

    /**
     * Check if the domain is active.
     */
    public function isActive(): bool;
}
