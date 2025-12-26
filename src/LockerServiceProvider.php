<?php

namespace Mvonline\Locker;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Locker package.
 *
 * @package Mvonline\Locker
 */
class LockerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('locker', function ($app) {
            return new LockerManager(
                $app['cache'],
                $app['events'],
                $app['config']->get('locker.connection')
            );
        });

        $this->app->alias('locker', LockerManager::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/locker.php' => config_path('locker.php'),
        ], 'locker-config');
    }
}

