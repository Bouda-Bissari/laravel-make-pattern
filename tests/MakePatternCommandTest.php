<?php

namespace Bouda\MakePattern\Tests;

use Illuminate\Support\Facades\File;

class MakePatternCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('Models'));
        File::deleteDirectory(app_path('Services'));
        File::deleteDirectory(app_path('Repositories'));
        File::deleteDirectory(app_path('Http/Controllers'));
        File::deleteDirectory(app_path('Http/Requests'));
        File::deleteDirectory(app_path('Http/Resources'));
        File::deleteDirectory(app_path('Policies'));
        File::deleteDirectory(app_path('Domain'));
        File::deleteDirectory(base_path('tests/Feature'));
        File::deleteDirectory(base_path('acme'));

        parent::tearDown();
    }

    public function test_it_generates_a_full_scaffold_for_an_entity(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Models/Post.php'));
        $this->assertFileExists(app_path('Services/PostService.php'));
        $this->assertFileExists(app_path('Repositories/PostRepository.php'));
        $this->assertFileExists(app_path('Repositories/Contracts/PostRepositoryInterface.php'));
        $this->assertFileExists(app_path('Http/Controllers/PostController.php'));
        $this->assertFileExists(app_path('Http/Requests/PostStoreRequest.php'));
        $this->assertFileExists(app_path('Http/Requests/PostUpdateRequest.php'));
        $this->assertFileExists(app_path('Http/Resources/PostResource.php'));
        $this->assertFileExists(app_path('Policies/PostPolicy.php'));
    }

    public function test_it_skips_existing_files_without_force(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);
        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);

        $this->assertFileExists(app_path('Models/Post.php'));
    }

    public function test_only_option_limits_generation_to_selected_layers(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Models/Post.php'));
        $this->assertFileDoesNotExist(app_path('Services/PostService.php'));
    }

    public function test_undo_removes_files_from_last_run(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post'])->assertExitCode(0);
        $this->assertFileExists(app_path('Models/Post.php'));

        $this->artisan('make:pattern:undo')->assertExitCode(0);

        $this->assertFileDoesNotExist(app_path('Models/Post.php'));
    }

    public function test_force_option_overwrites_existing_files(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model'])->assertExitCode(0);

        $path = app_path('Models/Post.php');
        $originalMtime = filemtime($path);

        // Ensure a measurable time difference
        sleep(1);

        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model', '--force' => true])
            ->assertExitCode(0);

        $this->assertGreaterThan($originalMtime, filemtime($path));
    }

    public function test_domain_option_generates_files_in_domain_directory(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model,service', '--domain' => 'Blog'])
            ->assertExitCode(0);

        $this->assertFileExists(app_path('Domain/Blog/Models/Post.php'));
        $this->assertFileExists(app_path('Domain/Blog/Services/PostService.php'));

        // Default path should NOT be used
        $this->assertFileDoesNotExist(app_path('Models/Post.php'));

        // Namespace should contain Domain\Blog
        $this->assertStringContainsString(
            'App\\Domain\\Blog\\Services',
            file_get_contents(app_path('Domain/Blog/Services/PostService.php'))
        );
    }

    public function test_namespace_option_overrides_root_namespace(): void
    {
        $this->artisan('make:pattern', ['name' => 'Post', '--only' => 'model', '--namespace' => 'Acme'])
            ->assertExitCode(0);

        $modelPath = base_path('acme/Models/Post.php');
        $this->assertFileExists($modelPath);
        $this->assertStringContainsString('namespace Acme\\Models', file_get_contents($modelPath));
    }
}
