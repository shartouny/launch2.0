<?php

namespace SunriseIntegration\OrderDesk\Models;

/**
 * Class ReturnAddress
 * @package SunriseIntegration\OrderDesk\Models
 *
 * @method getTitle
 * @method getName
 * @method getCompany
 * @method getAddress1
 * @method getAddress2
 * @method getAddress3
 * @method getAddress4
 * @method getCity
 * @method getState
 * @method getPostalCode
 * @method getCountry
 * @method getPhone
 *
 * @method setTitle($value)
 * @method setName($value)
 * @method setCompany($value)
 * @method setAddress1($value)
 * @method setAddress2($value)
 * @method setAddress3($value)
 * @method setAddress4($value)
 * @method setCity($value)
 * @method setState($value)
 * @method setPostalCode($value)
 * @method setCountry($value)
 * @method setPhone($value)
 */

class ReturnAddress extends AbstractEntity
{
    protected $title;
    protected $name;
    protected $company;
    protected $address1;
    protected $address2;
    protected $city;
    protected $state;
    protected $postal_code;
    protected $country;
    protected $phone;
}
