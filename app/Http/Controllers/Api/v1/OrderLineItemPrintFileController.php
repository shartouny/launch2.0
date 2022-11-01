<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Orders\OrderLineItemPrintFileCollectionResource;
use App\Http\Resources\Orders\OrderLineItemPrintFileResource;
use App\Jobs\DeleteOrderLineItemPrintFile;
use App\Jobs\ProcessOrderLineItemPrintFile;
use App\Jobs\ProcessProductVariantPrintFile;
use App\Models\Orders\OrderLineItem;
use App\Models\Orders\OrderLineItemPrintFile;
use App\Models\Orders\OrderLog;
use App\Models\Products\ProductPrintFile;
use App\Models\Products\ProductVariantPrintFile;
use Illuminate\Http\Request;

/**
 * @group  Orders
 *
 * APIs for managing account orders
 */

class OrderLineItemPrintFileController extends Controller
{

    /**
     * Get Order Line Item Print Files
     *
     * Get account order line item print files
     *
     * @urlParam lineItemId required Item Id
     */
    public function index(Request $request, $orderId, $lineItemId)
    {
        $printFileId = $request->printFileId;

        $orderLineItemPrintFiles = OrderLineItemPrintFile::where([['order_id', $orderId], ['order_line_item_id', $lineItemId], ['product_print_file_id', $printFileId]])->get();

        return new OrderLineItemPrintFileCollectionResource($orderLineItemPrintFiles);
    }

    public function store(Request $request, $orderId, $lineItemId)
    {
        $printFileId = $request->printFileId;
        $printFile = ProductVariantPrintFile::find($printFileId);
        if (!$printFile) {
            return $this->responseNotFound('Print File not found');
        }

        $request->validate([
            'image' => OrderLineItemPrintFile::getImageRequirementsValidation($printFile)
        ]);

        $lineItem = OrderLineItem::where([['id', $lineItemId], ['order_id', $orderId]])->first();
        if (!$lineItem) {
            return $this->responseNotFound('Line Item not found');
        }

        $orderLineItemPrintFile = OrderLineItemPrintFile::where([['order_id', $orderId], ['order_line_item_id', $lineItemId], ['print_file_id', $printFileId]])->first();
        if (!$orderLineItemPrintFile) {
            $orderLineItemPrintFile = OrderLineItemPrintFile::create([
                'account_id' => $lineItem->account_id,
                'order_id' => $lineItem->order_id,
                'order_line_item_id' => $lineItem->id,
                'print_file_id' => $printFileId
            ]);
        }
        $orderLineItemPrintFile->saveFileFromRequest($request->file('image'), null, null, $makePublic = true);

        OrderLog::create([
            'order_id' => $orderId,
            'message' => 'Print file replaced for '.$lineItem->sku,
            'message_type' => OrderLog::MESSAGE_TYPE_INFO
        ]);

        return new OrderLineItemPrintFileResource($orderLineItemPrintFile);
    }

    public function destroy(Request $request, $orderId, $lineItemId, $orderLineItemPrintFileId)
    {
        $orderLineItemPrintFile = OrderLineItemPrintFile::where([['id', $orderLineItemPrintFileId], ['order_id', $orderId], ['order_line_item_id', $lineItemId]])->first();
        if (!$orderLineItemPrintFile) {
            return $this->responseNotFound();
        }

        // Soft Delete OrderLineItemPrintFile Job
        if(config('app.env') === 'local'){
            DeleteOrderLineItemPrintFile::dispatch($orderLineItemPrintFile);
        } else {
            DeleteOrderLineItemPrintFile::dispatch($orderLineItemPrintFile)->onQueue('deletes');
        }

        $orderLineItemPrintFile->delete();

        return $this->responseOk();
    }

}
