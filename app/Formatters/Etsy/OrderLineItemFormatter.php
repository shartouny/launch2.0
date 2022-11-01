<?php

namespace App\Formatters\Etsy;

use App\Formatters\IFormatter;
use App\Models\Orders\Order;
use App\Models\Orders\OrderLineItem;
use App\Models\Platforms\PlatformStore;
use Illuminate\Support\Facades\Log;
use SunriseIntegration\Etsy\Models\ListingImage;
use SunriseIntegration\Etsy\Models\ListingProduct;

class OrderLineItemFormatter implements IFormatter
{

    static function formatForPlatform($order, $platformStore, $options = [], $logger = null)
    {

    }

    /**
     * @param \SunriseIntegration\Etsy\Models\Transaction $etsyTransaction
     * @param PlatformStore $platformStore
     * @param array $options
     * @param Log $logger
     * @return mixed
     */
    static function formatForDb($etsyTransaction, $platformStore, $options = [], $logger = null)
    {
        $listingProduct = new ListingProduct($etsyTransaction->getProductData());

        $lineItem = new OrderLineItem();
        $lineItem->account_id = $platformStore->account_id;
        $lineItem->title = $etsyTransaction->getTitle();
        $lineItem->sku = $listingProduct->getSku();
        $lineItem->quantity = $etsyTransaction->getQuantity();
        $lineItem->price = $etsyTransaction->getPrice();
        $lineItem->platform_product_id = $etsyTransaction->getListingId(); //$listingProduct->getProductId();

        $propertiesFormatted=[];
        $variations = $etsyTransaction->getVariations();
        foreach ($variations as $variation){
            if($variation->formatted_name == 'Personalization'){
                $propertiesFormatted['custom_text'] = $variation->formatted_value;
            }
            else{
                $propertiesFormatted[$variation->formatted_name] = $variation->formatted_value;
            }
        }

        $lineItem->properties = json_encode($propertiesFormatted);

        //$variations = $listingProduct->variations;
        $lineItem->platform_variant_id = $listingProduct->getProductId();

        // $lineItem->platform_line_item_id = $listingProduct->getProductId(); // old. Causes unique constraint on db if order with more than 2 of same product
        $lineItem->platform_line_item_id = $etsyTransaction->getTransactionId();

        return $lineItem;
    }
}
