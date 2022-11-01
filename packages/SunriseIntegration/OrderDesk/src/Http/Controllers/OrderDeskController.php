<?php

namespace SunriseIntegration\OrderDesk\Http\Controllers;


use App\Jobs\FulfillOrder;
use App\Logger\CronLoggerFactory;
use App\Models\Orders\OrderStatus;
use Exception;
use stdClass;
use App\Models\Orders\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Shipments\Shipment;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Formatters\OrderDesk\ShipmentFormatter;
use SunriseIntegration\OrderDesk\Models\Order as OrderDeskOrder;
use SunriseIntegration\OrderDesk\Models\Shipment as OrderDeskShipment;
use SunriseIntegration\OrderDesk\OrderDesk;
use function Sentry\captureException;

class OrderDeskController extends Controller
{

    /**
     * @var CronLoggerFactory
     */
    protected $logger;

    protected $channel = 'webhooks';

    public function __construct(Request $request)
    {
        $loggerFactory = new CronLoggerFactory($this->channel);
        $this->logger = $loggerFactory->getLogger('logs/');

        $url = $request->fullUrl();
        $method = $request->method();

        $this->logger->title("$method $url");
        $this->logger->info("Headers: " . json_encode($request->headers->all()));

        //TODO: Move to middleware
        $agent = $request->header('user-agent');
        if ($agent !== 'OrderDesk/2.0 (https://www.orderdesk.me/)') {
            $this->logger->warning('Unauthorized Source');
            return $this->responseBadRequest('Unauthorized Source');
        }

        $this->logger->info('Data: ' . json_encode($request->all()));
        if (!$request->has('order')) {
            $this->logger->error('Order object not provided on request from OrderDesk');
            return $this->responseBadRequest('Shipment not provided from OrderDesk');
        }
    }

