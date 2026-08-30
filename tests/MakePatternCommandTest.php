<?php

namespace Bouda\MakePattern\Tests;

use Bouda\MakePattern\Generators\ProviderRegistrar;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;

class MakePatternCommandTest extends TestCase
{
    private ?string $bootstrapProviders = null;

    protected function setUp(): void
    {
        parent::setUp();

        $path = base_path('bootstrap/providers.php');
        $this->bootstrapProviders = File::exists($path) ? File::get($path) : null;
    }

    protected function tearDown(): void
    {
        foreach (['Models', 'Domain', 'Services', 'Repositories', 'Policies', 'Providers', 'Http'] as $directory) {
            File::deleteDirectory(app_path($directory));
        }

        File::deleteDirectory(base_path('tests/Feature'));
        File::deleteDirectory(base_path('acme'));
        File::deleteDirectory(base_path('src'));
        File::deleteDirectory(database_path('factories'));
        File::deleteDirectory(storage_path('app/make-pattern'));
        File::delete(base_path('routes/api.php'));

        foreach (glob(database_path('migrations/*_create_*_table.php')) ?: [] as $migration) {
            File::delete($migration);
        }

        if ($this->bootstrapProviders !== null) {
            File::put(base_path('bootstrap/providers.php'), $this->bootstrapProviders);
        }

        parent::tearDown();
    }

    /**
     * A namespace that does not match its directory is invisible to composer's
     * autoloader, which is how --domain used to produce unloadable code.
     */
    private function assertEveryGeneratedFileIsAutoloadable(): void
    {
        // The psr-4 map a real Laravel composer.json declares.
        $roots = [
            'Database\\Factories' => database_path('factories'),
            'Database\\Seeders' => database_path('seeders'),
            'App' => app_path(),
            'Tests' => base_path('tests'),
        ];

        $checked = 0;

        foreach ($this->generatedFiles() as $file) {
            $contents = File::get($file);

            if (! preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
                continue;
            }

            $namespace = trim($matches[1]);
            $root = null;

            foreach (array_keys($roots) as $candidate) {
                if ($namespace === $candidate || str_starts_with($namespace, $candidate.'\\')) {
                    $root = $candidate;

                    break;
                }
            }

            $this->assertNotNull($root, "Namespace [{$namespace}] in {$file} is under no psr-4 root.");

            $relative = trim(substr($namespace, strlen($root)), '\\');
            $expected = $this->normalise($roots[$root].'/'.str_replace('\\', '/', $relative));
            $actual = $this->normalise(dirname($file));

            $this->assertSame(
                $expected,
                $actual,
                "Namespace [{$namespace}] does not match the directory of ".basename($file)
            );

            $this->assertMatchesRegularExpression(
                '/\b(class|interface|trait|enum)\s+'.preg_quote(pathinfo($file, PATHINFO_FILENAME), '/').'\b/',
                $contents,
                'Type name does not match the file name of '.basename($file)
            );

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'No generated file was inspected.');
    }

