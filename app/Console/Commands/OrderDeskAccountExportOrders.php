<?php

namespace App\Console\Commands;

use App\Formatters\OrderDesk\OrderFormatter;
use App\Logger\ConnectorLoggerFactory;
use App\Logger\CustomLogger;
use App\Models\Accounts\Account;
use App\Models\Accounts\AccountPayment;
use App\Models\Accounts\AccountPaymentMethod;
use App\Models\Accounts\AccountPaymentMethod\ChargeDetails;
use App\Models\Accounts\AccountSettings;
use App\Models\Blanks\BlankCategory;
use App\Models\Blanks\CountryGroupBlankShipping;
use App\Models\Blanks\CountryGroupBlankShippingVariant;
use App\Models\Orders\Order;
use App\Models\Orders\OrderAccountPayment;
use App\Models\Orders\OrderLineItem;
use App\Models\Orders\OrderLog;
use App\Models\Orders\OrderStatus;

use App\Models\Products\ProductPrintFile;
use App\Models\Products\ProductVariantPrintFile;
use App\Notifications\SlackMessage;
use App\Notifications\SlackNotification;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use App\Logger\CronLoggerFactory;
use Illuminate\Mail\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use SunriseIntegration\OrderDesk\API;
use SunriseIntegration\Shopify\Models\Order\LineItem;
use SunriseIntegration\TeelaunchModels\Models\Orders\OrderAccountPaymentLineItem;
use App\Traits\EmailNotification;


class OrderDeskAccountExportOrders extends Command
{
    use EmailNotification;
    protected $resetOrderId = 100000000519;

    protected $account;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orderdesk:account-export-orders {accountId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs order export for OrderDesk';

    /**
     * @CustomLogger
     */
    protected $logger;

    protected $orderDesk;

    protected $skippedOrders = [];

    protected $logChannel = 'export-orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

//        $loggerFactory = new CronLoggerFactory('orderdesk_account_export_orders_cron');
//        $this->logger = $loggerFactory->getLogger();

    }

