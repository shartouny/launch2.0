<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class ListingOffering
 *
 * @method int getOfferingId;
 * @method int getPrice;
 * @method int getQuantity;
 * @method bool getIsEnabled;
 * @method bool getIsDeleted;
 *
 * @method setOfferingId(int $value);
 * @method setPrice(float $value);
 * @method setQuantity(int $value);
 * @method setIsEnabled(bool $value);
 * @method setIsDeleted(bool $value);
 *
 * @package SunriseIntegration\Etsy\Models
 */
class ListingOffering extends AbstractEntity
{

    #region Properties

    #Fields
    protected $offering_id;
    protected $price;
    protected $quantity;
    protected $is_enabled;
    protected $is_deleted;

    #endregion

}
