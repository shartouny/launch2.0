<?php

namespace SunriseIntegration\Etsy\Commands\Orders;

use App\Console\Commands\SyncPlatformEntityForPlatformStore;

class ImportEtsyStoreOrders extends SyncPlatformEntityForPlatformStore
{
    protected $signature = 'etsy:import-orders-account {--account_id=} {--platform_store_id=} {--min_updated_at=} {--force} {options?*}';

    protected $description = 'Import Etsy Orders';

    protected $logChannel = 'import-orders';

    protected $configName = 'etsy.name';

    protected $entity = 'order';

    protected $connectorCommand = 'importOrders';

    protected $lastSyncField = 'orders_last_imported_at';

    protected $syncFrequencyMinutes = 60;

    public function __construct()
    {
        parent::__construct();
    }

}
