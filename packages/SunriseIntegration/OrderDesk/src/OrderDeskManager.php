<?php

namespace SunriseIntegration\OrderDesk;

use App\Models\Orders\Order;
use App\Platform\PlatformManager;
use Exception;

class OrderDeskManager extends PlatformManager
{
    /**
     * @var API
     */
    protected $api;

    public function loadApi()
    {
        try {
            $this->api = new API(config('orderdesk.api_key'), config('orderdesk.store_id'), $this->logger);
        } catch (Exception $e) {
            $this->logger->error($e);
        }
        return $this->api;
    }

    /**
     * @param array $arguments
     */
    public function importOrders($arguments = []){}

    public function processOrder($order){}

    public function fulfillOrder(Order $order){}

    /**
     * @param array $arguments
     * @return mixed|void
     */
    public function importProducts($arguments = []){}

    public function processProduct($product){}
}
