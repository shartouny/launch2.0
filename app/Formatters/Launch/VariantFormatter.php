<?php

namespace App\Formatters\Launch;

use App\Formatters\IFormatter;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProductVariant;
use App\Models\Products\ProductVariant;
use Carbon\Carbon;
use SunriseIntegration\Shopify\Helpers\ShopifyHelper;

class VariantFormatter implements IFormatter
{
    /**
     * @param ProductVariant $variant
     * @param $platformStore
     * @param array $options
     * @param null $logger
     * @return array
     */
    static function formatForPlatform($variant, $platformStore, $options = [], $logger = null)
    {
        $fulfillmentService = config('shopify.fulfillment_service');
        $inventoryManagement = null;

        $useStyleOption = $options['useStyleOption'];
        $imageId = $options['imageId'];

        $optionValues = [];

        if(isset($options['productOptions'])){
            // use product options to make sure we have them in the right order for batch products
            foreach($options['productOptions'] as $productOption){
                $isSet = false;
                if($productOption['name'] == 'Style'){
                    $optionValues[] = $variant->blankVariant->blank->name;
                    $isSet = true;
                }
                else {
                    // find right option
                    foreach ($variant->blankVariant->optionValues as $optionValue) {
                        if($optionValue->option->name == $productOption['name']){
                            $optionValues[] = $optionValue->name;
                            $isSet = true;
                        }
                    }
                }
                if(!$isSet){
                    // push null for empty option
                    $optionValues[] = null;
                }
            }
        }
        else {
            if ($useStyleOption) {
                $optionValues[] = $variant->blankVariant->blank->name;
            }
            foreach ($variant->blankVariant->optionValues as $optionValue) {
                $optionValues[] = $optionValue->name;
            }
        }

        // find the right options to map
        $option1 = $optionValues[0] ?? null;
        $option2 = $optionValues[1] ?? null;
        $option3 = $optionValues[2] ?? null;

        $launchVariant = [];
        $launchVariant['id'] = strtotime(Carbon::now()) . rand(0,10000);
        $launchVariant['title'] = implode(',', $optionValues);
        $launchVariant['price'] = $variant->price;
        $launchVariant['sku'] = $variant->blankVariant->sku;
        $launchVariant['grams'] = $variant->blankVariant->weight ?? null;
        $launchVariant['inventoryManagement'] = $inventoryManagement;
        $launchVariant['inventoryPolicy'] = 'continue';
        $launchVariant['fulfillmentService'] = $fulfillmentService;
        $launchVariant['imageId'] = $imageId;
        $launchVariant['option1'] = $option1;
        $launchVariant['option2'] = $option2;
        $launchVariant['option3'] = $option3;
        $launchVariant['optionValues'] = $variant->blankVariant->optionValues->pluck('name');

        return $launchVariant;
    }


    /**
     * @param \SunriseIntegration\Shopify\Models\Product\Variant $platformData
     * @param PlatformStore $platformStore
     * @param array $options
     * @param null $logger
     * @return PlatformStoreProductVariant
     */
    static function formatForDb($platformData, $platformStore, $options = [], $logger = null)
    {
        $urlBase = $platformStore->url;
        $platformStoreProductVariant = new PlatformStoreProductVariant();
        $platformStoreProductVariant->platform_store_product_id = $options['platform_store_product_id'] ?? null;
        $platformStoreProductVariant->platform_variant_id = $platformData['id'];
        $platformStoreProductVariant->title = $platformData['title'];
        $platformStoreProductVariant->sku = $platformData['sku'] ?? "";
        $platformStoreProductVariant->price = $platformData['price'];
        $platformStoreProductVariant->data = json_encode($platformData);
        $platformStoreProductVariant->link = $urlBase.'/'.$options['platform_store_product_id'].'/variants/'.$platformData['id'];
        $platformStoreProductVariant->platform_created_at = Carbon::parse(Carbon::now())->toDateTimeString() ?? null;
        $platformStoreProductVariant->platform_updated_at = Carbon::parse(Carbon::now())->toDateTimeString() ?? null;
        $platformStoreProductVariant->image = !empty($platformData['images']) ? $platformData['images'][0] : null;
        return $platformStoreProductVariant;
    }
}

