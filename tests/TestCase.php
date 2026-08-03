<?php

namespace Bouda\MakePattern\Tests;

use Bouda\MakePattern\MakePatternServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            MakePatternServiceProvider::class,
        ];
    }
}
