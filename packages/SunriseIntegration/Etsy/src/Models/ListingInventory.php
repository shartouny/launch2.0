<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class ListingInventory
 *
 * @method array getProducts()
 * @method bool getPriceOnProperty()
 * @method bool getQuantityOnProperty()
 * @method bool getSkuOnProperty()
 *
 * @method setProducts(array $value)
 * @method setPriceOnProperty(bool $value)
 * @method setQuantityOnProperty(bool $value)
 * @method setSkuOnProperty(bool $value)
 *
 * @package SunriseIntegration\Etsy\Models
 */
class ListingInventory extends AbstractEntity
{

    #region Properties

    #Fields
    protected $products;
    protected $price_on_property;
    protected $quantity_on_property;
    protected $sku_on_property;

    #endregion

}
