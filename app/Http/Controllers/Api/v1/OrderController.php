<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Orders\OrderCollectionResource;
use App\Http\Resources\Orders\OrderCollectionListResource;
use App\Http\Resources\Orders\OrderResource;
use App\Http\Resources\Orders\OrderViewResource;
use App\Jobs\DeleteOrderLineItem;
use App\Jobs\ProcessOrderLineItemImage;
use App\Models\Accounts\Account;
use App\Models\Blanks\Blank;
use App\Models\Blanks\CountryGroupBlankShippingVariant;
use App\Models\Orders\Address;
use App\Models\Orders\Order;
use App\Models\Orders\OrderLineItem;
use App\Models\Orders\OrderLineItemArtFile;
use App\Models\Orders\OrderLog;
use App\Models\Orders\OrderStatus;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformStore;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SunriseIntegration\Teelaunch\TeelaunchManager;
use App\Models\Platforms\PlatformStoreProductVariantMapping;
use App\Models\Platforms\PlatformStoreProductVariant;
use App\Models\Platforms\PlatformStoreProduct;
use App\Models\Products\ProductArtFile;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountSettings;

/**
 * @group  Orders
 *
 * APIs for managing account orders
 */
class OrderController extends Controller
{

    /**
     * Get Orders
     *
     * Get account orders
     * @queryParam status required The status id you are looking for, insert 'all' to retrieve all statuses
     */
    public function index(Request $request)
    {
        $limit = (int)config('pagination.per_page');

        $statusParam = $request->status == 'all' ? OrderStatus::all() : [$request->status];
        $viewParam = $request->view === 'deleted';

        $searchArray = Order::buildSearchArray($request);

        if ($viewParam) {
            $orders = Order::whereIn('status', $statusParam)
                ->where(function ($query) use ($searchArray) {
                    $query->search($searchArray);
                })
                ->withoutGlobalScopes(['art', 'logs'])
                ->onlyTrashed()
                ->orderBy('platform_created_at', 'desc')
                ->paginate($limit);
        } else {
            $orders = Order::whereIn('status', $statusParam)
                ->where(function ($query) use ($searchArray) {
                    $query->search($searchArray);
                })
                ->withoutGlobalScopes(['art', 'logs'])
                ->orderBy('platform_created_at', 'desc')
                ->paginate($limit);

        }

        foreach ($orders as $order) {
            if ($order->store) {
                $order->store->platformType = $order->store->platformType ?? null;
            }
        }

        return OrderCollectionListResource::collection($orders);
    }


