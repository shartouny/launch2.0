<?php

namespace SunriseIntegration\Shopify\Models\Order;

use SunriseIntegration\Shopify\Models\AbstractEntity;

class FinancialStatus extends AbstractEntity {

	#region Constants

	const PENDING = 'pending';
	const AUTHORIZED = 'authorized';
	const PARTIALLY_PAID = 'partially_paid';
	const PAID = 'paid';
	const PARTIALLY_REFUNDED = 'partially_refunded';
	const REFUNDED = 'refunded';
	const VOIDED = 'voided';
	const CANCELED = 'canceled';
	/**
	 * All authorized, pending, and paid orders (default)
	 */
	const ANY = 'any';

	#endregion

	public function toArray()
	{
		return [
			self::PENDING,
			self::AUTHORIZED,
			self::PARTIALLY_PAID,
			self::PAID,
			self::PARTIALLY_REFUNDED,
			self::REFUNDED,
			self::VOIDED,
			self::CANCELED,
			self::ANY,
		];
	}
}
