<?php

namespace SunriseIntegration\OrderDesk\Models;

/**
 * Class OrderItem
 * @package SunriseIntegration\OrderDesk\Models
 *
 * @method getId;
 * @method getName;
 * @method getPrice;
 * @method getQuantity;
 * @method getWeight;
 * @method getCode;
 * @method getDeliveryType;
 * @method getCategoryCode;
 * @method getVariationList;
 * @method getMetadata;
 *
 * @method setId($value)
 * @method setName($value)
 * @method setPrice($value)
 * @method setQuantity($value)
 * @method setWeight($value)
 * @method setCode($value)
 * @method setDeliveryType($value)
 * @method setCategoryCode($value)
 * @method setVariationList($value)
 * @method setMetadata($value)
 */

class OrderItem extends AbstractEntity
{
    protected $id;
    protected $name;
    protected $price;
    protected $quantity;
    protected $weight;
    protected $code;
    protected $delivery_type;
    protected $category_code;
    protected $variation_list;
    protected $metadata;
}
