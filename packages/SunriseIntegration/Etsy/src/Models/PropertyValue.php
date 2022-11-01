<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class PropertyValue
 *
 * @method int getPropertyId;
 * @method string getPropertyName;
 * @method int getScaleId;
 * @method string getScaleName;
 * @method array getValueIds;
 * @method array getValues;
 *
 * @method setPropertyId(int $value);
 * @method setPropertyName(string $value);
 * @method setScaleId(int $value);
 * @method setScaleName(string $value);
 * @method setValueIds(array $value);
 * @method setValues(array $value);
 *
 * @package SunriseIntegration\Etsy\Models
 */
class PropertyValue extends AbstractEntity
{

    #region Properties

    #Fields
    protected $property_id;
    protected $property_name;
    protected $scale_id;
    protected $scale_name;
    protected $value_ids;
    protected $values;

    #endregion
}


