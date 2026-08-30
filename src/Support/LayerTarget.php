<?php

namespace Bouda\MakePattern\Support;

/**
 * A fully resolved destination for one generated file.
 *
 * Path and namespace are always derived from the same segment list, so they
 * cannot drift apart and break PSR-4 autoloading.
 */
final class LayerTarget
{
    public function __construct(
        public readonly string $key,
        public readonly ?string $namespace,
        public readonly string $directory,
        public readonly string $className,
        public readonly string $path,
        public readonly ?string $existsGlob = null,
    ) {
    }

    /**
     * The fully qualified class name, or null for files without a namespace.
     */
    public function fqcn(): ?string
    {
        if ($this->namespace === null) {
            return null;
        }

        return $this->namespace.'\\'.$this->className;
    }

    /**
     * An already-generated file matching this target, honouring exists_glob.
     */
    public function existingPath(): ?string
    {
        if ($this->existsGlob !== null) {
            $matches = glob(rtrim($this->directory, '/\\').'/'.$this->existsGlob) ?: [];

            return $matches[0] ?? null;
        }

        return is_file($this->path) ? $this->path : null;
    }
}
