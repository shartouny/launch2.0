<?php

namespace SunriseIntegration\Shopify\Commands\Products;

use App\Console\Commands\SyncPlatformEntityForPlatformStore;

class ImportShopifyStoreProducts extends SyncPlatformEntityForPlatformStore
{
  protected $signature = 'shopify:import-products-account  {--account_id=} {--platform_store_id=} {--force=}';

  protected $description = 'Import Shopify Products';

  protected $logChannel = 'import-products';

  protected $configName = 'shopify.name';

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