    public function store(Request $request)
    {
        $products = collect(json_decode($request->platform_data)->products);

        // Prepare products array
        $formattedProductsData = [];
        foreach ($products as $product) {
            if (!isset($formattedProductsData[$product->product_id])) {
                $formattedProductsData[$product->product_id] = [];
            }

            if (isset($formattedProductsData[$product->product_id])) {
                $formattedProductsData[$product->product_id]['variants'][] = $product;
            }
        }
        $products = $formattedProductsData;

        // Validate Request
        $this->validate($request, [
            'email' => ['email'],
            'name' => ['string', 'required'],
            'address1' => ['string', 'required'],
            'address2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'platform_data' => ['json'],
            'zip' => ['required', 'string'],
            'country' => ['required', 'string'],
            'total' => ['required', 'numeric', 'min:0']
        ]);

        // Find or create a platform
        $teelaunchPlatform = Platform::firstOrCreate(
            ['name' => 'teelaunch'],
            [
                'name' => 'teelaunch',
                'manager_class' => TeelaunchManager::class,
                'logo' => '/favicon.ico',
                'enabled' => true
            ]);

        if (empty($teelaunchPlatform->logo)) {
            $teelaunchPlatform->update(['logo' => '/favicon.ico']);
        }

        // create or attach a platform store
        $platform_store = $teelaunchPlatform->stores()->where('account_id', Auth::user()->account_id)->first();

        if (!$platform_store) {
            $platform_store = $teelaunchPlatform->stores()->create(['name' => $request->platformStoreName, 'account_id' => Auth::user()->account_id]);
        }

        if (!$platform_store) {
            return response()->json(['message' => 'Could not create platform store'], 500);
        }

        // get account order hold flag
        $accountSetting = AccountSettings::where([['account_id', Auth::user()->account_id], ['key', 'order_hold']])->first();
        $holdOrders = $accountSetting ? boolval($accountSetting->value) : false;

        // Create Address
        $address = Address::create([
            'first_name' => explode(',', $request->name)[0],
            'last_name' => explode(',', $request->name)[1] ?? '',
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'state' => $request->state,
            'phone' => $request->phone,
            'zip' => $request->zip,
            'country' => $request->country
        ]);

        // Create Platform Store Fake Products
        foreach ($products as $key => $variants) {
            $platformStoreProduct = new PlatformStoreProduct;
            $platformStoreProduct->platform_store_id = $platform_store->id;
            $platformStoreProduct->platform_product_id = strtotime(Carbon::now()) . rand(0, 10000);
            $platformStoreProduct->image = '';
            $platformStoreProduct->link = '';
            $platformStoreProduct->title = $product->title;
            $platformStoreProduct->data = '';
            $platformStoreProduct->save();

            // Create Platform Store Fake Product Variants
            $variants = array_flatten($variants);
            foreach ($variants as $variant) {
                $platformStoreProductVariant = new PlatformStoreProductVariant;
                $platformStoreProductVariant->platform_store_product_id = $platformStoreProduct->id;
                $platformStoreProductVariant->platform_variant_id = $platformStoreProduct->platform_product_id . rand(0, 10000);
                $platformStoreProductVariant->image = '';
                $platformStoreProductVariant->link = '';
                $platformStoreProductVariant->data = '';
                $platformStoreProductVariant->sku = $variant->sku;
                $platformStoreProductVariant->title = $variant->title;
                $platformStoreProductVariant->price = $variant->price / $variant->quantity;
                $platformStoreProductVariant->save();

                // Create Platform Store Fake Product Variant Mapping With Teelaunch Variant
                $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping;
                $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;
                $platformStoreProductVariantMapping->product_variant_id = $variant->variant_id;
                $platformStoreProductVariantMapping->save();

                //Add platform related data to variants
                $variant->platform_line_item_id = $platformStoreProductVariant->id;
                $variant->platform_product_id = $platformStoreProduct->platform_product_id;;
                $variant->platform_variant_id = $platformStoreProductVariant->platform_variant_id;
            }
        }

        // Create Order
        $orderNumber = strtotime(Carbon::now()) . rand(0, 10000);
        $order = Order::create([
            'status' => $holdOrders === true ? OrderStatus::HOLD : OrderStatus::PENDING,
            'platform_order_number' => $orderNumber,
            'platform_order_id' => $orderNumber,
            'email' => $request->email,
            'total' => $request->total,
            'platform_data' => $request->platform_data,
            'shipping_address_id' => $address->id,
            'billing_address_id' => $address->id,
            'platform_created_at' => now(),
            'platform_updated_at' => now(),
            'platform_store_id' => $platform_store->id,
            'account_id' => $platform_store->account_id
        ]);

        $lineItems = [];
        // Create Order Line Items
        foreach ($products as $key => $variants) {
            $variants = array_flatten($variants);
            foreach ($variants as $variant) {
                for ($q = 0; $q < $variant->quantity; $q++) {
                    $lineItem = OrderLineItem::create([
                        'order_id' => $order->id,
                        'platform_line_item_id' => $variant->platform_line_item_id,
                        'platform_product_id' => $variant->platform_product_id,
                        'platform_variant_id' => $variant->platform_variant_id,
                        'title' => $variant->title,
                        'quantity' => 1,
                        'sku' => $variant->sku,
                        'price' => $variant->price / $variant->quantity,
                        'product_variant_id' => $variant->variant_id,
                        'account_id' => $platform_store->account_id,
                        'properties' => !empty($variant->properties) ? json_encode($variant->properties) : null
                    ]);

                    if ($variant->image_url) {
                        if (config('app.env') === 'local') {
                            ProcessOrderLineItemImage::dispatch($lineItem, $variant->image_url);
                        } else {
                            ProcessOrderLineItemImage::dispatch($lineItem, $variant->image_url)->onQueue('order-line-items-image');
                        }
                    }

                    $lineItems[] = $lineItem;
                }
            }
        };

        if (!$address || !$order || !$products) {
            $this->rollbackOrder(
                $order || null,
                $address || null,
                $lineItems || null
            );
            return response()->json(['message' => 'Something went wrong while trying to create your order'], 500);
        }

        $order->logs()->create([
            'message' => 'Order created into teelaunch',
            'message_type' => OrderLog::MESSAGE_TYPE_INFO
        ]);

        return response()->json('data stored successfully!');
    }

