<?php

namespace App\Formatters\Launch;

use App\Formatters\IFormatter;
use App\Models\Platforms\PlatformStore;
use App\Models\Products\Product;
use App\Models\Platforms\PlatformStoreProduct;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ProductFormatter implements IFormatter
{

    /**
     * @param Product $product
     * @param $platformStore
     * @param array $options
     * @param null $logger
     * @return array
     */
    static function formatForPlatform($product, $platformStore, $options = [], $logger = null)
    {
        $launchProduct = [];

        $blanks = $product->variants->pluck('blankVariant')->pluck('blank')->unique();

        //Check if we need to include "Style" as the first option (required when blank_ids on blank_variants differ)
        $useStyleOption = $blanks->count() > 1;

        //Get options
        $productOptions = [];
        $blankOptions = new Collection();
        if ($useStyleOption) {
            $productOption = [];
            $productOption['name'] = 'Style';
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
                $productOption = [];
                $productOption['name'] = $option->name;
                $productOptions[] = $productOption;
                $usedProductOptionNames[] = $option->name;
            }
        }

        //Set Shopify data
        $launchProduct['title'] = $product->name;
        $launchProduct['description'] = $product->description;
        $launchProduct['vendor'] = 'teelaunch';
        $launchProduct['image'] = $product->main_image_thumb_url;
        $launchProduct['tags'] = $product->tags;
        $launchProduct['options'] = $productOptions;
        $launchProduct['category'] = array(
            'id' => $blanks[0] && $blanks[0]->category ? $blanks[0]->category->id : null,
            'name' => $blanks[0] && $blanks[0]->category ? $blanks[0]->category->name : null
        );

        $options = [
            "productOptions" => $productOptions,
            "useStyleOption" => $useStyleOption,
            "imageId" => null
        ];

        foreach ($product->variants as $variant) {
            $launchVariant = VariantFormatter::formatForPlatform($variant, $platformStore, $options, $logger);
            $launchProduct['variants'][] = $launchVariant;
        }

        return $launchProduct;
    }

    /**
     * @param \SunriseIntegration\Shopify\Models\Product $shopifyProduct
     * @param PlatformStore $platformStore
     * @param array $options
     * @param Log $logger
     * @return mixed
     */
    //TODO: How to separate the formatting and saving
    static function formatForDb($launchProduct, $platformStore, $options = [], $logger = null)
    {
        $urlBase = $platformStore->url;

        $platformStoreProduct = new PlatformStoreProduct();
        $platformStoreProduct->platform_store_id = $platformStore->id;
        $platformStoreProduct->platform_product_id = strtotime(Carbon::now()) . rand(0,10000);
        $platformStoreProduct->data = json_encode($launchProduct);
        $platformStoreProduct->image = $launchProduct['image'];
        $platformStoreProduct->title = $launchProduct['title'];
        $platformStoreProduct->link = $urlBase.'/'.$platformStoreProduct->platform_product_id.'/'.strtolower(str_replace(' ', '-', $launchProduct['title']));
        $platformStoreProduct->platform_created_at = Carbon::parse(Carbon::now())->toDateTimeString() ?? null;
        $platformStoreProduct->platform_updated_at = Carbon::parse(Carbon::now())->toDateTimeString() ?? null;
        return $platformStoreProduct;
    }
}