    /**
     * @return list<string>
     */
    private function generatedFiles(): array
    {
        $files = [];

        foreach ([app_path(), base_path('tests/Feature'), database_path('factories')] as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function normalise(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Stand in for `php artisan install:api`, which the generator requires
     * before it will touch routes/api.php.
     */
    private function installApiRoutes(): void
    {
        File::put(base_path('routes/api.php'), "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
    }

    private function assertPhpIsValid(string $path): void
    {
        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $status);

        $this->assertSame(0, $status, "Generated file is not valid PHP: {$path}\n".implode("\n", $output));
    }

    public function test_it_generates_every_layer(): void
    {
        $this->installApiRoutes();

        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        foreach ([
            app_path('Models/Post.php'),
            app_path('Repositories/Contracts/PostRepositoryInterface.php'),
            app_path('Repositories/PostRepository.php'),
            app_path('Services/PostService.php'),
            app_path('Http/Controllers/PostController.php'),
            app_path('Http/Requests/PostStoreRequest.php'),
            app_path('Http/Requests/PostUpdateRequest.php'),
            app_path('Http/Resources/PostResource.php'),
            app_path('Policies/PostPolicy.php'),
            app_path('Providers/PatternServiceProvider.php'),
            database_path('factories/PostFactory.php'),
            base_path('tests/Feature/PostTest.php'),
            base_path('routes/api.php'),
        ] as $path) {
            $this->assertFileExists($path);
        }

        $this->assertNotEmpty(glob(database_path('migrations/*_create_posts_table.php')));
        $this->assertEveryGeneratedFileIsAutoloadable();
    }

    public function test_every_generated_file_is_valid_php(): void
    {
        $this->installApiRoutes();

        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        foreach ($this->generatedFiles() as $file) {
            $this->assertPhpIsValid($file);
        }

        $this->assertPhpIsValid(base_path('routes/api.php'));
        $this->assertPhpIsValid(base_path('bootstrap/providers.php'));

        foreach (glob(database_path('migrations/*_create_posts_table.php')) ?: [] as $migration) {
            $this->assertPhpIsValid($migration);
        }
    }

    #[DataProvider('optionProvider')]
    public function test_namespaces_and_directories_stay_in_sync_for_every_option(array $options): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'] + $options)->assertExitCode(0);

        $this->assertEveryGeneratedFileIsAutoloadable();

        foreach ($this->generatedFiles() as $file) {
            $this->assertPhpIsValid($file);
        }
    }

    public static function optionProvider(): array
    {
        return [
            'default' => [[]],
            'domain' => [['--domain' => 'Blog']],
            'domain lowercase' => [['--domain' => 'blog']],
        ];
    }

    public function test_domain_places_nested_layers_where_psr4_expects_them(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--domain' => 'Blog'])->assertExitCode(0);

        // These four were the regression: the parent segment used to be dropped.
        $this->assertFileExists(app_path('Domain/Blog/Repositories/Contracts/PostRepositoryInterface.php'));
        $this->assertFileExists(app_path('Domain/Blog/Http/Controllers/PostController.php'));
        $this->assertFileExists(app_path('Domain/Blog/Http/Requests/PostStoreRequest.php'));
        $this->assertFileExists(app_path('Domain/Blog/Http/Resources/PostResource.php'));

        $this->assertFileExists(base_path('tests/Feature/Blog/PostTest.php'));
        $this->assertFileDoesNotExist(app_path('Domain/Blog/Feature/PostTest.php'));

        // Migrations are global and must not be moved into the domain.
        $this->assertNotEmpty(glob(database_path('migrations/*_create_posts_table.php')));
    }

    public function test_namespace_option_moves_the_primary_root_only(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--namespace' => 'Acme', '--path' => 'src'])
            ->assertExitCode(0);

        $this->assertFileExists(base_path('src/Models/Post.php'));
        $this->assertStringContainsString('namespace Acme\\Models;', File::get(base_path('src/Models/Post.php')));

        // The test suite is not the primary root.
        $this->assertFileExists(base_path('tests/Feature/PostTest.php'));
    }

    public function test_it_binds_the_repository_interface_in_the_container(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        $provider = File::get(app_path('Providers/PatternServiceProvider.php'));

        $this->assertStringContainsString(
            '\App\Repositories\Contracts\PostRepositoryInterface::class => \App\Repositories\PostRepository::class,',
            $provider
        );
        $this->assertStringContainsString(
            '\App\Models\Post::class => \App\Policies\PostPolicy::class,',
            $provider
        );
        $this->assertStringContainsString(
            'PatternServiceProvider::class',
            File::get(base_path('bootstrap/providers.php'))
        );
    }

    public function test_running_twice_never_duplicates_a_binding_or_a_route(): void
    {
        $this->installApiRoutes();

        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);
        $this->artisan('make:pattern', ['name' => 'Post', '--force' => true])->assertExitCode(0);

        $provider = File::get(app_path('Providers/PatternServiceProvider.php'));
        $routes = File::get(base_path('routes/api.php'));

        $this->assertSame(1, substr_count($provider, 'PostRepositoryInterface::class =>'));
        $this->assertSame(1, substr_count($provider, 'Post::class => \App\Policies\PostPolicy::class'));
        $this->assertSame(1, substr_count($routes, 'PostController::class'));
        $this->assertSame(1, substr_count(File::get(base_path('bootstrap/providers.php')), 'PatternServiceProvider::class'));
        $this->assertStringContainsString(ProviderRegistrar::REPOSITORY_MARKER, $provider);
    }

    public function test_it_registers_a_route_for_the_controller(): void
    {
        $this->installApiRoutes();

        $this->artisan('make:pattern', ['name' => 'BlogPost'])->assertExitCode(0);

        $routes = File::get(base_path('routes/api.php'));

        $this->assertStringContainsString('use App\Http\Controllers\BlogPostController;', $routes);
        $this->assertStringContainsString("Route::apiResource('blog-posts', BlogPostController::class);", $routes);
    }

    /**
     * `php artisan install:api` silently skips wiring bootstrap/app.php when
     * routes/api.php already exists, so creating that file ourselves would
     * leave the routes unreachable forever.
     */
    public function test_it_refuses_to_create_the_api_route_file_itself(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'])
            ->expectsOutputToContain('install:api')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist(base_path('routes/api.php'));

        // The rest of the scaffold is generated regardless.
        $this->assertFileExists(app_path('Http/Controllers/PostController.php'));
    }

    public function test_route_middleware_is_configurable(): void
    {
        $this->installApiRoutes();
        config(['make-pattern.routes.middleware' => ['auth:sanctum']]);

        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        $this->assertStringContainsString(
            "Route::middleware(['auth:sanctum'])->apiResource('posts', PostController::class);",
            File::get(base_path('routes/api.php'))
        );
    }

    #[DataProvider('primaryKeyProvider')]
    public function test_the_primary_key_strategy_drives_the_model_and_the_migration(
        string $strategy,
        string $expectedTrait,
        string $expectedColumn,
        string $expectedIdType,
    ): void {
        config(['make-pattern.primary_key.strategy' => $strategy]);

        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        $model = File::get(app_path('Models/Post.php'));
        $migration = File::get(glob(database_path('migrations/*_create_posts_table.php'))[0]);
        $repository = File::get(app_path('Repositories/PostRepository.php'));

        $expectedTrait === ''
            ? $this->assertStringNotContainsString('HasUlids', $model)
            : $this->assertStringContainsString($expectedTrait, $model);

        $this->assertStringContainsString($expectedColumn, $migration);
        $this->assertStringContainsString("find({$expectedIdType} \$id)", $repository);
    }

    public static function primaryKeyProvider(): array
    {
        return [
            'ulid' => ['ulid', 'HasUlids', "\$table->ulid('id')->primary();", 'string'],
            'uuid' => ['uuid', 'HasUuids', "\$table->uuid('id')->primary();", 'string'],
            'increment' => ['increment', '', '$table->id();', 'int'],
        ];
    }

    public function test_tenancy_adds_the_driver_trait_to_the_model(): void
    {
        config([
            'make-pattern.tenancy.enabled' => true,
            'make-pattern.tenancy.driver' => 'stancl',
        ]);

        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        $model = File::get(app_path('Models/Post.php'));

        $this->assertStringContainsString('use Stancl\Tenancy\Database\Concerns\BelongsToTenant;', $model);
        $this->assertStringContainsString('BelongsToTenant', $model);
    }

    public function test_wrap_repository_calls_selects_the_logging_stub(): void
    {
        config(['make-pattern.wrap_repository_calls' => true]);

        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'repository'])->assertExitCode(0);

