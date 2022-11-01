<?php

namespace App\Formatters\OrderDesk;

use App\Models\Shipments\Shipment;
use App\Models\Shipments\ShipmentStatus;
use SunriseIntegration\OrderDesk\Models\Shipment as OrderDeskShipment;

class ShipmentFormatter
{
    static function formatForDb(int $orderId, OrderDeskShipment $orderDeskShipment): Shipment
    {
        $shipment = new Shipment();
        $shipment->status = ShipmentStatus::PENDING;
        $shipment->order_id = $orderId;

        $shipment->platform_shipment_id = $orderDeskShipment->getId();
        $shipment->platform_order_id = $orderDeskShipment->getOrderId();
        $shipment->tracking_number = $orderDeskShipment->getTrackingNumber();
        $shipment->carrier = $orderDeskShipment->getCarrierCode();
        $shipment->method = $orderDeskShipment->getShipmentMethod();
        $shipment->tracking_url = $orderDeskShipment->getTrackingUrl();
        $shipment->shipped_at = $orderDeskShipment->getDateShipped();

        $shipment->data = $orderDeskShipment->toJson();

        return $shipment;
    }
}