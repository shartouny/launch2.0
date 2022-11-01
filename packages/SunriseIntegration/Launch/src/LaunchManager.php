<?php

namespace SunriseIntegration\Launch;

use App\Models\Accounts\Account;
use App\Models\Orders\Order;
use App\Models\Orders\OrderLog;
use App\Models\Orders\OrderStatus;
use App\Models\Shipments\ShipmentStatus;
use App\Platform\PlatformManager;

use App\Traits\EmailNotification;

class LaunchManager extends PlatformManager
{
  use EmailNotification;

  public function loadApi()
  {
    return 'api loaded';
  }

  public function importOrders($arguments = [])
  {
    //
  }

  public function processOrder($order)
  {
    //
  }

  public function fulfillOrder(Order $order)
  {
      $this->logger->header("fulfill Order ID {$order->id}");

      if (!isset($order->platform_order_id)) {
          $this->logger->error('Receipt ID not found on Order Object');
          return;
      }

      $order->status = OrderStatus::PROCESSING_FULFILLMENT;
      $order->save();

      $pendingShipments = $order->shipments()->where('status', ShipmentStatus::PENDING)->get();
      $this->logger->subheader("Pending Shipment Count: " . count($pendingShipments));
      foreach ($pendingShipments as $shipment) {
          $this->logger->info("Send Shipment ID $shipment->id");

          $shipment->status = ShipmentStatus::PROCESSING;
          $shipment->save();
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

      //Since it's our own store we need to notify account owner with order tracking details
      if ($pendingShipments){
          $account = Account::find($this->accountId);
          $to = $account->user->email;

          $this->sendTrackingNotificationEmail($to, $order->platform_order_id, $pendingShipments);
      }
  }

  public function importProducts($arguments = [])
  {
    //
  }

  public function processProduct($product)
  {
    //
  }

}
