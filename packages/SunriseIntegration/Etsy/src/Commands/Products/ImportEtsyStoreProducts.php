<?php

namespace SunriseIntegration\Etsy\Commands\Products;

use App\Console\Commands\SyncPlatformEntityForPlatformStore;

class ImportEtsyStoreProducts extends SyncPlatformEntityForPlatformStore
{
    protected $signature = 'etsy:import-products-account {--account_id=} {--platform_store_id=} {--force=}';

    protected $description = 'Import Etsy Products';

    protected $logChannel = 'import-products';

    protected $configName = 'etsy.name';

    protected $entity = 'product';

    protected $connectorCommand = 'importProducts';

    protected $lastSyncField = 'products_last_imported_at';

    protected $syncFrequencyField = 'product_import_frequency';

    protected $syncFrequencyMinutes = 60 * 6;

    public function __construct()
    {
        parent::__construct();
    }

}
