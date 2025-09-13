<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\Commands;

use LaravelDoctrine\Tenancy\Console\Commands\TenantDatabaseCommand;
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Database\TenantDatabaseManager;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;
use LaravelDoctrine\Tenancy\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class TenantDatabaseCommandTest extends TestCase
{
    private TenantDatabaseManager&MockObject $databaseManager;
    private TenantDatabaseCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->databaseManager = $this->createMock(TenantDatabaseManager::class);
        $this->command = new TenantDatabaseCommand();
        
        // Register the command
        $this->app->instance(TenantDatabaseManager::class, $this->databaseManager);
    }

    public function test_create_action_creates_tenant_database()
    {
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';
        $tenantIdObject = TenantId::fromString($tenantId);

        $this->databaseManager
            ->expects($this->once())
            ->method('createTenantDatabase')
            ->with($this->callback(function ($id) use ($tenantIdObject) {
                return $id->value()->toString() === $tenantIdObject->value()->toString();
            }));

        $this->artisan('tenant:database', [
            'action' => 'create',
            'tenant-id' => $tenantId,
        ])
        ->expectsOutput("Creating database for tenant: {$tenantId}")
        ->expectsOutput('✅ Tenant database created successfully')
        ->assertExitCode(0);
    }

    public function test_delete_action_deletes_tenant_database_when_confirmed()
    {
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';
        $tenantIdObject = TenantId::fromString($tenantId);

        $this->databaseManager
            ->expects($this->once())
            ->method('deleteTenantDatabase')
            ->with($this->callback(function ($id) use ($tenantIdObject) {
                return $id->value()->toString() === $tenantIdObject->value()->toString();
            }));

        $this->artisan('tenant:database', [
            'action' => 'delete',
            'tenant-id' => $tenantId,
        ])
        ->expectsConfirmation('Are you sure you want to delete this tenant database?', 'yes')
        ->expectsOutput("Deleting database for tenant: {$tenantId}")
        ->expectsOutput('✅ Tenant database deleted successfully')
        ->assertExitCode(0);
    }

    public function test_delete_action_cancels_when_not_confirmed()
    {
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';

        $this->databaseManager
            ->expects($this->never())
            ->method('deleteTenantDatabase');

        $this->artisan('tenant:database', [
            'action' => 'delete',
            'tenant-id' => $tenantId,
        ])
        ->expectsConfirmation('Are you sure you want to delete this tenant database?', 'no')
        ->expectsOutput("Deleting database for tenant: {$tenantId}")
        ->expectsOutput('Operation cancelled')
        ->assertExitCode(0);
    }

    public function test_migrate_action_migrates_tenant_database()
    {
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';
        $tenantIdObject = TenantId::fromString($tenantId);

        $this->databaseManager
            ->expects($this->once())
            ->method('migrateTenantDatabase')
            ->with($this->callback(function ($id) use ($tenantIdObject) {
                return $id->value()->toString() === $tenantIdObject->value()->toString();
            }));

        $this->artisan('tenant:database', [
            'action' => 'migrate',
            'tenant-id' => $tenantId,
        ])
        ->expectsOutput("Migrating database for tenant: {$tenantId}")
        ->expectsOutput('✅ Tenant database migrated successfully')
        ->assertExitCode(0);
    }

    public function test_unknown_action_returns_error()
    {
        $this->artisan('tenant:database', [
            'action' => 'unknown',
            'tenant-id' => '550e8400-e29b-41d4-a716-446655440000',
        ])
        ->expectsOutput('Unknown action: unknown. Use: create, delete, or migrate')
        ->assertExitCode(1);
    }

    public function test_invalid_tenant_id_returns_error()
    {
        $this->artisan('tenant:database', [
            'action' => 'create',
            'tenant-id' => 'invalid-uuid',
        ])
        ->expectsOutput('Error: Invalid UUID string: invalid-uuid')
        ->assertExitCode(1);
    }

    public function test_database_manager_exception_returns_error()
    {
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';

        $this->databaseManager
            ->expects($this->once())
            ->method('createTenantDatabase')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->artisan('tenant:database', [
            'action' => 'create',
            'tenant-id' => $tenantId,
        ])
        ->expectsOutput("Creating database for tenant: {$tenantId}")
        ->expectsOutput('Error: Database connection failed')
        ->assertExitCode(1);
    }
}
