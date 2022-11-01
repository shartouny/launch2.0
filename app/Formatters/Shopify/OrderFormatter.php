<?php

namespace App\Formatters\Shopify;

use App\Formatters\IFormatter;
use App\Models\Orders\Order;
use App\Models\Platforms\PlatformStore;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Orders\Address;

class OrderFormatter implements IFormatter
{

    static function formatForPlatform($order, $platformStore, $options = [], $logger = null)
    {

    }

    /**
     * @param \SunriseIntegration\Shopify\Models\Order $shopifyOrder
     * @param PlatformStore $platformStore
     * @param array $options
     * @param Log $logger
     * @return mixed
     */
    static function formatForDb($shopifyOrder, $platformStore, $options = [], $logger = null)
    {
        $order = new Order();
        $order->account_id = $platformStore->account_id;
        $order->platform_store_id = $platformStore->id;
        $order->platform_order_id = $shopifyOrder->getId();
        $order->platform_order_number = $shopifyOrder->getName();
        $order->platform_created_at = Carbon::parse($shopifyOrder->getCreatedAt())->toDateTimeString();
        $order->platform_updated_at = Carbon::parse($shopifyOrder->getUpdatedAt())->toDateTimeString();
        $order->platform_data = $shopifyOrder->toJson();
        $order->email = $shopifyOrder->getEmail();
        $order->total = $shopifyOrder->getTotalPrice();
        return $order;
    }
}
