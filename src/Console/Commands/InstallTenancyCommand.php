<?php

namespace LaravelDoctrine\Tenancy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallTenancyCommand extends Command
{
    protected $signature = 'tenancy:install 
                            {--force : Overwrite existing files}
                            {--migrations : Publish core migration stubs}';

    protected $description = 'Install tenancy package with config and migrations';

    public function handle(): int
    {
        $this->info('Installing Laravel Doctrine Tenancy package');

        // 1. Publish configuration
        $this->info('Publishing configuration');
        $exitCode = Artisan::call('vendor:publish', [
            '--provider' => 'LaravelDoctrine\Tenancy\Infrastructure\Providers\TenancyServiceProvider',
            '--tag' => 'tenancy-config',
            '--force' => $this->option('force'),
        ]);

        if ($exitCode === 0) {
            $this->line('Configuration published');
        } else {
            $this->warn('Configuration may already exist');
        }

        // 2. Optionally publish migrations (via stubs)
        if ($this->option('migrations')) {
            $this->info('Publishing migration stubs');
            $migrationsExit = Artisan::call('vendor:publish', [
                '--provider' => 'LaravelDoctrine\\Tenancy\\Infrastructure\\Providers\\TenancyServiceProvider',
                '--tag' => 'tenancy-migrations-stub',
                '--force' => $this->option('force'),
            ]);

            if ($migrationsExit === 0) {
                $this->line('Migration stubs published');
            } else {
                $this->warn('Migration stubs may already exist');
            }
        } else {
            $this->line('Skipping migration stubs (run: php artisan vendor:publish --tag=tenancy-migrations-stub)');
        }

        $this->line('');
        $this->info('Tenancy package installed successfully');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Configure your entities in config/tenancy.php');
        $this->line('2. Run: php artisan migrate');

        return 0;
    }
}
