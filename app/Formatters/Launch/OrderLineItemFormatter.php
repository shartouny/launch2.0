<?php

namespace App\Formatters\Launch;

use App\Formatters\IFormatter;
use App\Models\Orders\Order;
use App\Models\Orders\OrderLineItem;
use App\Models\Platforms\PlatformStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use SunriseIntegration\Shopify\API;

class OrderLineItemFormatter implements IFormatter
{

    static function formatForPlatform($order, $platformStore, $options = [], $logger = null)
    {

    }

    /**
     * @param \SunriseIntegration\Shopify\Models\Order\LineItem $shopifyOrderLineItem
     * @param PlatformStore $platformStore
     * @param array $options
     * @param Log $logger
     * @return mixed
     */
    //TODO: How to separate the formatting and saving
    static function formatForDb($shopifyOrderLineItem, $platformStore, $options = [], $logger = null)
    {
        $lineItem = new OrderLineItem();
        $lineItem->account_id = $platformStore->account_id;
        // $lineItem->order_id =
        $lineItem->platform_line_item_id = $shopifyOrderLineItem->getId();
        $lineItem->platform_product_id = $shopifyOrderLineItem->getProductId();
        $lineItem->platform_variant_id = $shopifyOrderLineItem->getVariantId();
        $lineItem->title = $shopifyOrderLineItem->getTitle();
        $lineItem->quantity = $shopifyOrderLineItem->getQuantity();
        $lineItem->sku = $shopifyOrderLineItem->getSku() ?? "";
        $lineItem->price = $shopifyOrderLineItem->getPrice();

        $propertiesFormatted=[];
        $properties = $shopifyOrderLineItem->getProperties();
        foreach ($properties as $property){
            $property->name = str_replace(' ', '', strtolower($property->name));

            if($property->name === 'textbox'){
                $propertiesFormatted['custom_text'] = $property->value;
            }
            elseif($property->name === '_printfile'){
                $propertiesFormatted['custom_print'] = $property->value;
            }
            else{
                $propertiesFormatted[$property->name] = $property->value;
            }

        }

        $lineItem->properties = json_encode($propertiesFormatted);

        // add any passed options
        foreach($options as $key => $value) {
            $lineItem->{$key} = $value;
        }
        // $lineItem->product_variant_id = ;
        // $lineItem->file_name = ;
        // $lineItem->image_type_id = ;
        return $lineItem;
    }
}
