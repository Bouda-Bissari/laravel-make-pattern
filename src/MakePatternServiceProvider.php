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

        $this->app->bind(GenerationLog::class, function ($app) {
            return new GenerationLog($app['config']->get('make-pattern.log_path'));
        });
    }
}
