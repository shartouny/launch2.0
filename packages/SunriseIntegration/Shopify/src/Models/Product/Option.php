<?php

namespace SunriseIntegration\Shopify\Models\Product;


use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class Option
 *
 * @method getCreatedAt()
 * @method getId()
 * @method getName()
 * @method getPosition()
 * @method getValues()
 * @method getProductId()
 *
 * @method setCreatedAt($date)
 * @method setId($id)
 * @method setName($name)
 * @method setPosition($position)
 *
 *
 * @package SunriseIntegration\Shopify\Models\Product
 */
class Option extends AbstractEntity {


	#region Properties

	protected $id;
	protected $product_id;
	protected $position;
	protected $name;
	protected $values = [];

	#endregion

	public function load( $data ) {

		if ( \is_string($data) && $data !== '' ) {
			$data = json_decode( $data );
			$data = ! empty( $data->image ) ? $data->image : $data;
		}

		parent::load( $data );
	}
}
