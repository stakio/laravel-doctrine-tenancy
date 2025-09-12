<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the multi-tenancy system.
    | You can customize these settings based on your requirements.
    |
    */

    'enabled' => env('MULTI_TENANCY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tenant Entity Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the tenant entity class to use. You can use the default
    | LaravelDoctrine\Tenancy\Domain\Tenant class or provide your own
    | custom tenant entity that implements TenantEntityInterface.
    |
    */
    'tenant_entity' => env('TENANT_ENTITY_CLASS', \LaravelDoctrine\Tenancy\Domain\Tenant::class),

    /*
    |--------------------------------------------------------------------------
    | Domain Entity Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the domain entity class to use. You can use the default
    | LaravelDoctrine\Tenancy\Domain\DomainEntity class or provide your own
    | custom domain entity that implements DomainEntityInterface.
    |
    */
    'domain_entity' => env('DOMAIN_ENTITY_CLASS', \LaravelDoctrine\Tenancy\Domain\DomainEntity::class),

    /*
    |--------------------------------------------------------------------------
    | Tenant Identification
    |--------------------------------------------------------------------------
    |
    | Configure how tenants are identified in your application.
    |
    */

    'identification' => [
        'strategies' => [
            'subdomain' => [
                'enabled' => env('TENANT_SUBDOMAIN_ENABLED', true),
                'exclude_subdomains' => ['www', 'api', 'admin', 'app'],
            ],
            'header' => [
                'enabled' => env('TENANT_HEADER_ENABLED', true),
                'header_name' => env('TENANT_HEADER_NAME', 'X-Tenant-ID'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Routing Configuration
    |--------------------------------------------------------------------------
    |
    | Define which entities should use the central database vs tenant-specific databases.
    | This configuration is used by SmartEntityManager to route operations automatically.
    |
    */
    'entity_routing' => [
        'central' => [
            // The tenant entity, domain entity, and event log are automatically added to central entities
            // Add other central entities here
            // Example: 'App\Domain\User\User',
        ],
        'tenant' => [
            // Add tenant entities here
            // Example: 'App\Domain\Project\Project',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tenant databases are created and managed.
    |
    */

    'database' => [
        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        'naming' => [
            'strategy' => env('TENANT_DB_NAMING_STRATEGY', 'suffix'), // suffix, prefix, custom
            'separator' => env('TENANT_DB_SEPARATOR', '_'),
        ],
        'auto_create' => env('TENANT_AUTO_CREATE_DB', true),
        'auto_migrate' => env('TENANT_AUTO_MIGRATE', true),
        'auto_seed' => env('TENANT_AUTO_SEED', false),
        'central_connection' => env('TENANT_CENTRAL_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which routes should have tenant resolution enabled.
    |
    */

    'middleware' => [
        'resolve_tenant' => [
            'enabled' => env('TENANT_MIDDLEWARE_ENABLED', true),
            'routes' => [
                'api/*',
                'web/*',
            ],
            'exclude_routes' => [
                'api/v1/tenancy/*', // Exclude tenant management routes
                'api/v1/auth/*',    // Exclude authentication routes
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for tenant-specific data.
    |
    */

    'cache' => [
        'enabled' => env('TENANT_CACHE_ENABLED', true),
        'prefix' => env('TENANT_CACHE_PREFIX', 'tenant_'),
        'ttl' => env('TENANT_CACHE_TTL', 3600), // 1 hour
        'redis_prefix' => env('TENANT_REDIS_PREFIX', 'tenant_cache_'),
        'doctrine_query_cache' => env('TENANT_DOCTRINE_QUERY_CACHE', true),
        'doctrine_result_cache' => env('TENANT_DOCTRINE_RESULT_CACHE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for tenant-related operations.
    |
    */

    'logging' => [
        'enabled' => env('TENANT_LOGGING_ENABLED', true),
        'channel' => env('TENANT_LOG_CHANNEL', 'stack'),
        'level' => env('TENANT_LOG_LEVEL', 'info'),
        'tenant_context' => env('TENANT_LOG_CONTEXT', true),
        'separate_channels' => env('TENANT_SEPARATE_CHANNELS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution
    |--------------------------------------------------------------------------
    |
    | Configure how tenants are resolved from requests.
    |
    */
    'resolution' => [
        'header' => env('TENANT_HEADER_NAME', 'X-Tenant-ID'),
        'subdomain' => env('TENANT_SUBDOMAIN_RESOLUTION', false),
        'subdomain_pattern' => env('TENANT_SUBDOMAIN_PATTERN', '*.{domain}'),
        'fallback_to_header' => env('TENANT_FALLBACK_TO_HEADER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-related events for extensibility.
    |
    */
    'events' => [
        'enabled' => env('TENANT_EVENTS_ENABLED', true),
        'dispatch_resolved' => env('TENANT_DISPATCH_RESOLVED', true),
        'dispatch_switched' => env('TENANT_DISPATCH_SWITCHED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance optimizations for tenant operations.
    |
    */
    'performance' => [
        'use_singleton_ems' => env('TENANT_USE_SINGLETON_EMS', true),
        'connection_pooling' => env('TENANT_CONNECTION_POOLING', false),
        'async_migrations' => env('TENANT_ASYNC_MIGRATIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration and Seeding
    |--------------------------------------------------------------------------
    |
    | Configure paths for tenant-specific migrations and seeders.
    |
    */
    'migrations' => [
        'tenant_path' => 'database/migrations/tenant',
        'central_path' => 'database/migrations',
    ],

    'seeders' => [
        'tenant_path' => 'database/seeders/tenant',
        'default_tenant_seeder' => 'TenantDatabaseSeeder',
    ],
];
