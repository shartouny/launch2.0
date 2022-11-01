<?php

namespace SunriseIntegration\Shopify;

use App\Formatters\Shopify\ProductFormatter as ShopifyProductFormatter;
use App\Formatters\Shopify\VariantFormatter;
use App\Jobs\ProcessOrderLineItemImage;
use App\Models\Accounts\Account;
use App\Models\Platforms\PlatformStoreProductVariantMapping;
use App\Models\Shipments\ShipmentStatus;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreSettings;
use App\Platform\PlatformManager;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\Orders\OrderStatus;
use App\Formatters\Shopify\OrderFormatter;
use App\Formatters\Shopify\AddressFormatter;
use SunriseIntegration\Shopify\Helpers\ShopifyHelper;
use SunriseIntegration\Shopify\Models\Product;
use SunriseIntegration\Shopify\Models\Product\Variant;
use SunriseIntegration\Shopify\Models\FulfillmentService;
use SunriseIntegration\Shopify\Models\Order as ShopifyOrder;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProduct;
use OAuthException;
use App\Models\Orders\Order;
use App\Models\Orders\OrderLineItem;
use App\Models\Orders\OrderLog;
use SunriseIntegration\Shopify\Models\Order\Transaction;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountSettings;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProductVariant;
use App\Formatters\Shopify\OrderLineItemFormatter;
use Exception;
use SunriseIntegration\TeelaunchModels\Models\Products\ProductVariant;
use App\Traits\EmailNotification;

class ShopifyManager extends PlatformManager
{
    use EmailNotification;
    /**
     * @var API
     */
    protected $api;
    protected $holdOrders;

    public $platformName = 'Shopify';

    function loadApi()
    {
        try {
            $shop = $this->platformStore;

            $shopUrl = $this->platformStore->url ?? $shop->name;

            $shopify = new API([
                'key' => config('shopify.api_key'),
                'secret' => config('shopify.api_secret'),
                'shop' => $shopUrl
            ], $this->logger);

            if (!isset($shop) || $shop->api_token == null) {
                $this->logger->info("Shopify not installed");

            }

            $shopify->setAccessToken($shop->api_token);

        } catch (OAuthException $e) {
            $this->logger->error($e);
        }

        $this->api = $shopify;

        return $this->api;


    }

    function importOrders($arguments = [])
    {
        if (!$this->platformStore || $this->platformStore->deleted_at) {
            $this->logger->info("Skipping {$this->platformStore->name}");
            return;
        }

        $accountSetting = AccountSettings::where([['account_id', $this->platformStore->account_id], ['key', 'order_hold']])->first();
        $this->holdOrders = $accountSetting ? boolval($accountSetting->value) : false;

        $limit = config('app.env') === 'local' ? 10 : 100;
        $pageInfo = null;
        $retry = 0;

        $minLastModified = $arguments['min_updated_at'] ?? '-1 day';

        $params = [
            // 'was_shipped' => 'false',
            // 'was_paid' => 'true',
            'updated_at_min' => date("c", strtotime($minLastModified)), // 2008-12-31 03:00
            'updated_at_max' => date("c", strtotime('now'))
        ];

        if (config('app.env') === 'local') {
            $params = [
                // 'was_paid' => 'true',
                'updated_at_min' => date("c", strtotime($minLastModified)),
                'updated_at_max' => date("c", strtotime('now'))
            ];
        }

        do {
            // try to get the request 3 times
            do {
                $response = $this->api->getOrders($limit, $pageInfo, $params);
                $retry++;
                if(isset($response->statusCode) && $response->statusCode == 429) {
                    // throttled
                    $this->logger->info("Throttled. Sleeping for 1 sec.");
                    sleep(1);
                }
            } while ($retry < 3 && $this->api->lastHttpCode !== 200);

            if ($this->api->lastHttpCode !== 200) {
                //Failed after 3 attempts
                $this->logger->error("Failed call GetProducts");
                $this->logger->error("Response: " . json_encode($response));
                return;
            }

            $this->logger->info("Get Orders | Limit: $limit | Page: $pageInfo | HTTP {$this->api->lastHttpCode}");

            $pageInfo = $this->api->getPagination();

            $orders = $response->orders;
            $this->logger->info("Received " . count($orders) . " Orders");

            //Iterate over each order
            foreach ($orders as $order) {
                $this->processOrder($order);
            }
        } while ($pageInfo);

        $this->platformStore->settings()->updateOrCreate([
            'key' => 'orders_last_imported_at'
        ], [
            'value' => Carbon::now()
        ]);
    }

