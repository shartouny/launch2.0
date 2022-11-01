<?php

namespace SunriseIntegration\Rutter;

use App\Formatters\Etsy\ProductFormatter as EtsyProductFormatter;
use App\Formatters\Etsy\VariantFormatter as EtsyVariantFormatter;
use App\Formatters\Rutter\OrderFormatter;
use App\Formatters\Rutter\OrderLineItemFormatter;
use App\Formatters\Rutter\ProductFormatter as RutterProductFormatter;
use App\Formatters\Rutter\VariantFormatter as RutterVariantFormatter;
use App\Jobs\ProcessOrderLineItemImage;
use App\Models\Accounts\Account;
use App\Models\Orders\Order;
use App\Models\Orders\OrderLog;
use App\Models\Orders\OrderStatus;
use App\Models\Platforms\PlatformStoreProductVariantMapping;
use App\Models\Shipments\ShipmentStatus;
use App\Platform\PlatformManager;
use Carbon\Carbon;
use Exception;
use App\Traits\EmailNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SunriseIntegration\Etsy\Helpers\EtsyHelper;
use SunriseIntegration\Etsy\Models\Listing;
use SunriseIntegration\Etsy\Models\ListingInventory;
use SunriseIntegration\Etsy\Models\ListingProduct;
use SunriseIntegration\Rutter\Http\Api;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountSettings;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProduct;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProductVariant;

class RutterManager extends PlatformManager
{
  use EmailNotification;
    /**
     * @var \SunriseIntegration\Rutter\Http\API
     */
    protected $api;
    protected $holdOrders;

