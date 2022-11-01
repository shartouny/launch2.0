<?php

namespace SunriseIntegration\Shopify\Commands\Products;

use App\Console\Commands\SyncPlatformEntity;

class ImportShopifyProducts extends SyncPlatformEntity
{

  protected $signature = 'shopify:import-products {--account_id=} {--platform_store_id=} {--force=}';

  protected $description = 'Import Shopify Products';

  protected $logChannel = 'import-products';

  protected $configName = 'shopify.name';

  protected $entity = 'product';

  protected $lastSyncField = 'products_last_imported_at';

  protected $syncFrequencyField = 'sync_products_frequency';

  protected $accountSyncCommand = 'shopify:import-products-account';

  public function __construct()
  {
    parent::__construct();
  }

}
