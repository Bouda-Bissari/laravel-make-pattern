<?php

namespace Bouda\MakePattern\Support;

class StubReplacer
{
    public static function fill(string $stubContent, array $replacements): string
    {
        foreach ($replacements as $search => $value) {
            $stubContent = str_replace('{{ '.$search.' }}', $value, $stubContent);
            $stubContent = str_replace('{{'.$search.'}}', $value, $stubContent);
        }

        return $stubContent;
    }
}
