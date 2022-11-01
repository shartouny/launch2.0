<?php


namespace App\Jobs;

use App\Logger\CronLoggerFactory;
use App\Models\Platforms\Platform;
use Illuminate\Support\Facades\Artisan;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStore;

class ReSyncPlatformStoreProducts extends BaseJob
{
    protected $platformStore;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(PlatformStore $platformStore)
    {
        parent::__construct();
        $this->platformStore = $platformStore;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $loggerFactory = new CronLoggerFactory('re-sync-platform-store-products-cron');
        $logger = $loggerFactory->getLogger();

        $logger->title('Start Platform Store Products Re-sync');

        $platformStore = $this->platformStore;
        $platformStoreId = $this->platformStore->id;
        $platformStoreName = $this->platformStore->name;
        $platformStoreAccount = $this->platformStore->account_id;

        $platform = Platform::where('id', $platformStore->platform_id)->first();
        $platformName = $platform->name;

        if(!$platform->enabled){
            $logger->warning("{$platformName} platform is disabled");
            return;
        }

        $logger->info("Importing Products for {$platformName}:{$platformStoreName} connector under account: {$platformStoreAccount}");

        try {
            Artisan::call(strtolower("{$platformName}:import-products-account --account_id={$platformStoreAccount} --platform_store_id={$platformStoreId}"));
        } catch (\Exception $e) {
            $logger->error($e);
        }

        $logger->title('Finished Importing Platform Products');
    }

}
