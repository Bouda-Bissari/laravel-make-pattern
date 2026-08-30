<?php

namespace Bouda\MakePattern\Generators;

use Bouda\MakePattern\Support\FileAction;
use Illuminate\Support\Facades\File;

/**
 * Registers the generated controller in the application's route file.
 *
 * A scaffold whose controller is unreachable is only half generated, so the
 * resource route is written too. Registration is idempotent: a controller that
 * is already routed is never added twice.
 */
class RouteRegistrar
{
    public function __construct(
        private readonly array $config,
        private readonly string $basePath,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['routes']['enabled'] ?? false);
    }

    public function path(): string
    {
        return (string) ($this->config['routes']['file'] ?? rtrim($this->basePath, '/\\').'/routes/api.php');
    }

    /**
     * @param  array<string, string>  $variables
     * @param  callable(string): ?string  $backup
     * @return array{files: list<array{path: string, action: string, backup: ?string}>, notes: list<string>}
     */
    public function sync(array $variables, callable $backup): array
    {
        $controller = $variables['controllerFqcn'] ?? '';
        $uri = $variables['routeUri'] ?? '';

        if ($controller === '' || $uri === '') {
            return ['files' => [], 'notes' => []];
        }

        $path = $this->path();
        $notes = [];
        $created = ! File::exists($path);

        // `php artisan install:api` refuses to wire routes/api.php into
        // bootstrap/app.php when the file already exists. Creating it here would
        // leave the routes permanently unreachable and break the one command
        // that fixes it, so hand the job back to the user instead.
        if ($created && $this->wouldBreakInstallApi($path)) {
            return ['files' => [], 'notes' => [
                'Skipped route registration: routes/api.php does not exist yet. '
                .'Run `php artisan install:api`, then re-run this command to register the route.',
            ]];
        }

        if ($created) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        }

        $original = File::get($path);

        if (str_contains($original, class_basename($controller).'::class')) {
            return ['files' => $created ? [['path' => $path, 'action' => FileAction::CREATED, 'backup' => null]] : [], 'notes' => []];
        }

        $contents = $this->addUseStatement($original, $controller);
        $contents = rtrim($contents, "\n")."\n\n".$this->routeLine($controller, $uri)."\n";

        $copy = $created ? null : $backup($path);
        File::put($path, $contents);

        if ($note = $this->routingNote($path)) {
            $notes[] = $note;
        }

        return [
            'files' => [[
                'path' => $path,
                'action' => $created ? FileAction::CREATED : FileAction::APPENDED,
                'backup' => $copy,
            ]],
            'notes' => $notes,
        ];
    }

    private function routeLine(string $controller, string $uri): string
    {
        $method = ($this->config['routes']['method'] ?? 'apiResource') === 'resource' ? 'resource' : 'apiResource';
        $middleware = array_values(array_filter((array) ($this->config['routes']['middleware'] ?? [])));

        $call = "{$method}('{$uri}', ".class_basename($controller).'::class);';

        if ($middleware === []) {
            return 'Route::'.$call;
        }

        $list = implode(', ', array_map(fn (string $name) => "'{$name}'", $middleware));

        return "Route::middleware([{$list}])->".$call;
    }

    private function addUseStatement(string $contents, string $controller): string
    {
        $statement = "use {$controller};";

        if (str_contains($contents, $statement)) {
            return $contents;
        }

        $lines = explode("\n", $contents);
        $anchor = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/^use\s+[^;]+;/', trim($line))) {
                $anchor = $index;
            }
        }

        if ($anchor === null) {
            foreach ($lines as $index => $line) {
                if (str_starts_with(trim($line), '<?php')) {
                    $anchor = $index;
                    array_splice($lines, $index + 1, 0, ['']);

                    break;
                }
            }
        }

        if ($anchor === null) {
            return $contents;
        }

        array_splice($lines, $anchor + 1, 0, [$statement]);

        return implode("\n", $lines);
    }

    /**
     * True when creating this file would take `install:api` out of play.
     */
    private function wouldBreakInstallApi(string $path): bool
    {
        return basename($path) === 'api.php' && $this->apiRoutesAreUnwired() === true;
    }

    /**
     * Laravel 11+ does not ship routes/api.php: writing the file is not enough
     * if the application never loads it.
     */
    private function routingNote(string $path): ?string
    {
        if (basename($path) !== 'api.php' || $this->apiRoutesAreUnwired() !== true) {
            return null;
        }

        return 'routes/api.php exists but is not loaded by bootstrap/app.php. Add '
            ."`api: __DIR__.'/../routes/api.php'` to withRouting() so the generated routes are reachable.";
    }

    /**
     * Whether bootstrap/app.php loads an API route file.
     *
     * Null when the question does not apply, e.g. on Laravel 10 where routing
     * is configured by a service provider instead.
     */
    private function apiRoutesAreUnwired(): ?bool
    {
        $bootstrap = rtrim(str_replace('\\', '/', $this->basePath), '/').'/bootstrap/app.php';

        if (! File::exists($bootstrap)) {
            return null;
        }

        return ! preg_match('/\bapi\s*:/', File::get($bootstrap));
    }
}
