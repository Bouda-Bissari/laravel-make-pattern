<?php

namespace Bouda\MakePattern\Commands;

use Bouda\MakePattern\Generators\FileGenerator;
use Bouda\MakePattern\Support\GenerationLog;
use Bouda\MakePattern\Support\MakePatternLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakePatternCommand extends Command
{
    protected $signature = 'make:pattern
        {name : The entity name, e.g. Post}
        {--only= : Comma-separated list of layers to generate (default: all enabled in config)}
        {--force : Overwrite files that already exist}';

    protected $description = 'Generate a full CRUD scaffold (Model, Repository, Service, Controller, Requests, Resource, Policy, Test) from a single command.';

    public function handle(FileGenerator $generator, GenerationLog $log): int
    {
        $name = $this->argument('name');
        $layers = config('make-pattern.layers', []);

        if ($only = $this->option('only')) {
            $wanted = array_map('trim', explode(',', $only));
            $layers = array_intersect_key($layers, array_flip($wanted));
        }

        $runId = Str::ulid()->toString();
        $force = (bool) $this->option('force');

        MakePatternLogger::info('Starting make:pattern generation', [
            'run_id' => $runId,
            'entity' => $name,
            'layers' => array_keys(array_filter($layers, fn ($layer) => $layer['enabled'] ?? true)),
            'force' => $force,
        ]);

        $createdFiles = [];

        foreach ($layers as $layerKey => $layerConfig) {
            if (! ($layerConfig['enabled'] ?? true)) {
                continue;
            }

            $stubName = $layerConfig['stub'];

            if ($layerKey === 'repository' && config('make-pattern.wrap_repository_calls', false)) {
                $stubName = 'repository-with-logging';
            }

            $stubPath = $this->resolveStubPath($stubName);

            if (! File::exists($stubPath)) {
                $message = "Stub not found for layer [{$layerKey}], ignored: {$stubPath}";
                $this->warn($message);
                MakePatternLogger::warning($message, ['run_id' => $runId, 'layer' => $layerKey]);

                continue;
            }

            try {
                $path = $generator->generate($name, $layerConfig, $stubPath, $force);

                if ($path === null) {
                    $message = "Skipping layer [{$layerKey}] (already exists, use --force)";
                    $this->line("  <fg=yellow>skip</> {$layerKey}");
                    MakePatternLogger::warning($message, ['run_id' => $runId, 'layer' => $layerKey]);

                    continue;
                }

                $createdFiles[] = $path;
                $this->line("  <fg=green>created</> {$path}");
                MakePatternLogger::info('File generated', [
                    'run_id' => $runId,
                    'layer' => $layerKey,
                    'path' => $path,
                ]);
            } catch (\Throwable $e) {
                $message = "Exception while generating layer [{$layerKey}] for entity [{$name}]: {$e->getMessage()}";

                $this->error($message);
                MakePatternLogger::error($message, [
                    'run_id' => $runId,
                    'layer' => $layerKey,
                    'exception' => $e->getTraceAsString(),
                ]);

                $this->rollback($runId, $createdFiles);

                return self::FAILURE;
            }
        }

        if (empty($createdFiles)) {
            $message = "No files generated for [{$name}], run_id [{$runId}].";
            $this->warn('No files generated.');
            MakePatternLogger::info($message, ['run_id' => $runId, 'entity' => $name]);

            return self::SUCCESS;
        }

        $entry = $log->record($runId, $name, $createdFiles);

        $this->info($entry['id'] . ' — ' . count($createdFiles) . ' file(s) generated for [' . $name . ']. Use `make:pattern:undo` to undo.');
        MakePatternLogger::info("make:pattern generation complete", [
            'run_id' => $entry['id'],
            'entity' => $name,
            'files' => count($createdFiles),
        ]);

        return self::SUCCESS;
    }

    protected function rollback(string $runId, array $createdFiles): void
    {
        foreach ($createdFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
                MakePatternLogger::warning('Removed partially generated file during rollback', [
                    'run_id' => $runId,
                    'path' => $file,
                ]);
            }
        }
    }

    protected function resolveStubPath(string $stubName): string
    {
        $published = resource_path("stubs/vendor/make-pattern/{$stubName}.stub");

        if (File::exists($published)) {
            return $published;
        }

        return __DIR__ . '/../../stubs/' . $stubName . '.stub';
    }
}
