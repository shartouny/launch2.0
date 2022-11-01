<?php

namespace SunriseIntegration\Shopify\Models\Order;


use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class Fulfillment
 *
 * @method getCreatedAt()
 * @method getId()
 * @method getCode()
 * @method getLineItems()
 * @method getNotifyCustomer()
 * @method getOrderId()
 * @method getReceipt()
 * @method getStatus()
 * @method getTrackingCompany()
 * @method getTrackingNumber()
 * @method getTrackingNumbers()
 * @method getTrackingUrls()
 * @method getUpdatedAt()
 * @method getVariantInventoryManagement()
 * @method getShipmentStatus()
 * @method getLocationId()
 *
 * @method setCreatedAt($date)
 * @method setId($id)
 * @method setCode($code)
 * @method setLineItems($items)
 * @method setNotifyCustomer($send)
 * @method setOrderId($id)
 * @method setReceipt($receipt)
 * @method setStatus($status)
 * @method setTrackingCompany($company)
 * @method setTrackingNumber($number)
 * @method setTrackingNumbers($numbers)
 * @method setTrackingUrls($urls)
 * @method setUpdatedAt($date)
 * @method setVariantInventoryManagement($policy)
 * @method setShipmentStatus($status)
 * @method setLocationId($locationId)
 *
 * @package SunriseIntegration\Shopify\Models\Order
 */
class Fulfillment extends AbstractEntity {

	#region Properties

	protected $created_at;
	protected $id;
	protected $code;
	protected $line_items;
	protected $notify_customer = false;
	protected $order_id;
	protected $receipt;
	protected $status;
	protected $tracking_company;
	protected $tracking_number;
	protected $tracking_numbers = [];
	protected $tracking_urls = '';
	protected $updated_at;
	protected $variant_inventory_management;
	protected $shipment_status;

	#endregion
}
