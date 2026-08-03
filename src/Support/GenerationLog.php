<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerationLog
{
    public function __construct(private string $logPath)
    {
    }

    public function record(string $id, string $entity, array $createdFiles): array
    {
        $mtimes = [];

        foreach ($createdFiles as $file) {
            $mtimes[$file] = File::exists($file) ? filemtime($file) : 0;
        }

        $entry = [
            'id' => $id,
            'entity' => $entity,
            'generated_at' => now()->toDateTimeString(),
            'files' => $createdFiles,
            'mtimes' => $mtimes,
        ];

        $history = array_merge([$entry], $this->all());

        $this->put($history);

        return $entry;
    }

    public function all(): array
    {
        if (! File::exists($this->logPath)) {
            return [];
        }

        $decoded = json_decode(File::get($this->logPath), true);

        return is_array($decoded) ? array_values($decoded) : [];
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
        $history = array_values(array_filter(
            $this->all(),
            fn (array $entry) => ($entry['id'] ?? null) !== $id
        ));

        $this->put($history);
    }

    protected function put(array $history): void
    {
        File::ensureDirectoryExists(dirname($this->logPath));
        File::put($this->logPath, json_encode($history, JSON_PRETTY_PRINT));
    }
}