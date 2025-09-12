<?php

namespace LaravelDoctrine\Tenancy\Domain;

use Doctrine\ORM\Mapping as ORM;
use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;
use LaravelDoctrine\Tenancy\Domain\Events\TenantCreated;
use LaravelDoctrine\Tenancy\Domain\Events\TenantUpdated;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantName;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
#[ORM\HasLifecycleCallbacks]
class Tenant implements TenantEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $domain;

    #[ORM\Column(name: 'deactivated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deactivatedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        UuidInterface $id,
        TenantName $name,
        Domain $domain
    ) {
        $this->id = $id;
        $this->name = $name->value();
        $this->domain = $domain->value();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TenantCreated($this->id, $this->toIdentifier()));
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function id(): UuidInterface
    {
        return $this->getId();
    }

    public function name(): TenantName
    {
        return new TenantName($this->name);
    }

    public function domain(): Domain
    {
        return new Domain($this->domain);
    }

    public function updateName(TenantName $name): void
    {
        $this->name = $name->value();
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new TenantUpdated($this));
    }

    public function updateDomain(Domain $domain): void
    {
        $this->domain = $domain->value();
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new TenantUpdated($this));
    }

    public function activate(): void
    {
        $this->deactivatedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new TenantUpdated($this));
    }

    public function deactivate(): void
    {
        $this->deactivatedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new TenantUpdated($this));
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeactivatedAt(): ?\DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function toIdentifier(): TenantIdentifier
    {
        return new TenantId($this->id);
    }

    private function recordEvent(object $event): void
    {
        // This would typically dispatch events through Laravel's event system
        // For now, we'll leave this as a placeholder
    }
}
