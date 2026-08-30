<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a layer's configuration into a concrete file destination.
 *
 * A layer declares only a namespace. Its directory is derived from that
 * namespace relative to its PSR-4 root, which is what makes --domain and
 * --namespace safe: both path and namespace are rebuilt from one segment list.
 */
final class LayerResolver
{
    public function __construct(
        private readonly array $config,
        private readonly string $basePath,
    ) {
    }

    public function resolve(
        string $key,
        array $layer,
        EntityName $entity,
        GenerationOptions $options,
    ): LayerTarget {
        $className = $entity->studly.($layer['suffix'] ?? '');

        $replacements = [
            'class' => $className,
            'entity' => $entity->studly,
            'entityTable' => $entity->table,
            'timestamp' => date('Y_m_d_His'),
        ];

        $fileName = StubReplacer::fill($layer['file_name'] ?? '{{ class }}', $replacements);

        $existsGlob = isset($layer['exists_glob'])
            ? StubReplacer::fill($layer['exists_glob'], $replacements)
            : null;

        // Layers without a namespace (migrations) are placed verbatim and are
        // never moved by --domain or --namespace: they are global to the app.
        if (($layer['namespace'] ?? null) === null) {
            if (empty($layer['path'])) {
                throw new RuntimeException(
                    "Layer [{$key}] has no namespace, so it must declare an explicit 'path' in config/make-pattern.php."
                );
            }

            $directory = rtrim(str_replace('\\', '/', $layer['path']), '/');

            return new LayerTarget(
                key: $key,
                namespace: null,
                directory: $directory,
                className: $className,
                path: $directory.'/'.$fileName.'.php',
                existsGlob: $existsGlob,
            );
        }

        [$rootNamespace, $rootPath, $domainStrategy] = $this->rootFor($key, $layer);

        $relative = $this->relativeSegments($layer['namespace'], $rootNamespace);

        // --namespace only rewrites the primary root (App by default).
        if ($options->rootNamespace !== null && $this->isPrimary($rootNamespace)) {
            $rootNamespace = $options->rootNamespace;
            $rootPath = $options->resolvedRootPath($this->basePath);
        }

        $segments = $this->applyDomain($relative, $domainStrategy, $options->domain);

        $namespace = $rootNamespace;
        $directory = rtrim(str_replace('\\', '/', $rootPath), '/');

        foreach ($segments as $segment) {
            $namespace .= '\\'.$segment;
            $directory .= '/'.$segment;
        }

        return new LayerTarget(
            key: $key,
            namespace: $namespace,
            directory: $directory,
            className: $className,
            path: $directory.'/'.$fileName.'.php',
            existsGlob: $existsGlob,
        );
    }

    /**
     * Map a fully qualified class name onto a file path using the configured roots.
     *
     * Used for classes the generator maintains but does not treat as a layer,
     * such as the generated service provider.
     */
    public function pathForClass(string $fqcn): string
    {
        $fqcn = trim($fqcn, '\\');
        $namespace = Str::beforeLast($fqcn, '\\');

        [$rootNamespace, $rootPath] = $this->rootFor('provider', ['namespace' => $namespace]);

        $segments = $this->relativeSegments($namespace, $rootNamespace);
        $directory = rtrim(str_replace('\\', '/', $rootPath), '/');

        foreach ($segments as $segment) {
            $directory .= '/'.$segment;
        }

        return $directory.'/'.class_basename($fqcn).'.php';
    }

    /**
     * Locate the PSR-4 root a layer namespace belongs to (longest match wins).
     *
     * A layer under no configured root becomes its own root, using its declared
     * path. That keeps custom layers working instead of silently corrupting them.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function rootFor(string $key, array $layer): array
    {
        $namespace = trim($layer['namespace'], '\\');
        $best = null;

        foreach ($this->config['roots'] ?? [] as $rootNamespace => $root) {
            $rootNamespace = trim($rootNamespace, '\\');

            if ($namespace !== $rootNamespace && ! Str::startsWith($namespace, $rootNamespace.'\\')) {
                continue;
            }

            if ($best === null || strlen($rootNamespace) > strlen($best[0])) {
                $best = [$rootNamespace, $root['path'] ?? null, $root['domain'] ?? 'prefix'];
            }
        }

        if ($best === null) {
            if (empty($layer['path'])) {
                throw new RuntimeException(
                    "Layer [{$key}] uses namespace [{$namespace}], which matches no root in config/make-pattern.php. "
                    ."Add it to the 'roots' array, or give the layer an explicit 'path'."
                );
            }

            return [$namespace, $layer['path'], 'prefix'];
        }

        if (empty($best[1])) {
            throw new RuntimeException("Root [{$best[0]}] in config/make-pattern.php has no 'path'.");
        }

        return $best;
    }

    private function isPrimary(string $rootNamespace): bool
    {
        foreach ($this->config['roots'] ?? [] as $candidate => $root) {
            if (trim($candidate, '\\') === $rootNamespace) {
                return (bool) ($root['primary'] ?? false);
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function relativeSegments(string $namespace, string $rootNamespace): array
    {
        $namespace = trim($namespace, '\\');

        if ($namespace === $rootNamespace) {
            return [];
        }

        return array_values(array_filter(
            explode('\\', substr($namespace, strlen($rootNamespace) + 1))
        ));
    }

    /**
     * @param  list<string>  $relative
     * @return list<string>
     */
    private function applyDomain(array $relative, string $strategy, ?string $domain): array
    {
        if ($domain === null || $strategy === 'none') {
            return $relative;
        }

        // Appended layers (tests, factories) read better as Tests\Feature\Blog
        // than Tests\Feature\Domain\Blog, so the segment applies to prefixes only.
        if ($strategy === 'suffix') {
            return [...$relative, $domain];
        }

        $segment = $this->config['domain']['segment'] ?? 'Domain';

        $domainSegments = $segment === '' || $segment === null
            ? [$domain]
            : [Str::studly($segment), $domain];

        return [...$domainSegments, ...$relative];
    }
}
