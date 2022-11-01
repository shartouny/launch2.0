<?php

namespace App\Formatters\Rutter;

use App\Models\Platforms\PlatformStoreProductVariant;
use App\Models\Products\ProductVariant;
use Carbon\Carbon;

class VariantFormatter
{
    const PROPERTY_DELIMITER = ' / ';

    /**
     * @param ProductVariant $variant
     * @param $platformStore
     * @param $options
     * @param null $logger
     * @return array
     */
    static function formatForPlatform($variant, $platformStore, $options, $logger = null)
    {
        $useStyleOption = $options['useStyleOption'];

        $rutterProductVariant = [];
        $rutterProductVariant['sku'] = $variant->blankVariant->sku.'_'.strtotime(Carbon::now());
        $rutterProductVariant['price'] = $variant->price;
        $rutterProductVariant['inventory'] = [
            'total_count' => '999'
        ];
        $rutterProductVariant['weight'] = [
            'unit' => 'g',
            'value' => $variant->blankVariant->weight ?? 0
        ];

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

        foreach ($options['productOptions'] as $key => $productOption){
            if(!empty($optionValues[$key])){
                $rutterProductVariant['option_values'][] = [
                    'name' => $productOption['name'],
                    'value' => $optionValues[$key]
                ];
            }
        }

        if(!isset($rutterProductVariant['option_values'])){
            $rutterProductVariant['option_values'] = [];
        }

        return $rutterProductVariant;
    }

    /**
     * @param array $platformProduct
     * @return PlatformStoreProductVariant
     */
    static function formatForDb($productVariant)
    {
        $platformStoreProductVariant = new PlatformStoreProductVariant();
        $platformStoreProductVariant->platform_variant_id = $productVariant->id;
        $platformStoreProductVariant->data = json_encode($productVariant);
        $platformStoreProductVariant->title = $productVariant->title;
        $platformStoreProductVariant->sku = $productVariant->sku;
        $platformStoreProductVariant->price = $productVariant->price;
        $platformStoreProductVariant->platform_created_at = !empty($productVariant->created_at) ? Carbon::createFromTimestamp(strtotime($productVariant->created_at))->toDateTimeString() : null;
        $platformStoreProductVariant->platform_updated_at = !empty($productVariant->updated_at) ? Carbon::createFromTimestamp(strtotime($productVariant->updated_at))->toDateTimeString() : null;
        $platformStoreProductVariant->image = $productVariant->images[0]->src ?? '';
        $platformStoreProductVariant->is_ignored = false;

        return $platformStoreProductVariant;
    }

}
