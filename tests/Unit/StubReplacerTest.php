<?php

namespace Bouda\MakePattern\Tests\Unit;

use Bouda\MakePattern\Support\StubReplacer;
use PHPUnit\Framework\TestCase;

class StubReplacerTest extends TestCase
{
    public function test_it_replaces_spaced_placeholders(): void
    {
        $result = StubReplacer::fill('Hello {{ name }}!', ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function test_it_replaces_unspaced_placeholders(): void
    {
        $result = StubReplacer::fill('Hello {{name}}!', ['name' => 'World']);

        $this->assertSame('Hello World!', $result);
    }

    public function test_it_replaces_multiple_variables(): void
    {
        $stub = 'namespace {{ namespace }}; class {{ class }} {}';

        $result = StubReplacer::fill($stub, [
            'namespace' => 'App\\Models',
            'class'     => 'Post',
        ]);

        $this->assertSame('namespace App\\Models; class Post {}', $result);
    }

    public function test_it_leaves_unmatched_placeholders_unchanged(): void
    {
        $result = StubReplacer::fill('Hello {{ unknown }}!', ['name' => 'World']);

        $this->assertSame('Hello {{ unknown }}!', $result);
    }

    public function test_it_replaces_same_placeholder_multiple_times(): void
    {
        $result = StubReplacer::fill('{{ entity }}::find() returns {{ entity }}', ['entity' => 'Post']);

        $this->assertSame('Post::find() returns Post', $result);
    }

    public function test_it_handles_empty_replacements(): void
    {
        $result = StubReplacer::fill('Hello {{ name }}!', []);

        $this->assertSame('Hello {{ name }}!', $result);
    }
}
