# Laravel Doctrine Tenancy

Professional multi-tenancy for Laravel using Doctrine ORM. Clean APIs, job-based database operations, and first-class portability.

## Highlights

- **Database-per-tenant** architecture
- **Automatic routing** via a Smart Entity Manager
- **Domain/Header** based tenant resolution
- **Job-based ops** (migrate/delete) with retries and optional sync
- **Rollback-aware migrations** and structured logging
- **Laravel 11/12** and **Doctrine ORM 3**

## Installation

```bash
composer require doctrine-tenancy/laravel
```

Then run the installer:

```bash
# Publish config only (recommended)
php artisan tenancy:install

# Publish config + core migration stubs (optional)
php artisan tenancy:install --migrations

# You can always publish stubs later
php artisan vendor:publish --tag=tenancy-migrations-stub
```

Run your application migrations (includes the published `tenants` and `tenant_domains` if you chose to publish stubs):

```bash
php artisan migrate
```

## Usage

### 1) Configure Entity Routing

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

### 2) Add Middleware

```php
Route::middleware(['resolve.tenant'])->group(function () {
    // Your tenant-aware routes
    Route::get('/patients', [PatientController::class, 'index']);
});
```

### 3) Use in Controllers

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

- **Domain-based**: `tenant1.example.com` → `tenant1`
- **Header-based**: set `X-Tenant-ID: <uuid>`

## Database Management (Jobs)

All tenant DB operations run as jobs. Use `--sync` for blocking execution.

### Migrate
```bash
# Asynchronous (recommended)
php artisan tenant:database migrate {tenant-id}

# Synchronous (blocks until done)
php artisan tenant:database migrate {tenant-id} --sync

# Optional email notification on completion/failure
php artisan tenant:database migrate {tenant-id} --notify=admin@example.com
```

### Delete
```bash
# Asynchronous
php artisan tenant:database delete {tenant-id}

# Synchronous
php artisan tenant:database delete {tenant-id} --sync
```

### Queue Management
```bash
# Run workers for tenant queues
php artisan queue:work --queue=tenant-migrations,tenant-deletions

# Check failed jobs
php artisan queue:failed
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
    
    'migrations' => [
        'tenant_path' => 'database/migrations/tenant',
    ],
    
    'logging' => [
        'channel' => env('TENANCY_LOG_CHANNEL', null), // Optional dedicated channel
    ],
    
    'identification' => [
        'header_name' => env('TENANCY_HEADER_NAME', 'X-Tenant-ID'),
        'excluded_subdomains' => ['www', 'api', 'admin'],
    ],
];
```

### Publishing

```bash
# Config
php artisan vendor:publish --tag=tenancy-config

# Migration stubs (optional)
php artisan vendor:publish --tag=tenancy-migrations-stub
```

Publishing stubs copies `tenants` and `tenant_domains` migrations into `database/migrations/`. Use them as-is or adapt for custom entities.

## Supported Database Drivers

This package supports the following database drivers:

- **MySQL** 5.7+ / **MariaDB** 10.3+
- **PostgreSQL** 10+
- **SQLite** 3.8+

### Database-Specific Features

- **MySQL/MariaDB**: Full support with `INFORMATION_SCHEMA` queries for database existence checks
- **PostgreSQL**: Full support with `pg_database` queries and connection termination for safe drops
- **SQLite**: Basic support (databases are files, created on first connection)

## Versioning Strategy

This package follows [Semantic Versioning](https://semver.org/):

- **Major versions** (1.0, 2.0): Breaking changes that require code modifications
- **Minor versions** (1.1, 1.2): New features, backward compatible
- **Patch versions** (1.1.1, 1.1.2): Bug fixes, backward compatible

### Breaking Changes Policy

- Breaking changes are only introduced in major versions
- Deprecations are announced at least one minor version before removal
- Migration guides are provided for major version upgrades

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- Doctrine ORM 3.0+

## License

MIT