    public function getOrderCost(Request $request)
    {

        $variantShippingRatesCache = [];
        $orderVendors = [];
        $vendorShippingPrices = [];
        $country = $request->countryCode;
        $lineItems = $request->selectedProducts;
        $orderDiscountTotal = 0.00;
        $orderLineItemSubtotal = 0.00;
        $orderShippingTotal = 0.00;
        $orderTaxTotal = 0.00;

        $account = Account::where('id', Auth::user()->account_id)->first();

        $lineItems = json_decode(json_encode($lineItems), FALSE);

        foreach ($lineItems as $lineItem) {
            $blankVariant = $lineItem->blankVariant;
            $blank = Blank::findOrFail($blankVariant->blankId);
            $vendor = $blank->vendor;
            $vendorName = $vendor->name ?? null;

            //Shipping - Cache results for quicker recall
            $shippingKey = "$vendorName#$country#$blank->id";

            if (isset($variantShippingRatesCache[$shippingKey])) {
                $variantShipping = $variantShippingRatesCache[$shippingKey];
            } else {
                $variantShipping = CountryGroupBlankShippingVariant::variantCountryShipping($blankVariant->id, $country);
                $variantShippingRatesCache[$shippingKey] = $variantShipping;
            }

            //Assign Vendor Shipping Rates, using the highest rate
            if (count($vendorShippingPrices) > 0 && isset($vendorShippingPrices[$vendorName])) {
                $existingVariantShipping = $vendorShippingPrices[$vendorName];
                //Use more expensive option
                if ($variantShipping->ship_price > $existingVariantShipping->ship_price) {
                    $vendorShippingPrices[$shippingKey] = $variantShipping;
                }
            } else {
                $variantShipping = CountryGroupBlankShippingVariant::variantCountryShipping($blankVariant->id, $country);
                $vendorShippingPrices[$vendorName] = $variantShipping;
            }
        }

        foreach ($lineItems as $lineItem) {
            $blankVariant = $lineItem->blankVariant;
            $blank = Blank::findOrFail($blankVariant->blankId);
            $vendor = $blank->vendor;
            $vendorName = $vendor->name;
            $variantShipping = $vendorShippingPrices[$vendorName];

            $basePrice = $blankVariant->price;

            //Surcharge is already sent from the orders view within the unitcost
//        //Surcharges for additional printing
//        $surcharge = $this->getSurcharge($category, $printFiles);
            $unitCost = $basePrice;

            $subtotal = $unitCost * max(0, $lineItem->quantity);

            // Check if blank has a discount related to the account
            $accountBlankDiscount = $account->blankDiscounts->where('blank_id', $blankVariant->blankId)->first();
            $discountPercent = $accountBlankDiscount->percent ?? 0;
            $discount = $discountPercent > 0 ? $subtotal * ($discountPercent / 100) : 0.00;

            $shippingCharge = $this->getShippingCharge($lineItem, $variantShipping, $vendorName, $orderVendors);

            $tax = 0.00;
            if ($variantShipping && $blank->is_tax_enabled && $variantShipping->tax > 0) {
                $tax = ($shippingCharge + $subtotal) * ($variantShipping->tax / 100);
            }

            $orderLineItemSubtotal += $subtotal;
            $orderShippingTotal += $shippingCharge;
            $orderDiscountTotal += $discount;
            $orderTaxTotal += $tax;

        }

        $orderTotalCost = $orderLineItemSubtotal + $orderShippingTotal + $orderTaxTotal - $orderDiscountTotal;

        return response()->json([
            'totalCost' => $orderTotalCost,
            'shippingTotal' => $orderShippingTotal,
            'discountTotal' => $orderDiscountTotal,
            'subTotal' => $orderLineItemSubtotal,
            'tax' => $orderTaxTotal
        ]);
    }

    public function rollbackOrder($order, $address, $lineItems)
    {
        // this will attempt to completely roll back manual order
        try {
            if ($order) {
                $order->delete();
                $order->logs()->create([
                    'message' => 'Order was deleted',
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);
            }

        } catch (Exception $e) {
            LOG::error($e);
        }
        // TODO should we also delete address here?

        if ($lineItems && count($lineItems) > 0) {
            foreach ($lineItems as $lineItem) {
                try {
                    $lineItem->delete();
                } catch (Exception $e) {
                    LOG::error($e);
                }
            }
        }
    }

    /**
     * Get Order By Id
     *
     * Get account order by id
     *
     * @urlParam id required Order id
     */
    public function show(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return $this->responseNotFound();
        }

        if ($order->store) {
            $order->store->platform->platformType = $order->store->platformType ?? null;
        }

        $previousOrder = Order::select('id')->where('id', '>', $id)->min('id');
        $order->previousOrderId = $previousOrder ?? null;

        $nextOrder = Order::select('id')->where('id', '<', $id)->max('id');
        $order->nextOrderId = $nextOrder ?? null;

//        return new OrderViewResource($order);
        return new OrderResource($order);

    }

