<?php

namespace App\Console\Commands;

use App\Models\Platforms\Platform;
use Illuminate\Console\Command;
use App\Logger\CronLoggerFactory;
use Illuminate\Support\Facades\DB;

class ImportPlatformOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platforms:import-orders {options?*} {--account_id= : Account to sync} {--platform_store_id= : PlatformStore to sync} {--min_updated_at= : Minimum order updated at in PHP strtotime format i.e. "-1 day", "-14 days"} {--force : Force sync, ignoring last sync time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs order imports for all Platforms';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $options = $this->options();
        $mergedArgs = $arguments;
        foreach ($options as $name => $value){
            if($value) {
                $mergedArgs["--$name"] = $value;
            }
        }

        $loggerFactory = new CronLoggerFactory('sync-orders-cron');
        $logger = $loggerFactory->getLogger();

        $logger->title('Start Importing Platform Orders');
        $platforms = Platform::where('name','!=','Launch')->where('name','!=','teelaunch')->get();


        foreach ($platforms as $platform) {
            $name = $platform->name;

            if(!$platform->enabled){
                $logger->warning("{$name} platform is disabled");
                continue;
            }

            $logger->info("Importing Orders for {$name} connector");

            try {
                unset($arguments['command']);
                $this->call(strtolower("{$name}:import-orders"), $mergedArgs);
            } catch (\Exception $e) {
                $logger->error($e);
            }
        }

        $logger->title('Finished Importing Platform Orders');
    }
}
