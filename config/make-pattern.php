<?php

return [
    'base_namespace' => 'App',

    'layers' => [
        'model' => [
            'enabled' => true,
            'namespace' => 'App\\Models',
            'path' => app_path('Models'),
            'stub' => 'model',
            'suffix' => '',
        ],
        'repository_interface' => [
            'enabled' => true,
            'namespace' => 'App\\Repositories\\Contracts',
            'path' => app_path('Repositories/Contracts'),
            'stub' => 'repository-interface',
            'suffix' => 'RepositoryInterface',
        ],
        'repository' => [
            'enabled' => true,
            'namespace' => 'App\\Repositories',
            'path' => app_path('Repositories'),
            'stub' => 'repository',
            'suffix' => 'Repository',
        ],
        'service' => [
            'enabled' => true,
            'namespace' => 'App\\Services',
            'path' => app_path('Services'),
            'stub' => 'service',
            'suffix' => 'Service',
        ],
        'controller' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Controllers',
            'path' => app_path('Http/Controllers'),
            'stub' => 'controller',
            'suffix' => 'Controller',
        ],
        'store_request' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Requests',
            'path' => app_path('Http/Requests'),
            'stub' => 'request',
            'suffix' => 'StoreRequest',
        ],
        'update_request' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Requests',
            'path' => app_path('Http/Requests'),
            'stub' => 'request',
            'suffix' => 'UpdateRequest',
        ],
        'resource' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Resources',
            'path' => app_path('Http/Resources'),
            'stub' => 'resource',
            'suffix' => 'Resource',
        ],
        'policy' => [
            'enabled' => true,
            'namespace' => 'App\\Policies',
            'path' => app_path('Policies'),
            'stub' => 'policy',
            'suffix' => 'Policy',
        ],
        'test' => [
            'enabled' => true,
            'namespace' => 'Tests\\Feature',
            'path' => base_path('tests/Feature'),
            'stub' => 'test',
            'suffix' => 'Test',
        ],
    ],

    // 'increment', 'uuid', or 'ulid'
    'primary_key' => [
        'strategy' => 'ulid',
    ],

    'tenancy' => [
        'enabled' => false,
        // 'stancl' or 'spatie' — adapts the generated Model & Repository
        'driver' => 'stancl',
    ],

    'log_path' => storage_path('app/make-pattern/history.json'),

    // Wrap each create/update/delete repository call in a try/catch that logs
    // via the Log facade and rethrows. Exceptions are never swallowed.
    'wrap_repository_calls' => false,
];
