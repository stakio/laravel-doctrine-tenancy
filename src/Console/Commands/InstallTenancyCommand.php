<?php

namespace LaravelDoctrine\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class InstallTenancyCommand extends Command
{
    protected $signature = 'tenancy:install 
                            {--custom-entities : Skip tenant and domain migrations, keep only event logs}
                            {--force : Overwrite existing migrations}';

    protected $description = 'Install tenancy package with config and migrations';

    public function handle(): int
    {
        $this->info('Installing Laravel Doctrine Tenancy package...');

        // 1. Publish configuration
        $this->info('Publishing configuration...');
        $exitCode = Artisan::call('vendor:publish', [
            '--provider' => 'LaravelDoctrine\Tenancy\Infrastructure\Providers\TenancyServiceProvider',
            '--tag' => 'tenancy-config',
            '--force' => $this->option('force'),
        ]);

        if ($exitCode === 0) {
            $this->line('✅ Configuration published');
        } else {
            $this->warn('Configuration may already exist');
        }

        // 2. Create migrations
        $this->info('Creating migrations...');
        $this->createMigrations();

        $this->line('');
        $this->info('🎉 Tenancy package installed successfully!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Run: php artisan migrate');
        $this->line('2. Configure your entities in config/tenancy.php');

        return 0;
    }

    private function createMigrations(): void
    {
        $customEntities = $this->option('custom-entities');
        $force = $this->option('force');

        if ($customEntities) {
            $this->info('Custom entities mode: Creating event logs migration only');
            $migrations = [
                'create_tenant_event_logs_table' => [
                    'table' => 'tenant_event_logs',
                    'source' => __DIR__ . '/../../../database/migrations/2024_01_01_000002_create_tenant_event_logs_table.php',
                ],
            ];
        } else {
            $this->info('Default entities mode: Creating all migrations');
            $migrations = [
                'create_tenants_table' => [
                    'table' => 'tenants',
                    'source' => __DIR__ . '/../../../database/migrations/2024_01_01_000000_create_tenants_table.php',
                ],
                'create_tenant_domains_table' => [
                    'table' => 'tenant_domains',
                    'source' => __DIR__ . '/../../../database/migrations/2024_01_01_000001_create_tenant_domains_table.php',
                ],
                'create_tenant_event_logs_table' => [
                    'table' => 'tenant_event_logs',
                    'source' => __DIR__ . '/../../../database/migrations/2024_01_01_000002_create_tenant_event_logs_table.php',
                ],
            ];
        }

        $createdMigrations = [];

        foreach ($migrations as $name => $config) {
            try {
                // Check if migration already exists
                $existingFile = $this->findExistingMigration($name);
                if ($existingFile && !$force) {
                    $this->warn("Migration already exists: " . basename($existingFile));
                    continue;
                }

                // Use Laravel's make:migration command
                $exitCode = Artisan::call('make:migration', [
                    'name' => $name,
                    '--create' => $config['table'],
                ]);

                if ($exitCode !== 0) {
                    $this->error("Failed to create migration: {$name}");
                    continue;
                }

                // Get the generated migration file
                $migrationFile = $this->getLatestMigrationFile($name);
                
                if ($migrationFile && File::exists($config['source'])) {
                    // Replace the generated content with our package content
                    $content = File::get($config['source']);
                    File::put($migrationFile, $content);
                    $this->line("✅ Created: " . basename($migrationFile));
                    $createdMigrations[] = basename($migrationFile);
                }

            } catch (\Exception $e) {
                $this->error("Error creating migration {$name}: " . $e->getMessage());
            }
        }

        $this->info("Successfully created " . count($createdMigrations) . " migration(s)");
    }

    private function findExistingMigration(string $name): ?string
    {
        $migrationPath = database_path('migrations');
        $files = glob($migrationPath . '/*_' . $name . '.php');
        
        return empty($files) ? null : max($files);
    }

    private function getLatestMigrationFile(string $name): ?string
    {
        $migrationPath = database_path('migrations');
        $files = glob($migrationPath . '/*_' . $name . '.php');
        
        if (empty($files)) {
            return null;
        }

        // Return the most recent file (highest timestamp)
        return max($files);
    }
}
