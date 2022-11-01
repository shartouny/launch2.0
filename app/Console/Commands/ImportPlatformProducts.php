<?php

namespace App\Console\Commands;

use App\Models\Platforms\Platform;
use Illuminate\Console\Command;
use App\Logger\CronLoggerFactory;
use Illuminate\Support\Facades\DB;

class ImportPlatformProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platforms:import-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs product imports for all Platforms';

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
        $loggerFactory = new CronLoggerFactory('sync-platforms-cron');
        $logger = $loggerFactory->getLogger();

        $logger->title('Start Importing Platform Products');

        $platforms = Platform::where('name','!=','Launch')->get();

        foreach ($platforms as $platform) {
            $name = $platform->name;

            if(!$platform->enabled){
                $logger->warning("{$name} platform is disabled");
                continue;
            }

            $logger->info("Importing Products for {$name} connector");

            try {
                $this->call(strtolower("{$name}:import-products"));
            } catch (\Exception $e) {
                $logger->error($e);
            }
        }

        $logger->title('Finished Importing Platform Orders');
    }
}
