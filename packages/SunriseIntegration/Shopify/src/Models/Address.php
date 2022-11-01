<?php

namespace SunriseIntegration\Shopify\Models;


/**
 * Class Address
 *
 * @method getAddress1()
 * @method getAddress2()
 * @method getCity()
 * @method getCompany()
 * @method getFirstName()
 * @method getLastName()
 * @method getPhone()
 * @method getProvince()
 * @method getCountry()
 * @method getZip()
 * @method getName()
 * @method getProvinceCode()
 * @method getCountryCode()
 * @method getCountryName()
 * @method getDefault()
 *
 * @method setAddress1($address)
 * @method setAddress2($address)
 * @method setCity($city)
 * @method setCompany($company)
 * @method setFirstName($firstName)
 * @method setLastName($lastName)
 * @method setPhone($phone)
 * @method setProvince($province)
 * @method setCountry($country)
 * @method setZip($zip)
 * @method setName($name)
 * @method setProvinceCode($provinceCode)
 * @method setCountryCode($countryCode)
 * @method setCountryName($name)
 * @method setDefault($default)
 *
 * @package SunriseIntegration\Shopify\Models
 */
class Address extends AbstractEntity {

	#region Properties

	protected $address1;
	protected $address2;
	protected $city;
	protected $company;
	protected $first_name;
	protected $last_name;
	protected $phone;
	protected $province;
	protected $country;
	protected $zip;
	protected $name;
	protected $province_code;
	protected $country_code;
	protected $country_name;
	protected $default;

	#endregion


}
