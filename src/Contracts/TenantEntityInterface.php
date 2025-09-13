<?php

namespace LaravelDoctrine\Tenancy\Contracts;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantName;
use Ramsey\Uuid\UuidInterface;

/**
 * Interface that all tenant entities must implement.
 * This allows users to use their own tenant entity classes while maintaining compatibility.
 */
interface TenantEntityInterface
{
    /**
     * Get the tenant's unique identifier.
     */
    public function getId(): UuidInterface;

    /**
     * Get the tenant's name as a value object.
     */
    public function name(): TenantName;

    /**
     * Check if the tenant is active (not deactivated).
     */
    public function isActive(): bool;

    /**
     * Convert the tenant to a TenantIdentifier for context operations.
     */
    public function toIdentifier(): TenantIdentifier;
}
