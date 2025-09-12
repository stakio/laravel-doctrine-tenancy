<?php

namespace LaravelDoctrine\Tenancy\Contracts;

use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

interface DomainEntityInterface
{
    public function getId(): UuidInterface;
    public function id(): UuidInterface;
    public function domain(): Domain;
    public function tenantId(): TenantId;
    public function isPrimary(): bool;
    public function setPrimary(bool $primary): void;
    public function isActive(): bool;
    public function activate(): void;
    public function deactivate(): void;
    public function getCreatedAt(): \DateTimeImmutable;
    public function getUpdatedAt(): \DateTimeImmutable;
    public function getDeactivatedAt(): ?\DateTimeImmutable;
}
