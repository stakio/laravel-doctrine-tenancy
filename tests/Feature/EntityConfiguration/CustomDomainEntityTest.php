<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\EntityConfiguration;

use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use LaravelDoctrine\Tenancy\Contracts\DomainEntityInterface;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class CustomDomainEntityTest extends TestCase
{
    use TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    #[Test]
    public function it_works_with_custom_domain_entity_that_extends_default()
    {
        // Create a custom domain entity class
        $customDomainClass = 'CustomDomainEntity';
        
        // Mock the custom domain entity
        $this->app->bind($customDomainClass, function () {
            return new class implements DomainEntityInterface {
                private $id;
                private $domain;
                private $tenantId;
                private $isActive = true;
                private $customField;

                public function __construct()
                {
                    $this->id = \Ramsey\Uuid\Uuid::uuid4();
                    $this->domain = new Domain('custom-domain.com');
                    $this->tenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
                    $this->customField = 'custom_domain_value';
                }

                public function getId(): \Ramsey\Uuid\UuidInterface { return $this->id; }
                public function id(): \Ramsey\Uuid\UuidInterface { return $this->id; }
                public function domain(): Domain { return $this->domain; }
                public function tenantId(): TenantId { return $this->tenantId; }
                public function isActive(): bool { return $this->isActive; }
                public function isPrimary(): bool { return false; }
                public function setPrimary(bool $primary): void { /* no-op */ }
                public function activate(): void { $this->isActive = true; }
                public function deactivate(): void { $this->isActive = false; }
                public function getCreatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                public function getUpdatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                public function getDeactivatedAt(): ?\DateTimeImmutable { return null; }
                
                // Custom method
                public function getCustomField(): string { return $this->customField; }
            };
        });

        // Configure the custom domain entity
        Config::set('tenancy.domain_entity', $customDomainClass);

        // Verify configuration is loaded
        $this->assertEquals($customDomainClass, TenancyConfig::getDomainEntityClass());
    }

    #[Test]
    public function it_handles_custom_domain_entity_with_different_structure()
    {
        // Create a custom domain entity with different structure
        $customDomainClass = 'DifferentStructureDomain';
        
        $this->app->bind($customDomainClass, function () {
            return new class implements DomainEntityInterface {
                private $uuid;
                private $domainName;
                private $ownerTenantId;
                private $active = true;
                private $priority = 1;

                public function __construct()
                {
                    $this->uuid = \Ramsey\Uuid\Uuid::uuid4();
                    $this->domainName = new Domain('different-domain.com');
                    $this->ownerTenantId = new TenantId(\Ramsey\Uuid\Uuid::uuid4());
                    $this->priority = 1;
                }

                public function getId(): \Ramsey\Uuid\UuidInterface { return $this->uuid; }
                public function id(): \Ramsey\Uuid\UuidInterface { return $this->uuid; }
                public function domain(): Domain { return $this->domainName; }
                public function tenantId(): TenantId { return $this->ownerTenantId; }
                public function isActive(): bool { return $this->active; }
                public function isPrimary(): bool { return false; }
                public function setPrimary(bool $primary): void { /* no-op */ }
                public function activate(): void { $this->active = true; }
                public function deactivate(): void { $this->active = false; }
                public function getCreatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                public function getUpdatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
                public function getDeactivatedAt(): ?\DateTimeImmutable { return null; }
                
                // Custom methods
                public function getPriority(): int { return $this->priority; }
                public function setPriority(int $priority): void { $this->priority = $priority; }
            };
        });

        // Configure the custom domain entity
        Config::set('tenancy.domain_entity', $customDomainClass);

        // Verify configuration is loaded
        $this->assertEquals($customDomainClass, TenancyConfig::getDomainEntityClass());
    }

    #[Test]
    public function it_falls_back_to_default_domain_entity_when_custom_not_configured()
    {
        // Reset to default configuration
        Config::set('tenancy.domain_entity', null);

        // Should fall back to default
        $this->assertEquals(
            \LaravelDoctrine\Tenancy\Domain\DomainEntity::class,
            TenancyConfig::getDomainEntityClass()
        );
    }

    #[Test]
    public function it_handles_mixed_custom_entities()
    {
        // Configure both custom entities
        $customTenantClass = 'CustomTenantEntity';
        $customDomainClass = 'CustomDomainEntity';
        
        Config::set('tenancy.tenant_entity', $customTenantClass);
        Config::set('tenancy.domain_entity', $customDomainClass);

        // Verify both configurations are loaded
        $this->assertEquals($customTenantClass, TenancyConfig::getTenantEntityClass());
        $this->assertEquals($customDomainClass, TenancyConfig::getDomainEntityClass());
    }
}
