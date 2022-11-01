<?php

namespace SunriseIntegration\Rutter\Commands\Orders;

use App\Console\Commands\SyncPlatformEntityForPlatformStore;

class ImportRutterStoreOrders extends SyncPlatformEntityForPlatformStore
{
    protected $signature = 'rutter:import-orders-account {--account_id=} {--platform_store_id=} {--min_updated_at=} {--force} {options?*}';

    protected $description = 'Import Rutter Orders';

    protected $logChannel = 'import-orders';

    protected $configName = 'rutter.name';

    protected $entity = 'order';

    protected $connectorCommand = 'importOrders';

    protected $lastSyncField = 'orders_last_imported_at';

    protected $syncFrequencyMinutes = 60;

    public function __construct()
    {
        parent::__construct();
    }

}
