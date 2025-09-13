<?php

namespace LaravelDoctrine\Tenancy\Domain;

use LaravelDoctrine\Tenancy\Contracts\DomainEntityInterface;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_domains')]
class DomainEntity implements DomainEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $domain;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $tenantId;

    #[ORM\Column(type: 'boolean')]
    private bool $isPrimary = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deactivatedAt = null;

    public function __construct(
        UuidInterface $id,
        Domain $domain,
        TenantId $tenantId,
        bool $isPrimary = false,
        bool $isActive = true
    ) {
        $this->id = $id;
        $this->domain = $domain->value();
        $this->tenantId = $tenantId->value();
        $this->isPrimary = $isPrimary;
        $this->isActive = $isActive;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function domain(): Domain
    {
        return new Domain($this->domain);
    }

    public function tenantId(): TenantId
    {
        return new TenantId($this->tenantId);
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
