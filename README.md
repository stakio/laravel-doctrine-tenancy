# Laravel Doctrine Tenancy

A lightweight, high-performance multi-tenancy package for Laravel applications using Doctrine ORM.

## Features

- **Database-per-tenant** architecture
- **Smart Entity Manager** with automatic routing
- **Multiple resolution strategies** (header, domain)
- **Custom tenant entities** support
- **Manual database management** (simple commands)
- **Laravel 11.0+** and **12.0+** support
- **Zero over-engineering** - Clean, SOLID code

## Installation

```bash
composer require doctrine-tenancy/laravel
```

## Quick Start

```bash
# Install with migrations
php artisan tenancy:install

# Run migrations
php artisan migrate
```

## Usage

### 1. Configure Entity Routing

```php
// config/tenancy.php
'entity_routing' => [
    'central' => [
        'App\Entities\Clinic',        // Shared across all tenants
    ],
    'tenant' => [
        'App\Entities\Patient',       // Tenant-specific data
    ],
],
```

### 2. Add Middleware

```php
Route::middleware(['resolve.tenant'])->group(function () {
    // Your tenant-aware routes
    Route::get('/patients', [PatientController::class, 'index']);
});
```

### 3. Use in Controllers

```php
use Doctrine\ORM\EntityManagerInterface;

class PatientController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function index()
    {
        // Automatically routes to tenant database
        $patients = $this->entityManager->getRepository(Patient::class)->findAll();
        return response()->json($patients);
    }
}
```

## Tenant Resolution

### Domain-based Resolution
```
tenant1.example.com → resolves to tenant1
tenant2.example.com → resolves to tenant2
```

### Header-based Resolution
```http
X-Tenant-ID: 550e8400-e29b-41d4-a716-446655440000
```

## Database Management

### Create Tenant Database
```bash
php artisan tenant:database create {tenant-id}
```

### Run Migrations
```bash
php artisan tenant:database migrate {tenant-id}
```

### Delete Tenant Database
```bash
php artisan tenant:database delete {tenant-id}
```

## Custom Entities

Create your own tenant and domain entities by implementing the required interfaces:

```php
use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;
use LaravelDoctrine\Tenancy\Contracts\TenantIdentifier;

class Clinic implements TenantEntityInterface
{
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function name(): TenantName
    {
        return new TenantName($this->name);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function toIdentifier(): TenantIdentifier
    {
        return new TenantId($this->id);
    }
}
```

Then configure in `config/tenancy.php`:

```php
'tenant_entity' => App\Entities\Clinic::class,
'domain_entity' => App\Entities\ClinicDomain::class,
```

## Configuration

```php
// config/tenancy.php
return [
    'enabled' => true,
    
    'tenant_entity' => \LaravelDoctrine\Tenancy\Domain\Tenant::class,
    'domain_entity' => \LaravelDoctrine\Tenancy\Domain\DomainEntity::class,
    
    'entity_routing' => [
        'central' => ['App\Entities\Clinic'],
        'tenant' => ['App\Entities\Patient'],
    ],
    
    'database' => [
        'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'default'),
        'prefix' => env('TENANCY_DATABASE_PREFIX', 'tenant_'),
    ],
    
    'identification' => [
        'header_name' => env('TENANCY_HEADER_NAME', 'X-Tenant-ID'),
        'excluded_subdomains' => ['www', 'api', 'admin'],
    ],
];
```

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- Doctrine ORM 3.0+

## License

MIT