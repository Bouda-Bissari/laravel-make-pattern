<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Facades\File;

/**
 * Append-only record of every generation run, with enough information to undo
 * one safely.
 *
 * Files are tracked by content hash rather than mtime, and a file that already
 * existed is backed up before being overwritten so undo can restore it instead
 * of deleting work the generator never authored.
 */
class GenerationLog
{
    public const CREATED = 'created';

    public const OVERWRITTEN = 'overwritten';

    public const APPENDED = 'appended';

    public function __construct(
        private readonly string $logPath,
        private readonly string $backupDirectory,
        private readonly int $keep = 50,
    ) {
    }

    /**
     * Copy a file aside before it is modified, and return the backup path.
     */
    public function backup(string $runId, string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $directory = rtrim($this->backupDirectory, '/\\').'/'.$runId;
        File::ensureDirectoryExists($directory);

        $backup = $directory.'/'.md5($path).'-'.basename($path);
        File::copy($path, $backup);

        return $backup;
    }

    /**
     * @param  list<array{path: string, action: string, backup: ?string}>  $files
     * @param  list<array{path: string, action: string, backup: ?string}>  $mutations
     */
    public function record(string $id, string $entity, array $files, array $mutations = [], array $options = []): array
    {
        $entry = [
            'id' => $id,
            'entity' => $entity,
            'generated_at' => now()->toDateTimeString(),
            'options' => array_filter($options, fn ($value) => $value !== null && $value !== false),
            'files' => array_map(fn (array $file) => $this->withHash($file), $files),
            'mutations' => array_map(fn (array $file) => $this->withHash($file), $mutations),
        ];

        $history = array_merge([$entry], $this->all());

        $this->prune(array_slice($history, $this->keep));
        $this->put(array_slice($history, 0, $this->keep));

        return $entry;
    }

    private function withHash(array $file): array
    {
        $file['hash'] = File::exists($file['path']) ? md5_file($file['path']) : null;

        return $file;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        if (! File::exists($this->logPath)) {
            return [];
        }

        $decoded = json_decode(File::get($this->logPath), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map($this->normalise(...), $decoded));
    }

    /**
     * Accept entries written by version 1, where files were plain path strings.
     */
    private function normalise(array $entry): array
    {
        $entry['mutations'] ??= [];
        $entry['options'] ??= [];
        $entry['files'] = array_map(function ($file) use ($entry) {
            if (is_array($file)) {
                return $file + ['action' => self::CREATED, 'backup' => null, 'hash' => null];
            }

            // Version 1 stored mtimes. Treat the hash as unknown rather than
            // trusting a comparison that was never reliable.
            return [
                'path' => $file,
                'action' => self::CREATED,
                'backup' => null,
                'hash' => null,
            ];
        }, $entry['files'] ?? []);

        unset($entry['mtimes']);

        return $entry;
    }

    public function last(): ?array
    {
        return $this->all()[0] ?? null;
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $entry) {
            if (($entry['id'] ?? null) === $id) {
                return $entry;
            }
        }

        return null;
    }

    public function remove(string $id): void
    {
        $history = [];

        foreach ($this->all() as $entry) {
            if (($entry['id'] ?? null) === $id) {
                $this->prune([$entry]);

                continue;
            }

            $history[] = $entry;
        }

        $this->put($history);
    }

    /**
     * Delete the backup directories of entries falling out of the history.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    private function prune(array $entries): void
    {
        foreach ($entries as $entry) {
            $directory = rtrim($this->backupDirectory, '/\\').'/'.($entry['id'] ?? '');

            if (($entry['id'] ?? '') !== '' && File::isDirectory($directory)) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function put(array $history): void
    {
        File::ensureDirectoryExists(dirname($this->logPath));
        File::put($this->logPath, json_encode(array_values($history), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