    function getLineItemThumbnail($lineItem, $thumbnailSize = 75)
    {
        try {
            $variantId = $lineItem->getVariantId();
            $productImages = $this->api->getProductImages($lineItem->getProductId());
            // keep a fallback of position 1 image incase we don't find one with the associated variant id
            $fallbackImageUrl = null;
            $imageUrl = null;
            $lastVariantImagePosition = 99999; // a high number to reduce
            // the goal is to find the first position with the line item variant id;
            $this->logger->debug("getLineItemThumbnail: " . json_encode($productImages));
            foreach($productImages->images as $productImage) {
                if ($productImage->position == 1){
                    // keep this one as the fallback
                    $fallbackImageUrl = $productImage->src;
                }
                // see if this image is associated with our variant
                foreach($productImage->variant_ids as $variantId) {
                    if($variantId == $lineItem->getVariantId() && $productImage->position < $lastVariantImagePosition){
                        // this image is for our variant and is a lower position (higher priority) than our saved one, use it
                        $lastVariantImagePosition = $productImage->position;
                        $imageUrl = $productImage->src;
                        // $this->logger->debug("getLineItemThumbnail: " . "Variant ID matches ". $variantId);
                    }
                }
            }

            if($fallbackImageUrl == null && $imageUrl == null){
              // we didn't find an image
              return null;
            }

            if($imageUrl == null) {
              // use the fallback url
              $imageUrl = $fallbackImageUrl;
            }

            // convert url link to a thumbnail by adding _{width}x{height} before extension.
            // TODO I remember a way to request a real thumbnail with url, but cannot find it anywhere, I believe this will just resize and not crop
            $urlArr = explode( '.', $imageUrl );
            $targetIndex = count($urlArr) - 2;
            $urlArr[$targetIndex] = $urlArr[$targetIndex] . "_" . $thumbnailSize . "x" . $thumbnailSize;

            $imageUrl = implode('.', $urlArr);
            // $this->logger->debug("getLineItemThumbnail Image url: " . $imageUrl);
            return $imageUrl;
        } catch (Exception $e) {
            // something went wrong
            $this->logger->error("getLineItemThumbnail: " . $e->getMessage());
            return null;
        }
    }

    function ensureProductIsOnPlatform($shopifyLineItem)
    {
        //Save product to platform products table
        $this->logger->debug("Check for Platform Store Product with platform_product_id: " . $shopifyLineItem->getProductId());
        $platformStoreProduct = PlatformStoreProduct::with('variants')->withoutGlobalScopes(['default'])->where([
            ['platform_store_id',$this->platformStore->id],
            ['platform_product_id', (string) $shopifyLineItem->getProductId()]
        ])->withTrashed()->first();

        $this->logger->debug("Platform Store Product:" . json_encode($platformStoreProduct, JSON_PRETTY_PRINT));

        if (!$platformStoreProduct) {
            $this->logger->info("Importing order item into platform product table");

            //Import product
            $response = $this->api->getProduct($shopifyLineItem->getProductId());
            if ($this->api->lastHttpCode === 200) {
                $product = $response->product;
                $productModel = new Product(null, $product);
                $this->processProduct($productModel);
            } else {
                $this->logger->warning("Failed to get Product Listing");
            }

            $platformStoreProduct = PlatformStoreProduct::with('variants')->withoutGlobalScopes(['default'])->where([['platform_store_id',$this->platformStore->id],['platform_product_id', (string) $shopifyLineItem->getProductId()]])->first();
        }

        if ($platformStoreProduct) {
            $platformStoreProductVariant = $platformStoreProduct->variants()->where('platform_variant_id', (string)$shopifyLineItem->getVariantId())->first();

            // TODO why to we set true here and check if it is true immediately after?
            $importLineItem = true;
            if (!$platformStoreProductVariant && $importLineItem) {
                // we do not have mapping for product variant in our system
                $this->logger->debug("Importing line item variant: " . $shopifyLineItem->getVariantId());
                //Only import line item
                $variantBySku = null;
                if($shopifyLineItem->getSku() != '') {
                    $variantBySku = $platformStoreProduct->variants()->where('sku', $shopifyLineItem->getSku())->with('productVariantMapping')->first();
                }
                $options = [
                    'platform_store_product_id' => $platformStoreProduct->id,
                    // 'product_images' => $productImages
                ];

                if($variantBySku && isset($variantBySku->productVariantMapping)) {
                    $platformStoreProductVariantData = VariantFormatter::formatForDb($variantBySku, $this->platformStore, $options);
                    $platformStoreProductVariant = $platformStoreProduct->variants()->save($platformStoreProductVariantData);

                    $this->logger->info("Mapping by SKU due to new Listing Product ID");
                    $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping();
                    $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;
                    $platformStoreProductVariantMapping->product_variant_id = $variantBySku->productVariantMapping->product_variant_id;
                    $platformStoreProductVariantMapping->save();
                    $this->logger->info("Platform Store Product Variant Mapping Created | Data: " . $platformStoreProductVariantMapping->toJson());

                    if($variantBySku->delete()) {
                        $this->logger->info("Deleted old Product Variant id {$variantBySku->id}");
                    }
                    $this->logger->info("Platform Store Product Variant Created | ID: $platformStoreProductVariant->id");
                } else {
                    $this->logger->info("No existing mapping found for SKU {$shopifyLineItem->getSku()}, new product will remain unmapped");
                }
            }
        } else {
            // Product is not in our system and cannot be retrieved from Shopify
            $this->logger->warning("Importing line item variant failed: ". json_encode($platformStoreProduct));
        }
    }

