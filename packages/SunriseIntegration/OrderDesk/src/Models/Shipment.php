<?php

namespace SunriseIntegration\OrderDesk\Models;

/**
 * Class Shipment
 * @package SunriseIntegration\OrderDesk\Models
 *
 * @method getTrackingNumber
 * @method getCarrierCode
 * @method getShipmentMethod
 * @method getWeight
 * @method getCost
 * @method getStatus
 * @method getTrackingUrl
 *
 * @method setTrackingNumber($value)
 * @method setCarrierCode($value)
 * @method setShipmentMethod($value)
 * @method setWeight($value)
 * @method setCost($value)
 * @method setStatus($value)
 * @method setTrackingUrl($value)
 */
class Shipment extends AbstractEntity
{
    protected $tracking_number;
    protected $carrier_code;
    protected $shipment_method;
    protected $weight;
    protected $cost;
    protected $status;
    protected $tracking_url;
}