    public function loadApi()
    {
        try {
            $this->api = new Api($this->platformStore->apiToken);
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        return $this->api;
    }

    public function importOrders($arguments = [])
    {
        if (!$this->platformStore || $this->platformStore->deleted_at) {
            $this->logger->info("Skipping {$this->platformStore->name}");
            return;
        }

        $accountSetting = AccountSettings::where([['account_id', $this->platformStore->account_id],['key','order_hold']])->first();
        $this->holdOrders = $accountSetting ? boolval($accountSetting->value) : false;

        $limit = config('app.env') === 'local' ? 1 : 50;
        $cursor = '';

        $minLastModified = $arguments['min_updated_at'] ?? '-1 day';

        $params = [
            'updated_at_min' => strtotime($minLastModified)*1000,
            'updated_at_max' => strtotime('now')*1000,
            'expand' => 'transactions',
            'payment_status' => 'paid'
        ];

        $this->platformStore->settings()->updateOrCreate([
            'key' => 'orders_last_imported_at'
        ], [
            'value' => Carbon::now()
        ]);

        do {
            $response = $this->api->getAllOrders($limit, $cursor, $params);
            $this->logger->info("Get Orders | Limit: $limit | Cursor: $cursor | HTTP {$response['code']}");

            if ($response['code'] !== 200) {
                //Failed
                $this->logger->error(json_encode($response));
                return;
            }

            $cursor = $response['data']->next_cursor ?? null;
            $orders = $response['data']->orders ?? [];

            //Iterate over each order
            $this->logger->info("Received " . count($orders) . " Orders");
            foreach ($orders as $order) {
                $this->processOrder($order);
            }
        } while (!empty($cursor));
    }

    public function processOrder($order)
    {

        if ($order->fulfillment_status != 'unfulfilled' && config('app.env') !== 'local') {
            $this->logger->info("Skipping Order, already shipped");
            return;
        }

        $this->logger->subheader("Process Order ID {$order->platform_id}");
        $formattedOrder = OrderFormatter::formatForDb($order, $this->platformStore);

        $existingOrder = Order::withoutGlobalScopes(['logs','lineItems','storePlatform','shipping','payments','blankVariant','art'])->where([['platform_store_id', $this->platformStore->id], ['platform_order_id', $formattedOrder->platform_order_id]])->withTrashed()->first();
        if($existingOrder) {
            $message = "Order already imported | Order ID: $existingOrder->id";
            if($existingOrder->trashed()){
                $message .= " | Order Deleted";
            }

            $this->logger->info($message);
            return;
        }

        $account = Account::find($this->accountId);
        $this->logger->debug("Data: " . json_encode($order));

        if($this->holdOrders === null){
            $accountSetting = AccountSettings::where([['account_id', $this->platformStore->account_id],['key','order_hold']])->first();
            $this->holdOrders = $accountSetting ? boolval($accountSetting->value) : false;
        }

        $formattedOrder->status = $this->holdOrders === true ? OrderStatus::HOLD : OrderStatus::PENDING;

         // Get Not teelaunch Orders Ignore Flag & check if valid to create order if flag is on
        $ignoredLineItemsCount = 0;
        $orderLineItemsCount = count($order->line_items);
        $ignoreNotTeeLaunchOrders = AccountSettings::where('account_id', '=', $this->accountId)->where('key', '=', 'ignore_not_teelaunch_order')->first();
        if($ignoreNotTeeLaunchOrders && $ignoreNotTeeLaunchOrders->value){
            foreach ($order->line_items as $key => $lineItem) {
                $ignoreLineItem = false;
                $this->logger->subheader("Process Line Item ID {$lineItem->product_id}");
                $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where('platform_product_id', $lineItem->product_id)->first();
                if($platformStoreProduct){
                    //Product has no variants, use it's id as a variant id
                    if(stripos($lineItem->variant_id, 'Not Found') === 0){
                        $lineItem->variant_id = $lineItem->product_id;
                    }

                    $platformStoreProductVariant = $platformStoreProduct->variants()
                        ->where('platform_store_product_id', $platformStoreProduct->id)
                        ->where('platform_variant_id', $lineItem->variant_id)
                        ->with('productVariantMapping')->first();
                    if($platformStoreProductVariant){
                        if($platformStoreProductVariant->is_ignored == 1){
                            // variant is ignored
                            $ignoreLineItem = true;
                        } else if (!isset($platformStoreProductVariant->productVariant)) {
                            $ignoreLineItem = true;
                        }
                    } else {
                        // variant not in system, ignore
                        $ignoreLineItem = true;
                    }
                }
                else {
                    // product not in system, ignore
                    $ignoreLineItem = true;
                }
                if($ignoreLineItem){
                    $this->logger->info("Removing ignored line item {$lineItem->product_id} from order data");
                    unset($order->line_items[$key]);
                    $ignoredLineItemsCount++;
                }
            }

            if($ignoredLineItemsCount == $orderLineItemsCount){
                $this->logger->info("Skipping Order Creation, all order line items are marked as not teelaunch");
                return;
            }
        }

         $formattedOrder->save();
        $formattedOrder->logs()->create([
            'message' => 'Order imported into teelaunch',
            'message_type' => OrderLog::MESSAGE_TYPE_INFO
        ]);

        $this->logger->info("Order ID $formattedOrder->id Saved to DB | Platform Order ID: $formattedOrder->platform_order_id");

        //Array to store the out of stock variants
        $variantsOutOfStock = [];
        $variantsName = [];
        $product = [];

         //Iterate over order line items
        foreach ($order->line_items as $lineItem) {
            $this->logger->subheader("Process Line Item ID {$lineItem->product_id}");

            //Fetch line item product details
            $productDetails = $this->api->getProduct($lineItem->product_id);
            if ($productDetails['code'] === 200 && !empty($productDetails['data']->product)) {
                $product = $productDetails['data']->product;
            }

            //Save product to platform products table
            $this->logger->debug("Check for Platform Store Product with platform_product_id: " . $lineItem->product_id);
            $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where([
                ['platform_store_id',$this->platformStore->id],
                ['platform_product_id', $lineItem->product_id]
            ])->first();

            if (!$platformStoreProduct) {
                $this->logger->info("Platform store product not found, importing order line item into DB");
                if(empty($product)) {
                    $this->logger->warning("Failed to get Product Listing");
                    $this->logger->warning("Skipping Order Line Item");
                    continue;
                }

                $this->logger->subheader("Process Product ID {$product->id}");
                $this->logger->debug("Product: " . json_encode($product));
                $this->processProduct($product);

                $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where([
                    ['platform_store_id',$this->platformStore->id],
                    ['platform_product_id', $lineItem->product_id]
                ])->first();
            }

            if ($platformStoreProduct) {
                $platformStoreProductVariant = $platformStoreProduct->variants()->where('platform_variant_id', $lineItem->variant_id)->first();

                if(!$platformStoreProductVariant){
                    // try with trashed if we didn't find anything
                    $platformStoreProductVariant = $platformStoreProduct->variants()->withTrashed()->where('platform_variant_id', $lineItem->variant_id)->first();
                    if ($platformStoreProductVariant && $platformStoreProductVariant->deleted_at) {
                        // variant was soft deleted for some reason. Restore it
                        $platformStoreProductVariant->restore();
                    }
                }

                $importLineItem = true;
                if (!$platformStoreProductVariant && $importLineItem) {
                    //Only import line item
                    $variantBySku = null;
                    if(isset($lineItem->sku) && $lineItem->sku != '') {
                        $variantBySku = $platformStoreProduct->variants()->where('sku', $lineItem->sku)->with('productVariantMapping')->first();
                    }

                    $platformStoreProductVariantData = RutterVariantFormatter::formatForDb($lineItem);
                    $platformStoreProductVariant = $platformStoreProduct->variants()->save($platformStoreProductVariantData);

                    if($variantBySku && isset($variantBySku->productVariantMapping)) {
                        $this->logger->info("Mapping by SKU due to new Listing Product ID");
                        $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping();
                        $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;
                        $platformStoreProductVariantMapping->product_variant_id = $variantBySku->productVariantMapping->product_variant_id;
                        $platformStoreProductVariantMapping->save();
                        $this->logger->info("Platform Store Product Variant Mapping Created | Data: " . $platformStoreProductVariantMapping->toJson());

                        if($variantBySku->delete()) {
                            $this->logger->info("Deleted old Product Variant id {$variantBySku->id}");
                        }
                    } else {
                        $this->logger->info("No existing mapping found for SKU {$lineItem->sku}, new product will remain unmapped");
                    }

                    $this->logger->info("Platform Store Product Variant Created | ID: $platformStoreProductVariant->id");
                }

                if($platformStoreProductVariant && $platformStoreProductVariant->productVariant && $platformStoreProductVariant->productVariant->blankVariant){
                    //Check if variant is out of stock
                    $blankVariant = $platformStoreProductVariant->productVariant->blankVariant;
                    if ($blankVariant->is_out_of_stock) {
                        $outOfStockMessage = [];
                        //Push the out of stock variant here
                        $variantsOutOfStock[] = $blankVariant->is_out_of_stock;

                        foreach ($blankVariant->optionValues as $variantOption) {
                            $outOfStockMessage[] = $variantOption->name;
                        }

                        $outOfStockMessage = implode(',', $outOfStockMessage);
                        $variantsName[] = $outOfStockMessage;
                        $formattedOrder->logs()->create([
                            'order_id' => $formattedOrder->id,
                            'message' => "Variant $outOfStockMessage is out of stock",
                            'message_type' => OrderLog::MESSAGE_TYPE_INFO
                        ]);
                    }
                }
                else {
                    $this->logger->info("Unable to map product {$lineItem->product_id}. Will remain unmapped");
                }

                if($platformStoreProductVariant){
                    //Check if at least one of the products has order_hold flag set to true
                    if($formattedOrder->status !== OrderStatus::HOLD && $platformStoreProductVariant->shouldApplyOrderHold()){
                        $formattedOrder->status = OrderStatus::HOLD;
                        $formattedOrder->save();
                    }
                }
                else {
                    $this->logger->error("Unable to make platformStoreProductVariant");
                    $formattedOrder->status = OrderStatus::HOLD;
                    $formattedOrder->save();
                }
            }

            //Break quantities into separate line items
            $lineItemQuantity = $lineItem->quantity;
            $lineItemId = $lineItem->id;
            if(!isset($lineItem->sku)){
                $this->logger->debug("Skipping lineitem, lineitem has no sku!");
                continue;
            }
            for ($quantity = 0; $quantity < $lineItemQuantity; $quantity++) {
                //Format data

                $lineItem->id = $lineItemId;
                $lineItem->quantity = 1;
                $imageUrl = $product->images[0]->src ?? '';

                $formattedLineItem = OrderLineItemFormatter::formatForDb($lineItem, $this->platformStore);
                $formattedLineItem = $formattedOrder->lineItems()->save($formattedLineItem);

                if($imageUrl){
                    if (config('app.env') === 'local') {
                        ProcessOrderLineItemImage::dispatch($formattedLineItem, $imageUrl);
                    } else {
                        ProcessOrderLineItemImage::dispatch($formattedLineItem, $imageUrl)->onQueue('order-line-items-image');
                    }
                }

                $this->logger->info("Order Line Item ID $formattedLineItem->id Saved to DB | SKU: $formattedLineItem->sku | Qty: $formattedLineItem->quantity");
            }
        }

        //if at lease one variant out of stock, the order is set to OUT OF STOCK
        if (!empty($variantsOutOfStock)) {
            $this->logger->info('Line item(s) out of stock, setting status to OUT OF STOCK');
            $formattedOrder->status = OrderStatus::OUT_OF_STOCK;
            $formattedOrder->save();
            $this->sendOutOfStockEmail($account->user->email, $order, $variantsName);
        }
    }

    public function fulfillOrder(Order $order): void
    {
        $this->logger->header("Fulfill Order ID {$order->id}");

        if (!isset($order->platform_order_id)) {
            $this->logger->error('Receipt ID not found on Order Object');
            return;
        }

        $order->status = OrderStatus::PROCESSING_FULFILLMENT;
        $order->save();

        $pendingShipments = $order->shipments()->where('status', ShipmentStatus::PENDING)->get();
        $this->logger->subheader("Pending Shipment Count: " . count($pendingShipments));
        foreach ($pendingShipments as $shipment) {
            $this->logger->info("Send Shipment ID $shipment->id ");

            $shipment->status = ShipmentStatus::PROCESSING;
            $shipment->save();

            $response = $this->api->submitTracking($order->platform_order_id, $shipment->tracking_number, $shipment->carrier);

            if ($response['code'] == 200) {
                    $shipment->status = ShipmentStatus::FULFILLED;
                    $shipment->save();

                    $order->logs()->create([
                        'message' => "Tracking $shipment->tracking_number sent to store",
                        'message_type' => OrderLog::MESSAGE_TYPE_INFO
                    ]);

                    $this->logger->debug("Response: " . json_encode($response['data']));
                    $this->logger->info("Success");
                }
            else {
                    $this->logger->error("Failed | Response: " . json_encode($response));
                    $errorMessage = !empty($response['message']) ? json_encode($response['message']) : '';

                    if (stripos($errorMessage, 'Invalid request field lin') !== false) {
                        $shipment->status = ShipmentStatus::FULFILLED;
                        $order->logs()->create([
                            'message' => "Order already Fulfilled",
                            'message_type' => OrderLog::MESSAGE_TYPE_WARNING
                        ]);
                    }
                    else{
                        $shipment->status = ShipmentStatus::PENDING;
                        $order->logs()->create([
                            'message' => "Failed to send tracking #$shipment->tracking_number to store",
                            'message_type' => OrderLog::MESSAGE_TYPE_ERROR
                        ]);
                    }
                    $shipment->save();
                }

        }

        if ($order->order_desk_split <= $order->shipments()->where('status', ShipmentStatus::FULFILLED)->count()) {
            $this->logger->info("Set Order to Fulfilled");

            $order->logs()->create([
                'message' => "Order Fulfilled",
                'message_type' => OrderLog::MESSAGE_TYPE_INFO
            ]);

            $order->status = OrderStatus::FULFILLED;
            $order->save();
        }
    }

    public function importProducts($arguments = [])
    {
        if (!$this->platformStore || $this->platformStore->deleted_at) {
            $this->logger->info("Skipping {$this->platformStore->name}");
            return;
        }

        $limit = config('app.env') === 'local' ? 1 : 50;
        $cursor = '';

        $params = [];

        do {
            $response = $this->api->getAllProducts($limit, $cursor, $params);

            $this->logger->info("Get Products | Limit: $limit | Cursor: $cursor | HTTP {$response['code']}");
            if ($response['code'] !== 200) {
                //Failed
                $this->logger->error(json_encode($response));
                return;
            }

            $this->logger->debug("Response: " . json_encode($response));

            $this->platformStore->settings()->updateOrCreate([
                'key' => 'products_last_imported_at'
            ], [
                'value' => Carbon::now()
            ]);

            $cursor = $response['data']->next_cursor ?? null;
            $products = $response['data']->products;
            $this->logger->info("Received " . count($products) . " Products");

            //Iterate over each product
            foreach ($products as $product) {
                $this->logger->subheader("Process Product ID {$product->id}");
                $this->logger->debug("Product: " . json_encode($product));

                $this->processProduct($product);
            }
        } while (!empty($cursor));
    }

    public function processProduct($product)
    {
        $platformStoreProductUpdated = false;

        $platformStoreProduct = PlatformStoreProduct::withoutGlobalScopes(['default'])->where([
            ['platform_store_id',$this->platformStore->id],
            ['platform_product_id', $product->id]
        ])->first();

        $platformStoreProductData = RutterProductFormatter::formatForDb($product, $this->platformStore);

        if($platformStoreProduct){
            $this->logger->info("Platform Store Product Exist | ID: $platformStoreProduct->id");
            if (empty($platformStoreProduct->image) || strtotime($platformStoreProduct->platform_updated_at) < strtotime($platformStoreProductData->platform_updated_at)) {
                $platformStoreProductUpdated = true;
                $platformStoreProduct->image = $platformStoreProductData->image;
                $platformStoreProduct->title = $platformStoreProductData->title;
                $platformStoreProduct->save();
                $this->logger->info("Platform Store Product Updated | ID: $platformStoreProduct->id");
            }
        }
        else {
            $platformStoreProduct = $platformStoreProductData;
            $platformStoreProduct->save();
            $this->logger->info("Platform Store Product Created | ID: $platformStoreProduct->id");
            $this->logger->debug("Data: " . $platformStoreProduct->toJson());
        }

        $platformStoreProduct->refresh();

        foreach ($product->variants as $variantIndex => $variant) {
            $platformStoreProductVariant = PlatformStoreProductVariant::where('platform_store_product_id', $platformStoreProduct->id)
                ->where(function ($q) use ($variant) {
                        return $q->where('platform_variant_id', $variant->id);
                })->first();

            $platformStoreProductVariantData = RutterVariantFormatter::formatForDb($variant);
            $platformStoreProductVariantData->is_ignored = $platformStoreProduct->is_ignored;

            if($platformStoreProductVariant){
                if ($platformStoreProductUpdated || (strtotime($platformStoreProductVariant->platform_updated_at) < strtotime($platformStoreProductVariantData->platform_updated_at))) {
                    $platformStoreProductVariant->image = $platformStoreProductVariantData->image;
                    $platformStoreProductVariant->title = $platformStoreProductVariantData->title;
                    $platformStoreProductVariant->sku = $platformStoreProductVariantData->sku;
                    $platformStoreProductVariant->price = $platformStoreProductVariantData->price;
                    $platformStoreProductVariant->save();
                    $this->logger->info("Platform Store Product Variant Updated | ID: $platformStoreProductVariant->id");
                }
            }
            else {
                    $variantBySku = null;
                    if($variant->sku != '') {
                        $variantBySku = $platformStoreProduct->variants()
                            ->where('sku', $variant->sku)
                            ->with('productVariantMapping')
                            ->first();
                    }

                    $platformStoreProductVariant = $platformStoreProduct->variants()->save($platformStoreProductVariantData);
                    $this->logger->info("Platform Store Product Variant Created | ID: $platformStoreProductVariant->id");
                    $this->logger->debug("Data: " . $platformStoreProductVariant->toJson());

                    if($variantBySku && isset($variantBySku->productVariantMapping)) {
                        $this->logger->info("Mapping by SKU due to new Listing Product ID");
                        $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping();
                        $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;
                        $this->logger->info("Variant by SKU: ".json_encode($variantBySku));
                        $platformStoreProductVariantMapping->product_variant_id = $variantBySku->productVariantMapping->product_variant_id;
                        $platformStoreProductVariantMapping->save();
                        $this->logger->info("Platform Store Product Variant Mapping Created | Data: " . $platformStoreProductVariantMapping->toJson());

                        if($variantBySku->delete()) {
                            $this->logger->info("Deleted old Product Variant id {$variantBySku->id}");
                        }
                    }
                    else {
                        $this->logger->info("No existing mapping found for SKU {$variant->sku}, new product will remain unmapped");
                    }
                }
        }
    }
}
