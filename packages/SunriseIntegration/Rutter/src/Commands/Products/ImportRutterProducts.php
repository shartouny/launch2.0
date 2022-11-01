<?php

namespace SunriseIntegration\Rutter\Commands\Products;

use App\Console\Commands\SyncPlatformEntity;

class ImportRutterProducts extends SyncPlatformEntity
{

    protected $signature = 'rutter:import-products {--account_id=} {--platform_store_id=} {--force : Force sync, ignoring last sync time}';

    protected $description = 'Import Rutter Products';

    protected $logChannel = 'import-products';

    protected $configName = 'rutter.name';

    protected $entity = 'product';

    protected $lastSyncField = 'products_last_imported_at';

    protected $syncFrequencyField = 'sync_products_frequency';

    protected $accountSyncCommand = 'rutter:import-products-account';

    public function __construct()
    {
        parent::__construct();
    }

}