    /**
     * @param Request $request
     * @return stdClass
     */
    protected function handleRequest(Request $request)
    {
        $data = $request->input('order');
        try {
            $orderData = json_decode($data);
        } catch(Exception $e) {
            // workaround for posting from Postman for development or if orderdesk decides to properly send the order data
            $orderData = json_decode(json_encode($request->order));
            $this->logger->debug("orderData: " . json_encode($orderData));
        }
        return $orderData;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function handleShipmentsHook(Request $request)
    {
        $data = $this->handleRequest($request);
        $orderDeskOrder = new OrderDeskOrder($data);

        $orderMetadata = $orderDeskOrder->getOrderMetadata();
        $this->logger->debug("Order Metadata: " . json_encode($orderMetadata));

        $orderId = $orderMetadata->order_id;
        if (!$orderId) {
            $this->logger->error('Order Metadata is missing required order_id');
            return $this->responseBadRequest();
        }

        $order = Order::where('id', $orderId)->first();

        if(!$order){
            $this->logger->warning("Order ID $orderId not found");

            if(isset($orderMetadata->platform_order_id)){
                $this->logger->info("Get Order by Platform Order ID: $orderMetadata->platform_order_id");
                $order = Order::where('platform_order_id', $orderMetadata->platform_order_id)->first();
            }
        }

        if (!$order) {
            $orderDeskOrderId = $orderDeskOrder->getSourceId();
            $explodedId = explode('-',$orderDeskOrderId);

            //Rutter - fix for orders having string as platform order id ex: (WIX) 123-asd-159
            unset($explodedId[count($explodedId)-1]);
            $explodedId = implode('-', $explodedId);

            $this->logger->info("Get Platform Order ID by Exploding Source ID: $orderDeskOrderId");
            $this->logger->info("Platform Order ID: $explodedId");
            $order = Order::where('platform_order_id', $explodedId)->first();
        }

        if (!$order) {
            $this->logger->warning("No Order found");
            return $this->responseNotFound();
        }
        // we only need to associate one shipment with the line items, store one id here
        $lineItemShipmentId = NULL;

        foreach ($orderDeskOrder->getOrderShipments() as $shipmentIndex => $shipmentData) {
            $shipmentCount = $shipmentIndex + 1;
            $this->logger->subheader("Shipment $shipmentCount");
            $this->logger->debug("Shipment Data: " . json_encode($shipmentData));

            $orderDeskShipment = new OrderDeskShipment($shipmentData);

            if (Shipment::where('platform_shipment_id', $orderDeskShipment->getId())->first()) {
                continue;
            }

            $shipment = ShipmentFormatter::formatForDb($order->id, $orderDeskShipment);
            $order->shipments()->save($shipment);
            $lineItemShipmentId = $shipment->id;
        }

        // if we have a shipment id for this split, save it on the associated order line items
        if ($lineItemShipmentId) {
              // build array with all line items
              $includedLineItemArray = [];
            foreach ($orderDeskOrder->getOrderItems() as $orderItem) {
                array_push($includedLineItemArray, intval($orderItem->metadata->line_item_id));
            }
            // associate line items in shipment with lineItemShipmentId
            foreach ($order->lineItems as $lineItem) {
                if (in_array($lineItem->id, $includedLineItemArray)) {
                    $lineItem->shipment_id = $lineItemShipmentId;
                }
                $lineItem->save();
            }
        }

        if (!isset($orderMetadata->order_split_total)) {
            $this->logger->error("'Order Metadata is missing required order_split_total");
        }
        $orderSplitTotal = $orderMetadata->order_split_total ?? 1;

        if($order->status === OrderStatus::PRODUCTION) {
            $order->status = OrderStatus::SHIPPED;
            $this->logger->debug("Set Order Status to Shipped");
        }

        // use the largest order split total as some splits will reflect lower amount
        // order split hook below also keeps track of it.
        if($orderSplitTotal > $order->order_desk_split){
            $order->order_desk_split = $orderSplitTotal;
            $order->save();
        }

        $this->logger->debug("Order Desk Split: $order->order_desk_split");

        $this->sendShipmentToPlatform($order);

        return $this->responseOk();
    }

    public function handleOrderSplitHook(Request $request)
    {
        $data = $this->handleRequest($request);
        $orderDeskOrder = new OrderDeskOrder($data);

        $orderMetadata = $orderDeskOrder->getOrderMetadata();
        $this->logger->debug("Order Metadata: " . json_encode($orderMetadata));

        $orderId = $orderMetadata->order_id;
        if (!$orderId) {
            $this->logger->error('Order Metadata is missing required order_id');
            return $this->responseBadRequest();
        }

        $order = Order::where('id', $orderId)->first();

        if(!$order){
            $this->logger->warning("Order ID $orderId not found");

            if(isset($orderMetadata->platform_order_id)){
                $this->logger->info("Get Order by Platform Order ID: $orderMetadata->platform_order_id");
                $order = Order::where('platform_order_id', $orderMetadata->platform_order_id)->first();
            }
        }

        if (!$order) {
            $this->logger->warning("No Order found");
            return $this->responseNotFound();
        }

        // increment number of splits
        $order->order_desk_split = $order->order_desk_split + 1;
        $this->logger->debug("Order Desk Split: $order->order_desk_split");
        $order->save();

        return $this->responseOk();
    }

    protected function sendShipmentToPlatform(Order $order): void
    {
        $store = $order->store;
        $manager = $store->platform->manager_class;

        $this->logger->debug("Creating instance of {$manager} to fulfill orders and send shipments to platform | Order ID: {$order->id} | Store ID: {$store->id}");

        $platformManager = new $manager($manager, $order->account_id, $store->id);

        $this->logger->debug("Scheduling Job to process shipments...");

        if(config('app.env') === 'local'){
            FulfillOrder::dispatch($platformManager, $order, $this->logger);
        } else {
            FulfillOrder::dispatch($platformManager, $order, $this->logger)->onQueue('orders');
        }
    }
}
