<?php

namespace Bouda\MakePattern\Generators;

use Bouda\MakePattern\Support\StubReplacer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileGenerator
{
    /**
     * Generate one file for a given entity/layer combination.
     *
     * @param  string|null  $domainPrefix   e.g. "Blog" - generates in app/Domain/Blog/{layer}/
     * @param  string|null  $rootNamespace  e.g. "Acme" - replaces "App" in all namespaces
     * @return string|null The path of the file written, or null if skipped (already exists, not forced).
     */
    public function generate(
        string $entityName,
        array $layerConfig,
        string $stubPath,
        bool $force = false,
        ?string $domainPrefix = null,
        ?string $rootNamespace = null,
    ): ?string {
        $config = $this->resolveConfig($layerConfig, $domainPrefix, $rootNamespace);

        $className = Str::studly($entityName) . $config['suffix'];
        $filePath  = rtrim($config['path'], '/') . '/' . $className . '.php';

        if (File::exists($filePath) && ! $force) {
            return null;
        }

        $stubContent = File::get($stubPath);

        // modelNamespace: where the Model class lives (e.g. App\Models or App\Domain\Blog\Models)
        $modelNamespace = $this->buildModelNamespace($domainPrefix, $rootNamespace);

        // domainNamespace: root prefix for all layers in this run
        // e.g. App\Domain\Blog (when --domain=Blog) or just App (default)
        $root = $rootNamespace ?? 'App';
        $domainNamespace = $domainPrefix !== null
            ? $root . '\\Domain\\' . Str::studly($domainPrefix)
            : $root;

        $filled = StubReplacer::fill($stubContent, [
            'namespace'       => $config['namespace'],
            'modelNamespace'  => $modelNamespace,
            'rootNamespace'   => $root,
            'domainNamespace' => $domainNamespace,
            'class'           => $className,
            'entity'          => Str::studly($entityName),
            'entityVariable'  => Str::camel($entityName),
            'entityTable'     => Str::snake(Str::pluralStudly($entityName)),
        ]);

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $filled);

        return $filePath;
    }

    /**
     * Resolve the effective path and namespace for a layer,
     * applying domain and/or namespace overrides.
     */
    protected function resolveConfig(
        array $layerConfig,
        ?string $domainPrefix,
        ?string $rootNamespace,
    ): array {
        $config = $layerConfig;

        if ($rootNamespace !== null) {
            $config['namespace'] = preg_replace('/^App(\\\\|$)/', $rootNamespace . '$1', $config['namespace']);
            $config['path']      = preg_replace(
                '#' . preg_quote(app_path(), '#') . '#',
                base_path(lcfirst($rootNamespace)),
                $config['path'],
            );
        }

        if ($domainPrefix !== null) {
            $studlyDomain = Str::studly($domainPrefix);
            // Replace root namespace with root\Domain\{Domain}\rest
            $config['namespace'] = preg_replace(
                '/^([A-Za-z\\\\]+?)(\\\\|$)/',
                '$1\\Domain\\' . $studlyDomain . '$2',
                $config['namespace'],
            );
            // Rebuild path: app/Domain/{Domain}/{layer-folder}
            $config['path'] = app_path('Domain/' . $studlyDomain . '/' . basename($config['path']));
        }

        return $config;
    }

    /**
     * Determine the namespace where the generated Model will live.
     */
    protected function buildModelNamespace(
        ?string $domainPrefix,
        ?string $rootNamespace,
    ): string {
        $root = $rootNamespace ?? 'App';

        if ($domainPrefix !== null) {
            return $root . '\\Domain\\' . Str::studly($domainPrefix) . '\\Models';
        }

        return $root . '\\Models';
    }
}