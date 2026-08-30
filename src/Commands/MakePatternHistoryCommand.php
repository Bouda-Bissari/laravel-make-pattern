<?php

namespace Bouda\MakePattern\Commands;

use Bouda\MakePattern\Support\GenerationLog;
use Illuminate\Console\Command;

class MakePatternHistoryCommand extends Command
{
    protected $signature = 'make:pattern:history
        {--id= : Show the files written by a single run}';

    protected $description = 'List every make:pattern generation run, most recent first.';

    public function handle(GenerationLog $log): int
    {
        if ($id = $this->option('id')) {
            return $this->showRun($log, $id);
        }

        $history = $log->all();

        if ($history === []) {
            $this->warn('No generations recorded yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Entity', 'Domain', 'Date', 'Files'],
            array_map(fn (array $entry) => [
                $entry['id'],
                $entry['entity'],
                $entry['options']['domain'] ?? '—',
                $entry['generated_at'],
                count($entry['files'] ?? []) + count($entry['mutations'] ?? []),
            ], $history)
        );

        return self::SUCCESS;
    }

    private function showRun(GenerationLog $log, string $id): int
    {
        $entry = $log->find($id);

        if (! $entry) {
            $this->error("No recorded run found for id [{$id}].");

            return self::FAILURE;
        }

        $this->line("Run <fg=cyan>{$entry['id']}</> — {$entry['entity']} — {$entry['generated_at']}");
        $this->newLine();

        $rows = array_map(fn (array $file) => [
            $file['action'] ?? 'created',
            $this->relative($file['path']),
            ($file['backup'] ?? null) ? 'yes' : '—',
        ], array_merge($entry['files'] ?? [], $entry['mutations'] ?? []));

        $this->table(['Action', 'File', 'Backup'], $rows);

        return self::SUCCESS;
    }

    private function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalised = str_replace('\\', '/', $path);

        return str_starts_with($normalised, $base) ? substr($normalised, strlen($base)) : $normalised;
    }
}
