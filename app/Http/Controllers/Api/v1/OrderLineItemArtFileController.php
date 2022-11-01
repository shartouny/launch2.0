<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderLineItemArtFile;
use App\Jobs\ProcessOrderLineItemPrintFile;
use App\Models\Orders\OrderLineItem;
use App\Models\Orders\OrderLineItemArtFile;
use App\Models\Orders\OrderLineItemPrintFile;
use App\Models\Orders\OrderLog;
use App\Models\Products\ProductArtFile;
use App\Models\Products\ProductPrintFile;
use Illuminate\Http\Request;

/**
 * @group  Orders
 *
 * APIs for managing account orders
 */

class OrderLineItemArtFileController extends Controller
{

    public function store(Request $request, $orderId, $lineItemId, $artFileId)
    {
        $request->validate([
            'accountImageId' => 'int|required'
        ]);

        $accountImageId = $request->accountImageId;

        $lineItem = OrderLineItem::where([['id', $lineItemId], ['order_id', $orderId]])->first();
        if (!$lineItem) {
            return $this->responseNotFound('Line Item not found');
        }

        $productArtFile = ProductArtFile::findOrFail($artFileId);
        $orderLineItemArtFile = OrderLineItemArtFile::where([['order_id', $orderId], ['order_line_item_id', $lineItemId], ['product_art_file_id', $productArtFile->id]])->first();

        if (!$orderLineItemArtFile) {
            $orderLineItemArtFile = new OrderLineItemArtFile();
            $orderLineItemArtFile->account_id = $lineItem->account_id;
            $orderLineItemArtFile->order_id = $lineItem->order_id;
            $orderLineItemArtFile->order_line_item_id = $lineItem->id;
            $orderLineItemArtFile->product_art_file_id = $productArtFile->id;
        }

        $orderLineItemArtFile->product_id = $productArtFile->product_id;

        $orderLineItemArtFile->account_image_id = $accountImageId;

        $orderLineItemArtFile->blank_stage_group_id = $productArtFile->blank_stage_group_id;
        $orderLineItemArtFile->blank_stage_id = $productArtFile->blank_stage_id;
        $orderLineItemArtFile->blank_stage_create_type_id = $productArtFile->blank_stage_create_type_id;
        $orderLineItemArtFile->blank_stage_location_id = $productArtFile->blank_stage_location_id;
        $orderLineItemArtFile->blank_stage_location_sub_id = $productArtFile->blank_stage_location_sub_id;
        $orderLineItemArtFile->blank_stage_location_sub_offset_id = $productArtFile->blank_stage_location_sub_offset_id;
        $orderLineItemArtFile->blank_id = $productArtFile->blank_id;

        $orderLineItemArtFile->status = 1;
        $orderLineItemArtFile->save();

        OrderLog::create([
            'order_id' => $orderId,
            'message' => 'Art file replaced for '.$lineItem->sku,
            'message_type' => OrderLog::MESSAGE_TYPE_INFO
        ]);

        if (config('app.env') === 'local') {
            ProcessOrderLineItemArtFile::dispatch($orderLineItemArtFile);
        } else {
            ProcessOrderLineItemArtFile::dispatch($orderLineItemArtFile)->onQueue('print-files');
        }


        return $this->responseOk();
    }
}
