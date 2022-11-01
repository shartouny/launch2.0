<?php

namespace SunriseIntegration\Shopify\Commands\Orders;

use App\Console\Commands\SyncPlatformEntity;

class ImportShopifyOrders extends SyncPlatformEntity
{

    protected $signature = 'shopify:import-orders {--account_id=} {--platform_store_id=} {--min_updated_at= : Minimum order updated at in PHP strtotime format i.e. "-1 day", "-14 days"} {--force : Force sync, ignoring last sync time} {options?*} ';

    protected $description = 'Import Shopify Orders';

    protected $logChannel = 'import-orders';

    protected $configName = 'shopify.name';

    protected $entity = 'order';

    protected $syncFrequencyField = 'sync_order_frequency';

    protected $accountSyncCommand = 'shopify:import-orders-account';

    public function __construct()
    {
       parent::__construct();
    }

}
