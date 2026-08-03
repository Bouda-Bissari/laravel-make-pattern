<?php

namespace Bouda\MakePattern\Commands;

use Bouda\MakePattern\Support\GenerationLog;
use Illuminate\Console\Command;

class MakePatternHistoryCommand extends Command
{
    protected $signature = 'make:pattern:history';

    protected $description = 'List every make:pattern generation run, most recent first.';

    public function handle(GenerationLog $log): int
    {
        $history = $log->all();

        if (empty($history)) {
            $this->warn('No generations recorded yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Entité', 'Date', 'Nb fichiers'],
            array_map(fn (array $entry) => [
                $entry['id'],
                $entry['entity'],
                $entry['generated_at'],
                count($entry['files']),
            ], $history)
        );

        return self::SUCCESS;
    }
}