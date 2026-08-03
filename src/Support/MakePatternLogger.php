<?php

namespace Bouda\MakePattern\Support;

use Illuminate\Support\Facades\Log;

class MakePatternLogger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $log = config('logging.channels.make-pattern')
            ? Log::channel('make-pattern')
            : Log::getFacadeRoot();

        $log->{$level}($message, $context);
    }
}