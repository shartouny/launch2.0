<?php

namespace SunriseIntegration\Shopify\Models\Product;


use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class Variant
 *
 *
 * @method getBarcode()
 * @method getCompareAtPrice()
 * @method getCreatedAt()
 * @method getFulfillmentService()
 * @method getGrams()
 * @method getId()
 * @method getImageId()
 * @method getInventoryManagement()
 * @method getInventoryPolicy()
 * @method getInventoryQuantity()
 * @method getOldInventoryQuantity()
 * @method getInventoryQuantityAdjustment()
 * @method getMetafield()
 * @method getOption1()
 * @method getOption2()
 * @method getOption3()
 * @method getPosition()
 * @method getPrice()
 * @method getProductId()
 * @method getRequiresShipping()
 * @method getSku()
 * @method getTaxable()
 * @method getTitle()
 * @method getUpdatedAt()
 * @method getWeight()
 * @method getWeightUnit()
 * @method getInventoryItemId()
 *
 * @method setInventoryItemId($id)
 * @method setBarcode($barcode)
 * @method setCompareAtPrice($price)
 * @method setCreatedAt($date)
 * @method setFulfillmentService($service)
 * @method setGrams($grams)
 * @method setId($id)
 * @method setImageId($id)
 * @method setInventoryManagement($type)
 * @method setInventoryPolicy($policy)
 * @method setInventoryQuantity($quantity)
 * @method setOldInventoryQuantity($quantity)
 * @method setInventoryQuantityAdjustment($quantity)
 * @method setMetafield($metafield)
 * @method setOption1($option)
 * @method setOption2($option)
 * @method setOption3($option)
 * @method setPosition($position)
 * @method setPrice($price)
 * @method setProductId($id)
 * @method setRequiresShipping($shipping)
 * @method setSku($sku)
 * @method setTaxable($tax)
 * @method setTitle($title)
 * @method setUpdatedAt($date)
 * @method setWeight($weight)
 * @method setWeightUnit($unitWeight)
 *
 * @package SunriseIntegration\Shopify\Models\Product
 */
class Variant extends AbstractEntity {

	#region Properties

	protected $barcode;
	protected $compare_at_price;
	protected $created_at;
	protected $fulfillment_service;
	protected $grams;
	protected $id;
	protected $image_id;
	protected $inventory_management;
	protected $inventory_policy;
	protected $inventory_quantity;
	protected $old_inventory_quantity;
	protected $inventory_quantity_adjustment;
	protected $metafield = [];
	protected $option1;
	protected $option2;
	protected $option3;
	protected $position;
	protected $price;
	protected $product_id;
	protected $requires_shipping;
	protected $sku;
	protected $taxable;
	protected $title;
	protected $updated_at;
	protected $weight;
	protected $weight_unit;

	#endregion

	public function load( $data ) {

		if ( \is_string($data) && $data !== '' ) {
			$data = json_decode( $data );
			$data = ! empty( $data->variant ) ? $data->variant : $data;
		}

		parent::load( $data );
	}

    public function update() {
        $result = $this->getRequestData('/admin/variants/' . $this->getId() . '.json');

        if ( ! empty( $result ) ) {
            $result = json_decode( $result );

            if ( ! empty( $result->count ) ) {
                return $result->count;
            }
        }

        return 0;

    }
}