    /**
     * Cancel Order
     *
     * Cancel account order
     *
     * @urlParam id required Order id
     */
    public function cancel(Request $request, $id)
    {
        $ids = explode(',', $id);

        $orders = Order::whereIn('id', $ids)->where(function ($q) {
            return $q->where('status', OrderStatus::HOLD)->orWhere('status', OrderStatus::PENDING)->orWhere('status', OrderStatus::OUT_OF_STOCK);
        })->get();

        foreach ($orders as $order) {
            $order->cancel();
        }

        return new OrderCollectionResource($orders);
    }

    public function restore(Request $request, $id)
    {
        $ids = explode(',', $id);
        try {
            $orders = Order::whereIn('id', $ids)->onlyTrashed()->get();
            foreach ($orders as $order) {
                $order->restore();
                $order->logs()->create([
                    'message' => 'Order was restored',
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while trying to restore this order'
            ]);
        }
    }

    /**
     * Release Order
     *
     * Release account order
     *
     * @urlParam id required Order id
     */
    public function release(Request $request, $id)
    {
        $ids = explode(',', $id);

        $orders = Order::whereIn('id', $ids)->whereIn('status', [OrderStatus::HOLD, OrderStatus::OUT_OF_STOCK])->get();

        foreach ($orders as $order) {
            $order->release();
        }

        return new OrderCollectionResource($orders);
    }

    /**
     * Hold Order
     *
     * Hold account order
     *
     * @urlParam id required Order id
     */
    public function hold(Request $request, $id)
    {
        $ids = explode(',', $id);

        $orders = Order::whereIn('id', $ids)->where('status', OrderStatus::PENDING)->get();

        foreach ($orders as $order) {
            $order->hold();
        }

        return new OrderCollectionResource($orders);
    }

    /**
     * Clear Order Error
     *
     * Clear account order error
     *
     * @urlParam id required Comma separated order ids
     */
    public function clearError(Request $request, $id)
    {
        $ids = explode(',', $id);

        $orders = Order::whereIn('id', $ids)->where('has_error', true)->get();

        foreach ($orders as $order) {
            $order->clearError();
        }

        return new OrderCollectionResource($orders);
    }

    /**
     * Delete Order
     *
     * Delete account order
     *
     * @urlParam id required Order id
     */
    public function destroy(Request $request, $id)
    {
        $ids = explode(',', $id);

        $orders = Order::whereIn('id', $ids)->get();

        foreach ($orders as $order) {
            $order->delete();
            $order->logs()->create([
                'message' => 'Order was deleted',
                'message_type' => OrderLog::MESSAGE_TYPE_INFO
            ]);

            if (config('app.env') === 'local') {
                DeleteOrderLineItem::dispatch($order);
            } else {
                DeleteOrderLineItem::dispatch($order)->onQueue('deletes');
            }
        }

        return $this->responseOk();
    }

    public function mapLineItemVariant(Request $request, $orderId, $lineItemId)
    {
        $request->validate([
                'productVariantId' => 'required|integer'
            ]
        );

        $order = Order::where('id', $orderId)->firstOrFail();

        $lineItem = $order->lineItems()->where('id', $lineItemId)->firstOrFail();
        $lineItem->product_variant_id = $request->get('productVariantId');
        $lineItem->save();

        $order->has_error = false;
        $order->save();

        return new OrderResource($lineItem);
    }

    public function deleteLineItems($orderId, Request $request)
    {
        try {
            $orderLineItems = OrderLineItem::where('order_id', $orderId)->whereIn('id', $request->only('ids'))->get();

            $deletedArr = $orderLineItems->map(function ($orderLineItem) {
                return $orderLineItem->price * $orderLineItem->quantity;
            });

            $deletedTotal = $deletedArr->reduce(function ($total, $curr) {
                return $total + $curr;
            }, 0);

            $order = Order::find($orderId);
            $order->update([
                'total' => $order->total - $deletedTotal
            ]);

            foreach ($orderLineItems as $orderLineItem) {
                OrderLog::create([
                    'order_id' => $orderId,
                    'message' => "$orderLineItem->title is removed",
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);

                $orderLineItem->delete();
            }

            return $this->responseOk();

        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while trying to remove line items from this order'
            ]);
        }
    }

