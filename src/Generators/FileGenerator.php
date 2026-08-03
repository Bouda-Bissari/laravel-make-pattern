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
     * @return string|null The path of the file written, or null if skipped (already exists, not forced).
     */
    public function generate(string $entityName, array $layerConfig, string $stubPath, bool $force = false): ?string
    {
        $className = Str::studly($entityName).$layerConfig['suffix'];
        $filePath = rtrim($layerConfig['path'], '/').'/'.$className.'.php';

        if (File::exists($filePath) && ! $force) {
            return null;
        }

        $stubContent = File::get($stubPath);

        $filled = StubReplacer::fill($stubContent, [
            'namespace' => $layerConfig['namespace'],
            'class' => $className,
            'entity' => Str::studly($entityName),
            'entityVariable' => Str::camel($entityName),
            'entityTable' => Str::snake(Str::pluralStudly($entityName)),
        ]);

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $filled);

        return $filePath;
    }
}
