<?php

namespace App\Http\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;

class VariantListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $formattedProductVariants = [];

        foreach ($this->variants as $variant){
            $optionValues = [];
            $categoryDisplayName = '';
            $price = 0;
            if ($variant->productVariant && $variant->productVariant->printFiles) {
                $categoryDisplayName = $variant->productVariant->blankVariant->blank->categoryDisplay;
                $printFiles = $variant->productVariant->printFiles;
                $price = $variant->productVariant->blankVariant->price;
            }
            else if ($variant->printFiles) {
                $categoryDisplayName = $variant->blankVariant->blank->categoryDisplay;
                $printFiles = $variant->printFiles;
                $price = $variant->blankVariant->price;
            }
            //Adding surcharge
            if (strtolower($categoryDisplayName) === 'apparel' && sizeof($printFiles) > 1) {
                $price += 5;
            }

            foreach ($variant->blankVariant->optionValues as $optionValue) {
                $optionValues[] = [
                    'id' => $optionValue->id,
                    'name' => $optionValue->name,
                    'hexCode' => $optionValue->hex_code,
                    'option' => $optionValue->option
                ];
            }

            $formattedProductVariants[] = [
                'id' => $variant->id,
                'mockUpFiles' => $variant->mockupFiles,
                'isOutOfStock' => $variant->blankVariant->is_out_of_stock,
                'optionValues' => $optionValues,
                'price' => $price,
                'total' => $variant->price,
                'sku' => $variant->blankVariant->sku
            ];
        }

        return [
            'id' => $this->id,
            'mainImageThumbUrl' => $this->mainImageThumbUrl,
            'name' => $this->name,
            'variants' => $formattedProductVariants
        ];
    }
}
