<?php

namespace Bouda\MakePattern\Commands;

use Bouda\MakePattern\Support\GenerationLog;
use Bouda\MakePattern\Support\MakePatternLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakePatternUndoCommand extends Command
{
    protected $signature = 'make:pattern:undo
        {--id= : Roll back a specific run by its id. Defaults to the most recent run}';

    protected $description = 'Remove the files created by a make:pattern run.';

    public function handle(GenerationLog $log): int
    {
        $id = $this->option('id');
        $entry = $id ? $log->find($id) : $log->last();

        if (! $entry) {
            $message = $id
                ? "No recorded run found for id [{$id}]."
                : 'No previous generation found.';

            $this->warn($message);
            MakePatternLogger::error('Rollback failed: run not found in history.', [
                'requested_id' => $id,
            ]);

            return self::FAILURE;
        }

        foreach ($entry['files'] as $file) {
            if (! File::exists($file)) {
                $this->line("  <fg=yellow>missing</> {$file}");
                continue;
            }

            $mtimes = $entry['mtimes'] ?? [];
            $modified = ($mtimes[$file] ?? null) !== filemtime($file);

            if ($modified) {
                $message = "File modified since generation [{$entry['id']}], skipping: {$file}";
                $this->warn("  <fg=yellow>modified</> {$file}");
                MakePatternLogger::warning($message, [
                    'run_id' => $entry['id'],
                    'path' => $file,
                ]);
                continue;
            }

            File::delete($file);
            $this->line("  <fg=red>removed</> {$file}");
        }

        $log->remove($entry['id']);

        $this->info('Rollback complete.');
        MakePatternLogger::info('make:pattern:undo completed', [
            'run_id' => $entry['id'],
            'files' => count($entry['files']),
        ]);

        return self::SUCCESS;
    }
}