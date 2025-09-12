# Laravel Doctrine Tenancy

A comprehensive, production-ready multi-tenancy package for Laravel applications using Doctrine ORM. Built with Domain-Driven Design principles and enterprise-grade features.

## 🚀 Features

### Core Multi-Tenancy
- **Database-per-tenant** architecture with automatic routing
- **Smart Entity Manager** that automatically routes operations to central or tenant databases
- **Runtime database switching** (MySQL `USE` statements, PostgreSQL reconnection)
- **Auto-creation** of tenant databases with configurable naming strategies
- **Migration and seeding** support for tenant databases

### Tenant Resolution
- **Multiple resolution strategies** (header-based, domain-based)
- **Priority-based fallback** system
- **Configurable excluded subdomains**
- **Case-insensitive domain resolution**

### Custom Entities & Flexibility
- **Custom tenant entities** - Use your own tenant entity with additional fields and business logic
- **Custom domain entities** - Support multiple domains per tenant
- **Interface-based design** for maximum flexibility
- **Dynamic entity resolution** using EntityManager

### Event Tracking & Monitoring
- **Comprehensive event tracking** for tenant and domain lifecycle events
- **Job dispatching** based on event types and status
- **Retry mechanisms** for failed operations
- **Tenant status monitoring** and auto-recovery
- **Audit trail** and event history

### Enterprise Features
- **Structured logging** with contextual information
- **Performance monitoring** and metrics
- **Input validation** and sanitization
- **Caching** for improved performance
- **Configuration validation** at boot time
- **Exception hierarchy** for better error handling

## 📦 Installation

```bash
composer require doctrine-tenancy/laravel
```

## ⚙️ Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="LaravelDoctrine\Tenancy\Infrastructure\Providers\TenancyServiceProvider" --tag="tenancy-config"
```

Install the package with migrations:

```bash
# For default entities (tenants, domains, events)
php artisan tenancy:install

# For custom entities (events only)
php artisan tenancy:install --custom-entities
```

Run the migrations to create the necessary tables:

```bash
php artisan migrate
```

## 🏗️ Quick Start

### 1. Configure Entity Routing

Define which entities belong to central vs tenant databases in `config/tenancy.php`:

```php
'entity_routing' => [
    'central' => [
        // Central entities (shared across all tenants)
        'App\Domain\User\User',
        'App\Domain\System\Configuration',
    ],
    'tenant' => [
        // Tenant-specific entities
        'App\Domain\Project\Project',
        'App\Domain\Order\Order',
        'App\Domain\Invoice\Invoice',
    ],
],
```

### 2. Add Middleware to Routes

```php
Route::middleware(['resolve.tenant'])->group(function () {
    // Your tenant-aware routes
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
});
```

### 3. Use SmartEntityManager in Your Code

```php
use Doctrine\ORM\EntityManagerInterface;

class ProjectController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
    
    public function index()
    {
        // Automatically routes to tenant database for Project entities
        $projects = $this->entityManager->getRepository(Project::class)->findAll();
        
        return response()->json($projects);
    }
}
```

## 📋 Migration Strategy

### Default Entities (Package Provided)

The package provides migrations for its default entities. These are published to your project:

- `tenants` - Default tenant entity table
- `tenant_domains` - Domain entities table  
- `tenant_event_logs` - Event tracking table

**To use default entities:**
1. Publish migrations: `php artisan tenancy:publish-migrations`
2. Run migrations: `php artisan migrate`

### Custom Entities (User Responsibility)

If you create custom tenant or domain entities with additional fields, you must create your own migrations:

```bash
# For custom tenant entity fields
php artisan make:migration add_custom_fields_to_tenants_table

