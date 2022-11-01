<?php

namespace SunriseIntegration\Shopify\Models\Product;


use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class Image
 *
 * @method getCreatedAt()
 * @method getId()
 * @method getPosition()
 * @method getProductId()
 * @method getVariantIds=[]()
 * @method getSrc()
 * @method getUpdatedAt()
 * @method getWidth()
 * @method getHeight()
 *
 * @method setCreatedAt($date)
 * @method setId($id)
 * @method setPosition($position)
 * @method setProductId($id)
 * @method setVariantIds($ids)
 * @method setSrc($src)
 * @method setUpdatedAt($date)
 * @method setWidth($width)
 * @method setHeight($height)
 *
 * @package SunriseIntegration\Shopify\Models\Product
 */
class Image extends AbstractEntity {


	#region Properties

	protected $created_at;
	protected $id;
	protected $position;
	protected $product_id;
	protected $variant_ids = [];
	protected $src;
	protected $updated_at;
	protected $width;
	protected $height;

	#endregion

	public function load( $data ) {

		if ( \is_string($data) && $data !== '' ) {
			$data = json_decode( $data );
			$data = ! empty( $data->image ) ? $data->image : $data;
		}

		parent::load( $data );
	}
}
