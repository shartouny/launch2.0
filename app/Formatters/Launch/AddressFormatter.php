<?php

namespace App\Formatters\Launch;

use App\Formatters\IFormatter;
use App\Models\Platforms\PlatformStore;
use Illuminate\Support\Facades\Log;
use App\Models\Orders\Address;

class AddressFormatter implements IFormatter
{

    static function formatForPlatform($order, $platformStore, $options = [], $logger = null)
    {

    }

    /**
     * @param \SunriseIntegration\Shopify\Models\Address $shopifyAddress
     * @param PlatformStore $platformStore
     * @param array $options
     * @param Log $logger
     * @return App\Models\Orders\Address
     */
    static function formatForDb($shopifyAddress, $platformStore, $options = [], $logger = null)
    {
        // convert Shopify Address to Teelaunch Address
        $address = new Address();
        $address->first_name = self::sanitize($shopifyAddress->getFirstName());
        $address->last_name = self::sanitize($shopifyAddress->getLastName());
        $address->company = self::sanitize($shopifyAddress->getCompany());
        $address->address1 = self::sanitize($shopifyAddress->getAddress1());
        $address->address2 = self::sanitize($shopifyAddress->getAddress2());
        $address->city = $shopifyAddress->getCity();
        $address->state = $shopifyAddress->getProvinceCode();
        $address->zip = $shopifyAddress->getZip();
        $address->country = $shopifyAddress->getCountryCode();
        $address->phone = $shopifyAddress->getPhone();
        return $address;
    }

    /**
     * @param $string
     * @return mixed
     */
    static function sanitize($string)
    {
        $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
        $string = str_replace('\n', '', $string);
        $string = str_replace('\r', '', $string);
        $string = str_replace('"', '', $string);
        $string = str_replace("'", '', $string);
        $string = str_replace("\\", '', $string);
        $string = trim($string);

        return $string;
    }
}
