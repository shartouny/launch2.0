<?php

namespace App\Formatters\Shopify;

use App\Formatters\IFormatter;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProductVariant;
use App\Models\Products\ProductVariant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use mysql_xdevapi\Exception;
use SunriseIntegration\Shopify\Models\Product;
use SunriseIntegration\Shopify\Helpers\ShopifyHelper;

class VariantFormatter implements IFormatter
{
    /**
     * @param ProductVariant $variant
     * @param $platformStore
     * @param array $options
     * @param null $logger
     * @return Product\Variant
     */
    static function formatForPlatform($variant, $platformStore, $options = [], $logger = null)
    {
        $fulfillmentService = config('shopify.fulfillment_service');
        $inventoryManagement = null;

        $useStyleOption = $options['useStyleOption'];
        $imageId = $options['imageId'];

        //TODO: Use image_ids from add image to product


        $optionValues = [];

        if(isset($options['productOptions'])){
            // use product options to make sure we have them in the right order for batch products
            foreach($options['productOptions'] as $productOption){
                $isSet = false;
                if($productOption->getName() == 'Style'){
                    $optionValues[] = $variant->blankVariant->blank->name;
                    $isSet = true;
                } else {
                    // find right option
                    foreach ($variant->blankVariant->optionValues as $optionValue) {
                        if($optionValue->option->name == $productOption->getName()){
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
        } else {
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

        $shopifyVariant = new Product\Variant();
        $shopifyVariant->setGrams($variant->blankVariant->weight ?? null);
        $shopifyVariant->setInventoryManagement($inventoryManagement);
        $shopifyVariant->setInventoryPolicy('continue');
        $shopifyVariant->setFulfillmentService($fulfillmentService);
        $shopifyVariant->setImageId($imageId);
        $shopifyVariant->setOption1($option1);
        $shopifyVariant->setOption2($option2);
        $shopifyVariant->setOption3($option3);
        $shopifyVariant->setPrice($variant->price);
        $shopifyVariant->setSku($variant->blankVariant->sku);

        return $shopifyVariant;
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
        $urlBase = ShopifyHelper::getStoreUrlBase($platformStore);
        //created here----> so any filed start with null
        $platformStoreProductVariant = new PlatformStoreProductVariant();
        $platformStoreProductVariant->platform_store_product_id = $options['platform_store_product_id'] ?? null;
        $platformStoreProductVariant->platform_variant_id = $platformData->getId();
        $platformStoreProductVariant->title = $platformData->getTitle();
        $platformStoreProductVariant->sku = $platformData->getSku() ?? "";
        $platformStoreProductVariant->price = $platformData->getPrice();
        $platformStoreProductVariant->data = $platformData->toJson();
        $platformStoreProductVariant->link = $urlBase.'/admin/products/'.$options['product']->getId()  . '/variants/' . $platformData->getId();
        $platformStoreProductVariant->platform_created_at = Carbon::parse($platformData->getCreatedAt())->toDateTimeString() ?? null;
        $platformStoreProductVariant->platform_updated_at = Carbon::parse($platformData->getUpdatedAt())->toDateTimeString() ?? null;

        if(isset($options['product'])){
          foreach ($options['product']->getImages() as $productImage){
            if($productImage->getId() == $platformData->getImageId()){
              $platformStoreProductVariant->image = $productImage->getSrc();
              break;
            }
          }
        }

        if(!$platformStoreProductVariant->image && isset($options['product']->getImages()[0])){
          $platformStoreProductVariant->image = $options['product']->getImages()[0]->getSrc();
        }
        return $platformStoreProductVariant;
    }
}

