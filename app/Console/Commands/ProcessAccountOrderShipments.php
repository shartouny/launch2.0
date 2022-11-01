<?php

namespace App\Console\Commands;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Shipments\ShipmentStatus;
use Carbon\Carbon;
use App\Jobs\FulfillOrder;
use App\Logger\CustomLogger;

use App\Models\Orders\Order;
use Illuminate\Console\Command;
use App\Models\Accounts\Account;
use App\Logger\CronLoggerFactory;
use App\Models\Orders\OrderStatus;

class ProcessAccountOrderShipments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platforms:process-account-order-shipments {accountId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs account order shipment processing';

    /**
     * @CustomLogger
     */
    protected $logger;
    protected $skippedOrders = [];
    protected $logChannel = "order-shipments";
    protected $platformName = "system";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->argument('accountId');

        $loggerFactory = new ConnectorLoggerFactory(
            $this->logChannel,
            $accountId,
            $this->platformName);

        $this->logger = $loggerFactory->getLogger();

        $account = Account::find($accountId);

        $this->logger->title("Start Processing Account Order Shipments for Account: {$account->name}");

        $this->resetOrders($accountId);

        $orders = $this->getShippedOrders($accountId);

        if (!$orders) {
            $this->logger->title("No orders in Production to process");
        } else {
            // Set next status
            Order::whereIn('id', $orders->pluck('id'))->update([
                'status' => OrderStatus::PROCESSING_FULFILLMENT
            ]);

            // Compare the number of shipments to the total order splits from Order Desk
            foreach ($orders as $order) {
                $this->logger->subSubheader("Order ID");

                $total = $order->shipments()->count();

                $this->logger->debug("Total Shipments: $total");
                $this->logger->debug("OrderDesk Split: $order->order_desk_split");

                if ($total >= $order->order_desk_split) {
                    $this->logger->debug("Fulfill in Platform");
                    $this->sendToPlatform($order);
                } else {
                    $this->logger->debug("Skip fulfillment, still waiting on shipments");
                    $order->status = OrderStatus::PRODUCTION;
                    $order->save();
                }
            }
        }

        $this->logger->title('Finished Processing Account Order Shipments');
    }

    /**
     * @param $accountId
     */
    protected function resetOrders($accountId)
    {
        $updatedAt = config('app.env') !== 'local' ? Carbon::now()->subHours(1) : Carbon::now();
        Order::where([
            ['account_id', $accountId],
            ['status', OrderStatus::PROCESSING_FULFILLMENT],
            ['updated_at', '<=', $updatedAt]
        ])->update(['status' => OrderStatus::SHIPPED]);
    }

    protected function getShippedOrders($accountId)
    {
        return Order::where([
                ['account_id', $accountId],
                ['has_error', false],
                ['status', OrderStatus::SHIPPED]]
        )->whereHas('shipments', function ($q) {
            return $q->where('status', ShipmentStatus::PENDING);
        })->with('store')->get();
    }

    protected function sendToPlatform(Order $order): void
    {
        $store = $order->store;
        $manager = $store->platform->manager_class;

        $this->logger->info("Creating instance of {$manager} to fulfill orders and send shipments to platform");

        $platformManager = new $manager($manager, $order->account_id, $store->id, $this->logger);

        $this->logger->info("Scheduling Job to process shipments...");

        if(config('app.env') === 'local'){
            FulfillOrder::dispatch($platformManager, $order, $this->logger);
        }else {
            FulfillOrder::dispatch($platformManager, $order, $this->logger)->onQueue('orders');
        }
    }
}
