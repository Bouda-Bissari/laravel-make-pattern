<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A validated entity name.
 *
 * Guarantees the studly form is a legal, non-reserved PHP class name, so the
 * generator can never write unparseable code or a path outside the project.
 */
final class EntityName
{
    /**
     * PHP reserved words and soft-reserved type names that cannot be class names.
     */
    private const RESERVED = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo',
        'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif',
        'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final',
        'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'int',
        'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never',
        'new', 'null', 'object', 'or', 'parent', 'print', 'private', 'protected',
        'public', 'readonly', 'require', 'require_once', 'return', 'self', 'static',
        'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var',
        'void', 'while', 'xor', 'yield',
    ];

    private function __construct(
        public readonly string $studly,
        public readonly string $camel,
        public readonly string $table,
        public readonly string $routeUri,
        public readonly string $snake,
    ) {
    }

    public static function make(string $raw): self
    {
        // Checked before trimming: trim() silently strips NUL and other control
        // characters, and quietly accepting a name someone smuggled them into
        // is worse than refusing it.
        if (preg_match('/[\x00-\x1F\x7F]/', $raw)) {
            throw new InvalidArgumentException('The entity name contains control characters.');
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            throw new InvalidArgumentException('The entity name cannot be empty.');
        }

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $trimmed)) {
            throw new InvalidArgumentException(
                "Invalid entity name [{$trimmed}]. Use letters, digits and underscores only, starting with a letter (e.g. Post, BlogPost, blog_post)."
            );
        }

        $studly = Str::studly($trimmed);

        if ($studly === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $studly)) {
            throw new InvalidArgumentException("Entity name [{$trimmed}] does not produce a valid class name.");
        }

        if (in_array(strtolower($studly), self::RESERVED, true)) {
            throw new InvalidArgumentException("Entity name [{$studly}] is a reserved PHP word and cannot be a class name.");
        }

        $plural = Str::pluralStudly($studly);

        return new self(
            studly: $studly,
            camel: Str::camel($studly),
            table: Str::snake($plural),
            routeUri: str_replace('_', '-', Str::snake($plural)),
            snake: Str::snake($studly),
        );
    }
}
