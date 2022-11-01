<?php

namespace SunriseIntegration\OrderDesk\Models;

/**
 * Class Discount
 * @package SunriseIntegration\OrderDesk\Models
 *
 * @method getName;
 * @method getCode;
 * @method getAmount;
 *
 * @method setName($value)
 * @method setCode($value)
 * @method setAmount($value);
 */

class Discount extends AbstractEntity
{
    protected $name;
    protected $code;
    protected $amount;
}
