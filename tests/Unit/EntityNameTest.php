<?php

namespace Bouda\MakePattern\Tests\Unit;

use Bouda\MakePattern\Support\EntityName;
use Bouda\MakePattern\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class EntityNameTest extends TestCase
{
    public function test_it_derives_every_form_from_a_studly_name(): void
    {
        $entity = EntityName::make('BlogPost');

        $this->assertSame('BlogPost', $entity->studly);
        $this->assertSame('blogPost', $entity->camel);
        $this->assertSame('blog_posts', $entity->table);
        $this->assertSame('blog-posts', $entity->routeUri);
        $this->assertSame('blog_post', $entity->snake);
    }

    public function test_it_accepts_snake_case_input(): void
    {
        $this->assertSame('BlogPost', EntityName::make('blog_post')->studly);
    }

    #[DataProvider('traversalProvider')]
    public function test_it_rejects_names_that_could_escape_the_target_directory(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntityName::make($name);
    }

    public static function traversalProvider(): array
    {
        return [
            'parent directory' => ['../../../pwned'],
            'absolute path' => ['/etc/passwd'],
            'windows path' => ['..\\..\\evil'],
            'dot' => ['Post.php'],
            'null byte' => ["Post\0"],
            'empty' => [''],
            'whitespace' => ['   '],
        ];
    }

    #[DataProvider('invalidClassNameProvider')]
    public function test_it_rejects_names_that_are_not_valid_php_classes(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntityName::make($name);
    }

    public static function invalidClassNameProvider(): array
    {
        return [
            'leading digit' => ['123abc'],
            'reserved word' => ['Class'],
            'reserved word lowercase' => ['interface'],
            'reserved type' => ['string'],
            'hyphenated' => ['blog-post'],
        ];
    }
}
