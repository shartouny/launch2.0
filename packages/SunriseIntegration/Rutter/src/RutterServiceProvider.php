<?php

namespace SunriseIntegration\Rutter;

use Illuminate\Support\ServiceProvider;
use SunriseIntegration\Rutter\Commands\Orders\ImportRutterOrders;
use SunriseIntegration\Rutter\Commands\Orders\ImportRutterStoreOrders;
use SunriseIntegration\Rutter\Commands\Products\ImportRutterProducts;
use SunriseIntegration\Rutter\Commands\Products\ImportRutterStoreProducts;

class RutterServiceProvider extends ServiceProvider
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
            __DIR__.'/config/rutter.php' => config_path('rutter.php')
        ], 'rutter');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportRutterOrders::class,
                ImportRutterStoreOrders::class,
                ImportRutterProducts::class,
                ImportRutterStoreProducts::class
            ]);
        }
    }

    public function register()
    {
        $this->app->bind(config('rutter.name'), function(){
            return new Rutter(config('rutter.name'));
        });
    }
}
