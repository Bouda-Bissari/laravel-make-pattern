<?php

namespace Bouda\MakePattern;

use Bouda\MakePattern\Commands\MakePatternCommand;
use Bouda\MakePattern\Commands\MakePatternHistoryCommand;
use Bouda\MakePattern\Commands\MakePatternUndoCommand;
use Bouda\MakePattern\Support\GenerationLog;
use Illuminate\Support\ServiceProvider;

class MakePatternServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/make-pattern.php' => config_path('make-pattern.php'),
            ], 'make-pattern-config');

            $this->publishes([
                __DIR__.'/../stubs' => resource_path('stubs/vendor/make-pattern'),
            ], 'make-pattern-stubs');

            $this->commands([
                MakePatternCommand::class,
                MakePatternUndoCommand::class,
                MakePatternHistoryCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/make-pattern.php', 'make-pattern');

        $this->app->singleton(GenerationLog::class, function ($app) {
            $config = $app['config']->get('make-pattern');

            return new GenerationLog(
                // 'log_path' is the version 1 key; honour it so a published
                // config from before the rename keeps pointing at the same file.
                logPath: $config['history']['path']
                    ?? $config['log_path']
                    ?? storage_path('app/make-pattern/history.json'),
                backupDirectory: $config['history']['backups']
                    ?? storage_path('app/make-pattern/backups'),
                keep: (int) ($config['history']['keep'] ?? 50),
            );
        });
    }
}
