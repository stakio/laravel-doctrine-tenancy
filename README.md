# Laravel Doctrine Tenancy

A lightweight multi-tenancy package for Laravel applications using Doctrine ORM.

## Features

- **Database-per-tenant** architecture
- **Smart Entity Manager** with automatic routing
- **Multiple resolution strategies** (header, domain)
- **Custom tenant entities** support
- **Event tracking** system
- **Laravel 11.0+** and **12.0+** support

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
        'App\Domain\User\User',
    ],
    'tenant' => [
        'App\Domain\Project\Project',
    ],
],
```

### 2. Add Middleware

```php
Route::middleware(['resolve.tenant'])->group(function () {
    // Your tenant-aware routes
});
```

### 3. Use in Controllers

```php
use Doctrine\ORM\EntityManagerInterface;

class ProjectController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function index()
    {
        // Automatically routes to tenant database
        $projects = $this->entityManager->getRepository(Project::class)->findAll();
        return response()->json($projects);
    }
}
```

## Custom Entities

```bash
# Install with custom entities (events only)
php artisan tenancy:install --custom-entities
```

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- Doctrine ORM 3.0+

## License

MIT