    function processOrder($orderObj)
    {
        $order = new ShopifyOrder(null, $orderObj);

        $this->logger->subheader("Process Order ID {$order->getId()}");



        $existingOrder = Order::
        withoutGlobalScopes(['logs','lineItems','storePlatform','shipping','payments','blankVariant','art'])->
        where([['platform_store_id', $this->platformStore->id], ['platform_order_id', (string)$order->getId()]])
            ->withTrashed()->first('id');

        if($existingOrder) {
            $message = "Order already imported | Order ID: $existingOrder->id";
            if($existingOrder->trashed()){
                $message .= " | Order Deleted";
            }

            $this->logger->info($message);
            return;
        }

        $account = Account::find($this->accountId);

        // $this->logger->debug("Data: " . json_encode($order));

        $lineItems = $order->getLineItems();

        if (count($lineItems) == 0) {
            $this->logger->error("Skipping Order, no associated LineItems");
            return;
        }

        // TODO check if was shipped
        // if ($order->getWasShipped() && config('app.env') !== 'local') {
        //     $this->logger->info("Skipping Order, already shipped");
        //     return;
        // }

        if($this->holdOrders === null){
            $accountSetting = AccountSettings::where([['account_id', $this->platformStore->account_id],['key','order_hold']])->first();
            $this->holdOrders = $accountSetting ? boolval($accountSetting->value) : false;
        }

        $formattedOrder = OrderFormatter::formatForDb($order, $this->platformStore);
        $formattedOrder->status = $this->holdOrders === true ? OrderStatus::HOLD : OrderStatus::PENDING;

        // set status to platform payment hold if shopify payment is pending
        if($order->getFinancialStatus() === 'pending'){
            $formattedOrder->status = OrderStatus::PLATFORM_PAYMENT_HOLD;
        }

        // get addresses
        $formattedShippingAddress = AddressFormatter::formatForDb($order->getShippingAddress(), $this->platformStore);
        $formattedBillingAddress = AddressFormatter::formatForDb($order->getBillingAddress(), $this->platformStore);

        // save only shipping for now
        $formattedShippingAddress->save();

        // Save both, easier for tracking changes to order
        $formattedBillingAddress->save();

        // add addresses to order
        $formattedOrder->shipping_address_id = $formattedShippingAddress->id;
        $formattedOrder->billing_address_id = $formattedBillingAddress->id;

        // Get Not Teelaunch Orders Ignore Flag & check if valid to create order if flag is on
        $ignoredLineItemsCount = 0;
        $orderLineItemsCount = count($lineItems);
        $ignoreNotTeeLaunchOrders = AccountSettings::where('account_id', '=', $this->accountId)->where('key', '=', 'ignore_not_teelaunch_order')->first();
        if($ignoreNotTeeLaunchOrders && $ignoreNotTeeLaunchOrders->value){
            foreach ($lineItems as $key => $lineItem) {
                $ignoreLineItem = false;
                $this->logger->subheader("Process Line Item ID {$lineItem->getId()}");
                $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where('platform_product_id', (string) $lineItem->getProductId())->first();
                 if($platformStoreProduct){
                    $platformStoreProductVariant = $platformStoreProduct->variants()->where('platform_store_product_id', $platformStoreProduct->id)->where('platform_variant_id', (string)$lineItem->getVariantId())->with('productVariantMapping')->first();
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
                    $this->logger->info("Removing ignored line item {$lineItem->getId()} from order data");
                    unset($lineItems[$key]);
                    $ignoredLineItemsCount++;
                }
            }

            if($ignoredLineItemsCount == $orderLineItemsCount){
                $this->logger->info("Skipping Order Creation, all order line items are marked as not teelaunch");
                return;
            }
        }

        // save the formatted order
        $formattedOrder->save();

        $formattedOrder->logs()->create([
            'message' => 'Order imported into teelaunch',
            'message_type' => OrderLog::MESSAGE_TYPE_INFO
        ]);

        $this->logger->info("Order ID $formattedOrder->platform_order_id Saved to DB | Platform Order ID: $formattedOrder->platform_order_id");

        $this->logger->debug("Line Items: " . json_encode($lineItems));

        //Array to store the out of stock variant
        $variantsOutOfStock = [];
        $variantsName = [];

        foreach ($lineItems as $lineItem){

            $this->logger->subheader("Process Line Item ID {$lineItem->getId()}");

            $this->ensureProductIsOnPlatform($lineItem);

            $platformStoreProduct = PlatformStoreProduct::with(['variants','variants.productVariant'])->withoutGlobalScopes(['default'])->where('platform_product_id', (string) $lineItem->getProductId())->first();

             if($platformStoreProduct){
                $platformStoreProductVariant = $platformStoreProduct->variants()->where('platform_store_product_id', $platformStoreProduct->id)->where('platform_variant_id', (string)$lineItem->getVariantId())->with('productVariantMapping')->first();

                if($platformStoreProductVariant){
                    //Set Order Hold based on ProductVariant Order Hold
                    if($formattedOrder->status !== OrderStatus::HOLD && $order->getFinancialStatus() !== 'pending'){
                        if($platformStoreProductVariant->shouldApplyOrderHold()){
                            $formattedOrder->status = OrderStatus::HOLD;
                            $formattedOrder->save();
                        }
                    }
                }

                if($platformStoreProductVariant && $platformStoreProductVariant->productVariant && $platformStoreProductVariant->productVariant->blankVariant) {
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
                    $this->logger->info("Unable to map product {$lineItem->getProductId()}. Will remain unmapped");
                }
            } else {
                $this->logger->info("Unable to import product {$lineItem->getProductId()}.");
            }

            //Break quantities into separate line items
            $lineItemQuantity = $lineItem->getQuantity();
            $lineItemId = $lineItem->getId();
            for ($quantity = 0; $quantity < $lineItemQuantity; $quantity++){
                //Format data

                $lineItem->setId($lineItemId);
                $lineItem->setQuantity(1);
                $formattedLineItem = OrderLineItemFormatter::formatForDb($lineItem, $this->platformStore);
                $formattedLineItem = $formattedOrder->lineItems()->save($formattedLineItem);

                $lineItemThumbnail = $this->getLineItemThumbnail($lineItem);
                if ($lineItemThumbnail) {
                    // determine extension
                    $lineItemThumbnailArr = explode('.', $lineItemThumbnail);
                    // Shopify urls have query strings sometimes, remove them
                    $ext = explode("?", $lineItemThumbnailArr[count($lineItemThumbnailArr) - 1])[0];
                    if (config('app.env') === 'local') {
                        ProcessOrderLineItemImage::dispatch($formattedLineItem, $lineItemThumbnail);
                    } else {
                        ProcessOrderLineItemImage::dispatch($formattedLineItem, $lineItemThumbnail)->onQueue('order-line-items-image');
                    }
                }

                $this->logger->info("Order Line Item ID $formattedLineItem->id Saved to DB | SKU: $formattedLineItem->sku | Qty: $formattedLineItem->quantity");
            }
        }

        //if the array not empty, so at lease one variant out of stock, the the order go on hold
        if (!empty($variantsOutOfStock)) {
            $this->logger->info('Variant is out of stock, setting status to HOLD');
            $formattedOrder->status = OrderStatus::OUT_OF_STOCK;
            $formattedOrder->save();
            $this->sendOutOfStockEmail($account->user->email, $formattedOrder, $variantsName);
        }
    }
    /**
     * Returns the fulfillment location id from the database if it has been registered with the store.
     * If it has not been registered, it registers our fulfillment service with the store,
     * saves it
     *
     * @return string
     */
    function getFulfillmentServiceLocationId(): ?string
    {
        $this->logger->debug("getFulfillmentServiceLocationId called");
        $fulfillmentServiceQuery = PlatformStoreSettings::where([['platform_store_id', $this->platformStore->id], ['key', 'fulfillment_service_location_id']])->first();
        if ($fulfillmentServiceQuery) {
            // we have already registered a fulfillment service, return FulfillmentService
            return $fulfillmentServiceQuery->value;
        } else {
            // we need to register a fulfillment service with the store
            ShopifyHelper::setup_fulfillment_service($this->platformStore, $this->logger);
            $fulfillmentServiceQuery = PlatformStoreSettings::where([['platform_store_id', $this->platformStore->id], ['key', 'fulfillment_service_location_id']])->first();
            if ($fulfillmentServiceQuery) {
                return $fulfillmentServiceQuery->value;
            } else {
                return null;
            }
        }
    }

