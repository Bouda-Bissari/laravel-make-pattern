<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Str;

/**
 * The per-run options that reshape where generated code lands.
 */
final class GenerationOptions
{
    private function __construct(
        public readonly ?string $domain,
        public readonly ?string $rootNamespace,
        public readonly ?string $rootPath,
        public readonly bool $force,
    ) {
    }

    public static function make(
        ?string $domain = null,
        ?string $rootNamespace = null,
        ?string $rootPath = null,
        bool $force = false,
    ): self {
        return new self(
            domain: $domain !== null && $domain !== '' ? Str::studly($domain) : null,
            rootNamespace: $rootNamespace !== null && $rootNamespace !== ''
                ? trim(str_replace('/', '\\', $rootNamespace), '\\')
                : null,
            rootPath: $rootPath !== null && $rootPath !== '' ? rtrim(str_replace('\\', '/', $rootPath), '/') : null,
            force: $force,
        );
    }

    /**
     * The directory the overridden root namespace maps to.
     *
     * Defaults to a lowercased mirror of the namespace at the project root,
     * the same way App maps to app/.
     */
    public function resolvedRootPath(string $basePath): ?string
    {
        if ($this->rootNamespace === null) {
            return null;
        }

        if ($this->rootPath !== null) {
            return Str::startsWith($this->rootPath, ['/', '\\']) || preg_match('/^[A-Za-z]:/', $this->rootPath)
                ? $this->rootPath
                : rtrim($basePath, '/\\').'/'.$this->rootPath;
        }

        return rtrim($basePath, '/\\').'/'.Str::lower(str_replace('\\', '/', $this->rootNamespace));
    }
}
