<?php

namespace SunriseIntegration\Etsy\Commands\Products;

use App\Console\Commands\SyncPlatformEntity;

class ImportEtsyProducts extends SyncPlatformEntity
{

    protected $signature = 'etsy:import-products {--account_id=} {--platform_store_id=} {--force : Force sync, ignoring last sync time}';

    protected $description = 'Import Etsy Products';

    protected $logChannel = 'import-products';

    protected $configName = 'etsy.name';

    protected $entity = 'product';

    protected $lastSyncField = 'products_last_imported_at';

    protected $syncFrequencyField = 'sync_products_frequency';

    protected $accountSyncCommand = 'etsy:import-products-account';

    public function __construct()
    {
        parent::__construct();
    }

}
