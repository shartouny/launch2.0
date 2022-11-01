<?php

namespace App\Console\Commands;

use App\Logger\CustomLogger;
use App\Models\Platforms\PlatformStore;

use Illuminate\Console\Command;
use App\Logger\ConnectorLoggerFactory;
use Carbon\Carbon;


class SyncPlatformEntityForPlatformStore extends Command
{
    /**
     * Properties below must be implemented in Child Class
     */
    protected $signature = 'platform:sync-entity {--account_id=} {--platform_store_id=}';

    protected $lastSyncField = 'entity_last_imported_at';

    /**
     * @deprecated use $syncFrequencyMinutes to set frequency
     * @var string
     */
    protected $syncFrequencyField = 'entity_import_frequency';

    protected $syncFrequencyMinutes = 60;

    protected $logChannel = 'sync_entity';

    protected $configName = 'platform.name';

    protected $entity = 'entity';

    protected $connectorCommand = 'syncEntity'; //Can this be removed and deduced from other data?

    /**
     * Dont implement these in Child Class
     */
    protected $accountId;

    protected $logger;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->accountId = $this->options()['account_id'];//$this->argument('accountId');

        $config = config($this->configName);

        $loggerFactory = new ConnectorLoggerFactory(
            $this->logChannel,
            $this->accountId,
            $config);

        $this->logger = $loggerFactory->getLogger();

        $this->logger->title('SYNC ' . strtoupper(str_plural($this->entity)) . ' CRON');

        $forceRun = $this->option('force') ?? false;
        $this->logger->info('Force: '.($forceRun ? 'true' : 'false'));

        try {
            if ($forceRun || $this->canRunCron( $this->logger)) {
                try {
                    $connectorManager = $this->getConnectorManager(
                        $config,
                        $this->logChannel
                    );

                    $connectorCommand = $this->connectorCommand;

                    $connectorManager->$connectorCommand(array_merge($this->arguments(), $this->options()));

                } catch (\Exception $e) {
                    $this->logger->error($e);
                } catch (\Throwable $th) {
                    $this->logger->error($th);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e);
        }

        $this->logger->title('END SYNC ' . strtoupper(str_plural($this->entity)) . ' CRON');
    }

    protected function canRunCron(CustomLogger $logger)
    {
        $platformStoreId = $this->options()['platform_store_id'];//$this->argument('platformStoreId');
        $platformStore = PlatformStore::find($platformStoreId);

        if (!$platformStore) {
            $logger->error("Failed to find Platform Store id {$platformStoreId}");
            return false;
        }

        if (!$platformStore->enabled) {
            $logger->error("Platform Store id {$platformStoreId} is disabled");
            return false;
        }

        return $this->needsSyncing($platformStore, $logger);
    }

    protected function needsSyncing($platformStore, CustomLogger $logger)
    {
        $frequency = $this->syncFrequencyMinutes;

        $lastSync = $platformStore->settings()->where('key', $this->lastSyncField)->first();
        $lastSync = $lastSync->value ?? '2017-01-01 01:01:01';

        if (config('app.env') === 'local') {
             $lastSync = '2017-01-01 01:01:01';
        }

        $logger->info('Last Sync Time: ' . $lastSync . ' | ' . 'Sync Frequency: ' . $frequency . ' minutes');

        $lastSyncTime = Carbon::parse($lastSync);
        $timeNow = Carbon::now();

        $difference = $lastSyncTime->diffInMinutes($timeNow);
        if (($difference + 1) >= $frequency) {
            $logger->info('Time elapsed is GREATER THAN Sync Frequency. Start Sync');
            return true;
        } else {
            $logger->info('Time elapsed is LESS THAN Sync Frequency: DO NOTHING');
            return false;
        }
    }

    protected function getConnectorManager($name, $logChannel = null)
    {
        $service = app($name);
        $accountId = $this->options()['account_id'];
        $platformStoreId = $this->options()['platform_store_id'];
        $service->setAccountId($accountId);

        $connectorManager = $service->getConnectorManager($platformStoreId, $logChannel);
        $connectorManager->setLastSyncField($this->lastSyncField);
        $connectorManager->setSyncFrequencyMinutes($this->syncFrequencyMinutes);

        return $connectorManager;
    }
}
