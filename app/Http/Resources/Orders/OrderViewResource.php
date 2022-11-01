<?php

namespace App\Http\Resources\Orders;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Resources\Json\JsonResource;
use function Clue\StreamFilter\append;

class OrderViewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $formattedLineItems = [];
        $printFiles = [];


        foreach ($this->lineItems as $lineItem) {
            $formattedArtFile = [];
            $formattedStageFile = [];
            $categoryDisplayName = null;
            $price =0;
            $retailPrice = 0;

            if($lineItem->productVariant && $lineItem->productVariant->blankVariant && $lineItem->productVariant->blankVariant->blank) {
                $categoryDisplayName = $lineItem->productVariant->blankVariant->blank->categoryDisplay;
                $printFiles = $lineItem->productVariant->printFiles;
                $price = $lineItem->productVariant->blankVariant->price;
                $formattedArtFile  =  isset($lineItem->productVariant->product->artFiles) && sizeof($lineItem->productVariant->product->artFiles) > 0 ?
                    array_map(function ($item){
                        return     [
                            'productArtFileId' => $item['product_art_file_id'],
                            'fileUrl' =>  $item['file_url'],
                            'thumbUrl' =>  $item['thumb_url'],
                            'status' => $item['status'],
                        ];
                    },$lineItem->ArtFiles->toArray()) : [];
                $retailPrice= $lineItem->productVariant->price;
            }
            else if($lineItem->platformStoreProductVariant && $lineItem->platformStoreProductVariant->productVariant && $lineItem->platformStoreProductVariant->productVariant->printFiles && $lineItem->platformStoreProductVariant->productVariant->blankVariant) {
                $categoryDisplayName = $lineItem->platformStoreProductVariant->productVariant->blankVariant->blank->categoryDisplay;
                $printFiles = $lineItem->platformStoreProductVariant->productVariant->printFiles;
                $price = $lineItem->platformStoreProductVariant->productVariant->blankVariant->price;
                $retailPrice= $lineItem->platformStoreProductVariant->productVariant->price;
                $formattedArtFile  =  isset($lineItem->platformStoreProductVariant->productVariant->product->artFiles) && sizeof($lineItem->platformStoreProductVariant->productVariant->product->artFiles) > 0 ?
                    array_map(function ($item){
                        return     [
                            'productArtFileId' => $item['product_art_file_id'],
                            'fileUrl' =>  $item['file_url'],
                            'thumbUrl' =>  $item['thumb_url'],
                            'status' => $item['status'],
                        ];
                    },$lineItem->ArtFiles->toArray()) : [];
            }

            //Adding surcharge
            if (strtolower($categoryDisplayName) === 'apparel' && sizeof($printFiles) > 1) {
                $price += 5;
            }

            //Formatting Stage Files data
            if($lineItem->productVariant){
                foreach ($lineItem->productVariant->stageFiles as $stageFile) {
                    $formattedStageFile [] = $stageFile->id ? [
                        'id'=> $stageFile->id,
                        'blankStage' => isset($stageFile->blankStage) && sizeof($stageFile->blankStage->create_types) > 0 ? [
                            'id' => $stageFile->blankStage->id,
                            'storeWidthMin' => $stageFile->blankStage->create_types[0]->image_requirement->store_width_min,
                            'storeWidthMax' => $stageFile->blankStage->create_types[0]->image_requirement->store_width_max,
                            'storeHeightMin' => $stageFile->blankStage->create_types[0]->image_requirement->store_height_min,
                            'storeHeightMax' => $stageFile->blankStage->create_types[0]->image_requirement->store_height_max,
                            'storeSizeMinReadable' => $stageFile->blankStage->create_types[0]->image_requirement->store_size_min_readable,
                            'storeSizeMaxReadable' => $stageFile->blankStage->create_types[0]->image_requirement->store_size_max_readable,
                        ] : [],
                        'productArtFile' => $stageFile->productArtFile ? [
                            'id'=> $stageFile->productArtFile->id,
                            'width'=> $stageFile->productArtFile->width,
                            'height'=> $stageFile->productArtFile->height,
                            'fileUrl' => $stageFile->productArtFile->fileUrl,
                            'thumbUrl' => $stageFile->productArtFile->thumbUrl,
                            'blankStageId' => $stageFile->productArtFile->blankStageId,
                        ] : null,
                        'shortName' => $stageFile->blankStageLocation->short_name,
                        'imageType' => new BaseResource($stageFile->imageType),
                        'blankStageCreateType' => $stageFile->blankStageCreateType,
                    ] : null;
                }
            }
            else if($lineItem->platformStoreProductVariant && $lineItem->platformStoreProductVariant->productVariant){
                foreach ($lineItem->platformStoreProductVariant->productVariant->stageFiles as $stageFile) {
                    $formattedStageFile [] = $stageFile->id ? [
                        'id'=> $stageFile->id,
                        'blankStage' =>  isset($stageFile->blankStage) && sizeof($stageFile->blankStage->create_types) > 0 ? [
                            'id' => $stageFile->blankStage->id,
                            'storeWidthMin' => $stageFile->blankStage->create_types[0]->image_requirement->store_width_min,
                            'storeWidthMax' => $stageFile->blankStage->create_types[0]->image_requirement->store_width_max,
                            'storeHeightMin' => $stageFile->blankStage->create_types[0]->image_requirement->store_height_min,
                            'storeHeightMax' => $stageFile->blankStage->create_types[0]->image_requirement->store_height_max,
                            'storeSizeMinReadable' => $stageFile->blankStage->create_types[0]->image_requirement->store_size_min_readable,
                            'storeSizeMaxReadable' => $stageFile->blankStage->create_types[0]->image_requirement->store_size_max_readable,
                        ] : null,
                        'productArtFile' => $stageFile->productArtFile ? [
                            'id'=> $stageFile->productArtFile->id,
                            'width'=> $stageFile->productArtFile->width,
                            'height'=> $stageFile->productArtFile->height,
                            'fileUrl' => $stageFile->productArtFile->fileUrl,
                            'thumbUrl' => $stageFile->productArtFile->thumbUrl
                        ] : null,
                        'shortName' => $stageFile->blankStageLocation->short_name,
                        'imageType' => new BaseResource($stageFile->imageType),
                        'blankStageCreateType' => $stageFile->blankStageCreateType
                    ] : null;
                }
            }
            else {
                $retailPrice = $lineItem->price;
            }

            //Formatting LineItems
            $formattedLineItems [] = $lineItem->id ? [
                'id' => $lineItem->id,
                'categoryDisplay' => $categoryDisplayName,
                'printFilesSize' => sizeof($printFiles),
                'quantity' => $lineItem->quantity,
                'name' => $lineItem->title,
                'sku' => $lineItem->sku,
                'productVariantId' => $lineItem->productVariant->id ?? $lineItem->product_variant_id,
                'platformStoreProductVariant'=> $lineItem->platformStoreProductVariant && $lineItem->platformStoreProductVariant->id ? [
                    'id' => $lineItem->platformStoreProductVariant->id,
                    'platformStoreProductId' => $lineItem->platformStoreProductVariant->platformStoreProduct->id,
                    'isIgnored'=> $lineItem->platformStoreProductVariant->is_ignored,
                    'productVariant' => $lineItem->platformStoreProductVariant->productVariant ? [
                        'id' => $lineItem->platformStoreProductVariant->productVariant->id,
                        'thumbnail' => $lineItem->platformStoreProductVariant->productVariant->thumbnail,
                        'blankVariant' => $lineItem->platformStoreProductVariant->productVariant->blankVariant? [
                            'id' => $lineItem->platformStoreProductVariant->productVariant->blankVariant->id,
                            'sku' => $lineItem->platformStoreProductVariant->productVariant->blankVariant->sku,
                            'price' => $price,
                            'optionValues'=> new BaseResource($lineItem->platformStoreProductVariant->productVariant->blankVariant->optionValues)
                        ] : null,
                        'stageFiles' =>  $formattedStageFile
                    ] : null
                ] : null,
                'price' => $price,
                'retailPrice'=>$retailPrice,
                'properties' => $lineItem->properties,
                'thumbUrl' => $lineItem->thumb_url,
                'platformVariantId' => $lineItem->platform_variant_id,
                'productVariant' => $lineItem->productVariant ? [
                    'id'=> $lineItem->productVariant->id,
                    'thumbnail' => $lineItem->productVariant->thumbnail,
                    'blankVariant' => $lineItem->productVariant->blankVariant ? [
                        'id' => $lineItem->productVariant->blankVariant->id,
                        'sku' => $lineItem->productVariant->blankVariant->sku,
                        'optionValues'=> new BaseResource($lineItem->productVariant->blankVariant->optionValues)
                    ] : null,
                    'stageFiles' => $formattedStageFile,
                    'artFiles' => $formattedArtFile,

                ]: null,
                'artFiles' => $formattedArtFile,
            ] : null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'statusReadable' => $this->statusReadable,
            'platformOrderNumber'=> $this->platform_order_number,
            'lineItems' => $formattedLineItems,
            'payments' => new BaseResource($this->payments),
            'billingAddress' => new BaseResource($this->billingAddress),
            'shippingAddress' => new BaseResource($this->shippingAddress),
            'shipments' => new BaseResource($this->shipments),
            'store' => new BaseResource($this->store),
            'logs' => new BaseResource($this->logs),
            'orderDeskId' => $this->order_desk_id,
            'nextOrderId' => $this->nextOrderId,
            'previousOrderId' => $this->previousOrderId,
            'hasError' => $this->has_error,
        ];
    }
}