    public function updateOrderLineItem($orderId, Request $request)
    {
        try {
            // Find order line items
            $lineItemsIds = array_map(function ($item) {
                return $item['id'];
            }, $request->lineItems);

            $logs = [];

            $orderLineItems = OrderLineItem::where('order_id', $orderId)->whereIn('id', $lineItemsIds)->get();

            // Update order lineitems
            $orderLineItems->each(function ($lineItem) use ($request, $orderId, &$logs) {

                // Get the related line item
                $relatedQuantityLineItem = array_flatten(array_filter($request->lineItems, function ($item) use ($lineItem) {
                    // Get line item that has a different quantity
                    return $item['id'] === $lineItem->id && $item['quantity'] !== $lineItem->quantity;
                }));
                $quantity = $relatedQuantityLineItem[1] ?? null;

                // Get the related line item
                $relatedPropertiesLineItem = array_flatten(array_filter($request->lineItems, function ($item) use ($lineItem) {
                    return $item['id'] === $lineItem->id && $item['properties'] !== $lineItem->properties;
                }));
                $properties = $relatedPropertiesLineItem[3] ?? null;

                // Only update the line item with a different quantity
                if ($quantity) {
                    $lineItem->update(['quantity' => $quantity]);
                    $logs[] = OrderLog::create([
                        'order_id' => $orderId,
                        'message' => "$lineItem->title quantity is updated to $quantity",
                        'message_type' => OrderLog::MESSAGE_TYPE_INFO
                    ]);

                    $updatedPrice = array_reduce($request->lineItems, function ($acc, $item) {
                        return $acc + ($item['quantity'] * $item['price']);
                    }, 0);

                    Order::find($orderId)->update(['total' => $updatedPrice]);
                }

                // Only update the line item with a different properties
                if ($properties) {
                    $lineItem->update(['properties' => $properties]);
                    $logs[] = OrderLog::create([
                        'order_id' => $orderId,
                        'message' => "$lineItem->title personalization is updated successfully",
                        'message_type' => OrderLog::MESSAGE_TYPE_INFO
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Order line item updated successfully',
                    'logs' => $logs
                ]);
            });

        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while trying to update your order line items'
            ]);
        }
    }

    public function getShippingCharge($lineItem, $variantShipping, $vendor, &$orderVendors)
    {
        //Charge ship_price on first of each vendor in order, then ship_price_discounted on subsequent
        if (!isset($orderVendors[$vendor])) {
            $firstShipPrice = $variantShipping->ship_price;
            $additionalShipPrice = $variantShipping->ship_price_discounted * max(0, $lineItem->quantity - 1);
            $totalPrice = $firstShipPrice + $additionalShipPrice;
            $internationalShipPrice = $totalPrice * max(0, $lineItem->quantity - 1) * ($variantShipping->international_rate / 100);
            $totalPrice = $totalPrice + $internationalShipPrice;

            // Calculate temporary ship price if enabled
            if ($variantShipping && $variantShipping->temporary_ship_charge && $variantShipping->ship_price_temporary > 0) {
                $totalPrice += $variantShipping->ship_price_temporary;
            }

            $orderVendors[$vendor] = $variantShipping;
        } else {
            $firstShipPrice = $variantShipping->ship_price;
            $additionalShipPrice = $variantShipping->ship_price_discounted * max(0, $lineItem->quantity);
            $totalPrice = $additionalShipPrice;
            $internationalShipPrice = ($totalPrice + $firstShipPrice) * max(0, $lineItem->quantity) * ($variantShipping->international_rate / 100);
            $totalPrice = $totalPrice + $internationalShipPrice;
        }

        return $totalPrice;
    }

    public function getSurcharge($category, $printFiles)
    {
        $surcharge = 0;
        $isApparel = strtolower($category->name) == 'apparel';
        if ($isApparel && count($printFiles) > 1) {
            $surcharge = 5;
        }
        return $surcharge;
    }

    public function sync(Request $request){
        header('Access-Control-Allow-Origin: '.env('ADMIN_APP_URL'));
        try {
            Artisan::call("platforms:import-orders --account_id={$request->a} --min_updated_at='-3 days' --force");
            return $this->responseOk(['success'=> true]);
        }catch (Exception $e)
        {
            return $this->responseServerError([$e->getMessage()]);
        }

    }

}

