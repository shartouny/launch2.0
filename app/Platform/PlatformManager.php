<?php

namespace App\Platform;

use App\Logger\CustomLogger;
use App\Models\Orders\Order;
use App\Logger\ConnectorLoggerFactory;
use App\Models\Orders\OrderStatus;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProductVariant;
use SunriseIntegration\TeelaunchModels\Models\Products\Product;
use SunriseIntegration\TeelaunchModels\Models\Products\ProductVariant;

abstract class PlatformManager
{
    /**
     * @var string
     */
    protected $name;
    /**
     * @var int
     */
    protected $accountId;
    /**
     * @var int
     */
    protected $platformStoreId;
    /**
     * @var PlatformStore
     */
    protected $platformStore;
    /**
     * @var CustomLogger
     */
    protected $logger;
    /**
     * @var int
     */
    protected $totalEntities = 0;
    /**
     * @var int
     */
    protected $failedEntities = 0;

    protected $api; //TODO: Generalize the Platform API Classes to allow type hinting

    protected $lastSyncField;

    protected $syncFrequencyMinutes = 60;

    public function __construct($name, $accountId, $platformStoreId, $logger = null)
    {
        $this->name = $name;
        $this->accountId = $accountId;
        $this->platformStoreId = $platformStoreId;
        $this->platformStore = PlatformStore::find($platformStoreId);
        $this->logger = $logger ?? (new ConnectorLoggerFactory('general', $accountId, $name))->getLogger();
        $this->loadApi();
        app()->instance('current-logger', $this->logger);
    }

    public function setLastSyncField($lastSyncField)
    {
        $this->lastSyncField = $lastSyncField;
    }

    public function setSyncFrequencyMinutes($syncFrequencyMinutes)
    {
        $this->syncFrequencyMinutes = $syncFrequencyMinutes;
    }

    public function getLastSyncField()
    {
        return $this->lastSyncField;
    }

    public function getSyncFrequencyMinutes()
    {
        return $this->syncFrequencyMinutes;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getSettings()
    {
        return $this->platformStore->settings;
    }

    abstract function loadApi();

    abstract function importOrders($arguments = []);

    abstract function processOrder($order);

    abstract function fulfillOrder(Order $order);

    /**
     * @param array $arguments
     * @return mixed
     */
    abstract function importProducts($arguments = []);

    abstract function processProduct($product);
}
