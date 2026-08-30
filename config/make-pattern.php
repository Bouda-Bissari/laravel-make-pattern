<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PSR-4 roots
    |--------------------------------------------------------------------------
    |
    | Every layer namespace below must sit under one of these roots. The
    | generator derives a layer's directory from its namespace relative to its
    | root, which is what keeps path and namespace in sync no matter which
    | options (--domain, --namespace) are used.
    |
    | - path    : the directory the root namespace maps to in composer.json
    | - domain  : where --domain=Blog is inserted for layers under this root.
    |             'prefix' => App\Domain\Blog\Services
    |             'suffix' => Tests\Feature\Blog
    |             'none'   => --domain is ignored for this root
    | - primary : the root that --namespace overrides. Exactly one root
    |             should be primary.
    |
    */

    'roots' => [
        'App' => [
            'path' => app_path(),
            'domain' => 'prefix',
            'primary' => true,
        ],
        'Tests' => [
            'path' => base_path('tests'),
            'domain' => 'suffix',
            'primary' => false,
        ],
        // Mirrors Laravel's own psr-4 entry: Database\Factories\ maps to the
        // lowercase database/factories/ directory, not database/Factories/.
        'Database\\Factories' => [
            'path' => database_path('factories'),
            'domain' => 'suffix',
            'primary' => false,
        ],
        'Database\\Seeders' => [
            'path' => database_path('seeders'),
            'domain' => 'suffix',
            'primary' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain (DDD) segment
    |--------------------------------------------------------------------------
    |
    | The namespace segment inserted by --domain. Change to 'Modules' for a
    | modular monolith layout, or '' to insert the domain name directly
    | (App\Blog\Services instead of App\Domain\Blog\Services).
    |
    */

    'domain' => [
        'segment' => 'Domain',
    ],

    /*
    |--------------------------------------------------------------------------
    | Project-wide classes referenced by the generated code
    |--------------------------------------------------------------------------
    */

    // Imported by the generated Policy. Set to null to generate a policy
    // without any User type hint.
    'user_model' => 'App\\Models\\User',

    // Extended by the generated Controller. Set to null to generate a
    // standalone controller that extends nothing.
    'base_controller' => 'App\\Http\\Controllers\\Controller',

    /*
    |--------------------------------------------------------------------------
    | Primary key strategy
    |--------------------------------------------------------------------------
    |
    | 'ulid', 'uuid' or 'increment'. Drives the Model traits, the migration
    | column, and the $id type hints across Repository, Service and Controller.
    |
    */

    'primary_key' => [
        'strategy' => 'ulid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | When enabled, the generated Model pulls in the tenant-scoping trait of
    | the chosen driver. Add your own driver here without touching the package.
    |
    */

    'tenancy' => [
        'enabled' => false,

        // Which driver key below to use.
        'driver' => 'stancl',

        'drivers' => [
            'stancl' => [
                'trait' => 'Stancl\\Tenancy\\Database\\Concerns\\BelongsToTenant',
            ],
            'spatie' => [
                'trait' => 'Spatie\\Multitenancy\\Models\\Concerns\\UsesTenantConnection',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Repository logging
    |--------------------------------------------------------------------------
    |
    | Wrap each write in a try/catch that logs via the Log facade and rethrows.
    | Exceptions are never swallowed. Uses the 'repository-with-logging' stub.
    |
    */

    'wrap_repository_calls' => false,

    /*
    |--------------------------------------------------------------------------
    | Route registration
    |--------------------------------------------------------------------------
    |
    | Appends a resource route for the generated controller. Idempotent: a
    | controller that is already routed is never registered twice.
    |
    */

    'routes' => [
        'enabled' => true,
        'file' => base_path('routes/api.php'),

        // 'apiResource' (no create/edit) or 'resource'.
        'method' => 'apiResource',

        // Extra middleware, e.g. ['auth:sanctum'].
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated service provider
    |--------------------------------------------------------------------------
    |
    | Without a container binding, injecting a RepositoryInterface throws a
    | BindingResolutionException. And under --domain, Laravel's policy
    | auto-discovery no longer finds the policy. The generator maintains one
    | provider that solves both, appending to it on every run.
    |
    */

    'provider' => [
        'enabled' => true,
        'class' => 'App\\Providers\\PatternServiceProvider',

        // Add the provider to bootstrap/providers.php automatically.
        'auto_register' => true,

        'bind_repositories' => true,
        'register_policies' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | When enforced, the generated controller calls Gate::authorize() in every
    | action, using the 'controller-authorized' stub. Turn off for a controller
    | with no authorization calls.
    |
    */

    'policies' => [
        'enforce_in_controller' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation history
    |--------------------------------------------------------------------------
    |
    | Every run is recorded so it can be rolled back. Files overwritten with
    | --force are backed up first, and restored (not deleted) on undo.
    |
    */

    'history' => [
        'path' => storage_path('app/make-pattern/history.json'),
        'backups' => storage_path('app/make-pattern/backups'),

        // Number of runs to keep. Older entries and their backups are pruned.
        'keep' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Layers
    |--------------------------------------------------------------------------
    |
    | Each layer is one generated file. Keys:
    |   enabled   - generate it or not
    |   namespace - PSR-4 namespace (null for files that have none, e.g. migrations)
    |   path      - only used when namespace is null; otherwise derived from the roots
    |   stub      - stub name, resolved from resource/stubs/vendor/make-pattern first
    |   suffix    - appended to the studly entity name to build the class name
    |   file_name - file name pattern, defaults to '{{ class }}'
    |   exists_glob - optional glob used instead of the exact file name to detect
    |                 an existing file (migrations carry a timestamp)
    |
    */

    'layers' => [
        'model' => [
            'enabled' => true,
            'namespace' => 'App\\Models',
            'stub' => 'model',
            'suffix' => '',
        ],
        'migration' => [
            'enabled' => true,
            'namespace' => null,
            'path' => database_path('migrations'),
            'stub' => 'migration',
            'suffix' => '',
            'file_name' => '{{ timestamp }}_create_{{ entityTable }}_table',
            'exists_glob' => '*_create_{{ entityTable }}_table.php',
        ],
        'factory' => [
            'enabled' => true,
            'namespace' => 'Database\\Factories',
            'stub' => 'factory',
            'suffix' => 'Factory',
        ],
        'repository_interface' => [
            'enabled' => true,
            'namespace' => 'App\\Repositories\\Contracts',
            'stub' => 'repository-interface',
            'suffix' => 'RepositoryInterface',
        ],
        'repository' => [
            'enabled' => true,
            'namespace' => 'App\\Repositories',
            'stub' => 'repository',
            'suffix' => 'Repository',
        ],
        'service' => [
            'enabled' => true,
            'namespace' => 'App\\Services',
            'stub' => 'service',
            'suffix' => 'Service',
        ],
        'controller' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Controllers',
            'stub' => 'controller',
            'suffix' => 'Controller',
        ],
        'store_request' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Requests',
            'stub' => 'request',
            'suffix' => 'StoreRequest',
        ],
        'update_request' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Requests',
            'stub' => 'request',
            'suffix' => 'UpdateRequest',
        ],
        'resource' => [
            'enabled' => true,
            'namespace' => 'App\\Http\\Resources',
            'stub' => 'resource',
            'suffix' => 'Resource',
        ],
        'policy' => [
            'enabled' => true,
            'namespace' => 'App\\Policies',
            'stub' => 'policy',
            'suffix' => 'Policy',
        ],
        'test' => [
            'enabled' => true,
            'namespace' => 'Tests\\Feature',
            'stub' => 'test',
            'suffix' => 'Test',
        ],
    ],

];
