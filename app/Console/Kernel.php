<?php

namespace App\Console;

use App\Console\Commands\Cleanup;
use App\Console\Commands\SyncPlatformEntity;
use App\Console\Commands\SyncPlatformEntityForPlatformStore;
use App\Console\Commands\ImportPlatformOrders;
use App\Console\Commands\OrderDeskAccountExportOrders;
use App\Console\Commands\OrderDeskExportOrders;
use App\Console\Commands\ProcessAccountOrderShipments;
use App\Console\Commands\ProcessOrderShipments;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use SunriseIntegration\Etsy\Commands\Orders\ImportEtsyOrders;
use SunriseIntegration\Etsy\Commands\Orders\ImportEtsyStoreOrders;
use SunriseIntegration\Etsy\Commands\Products\ImportEtsyStoreProducts;
use SunriseIntegration\Rutter\Commands\Orders\ImportRutterOrders;
use SunriseIntegration\Rutter\Commands\Orders\ImportRutterStoreOrders;
use SunriseIntegration\Rutter\Commands\Products\ImportRutterStoreProducts;
use SunriseIntegration\Shopify\Commands\Orders\ImportShopifyOrders;
use SunriseIntegration\Shopify\Commands\Orders\ImportShopifyStoreOrders;
use SunriseIntegration\Shopify\Commands\Products\ImportShopifyStoreProducts;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        SyncPlatformEntity::class,
        SyncPlatformEntityForPlatformStore::class,
        ImportPlatformOrders::class,
        ProcessOrderShipments::class,
        ProcessAccountOrderShipments::class,
        OrderDeskExportOrders::class,
        OrderDeskAccountExportOrders::class,
        Cleanup::class,
        ImportEtsyOrders::class,
        ImportShopifyOrders::class,
        ImportRutterOrders::class,
        ImportEtsyStoreProducts::class,
        ImportEtsyStoreOrders::class,
        ImportShopifyStoreOrders::class,
        ImportShopifyStoreProducts::class,
        ImportRutterStoreOrders::class,
        ImportRutterStoreProducts::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('platforms:import-products')->withoutOverlapping(5)->everySixHours();

        $schedule->command('platforms:import-orders')->withoutOverlapping(5)->hourly();

        $schedule->command('platforms:process-order-shipments')->withoutOverlapping(5)->everyFifteenMinutes();

        switch(config('app.order_process_frequency_hours')){
            case 0:
                $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->everyMinute();
                break;
            case 1:
                $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->hourly();
                break;
            case 2:
                $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->everyTwoHours();
                break;
            case 3:
                $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->everyThreeHours();
                break;
            case 4:
                // $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->everyFourHours();
                # v1 batch schedule
                foreach (['03:00', '07:00', '11:00', '15:00', '19:00', '23:00'] as $time) {
                    $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->timezone('America/Chicago')->dailyAt($time);
                }
                break;
            case 6:
                $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->everySixHours();
                break;
            default:
                $schedule->command('orderdesk:export-orders')->withoutOverlapping(5)->everySixHours();
                break;
        }

        $schedule->command('cleanup:start')->withoutOverlapping(5)->dailyAt('01:36');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        /** @noinspection PhpIncludeInspection */
        require base_path('routes/console.php');
    }
}
