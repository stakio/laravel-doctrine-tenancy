<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\EntityConfiguration;

use Illuminate\Support\Facades\Config;
use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantName;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use LaravelDoctrine\Tenancy\Tests\Traits\TenancyTestHelpers;
use PHPUnit\Framework\Attributes\Test;

class CustomTenantEntityTest extends TestCase
{
    use TenancyTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    #[Test]
    public function it_works_with_custom_tenant_entity_that_extends_default()
    {
        // Create a custom tenant entity class
        $customTenantClass = 'CustomTenantEntity';

        // Mock the custom tenant entity
        $this->app->bind($customTenantClass, function () {
            return new class implements TenantEntityInterface
            {
                private $id;

                private $name;

                private $domain;

                private $isActive = true;

                private $customField;

                public function __construct()
                {
                    $this->id = \Ramsey\Uuid\Uuid::uuid4();
                    $this->name = new TenantName('Custom Tenant');
                    $this->domain = new Domain('custom.com');
                    $this->customField = 'custom_value';
                }

                public function getId(): \Ramsey\Uuid\UuidInterface
                {
                    return $this->id;
                }

                public function id(): \Ramsey\Uuid\UuidInterface
                {
                    return $this->id;
                }

                public function name(): TenantName
                {
                    return $this->name;
                }

                public function domain(): Domain
                {
                    return $this->domain;
                }

                public function updateName(TenantName $name): void
                {
                    $this->name = $name;
                }

                public function updateDomain(Domain $domain): void
                {
                    $this->domain = $domain;
                }

                public function activate(): void
                {
                    $this->isActive = true;
                }

                public function deactivate(): void
                {
                    $this->isActive = false;
                }

                public function isActive(): bool
                {
                    return $this->isActive;
                }

                public function getCreatedAt(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable;
                }

                public function getUpdatedAt(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable;
                }

                public function getDeactivatedAt(): ?\DateTimeImmutable
                {
                    return null;
                }

                public function toIdentifier(): \LaravelDoctrine\Tenancy\Contracts\TenantIdentifier
                {
                    return new TenantId($this->id);
                }

                // Custom method
                public function getCustomField(): string
                {
                    return $this->customField;
                }
            };
        });

        // Configure the custom tenant entity
        Config::set('tenancy.tenant_entity', $customTenantClass);

        // Verify configuration is loaded
        $this->assertEquals($customTenantClass, TenancyConfig::getTenantEntityClass());
    }

    #[Test]
    public function it_handles_custom_tenant_entity_with_different_structure()
    {
        // Create a custom tenant entity with different structure
        $customTenantClass = 'DifferentStructureTenant';

        $this->app->bind($customTenantClass, function () {
            return new class implements TenantEntityInterface
            {
                private $uuid;

                private $tenantName;

                private $tenantDomain;

                private $active = true;

                private $metadata = [];

                public function __construct()
                {
                    $this->uuid = \Ramsey\Uuid\Uuid::uuid4();
                    $this->tenantName = new TenantName('Different Structure Tenant');
                    $this->tenantDomain = new Domain('different.com');
                    $this->metadata = ['source' => 'custom', 'version' => '2.0'];
                }

                public function getId(): \Ramsey\Uuid\UuidInterface
                {
                    return $this->uuid;
                }

                public function id(): \Ramsey\Uuid\UuidInterface
                {
                    return $this->uuid;
                }

                public function name(): TenantName
                {
                    return $this->tenantName;
                }

                public function domain(): Domain
                {
                    return $this->tenantDomain;
                }

                public function updateName(TenantName $name): void
                {
                    $this->tenantName = $name;
                }

                public function updateDomain(Domain $domain): void
                {
                    $this->tenantDomain = $domain;
                }

                public function activate(): void
                {
                    $this->active = true;
                }

                public function deactivate(): void
                {
                    $this->active = false;
                }

                public function isActive(): bool
                {
                    return $this->active;
                }

                public function getCreatedAt(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable;
                }

                public function getUpdatedAt(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable;
                }

                public function getDeactivatedAt(): ?\DateTimeImmutable
                {
                    return null;
                }

                public function toIdentifier(): \LaravelDoctrine\Tenancy\Contracts\TenantIdentifier
                {
                    return new TenantId($this->uuid);
                }

                // Custom methods
                public function getMetadata(): array
                {
                    return $this->metadata;
                }

                public function setMetadata(array $metadata): void
                {
                    $this->metadata = $metadata;
                }
            };
        });

        // Configure the custom tenant entity
        Config::set('tenancy.tenant_entity', $customTenantClass);

        // Verify configuration is loaded
        $this->assertEquals($customTenantClass, TenancyConfig::getTenantEntityClass());
    }

    #[Test]
    public function it_falls_back_to_default_tenant_entity_when_custom_not_configured()
    {
        // Reset to default configuration
        Config::set('tenancy.tenant_entity', null);

        // Should fall back to default
        $this->assertEquals(
            \LaravelDoctrine\Tenancy\Domain\Tenant::class,
            TenancyConfig::getTenantEntityClass()
        );
    }
}
