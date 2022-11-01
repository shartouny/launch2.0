<?php

namespace SunriseIntegration\Launch;

use Illuminate\Support\ServiceProvider;

class LaunchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        $this->publishes([
            __DIR__.'/config/launch.php' => config_path('launch.php')
        ], 'launch');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(config('launch.name'), function(){
            return new Launch(config('launch.name'));
        });
    }
}
