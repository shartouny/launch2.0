<?php

namespace SunriseIntegration\Etsy;

use Illuminate\Support\ServiceProvider;
use SunriseIntegration\Etsy\Commands\Orders\ImportEtsyOrders;
use SunriseIntegration\Etsy\Commands\Products\ImportEtsyProducts;
use SunriseIntegration\Etsy\Commands\Orders\ImportEtsyStoreOrders;
use SunriseIntegration\Etsy\Commands\Products\ImportEtsyStoreProducts;

class EtsyServiceProvider extends ServiceProvider
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
            __DIR__.'/config/etsy.php' => config_path('etsy.php')
        ], 'etsy');

//        $this->publishes([
//            __DIR__.'/resources' => resource_path('assets/SunriseIntegration/Etsy')
//        ], 'etsy');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportEtsyOrders::class,
                ImportEtsyStoreOrders::class,
                ImportEtsyProducts::class,
                ImportEtsyStoreProducts::class
            ]);
        }
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(config('etsy.name'), function(){
            return new Etsy(config('etsy.name'));
        });
    }
}
