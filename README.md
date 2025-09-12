# Laravel Doctrine Tenancy

A comprehensive multi-tenancy package for Laravel applications using Doctrine ORM.

## Features

- **Database-per-tenant** architecture
- **Automatic entity routing** (central vs tenant entities)
- **Runtime database switching** (MySQL `USE` statements, PostgreSQL reconnection)
- **Auto-creation** of tenant databases
- **Migration and seeding** support for tenant databases
- **Multiple tenant resolution strategies** (header, subdomain)
- **Comprehensive configuration** system
- **Laravel integration** with service providers and middleware

## Installation

```bash
composer require laravel-doctrine/tenancy
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="LaravelDoctrine\Tenancy\Infrastructure\Providers\TenancyServiceProvider" --tag="tenancy-config"
```

## Usage

### Basic Setup

1. Configure your entities in `config/tenancy.php`:

```php
'entity_routing' => [
    'central' => [
        'App\Domain\User\User',
        'LaravelDoctrine\Tenancy\Domain\Tenant',
    ],
    'tenant' => [
        'App\Domain\Project\Project',
        'App\Domain\Order\Order',
    ],
],
```

2. Add the middleware to your routes:

```php
Route::middleware(['resolve.tenant'])->group(function () {
    // Your tenant-aware routes
});
```

3. Use the SmartEntityManager in your repositories:

```php
use Doctrine\ORM\EntityManagerInterface;

class ProjectRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function findAll(): array
    {
        return $this->entityManager->getRepository(Project::class)->findAll();
    }
}
```

### Tenant Management

Create a tenant database:

```bash
php artisan tenant:database create {tenant-id}
```

Migrate tenant database:

```bash
php artisan tenant:database migrate {tenant-id}
```

Seed tenant database:

```bash
php artisan tenant:database seed {tenant-id}
```

### Using the Facade

```php
use LaravelDoctrine\Tenancy\Facades\Tenancy;

// Get current tenant
$tenant = Tenancy::getCurrentTenant();

// Create tenant database
Tenancy::database()->createTenantDatabase($tenant);

// Check if tenant context exists
if (Tenancy::hasCurrentTenant()) {
    // Tenant-specific logic
}
```

## Configuration Options

See `config/tenancy.php` for all available configuration options.

## Requirements

- PHP 8.2+
- Laravel 11.0+
- Doctrine ORM 3.0+
- Doctrine DBAL 4.0+

## License

MIT