# For custom domain entity fields  
php artisan make:migration add_custom_fields_to_tenant_domains_table
```

**Important:** The package only provides the base table structure. Any additional fields are your responsibility to migrate.

## 🎯 Advanced Usage

### Custom Tenant Entities

Create your own tenant entity with custom fields and business logic:

```php
use LaravelDoctrine\Tenancy\Contracts\TenantEntityInterface;
use LaravelDoctrine\Tenancy\Domain\ValueObjects\TenantId;

class CustomTenantEntity implements TenantEntityInterface
{
    private UuidInterface $id;
    private string $name;
    private string $domain;
    private string $plan; // Custom field
    private bool $isActive; // Custom field
    
    public function __construct(
        UuidInterface $id,
        TenantName $name,
        Domain $domain,
        string $plan = 'basic'
    ) {
        $this->id = $id;
        $this->name = $name->value();
        $this->domain = $domain->value();
        $this->plan = $plan;
        $this->isActive = true;
    }
    
    // Implement required interface methods
    public function getId(): UuidInterface { return $this->id; }
    public function toIdentifier(): TenantIdentifier { /* ... */ }
    
    // Add your custom methods
    public function upgradePlan(string $newPlan): void { /* ... */ }
    public function isPremium(): bool { return $this->plan === 'premium'; }
}
```

Configure your custom entity:

```php
// config/tenancy.php
'tenant_entity' => \App\Domain\Tenants\CustomTenantEntity::class,
```

### Domain Management

Support multiple domains per tenant:

```php
use LaravelDoctrine\Tenancy\Domain\ValueObjects\Domain;

// Create domain for tenant
$domain = new Domain('example.com');
$domainEntity = new DomainEntity($tenantId, $domain, true); // isPrimary = true

$entityManager->persist($domainEntity);
$entityManager->flush();
```

### Event Tracking & Monitoring

Track tenant lifecycle events:

```php
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventFactory;
use LaravelDoctrine\Tenancy\Infrastructure\EventTracking\EventTracker;

class TenantService
{
    public function __construct(
        private EventTracker $eventTracker
    ) {}
    
    public function createTenant(TenantId $tenantId, array $data): void
    {
        try {
            // Create tenant logic...
            
            // Track successful creation
            $event = EventFactory::createTenantCreated($tenantId, [
                'created_by' => auth()->id(),
                'source' => 'admin_panel'
            ]);
            $this->eventTracker->trackTenantEvent($event);
            
        } catch (\Exception $e) {
            // Track failed creation
            $event = EventFactory::createTenantCreationFailed(
                $tenantId, 
                $e->getMessage(),
                ['error_code' => 'TENANT_CREATE_FAILED']
            );
            $this->eventTracker->trackTenantEvent($event);
            throw $e;
        }
    }
}
```

Monitor tenant status:

```php
// Get comprehensive tenant status
$status = $eventTracker->getTenantStatus($tenantId);

if ($status['has_failures']) {
    // Handle failed operations
    $eventDispatcher->retryFailedEvents();
}

if ($status['is_created'] && !$status['is_migrated']) {
    // Start migration process
    $eventDispatcher->processTenantStatus($tenantId);
}
```

### Job Dispatching

The package automatically dispatches jobs based on events:

```php
// Jobs are automatically dispatched for:
// - TenantCreated -> SetupTenantJob, NotifyTenantCreatedJob
// - TenantCreationFailed -> CleanupFailedTenantJob, NotifyTenantCreationFailedJob
// - TenantMigrationStarted -> RunTenantMigrationJob
// - TenantMigrationCompleted -> PostMigrationJob, NotifyMigrationCompletedJob
// - DomainCreated -> HandleDomainEventJob
```

## 🔧 Configuration Options

### Database Configuration

```php
'database' => [
    'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
    'naming' => [
        'strategy' => env('TENANT_DB_NAMING_STRATEGY', 'suffix'),
        'separator' => env('TENANT_DB_SEPARATOR', '_'),
    ],
    'auto_create' => env('TENANT_AUTO_CREATE_DB', true),
    'auto_migrate' => env('TENANT_AUTO_MIGRATE', true),
    'central_connection' => env('TENANT_CENTRAL_CONNECTION', null),
],
```

### Resolution Configuration

```php
'resolution' => [
    'header' => env('TENANT_HEADER_NAME', 'X-Tenant-ID'),
    'subdomain' => env('TENANT_SUBDOMAIN_RESOLUTION', false),
    'fallback_to_header' => env('TENANT_FALLBACK_TO_HEADER', true),
],
```

### Caching Configuration

```php
'cache' => [
    'enabled' => env('TENANT_CACHE_ENABLED', true),
    'prefix' => env('TENANT_CACHE_PREFIX', 'tenant_'),
    'ttl' => env('TENANT_CACHE_TTL', 3600),
],
```

## 🧪 Testing

The package includes comprehensive test coverage:

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suites
vendor/bin/phpunit tests/Feature/EventTracking/
vendor/bin/phpunit tests/Feature/TenantResolution/
```

