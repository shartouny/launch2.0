<?php

namespace SunriseIntegration\Shopify\Commands\Orders;

use App\Console\Commands\SyncPlatformEntityForPlatformStore;

class ImportShopifyStoreOrders extends SyncPlatformEntityForPlatformStore
{
    protected $signature = 'shopify:import-orders-account {--account_id=} {--platform_store_id=} {--min_updated_at=} {--force} {options?*}';

    protected $description = 'Import Shopify Orders';

    protected $logChannel = 'import-orders';

    protected $configName = 'shopify.name';

    protected $entity = 'order';

    protected $connectorCommand = 'importOrders';

    protected $lastSyncField = 'orders_last_imported_at';

    protected $syncFrequencyMinutes = 60;

    public function __construct()
    {
        parent::__construct();
    }

}
