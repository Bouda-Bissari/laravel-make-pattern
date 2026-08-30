<?php

namespace Bouda\MakePattern\Generators;

use Bouda\MakePattern\Support\FileAction;
use Bouda\MakePattern\Support\LayerResolver;
use Bouda\MakePattern\Support\StubReplacer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Maintains the generated service provider.
 *
 * Without it, injecting a RepositoryInterface throws a BindingResolutionException,
 * and under --domain Laravel's policy auto-discovery no longer finds the policy.
 * Entries are appended into marker regions, so the file stays hand-editable and
 * running the command twice never duplicates a binding.
 */
class ProviderRegistrar
{
    public const REPOSITORY_MARKER = '// make-pattern:repositories';

    public const POLICY_MARKER = '// make-pattern:policies';

    public function __construct(
        private readonly array $config,
        private readonly LayerResolver $resolver,
        private readonly string $basePath,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['provider']['enabled'] ?? false);
    }

    public function providerClass(): string
    {
        return trim((string) ($this->config['provider']['class'] ?? ''), '\\');
    }

    public function path(): string
    {
        return $this->resolver->pathForClass($this->providerClass());
    }

    /**
     * Ensure the provider exists and holds this entity's binding and policy.
     *
     * @param  array<string, string>  $variables
     * @param  callable(string): ?string  $backup  Called before an existing file is modified.
     * @return array{files: list<array{path: string, action: string, backup: ?string}>, notes: list<string>}
     */
    public function sync(
        array $variables,
        string $stubPath,
        callable $backup,
        bool $bindRepository = true,
        bool $registerPolicy = true,
    ): array {
        $bindRepository = $bindRepository && ($this->config['provider']['bind_repositories'] ?? true);
        $registerPolicy = $registerPolicy && ($this->config['provider']['register_policies'] ?? true);

        // Nothing to register means nothing to create: a provider full of
        // bindings to classes that were never generated breaks the whole app.
        if (! $bindRepository && ! $registerPolicy) {
            return ['files' => [], 'notes' => []];
        }

        $path = $this->path();
        $files = [];
        $notes = [];
        $created = false;

        if (! File::exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            // array_merge, not +: the incoming variables already carry a
            // namespace and class from another layer, and they must lose.
            File::put($path, StubReplacer::fill(File::get($stubPath), array_merge($variables, [
                'namespace' => Str::beforeLast($this->providerClass(), '\\'),
                'class' => class_basename($this->providerClass()),
            ])));

            $created = true;
        }

        $original = File::get($path);
        $contents = $original;

        if ($bindRepository) {
            $contents = $this->addEntry(
                $contents,
                self::REPOSITORY_MARKER,
                $variables['repositoryInterfaceFqcn'] ?? '',
                $variables['repositoryFqcn'] ?? '',
                $notes,
                'binding',
            );
        }

        if ($registerPolicy) {
            $contents = $this->addEntry(
                $contents,
                self::POLICY_MARKER,
                $variables['modelFqcn'] ?? '',
                $variables['policyFqcn'] ?? '',
                $notes,
                'policy',
            );
        }

        $changed = $contents !== $original;

        if ($changed) {
            // Back up only a file that predates this run: a provider we just
            // created is removed outright by undo.
            $copy = $created ? null : $backup($path);
            File::put($path, $contents);

            if (! $created) {
                $files[] = ['path' => $path, 'action' => FileAction::APPENDED, 'backup' => $copy];
            }
        }

        if ($created) {
            $files[] = ['path' => $path, 'action' => FileAction::CREATED, 'backup' => null];
        }

        if ($this->config['provider']['auto_register'] ?? true) {
            [$registration, $note] = $this->registerInBootstrap($backup);

            if ($registration !== null) {
                $files[] = $registration;
            }

            if ($note !== null) {
                $notes[] = $note;
            }
        }

        return ['files' => $files, 'notes' => $notes];
    }

    /**
     * @param  list<string>  $notes
     */
    private function addEntry(
        string $contents,
        string $marker,
        string $key,
        string $value,
        array &$notes,
        string $label,
    ): string {
        if ($key === '' || $value === '') {
            return $contents;
        }

        if (! str_contains($contents, $marker)) {
            $notes[] = "Marker `{$marker}` is missing from ".class_basename($this->providerClass())
                .", so the {$label} for {$key} was not added.";

            return $contents;
        }

        if (str_contains($contents, "\\{$key}::class =>")) {
            return $contents;
        }

        $entry = "\\{$key}::class => \\{$value}::class,";

        return str_replace($marker, $entry."\n        ".$marker, $contents);
    }

    /**
     * Add the provider to bootstrap/providers.php.
     *
     * @param  callable(string): ?string  $backup
     * @return array{0: array{path: string, action: string, backup: ?string}|null, 1: string|null}
     */
    private function registerInBootstrap(callable $backup): array
    {
        $path = rtrim(str_replace('\\', '/', $this->basePath), '/').'/bootstrap/providers.php';
        $class = $this->providerClass();

        if (! File::exists($path)) {
            return [null, $this->note('bootstrap/providers.php was not found')];
        }

        $contents = File::get($path);

        if (str_contains($contents, $class)) {
            return [null, null];
        }

        // String surgery, not a regex: a Windows path or a namespace in the
        // replacement would be mangled by preg_replace's backreference syntax.
        $position = strrpos($contents, '];');

        if ($position === false) {
            return [null, $this->note('the return array in bootstrap/providers.php could not be located')];
        }

        $updated = substr($contents, 0, $position)
            ."    \\{$class}::class,\n"
            .substr($contents, $position);

        $copy = $backup($path);
        File::put($path, $updated);

        return [['path' => $path, 'action' => FileAction::APPENDED, 'backup' => $copy], null];
    }

    private function note(string $reason): string
    {
        return 'Could not register '.class_basename($this->providerClass())." automatically: {$reason}. "
            .'Register it yourself in bootstrap/providers.php (or config/app.php on Laravel 10).';
    }
}
