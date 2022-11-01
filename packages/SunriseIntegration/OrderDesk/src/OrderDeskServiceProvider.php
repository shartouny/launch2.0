<?php

namespace SunriseIntegration\OrderDesk;

use Illuminate\Support\ServiceProvider;

class OrderDeskServiceProvider extends ServiceProvider
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
            __DIR__.'/config/orderdesk.php' => config_path('orderdesk.php')
        ], 'orderdesk');

        $this->loadRoutesFrom(__DIR__.'/routes.php');

        // if ($this->app->runningInConsole()) {
        //     $this->commands([
        //         ExportOrders::class,
        //         ExportStoreOrders::class
        //     ]);
        // }
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(config('orderdesk.name'), function(){
            return new OrderDesk(config('orderdesk.name'));
        });
    }
}
