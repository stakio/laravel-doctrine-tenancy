<?php

return [
    'enabled' => env('MULTI_TENANCY_ENABLED', true),

    'tenant_entity' => env('TENANT_ENTITY_CLASS', \LaravelDoctrine\Tenancy\Domain\Tenant::class),
    'domain_entity' => env('DOMAIN_ENTITY_CLASS', \LaravelDoctrine\Tenancy\Domain\DomainEntity::class),

    'identification' => [
        'strategies' => ['header', 'domain'],
        'header_name' => env('TENANT_HEADER_NAME', 'X-Tenant-ID'),
        'domain_separator' => env('TENANT_DOMAIN_SEPARATOR', '.'),
        'excluded_subdomains' => ['www', 'api', 'admin'],
    ],

    'database' => [
        'central_connection' => env('TENANCY_CENTRAL_CONNECTION', 'default'),
        'prefix' => env('TENANCY_DATABASE_PREFIX', 'tenant_'),
        'naming' => [
            'strategy' => env('TENANCY_NAMING_STRATEGY', 'prefix'),
            'separator' => env('TENANCY_NAMING_SEPARATOR', '_'),
        ],
    ],

    'entity_routing' => [
        'central' => [
            // Add your central entities here
        ],
        'tenant' => [
            // Add your tenant entities here
        ],
    ],

    'caching' => [
        'enabled' => env('TENANCY_CACHE_ENABLED', true),
        'ttl' => env('TENANCY_CACHE_TTL', 3600),
        'prefix' => env('TENANCY_CACHE_PREFIX', 'tenancy'),
    ],

    'logging' => [
        'enabled' => env('TENANCY_LOGGING_ENABLED', true),
        'channel' => env('TENANCY_LOG_CHANNEL', 'default'),
    ],
];