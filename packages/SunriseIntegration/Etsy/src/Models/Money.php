<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class Money
 *
 * @method getAmount;
 * @method getDivisor;
 * @method getCurrencyCode;
 * @method getFormattedRaw;
 * @method getFormattedShort;
 * @method getFormattedLong;
 * @method getBeforeConversion;
 *
 * @method setAmount(int $value);
 * @method setDivisor(int $value);
 * @method setCurrencyCode(string $value);
 * @method setFormattedRaw(string $value);
 * @method setFormattedShort(string $value);
 * @method setFormattedLong(string $value);
 * @method setBeforeConversion(Money $value);
 *
 * @package SunriseIntegration\Etsy\Models
 */
class Money extends AbstractEntity
{

    #region Properties

    #Fields
    protected $amount;
    protected $divisor;
    protected $currency_code;
    protected $formatted_raw;
    protected $formatted_short;
    protected $formatted_long;
    protected $before_conversion;

    #endregion

}
