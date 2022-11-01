<?php

namespace App\Formatters\Rutter;

use App\Formatters\IFormatter;
use App\Models\Orders\OrderLineItem;
use App\Models\Platforms\PlatformStore;
use Illuminate\Support\Facades\Log;

class OrderLineItemFormatter implements IFormatter
{

    static function formatForPlatform($order, $platformStore, $options = [], $logger = null)
    {

    }

    /**
     * @param array $etsyTransaction
     * @param PlatformStore $platformStore
     * @param array $options
     * @param Log $logger
     * @return mixed
     */
    static function formatForDb($orderLineItem, $platformStore, $options = [], $logger = null)
    {
        $lineItem = new OrderLineItem();
        $lineItem->account_id = $platformStore->account_id;
        $lineItem->title = $orderLineItem->title;
        $lineItem->sku = $orderLineItem->sku;
        $lineItem->quantity = $orderLineItem->quantity;
        $lineItem->price = $orderLineItem->price;
        $lineItem->platform_product_id = $orderLineItem->product_id;
        $lineItem->platform_variant_id = $orderLineItem->variant_id;
        $lineItem->platform_line_item_id = $orderLineItem->id;

        return $lineItem;
    }
}
