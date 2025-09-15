<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\Configuration;

use Illuminate\Support\Facades\Config;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\TenancyConfig;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConfigurationTest extends TestCase
{
    #[Test]
    public function it_loads_default_configuration()
    {
        $this->assertTrue(Config::has('tenancy'));
        $this->assertTrue(Config::get('tenancy.enabled'));
        $this->assertEquals('X-Tenant-ID', Config::get('tenancy.identification.header_name'));
    }

    #[Test]
    public function it_has_correct_default_entity_classes()
    {
        $this->assertEquals(
            \LaravelDoctrine\Tenancy\Domain\Tenant::class,
            TenancyConfig::getTenantEntityClass()
        );

        $this->assertEquals(
            \LaravelDoctrine\Tenancy\Domain\DomainEntity::class,
            TenancyConfig::getDomainEntityClass()
        );
    }

    #[Test]
    public function it_has_correct_default_resolution_header()
    {
        $this->assertEquals('X-Tenant-ID', TenancyConfig::getResolutionHeader());
    }

    #[Test]
    public function it_has_correct_excluded_subdomains()
    {
        $excluded = TenancyConfig::getExcludedSubdomains();
        $expected = ['www', 'api', 'admin'];

        $this->assertEquals($expected, $excluded);
    }

    #[Test]
    public function it_allows_custom_tenant_entity_configuration()
    {
        $customClass = 'CustomTenantClass';
        Config::set('tenancy.tenant_entity', $customClass);

        $this->assertEquals($customClass, TenancyConfig::getTenantEntityClass());
    }

    #[Test]
    public function it_allows_custom_domain_entity_configuration()
    {
        $customClass = 'CustomDomainClass';
        Config::set('tenancy.domain_entity', $customClass);

        $this->assertEquals($customClass, TenancyConfig::getDomainEntityClass());
    }

    #[Test]
    public function it_has_correct_entity_routing_configuration()
    {
        $routing = TenancyConfig::getEntityRouting();

        $this->assertArrayHasKey('central', $routing);
        $this->assertArrayHasKey('tenant', $routing);
        $this->assertIsArray($routing['central']);
        $this->assertIsArray($routing['tenant']);
    }

    #[Test]
    public function it_includes_tenant_and_domain_entities_in_central_routing()
    {
        $routing = TenancyConfig::getEntityRouting();

        $this->assertContains(
            \LaravelDoctrine\Tenancy\Domain\Tenant::class,
            $routing['central']
        );

        $this->assertContains(
            \LaravelDoctrine\Tenancy\Domain\DomainEntity::class,
            $routing['central']
        );
    }
}
