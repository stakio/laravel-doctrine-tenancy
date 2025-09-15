<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\Commands;

use Illuminate\Support\Facades\Bus;
use LaravelDoctrine\Tenancy\Console\Commands\TenantDatabaseCommand;
use LaravelDoctrine\Tenancy\Jobs\MigrateTenantDatabaseJob;
use LaravelDoctrine\Tenancy\Tests\TestCase;

class TenantDatabaseCommandTest extends TestCase
{
    private TenantDatabaseCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new TenantDatabaseCommand;
    }

    public function test_delete_action_deletes_tenant_database_when_confirmed()
    {
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';

        $this->artisan('tenant:database', [
            'action' => 'delete',
            'tenant-id' => $tenantId,
            '--sync' => true,
        ])
            ->expectsConfirmation('Are you sure you want to delete this tenant database?', 'yes')
            ->expectsOutput("Deleting database for tenant: {$tenantId}")
            ->expectsOutput('Tenant database deleted successfully')
            ->assertExitCode(0);
    }

    public function test_delete_action_cancels_when_not_confirmed()
    {
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';

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
        Bus::fake();

        $this->artisan('tenant:database', [
            'action' => 'migrate',
            'tenant-id' => $tenantId,
        ])
            ->expectsOutput("Dispatching migrate job for tenant: {$tenantId}")
            ->expectsOutput('Job dispatched successfully!')
            ->assertExitCode(0);

        Bus::assertDispatched(MigrateTenantDatabaseJob::class, function ($job) use ($tenantId) {
            return (string) $job->tenant->value() === $tenantId;
        });
    }

    public function test_unknown_action_returns_error()
    {
        $this->artisan('tenant:database', [
            'action' => 'unknown',
            'tenant-id' => '550e8400-e29b-41d4-a716-446655440000',
        ])
            ->expectsOutput('Unknown action: unknown. Use: migrate or delete')
            ->assertExitCode(1);
    }

    public function test_invalid_tenant_id_returns_error()
    {
        $this->artisan('tenant:database', [
            'action' => 'migrate',
            'tenant-id' => 'ab', // Too short
        ])
            ->expectsOutput('Error: Invalid UUID string: ab')
            ->assertExitCode(1);
    }

    public function test_invalid_tenant_id_characters_returns_error()
    {
        $this->artisan('tenant:database', [
            'action' => 'migrate',
            'tenant-id' => 'invalid@tenant#id',
        ])
            ->expectsOutput('Error: Invalid UUID string: invalid@tenant#id')
            ->assertExitCode(1);
    }
}
