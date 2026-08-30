<?php

namespace Bouda\MakePattern\Support;

use RuntimeException;

/**
 * Locates the stub file for a layer.
 *
 * A published stub always wins over the packaged one, and feature flags select
 * a variant (repository-with-logging, controller-authorized) before falling
 * back to the base stub. Publishing a variant file is enough to override it.
 */
final class StubResolver
{
    public function __construct(
        private readonly array $config,
        private readonly string $publishedDirectory,
        private readonly string $packageDirectory,
    ) {
    }

    /**
     * @throws RuntimeException when the layer declares no stub, or none exists.
     */
    public function pathFor(string $layerKey, array $layer): string
    {
        if (empty($layer['stub'])) {
            throw new RuntimeException(
                "Layer [{$layerKey}] has no 'stub' key in config/make-pattern.php. "
                .'If you published the config with an older version of this package, re-publish it with '
                .'`php artisan vendor:publish --tag=make-pattern-config --force`.'
            );
        }

        $tried = [];

        foreach ($this->candidates($layerKey, $layer['stub']) as $name) {
            foreach ([$this->publishedDirectory, $this->packageDirectory] as $directory) {
                $path = rtrim($directory, '/\\').'/'.$name.'.stub';
                $tried[] = $path;

                if (is_file($path)) {
                    return $path;
                }
            }
        }

        throw new RuntimeException(
            "No stub found for layer [{$layerKey}]. Looked for: ".implode(', ', array_unique($tried))
        );
    }

    /**
     * Stub names to try, most specific first.
     *
     * @return list<string>
     */
    private function candidates(string $layerKey, string $stub): array
    {
        $candidates = [];

        if ($layerKey === 'repository' && ($this->config['wrap_repository_calls'] ?? false)) {
            $candidates[] = $stub.'-with-logging';
        }

        if ($layerKey === 'controller' && ($this->config['policies']['enforce_in_controller'] ?? false)) {
            $candidates[] = $stub.'-authorized';
        }

        $candidates[] = $stub;

        return $candidates;
    }
}
