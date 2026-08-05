# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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