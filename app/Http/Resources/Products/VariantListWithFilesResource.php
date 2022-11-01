<?php

namespace App\Http\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;

class VariantListWithFilesResource extends JsonResource
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
        $formattedArtFiles = [];
        $formattedStageFiles = [];

        foreach ($this->variants as $variant) {
            $optionValues = [];
            $categoryDisplayName = '';
            $price = 0;
            if ($variant->printFiles) {
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

            foreach ($variant->stageFiles as $stageFile) {
                $formattedStageFiles[] = [
                    'id' => $stageFile->id,
                    'account_image_id' => $stageFile->account_image_id,
                ];
            }

            $formattedProductVariants[] = [
                'id' => $variant->id,
                'productId' => $variant->productId,
                'total' => $variant->price,
                'thumb' => $variant->thumbnail,
                'blankVariant' => [
                    'id' => $variant->blankVariant->id,
                    'blankId' => $variant->blankVariant->blank_id,
                    'sku' => $variant->blankVariant->sku,
                    'price' => $price,
                    'blankCategoryId' => $variant->blankVariant->blank->blank_category_id,
                    'fileName' => $variant->blankVariant->image->file_name,
                    'thumbnail' => $variant->blankVariant->thumbnail,
                    'optionValues' => $optionValues,
                ]
            ];
        }

        foreach ($this->ArtFiles as $artFile) {
            $formattedArtFiles[] = [
                'id' => $artFile->id,
                'fileUrl' => $artFile->fileUrl,
                'thumbUrl' => $artFile->thumbUrl,
                'blankStageLocation' => [
                    'shortName' => $artFile->blankStageLocation->short_name
                ]
            ];
        }

        return [
            'id' => $this->id,
            'mainImageThumbUrl' => $this->mainImageThumbUrl,
            'name' => $this->name,
            'variants' => $formattedProductVariants,
            'artFiles' => $formattedArtFiles,
            'stageFiles' => $formattedStageFiles
        ];
    }
}