    /**
     * Execute the console command.
     * @throws Exception
     */
    public function handle()
    {
        //For some reason this is persisting
        $this->skippedOrders = [];

        $accountId = $this->argument('accountId');
        $this->account = $account = Account::find($accountId);

        $loggerFactory = new ConnectorLoggerFactory(
            $this->logChannel,
            $this->account->id,
            'orderdesk');

        $this->logger = $loggerFactory->getLogger();

        AccountSettings::updateOrCreate([
            'account_id' => $this->account->id,
            'key' => 'orders_last_exported'
        ], [
            'value' => Carbon::now()
        ]);

        $this->logger->title('Start Exporting Orders on '.config('app.name'). ' at '. Carbon::now());

        $this->orderDesk = new API(config('orderdesk.api_key'), config('orderdesk.store_id'), $this->logger);

        $this->resetStuckOrders(OrderStatus::PROCESSING_PAYMENT, OrderStatus::PENDING);
        $this->resetStuckOrders(OrderStatus::PROCESSING_PRODUCTION, OrderStatus::PAID);

        if (config('app.env') == 'local' && $this->resetOrderId) {
            $orderToReset = Order::find($this->resetOrderId);
            if ($orderToReset) {
                $orderToReset->status = OrderStatus::PENDING;
                $orderToReset->has_error = false;
                $orderToReset->save();
            }
        }

        try {
            $accountPayment = $this->calculateOrderCosts();
            if ($accountPayment) {
                $this->chargeActivePaymentMethod($accountPayment);
            }
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        try {
            $this->sendOrdersToOrderDesk();
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        try {
            $this->sendErrorEmail();
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        $this->logger->title('Finished Exporting Orders');

    }

    public function payUsingAccountCreditBalance($accountPayment){

        $accountPaymentMethod = AccountPaymentMethod::where([['account_id', $this->account->id], ['is_active', true]])->first();
        $accountCredit = AccountSettings::getAccountCredit();
        $this->logger->info('Account credit: '.$accountCredit);
        $this->logger->info('Account Payment: '. $accountPayment);

        if ($accountCredit && is_object($accountCredit) && isset($accountCredit->value) && $accountCredit->value > 0) {
            if ($accountCredit->value >= $accountPayment->amount) {
                $orderIds = $accountPayment->orderPayments->pluck('order_id');

                $this->logger->info('Deduct all payment from account credit: '. $accountPayment->amount);
                $orderPayments = $accountPayment->orderPayments;
                unset($accountPayment->orderPayments);

                $accountPayment->account_payment_method_id = $accountPaymentMethod->id;
                $accountPayment->transaction_id = 'ACCOUNT-CREDIT';
                $accountPayment->status = ChargeDetails::STATUS_SUCCESS;
                $accountPayment->save();

                foreach ($orderPayments as $orderPayment) {
                    $lineItems = $orderPayment->lineItems;
                    unset($orderPayment->lineItems);
                    $payment = $accountPayment->orderPayments()->save($orderPayment);

                    foreach ($lineItems as $lineItem) {
                        $payment->lineItems()->save($lineItem);
                    }
                }

                //Update account credit
                $accountCredit->value -= $accountPayment->amount;
                $accountCredit->save();

                //Set orders as Paid
                Order::whereIn('id', $orderIds)->update([
                    'status' => OrderStatus::PAID
                ]);

                //Create orders logs
                foreach ($orderIds as $orderId) {
                    OrderLog::create([
                        'order_id' => $orderId,
                        'message' => 'Order successfully charged',
                        'message_type' => OrderLog::MESSAGE_TYPE_INFO
                    ]);
                }
                $this->sendReceiptEmail($accountPayment);

                return null;
            }
            elseif ($accountCredit->value < $accountPayment->amount) {

                $accountCreditOrderPayments = new AccountPayment();
                $accountCreditOrderPayments->account_id         = $this->account->id;
                $accountCreditOrderPayments->discount_total     = 0;
                $accountCreditOrderPayments->subtotal           = 0;
                $accountCreditOrderPayments->shipping_total     = 0;
                $accountCreditOrderPayments->tax_total          = 0;
                $accountCreditOrderPayments->amount             = 0;
                $accountCreditOrderPayments->orderPayments      = collect([]);

                $accountOrderPayments = $accountPayment->orderPayments;
                $accountCreditValue = $accountCredit->value;
                foreach ($accountOrderPayments as $key => $orderPayment){
                    if($orderPayment->total_cost < $accountCreditValue) {
                        unset($accountOrderPayments[$key]);
                        $this->logger->info('Unsetting: ' . $orderPayment->order_id);

                        //Update account credit
                        $accountCredit->value -= $orderPayment->total_cost;
                        $accountCredit->save();
                        $this->logger->info('New Account Credit Value: ' . $accountCredit->value);

                        $accountPayment->discount_total     -= $orderPayment->discount;
                        $accountPayment->subtotal           -= $orderPayment->line_item_subtotal;
                        $accountPayment->shipping_total     -= $orderPayment->shipping_subtotal;
                        $accountPayment->tax_total          -= $orderPayment->tax;
                        $accountPayment->amount             -= $orderPayment->total_cost;
                        $accountPayment->orderPayments       = $accountOrderPayments;

                        $accountCreditOrderPayments->discount_total     += $orderPayment->discount;
                        $accountCreditOrderPayments->subtotal           += $orderPayment->line_item_subtotal;
                        $accountCreditOrderPayments->shipping_total     += $orderPayment->shipping_subtotal;
                        $accountCreditOrderPayments->tax_total          += $orderPayment->tax;
                        $accountCreditOrderPayments->amount             += $orderPayment->total_cost;
                        $accountCreditOrderPayments->orderPayments->push($orderPayment);

                        $accountCreditValue -= $orderPayment->total_cost;

                        $this->logger->info('Updated CARD Account Payment: ' . $accountPayment);
                        $this->logger->info('Updated ACCOUNTCREDIT Account Payment: ' . $accountCreditOrderPayments);
                    }
                }

                if($accountCreditOrderPayments->amount > 0){
                    $orderPayments = $accountCreditOrderPayments->orderPayments;
                    unset($accountCreditOrderPayments->orderPayments);
                    $accountCreditOrderPayments->account_payment_method_id = $accountPaymentMethod->id;
                    $accountCreditOrderPayments->transaction_id = 'ACCOUNT-CREDIT';
                    $accountCreditOrderPayments->status = ChargeDetails::STATUS_SUCCESS;
                    $accountCreditOrderPayments->save();

                    foreach ($orderPayments as $orderPayment) {
                        $orderIds[] = $orderPayment->order_id;

                        $lineItems = $orderPayment->lineItems;
                        unset($orderPayment->lineItems);
                        $payment = $accountCreditOrderPayments->orderPayments()->save($orderPayment);

                        foreach ($lineItems as $lineItem) {
                            $payment->lineItems()->save($lineItem);
                        }
                    }

                    //Set orders as Paid
                    Order::whereIn('id', $orderIds)->update([
                        'status' => OrderStatus::PAID
                    ]);

                    //Create orders logs
                    foreach ($orderIds as $orderId) {
                        OrderLog::create([
                            'order_id' => $orderId,
                            'message' => 'Order successfully charged',
                            'message_type' => OrderLog::MESSAGE_TYPE_INFO
                        ]);
                    }

//                $this->sendReceiptEmail($accountPayment);
                }
            }
        }

        return $accountPayment;
    }

    public function sendErrorEmail()
    {
        $this->logger->debug('sendErrorEmail');
        $this->logger->debug('skippedOrders: ' . json_encode($this->skippedOrders));
        if (count($this->skippedOrders) > 0) {
            $this->sendGeneralErrorEmail($this->account->user->email, $this->skippedOrders);
        }
    }

    public function orderHasError($order, $message, $orderStatus, $messageType = OrderLog::MESSAGE_TYPE_ERROR)
    {
        $this->logger->warning('Order Has Error | ' . $message);

        $this->skippedOrders[] = $order;

        $order->logs()->create([
            'message' => $message,
            'message_type' => $messageType
        ]);

        Order::where('id', $order->id)->update([
            'status' => $orderStatus,
            'has_error' => true
        ]);
    }

    /**
     * @param int $whereStatus
     * @param int $toStatus
     */
    function resetStuckOrders($whereStatus, $toStatus)
    {
        $fromStatusName = OrderStatus::getStatusName($whereStatus);
        $toStatusName = OrderStatus::getStatusName($toStatus);

        $updatedAt = config('app.env') !== 'local' ? Carbon::now()->subHours(3) : Carbon::now();

        $count = Order::where([
            ['account_id', $this->account->id],
            ['status', $whereStatus],
            ['updated_at', '<=', $updatedAt]])
            ->update(['status' => $toStatus]);

        $this->logger->info("Reset $count Orders from status $fromStatusName to $toStatusName where updated_at <= $updatedAt");
    }

    /**
     * @return AccountPayment|null
     */
    function calculateOrderCosts(): ?AccountPayment
    {
        $leftDailyMaxChargeValue = 0;

        //Get Daily Max Charge Account Setting
        $dailyMaxChargeEnabled = AccountSettings::where('account_id', $this->account->id)->where('key', 'daily_max_charge_enabled')->first();
        $dailyMaxChargeEnabled = $dailyMaxChargeEnabled->value ?? false;
        $dailyMaxChargeValue = AccountSettings::where('account_id', $this->account->id)->where('key', 'daily_max_charge')->first();
        $dailyMaxChargeValue = $dailyMaxChargeValue->value ?? null;

        //Check if billing is disabled
        if ($dailyMaxChargeEnabled && $dailyMaxChargeValue == 0) {
            $this->logger->debug("Skipping calculateOrderCosts, daily charge set to 0");
            return null;
        }

        //Check if processing is possible due to daily limit charge excess
        if ($dailyMaxChargeEnabled && $dailyMaxChargeValue > 0) {
            $totalAccountPayments = AccountPayment::where('account_id', $this->account->id)
                ->whereDate('created_at', Carbon::today())
                ->where('status', ChargeDetails::STATUS_SUCCESS)
                ->sum('amount');

            $leftDailyMaxChargeValue = $dailyMaxChargeValue - $totalAccountPayments;
            if ($leftDailyMaxChargeValue <= 0) {
                $this->logger->debug("Daily Charge Limit Reached | leftDailyMaxChargeValue: $leftDailyMaxChargeValue");
                return null;
            }
        }

        //Get all pending orders
        $orders = Order::where([['account_id', $this->account->id], ['has_error', false], ['status', OrderStatus::PENDING]])->orderBy('created_at','asc')->get();

        $this->logger->header("Calculate Order Costs for " . count($orders) . " Orders");

        if (count($orders) === 0) {
            $this->logger->info("No orders to calculate costs on");
            return null;
        }

        //Set next status
        Order::whereIn('id', $orders->pluck('id'))->update([
            'status' => OrderStatus::PROCESSING_PAYMENT
        ]);


        $this->logger->header("Calculate Order Costs for " . count($orders) . " Orders");

        $ordersToSkip = [];

        $variantShippingRatesCache = [];
        $orderAccountPayments = collect([]);
        foreach ($orders as $order) {
            $this->logger->subheader("Validate and Calculate Costs for Order ID $order->id");

            //Check for a successful payment made on this order
            $alreadyPaid = false;
            foreach ($order->payments as $payment) {
                if ($payment->accountPayment->status === 1) {
                    $alreadyPaid = true;
                    break;
                }
            }
            if ($alreadyPaid) {
                $this->logger->info("Order already paid for: " . json_encode($order->payments));
                $order->status = OrderStatus::PAID;
                $order->save();
                continue;
            }

            $mappedProductCount = 0;
            $orderVendors = [];
            $vendorShippingPrices = [];
            $formattedLineItems = [];

            $orderAccountPaymentLineItems = collect([]);

            $shippingAddress = $order->shippingAddress;
            if (!$shippingAddress) {
                //Cannot process without Shipping Address
                $this->orderHasError($order, 'Missing shipping address', OrderStatus::PENDING);
                $this->sendMissingCustomerInfoEmail($this->account->user->email, $order);
                continue;
            }

            $lineItems = $order->lineItems;

            if (!$this->validateLineItems($order, $lineItems)) {
                $this->logger->warning('Order failed validation');
                continue;
            }

            $this->logger->header('Calculate shipping rates');

            //Get shipping rates and tax %
            foreach ($lineItems as $lineItem) {
                $this->logger->subheader("Line Item $lineItem->id");

                if($lineItem->platformStoreProductVariant && $lineItem->platformStoreProductVariant->is_ignored){
                    $this->logger->info("PlatformStoreProductVariant is ignored");
                    continue;
                }

                $productVariant = $lineItem->productVariant ?? $lineItem->platformStoreProductVariant->productVariant;
                $blankVariant = $productVariant->blankVariant;
                $blank = $blankVariant->blank;
                $vendor = $blank->vendor;
                $vendorName = $vendor->name ?? null;
                $this->logger->debug("Vendor Name: $vendorName");
                $this->logger->debug("Shipping Country: " . $shippingAddress->country);

                //Shipping - Cache results for quicker recall
                //Shipping - Cache results for quicker recall
                $shippingKey = "$vendorName#$shippingAddress->country#$blank->id";
                $this->logger->debug("Shipping Key: " . $shippingKey);
                if (isset($variantShippingRatesCache[$shippingKey])) {
                    $variantShipping = $variantShippingRatesCache[$shippingKey];
                    $this->logger->debug("Variant Shipping from Cache: " . json_encode($variantShipping));
                } else {
                    $variantShipping = CountryGroupBlankShippingVariant::variantCountryShipping($blankVariant, $shippingAddress->country);
                    $variantShippingRatesCache[$shippingKey] = $variantShipping;
                    $this->logger->debug("Variant Shipping from DB: " . json_encode($variantShipping));
                }

                if (!$variantShipping) {
                    //Cannot process Order without shipping costs
                    $this->orderHasError($order, "Cannot calculate shipping costs for teelaunch Blank Variant SKU $blankVariant->sku", OrderStatus::PENDING);
                    $this->sendAdminEmail($order, "Cannot calculate shipping costs for teelaunch Blank Variant ID $blankVariant->id/ SKU $blankVariant->sku");
                    continue(2);
                }

                //Assign Vendor Shipping Rates, using the highest rate
                if (count($vendorShippingPrices) > 0 && isset($vendorShippingPrices[$vendorName])) {
                    $existingVariantShipping = $vendorShippingPrices[$vendorName];
                    //Use more expensive option
                    if ($variantShipping->ship_price > $existingVariantShipping->ship_price) {
                        $vendorShippingPrices[$shippingKey] = $variantShipping;
                    }
                } else {
                    $variantShipping = CountryGroupBlankShippingVariant::variantCountryShipping($blankVariant, $shippingAddress->country);
                    $vendorShippingPrices[$vendorName] = $variantShipping;
                }

            }
            $this->logger->debug("Vendor Shipping Prices: " . json_encode($vendorShippingPrices));

            $this->logger->header('Calculate OrderAccountPaymentLineItem');

            //Array to store the out of stock variant
            $variantsOutOfStock = [];
            $variantsName = [];

            //Check order line items availability (Out of stock - functionality)
            foreach ($lineItems as $lineItem) {
                $productVariant = $lineItem->productVariant ?? $lineItem->platformStoreProductVariant->productVariant; //TODO: Should we bake this check into the lineItem productVariant
                if($productVariant && $productVariant->blankVariant) {
                    $blankVariant = $productVariant->blankVariant;

                    //Check if variant is out of stock
                    if ($blankVariant->is_out_of_stock) {
                        $outOfStockMessage = [];
                        //Push the out of stock variant here
                        $variantsOutOfStock[] = $blankVariant->is_out_of_stock;

                        foreach ($blankVariant->optionValues as $variantOption) {
                            $outOfStockMessage[] = $variantOption->name;
                        }

                        $outOfStockMessage = implode(', ', $outOfStockMessage);
                        $variantsName[] = $outOfStockMessage;
                        OrderLog::create([
                            'order_id' => $order->id,
                            'message' => "Variant $outOfStockMessage is out of stock",
                            'message_type' => OrderLog::MESSAGE_TYPE_INFO
                        ]);
                    }
                }
            }

            //if the array not empty, so at lease one variant out of stock, the the order status is set to out of stock
            if (!empty($variantsOutOfStock)) {
                $this->logger->info('Variant is out of stock, setting status to OUT OF STOCK');
                $order->status = OrderStatus::OUT_OF_STOCK;
                $order->save();
                $this->sendOutOfStockEmail($this->account->user->email, $order, $variantsName);
                continue;
            }

            $orderTaxTotal = 0.00;
            $orderDiscountTotal = 0.00;
            $orderLineItemSubtotal = 0.00;
            $orderShippingTotal = 0.00;
            foreach ($lineItems as $lineItem) {
                $this->logger->subheader("Line Item $lineItem->id");

                if($lineItem->platformStoreProductVariant && $lineItem->platformStoreProductVariant->is_ignored){
                    $this->logger->info("PlatformStoreProductVariant is ignored");
                    continue;
                }

                // skip 0 quantity
                if($lineItem->quantity === 0){
                    $this->logger->info("Line item has 0 quantity");
                    continue;
                }

                $mappedProductCount++;
                $productVariant = $lineItem->productVariant ?? $lineItem->platformStoreProductVariant->productVariant; //TODO: Should we bake this check into the lineItem productVariant
                $printFiles = $productVariant->printFiles;
                $blankVariant = $productVariant->blankVariant;
                $blank = $blankVariant->blank;
                $category = $blank->category;
                $vendor = $blank->vendor;
                $vendorName = $vendor->name;
                $variantShipping = $vendorShippingPrices[$vendorName];

                $basePrice = $blankVariant->price;
                $this->logger->info("Base Prices: " . $basePrice);

                //Surcharges for additional printing
                $surcharge = $this->getSurcharge($category, $printFiles);
                $this->logger->info("Surcharge: " . $surcharge);

                $unitCost = $basePrice + $surcharge;
                $this->logger->info("Unit Cost: " . $unitCost);

                $subtotal = $unitCost * max(0, $lineItem->quantity);
                $this->logger->info("Subtotal: " . $subtotal);

                // Check if blank has a discount related to the account
                $accountBlankDiscount = $this->account->blankDiscounts->where('blank_id', $blankVariant->blank_id)->first();
                $discountPercent = $accountBlankDiscount->percent ?? 0;
                $discount = $discountPercent > 0 ? $subtotal * ($discountPercent / 100) : 0.00;
                $this->logger->info("Discount: " . $discount);

                $shippingCharge = $this->getShippingCharge($lineItem, $variantShipping, $vendorName, $orderVendors);
                $this->logger->info("Shipping Charge: " . $shippingCharge);

                //Calculate Tax on shipping and subtotals
                $tax = 0.00;
                if($variantShipping && $blank->is_tax_enabled && $variantShipping->tax > 0){
                    $this->logger->info("Tax: " . $variantShipping->tax);
                    $tax = ($shippingCharge + $subtotal) * ($variantShipping->tax / 100);
                }

                $orderAccountPaymentLineItem = new OrderAccountPaymentLineItem();
                $orderAccountPaymentLineItem->blank_variant_id = $blankVariant->id;
                $orderAccountPaymentLineItem->order_line_item_id = $lineItem->id;
                $orderAccountPaymentLineItem->product_variant_id = $productVariant->id;
                $orderAccountPaymentLineItem->quantity = $lineItem->quantity;
                $orderAccountPaymentLineItem->unit_cost = $unitCost;
                $orderAccountPaymentLineItem->discount = $discount;
                $orderAccountPaymentLineItem->subtotal = $subtotal;
                $orderAccountPaymentLineItem->shipping = $shippingCharge;
                $orderAccountPaymentLineItem->tax = $tax;
                $orderAccountPaymentLineItem->total = $subtotal + $shippingCharge + $tax - $discount;

                $orderAccountPaymentLineItems->push($orderAccountPaymentLineItem);

                //Store current mapped product variant in case it changes later
                if (!$lineItem->product_variant_id) {
                    $lineItem->product_variant_id = $productVariant->id;
                    $lineItem->save();
                }
            }

            if ($mappedProductCount === 0) {
                $this->logger->info('All line items on Order are ignored, setting status to IGNORED');
                $order->status = OrderStatus::IGNORED;
                $order->save();
                OrderLog::create([
                    'order_id' => $order->id,
                    'message' => 'All line items on Order are ignored',
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);
                continue;
            }

            foreach ($orderAccountPaymentLineItems as $orderLineItemCharge) {
                $orderLineItemSubtotal += $orderLineItemCharge->subtotal;
                $orderShippingTotal += $orderLineItemCharge->shipping;
                $orderDiscountTotal += $orderLineItemCharge->discount;
                $orderTaxTotal += $orderLineItemCharge->tax;
            }

            //Create an object to handle charge data, this should be made into a Model
            $orderTotalCost = $orderLineItemSubtotal + $orderShippingTotal + $orderTaxTotal - $orderDiscountTotal;

            //Check if orders can still be processed with the left daily charge amount in case billings settings are enabled
            if ($leftDailyMaxChargeValue > 0) {
                $orderChargeCheck = $leftDailyMaxChargeValue - $orderTotalCost;
                if ($orderChargeCheck <= 0) {
                    $ordersToSkip[] = $order->id;
                    continue;
                } else {
                    $leftDailyMaxChargeValue -= $orderTotalCost;
                }
            }

            $orderAccountPayment = new OrderAccountPayment();
            $orderAccountPayment->discount = $orderDiscountTotal;
            $orderAccountPayment->line_item_subtotal = $orderLineItemSubtotal;
            $orderAccountPayment->order_id = $order->id;
            $orderAccountPayment->shipping_subtotal = $orderShippingTotal;
            $orderAccountPayment->tax = $orderTaxTotal;
            $orderAccountPayment->total_cost = $orderTotalCost;
            $orderAccountPayment->lineItems = $orderAccountPaymentLineItems;

            $orderAccountPayments->push($orderAccountPayment);

            $this->logger->info("Order ID $order->id OrderAccountPayment:" . $orderAccountPayment->toJson());
        }

        //Re-flag orders as pending due to daily charge excess
        if (!empty($ordersToSkip)) {
            foreach ($ordersToSkip as $orderToSkip) {
                $order = Order::find($orderToSkip);
                if ($order) {
                    $order->status = OrderStatus::PENDING;
                    $order->save();

                    $orderChargeLog = $order->logs()->where('message', 'Daily Charge Limit has been reached, cannot charge Order today')->whereDate('created_at', Carbon::today())->first();
                    if (!$orderChargeLog) {
                        $order->logs()->create([
                            'message' => "Daily Charge Limit has been reached, cannot charge Order today",
                            'message_type' => OrderLog::MESSAGE_TYPE_INFO
                        ]);
                    }
                }
            }
        }

        $subtotal = 0.00;
        $shippingTotal = 0.00;
        $taxTotal = 0.00;
        $totalCharge = 0.00;
        $totalDiscount = 0.00;
        foreach ($orderAccountPayments as $orderAccountPayment) {
            $subtotal += $orderAccountPayment->line_item_subtotal;
            $shippingTotal += $orderAccountPayment->shipping_subtotal;
            $taxTotal += $orderAccountPayment->tax;
            $totalCharge += $orderAccountPayment->total_cost;
            $totalDiscount += $orderAccountPayment->discount;
        }
        $accountPayment = new AccountPayment();
        $accountPayment->account_id = $this->account->id;
        $accountPayment->discount_total = $totalDiscount;
        $accountPayment->amount = $totalCharge;
        $accountPayment->subtotal = $subtotal;
        $accountPayment->shipping_total = $shippingTotal;
        $accountPayment->tax_total = $taxTotal;

        $accountPayment->orderPayments = $orderAccountPayments;

        $this->logger->subheader("Total Charges for " . count($orderAccountPayments) . " Orders");
        $this->logger->info("Line Item Subtotal: $subtotal");
        $this->logger->info("Shipping Total: $shippingTotal");
        $this->logger->info("Tax Total: $taxTotal");
        $this->logger->info("Total Discount: $totalDiscount");
        $this->logger->info("Total: $totalCharge");

        $accountPayment = $this->payUsingAccountCreditBalance($accountPayment);

        return $accountPayment;
    }

    /**
     * @param Order $order
     * @param array $lineItems
     * @return bool
     */
    public function validateLineItems($order, $lineItems)
    {
        foreach ($lineItems as $lineItem) {
            $this->logger->subSubheader("Validate Line Item ID $lineItem->id");

            if (strtolower($order->store->platform->name) === 'teelaunch') {
                return true;
            }

            $platformStoreProductVariant = $lineItem->platformStoreProductVariant;
            if (empty($platformStoreProductVariant)) {
                $this->orderHasError($order, "Line Item SKU $lineItem->sku is not linked to a Platform Variant", OrderStatus::PENDING);
                return false;
            }

            if ($platformStoreProductVariant->is_ignored) {
                $this->logger->info("PlatformStoreProductVariant is set to ignore, skipping Line Item");
                continue;
            }

            $productVariant = $lineItem->productVariant ?? $platformStoreProductVariant->productVariant;
            if (empty($productVariant)) {
                //Product Variant not mapped
                $this->orderHasError($order, "Platform Variant SKU $lineItem->sku is not linked to a teelaunch Variant, either Link Variant or mark it as Not teelaunch", OrderStatus::PENDING);
                return false;
            }

            $blankVariant = $productVariant->blankVariant;
            if (empty($blankVariant)) {
                //Blank Variant not found
                $this->orderHasError($order, "teelaunch Variant SKU $productVariant->sku is not linked to a teelaunch Blank Variant", OrderStatus::PENDING);
                return false;
            }

            if (!$blankVariant->is_processable) {
                $blankAccess = $this->account->blankAccesses->where('blank_id', $blankVariant->blank_id)->first();
                if (!$blankAccess) {
                    //Blank Variant is not processable
                    $this->orderHasError($order, "teelaunch Blank Variant SKU $blankVariant->sku is not currently processable", OrderStatus::PENDING);
                    return false;
                }
            }

            $basePrice = $blankVariant->price;
            if (empty($basePrice)) {
                //Product Variant not mapped
                $this->orderHasError($order, "teelaunch Blank Variant SKU $blankVariant->sku is missing price, please contact support", OrderStatus::PENDING);
                $this->sendAdminEmail($order, "teelaunch Blank Variant ID $blankVariant->id/ SKU $blankVariant->sku is missing price");
                return false;
            }
            $this->logger->info("Valid Line Item");
        }
        return true;
    }

    /**
     * @param BlankCategory $category
     * @param array $printFiles
     * @return int
     */
    public function getSurcharge($category, $printFiles)
    {
        $surcharge = 0;
        $isApparel = strtolower($category->name) == 'apparel';
        if ($isApparel && count($printFiles) > 1) {
            $surcharge = 5;
        }
        return $surcharge;
    }

    /**
     * @param OrderLineItem $lineItem
     * @param CountryGroupBlankShipping $variantShipping
     * @param string $vendor
     * @param array $orderVendors
     * @return float|mixed
     */
    public function getShippingCharge($lineItem, $variantShipping, $vendor, &$orderVendors)
    {
        //Charge ship_price on first of each vendor in order, then ship_price_discounted on subsequent
        if (!isset($orderVendors[$vendor])) {
            $firstShipPrice = $variantShipping->ship_price;
            $additionalShipPrice = $variantShipping->ship_price_discounted * max(0, $lineItem->quantity - 1);
            $totalPrice = $firstShipPrice + $additionalShipPrice;
            $internationalShipPrice = $totalPrice * max(0, $lineItem->quantity - 1) * ($variantShipping->international_rate / 100);
            $totalPrice = $totalPrice + $internationalShipPrice;

            $this->logger->info("First ship price: $firstShipPrice");
            $this->logger->info("Additional ship price: $additionalShipPrice");
            $this->logger->info("international ship price: $internationalShipPrice");

            // Calculate temporary ship price if enabled
            if ($variantShipping && $variantShipping->temporary_ship_charge && $variantShipping->ship_price_temporary > 0) {
                $this->logger->info("Temporary Ship Price: " . $variantShipping->ship_price_temporary);
                $totalPrice += $variantShipping->ship_price_temporary;
            }

            $orderVendors[$vendor] = $variantShipping;
        } else {
            $firstShipPrice = $variantShipping->ship_price;
            $additionalShipPrice = $variantShipping->ship_price_discounted * max(0, $lineItem->quantity);
            $totalPrice = $additionalShipPrice;
            $internationalShipPrice = ($totalPrice + $firstShipPrice) * max(0, $lineItem->quantity) * ($variantShipping->international_rate / 100);
            $totalPrice = $totalPrice + $internationalShipPrice;

            $this->logger->info("Additional ship price: $additionalShipPrice");
            $this->logger->info("international ship price: $internationalShipPrice");

        }
        $this->logger->info("Shipping Total: $totalPrice");
        return $totalPrice;
    }

    /**
     * @param AccountPayment $accountPayment
     * @throws Exception
     */
    function chargeActivePaymentMethod(AccountPayment $accountPayment)
    {
        //Charge PaymentMethod
        $this->logger->header("Charge Payment Method");

        if ($accountPayment->amount == 0.00) {
            $this->logger->warning("AccountPayment amount is 0.00, skipping charge");
            return;
        }

        $accountPaymentMethod = AccountPaymentMethod::where([['account_id', $this->account->id], ['is_active', true]])->first();
        $manualCharge = AccountSettings::where('account_id', $this->account->id)->where('key', 'manual_charge')->first();
        $manualCharge = $manualCharge->value ?? 0;

        $chargeAccount = true; // set to false to do not charge accounts under local environment
        $this->logger->warning($chargeAccount);

        $orderIds = $accountPayment->orderPayments->pluck('order_id');

        if($manualCharge){
            $this->logger->warning("Charge Account Manually");

            $orderPayments = $accountPayment->orderPayments;
            unset($accountPayment->orderPayments);

            $accountPayment->account_payment_method_id = $accountPaymentMethod->id;
            $accountPayment->transaction_id = 'MANUAL-TRANSACTION';
            $accountPayment->status = ChargeDetails::STATUS_SUCCESS;
            $accountPayment->save();

            foreach ($orderPayments as $orderPayment) {
                $lineItems = $orderPayment->lineItems;
                unset($orderPayment->lineItems);
                $payment = $accountPayment->orderPayments()->save($orderPayment);

                foreach ($lineItems as $lineItem) {
                    $payment->lineItems()->save($lineItem);
                }
            }

            $accountPayment->save();
            //Set to Paid
            Order::whereIn('id', $orderIds)->update([
                'status' => OrderStatus::PAID
            ]);
        }
        else{
            if ($accountPaymentMethod) {
                if ($chargeAccount) {
                    //Charge payment method
                    $orderPayments = $accountPayment->orderPayments;

                    unset($accountPayment->orderPayments);
                    $accountPayment->account_payment_method_id = $accountPaymentMethod->id;
                    $accountPayment->status = ChargeDetails::STATUS_FAIL;
                    $accountPayment->save();

                    try {
                        $accountPaymentChargeStatus = $accountPaymentMethod->charge($accountPayment);
                    }
                    catch(Exception $e){
                        $this->logger->error($e);
                        $accountPaymentChargeStatus = false;
                    }

                    if ($accountPaymentChargeStatus) {
                        $chargeDetails = $accountPaymentMethod->getChargeDetails();
                        $accountPayment->transaction_id = $chargeDetails->getPlatformTransactionId();
                        $accountPayment->status = $chargeDetails->getStatus();
                        $accountPayment->save();
                        foreach ($orderPayments as $orderPayment) {
                            $lineItems = $orderPayment->lineItems;
                            unset($orderPayment->lineItems);
                            $payment = $accountPayment->orderPayments()->save($orderPayment);
                            foreach ($lineItems as $lineItem) {
                                $payment->lineItems()->save($lineItem);
                            }
                        }
                        //On success set next status
                        Order::whereIn('id', $orderIds)->update([
                            'status' => OrderStatus::PAID
                        ]);
                        foreach ($orderIds as $orderId) {
                            OrderLog::create([
                                'order_id' => $orderId,
                                'message' => 'Order successfully charged',
                                'message_type' => OrderLog::MESSAGE_TYPE_INFO
                            ]);
                        }
                        $this->sendReceiptEmail($accountPayment);
                    }
                    else{
                        $this->logger->warning("Failed to charge billing method");
                        //Set back to Pending
                        Order::whereIn('id', $orderIds)->update([
                            'status' => OrderStatus::PENDING
                        ]);
                        foreach ($orderIds as $orderId) {
                            OrderLog::create([
                                'order_id' => $orderId,
                                'message' => 'Failed to charge billing method, confirm your billing information is up to date',
                                'message_type' => OrderLog::MESSAGE_TYPE_ERROR
                            ]);
                        }
                        $this->sendChargeFailureEmail($this->account->user->email, $accountPaymentMethod->getChargeDetails());
                    }

                }
                else {
                    $orderPayments = $accountPayment->orderPayments;
                    unset($accountPayment->orderPayments);

                    $accountPayment->account_payment_method_id = null;
                    $accountPayment->transaction_id = 'FAKE-TRANSACTION';
                    $accountPayment->status = ChargeDetails::STATUS_SUCCESS;
                    $accountPayment->save();

                    foreach ($orderPayments as $orderPayment) {
                        $lineItems = $orderPayment->lineItems;
                        unset($orderPayment->lineItems);
                        $payment = $accountPayment->orderPayments()->save($orderPayment);

                        foreach ($lineItems as $lineItem) {
                            $payment->lineItems()->save($lineItem);
                        }
                    }

                    $accountPayment->save();
                    $this->logger->debug("2");
                    $this->logger->warning("Local Environment, skipping charge");
                    //Set to Paid
                    Order::whereIn('id', $orderIds)->update([
                        'status' => OrderStatus::PAID
                    ]);
                }
            }
            else {
                $this->logger->warning("No Active Payment Method");
                if ($chargeAccount) {
                    //Set back to Pending
                    Order::whereIn('id', $orderIds)->update([
                        'status' => OrderStatus::PENDING,
                        'has_error' => true
                    ]);
                    foreach ($orderIds as $orderId) {
                        OrderLog::create([
                            'order_id' => $orderId,
                            'message' => 'No active billing method available, please enter your billing information',
                            'message_type' => OrderLog::MESSAGE_TYPE_ERROR
                        ]);
                    }
                    $this->sendNoActivePaymentMethodEmail($this->account->user->email);
                }
                else {
                    $accountPayment->save();

                    $this->logger->warning("Local Environment, skipping charge");
                    //Set back to Pending
                    Order::whereIn('id', $orderIds)->update([
                        'status' => OrderStatus::PAID
                    ]);
                }
            }
        }

    }

    function sendOrdersToOrderDesk()
    {
        //Send Orders to Order Desk
        $this->logger->header("Send Orders to Order Desk");

        $orders = Order::where([['account_id', $this->account->id], ['status', OrderStatus::PAID]])->get();

        //Set next status
        Order::whereIn('id', $orders->pluck('id'))->update([
            'status' => OrderStatus::PROCESSING_PRODUCTION
        ]);

        foreach ($orders as $order) {
            $this->sendOrderToOrderDesk($order);
        }
    }

    /**
     * @param $order
     */
    function sendOrderToOrderDesk($order)
    {
        $order->refresh();

        $this->logger->subheader("Process Order ID $order->id");
        $this->orderDesk->lastHttpCode = null;
        $response = null;

        try {

            //If still processing print files or Line Item Image, set order back to PAID
            $filesProcessing = $this->isProcessingOrderLineItemFiles($order);
            if($filesProcessing['lineItem'] || $filesProcessing['printFile']){
                $order->status = OrderStatus::PAID;
                $order->save();

                if($filesProcessing['lineItem']){
                    $order->logs()->create([
                        'message' => 'Processing line items images, Order will be sent to production on next attempt',
                        'message_type' => OrderLog::MESSAGE_TYPE_INFO
                    ]);
                }
                elseif($filesProcessing['printFile']){
                    $order->logs()->create([
                        'message' => 'Processing print files, Order will be sent to production on next attempt',
                        'message_type' => OrderLog::MESSAGE_TYPE_INFO
                    ]);
                }
                return;
            }

            $orderDeskOrder = OrderFormatter::formatForPlatform($order);

            $this->logger->debug("Send Order Data to orderDesk: " . json_encode($orderDeskOrder));

            //Validate order line items print files
            $orderLineItemsHasError = false;
            $orderLineItems = $orderDeskOrder->getOrderItems();
            foreach($orderLineItems as $orderLineItem){

                $orderLineItemMetaData = $orderLineItem->getMetadata();

                //Skip print files validation in case of monogram type fields
                if(isset($orderLineItemMetaData['is_monogram']) && $orderLineItemMetaData['is_monogram']){
                    continue;
                }

                foreach($orderLineItemMetaData as $key => $value){
                    if(stripos($key, 'print_url_') === 0){
                        if(empty($value)){
                            $orderLineItemsHasError = true;
                            break 2;
                        }
                    }
                }
            }

            if($orderLineItemsHasError){
                $this->logger->debug("Order Desk Order has line items with no print files");
                $this->orderHasError($order, 'Order failed to send to production, some of the line items has no print files. Please contact our support for assistance', OrderStatus::PAID);
                return;
            }

            //Metadata length validation
            $metaDataLength = strlen(json_encode($orderDeskOrder->getOrderMetadata()));
            if ($metaDataLength >= 2000) {
                $this->logger->debug("Order Desk Order MetaData too long, max 2000 chars allowed | Length: $metaDataLength");
                $this->logger->warning("Order Desk Order MetaData: " . json_encode($orderDeskOrder->getOrderMetadata()));
                $this->orderHasError($order, 'Order failed to send to production, contact support for assistance', OrderStatus::PAID);
                return;
            }

            $response = $this->orderDesk->createOrder($orderDeskOrder->toArray());

            if ($this->orderDesk->lastHttpCode == 201) {
                $this->logger->info('Success');
                //Save OrderDesk ID to DB
                $order->order_desk_id = $response->order->id;
                $order->status = OrderStatus::PRODUCTION;
                $order->has_error = false;
                $order->save();

                $order->logs()->create([
                    'message' => 'Order sent to production',
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);
            } else {
                if (isset($response) && isset($response->existing_order_id)) {
                    $this->logger->info('Existing OrderDesk order found | order_desk_id: ' . $response->existing_order_id);

                    //Save OrderDesk ID to DB
                    $order->order_desk_id = $response->existing_order_id;
                    $order->status = OrderStatus::PRODUCTION;
                    $order->has_error = false;
                    $order->save();

                    $order->logs()->create([
                        'message' => 'Order sent to production',
                        'message_type' => OrderLog::MESSAGE_TYPE_INFO
                    ]);

                } else {
                    $this->logger->warning('Order rejected');
                    //Failed
                    $this->orderHasError($order, 'Order failed to send to production, contact support for assistance', OrderStatus::PAID);
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e);
        }
    }

    /**
     * @param Order $order
     * @return array
     */
    function isProcessingOrderLineItemFiles($order)
    {
        $response = [];
        $response['lineItem'] = false;
        $response['printFile'] = false;

        foreach ($order->lineItems as $lineItem) {
            if(!$lineItem->thumbUrl && strtolower($order->store->platform->name) !== 'teelaunch'){
                $response['lineItem'] = true;
                return $response;
            }

            foreach ($lineItem->printFiles as $printFile) {
                if ($printFile->status < ProductPrintFile::STATUS_FINISHED) {
                    $response['printFile'] = true;
                    return $response;
                }
            }
        }

        return $response;
    }

    /**
     * @param $subject
     * @param $emailContent
     * @param $to
     * @param $from
     * @param $cc
     * @param string $bcc
     * @param string $messageSubject
     * @return bool
     */
    function sendEmail($subject, $emailContent, $to, $from, $cc, $bcc = '', $messageSubject = ''): bool
    {
        if (config('app.env') != 'production') {
            $localEmail = config('app.local_email_to');
            if (!$localEmail) {
                $this->logger->info("Non Production Environment detected, set an email in LOCAL_EMAIL_TO env to send emails to");
                return false;
            }
            $originalEmail = $to;
            $emailContent = "<P>Originally for $originalEmail</P>" . $emailContent;
            $to = $localEmail;
            $this->logger->info("Non Production Environment detected, sending email to $to");
        }

        $from = 'customerservice@teelaunch.com';

        //--------- Create Email template----------------------//
        $currYear = date('Y');
        $company = 'teelaunch';
        $teelaunchEmail = $from;

        $emailBody = File::get('public/html/emailTemplate.html');

        if (!$emailBody) {
            $this->logger->warning("No email body found");
            return false;
        }

        if (empty($messageSubject)) {
            $messageSubject = $subject;
        }

        $emailBody = str_replace('*|MC:SUBJECT|*', $messageSubject, $emailBody);
        $emailBody = str_replace('*|CURRENT_YEAR|*', $currYear, $emailBody);
        $emailBody = str_replace('*|COMPANY|*', $company, $emailBody);
        $emailBody = str_replace('*|HTML:LIST_ADDRESS_HTML|*', $teelaunchEmail, $emailBody);
        $emailBody = str_replace('*|EMAIL_CONTENT|*', $emailContent, $emailBody);
        $emailBody = str_replace('*|EMAIL_SUBJECT|*', $subject, $emailBody);

        Mail::send([], [], function (Message $message) use ($to, $from, $cc, $bcc, $subject, $emailBody) {
            $message->from($from)
                ->to($to)
                ->subject($subject)
                ->setBody($emailBody, 'text/html');
        });

        $this->logger->info("Send Email | To: $to | From: $from | Subject: $subject");

        return true;
    }

    function sendNoActivePaymentMethodEmail($to)
    {
        $subject = 'Something went wrong with your teelaunch order';

        $message = 'When trying to process your latest order(s) on teelaunch, we had an error';
        $message .= '<p>To make sure things run smoothly moving forward, if you could quickly log into teelaunch and make sure your payment details are all correct, we won\'t have this problem again.</p>';
        $message .= '<p>No huge issue, just something we hope we can resolve quick so we can keep the speed in your deliveries that you expect.</p>';
        $message .= '<p>If you have any questions, please don\'t hesitate to contact us, we\'re just an email away and are more than happy to help out!</p>';
        $message .= 'Support team,<br>teelaunch';

        $from = 'customerservice@teelaunch.com';
        $cc = 'support@teelaunch.com';

        $this->sendEmail($subject, $message, $to, $from, $cc);
    }

    /**
     * @param $to
     * @param Order $order
     */
    function sendMissingCustomerInfoEmail($to, $order)
    {
        $this->logger->debug('sendMissingCustomerInfoEmail');

        $storeName = $order->store->name;
        $storeUrl = $order->store->url;
        $platformOrderNumber = $order->platform_order_number;
        $appUrl = config('app.url');
        $orderUrl = "$appUrl/orders/$order->id";

        $from = 'customerservice@teelaunch.com';
        $cc = 'support@teelaunch.com';

        $subject = 'teelaunch Order Missing Customer/Shipping Info.';

        $message = 'We got your latest order but something\'s missing.
                    <P>
                    For some reason our system has flagged this order as missing customer and/or shipping information and we won\'t be able to process the order until that\'s sorted. (We need to know where to send the products)
                    <P>';


        //TODO: Create platform specific orderLink methods
//        $message .= 'You can view the order here:
//                    <P>
//                    <a href="'.$storeUrl.'">' . $storeName . '</a>
//                    <P>';


        $message .= 'You can view the order here:
                    <P>
                    <a href="' . $orderUrl . '">Order ' . $platformOrderNumber . '</a>
                    <P>';

        $message .= 'Please take a look and verify all the details are correct and we will try and re-process the order.
                    <P>
                    If you have any questions or any problems, please don\'t hesitate to reply back to this email where one of the support team will take good care of you and make sure we get your order sorted out ASAP.
                    <P>
                    Thanks for your help!
                    <P>
                    Support team,<br>
                    teelaunch';


        $this->sendEmail($subject, $message, $to, $from, $cc);
    }

    function sendGeneralErrorEmail($to, $orders = [])
    {
        $this->logger->debug('sendGeneralErrorEmail');

        $appUrl = config('app.url');

        $orderLinks = [];
        foreach ($orders as $order) {
            $platformOrderNumber = $order->platform_order_number;
            $orderUrl = "$appUrl/orders/$order->id";
            $orderLinks[] = '<a href="' . $orderUrl . '">Order #' . $platformOrderNumber . '</a>';
        }

        $from = 'customerservice@teelaunch.com';
        $cc = 'support@teelaunch.com';

        $subject = 'Something went wrong with your teelaunch orders';

        $message = "We're trying to process your latest order but it contains a product that is not linked to a teelaunch product.";
        $message .= "<p>If you head on over to your teelaunch dashboard you need to either link it to a teelaunch product or if it is not a teelaunch product let us know by pressing the \"Not teelaunch\" button and we'll ignore it in the future.</p>";

        $message .= 'You can view the orders here:<p>';

        $message .= implode('<br>', $orderLinks);

        $message .= '</p>';

        $message .= "<p>If you have any questions about this order, reply to this email and we'd be happy to help you further.</p>";

        $this->sendEmail($subject, $message, $to, $from, $cc);
    }

    function sendAdminEmail($order, $addOnMessage = null)
    {
        $this->sendSlackNotification($order, $addOnMessage);

        $sunriseSupportEmails = explode(',', config('mail.sunrise_support_emails'));
        $teelaunchSupportEmails = explode(',', config('mail.teelaunch_support_emails'));

        $to = array_merge($sunriseSupportEmails, $teelaunchSupportEmails);
        $from = 'customerservice@teelaunch.com';
        $cc = 'support@teelaunch.com';

        $subject = "Failed to Process Order ID $order->id";

        $message = "Order ID $order->id needs Admin attention<P>";

        $message .= $addOnMessage;

        $this->sendEmail($subject, $message, $to, $from, $cc);
    }

    function sendChargeFailureEmail($to, $chargeDetails)
    {
        $from = 'customerservice@teelaunch.com';
        $cc = 'support@teelaunch.com';

        switch ($chargeDetails) {
            case 'Your card was declined.':
                $subject = "Whoops! Your card got declined";
                $message = "For some reason, while we were processing the card you had on file for your recent order… we got a big red message saying “Declined\"";
                $message .= "<p>Don't panic. This could be for a variety of reasons, and we are on hand to help sort this out!";
                $message .= "<p>Step 1) Log in to teelaunch and make sure you credit card details are correct and up to date</p>";
                $message .= "<p>We will process the order again in 48 hours, which should be plenty of time for you to get that done.</p>";
                $message .= "<p>The main thing is, don\'t worry. This happens often and is a usually because a number has been entered wrong or the card is out of date.</p>";
                $message .= "<p>If you have ANY questions, please don\'t hesitate to reply back to this email and we will do our very best to help out!</p>";
                $message .= "<p>Thanks!</p>";
                $message .= "Support team,<br>teelaunch";

                break;

            default:
                $subject = 'Something went wrong with your teelaunch orders';
                $message = "When trying to process your latest order(s) on teelaunch, we had an error message with our bank";

                $message .= "<p>To make sure things run smoothly moving forward, if you could quickly log into teelaunch and make sure your payment information are all correct, we won\'t have this problem again.</p>";
                $message .= "<p>No huge issue, just something we hope we can resolve quick so we can keep the speed in your deliveries that you expect.</p>";
                $message .= "<p>If you have any questions, please don\'t hesitate to contact us, we\'re just an email away and are more than happy to help out!</p>";
                $message .= "Support team,<br>teelaunch";

                break;
        }
        $this->sendEmail($subject, $message, $to, $from, $cc);
    }

    /**
     * @param AccountPayment $accountPayment
     * @throws Exception
     */
    function sendReceiptEmail($accountPayment)
    {
        $this->logger->debug("Account: " . json_encode($this->account->user));

        $to = $this->account->user->email;
        $accountName = $this->account->name;
        $shippingCost = $accountPayment->shipping_total;
        $totalCost = $accountPayment->amount;
        $discountPercent = 0;

        $orderPayments = $accountPayment->orderPayments ?? [];

        $from = 'customerservice@teelaunch.com';
        $bcc = 'receipts@teelaunch.com';
        $subject = "teelaunch Charges for $accountName - " . date('F j, Y');

        $message = '<table style="width: 100%; padding: 5px 20px; border-collapse: collapse; border-spacing: 0;" >
                        <tr>
                            <td colspan="4">Receipt: ' . $accountName . '</td>
                            <td colspan="4">' . date('F j, Y') . '</td>
                        </tr>
                        <tr>
                            <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">&nbsp;</td>
                            <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">Item</td>
                            <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">Price</td>
                            <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">Qty</td>
                            <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">Total</td>
                            <td style="background-color:#EBF5FF; border-top:1px solid #444444; white-space:nowrap;">&nbsp;</td>
                        </tr>';

        foreach ($orderPayments as $orderPayment) {
            $message .= $this->formatOrderAccountPaymentRow($orderPayment);
            foreach ($orderPayment->lineItems as $lineItem) {
                $message .= $this->formatAccountPaymentLineItemRow($lineItem);
            }
            $message .= $this->formatOrderAccountPaymentTotalRow($orderPayment);
        }


        $message .= '<tr>
                        <td style="border-top: 3px solid #444444;">&nbsp;</td>
                        <td style="border-top: 3px solid #444444; font-weight: 700; white-space:nowrap;">Grand Total</td>
                        <td colspan="2"  style="border-top: 3px solid #444444;" >&nbsp;</td>
                        <td style="border-top: 3px solid #444444; font-weight: 700;  white-space:nowrap;">$' . sprintf('%01.2f', $accountPayment->amount) . '</td>
                        <td style="border-top: 3px solid #444444;">&nbsp;</td>
                    </tr>
                    </table>';

        $this->sendEmail($subject, $message, $to, $from, $cc = null, $bcc);
    }

    /**
     * @param OrderAccountPayment $orderAccountPayment
     * @return string
     */
    public function formatOrderAccountPaymentRow($orderAccountPayment)
    {
        $this->logger->debug('formatOrderAccountPaymentRow');

        $order = Order::find($orderAccountPayment->order_id);
        $orderNumber = $order ? $order->platform_order_number : 'N/A';
        return '<tr>
                    <td colspan="6" style="border-top: 2px solid #444444; font-weight:bold; white-space:nowrap;">Order #' . $orderNumber . '</td>
                </tr>';
    }

    /**
     * @param OrderAccountPayment $orderAccountPayment
     * @return string
     */
    public function formatOrderAccountPaymentTotalRow($orderAccountPayment)
    {
        return '<tr>
                    <td>&nbsp;</td>
                    <td style="font-weight:bold;">Subtotal</td>
                    <td colspan="2">&nbsp;</td>
                    <td style="font-weight:bold;">$' . number_format($orderAccountPayment->line_item_subtotal, 2) . '</td>
                    <td>&nbsp;</td>
                 </tr>
                 <tr>
                    <td>&nbsp;</td>
                    <td style="font-weight:bold;">Shipping</td>
                    <td colspan="2">&nbsp;</td>
                    <td style="font-weight:bold;">$' . number_format($orderAccountPayment->shipping_subtotal, 2) . '</td>
                    <td>&nbsp;</td>
                 </tr>' .
            (($orderAccountPayment->discount > 0) ? '
                 <td>&nbsp;</td>
                    <td style="font-weight:bold;">Discount</td>
                    <td colspan="2">&nbsp;</td>
                    <td style="font-weight:bold;">$' . number_format($orderAccountPayment->discount, 2) . '</td>
                    <td>&nbsp;</td>
                </tr>' : '') .
            '<tr>
                    <td>&nbsp;</td>
                    <td style=" white-space:nowrap;font-weight:bold;">Order Total</td>
                    <td colspan="2">&nbsp;</td>
                    <td style="white-space:nowrap;font-weight:bold;">$' . number_format($orderAccountPayment->total_cost, 2) . '</td>
                    <td>&nbsp;</td>
                 </tr>';
    }

    /**
     * @param OrderAccountPaymentLineItem $accountPaymentLineItem
     * @return string
     */
    public function formatAccountPaymentLineItemRow($accountPaymentLineItem)
    {
        $orderLineItem = $accountPaymentLineItem->orderLineItem;
        $blankVariant = $accountPaymentLineItem->blankVariant;

        return '<tr>
                    <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">&nbsp;</td>
                    <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">' . $orderLineItem->title . '<br>SKU: ' . $blankVariant->sku . '</td>
                    <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">$' . $accountPaymentLineItem->unit_cost . '</td>
                    <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">' . $orderLineItem->quantity . '</td>
                    <td style="background-color:#EBF5FF; border-top:1px solid #444444; color:#000; white-space:nowrap;">$' . $accountPaymentLineItem->subtotal . '</td>
                    <td style="background-color:#EBF5FF; border-top:1px solid #444444; white-space:nowrap;">&nbsp;</td>
                </tr>';
    }

    public function sendSlackNotification($order, $message)
    {
        $slackMessage = new SlackMessage("Failed to Process Order ID $order->id: $message");
        $slackMessage->notify(new SlackNotification());
    }
}
