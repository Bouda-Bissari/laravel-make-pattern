<?php

namespace Bouda\MakePattern\Tests\Unit;

use Bouda\MakePattern\Generators\FileGenerator;
use Bouda\MakePattern\Tests\TestCase;
use Illuminate\Support\Facades\File;

class FileGeneratorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/make-pattern-test-'.uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpDir);
        parent::tearDown();
    }

    private function stubPath(): string
    {
        $path = $this->tmpDir.'/test.stub';
        file_put_contents($path, "namespace {{ namespace }};\nclass {{ class }} {}\n");

        return $path;
    }

    private function layerConfig(string $suffix = 'Service'): array
    {
        return [
            'namespace' => 'App\\Services',
            'path'      => $this->tmpDir,
            'suffix'    => $suffix,
            'enabled'   => true,
            'stub'      => 'test',
        ];
    }

    public function test_it_generates_a_file_with_correct_content(): void
    {
        $generator = new FileGenerator();
        $path = $generator->generate('Post', $this->layerConfig(), $this->stubPath());

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertStringContainsString('class PostService', file_get_contents($path));
        $this->assertStringContainsString('namespace App\\Services', file_get_contents($path));
    }

    public function test_it_returns_null_when_file_exists_and_not_forced(): void
    {
        $generator = new FileGenerator();
        $stub = $this->stubPath();

        $generator->generate('Post', $this->layerConfig(), $stub);
        $result = $generator->generate('Post', $this->layerConfig(), $stub, force: false);

        $this->assertNull($result);
    }

    public function test_it_overwrites_when_forced(): void
    {
        $generator = new FileGenerator();
        $stub = $this->stubPath();

        $first  = $generator->generate('Post', $this->layerConfig(), $stub);
        $second = $generator->generate('Post', $this->layerConfig(), $stub, force: true);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first, $second);
    }

    public function test_it_applies_domain_prefix_to_path_and_namespace(): void
    {
        $generator = new FileGenerator();
        $config = [
            'namespace' => 'App\\Services',
            'path'      => app_path('Services'),
            'suffix'    => 'Service',
            'enabled'   => true,
            'stub'      => 'test',
        ];

        // We don't actually write the file here — just verify namespace resolution
        $reflection = new \ReflectionClass($generator);
        $method     = $reflection->getMethod('resolveConfig');
        $method->setAccessible(true);

        $resolved = $method->invoke($generator, $config, 'Blog', null);

        $this->assertStringContainsString('Domain\\Blog', $resolved['namespace']);
        $this->assertStringContainsString('Domain/Blog', $resolved['path']);
    }

    public function test_it_applies_namespace_override(): void
    {
        $generator = new FileGenerator();
        $config = [
            'namespace' => 'App\\Services',
            'path'      => app_path('Services'),
            'suffix'    => 'Service',
            'enabled'   => true,
            'stub'      => 'test',
        ];

        $reflection = new \ReflectionClass($generator);
        $method     = $reflection->getMethod('resolveConfig');
        $method->setAccessible(true);

        $resolved = $method->invoke($generator, $config, null, 'Acme');

        $this->assertStringStartsWith('Acme', $resolved['namespace']);
        $this->assertStringNotContainsString('App', $resolved['namespace']);
    }
}