        $this->assertStringContainsString('Log::error', File::get(app_path('Repositories/PostRepository.php')));
    }

    public function test_authorization_can_be_turned_off(): void
    {
        config(['make-pattern.policies.enforce_in_controller' => false]);

        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        $this->assertStringNotContainsString('Gate::authorize', File::get(app_path('Http/Controllers/PostController.php')));
    }

    public function test_the_controller_enforces_the_policy_by_default(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        $controller = File::get(app_path('Http/Controllers/PostController.php'));

        $this->assertStringContainsString("Gate::authorize('viewAny', Post::class);", $controller);
        $this->assertStringContainsString('extends Controller', $controller);
    }

    public function test_an_unknown_layer_fails_instead_of_silently_doing_nothing(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'requests'])
            ->expectsOutputToContain('Unknown layer [requests]')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist(app_path('Models/Post.php'));
    }

    public function test_an_invalid_entity_name_is_refused(): void
    {
        $this->artisan('make:pattern', ['name' => '../../../pwned'])->assertExitCode(1);
        $this->artisan('make:pattern', ['name' => '123abc'])->assertExitCode(1);
        $this->artisan('make:pattern', ['name' => 'Class'])->assertExitCode(1);

        $this->assertFalse(File::exists(app_path('Models/../../../pwned.php')));
    }

    public function test_only_and_except_select_layers(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model,service'])->assertExitCode(0);

        $this->assertFileExists(app_path('Models/Post.php'));
        $this->assertFileExists(app_path('Services/PostService.php'));
        $this->assertFileDoesNotExist(app_path('Repositories/PostRepository.php'));

        $this->artisan('make:pattern', ['name' => 'Comment', '--except' => 'test,migration,factory'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Models/Comment.php'));
        $this->assertFileDoesNotExist(base_path('tests/Feature/CommentTest.php'));
        $this->assertEmpty(glob(database_path('migrations/*_create_comments_table.php')));
    }

    /**
     * A binding or a route pointing at a class that was never generated breaks
     * every request, so a partial run must not register anything.
     */
    public function test_a_partial_run_never_wires_up_classes_it_did_not_generate(): void
    {
        $this->installApiRoutes();

        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])->assertExitCode(0);

        $this->assertFileDoesNotExist(app_path('Providers/PatternServiceProvider.php'));
        $this->assertStringNotContainsString('PostController', File::get(base_path('routes/api.php')));
        $this->assertStringNotContainsString(
            'PatternServiceProvider',
            File::get(base_path('bootstrap/providers.php'))
        );
    }

    public function test_a_partial_run_registers_only_what_it_generated(): void
    {
        $this->installApiRoutes();

        $this->artisan('make:pattern', ['name' => 'Post', '--except' => 'controller'])->assertExitCode(0);

        $provider = File::get(app_path('Providers/PatternServiceProvider.php'));

        $this->assertStringContainsString('PostRepositoryInterface::class =>', $provider);
        $this->assertStringContainsString('PostPolicy::class', $provider);
        $this->assertStringNotContainsString('PostController', File::get(base_path('routes/api.php')));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--dry-run' => true])->assertExitCode(0);

        $this->assertFileDoesNotExist(app_path('Models/Post.php'));
        $this->assertFileDoesNotExist(base_path('routes/api.php'));
        $this->assertFileDoesNotExist(storage_path('app/make-pattern/history.json'));
    }

    public function test_existing_files_are_skipped_without_force(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])->assertExitCode(0);

        File::put(app_path('Models/Post.php'), '<?php // edited by hand');

        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])->assertExitCode(0);

        $this->assertSame('<?php // edited by hand', File::get(app_path('Models/Post.php')));
    }

    public function test_force_backs_up_the_overwritten_file_and_undo_restores_it(): void
    {
        $path = app_path('Models/Post.php');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<?php // precious hand-written code');

        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model', '--force' => true])
            ->assertExitCode(0);

        $this->assertStringContainsString('class Post extends Model', File::get($path));

        $this->artisan('make:pattern:undo')->assertExitCode(0);

        // The file predates the generator, so undo restores it instead of deleting it.
        $this->assertFileExists($path);
        $this->assertSame('<?php // precious hand-written code', File::get($path));
    }

    public function test_undo_removes_files_the_run_created(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);
        $this->assertFileExists(app_path('Models/Post.php'));

        $this->artisan('make:pattern:undo')->assertExitCode(0);

        $this->assertFileDoesNotExist(app_path('Models/Post.php'));
        $this->assertFileDoesNotExist(app_path('Providers/PatternServiceProvider.php'));
        $this->assertEmpty(glob(database_path('migrations/*_create_posts_table.php')));
    }

    public function test_undo_leaves_files_edited_after_generation_alone(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])->assertExitCode(0);

        File::put(app_path('Models/Post.php'), '<?php // edited after generation');

        $this->artisan('make:pattern:undo')->assertExitCode(0);

        $this->assertFileExists(app_path('Models/Post.php'));
        $this->assertSame('<?php // edited after generation', File::get(app_path('Models/Post.php')));
    }

    public function test_undo_force_removes_files_edited_after_generation(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])->assertExitCode(0);

        File::put(app_path('Models/Post.php'), '<?php // edited after generation');

        $this->artisan('make:pattern:undo', ['--force' => true])->assertExitCode(0);

        $this->assertFileDoesNotExist(app_path('Models/Post.php'));
    }

    public function test_history_lists_runs_and_their_files(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])->assertExitCode(0);

        $this->artisan('make:pattern:history')
            ->expectsOutputToContain('Post')
            ->assertExitCode(0);

        $history = json_decode(File::get(storage_path('app/make-pattern/history.json')), true);

        $this->artisan('make:pattern:history', ['--id' => $history[0]['id']])
            ->expectsOutputToContain('Models/Post.php')
            ->assertExitCode(0);
    }

    public function test_a_regenerated_migration_reuses_its_timestamp(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'migration'])->assertExitCode(0);
        $first = glob(database_path('migrations/*_create_posts_table.php'));

        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'migration', '--force' => true])
            ->assertExitCode(0);
        $second = glob(database_path('migrations/*_create_posts_table.php'));

        $this->assertCount(1, $second);
        $this->assertSame($first, $second);
    }
}
