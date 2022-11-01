<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class ListingProduct
 *
 * @method int getProductId;
 * @method array getPropertyValues;
 * @method string getSku;
 * @method array getOfferings;
 * @method bool getIsDeleted;
 *
 * @method setProductId(int $value);
 * @method setPropertyValues(array $value);
 * @method setSku(string $value);
 * @method setOfferings(array $value);
 * @method setIsDeleted(bool $value);
 *
 * @package SunriseIntegration\Etsy\Models
 */
class ListingProduct extends AbstractEntity
{

    #region Properties

    #Fields
    protected $product_id;
    protected $property_values;
    protected $sku;
    protected $offerings;
    protected $is_deleted;

    #endregion

}
