# Quick Start Guide

## 🚀 Get Started in 5 Minutes

### 1. Install the Package

```bash
composer require doctrine-tenancy/laravel
```

### 2. Install Package

```bash
# For default entities (tenants, domains, events)
php artisan tenancy:install

# For custom entities (events only)
php artisan tenancy:install --custom-entities
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Your Entities

Edit `config/tenancy.php`:

```php
'entity_routing' => [
    'central' => [
        'App\Domain\User\User',           // Shared across tenants
        'App\Domain\System\Configuration', // System-wide settings
    ],
    'tenant' => [
        'App\Domain\Project\Project',     // Tenant-specific
        'App\Domain\Order\Order',         // Tenant-specific
    ],
],
```

### 5. Add Middleware to Routes

```php
// routes/web.php
Route::middleware(['resolve.tenant'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
});
```

### 6. Use in Your Controllers

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

## 🎯 That's It!

Your application now supports multi-tenancy! The package will:

- ✅ **Automatically resolve tenants** from headers or domains
- ✅ **Route entities** to the correct database
- ✅ **Create tenant databases** on demand
- ✅ **Track events** for monitoring
- ✅ **Handle failures** gracefully

## 🔧 Next Steps

### Create a Tenant

```bash
php artisan tenant:database create {tenant-id}
```

### Monitor Events

```php
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventTracker;

$status = app(EventTracker::class)->getTenantStatus($tenantId);
```

### Custom Tenant Entity

```php
class CustomTenant extends \LaravelDoctrine\Tenancy\Domain\Tenant
{
    private string $plan;
    
    public function upgradePlan(string $newPlan): void
    {
        $this->plan = $newPlan;
    }
}
```

## 📚 Learn More

- [Full Documentation](README.md)
- [Configuration Options](config/tenancy.php)
- [Event Tracking Examples](examples/EventTrackingExample.php)
- [Package Verification](PACKAGE_VERIFICATION.md)

---

**Need help?** Check the [GitHub Issues](https://github.com/doctrine-tenancy/laravel/issues) or [Discussions](https://github.com/doctrine-tenancy/laravel/discussions).