    /**
     * Returns a boolean to determine if the lastHttpCode from the API is a success.
     * Shopify returns 200 (ok) for some and 201 (created) for others
     *
     * @return boolean
     */
    function isRequestOk ()
    {
        return $this->api->lastHttpCode == 201 || $this->api->lastHttpCode == 200;
    }

    /**
     * Fulfills pending shipments on a line item basis on Shopify
     *
     * @param Order $order
     * @return void
     */

    function fulfillOrder(Order $order): void
    {
        $this->logger->header("Fulfill Order ID {$order->id}");
        if (!isset($order->platform_order_id)) {
            $this->logger->error('Order ID not found on Order Object');
            return;
        }
        $order->status = OrderStatus::PROCESSING_FULFILLMENT;
        $order->save();

        $pendingShipments = $order->shipments()->where('status', ShipmentStatus::PENDING)->get();
        $this->logger->subheader("Pending Shipment Count: " . count($pendingShipments));
        $this->logger->debug('pendingShipments: ' . json_encode($pendingShipments));

        // get or create the fulfillment service id
        $locationId = $this->getFulfillmentServiceLocationId();

        // no need to process if we do not have pending shipments, should never happen
        if (count($pendingShipments) == 0) {
            return;
        }

        // process all pending shipments
        $lineItemsShipped = [];
        foreach ($pendingShipments as $shipment) {
            // create line_items for fulfillment
            $shopifyLineItemsInShipmentArray = [];

            foreach ($shipment->orderLineItems as $orderLineItem) {
                // since we split multiple quantities, we only need to fulfill one of the quantities base line items.
                if(!in_array($orderLineItem->platform_line_item_id, $lineItemsShipped)) {
                    $shopifyLineItemsInShipmentArray[] = [
                        "id" => $orderLineItem->platform_line_item_id
                    ];
                    $lineItemsShipped[] = $orderLineItem->platform_line_item_id;
                }
            }

            $this->logger->debug("Shopify line items: " . json_encode($shopifyLineItemsInShipmentArray));
            if (count($shopifyLineItemsInShipmentArray) == 0) {
                // Mark as fulfilled so it counts towards order desk splits, but no need to send to shopify
                // This should be rare, or never happen
                $this->logger->debug("No line items associated with shipment: " . $shipment->id);
                $shipment->status = ShipmentStatus::FULFILLED;
                $shipment->save();
                continue;
            }

            $shipment->status = ShipmentStatus::PROCESSING;
            $shipment->save();
            $fulfillment = [
                "location_id" => $locationId,
                "tracking_company" => $shipment->carrier,
                "tracking_urls" => [$shipment->tracking_url],
                "tracking_numbers" => [$shipment->tracking_number],
                "line_items" => $shopifyLineItemsInShipmentArray,
                "notify_customer" => true
            ];

            $this->logger->debug('$fulfillment: ' . json_encode($fulfillment));

            try {
                $retry = 0;
                do {
                    $response = $this->api->submitFulfillment(
                        $order->platform_order_id,
                        $fulfillment
                    );
                    $retry++;
                    sleep(1);
                } while ($retry < 3 && !$this->isRequestOk());

                if (!$this->isRequestOk()) {
                    if ($this->api->lastHttpCode == 422) {
                        // shopify has marked order as fulfilled
                        $shipment->status = ShipmentStatus::FULFILLED;
                        $shipment->save();
                        continue;
                    } else {
                        // revert back to pending to try again later
                        // TODO do we have any method to retry if another shipment doesn't trigger this method?
                        $this->logger->error("Error submitting tracking to Shopify: " . $this->api->lastHttpCode . " Response: " . json_encode($response));
                        $shipment->status = ShipmentStatus::PENDING;
                        $shipment->save();
                        continue;
                    }
                }

                // mark order as fulfilled on shopify
                $retry = 0;
                do {
                    $this->api->updateFulfillmentStatus(
                        $order->platform_order_id,
                        $response->fulfillment->id,
                        "complete"
                    );
                    $retry++;
                    sleep(1);
                } while ($retry < 3 && !$this->isRequestOk());

                if (!$this->isRequestOk()) {
                    // todo what do we do if this fails?
                    $this->logger->error("Error updating fulfillment status: " . $this->api->lastHttpCode . " Response: " . json_encode($response));
                    continue;
                }
                $shipment->status = ShipmentStatus::FULFILLED;
                $shipment->save();

                $this->logger->info("Line items marked fulfilled on Shopify: " . json_encode($shopifyLineItemsInShipmentArray));

                $order->logs()->create([
                    'message' => "Tracking information sent to Shopify",
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);

            } catch (Exception $e) {
                $this->logger->error($e);
            }
        }

        $fulfilledShipments = $order->shipments()->where('status', ShipmentStatus::FULFILLED)->get();

        // this may fail in rare case multiple shipments in single split
        // we may need a way to track not just splits but shipments per split as well
        if ($order->order_desk_split <= count($fulfilledShipments)) {
            // order is fulfilled
            $order->status = OrderStatus::FULFILLED;
            $order->save();
            $order->logs()->create([
                'message' => "Order Fulfilled",
                'message_type' => OrderLog::MESSAGE_TYPE_INFO
            ]);
            $this->logger->info("Order fulfilled. Shipments " . count($fulfilledShipments) . " of " . $order->order_desk_split);
            // order automatically fulfills on shopify once all line items are fulfilled
        } else {
            // order is not completely fulfilled
            $this->logger->info("Fulfilled shipments " . count($fulfilledShipments) . " of " . $order->order_desk_split);
        }
        return;
    }

    /**
     * @param array $arguments
     * @return mixed
     */
    function importProducts($arguments = [])
    {

        if (!$this->platformStore || $this->platformStore->deleted_at) {
            $this->logger->info("Skipping {$this->platformStore->name}");
            return;
        }

        $productIds = [];
        $params = [];
        $retry = 0;
        $limit = config('app.env') === 'local' ? 2 : 100;
        $pageInfo = null;
        do {
            $retry = 0;
            do {
                $response = $this->api->getProducts($productIds, $params, $limit, $pageInfo);
                $retry++;
                sleep(1);
            } while ($retry < 3 && $this->api->lastHttpCode !== 200);

            if ($this->api->lastHttpCode !== 200) {
                //Failed after 3 attempts
                $this->logger->error("Failed call GetProducts");
                $this->logger->error("Response: " . json_encode($response));
                return;
            }

            $pageInfo = $this->api->getPagination();
            $this->platformStore->settings()->updateOrCreate([
                'key' => 'products_last_imported_at'
            ], [
                'value' => Carbon::now()
            ]);


            $products = $response->products ?? [];

            foreach ($products as $product) {

                $productModel = new Product(null, $product);
                $this->processProduct($productModel);

            }


        } while ($pageInfo);

    }

    function processProduct($product)
    {

        $platformStoreProduct = PlatformStoreProduct::withoutGlobalScopes()->where([['platform_store_id', $this->platformStore->id], ['platform_product_id', (string) $product->getId()]])->first();
        $platformStoreProductData = ShopifyProductFormatter::formatForDb($product, $this->platformStore);

        if ($platformStoreProduct && ($platformStoreProduct->platform_updated_at < $platformStoreProductData->platform_updated_at)) {
            $platformStoreProduct->image = $platformStoreProductData->image;
            $platformStoreProduct->title = $platformStoreProductData->title;
            $platformStoreProduct->data = $platformStoreProductData->data;
            $platformStoreProduct->link = $platformStoreProductData->link;
            $platformStoreProduct->save();
            $this->logger->info("Platform Store Product Image and Title Updated | ID: $platformStoreProduct->id");
        }
        else {
            try {
                $platformStoreProduct = $platformStoreProductData;
                $platformStoreProduct->save();
                $this->logger->info("Platform Store Product Created | ID: $platformStoreProduct->id");
            } catch (\Exception   $e) {
                $this->logger->info('PDO exception. Probably soft Deleted. Fixing.');
                $platformStoreProduct = PlatformStoreProduct::withoutGlobalScopes()->where([['platform_store_id', $this->platformStore->id], ['platform_product_id', (string) $product->getId()]])->withTrashed()->first();
                if($platformStoreProduct) {
                    $platformStoreProduct->restore();
                    $platformStoreProduct->image = $platformStoreProductData->image;
                    $platformStoreProduct->title = $platformStoreProductData->title;
                    $platformStoreProduct->data = $platformStoreProductData->data;
                    $platformStoreProduct->link = $platformStoreProductData->link;
                    $platformStoreProduct->save();
                    $this->logger->info("Platform Store Product Image and Title Updated | ID: $platformStoreProduct->id");

                } else {
                    $this->logger->info('Could not recover deleted product. Skipping.');
                }
            }
        }

        $variants = $product->getVariants() ?? [];

        foreach ($variants as $variantIndex => $variant) {
            $variant = new Variant(null, $variant);

            $platformStoreProductVariant = PlatformStoreProductVariant::where('platform_store_product_id', $platformStoreProduct->id)->where(function ($q) use ($variant) {
                    return $q->where('platform_variant_id', (string)$variant->getId());
                })->first();

            $options = [
                'platform_store_product_id' => $platformStoreProduct->id,
                'product' => $product
            ];

            $platformStoreProductVariantData = VariantFormatter::formatForDb($variant, $this->platformStore, $options);
            $platformStoreProductVariantData->is_ignored = $platformStoreProduct->is_ignored;
            if(is_null($platformStoreProductVariantData->is_ignored)){
                $platformStoreProductVariantData->is_ignored = 0;
            }

            if ($platformStoreProductVariant && ($platformStoreProductVariant->platform_updated_at < $platformStoreProductVariantData->platform_updated_at)) {
                $platformStoreProductVariant->title = $platformStoreProductVariantData->title;
                $platformStoreProductVariant->sku = $platformStoreProductVariantData->sku;
                $platformStoreProductVariant->price = $platformStoreProductVariantData->price;
                $platformStoreProductVariant->data = $platformStoreProductVariantData->data;
                $platformStoreProductVariant->link = $platformStoreProductVariantData->link;
                $platformStoreProductVariant->image = $platformStoreProductVariantData->image;
                $platformStoreProductVariant->save();
                $this->logger->info("Platform Store Product Variant Updated | ID: $platformStoreProductVariant->id");
            } else {
                try {
                    $platformStoreProductVariant = $platformStoreProductVariantData;
                    $platformStoreProductVariant->save();
                    $this->logger->info("Platform Store Product Variant Created | ID: $platformStoreProductVariant->id");
                } catch (\Exception   $e) {
                    $platformStoreProductVariant = PlatformStoreProductVariant::where('platform_store_product_id', $platformStoreProduct->id)->where(function ($q) use ($variant) {
                        return $q->where('platform_variant_id', (string)$variant->getId());
                    })->withTrashed()->first();
                    if($platformStoreProductVariant) {
                        $platformStoreProductVariant->title = $platformStoreProductVariantData->title;
                        $platformStoreProductVariant->sku = $platformStoreProductVariantData->sku;
                        $platformStoreProductVariant->price = $platformStoreProductVariantData->price;
                        $platformStoreProductVariant->data = $platformStoreProductVariantData->data;
                        $platformStoreProductVariant->link = $platformStoreProductVariantData->link;
                        $platformStoreProductVariant->image = $platformStoreProductVariantData->image;
                        $platformStoreProductVariant->save();
                        $this->logger->info("Platform Store Product Variant Updated | ID: $platformStoreProductVariant->id");
                    } else {
                        $this->logger->info('Could not import product variant');
                    }
                }
            }
        }
    }

}
