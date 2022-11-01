<?php

namespace SunriseIntegration\Shopify\Models\Order;

use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class ShippingLine
 *
 * @method getCode()
 * @method getPrice()
 * @method getSource()
 * @method getTitle()
 * @method getTaxLines()
 * @method getCarrierIdentifier()
 * @method getRequestedFulfillmentServiceId()
 *
 * @method setCode($code)
 * @method setPrice($price)
 * @method setSource($source)
 * @method setTitle($title)
 * @method setTaxLines($tax)
 * @method setCarrierIdentifier($identifier)
 * @method setRequestedFulfillmentServiceId($serviceId)
 *
 * @package SunriseIntegration\Shopify\Models\Order
 */
class ShippingLine extends AbstractEntity {

	#region Properties

	protected $code;
	protected $price;
	protected $source = 'shopify';
	protected $title;
	protected $tax_lines = [];
	protected $carrier_identifier;
	protected $requested_fulfillment_service_id;

	#endregion
}
