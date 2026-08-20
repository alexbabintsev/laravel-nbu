<?php

declare(strict_types=1);

namespace AlexBabintsev\Nbu;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the client and publishes the config.
 *
 * Written by hand rather than through spatie/laravel-package-tools: this
 * package has no migrations, views or commands, so the tool would be a runtime
 * dependency bought for two method calls.
 */
class NbuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nbu.php', 'nbu');

        $this->app->singleton(Nbu::class, function ($app): Nbu {
            /** @var array{endpoint: string, timeout: int, cache: array{store: ?string, prefix: string, fallback_days: int}} $config */
            $config = $app['config']->get('nbu');

            return new Nbu($config);
        });

        $this->app->alias(Nbu::class, 'nbu');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nbu.php' => config_path('nbu.php'),
            ], 'nbu-config');
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [Nbu::class, 'nbu'];
    }
}
