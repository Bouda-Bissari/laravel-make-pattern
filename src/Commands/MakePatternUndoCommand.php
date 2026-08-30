<?php

namespace Bouda\MakePattern\Commands;

use Bouda\MakePattern\Support\GenerationLog;
use Bouda\MakePattern\Support\MakePatternLogger;
use Bouda\MakePattern\Support\Rollback;
use Illuminate\Console\Command;

class MakePatternUndoCommand extends Command
{
    protected $signature = 'make:pattern:undo
        {--id= : Roll back a specific run by its id. Defaults to the most recent run}
        {--force : Roll back even files that were edited after generation}';

    protected $description = 'Reverse a make:pattern run: delete what it created, restore what it overwrote.';

    public function handle(GenerationLog $log): int
    {
        $id = $this->option('id');
        $entry = $id ? $log->find($id) : $log->last();

        if (! $entry) {
            $message = $id
                ? "No recorded run found for id [{$id}]."
                : 'No previous generation found.';

            $this->warn($message);
            MakePatternLogger::warning('Rollback aborted: run not found in history.', ['requested_id' => $id]);

            return self::FAILURE;
        }

        $files = array_merge($entry['files'] ?? [], $entry['mutations'] ?? []);
        $results = Rollback::apply($files, verifyHash: ! $this->option('force'));

        $skipped = 0;

        foreach ($results as $result) {
            [$colour, $label] = match ($result['result']) {
                Rollback::REMOVED => ['red', 'removed  '],
                Rollback::RESTORED => ['green', 'restored '],
                Rollback::MISSING => ['gray', 'missing  '],
                Rollback::NO_BACKUP => ['yellow', 'no backup'],
                default => ['yellow', 'modified '],
            };

            if (in_array($result['result'], [Rollback::MODIFIED, Rollback::NO_BACKUP], true)) {
                $skipped++;
            }

            $this->line("  <fg={$colour}>{$label}</> {$this->relative($result['path'])}");
        }

        if ($skipped > 0) {
            $this->newLine();
            $this->warn("{$skipped} file(s) were left untouched because they changed after generation. "
                .'Re-run with --force to roll them back anyway.');
        }

        $log->remove($entry['id']);

        $this->newLine();
        $this->info("Rollback of run [{$entry['id']}] complete.");

        MakePatternLogger::info('make:pattern:undo completed', [
            'run_id' => $entry['id'],
            'files' => count($results),
            'skipped' => $skipped,
        ]);

        return self::SUCCESS;
    }

    private function relative(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalised = str_replace('\\', '/', $path);

        return str_starts_with($normalised, $base) ? substr($normalised, strlen($base)) : $normalised;
    }
}
