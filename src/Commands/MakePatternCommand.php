<?php

namespace Bouda\MakePattern\Commands;

use Bouda\MakePattern\Generators\FileGenerator;
use Bouda\MakePattern\Generators\ProviderRegistrar;
use Bouda\MakePattern\Generators\RouteRegistrar;
use Bouda\MakePattern\Support\EntityName;
use Bouda\MakePattern\Support\FileAction;
use Bouda\MakePattern\Support\GenerationLog;
use Bouda\MakePattern\Support\GenerationOptions;
use Bouda\MakePattern\Support\LayerResolver;
use Bouda\MakePattern\Support\LayerTarget;
use Bouda\MakePattern\Support\MakePatternLogger;
use Bouda\MakePattern\Support\Rollback;
use Bouda\MakePattern\Support\StubResolver;
use Bouda\MakePattern\Support\StubVariables;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class MakePatternCommand extends Command
{
    protected $signature = 'make:pattern
        {name : The entity name, e.g. Post}
        {--only= : Comma-separated layers to generate (default: every enabled layer)}
        {--except= : Comma-separated layers to skip}
        {--domain= : Group all layers under a domain, DDD style}
        {--namespace= : Override the root namespace (default: App)}
        {--path= : Directory the overridden root namespace maps to}
        {--force : Overwrite files that already exist (a backup is kept)}
        {--dry-run : Show what would be generated without writing anything}';

    protected $description = 'Generate a full CRUD scaffold (Model, Migration, Factory, Repository, Service, Controller, Requests, Resource, Policy, Test) from a single command.';

    public function handle(FileGenerator $generator, GenerationLog $log): int
    {
        $config = config('make-pattern');

        try {
            $entity = EntityName::make($this->argument('name'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $options = GenerationOptions::make(
            domain: $this->option('domain'),
            rootNamespace: $this->option('namespace'),
            rootPath: $this->option('path'),
            force: (bool) $this->option('force'),
        );

        $dryRun = (bool) $this->option('dry-run');
        $resolver = new LayerResolver($config, base_path());
        $stubs = new StubResolver(
            $config,
            resource_path('stubs/vendor/make-pattern'),
            dirname(__DIR__, 2).'/stubs',
        );

        try {
            $selected = $this->selectedLayers($config['layers'] ?? []);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Every layer is resolved, not just the selected ones, so a stub can
        // still reference a class from a layer excluded by --only.
        try {
            $targets = [];

            foreach ($config['layers'] ?? [] as $key => $layer) {
                $targets[$key] = $resolver->resolve($key, $layer, $entity, $options);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $variables = new StubVariables($config, $targets, $entity);
        $runId = Str::ulid()->toString();

        MakePatternLogger::info('Starting make:pattern generation', [
            'run_id' => $runId,
            'entity' => $entity->studly,
            'domain' => $options->domain,
            'namespace' => $options->rootNamespace,
            'layers' => array_keys($selected),
            'force' => $options->force,
            'dry_run' => $dryRun,
        ]);

        $written = [];

        try {
            foreach ($selected as $key => $layer) {
                $target = $targets[$key];
                $existing = $target->existingPath();

                if ($existing !== null && ! $options->force) {
                    $this->line("  <fg=yellow>skip</>    {$this->relative($existing)} <fg=gray>(exists, use --force)</>");
                    MakePatternLogger::warning("Skipped layer [{$key}]: file already exists", [
                        'run_id' => $runId,
                        'path' => $existing,
                    ]);

                    continue;
                }

                $stubPath = $stubs->pathFor($key, $layer);
                $path = $existing ?? $target->path;

                if ($dryRun) {
                    $this->line("  <fg=cyan>would write</> {$this->relative($path)}");

                    continue;
                }

                $backup = $existing !== null ? $log->backup($runId, $existing) : null;

                $generator->write(
                    $existing !== null ? $this->retarget($target, $existing) : $target,
                    $stubPath,
                    $variables->forTarget($target),
                );

                $written[] = [
                    'path' => $path,
                    'action' => $existing !== null ? FileAction::OVERWRITTEN : FileAction::CREATED,
                    'backup' => $backup,
                ];

                $label = $existing !== null ? '<fg=yellow>replaced</>' : '<fg=green>created</> ';
                $this->line("  {$label} {$this->relative($path)}");
            }

            $notes = [];

            if (! $dryRun) {
                $shared = $variables->forTarget($targets['model'] ?? reset($targets));

                foreach ($this->registrars($config, $resolver, $stubs, $shared, $runId, $log, $targets) as $result) {
                    $written = array_merge($written, $result['files']);
                    $notes = array_merge($notes, $result['notes']);

                    foreach ($result['files'] as $file) {
                        $verb = $file['action'] === FileAction::CREATED ? '<fg=green>created</> ' : '<fg=blue>updated</> ';
                        $this->line("  {$verb} {$this->relative($file['path'])}");
                    }
                }
            }
        } catch (Throwable $e) {
            $this->error("Generation failed: {$e->getMessage()}");
            MakePatternLogger::error('make:pattern failed, rolling back', [
                'run_id' => $runId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->rollback($written);

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run — nothing was written.');

            return self::SUCCESS;
        }

        if ($written === []) {
            $this->warn('Nothing to generate: every target already exists. Use --force to overwrite.');

            return self::SUCCESS;
        }

        $entry = $log->record($runId, $entity->studly, $written, [], [
            'domain' => $options->domain,
            'namespace' => $options->rootNamespace,
        ]);

        $this->newLine();
        $this->info(count($written).' file(s) written for ['.$entity->studly.'].');
        $this->line("Run <fg=cyan>php artisan make:pattern:undo --id={$entry['id']}</> to reverse it.");

        foreach (array_unique($notes) as $note) {
            $this->warn("  ! {$note}");
        }

        if ($options->rootNamespace !== null) {
            $this->warn('  ! Add "'.$options->rootNamespace.'\\\\": "'
                .$this->relative($options->resolvedRootPath(base_path())).'/" to the psr-4 autoload map in composer.json.');
        }

        MakePatternLogger::info('make:pattern generation complete', [
            'run_id' => $entry['id'],
            'entity' => $entity->studly,
            'files' => count($written),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<array{files: list<array<string, mixed>>, notes: list<string>}>
     */
    private function registrars(
        array $config,
        LayerResolver $resolver,
        StubResolver $stubs,
        array $variables,
        string $runId,
        GenerationLog $log,
        array $targets,
    ): array {
        $backup = fn (string $path) => $log->backup($runId, $path);
        $results = [];

        // Only wire up classes that are actually on disk. Registering a binding
        // or a route for a layer that --only excluded points the application at
        // a class that does not exist, and every request then fails.
        $exists = fn (string $key) => isset($targets[$key]) && $targets[$key]->existingPath() !== null;

        $provider = new ProviderRegistrar($config, $resolver, base_path());

        if ($provider->enabled()) {
            $results[] = $provider->sync(
                $variables,
                $stubs->pathFor('provider', ['stub' => 'provider']),
                $backup,
                bindRepository: $exists('repository_interface') && $exists('repository'),
                registerPolicy: $exists('model') && $exists('policy'),
            );
        }

        $routes = new RouteRegistrar($config, base_path());

        if ($routes->enabled() && $exists('controller')) {
            $results[] = $routes->sync($variables, $backup);
        }

        return $results;
    }

    /**
     * Reuse the path of an existing file (a migration carries a timestamp that
     * must not change when it is regenerated with --force).
     */
    private function retarget(LayerTarget $target, string $path): LayerTarget
    {
        return new LayerTarget(
            key: $target->key,
            namespace: $target->namespace,
            directory: $target->directory,
            className: $target->className,
            path: $path,
            existsGlob: $target->existsGlob,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $layers
     * @return array<string, array<string, mixed>>
     */
    private function selectedLayers(array $layers): array
    {
        $enabled = array_filter($layers, fn (array $layer) => $layer['enabled'] ?? true);

        $only = $this->parseList($this->option('only'));
        $except = $this->parseList($this->option('except'));

        foreach ([...$only, ...$except] as $name) {
            if (! array_key_exists($name, $layers)) {
                throw new InvalidArgumentException(
                    "Unknown layer [{$name}]. Available layers: ".implode(', ', array_keys($layers)).'.'
                );
            }
        }

        if ($only !== []) {
            $enabled = array_intersect_key($layers, array_flip($only));
        }

        if ($except !== []) {
            $enabled = array_diff_key($enabled, array_flip($except));
        }

        if ($enabled === []) {
            throw new InvalidArgumentException('No layer left to generate. Check --only, --except and the enabled flags in config/make-pattern.php.');
        }

        return $enabled;
    }

    /**
     * @return list<string>
     */
    private function parseList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * @param  list<array{path: string, action: string, backup: ?string}>  $written
     */
    private function rollback(array $written): void
    {
        $files = array_map(fn (array $file) => $file + ['hash' => null], $written);

        foreach (Rollback::apply($files, verifyHash: false) as $result) {
            $this->line("  <fg=red>rolled back</> {$this->relative($result['path'])} ({$result['result']})");
        }
    }

    private function relative(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalised = str_replace('\\', '/', $path);

        return str_starts_with($normalised, $base)
            ? substr($normalised, strlen($base))
            : $normalised;
    }
}
