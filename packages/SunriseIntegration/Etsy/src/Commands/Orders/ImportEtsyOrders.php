<?php

namespace SunriseIntegration\Etsy\Commands\Orders;

use App\Console\Commands\SyncPlatformEntity;

class ImportEtsyOrders extends SyncPlatformEntity
{

    protected $signature = 'etsy:import-orders {--account_id=} {--platform_store_id=} {--min_updated_at= : Minimum order updated at in PHP strtotime format i.e. "-1 day", "-14 days"} {--force : Force sync, ignoring last sync time} {options?*} ';

    protected $description = 'Import Etsy Orders';

    protected $logChannel = 'import-orders';

    protected $configName = 'etsy.name';

    protected $entity = 'order';

    protected $syncFrequencyField = 'sync_order_frequency';

    protected $accountSyncCommand = 'etsy:import-orders-account';

    public function __construct()
    {
       parent::__construct();
    }

}
