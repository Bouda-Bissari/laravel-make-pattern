<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Str;

/**
 * Builds the replacement map handed to every stub.
 *
 * Cross-layer references are derived from the resolved targets rather than
 * rebuilt by hand, so a stub always imports the class that was actually
 * written — including under --domain and --namespace.
 */
final class StubVariables
{
    /**
     * @param  array<string, LayerTarget>  $targets
     */
    public function __construct(
        private readonly array $config,
        private readonly array $targets,
        private readonly EntityName $entity,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function forTarget(LayerTarget $target): array
    {
        return array_merge($this->shared(), [
            'namespace' => $target->namespace ?? '',
            'class' => $target->className,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function shared(): array
    {
        $variables = [
            'entity' => $this->entity->studly,
            'entityVariable' => $this->entity->camel,
            'entityTable' => $this->entity->table,
            'entitySnake' => $this->entity->snake,
            'routeUri' => $this->entity->routeUri,
            'timestamp' => date('Y_m_d_His'),
            'idType' => $this->idType(),
            'migrationPrimaryKey' => $this->migrationPrimaryKey(),
            'modelTraits' => $this->modelTraits(),
            'modelImports' => $this->modelImports(),
            'modelFactoryMethod' => $this->modelFactoryMethod(),
            'modelNamespace' => $this->targets['model']->namespace ?? '',
            'providerFqcn' => (string) ($this->config['provider']['class'] ?? ''),
            'providerClass' => class_basename((string) ($this->config['provider']['class'] ?? '')),
        ];

        // Every layer exposes {{ <layer>Fqcn }} and {{ <layer>Class }}, including
        // layers added by the user, e.g. {{ repositoryInterfaceFqcn }}.
        foreach ($this->targets as $key => $target) {
            $prefix = Str::camel($key);
            $variables[$prefix.'Fqcn'] = $target->fqcn() ?? '';
            $variables[$prefix.'Class'] = $target->className;
            $variables[$prefix.'Namespace'] = $target->namespace ?? '';
        }

        return array_merge($variables, $this->userModel(), $this->baseController());
    }

    private function idType(): string
    {
        return $this->strategy() === 'increment' ? 'int' : 'string';
    }

    private function strategy(): string
    {
        $strategy = strtolower((string) ($this->config['primary_key']['strategy'] ?? 'ulid'));

        return in_array($strategy, ['ulid', 'uuid', 'increment'], true) ? $strategy : 'ulid';
    }

    private function migrationPrimaryKey(): string
    {
        return match ($this->strategy()) {
            'uuid' => "\$table->uuid('id')->primary();",
            'increment' => '$table->id();',
            default => "\$table->ulid('id')->primary();",
        };
    }

    /**
     * Trait short names appended to the model's `use HasFactory` statement.
     *
     * @return list<string>
     */
    private function traitFqcns(): array
    {
        $traits = match ($this->strategy()) {
            'ulid' => ['Illuminate\\Database\\Eloquent\\Concerns\\HasUlids'],
            'uuid' => ['Illuminate\\Database\\Eloquent\\Concerns\\HasUuids'],
            default => [],
        };

        $tenancy = $this->config['tenancy'] ?? [];

        if ($tenancy['enabled'] ?? false) {
            $driver = $tenancy['driver'] ?? null;
            $trait = $tenancy['drivers'][$driver]['trait'] ?? null;

            if ($trait) {
                $traits[] = $trait;
            }
        }

        return $traits;
    }

    private function modelTraits(): string
    {
        $short = array_map(fn (string $fqcn) => class_basename($fqcn), $this->traitFqcns());

        return $short === [] ? '' : ', '.implode(', ', $short);
    }

    /**
     * The model's full import block, sorted, because the trait set varies with
     * the primary key strategy and the tenancy driver.
     */
    private function modelImports(): string
    {
        $imports = array_merge($this->traitFqcns(), [
            'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
            'Illuminate\\Database\\Eloquent\\Model',
        ]);

        if ($this->factoryEnabled()) {
            $imports[] = $this->targets['factory']->fqcn();
        }

        $imports = array_values(array_unique(array_filter($imports)));
        sort($imports);

        return implode("\n", array_map(fn (string $fqcn) => "use {$fqcn};", $imports));
    }

    private function factoryEnabled(): bool
    {
        return isset($this->targets['factory'])
            && ($this->config['layers']['factory']['enabled'] ?? false)
            && $this->targets['factory']->fqcn() !== null;
    }

    /**
     * Bind the factory explicitly: Laravel's convention-based discovery does not
     * find it once --domain moves the model out of App\Models.
     */
    private function modelFactoryMethod(): string
    {
        if (! $this->factoryEnabled()) {
            return '';
        }

        $class = $this->targets['factory']->className;

        return <<<PHP

                protected static function newFactory(): {$class}
                {
                    return {$class}::new();
                }

            PHP;
    }

    /**
     * @return array<string, string>
     */
    private function userModel(): array
    {
        $fqcn = $this->config['user_model'] ?? null;

        if (! $fqcn) {
            return ['userModelFqcn' => '', 'userModelClass' => '', 'userModelImport' => '', 'userModelHint' => ''];
        }

        $class = class_basename($fqcn);
        $policyNamespace = $this->targets['policy']->namespace ?? null;
        $sameNamespace = $policyNamespace !== null && $policyNamespace === Str::beforeLast($fqcn, '\\');

        return [
            'userModelFqcn' => $fqcn,
            'userModelClass' => $class,
            'userModelImport' => $sameNamespace ? '' : "use {$fqcn};",
            'userModelHint' => $class,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function baseController(): array
    {
        $fqcn = $this->config['base_controller'] ?? null;

        if (! $fqcn) {
            return ['baseControllerImport' => '', 'controllerExtends' => ''];
        }

        $class = class_basename($fqcn);
        $controllerNamespace = $this->targets['controller']->namespace ?? null;
        $sameNamespace = $controllerNamespace !== null && $controllerNamespace === Str::beforeLast($fqcn, '\\');

        return [
            'baseControllerImport' => $sameNamespace ? '' : "use {$fqcn};",
            'controllerExtends' => " extends {$class}",
        ];
    }
}
