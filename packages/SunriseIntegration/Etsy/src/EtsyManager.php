<?php

namespace SunriseIntegration\Etsy;

use App\Formatters\Etsy\ProductFormatter as EtsyProductFormatter;
use App\Formatters\Etsy\VariantFormatter as EtsyVariantFormatter;
use App\Jobs\ProcessOrderLineItemImage;
use App\Models\Accounts\Account;
use App\Models\Platforms\PlatformStoreProductVariantMapping;
use App\Models\Shipments\ShipmentStatus;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OAuthException;

use App\Models\Orders\Order;
use SunriseIntegration\Etsy\API;
use App\Platform\PlatformManager;
use App\Models\Orders\OrderStatus;

use App\Formatters\Etsy\OrderFormatter;
use SunriseIntegration\Etsy\Models\Listing;
use SunriseIntegration\Etsy\Models\ListingImage;
use SunriseIntegration\Etsy\Models\ListingInventory;
use SunriseIntegration\Etsy\Models\ListingProduct;
use SunriseIntegration\Etsy\Models\Receipt;
use App\Formatters\Etsy\OrderLineItemFormatter;
use App\Models\Orders\OrderLog;
use SunriseIntegration\Etsy\Models\Transaction;
use SunriseIntegration\TeelaunchModels\Models\Products\Product;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountSettings;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProduct;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProductVariant;
use SunriseIntegration\TeelaunchModels\Models\Products\ProductVariant;
use SunriseIntegration\Etsy\Helpers\EtsyHelper;
use App\Traits\EmailNotification;

class EtsyManager extends PlatformManager
{
    use EmailNotification;
    /**
     * @var API
     */
    protected $api;
    protected $holdOrders;

    public function loadApi()
    {
        try {
            $this->api = new API(config('etsy.api_key'), config('etsy.api_secret'), $this->platformStore->apiToken, $this->platformStore->apiSecret, $this->logger);
        } catch (OAuthException $e) {
            $this->logger->error($e);
        }

        return $this->api;
    }

    public function importProducts($arguments = [])
    {
        if (!$this->platformStore || $this->platformStore->deleted_at) {
            $this->logger->info("Skipping {$this->platformStore->name}");
            return;
        }

        $limit = config('app.env') === 'local' ? 10 : 100;
        $offset = 0;

        $params = [];

        do {
            $response = $this->api->getAllShopListingsActive($limit, $offset, $includes = ['MainImage'], $params);

            $offset = $this->api->pagination->getNextOffset();

            if ($this->api->lastHttpCode !== 200) {
                //Failed
                $this->logger->error("Response: " . json_encode($response));
                return;
            }

            $this->logger->debug("Response: " . json_encode($response));

            $this->platformStore->settings()->updateOrCreate([
                'key' => 'products_last_imported_at'
            ], [
                'value' => Carbon::now()
            ]);

            $products = $response->results;
            $this->logger->info("Received " . count($products) . " Products");

            //Iterate over each product
            foreach ($products as $product) {
                $listing = new Listing($product);
                $this->logger->subheader("Process Listing ID {$listing->getListingId()}");
                $this->logger->debug("Listing: " . $listing->toJson());

                $platformStoreProduct = PlatformStoreProduct::withoutGlobalScopes(['default'])->where([['platform_store_id',$this->platformStore->id],['platform_product_id', (string)$listing->getListingId()]])->first();
                if($platformStoreProduct){
                    //Skip product to preserve limited amount of calls, this prevents us from being able to update products in DB unfortunately
                    continue;
                }

                $response = $this->api->getListing($listing->getListingId());

                if ($this->api->lastHttpCode !== 200) {
                    //Failed
                    $this->logger->error("Product Failed");
                    continue;
                }

                $listingData = $response->results;
                $listing = new Listing($listingData[0]);

                $this->processProduct($listing);
            }

        } while ($offset);
    }

