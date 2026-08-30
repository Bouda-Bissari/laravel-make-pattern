# Laravel Make Pattern

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bouda/laravel-make-pattern.svg?style=flat-square)](https://packagist.org/packages/bouda/laravel-make-pattern)
[![Tests](https://img.shields.io/github/actions/workflow/status/Bouda-Bissari/laravel-make-pattern/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Bouda-Bissari/laravel-make-pattern/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/bouda/laravel-make-pattern.svg?style=flat-square)](https://packagist.org/packages/bouda/laravel-make-pattern)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

Generate a complete, **working** CRUD scaffold from one Artisan command — Model, Migration, Factory, Repository (+ interface), Service, Controller, Form Requests, API Resource, Policy and a Feature test, wired together with a container binding and a route.

```bash
php artisan make:pattern Post
```

The generated test passes immediately, because the scaffold is connected: the repository interface is bound in the container, the policy is registered, and the controller is routed.

## Table of contents

- [Why](#why)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [What gets generated](#what-gets-generated)
- [Options](#options)
- [Domain (DDD) mode](#domain-ddd-mode)
- [Custom root namespace](#custom-root-namespace)
- [Configuration](#configuration)
- [Overriding the stubs](#overriding-the-stubs)
- [History, undo and backups](#history-undo-and-backups)
- [Logging](#logging)
- [Testing](#testing)
- [Upgrading from 0.2](#upgrading-from-02)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Why

Writing the same repository/service/controller boilerplate for every resource gets old fast, and copy-pasting an existing module invites drift between resources.

Most Laravel scaffolders are either too minimal (model + migration) or hard-code an architecture you may not use. Two things make this one different:

- **The output runs.** Generating a `PostService` that type-hints `PostRepositoryInterface` is useless if nothing binds the interface — you get a `BindingResolutionException` on the first request. This package maintains the binding, the policy registration and the route for you.
- **Every layer is config-driven.** Disable what you don't need, override any stub, point a layer at a different namespace. The generator adapts to your conventions instead of the other way around.

## Requirements

- PHP 8.2+
- Laravel 11.x, 12.x, or 13.x (PHP 8.3+ required for Laravel 13)

## Installation

```bash
composer require bouda/laravel-make-pattern
```

The service provider is auto-discovered — nothing to register manually.

```bash
# Only if your app doesn't follow the default folder conventions
php artisan vendor:publish --tag=make-pattern-config

# Only if you want to change the shape of the generated code
php artisan vendor:publish --tag=make-pattern-stubs
```

On Laravel 11+, `routes/api.php` doesn't exist until you run `php artisan install:api`. Run it **before** generating: `install:api` silently refuses to wire the file into `bootstrap/app.php` if it already exists, so the generator deliberately never creates it — it tells you to run `install:api` and skips route registration instead. Everything else is still generated.

## Usage

```bash
php artisan make:pattern Post
```

Preview without writing anything:

```bash
php artisan make:pattern Post --dry-run
```

## What gets generated

```text
app/Models/Post.php
app/Repositories/Contracts/PostRepositoryInterface.php
app/Repositories/PostRepository.php
app/Services/PostService.php
app/Http/Controllers/PostController.php
app/Http/Requests/PostStoreRequest.php
app/Http/Requests/PostUpdateRequest.php
app/Http/Resources/PostResource.php
app/Policies/PostPolicy.php
app/Providers/PatternServiceProvider.php     ← created once, appended to afterwards
database/migrations/2026_08_30_101500_create_posts_table.php
database/factories/PostFactory.php
tests/Feature/PostTest.php
```

Plus two idempotent edits:

- `routes/api.php` — `Route::apiResource('posts', PostController::class);`
- `bootstrap/providers.php` — registers `PatternServiceProvider`

`PatternServiceProvider` is the piece that makes the scaffold work. It accumulates one entry per entity:

```php
protected array $repositories = [
    \App\Repositories\Contracts\PostRepositoryInterface::class => \App\Repositories\PostRepository::class,
    // make-pattern:repositories
];

protected array $policies = [
    \App\Models\Post::class => \App\Policies\PostPolicy::class,
    // make-pattern:policies
];
```

New entries are inserted above the marker comments. The rest of the file is yours — running the command again never duplicates an entry or overwrites your edits.

## Options

| Option | Description |
| --- | --- |
| `--only=model,service` | Generate only these layers |
| `--except=test,migration` | Generate everything except these layers |
| `--domain=Blog` | Group all layers under a domain (DDD style) |
| `--namespace=Acme` | Override the root namespace (default: `App`) |
| `--path=src` | Directory the overridden root namespace maps to |
| `--force` | Overwrite existing files (the originals are backed up) |
| `--dry-run` | Print what would be written, write nothing |

An unknown layer name is an error, not a silent no-op. Valid layer names are the keys of the `layers` array in the config: `model`, `migration`, `factory`, `repository_interface`, `repository`, `service`, `controller`, `store_request`, `update_request`, `resource`, `policy`, `test`.

## Domain (DDD) mode

```bash
php artisan make:pattern Post --domain=Blog
```

```text
app/Domain/Blog/Models/Post.php
app/Domain/Blog/Repositories/Contracts/PostRepositoryInterface.php
app/Domain/Blog/Repositories/PostRepository.php
app/Domain/Blog/Services/PostService.php
app/Domain/Blog/Http/Controllers/PostController.php
app/Domain/Blog/Http/Requests/PostStoreRequest.php
app/Domain/Blog/Http/Resources/PostResource.php
app/Domain/Blog/Policies/PostPolicy.php
tests/Feature/Blog/PostTest.php
database/factories/Blog/PostFactory.php
database/migrations/..._create_posts_table.php   ← migrations stay global
```

Each layer's directory is derived from its namespace, so path and namespace can never drift apart: everything stays PSR-4 autoloadable.

Two details that matter in domain mode, and that the generator handles for you:

- **Policies** are registered explicitly in `PatternServiceProvider`, because Laravel's auto-discovery only looks for `App\Policies\PostPolicy` next to `App\Models\Post`.
- **Factories** are bound with a `newFactory()` method on the model, because factory discovery is convention-based too.

Rename the `Domain` segment (or drop it) in the config:

```php
'domain' => ['segment' => 'Modules'],   // App\Modules\Blog\Services
```

## Custom root namespace

```bash
php artisan make:pattern Post --namespace=Acme --path=src
# => src/Models/Post.php with namespace Acme\Models
```

`--path` defaults to a lowercased mirror of the namespace (`Acme` → `acme/`). Only the **primary** root moves; `Tests\` and `Database\Factories\` stay where they are. The command reminds you to add the namespace to the `psr-4` map in `composer.json`.

Combine with `--domain`:

```bash
php artisan make:pattern Post --domain=Blog --namespace=Acme
# => Acme\Domain\Blog\Models\Post
```

## Configuration

Published to `config/make-pattern.php`.

### Roots

Every layer namespace sits under a PSR-4 root. This is what keeps paths and namespaces in sync.

```php
'roots' => [
    'App' => ['path' => app_path(), 'domain' => 'prefix', 'primary' => true],
    'Tests' => ['path' => base_path('tests'), 'domain' => 'suffix'],
    'Database\\Factories' => ['path' => database_path('factories'), 'domain' => 'suffix'],
],
```

| Key | Meaning |
| --- | --- |
| `path` | The directory this namespace maps to in `composer.json` |
| `domain` | `prefix` → `App\Domain\Blog\Services`, `suffix` → `Tests\Feature\Blog`, `none` → ignore `--domain` |
| `primary` | The single root that `--namespace` rewrites |

### Everything else

| Key | Description | Default |
| --- | --- | --- |
| `layers.*.enabled` | Generate this layer or not | `true` |
| `layers.*.namespace` | PSR-4 namespace; the directory is derived from it | Laravel conventions |
| `layers.*.stub` | Stub name | per layer |
| `layers.*.suffix` | Appended to the entity name to form the class name | per layer |
| `primary_key.strategy` | `ulid`, `uuid` or `increment` — drives the model traits, the migration column **and** the `$id` type hints | `ulid` |
| `tenancy.enabled` / `tenancy.driver` | Adds the driver's trait to the model. Drivers are declarable in `tenancy.drivers` | `false` / `stancl` |
| `wrap_repository_calls` | Wrap repository writes in try/catch + `Log::error()`, rethrowing. Uses the `repository-with-logging` stub | `false` |
| `policies.enforce_in_controller` | Controller calls `Gate::authorize()` in every action. Uses the `controller-authorized` stub | `true` |
| `provider.enabled` | Maintain `PatternServiceProvider` | `true` |
| `provider.class` | Its fully qualified name | `App\Providers\PatternServiceProvider` |
| `provider.auto_register` | Add it to `bootstrap/providers.php` | `true` |
| `routes.enabled` / `routes.file` | Register a resource route for the controller | `true` / `routes/api.php` |
| `routes.method` | `apiResource` or `resource` | `apiResource` |
| `routes.middleware` | e.g. `['auth:sanctum']` | `[]` |
| `user_model` | Imported by the generated policy; `null` for no type hint | `App\Models\User` |
| `base_controller` | Extended by the generated controller; `null` to extend nothing | `App\Http\Controllers\Controller` |
| `history.path` / `history.backups` / `history.keep` | Where runs and backups are stored, and how many to keep | `storage/app/make-pattern/…`, `50` |

## Overriding the stubs

```bash
php artisan vendor:publish --tag=make-pattern-stubs
```

A published stub always wins over the packaged one, so you can override a single layer without touching the rest.

Every stub receives:

| Placeholder | Example |
| --- | --- |
| `{{ namespace }}`, `{{ class }}` | `App\Services`, `PostService` |
| `{{ entity }}`, `{{ entityVariable }}`, `{{ entitySnake }}` | `Post`, `post`, `post` |
| `{{ entityTable }}`, `{{ routeUri }}` | `posts`, `posts` |
| `{{ idType }}` | `string` or `int`, from the primary key strategy |
| `{{ <layer>Fqcn }}`, `{{ <layer>Class }}`, `{{ <layer>Namespace }}` | `{{ repositoryInterfaceFqcn }}` → `App\Repositories\Contracts\PostRepositoryInterface` |

Layer placeholders are generated from the config, so a layer you add yourself gets its own set automatically. Cross-layer references are built from the *resolved* targets, which is why a stub still imports the right class under `--domain`.

Feature flags select a stub variant before falling back to the base name, so publishing `repository-with-logging.stub` or `controller-authorized.stub` overrides just that variant.

## History, undo and backups

Every run is recorded in `storage/app/make-pattern/history.json`:

```bash
php artisan make:pattern:history
php artisan make:pattern:history --id=01ARZ3NDEKTSV4RRFFQ69G5FAV   # file-by-file
```

```text
+----------------------------+---------+--------+------------------+-------+
| ID                         | Entity  | Domain | Date             | Files |
+----------------------------+---------+--------+------------------+-------+
| 01ARZ3NDEKTSV4RRFFQ69G5FAV | Comment | —      | 2026-08-30 09:15 | 15    |
| 01ARZ3NDEK9G5FAVTSV4RRFFQ6 | Post    | Blog   | 2026-08-30 09:02 | 15    |
+----------------------------+---------+--------+------------------+-------+
```

```bash
php artisan make:pattern:undo                # the last run
php artisan make:pattern:undo --id=01ARZ...  # a specific run
```

Undo is content-aware:

- a file the run **created** is deleted;
- a file the run **overwrote** (`--force`) or **edited** (routes, provider) is **restored from its backup**, never deleted;
- a file that changed after generation is left alone and reported. Pass `--force` to roll it back anyway.

Backups live in `storage/app/make-pattern/backups/{run-id}/` and are pruned along with the history (`history.keep`, default 50 runs).

## Logging

Technical output goes to the standard `Log` facade, separate from the history above:

- `info` — start and end of a generation
- `warning` — a skipped file, a missing marker
- `error` — an exception, followed by an automatic rollback of that run

To keep generator noise out of `laravel.log`, define a `make-pattern` channel in `config/logging.php`; the package picks it up automatically.

```php
'channels' => [
    'make-pattern' => [
        'driver' => 'single',
        'path' => storage_path('logs/make-pattern.log'),
        'level' => 'info',
    ],
],
```

## Testing

```bash
composer test
```

Tests run against [Orchestra Testbench](https://github.com/orchestral/testbench), so no full Laravel app is needed.

## Upgrading from 0.2

Version 0.3 is a breaking release.

- **Re-publish your config** (`vendor:publish --tag=make-pattern-config --force`). Layers now declare a `namespace` and the directory is derived from it; `layers.*.path` is only used by layers without a namespace, like `migration`.
- **Re-publish your stubs** if you had published them. The `{{ domainNamespace }}` and `{{ rootNamespace }}` placeholders are gone, replaced by the per-layer `{{ <layer>Fqcn }}` set.
- `log_path` is replaced by `history.path`. The old key is still honoured if present.
- `primary_key.strategy` and `tenancy.*` were documented but not implemented in 0.2. They now work — check that the defaults match what your 0.2 code actually generated (`ulid`).
- Older history entries are still readable, but they carry no backups, so undoing a pre-0.3 run can only delete.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

Issues and pull requests are welcome. Please run `composer test` before submitting a PR.

## Security

If you discover a security issue, please open a GitHub issue rather than a public PR with exploit details.

## Credits

- [Bouda-Bissari](https://github.com/Bouda-Bissari)

## License

The MIT License (MIT). See [LICENSE](LICENSE) for more information.
