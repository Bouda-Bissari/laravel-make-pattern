<?php

namespace Bouda\MakePattern\Generators;

use Bouda\MakePattern\Support\LayerTarget;
use Bouda\MakePattern\Support\StubReplacer;
use Illuminate\Support\Facades\File;

/**
 * Renders one stub to one file.
 *
 * Placement is decided by the LayerResolver; this class only fills and writes.
 */
class FileGenerator
{
    /**
     * @param  array<string, string>  $variables
     */
    public function write(LayerTarget $target, string $stubPath, array $variables): string
    {
        $contents = StubReplacer::fill(File::get($stubPath), $variables);

        File::ensureDirectoryExists($target->directory);
        File::put($target->path, $this->tidy($contents));

        return $target->path;
    }

    /**
     * Collapse the blank lines left behind by placeholders that rendered empty,
     * so an optional trait or import never leaves a hole in the output.
     */
    private function tidy(string $contents): string
    {
        $contents = preg_replace("/\r\n|\r/", "\n", $contents);
        $contents = preg_replace("/\n{3,}/", "\n\n", $contents);
        $contents = preg_replace("/\{\n\n+/", "{\n", $contents);
        $contents = preg_replace("/\n+\}/", "\n}", $contents);

        return rtrim($contents, "\n")."\n";
    }
}
