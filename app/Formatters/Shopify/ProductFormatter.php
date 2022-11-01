<?php

namespace App\Formatters\Shopify;

use App\Formatters\IFormatter;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProduct;
use App\Models\Platforms\PlatformStoreProductVariant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use SunriseIntegration\Shopify\API;
use SunriseIntegration\Shopify\Models\Product as ShopifyProduct;
use SunriseIntegration\Shopify\Helpers\ShopifyHelper;

class ProductFormatter implements IFormatter
{

    /**
     * @param \App\Models\Products\Product $product
     * @param $platformStore
     * @param array $options
     * @param null $logger
     * @return ShopifyProduct
     */
    static function formatForPlatform($product, $platformStore, $options = [], $logger = null)
    {
        $shopifyProduct = new ShopifyProduct();

        $blanks = $product->variants->pluck('blankVariant')->pluck('blank')->unique();

        //Check if we need to include "Style" as the first option (required when blank_ids on blank_variants differ)
        $useStyleOption = $blanks->count() > 1;

        //Get options
        $productOptions = [];
        $blankOptions = new Collection();
        if ($useStyleOption) {
            $productOption = new ShopifyProduct\Option();
            $productOption->setName("Style");
            $productOptions[] = $productOption;
        }
        foreach ($blanks as $blank) {
            foreach ($blank->options as $blankOption) {
                $blankOptions->push($blankOption);
            }
        }
        $blankOptions->unique();
        $usedProductOptionNames = [];
        foreach ($blankOptions as $option) {
            if (!in_array($option->name ,$usedProductOptionNames) && count($productOptions) < 3) {
                $productOption = new ShopifyProduct\Option();
                $productOption->setName($option->name);
                $productOptions[] = $productOption;
                $usedProductOptionNames[] = $option->name;
            }
        }

        //Get product type
        $productType = $blanks[0] && $blanks[0]->category ? $blanks[0]->category->name : null;

        //Set Shopify data
        $shopifyProduct->setTitle($product->name);
        $shopifyProduct->setBodyHtml($product->description);
        $shopifyProduct->setVendor("teelaunch");
        $shopifyProduct->setTags($product->tags);
        $shopifyProduct->addOptions($productOptions);
        $shopifyProduct->setProductType($productType);
        $shopifyProduct->setPublishedScope('web');

        $options = [
            "productOptions" => $productOptions,
            "useStyleOption" => $useStyleOption,
            "imageId" => null
        ];

        foreach ($product->variants as $variant) {
            // $imageId = null;
            $shopifyVariant = VariantFormatter::formatForPlatform($variant, $platformStore, $options, $logger);
            $shopifyProduct->addVariant($shopifyVariant);
        }

        return $shopifyProduct;
    }

    /**
     * @param \SunriseIntegration\Shopify\Models\Product $shopifyProduct
     * @param PlatformStore $platformStore
     * @param array $options
     * @param Log $logger
     * @return mixed
     */
    //TODO: How to separate the formatting and saving
    static function formatForDb($shopifyProduct, $platformStore, $options = [], $logger = null)
    {
        $urlBase = ShopifyHelper::getStoreUrlBase($platformStore);
        $platformStoreProduct = new PlatformStoreProduct();
        $platformStoreProduct->platform_store_id = $platformStore->id;
        $platformStoreProduct->platform_product_id = $shopifyProduct->getId();
        $platformStoreProduct->data = $shopifyProduct->toJson();
        $platformStoreProduct->image = $shopifyProduct->getImage() ? $shopifyProduct->getImage()->getSrc() : null;
        $platformStoreProduct->title = $shopifyProduct->getTitle();
        $platformStoreProduct->link = $urlBase.'/admin/products/'.$shopifyProduct->getId();
        $platformStoreProduct->platform_created_at = Carbon::parse($shopifyProduct->getCreatedAt())->toDateTimeString() ?? null;
        $platformStoreProduct->platform_updated_at = Carbon::parse($shopifyProduct->getUpdatedAt())->toDateTimeString() ?? null;
        return $platformStoreProduct;
    }
}
