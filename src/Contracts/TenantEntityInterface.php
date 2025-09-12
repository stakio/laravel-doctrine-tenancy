<?php

namespace LaravelDoctrine\Tenancy\Contracts;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantName;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
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
     * Get the tenant's unique identifier (alias for getId).
     */
    public function id(): UuidInterface;

    /**
     * Get the tenant's name as a value object.
     */
    public function name(): TenantName;

    /**
     * Get the tenant's domain as a value object.
     */
    public function domain(): Domain;

    /**
     * Update the tenant's name.
     */
    public function updateName(TenantName $name): void;

    /**
     * Update the tenant's domain.
     */
    public function updateDomain(Domain $domain): void;

    /**
     * Check if the tenant is active (not deactivated).
     */
    public function isActive(): bool;

    /**
     * Deactivate the tenant.
     */
    public function deactivate(): void;

    /**
     * Activate the tenant.
     */
    public function activate(): void;

    /**
     * Get the tenant's creation timestamp.
     */
    public function getCreatedAt(): \DateTimeImmutable;

    /**
     * Get the tenant's last update timestamp.
     */
    public function getUpdatedAt(): \DateTimeImmutable;

    /**
     * Get the tenant's deactivation timestamp (if deactivated).
     */
    public function getDeactivatedAt(): ?\DateTimeImmutable;

    /**
     * Convert the tenant to a TenantIdentifier for context operations.
     */
    public function toIdentifier(): TenantIdentifier;
}
