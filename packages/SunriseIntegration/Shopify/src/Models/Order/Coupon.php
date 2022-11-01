<?php

namespace SunriseIntegration\Shopify\Models\Order;

use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class Coupon
 *
 * @method getAmount()
 * @method getType()
 * @method getCode()
 *
 * @method setAmount($amount)
 * @method setType($type)
 * @method setCode($code)
 *
 * @package SunriseIntegration\Shopify\Models\Order
 */
class Coupon extends AbstractEntity {

	protected $amount;
	protected $type;
	protected $code;

	const FIXED_AMOUNT = 'fixed_amount';
	const PERCENTAGE = 'percentage';
	const SHIPPING = 'shipping';
}
