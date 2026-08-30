<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Facades\File;

/**
 * Reverses what a run did to the filesystem.
 *
 * A file the run created is deleted; a file it overwrote or edited is restored
 * from its backup. When the content no longer matches what was recorded, the
 * file is left alone and reported, so edits made after generation are never
 * thrown away silently.
 */
final class Rollback
{
    public const REMOVED = 'removed';

    public const RESTORED = 'restored';

    public const MODIFIED = 'modified';

    public const MISSING = 'missing';

    public const NO_BACKUP = 'no-backup';

    /**
     * @param  list<array{path: string, action: string, backup: ?string, hash: ?string}>  $files
     * @param  bool  $verifyHash  Skip files whose content changed since generation.
     * @return list<array{path: string, result: string}>
     */
    public static function apply(array $files, bool $verifyHash = true): array
    {
        $results = [];

        // Reverse order so a file touched twice in one run unwinds correctly.
        foreach (array_reverse($files) as $file) {
            $results[] = [
                'path' => $file['path'],
                'result' => self::revert($file, $verifyHash),
            ];
        }

        return $results;
    }

    /**
     * @param  array{path: string, action: string, backup: ?string, hash: ?string}  $file
     */
    private static function revert(array $file, bool $verifyHash): string
    {
        $path = $file['path'];
        $action = $file['action'] ?? FileAction::CREATED;
        $backup = $file['backup'] ?? null;

        if (! File::exists($path)) {
            return self::MISSING;
        }

        if ($verifyHash && ! self::matches($path, $file['hash'] ?? null)) {
            return self::MODIFIED;
        }

        if ($action === FileAction::CREATED) {
            File::delete($path);

            return self::REMOVED;
        }

        if ($backup === null || ! File::exists($backup)) {
            return self::NO_BACKUP;
        }

        File::copy($backup, $path);

        return self::RESTORED;
    }

    /**
     * An unknown hash (a run recorded by version 1) is treated as a match:
     * refusing every rollback would be worse than trusting the record.
     */
    private static function matches(string $path, ?string $hash): bool
    {
        return $hash === null || md5_file($path) === $hash;
    }
}
