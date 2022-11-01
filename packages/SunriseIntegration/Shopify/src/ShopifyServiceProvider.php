<?php

namespace SunriseIntegration\Shopify;

use Illuminate\Support\ServiceProvider;
use SunriseIntegration\Shopify\Commands\Products\ImportShopifyStoreProducts;
use SunriseIntegration\Shopify\Http\Middleware\ShopifyAuth;
use SunriseIntegration\Shopify\Http\Middleware\ValidateHmac;
use SunriseIntegration\Shopify\Commands\Products\ImportShopifyProducts;
use SunriseIntegration\Shopify\Commands\Orders\ImportShopifyOrders;
use SunriseIntegration\Shopify\Commands\Orders\ImportShopifyStoreOrders;

class ShopifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        $this->loadViewsFrom(__DIR__.'/resources/views', 'shopify');

        $this->publishes([
            __DIR__.'/config/shopify.php' => config_path('shopify.php')
        ], 'shopify');

        $this->publishes([
            __DIR__.'/resources' => resource_path('assets/SunriseIntegration/Shopify')
        ], 'shopify');

        $this->app['router']->aliasMiddleware('hmac', ValidateHmac::class);
        $this->app['router']->aliasMiddleware('shopify', ShopifyAuth::class);

      if ($this->app->runningInConsole()) {
        $this->commands([
          ImportShopifyProducts::class,
          ImportShopifyStoreProducts::class,
          ImportShopifyOrders::class,
          ImportShopifyStoreOrders::class,
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
        $this->app->bind('Shopify', function(){
            return new Shopify('Shopify');
        });
    }
}