    /**
     * @param Listing $listing
     * @throws Exception
     */
    public function processProduct($listing)
    {
        $variationImages = $listing->getImages();

        $this->logger->info("Variation Images: " . json_encode($variationImages));

        $inventories = $listing->getInventory() ?? [];

        $platformStoreProduct = PlatformStoreProduct::withoutGlobalScopes(['default'])->with('variants')->where([['platform_store_id',$this->platformStore->id],['platform_product_id', (string)$listing->getListingId()]])->first();
        $platformStoreProductData = EtsyProductFormatter::formatForDb($listing, $this->platformStore);

        if ($platformStoreProduct && ($platformStoreProduct->platform_updated_at < $platformStoreProductData->platform_updated_at)) {
            $platformStoreProduct->image = $platformStoreProductData->image;
            $platformStoreProduct->title = $platformStoreProductData->title;
            $platformStoreProduct->save();
            $this->logger->info("Platform Store Product Image and Title Updated | ID: $platformStoreProduct->id");
        } else {
            $platformStoreProduct = $platformStoreProductData;
            $platformStoreProduct->save();
            $this->logger->info("Platform Store Product Created | ID: $platformStoreProduct->id");
            $this->logger->debug("Data: " . $platformStoreProduct->toJson());
        }
        $platformStoreProduct->refresh();

        foreach ($inventories as $inventory) {
            $listingInventory = new ListingInventory($inventory);

            $listingProducts = $listingInventory->getProducts();
            $this->logger->info("Listing has " . count($listingProducts) . " Variants");

            foreach ($listingProducts as $listingIndex => $listingProduct) {
                $listingProduct = new ListingProduct($listingProduct);

                $platformStoreProductVariant = PlatformStoreProductVariant::where('platform_store_product_id', $platformStoreProduct->id)
                    ->where(function ($q) use ($listingProduct) {
                        return $q->where('platform_variant_id', (string)$listingProduct->getProductId());
                    })->first();

                $platformStoreProductVariantData = EtsyVariantFormatter::formatForDb($listingProduct);
                $platformStoreProductVariantData->is_ignored = $platformStoreProduct->is_ignored;

                if ($platformStoreProductVariant && ($platformStoreProductVariant->platform_updated_at < $platformStoreProductVariantData->platform_updated_at)) {
                    if (isset($variationImages[$listingIndex])) {
                        $platformStoreProductVariant->image = $variationImages[$listingIndex]->url_75x75;
                    }
                    $platformStoreProductVariant->title = $platformStoreProductVariantData->title;
                    $platformStoreProductVariant->sku = $platformStoreProductVariantData->sku;
                    $platformStoreProductVariant->price = $platformStoreProductVariantData->price;
                    $platformStoreProductVariant->save();
                    $this->logger->info("Platform Store Product Variant Updated | ID: $platformStoreProductVariant->id");
                } else {
                    $variantBySku = null;
                    if($listingProduct->getSku() != '') {
                        $variantBySku = $platformStoreProduct->variants()->where('sku', $listingProduct->getSku())->with('productVariantMapping')->first();
                    }

//                    if (isset($variationImages[$listingIndex])) {
//                    //    $platformStoreProductVariant->image = $variationImages[$listingIndex]->url_75x75;
//                    }

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
                    } else {
                        $this->logger->info("No existing mapping found for SKU {$listingProduct->getSku()}, new product will remain unmapped");
                    }
                }
            }
        }
    }

    /**
     * Imports orders from Etsy
     * When calling the command send option --min_updated_at="x" where x is a PHP time string, to import older orders
     * @param array $arguments
     */
    public function importOrders($arguments = [])
    {
        if (!$this->platformStore || $this->platformStore->deleted_at) {
            $this->logger->info("Skipping {$this->platformStore->name}");
            return;
        }

        $accountSetting = AccountSettings::where([['account_id', $this->platformStore->account_id],['key','order_hold']])->first();
        $this->holdOrders = $accountSetting ? boolval($accountSetting->value) : false;

        $limit = config('app.env') === 'local' ? 1 : 100;
        $offset = 0;

        $minLastModified = $arguments['min_updated_at'] ?? '-1 day';

        $params = [
            'was_shipped' => 'false',
            'was_paid' => 'true',
            'min_last_modified' => strtotime($minLastModified),
            'max_last_modified' => strtotime('now')
        ];

        if (config('app.env') === 'local') {
            $params = [
                'was_paid' => 'true',
                'min_last_modified' => strtotime($minLastModified),
                'max_last_modified' => strtotime('now')
            ];
        }

        $this->platformStore->settings()->updateOrCreate([
            'key' => 'orders_last_imported_at'
        ], [
            'value' => Carbon::now()
        ]);

        do {
            $response = $this->api->getAllShopReceipts($limit, $offset, $includes = ['Transactions', 'Transactions/MainImage'], $params);

            $this->logger->info("Get Orders | Limit: $limit | Offset: $offset | HTTP {$this->api->lastHttpCode}");

            $offset = $this->api->pagination->getNextOffset();

            if ($this->api->lastHttpCode !== 200) {
                //Failed
                $this->logger->error(json_encode($response));
                return;
            }

            $orders = $response->results;
            $this->logger->info("Received " . count($orders) . " Orders");

            //Iterate over each order
            foreach ($orders as $order) {
                $this->processOrder($order);
            }

        } while ($offset);
    }

    public function processOrder($order)
    {
        $receipt = new Receipt($order);

        $this->logger->subheader("Process Receipt ID {$receipt->getReceiptId()}");

        $existingOrder = Order::withoutGlobalScopes(['logs','lineItems','storePlatform','shipping','payments','blankVariant','art'])->where([['platform_store_id', $this->platformStore->id], ['platform_order_id', (string)$receipt->getReceiptId()]])->withTrashed()->first();
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

        $transactions = $receipt->getTransactions();
        $this->logger->debug('Receipt: ' . json_encode($receipt));


        if (count($transactions) == 0) {
            $this->logger->error("Skipping Order, no associated Transaction");
            return;
        }

        if ($receipt->getWasShipped() && config('app.env') !== 'local') {
            $this->logger->info("Skipping Order, already shipped");
            return;
        }

        if($this->holdOrders === null){
            $accountSetting = AccountSettings::where([['account_id', $this->platformStore->account_id],['key','order_hold']])->first();
            $this->holdOrders = $accountSetting ? boolval($accountSetting->value) : false;
        }

        $formattedOrder = OrderFormatter::formatForDb($receipt, $this->platformStore);
        $formattedOrder->status = $this->holdOrders === true ? OrderStatus::HOLD : OrderStatus::PENDING;

        // Get Not Teelaunch Orders Ignore Flag & check if valid to create order if flag is on
        $ignoredLineItemsCount = 0;
        $orderLineItemsCount = count($transactions);
        $ignoreNotTeeLaunchOrders = AccountSettings::where('account_id', '=', $this->accountId)->where('key', '=', 'ignore_not_teelaunch_order')->first();
        if($ignoreNotTeeLaunchOrders && $ignoreNotTeeLaunchOrders->value){
            foreach ($transactions as $key => $transaction) {
                $ignoreLineItem = false;
                $transaction = new Transaction($transaction);
                $this->logger->subheader("Process Line Item ID {$transaction->getTransactionId()}");
                $listingProduct = new ListingProduct($transaction->getProductData());
                $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where('platform_product_id', (string)$listingProduct->getProductId())->first();
                if($platformStoreProduct){
                    $platformStoreProductVariant = $platformStoreProduct->variants()->where('platform_store_product_id', $platformStoreProduct->id)->where('platform_variant_id', (string)$transaction->getVariantId())->with('productVariantMapping')->first();
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
                } else {
                    // product not in system, ignore
                    $ignoreLineItem = true;
                }
                if($ignoreLineItem){
                    $this->logger->info("Removing ignored line item {$transaction->getTransactionId()} from order data");
                    unset($transactions[$key]);
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

        $isProductOrderHold = false;

        //Array to store the out of stock variant
        $variantsOutOfStock = [];
        $variantsName = [];

        foreach ($transactions as $transaction) {
            $transaction = new Transaction($transaction);

            $this->logger->subheader("Process Transaction ID {$transaction->getTransactionId()}");

            //Save product to platform products table
            $listingProduct = new ListingProduct($transaction->getProductData());
            $this->logger->debug("Check for Platform Store Product with platform_product_id: " . $transaction->getListingId());
            $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where([
                ['platform_store_id',$this->platformStore->id],
                ['platform_product_id', (string)$transaction->getListingId()]
            ])->first();
            $this->logger->debug("Platform Store Product:" . json_encode($platformStoreProduct, JSON_PRETTY_PRINT));

            if (!$platformStoreProduct) {
                $this->logger->info("Importing order item into platform product table");

                //Import entire listing
                $response = $this->api->getListing($transaction->getListingId());
                if ($this->api->lastHttpCode === 200) {
                    $listingData = $response->results;
                    $listing = new Listing($listingData[0]);
                    $this->processProduct($listing);
                } else {
                    $this->logger->warning("Failed to get Product Listing");
                }

                $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where([['platform_store_id',$this->platformStore->id],['platform_product_id', (string)$transaction->getListingId()]])->first();
            }

            if ($platformStoreProduct) {
                $platformStoreProductVariant = $platformStoreProduct->variants()->where('platform_variant_id', (string)$listingProduct->getProductId())->first();

                if(!$platformStoreProductVariant){
                    // try with trashed if we didn't find anything
                    $platformStoreProductVariant = $platformStoreProduct->variants()->withTrashed()->where('platform_variant_id', (string)$listingProduct->getProductId())->first();
                    if ($platformStoreProductVariant && $platformStoreProductVariant->deleted_at) {
                        // variant was soft deleted for some reason. Restore it
                        $platformStoreProductVariant->restore();
                    }
                }

                $importLineItem = true;
                if (!$platformStoreProductVariant && $importLineItem) {
                    //Only import line item
                    $variantBySku = null;
                    if($listingProduct->getSku() != '') {
                        $variantBySku = $platformStoreProduct->variants()->where('sku', $listingProduct->getSku())->with('productVariantMapping')->first();
                    }

                    $platformStoreProductVariantData = EtsyVariantFormatter::formatForDb($listingProduct);
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
                        $this->logger->info("No existing mapping found for SKU {$listingProduct->getSku()}, new product will remain unmapped");
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
                } else {
                    $this->logger->info("Unable to map product {$listingProduct->getProductId()}. Will remain unmapped");
                }

                if($platformStoreProductVariant){
                    //Check if at least one of the products has order_hold flag set to true
                    if($formattedOrder->status !== OrderStatus::HOLD && $platformStoreProductVariant->shouldApplyOrderHold()){
                        $formattedOrder->status = OrderStatus::HOLD;
                        $formattedOrder->save();
                    }
                } else {
                    $this->logger->error("Unable to make platformStoreProductVariant");
                    $formattedOrder->status = OrderStatus::HOLD;
                    $formattedOrder->save();
                }
            }

            //Break quantities into separate line items
            $lineItemQuantity = $transaction->getQuantity();
            $lineItemId = $transaction->getTransactionId();
            for ($quantity = 0; $quantity < $lineItemQuantity; $quantity++) {
                //Format data

                $transaction->setTransactionId($lineItemId);
                $transaction->setQuantity(1);
                $formattedLineItem = OrderLineItemFormatter::formatForDb($transaction, $this->platformStore);
                $mainImage = new ListingImage($transaction->getMainimage());

                $formattedLineItem = $formattedOrder->lineItems()->save($formattedLineItem);

                if ($mainImage) {
                    $imageUrl = $mainImage->url_75x75 ?? $mainImage->getUrlFullxfull();
                    if (config('app.env') === 'local') {
                        ProcessOrderLineItemImage::dispatch($formattedLineItem, $imageUrl);
                    } else {
                        ProcessOrderLineItemImage::dispatch($formattedLineItem, $imageUrl)->onQueue('order-line-items-image');
                    }
                }

                $this->logger->info("Order Line Item ID $formattedLineItem->id Saved to DB | SKU: $formattedLineItem->sku | Qty: $formattedLineItem->quantity");
            }
        }

        //if the array not empty, so at lease one variant out of stock, the the order go on OUT_OF_STOCK
        if (!empty($variantsOutOfStock)) {
            $this->logger->info('Variant is out of stock, setting status to HOLD');
            $formattedOrder->status = OrderStatus::OUT_OF_STOCK;
            $formattedOrder->save();
            $this->sendOutOfStockEmail($account->user->email, $formattedOrder, $variantsName);
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

            try {
                $response = $this->api->submitTracking($order->platform_order_id, $shipment->tracking_number, EtsyHelper::cleanCarrier($shipment->carrier));
            } catch (Exception $e) {
                $this->logger->error($e);
            }

            if ($this->api->lastHttpCode == 200) {
                $shipment->status = ShipmentStatus::FULFILLED;
                $shipment->save();

                $order->logs()->create([
                    'message' => "Tracking $shipment->tracking_number sent to Etsy",
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);

                $this->logger->debug("Response: " . json_encode($response->results[0]));
                $this->logger->info("Success");
            } else {
                $this->logger->error("Failed | Response: " . json_encode($response));

                if (stripos($response, 'Shipping notification email has already been sent for this receipt') !== false) {
                    $shipment->status = ShipmentStatus::FULFILLED;
                    $order->logs()->create([
                        'message' => "Etsy Order already Fulfilled",
                        'message_type' => OrderLog::MESSAGE_TYPE_WARNING
                    ]);
                } else {
                    $shipment->status = ShipmentStatus::PENDING;
                    $order->logs()->create([
                        'message' => "Failed to send tracking #$shipment->tracking_number to Etsy",
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
}
