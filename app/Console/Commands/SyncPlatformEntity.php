<?php

namespace App\Console\Commands;

use App\Logger\CustomLogger;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformStore;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Logger\CronLoggerFactory;
use Illuminate\Support\Facades\Log;

class SyncPlatformEntity extends Command
{
    /*
     * All properties must be implemented in Child Class
     */
    protected $signature = 'platform-entity:sync {options?*}';

    protected $configName = 'platform.name';

    protected $entity = 'entity_name';

    protected $lastSyncField = 'entities_last_imported_at';

    protected $syncFrequencyField = 'entities_import_frequency';

    protected $syncFrequencyMinutes = 60;

    protected $accountSyncCommand = 'platform-name:import-entities-account';

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
     *
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $options = $this->options();
        $mergedArgs = $arguments;
        foreach ($options as $name => $value) {
            if ($value) {
                $mergedArgs["--$name"] = $value;
            }
        }
        //dd($mergedArgs);

        $name = config($this->configName);
        if (!$name) {
            Log::error("SyncPlatformEntity could not find a config for $this->configName");
            return;
        }

        $loggerFactory = new CronLoggerFactory($name);
        $logger = $loggerFactory->getLogger();

        $platform = Platform::where('name', $name)->firstOrFail();
        $where = [
            ['platform_id', '=', $platform->id],
            ['enabled', true]
        ];


        $accountId = $options['account_id'] ?? null;
        if($accountId){
            $where[] = ['account_id', $accountId];
        }

        $platformStoreId = $options['platform_store_id'] ?? null;
        if($platformStoreId){
            $where[] = ['id', $platformStoreId];
        }

        PlatformStore::where($where)->chunkById(100, function ($platformStores) use ($logger, $arguments, $mergedArgs) {
            foreach ($platformStores as $platformStore) {

                $logger->title("Processing Account $platformStore->account_id Syncing " . str_plural($this->entity));

                $precheckIfSyncNecessary = false; //TODO: We are able to remove the precheck as the called process will check

                if ($precheckIfSyncNecessary) {
                    $lastSyncField = $this->lastSyncField;
                    $syncFrequencyField = $this->syncFrequencyField;

                    if ($this->needsSyncing($platformStore->$lastSyncField, $platformStore->$syncFrequencyField, $logger)) {
                        $process = 'php ' . base_path('artisan ' . $this->accountSyncCommand . ' ') . escapeshellarg($platformStore->account_id) . ' ' . escapeshellarg($platformStore->id) . ' >> /dev/null 2>&1 &';
                        $logger->info('Start sync, executing: ' . $process);
                        exec($process);
                    } else {
                        $logger->info('Skip sync, time elapsed is less than sync frequency');
                    }
                } else {

                    $mergedArgs['--account_id'] = $platformStore->account_id;
                    $mergedArgs['--platform_store_id'] = $platformStore->id;
                    if (isset($arguments['options'])) {
                        foreach ($arguments['options'] as $option) {
                            $exploded = explode('=', $option);
                            if (count($exploded) == 2) {
                                $argName = strpos('--', $exploded[0]) !== 0 ? '--' . $exploded[0] : $exploded[0];
                                $arguments[$argName] = $exploded[1];
                            }
                        }
                        unset($arguments['options']);
                    }
                    unset($arguments['command']);

                    $this->call($this->accountSyncCommand, $mergedArgs);

                    // $process = 'php ' . base_path('artisan ' . $this->accountSyncCommand . ' ') . escapeshellarg($platformStore->account_id) . ' ' . escapeshellarg($platformStore->id) . ' >> /dev/null 2>&1 &';
//                    $logger->info('Start sync, executing: ' . $process);
//                    exec($process);
                }
            }
        });


        $logger->title("Finished spinning syncs for " . ucfirst($name) . " " . str_plural($this->entity));
    }

    /**
     * @param string $lastSync
     * @param string $syncFrequency
     * @param CustomLogger $logger
     * @return bool
     */
    private function needsSyncing($lastSync, $syncFrequency, $logger)
    {
        if (!$syncFrequency) {
            $logger->info('Sync Frequency: Disabled');
            return false;
        }

        $logger->info('Last Sync Time: ' . $lastSync . ' | ' . 'Sync Frequency: ' . $syncFrequency);

        if (!$lastSync) {
            $lastSync = '2017-01-01 01:01:01';
        }

        $lastSyncTime = Carbon::parse($lastSync);
        $timeNow = Carbon::now();

        $difference = $lastSyncTime->diffInMinutes($timeNow);
        if (($difference + 1) >= $syncFrequency) {
            return true;
        } else {
            return false;
        }
    }
}
