# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v0.3.0] - 2026-08-30

Breaking release. It closes the gap between what the package documented and what it actually did, and makes the generated scaffold run out of the box.

### Fixed
- **The generated code now runs.** `PostService` type-hints `PostRepositoryInterface`, but nothing bound the interface, so the first request threw `BindingResolutionException`. The generator maintains a `PatternServiceProvider` holding the bindings and registers it in `bootstrap/providers.php`.
- **`--domain` no longer breaks PSR-4.** Nested layers lost their parent segment: the repository interface landed in `app/Domain/Blog/Contracts/` while declaring `App\Domain\Blog\Repositories\Contracts`, so composer could not autoload it. Controllers, Requests and Resources had the same defect. A layer's directory is now derived from its namespace, so the two cannot drift apart.
- **The `test` layer under `--domain`** was written to `app/Domain/Blog/Feature/` with namespace `Tests\Domain\Blog\Feature` — neither valid. It now goes to `tests/Feature/Blog/`.
- **`--force` followed by `undo` destroyed hand-written files.** An overwritten file was recorded as "created" and deleted on rollback. Overwritten files are backed up first and restored, never deleted.
- **Rollback after a mid-run exception** deleted overwritten files instead of restoring them.
- **Path traversal.** `make:pattern "../../../pwned"` wrote outside the project. Entity names are validated, and reserved words or names starting with a digit (which produced unparseable PHP) are refused.
- **`primary_key.strategy` and `tenancy.*` were documented but never read by any code.** The model always used `HasUlids`. Both now drive the generated model, migration and `$id` type hints.
- **An unknown `--only` value** generated nothing and exited 0. Unknown layer names are now an error listing the valid ones.
- **A layer missing its `stub` key** (a config published by an older version) crashed with an uncaught `Undefined array key`, skipping rollback. It now reports the problem and how to fix it.
- **`preg_replace` with a Windows path as the replacement** corrupted directories: `C:\Projects4cme` became `C:\Projects24cme`, because `` was read as a backreference. All path rewriting uses string operations.
- **Undo used `filemtime` equality** to decide whether a file had changed, which a formatter or a `git checkout` defeats. It compares content hashes.
- **A partial run wired up classes it did not generate.** `--only=model` still added a route and a binding pointing at a non-existent controller and repository, breaking every request. Registration now only covers layers that are actually on disk.
- The generated feature test called an API route that was never registered, so it always failed.

### Added
- **Migration and Factory layers.** The model declares `newFactory()` so factories keep working under `--domain`, where discovery by convention does not.
- **Route registration** in `routes/api.php`, idempotent, with configurable middleware and `apiResource`/`resource`.
- **Policy registration** in the generated provider, which is what makes policies work under `--domain`.
- `--except` to skip layers, `--dry-run` to preview, `--path` to choose the directory an overridden root namespace maps to.
- `make:pattern:history --id=` to list a single run file by file, and a `Domain` column in the listing.
- `make:pattern:undo --force` to roll back files edited after generation.
- Backups under `storage/app/make-pattern/backups/`, pruned with the history (`history.keep`).
- Configurable `roots` (the PSR-4 map the generator reasons about), `domain.segment`, `user_model`, `base_controller`, and `tenancy.drivers` for declaring your own driver.
- `policies.enforce_in_controller`: the controller calls `Gate::authorize()` in every action, via the `controller-authorized` stub.
- Every layer exposes `{{ <layer>Fqcn }}`, `{{ <layer>Class }}` and `{{ <layer>Namespace }}` to the stubs, generated from the config, so a layer you add yourself gets placeholders too.
- Return types and `Collection` generics throughout the generated repository, service and controller.

### Changed
- `log_path` is superseded by `history.path`. The old key is still honoured.
- Layers declare a `namespace`; `path` is only read for layers without one, such as `migration`.
- The generator no longer creates `routes/api.php` itself: `php artisan install:api` refuses to wire the file into `bootstrap/app.php` when it already exists, which would leave the routes permanently unreachable. It reports what to run instead.

### Removed
- The `{{ domainNamespace }}` and `{{ rootNamespace }}` stub placeholders, replaced by the per-layer set. `{{ rootNamespace }}` was never used by any stub.

## [v0.2.0] - 2026-08-05

### Added
- `--domain=<name>` option: generates all layers under `app/Domain/{name}/` with the matching namespaces (`App\Domain\{name}\Models`, etc.) — native DDD support.
- `--namespace=<name>` option: overrides the root namespace globally (default: `App`). Compatible with `--domain`.
- Stub variable `{{ modelNamespace }}`: replaces hardcoded `App\Models` imports with a dynamic placeholder across all stubs (repository, policy, etc.).
- Stub variable `{{ domainNamespace }}`: the root namespace prefix for all layers in a given run (e.g. `App\Domain\Blog`), used in controller and service stubs.
- Unit tests: `StubReplacerTest` (6 cases) and `FileGeneratorTest` (5 cases) covering the new domain/namespace options.
- Laravel 13 support in CI (testbench `^11.0`); PHP 8.2 × Laravel 13 excluded (L13 requires PHP 8.3+).

### Changed
- All stubs (`repository`, `policy`, `service`, `controller`, `repository-with-logging`) now use dynamic stub variables instead of hardcoded `App\`.
- Test suite split into two PHPUnit suites: `Unit` and `Feature`.

### Removed
- Laravel 10 dropped from CI matrix and `composer.json` (Laravel 10 is end-of-life).

## [v0.1.0] - 2026-08-03

### Added
- `make:pattern` command: generates a full CRUD scaffold (Model, Repository + interface, Service, Controller, Form Requests, API Resource, Policy, Feature test) from a single Artisan command.
- `--only=` option to limit generation to specific layers.
- `--force` option to overwrite already existing files.
- `make:pattern:undo` command: rolls back the last run; `--id=` option targets a specific run and warns when a file has been modified since it was generated.
- `make:pattern:history` command: lists the generation history (append-only log at `storage/app/make-pattern/history.json`).
- Logging through the `Log` facade (info / warning / error), with an opt-in `make-pattern` channel when defined in the host app, falling back to the default channel.
- `wrap_repository_calls` config option (boolean, default `false`): wraps `create` / `update` / `delete` repository calls in a try/catch that logs before rethrowing (uses the `repository-with-logging` stub).