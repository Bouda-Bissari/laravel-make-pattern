# Laravel Make Pattern

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bouda/laravel-make-pattern.svg?style=flat-square)](https://packagist.org/packages/bouda/laravel-make-pattern)
[![Tests](https://img.shields.io/github/actions/workflow/status/Bouda-Bissari/laravel-make-pattern/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Bouda-Bissari/laravel-make-pattern/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/bouda/laravel-make-pattern.svg?style=flat-square)](https://packagist.org/packages/bouda/laravel-make-pattern)
[![License](https://img.shields.io/packagist/l/bouda/laravel-make-pattern.svg?style=flat-square)](LICENSE)

Generate a full CRUD scaffold — Model, Repository (+ interface), Service, Controller, Form Requests, API Resource, Policy, and a Feature test — from a single Artisan command.

```bash
php artisan make:pattern Post
```

turns into nine consistent, ready-to-edit files, generated from stubs you fully control.

## Table of contents

- [Why](#why)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Generation history & rollback](#generation-history--rollback)
- [Logging](#logging)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Why

Writing the same repository/service/controller boilerplate for every resource gets old fast, and copy-pasting an existing module invites drift between resources. This package generates all the layers consistently, from stubs you own — no npm-package-style versioning overhead, no opinionated abstraction you can't see or change.

It targets a specific gap: existing Laravel scaffolders are either too minimal (model + migration only) or hard-code an architecture you may not use. Here, every layer is config-driven — disable what you don't need, override any stub, and the generator adapts to your project's conventions instead of the other way around.

## Requirements

- PHP 8.2+
- Laravel 10.x or 11.x

## Installation

```bash
composer require bouda/laravel-make-pattern
```

The service provider is auto-discovered — nothing to register manually.

Publish the config if your app doesn't follow the default folder conventions:

```bash
php artisan vendor:publish --tag=make-pattern-config
```

Publish the stubs if you want to override the generated code's structure:

```bash
php artisan vendor:publish --tag=make-pattern-stubs
```

Any stub you publish takes priority over the package's built-in one, so you can override a single layer (e.g. just the Controller) without touching the rest.

## Usage

```bash
php artisan make:pattern Post
```

Generates:

```
app/Models/Post.php
app/Repositories/Contracts/PostRepositoryInterface.php
app/Repositories/PostRepository.php
app/Services/PostService.php
app/Http/Controllers/PostController.php
app/Http/Requests/PostStoreRequest.php
app/Http/Requests/PostUpdateRequest.php
app/Http/Resources/PostResource.php
app/Policies/PostPolicy.php
tests/Feature/PostTest.php
```

For example, the generated repository looks like this:

```php
<?php

namespace App\Repositories;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;

class PostRepository implements PostRepositoryInterface
{
    public function all()
    {
        return Post::all();
    }

    public function find(string $id)
    {
        return Post::findOrFail($id);
    }

    // ...
}
```

### Options

| Option | Description |
|---|---|
| `--only=model,service` | Only generate the specified layers |
| `--force` | Overwrite files that already exist |

## Configuration

Published to `config/make-pattern.php`:

| Key | Description | Default |
|---|---|---|
| `layers.*.enabled` | Enable/disable a given layer (model, repository, service, controller, requests, resource, policy, test) | `true` |
| `layers.*.namespace` / `layers.*.path` | Namespace and output path per layer | Laravel default conventions |
| `primary_key.strategy` | `increment`, `uuid`, or `ulid` | `ulid` |
| `tenancy.enabled` / `tenancy.driver` | Adapt generated Model/Repository for a tenant-scoped app (`stancl` or `spatie`) | `false` |
| `wrap_repository_calls` | Wrap generated Repository methods in try/catch with `Log::error()` before rethrowing (uses `repository-with-logging` stub instead of `repository`) | `false` |
| `log_path` | Deprecated single-run log path, kept for backward compatibility | `storage/app/make-pattern/last-run.json` |

This is what makes the generator portable across projects and teams rather than tied to one codebase's conventions.

## Generation history & rollback

Every run is recorded in an append-only audit log (`storage/app/make-pattern/history.json`):

```bash
php artisan make:pattern:history
```

```
+----+------------+------------------+-------------+
| ID | Entity     | Date             | Files       |
+----+------------+------------------+-------------+
| 3  | Comment    | 2026-08-03 09:15 | 9           |
| 2  | Category   | 2026-08-03 09:10 | 9           |
| 1  | Post       | 2026-08-03 09:02 | 9           |
+----+------------+------------------+-------------+
```

Undo the last run:

```bash
php artisan make:pattern:undo
```

Or a specific run by id — files modified since generation are left untouched and reported rather than silently deleted:

```bash
php artisan make:pattern:undo --id=01ARZ3NDEKTSV4RRFFQ69G5FAV
```

## Logging

Technical/debug output goes to the standard `Log` facade, separate from the audit history above:

- `info` — start and end of a generation (entity, layers, run id, file count)
- `warning` — missing stub, or a file skipped because it already exists (use `--force`)
- `error` — an exception during file write (message + stack trace), followed by a clean stop

By default these use the app's default log channel. To keep generator noise out of `laravel.log`, define a dedicated channel in your app's `config/logging.php`:

```php
'channels' => [
    'make-pattern' => [
        'driver' => 'single',
        'path' => storage_path('logs/make-pattern.log'),
        'level' => 'info',
    ],
],
```

The package uses `make-pattern` automatically when that channel is defined.

## Testing

```bash
composer test
```

Tests run against [Orchestra Testbench](https://github.com/orchestral/testbench), so no full Laravel app is needed.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a history of changes.

## Contributing

Issues and pull requests are welcome. Please run `composer test` before submitting a PR.

## Security

If you discover a security issue, please open a GitHub issue rather than a public PR with exploit details.

## Credits

- [Bouda-Bissari](https://github.com/Bouda-Bissari)

## License

The MIT License (MIT). See [LICENSE](LICENSE) for more information.