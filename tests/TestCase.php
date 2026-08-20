<?php

declare(strict_types=1);

namespace AlexBabintsev\Nbu\Tests;

use AlexBabintsev\Nbu\NbuServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [NbuServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // An array store per test, so cached rates never leak between them.
        $app['config']->set('cache.default', 'array');
    }
}
