<?php

namespace App\Formatters\Rutter;

use App\Formatters\Rutter\VariantFormatter;
use App\Models\Platforms\PlatformStoreProduct;
use App\Models\Products\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ProductFormatter
{
    /**
     * @param Product $product
     * @param $platformStore
     * @param array $options
     * @param Log $logger
     * @return array
     * @throws Exception
     */
    static function formatForPlatform($product, $platformStore, $options = [], $logger = null)
    {

        $rutterProduct = [];

        $blanks = $product->variants->pluck('blankVariant')->pluck('blank')->unique();

        //Check if we need to include "Style" as the first option (required when blank_ids on blank_variants differ)
        $useStyleOption = $blanks->count() > 1;

        //Get options
        $productOptions = [];
        $blankOptions = new Collection();
        if ($useStyleOption) {
            $productOption = [];
            $productOption['name'] = "Style";
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

        //Get product type
        $productType = $blanks[0] && $blanks[0]->category ? $blanks[0]->category->id : null;

        //Set Rutter data
        $rutterProduct['name'] = $product->name;
        $rutterProduct['description'] = $product->description;
        $rutterProduct['category_id'] = $productType;
        $rutterProduct['tags'] = $product->tags;
        $rutterProduct['status'] = 'active';
        $rutterProduct['images'] = [];

        $options = [
            "productOptions" => $productOptions,
            "useStyleOption" => $useStyleOption
        ];

        foreach ($product->variants as $variant) {
            $rutterProductVariants = VariantFormatter::formatForPlatform($variant, $platformStore, $options, $logger);
            $rutterProduct['variants'][] = $rutterProductVariants;
        }

        return $rutterProduct;
    }

    /**
     * @param $platformProduct
     * @param $platformStore
     * @return PlatformStoreProduct
     */
    static function formatForDb($platformProduct, $platformStore)
    {
        $platformStoreProduct = new PlatformStoreProduct();
        $platformStoreProduct->platform_store_id = $platformStore->id;
        $platformStoreProduct->platform_product_id = $platformProduct->id;
        $platformStoreProduct->data = json_encode($platformProduct);
        $platformStoreProduct->link = $platformProduct->product_url ?? '';
        $platformStoreProduct->image = $platformProduct->images[0]->src ?? null;
        $platformStoreProduct->title = html_entity_decode(html_entity_decode($platformProduct->name, ENT_QUOTES), ENT_QUOTES);
        $platformStoreProduct->platform_created_at = !empty($platformProduct->created_at) ? Carbon::createFromTimestamp(strtotime($platformProduct->created_at))->toDateTimeString() : null;
        $platformStoreProduct->platform_updated_at = !empty($platformProduct->updated_at) ? Carbon::createFromTimestamp(strtotime($platformProduct->updated_at))->toDateTimeString() : Carbon::createFromTimestamp(strtotime(date("Y-m-d h:i:s")));
        $platformStoreProduct->is_ignored = $platformProduct->status == 'active' ? false : true;

        return $platformStoreProduct;
    }

    /**
     * Removes illegal characters from title
     * @param $title
     * @return mixed
     */
    static function sanitizeTitle($title)
    {
        //Convert all uppercase to ucwords otherwise Etsy complains
        if(strtoupper($title) == $title){
            $title = ucwords(strtolower($title));
        }
        $pattern = "/[^\p{L}\p{Nd}\p{P}\p{Sm}\p{Zs}™©®]/u";
        return substr(self::sanitizeString($title, $pattern), 0, 140);
    }

    static function sanitizeTags($title)
    {
        $pattern = "/[^\p{L}\p{Nd}\p{Zs}\-\'™©®]/u";
        return self::sanitizeString($title, $pattern);
    }

    static function sanitizeString($string, $pattern)
    {
        //If string doesn't match pattern then no changes required
        if (!preg_match($pattern, $string, $matches)) {
            return $string;
        }

        $sanitizedString = $string;
        foreach ($matches as $match) {
            if ($match) {
                $sanitizedString = str_replace($match, '', $sanitizedString);
            }
        }
        return $sanitizedString;
    }
}
