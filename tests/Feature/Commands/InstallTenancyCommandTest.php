<?php

namespace LaravelDoctrine\Tenancy\Tests\Feature\Commands;

use LaravelDoctrine\Tenancy\Console\Commands\InstallTenancyCommand;
use LaravelDoctrine\Tenancy\Tests\TestCase;

class InstallTenancyCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure clean state
        $this->cleanupMigrations();
    }

    protected function tearDown(): void
    {
        $this->cleanupMigrations();
        parent::tearDown();
    }

    public function test_install_command_runs_successfully()
    {
        $this->artisan('tenancy:install')
            ->expectsOutput('Installing Laravel Doctrine Tenancy package')
            ->expectsOutput('Publishing configuration')
            ->expectsOutput('Skipping migration stubs (run: php artisan vendor:publish --tag=tenancy-migrations-stub)')
            ->expectsOutput('Tenancy package installed successfully')
            ->expectsOutput('Next steps:')
            ->expectsOutput('1. Configure your entities in config/tenancy.php')
            ->expectsOutput('2. Run: php artisan migrate')
            ->assertExitCode(0);
    }

    public function test_install_command_with_force_option()
    {
        $this->artisan('tenancy:install', ['--force' => true, '--migrations' => true])
            ->expectsOutput('Installing Laravel Doctrine Tenancy package')
            ->expectsOutput('Publishing configuration')
            ->expectsOutput('Publishing migration stubs')
            ->expectsOutput('Tenancy package installed successfully')
            ->assertExitCode(0);
    }

    public function test_install_command_has_correct_signature()
    {
        $command = new InstallTenancyCommand;

        $this->assertEquals('tenancy:install', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('force'));
        $this->assertTrue($command->getDefinition()->hasOption('migrations'));
    }

    private function cleanupMigrations()
    {
        $migrationPath = database_path('migrations');

        if (is_dir($migrationPath)) {
            $files = glob($migrationPath.'/*_create_tenants_table.php');
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $files = glob($migrationPath.'/*_create_tenant_domains_table.php');
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
