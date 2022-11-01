<?php

namespace SunriseIntegration\Shopify\Models\Order;

use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class LineItem
 *
 * @method getFulfillableQuantity()
 * @method getFulfillmentService()
 * @method getFulfillmentStatus()
 * @method getGrams()
 * @method getId()
 * @method getPrice()
 * @method getProductId()
 * @method getQuantity()
 * @method getRequiresShipping()
 * @method getSku()
 * @method getTitle()
 * @method getVariantId()
 * @method getVariantTitle()
 * @method getVendor()
 * @method getName()
 * @method getGiftCard()
 * @method getProperties()
 * @method getTaxable()
 * @method getTaxLines()
 * @method getTotalDiscount()
 * @method getPreTaxPrice()
 *
 * @method setFulfillableQuantity($quantity)
 * @method setFulfillmentService($service)
 * @method setFulfillmentStatus($status)
 * @method setGrams($grams)
 * @method setId($id)
 * @method setPrice($price)
 * @method setProductId($productId)
 * @method setQuantity($quantity)
 * @method setRequiresShipping($shipping)
 * @method setSku($sku)
 * @method setTitle($title)
 * @method setVariantId($id)
 * @method setVariantTitle($title)
 * @method setVendor($vendor)
 * @method setName($name)
 * @method setGiftCard($gc)
 * @method setProperties($properties)
 * @method setTaxable($isTaxable)
 * @method setTaxLines($taxLines)
 * @method setTotalDiscount($discount)
 * @method setPreTaxPrice($price)
 *
 * @package SunriseIntegration\Shopify\Models\Order
 */
class LineItem extends AbstractEntity {

	#region Properties

	protected $fulfillable_quantity;
	protected $fulfillment_service;
	protected $fulfillment_status;
	protected $grams;
	protected $id;
	protected $price;
	protected $product_id;
	protected $quantity;
	protected $requires_shipping;
	protected $sku;
	protected $title;
	protected $variant_id;
	protected $variant_title;
	protected $vendor;
	protected $name;
	protected $gift_card;
	protected $properties = [];
	protected $taxable;
	protected $tax_lines;
	protected $total_discount;
	protected $pre_tax_price;

	#endregion
}
