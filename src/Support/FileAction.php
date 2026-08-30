<?php

namespace Bouda\MakePattern\Support;

/**
 * How a run touched a file, which decides how undo reverses it.
 */
final class FileAction
{
    /** Written by this run; undo deletes it. */
    public const CREATED = 'created';

    /** Existed before and was replaced with --force; undo restores the backup. */
    public const OVERWRITTEN = 'overwritten';

    /** Existed before and was edited in place; undo restores the backup. */
    public const APPENDED = 'appended';
}
