<?php

namespace Bouda\MakePattern\Tests\Unit;

use Bouda\MakePattern\Support\EntityName;
use Bouda\MakePattern\Support\GenerationOptions;
use Bouda\MakePattern\Support\LayerResolver;
use Bouda\MakePattern\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class LayerResolverTest extends TestCase
{
    private function resolver(array $overrides = []): LayerResolver
    {
        return new LayerResolver(array_replace_recursive([
            'roots' => [
                'App' => ['path' => '/project/app', 'domain' => 'prefix', 'primary' => true],
                'Tests' => ['path' => '/project/tests', 'domain' => 'suffix', 'primary' => false],
            ],
            'domain' => ['segment' => 'Domain'],
        ], $overrides), '/project');
    }

    private function layer(string $namespace, string $suffix = ''): array
    {
        return ['namespace' => $namespace, 'suffix' => $suffix, 'stub' => 'x'];
    }

    public function test_it_derives_the_directory_from_the_namespace(): void
    {
        $target = $this->resolver()->resolve(
            'repository_interface',
            $this->layer('App\\Repositories\\Contracts', 'RepositoryInterface'),
            EntityName::make('Post'),
            GenerationOptions::make(),
        );

        $this->assertSame('App\\Repositories\\Contracts', $target->namespace);
        $this->assertSame('/project/app/Repositories/Contracts', $target->directory);
        $this->assertSame('/project/app/Repositories/Contracts/PostRepositoryInterface.php', $target->path);
    }

    /**
     * The regression that mattered most: nested layers used to lose their parent
     * segment under --domain, so the file landed where PSR-4 could not find it.
     *
     */
    #[DataProvider('nestedLayerProvider')]
    public function test_domain_keeps_nested_namespaces_and_directories_in_sync(
        string $namespace,
        string $expectedNamespace,
        string $expectedDirectory,
    ): void {
        $target = $this->resolver()->resolve(
            'layer',
            $this->layer($namespace),
            EntityName::make('Post'),
            GenerationOptions::make(domain: 'Blog'),
        );

        $this->assertSame($expectedNamespace, $target->namespace);
        $this->assertSame($expectedDirectory, $target->directory);
    }

    public static function nestedLayerProvider(): array
    {
        return [
            'contracts' => [
                'App\\Repositories\\Contracts',
                'App\\Domain\\Blog\\Repositories\\Contracts',
                '/project/app/Domain/Blog/Repositories/Contracts',
            ],
            'controllers' => [
                'App\\Http\\Controllers',
                'App\\Domain\\Blog\\Http\\Controllers',
                '/project/app/Domain/Blog/Http/Controllers',
            ],
            'requests' => [
                'App\\Http\\Requests',
                'App\\Domain\\Blog\\Http\\Requests',
                '/project/app/Domain/Blog/Http/Requests',
            ],
            'flat layer' => [
                'App\\Models',
                'App\\Domain\\Blog\\Models',
                '/project/app/Domain/Blog/Models',
            ],
        ];
    }

    public function test_domain_is_appended_for_roots_configured_as_suffix(): void
    {
        $target = $this->resolver()->resolve(
            'test',
            $this->layer('Tests\\Feature', 'Test'),
            EntityName::make('Post'),
            GenerationOptions::make(domain: 'Blog'),
        );

        $this->assertSame('Tests\\Feature\\Blog', $target->namespace);
        $this->assertSame('/project/tests/Feature/Blog', $target->directory);
    }

    public function test_the_domain_segment_is_configurable(): void
    {
        $target = $this->resolver(['domain' => ['segment' => 'Modules']])->resolve(
            'model',
            $this->layer('App\\Models'),
            EntityName::make('Post'),
            GenerationOptions::make(domain: 'Blog'),
        );

        $this->assertSame('App\\Modules\\Blog\\Models', $target->namespace);
        $this->assertSame('/project/app/Modules/Blog/Models', $target->directory);
    }

    public function test_namespace_override_only_rewrites_the_primary_root(): void
    {
        $resolver = $this->resolver();
        $options = GenerationOptions::make(rootNamespace: 'Acme');

        $model = $resolver->resolve('model', $this->layer('App\\Models'), EntityName::make('Post'), $options);
        $test = $resolver->resolve('test', $this->layer('Tests\\Feature'), EntityName::make('Post'), $options);

        $this->assertSame('Acme\\Models', $model->namespace);
        $this->assertSame('/project/acme/Models', $model->directory);

        // The test suite root is not the primary root, so it stays put.
        $this->assertSame('Tests\\Feature', $test->namespace);
        $this->assertSame('/project/tests/Feature', $test->directory);
    }

    public function test_the_root_directory_can_be_overridden(): void
    {
        $target = $this->resolver()->resolve(
            'model',
            $this->layer('App\\Models'),
            EntityName::make('Post'),
            GenerationOptions::make(rootNamespace: 'Acme', rootPath: 'src'),
        );

        $this->assertSame('/project/src/Models', $target->directory);
    }

    public function test_domain_and_namespace_combine(): void
    {
        $target = $this->resolver()->resolve(
            'controller',
            $this->layer('App\\Http\\Controllers', 'Controller'),
            EntityName::make('Post'),
            GenerationOptions::make(domain: 'Blog', rootNamespace: 'Acme'),
        );

        $this->assertSame('Acme\\Domain\\Blog\\Http\\Controllers', $target->namespace);
        $this->assertSame('/project/acme/Domain/Blog/Http/Controllers', $target->directory);
    }

    public function test_layers_without_a_namespace_are_never_moved(): void
    {
        $target = $this->resolver()->resolve(
            'migration',
            [
                'namespace' => null,
                'path' => '/project/database/migrations',
                'suffix' => '',
                'file_name' => '{{ timestamp }}_create_{{ entityTable }}_table',
                'exists_glob' => '*_create_{{ entityTable }}_table.php',
            ],
            EntityName::make('Post'),
            GenerationOptions::make(domain: 'Blog', rootNamespace: 'Acme'),
        );

        $this->assertNull($target->namespace);
        $this->assertSame('/project/database/migrations', $target->directory);
        $this->assertMatchesRegularExpression('#/\d{4}_\d{2}_\d{2}_\d{6}_create_posts_table\.php$#', $target->path);
        $this->assertSame('*_create_posts_table.php', $target->existsGlob);
    }

    public function test_a_layer_under_no_configured_root_falls_back_to_its_own_path(): void
    {
        $target = $this->resolver()->resolve(
            'custom',
            ['namespace' => 'Vendor\\Custom', 'path' => '/project/vendor-custom', 'suffix' => 'Thing', 'stub' => 'x'],
            EntityName::make('Post'),
            GenerationOptions::make(domain: 'Blog'),
        );

        $this->assertSame('Vendor\\Custom\\Domain\\Blog', $target->namespace);
        $this->assertSame('/project/vendor-custom/Domain/Blog', $target->directory);
    }

    public function test_it_fails_loudly_when_a_layer_has_neither_a_known_root_nor_a_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/matches no root/');

        $this->resolver()->resolve(
            'custom',
            $this->layer('Vendor\\Custom'),
            EntityName::make('Post'),
            GenerationOptions::make(),
        );
    }

    public function test_it_maps_a_class_name_onto_a_path(): void
    {
        $this->assertSame(
            '/project/app/Providers/PatternServiceProvider.php',
            $this->resolver()->pathForClass('App\\Providers\\PatternServiceProvider'),
        );
    }
}