## 📊 Monitoring & Debugging

### Structured Logging

The package provides structured logging for all operations:

```php
// Logs are automatically created for:
// - Tenant resolution events
// - Database connection switches
// - Event tracking
// - Performance metrics
// - Security events
```

### Performance Monitoring

```php
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Logging\TenancyLogger;

// Performance metrics are automatically logged
TenancyLogger::performanceMetric('tenant_resolution', $duration);
```

## 🚨 Error Handling

The package provides a comprehensive exception hierarchy:

```php
use LaravelDoctrine\Tenancy\Infrastructure\Tenancy\Exceptions\{
    TenancyException,
    TenantException,
    TenantResolutionException,
    TenantDatabaseException,
    TenancyConfigurationException
};

try {
    // Tenant operations
} catch (TenantException $e) {
    // Handle tenant-specific errors
} catch (TenancyConfigurationException $e) {
    // Handle configuration errors
}
```

## 🔒 Security Features

- **Input validation** and sanitization
- **SQL injection protection**
- **Suspicious pattern detection**
- **Secure UUID handling**
- **Domain validation**

## 📈 Performance Optimizations

- **Entity routing caching**
- **Tenant resolution caching**
- **Connection pooling** (configurable)
- **Query optimization**
- **Memory usage monitoring**

## 🛠️ Artisan Commands

```bash
# Create tenant database
php artisan tenant:database create {tenant-id}

# Migrate tenant database
php artisan tenant:database migrate {tenant-id}

# Seed tenant database
php artisan tenant:database seed {tenant-id}

# List tenant databases
php artisan tenant:database list

# Drop tenant database
php artisan tenant:database drop {tenant-id}
```

## 📋 Requirements

- **PHP 8.2+**
- **Laravel 11.0+** or **Laravel 12.0+**
- **Doctrine ORM 3.0+**
- **Doctrine DBAL 4.0+**
- **MySQL 5.7+** or **PostgreSQL 10+**

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## 📄 License

MIT License. See [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation**: [Package Documentation](docs/)
- **Issues**: [GitHub Issues](https://github.com/doctrine-tenancy/laravel/issues)
- **Discussions**: [GitHub Discussions](https://github.com/doctrine-tenancy/laravel/discussions)

## 🏆 What's New in v1.2.0

- ✨ **Event Tracking System** - Comprehensive event tracking for tenant lifecycle
- 🔄 **Job Dispatching** - Automatic job dispatch based on events
- 📊 **Status Monitoring** - Tenant status monitoring and auto-recovery
- 🎯 **Custom Domain Entities** - Support for multiple domains per tenant
- 🛡️ **Enhanced Security** - Input validation and sanitization
- 📈 **Performance Monitoring** - Built-in performance metrics and logging
- 🧪 **Comprehensive Testing** - 67 tests with 1139 assertions
- 📚 **Better Documentation** - Complete usage examples and guides

---

**Built with ❤️ for the Laravel community**