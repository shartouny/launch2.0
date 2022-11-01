<?php

namespace App\Http\Resources\Orders;

use App\Models\Orders\OrderStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderCollectionListResource extends JsonResource
{
    public static $wrap = 'user';
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $formattedLineItems = [];
        $lineItemCost = 0;
        $ship = false;

        foreach ($this->lineItems as $lineItem){

            $categoryDisplayName = '';
            $price = 0;
            $quantity = 0;
            $printFiles=[];

            if ($lineItem->productVariant && $lineItem->productVariant->printFiles) {
                $categoryDisplayName = $lineItem->productVariant->blankVariant->blank->categoryDisplay ?? null;
                $printFiles = $lineItem->productVariant->printFiles ?? null;
                $quantity = $lineItem->quantity ?? null;
            }

            else if ($lineItem->platformStoreProductVariant && $lineItem->platformStoreProductVariant->productVariant && $lineItem->platformStoreProductVariant->productVariant->printFiles) {
                $categoryDisplayName = $lineItem->platformStoreProductVariant->productVariant->blankVariant->blank->categoryDisplay?? null;
                $printFiles = $lineItem->platformStoreProductVariant->productVariant->printFiles ?? null;
                $quantity = $lineItem->quantity ?? null;
            }

            if($lineItem->platformStoreProductVariant && $lineItem->platformStoreProductVariant->productVariant){
                $price = $lineItem->platformStoreProductVariant->productVariant->blankVariant->price ?? 0;
            }

            //Adding surcharge
            if (strtolower($categoryDisplayName) === 'apparel' && sizeof($printFiles) > 1) {
                $price += 5;
            }

            $formattedLineItems[] = [
                'id' => $this->id,
                'price' => $price,
                'platformStoreProductVariantIgnoreStatus' => $lineItem->platformStoreProductVariant ? $lineItem->platformStoreProductVariant->is_ignored : null,
                'quantity' => $quantity,
                'printFiles' => $printFiles
            ];
        }

        //Calculate estimated cost by lineItem
        if($this->status >= OrderStatus::PAID){
            foreach ($this->payments as $payment) {
                $lineItemCost = $payment->total_cost - $payment->refund + $lineItemCost;
                $ship = false;
            }
        }
        else {
            foreach ($formattedLineItems as $lineItem) {
                if (!$lineItem['platformStoreProductVariantIgnoreStatus'] && $lineItem['price']) {
                    $lineItemCost = $lineItem['price'] * $lineItem['quantity'] + $lineItemCost;
                }
            }
            $ship = true;
        }

        return [
            'id' => $this->id,
            'statusId' => $this->status,
            'store' => $this->store,
            'platformOrderNumber'=> $this->platform_order_number,
            'shippingAddress' => ['first_name' => $this->shippingAddress->first_name, 'last_name' => $this->shippingAddress->last_name],
            'hasError' => $this->has_error,
            'statusReadable' => $this->status_readable,
            'createdAtFormatted' => $this->created_at_formatted,
            'lineItemCost' => $lineItemCost,
            'ship' => $ship
        ];
    }
}


