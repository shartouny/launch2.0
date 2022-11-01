<?php



namespace App\Helpers;
use App\Models\Orders\Address;

class AddressHelper
{
    /**
	 * Checks to see if 2 addresses match
     * @param Address $address1
     * @param Address $address2
     * @return boolean
     */
    public static function addressesMatch (
      $address1,
      $address2
    ) {
        if(
            $address1->first_name == $address2->first_name &&
            $address1->last_name == $address2->last_name &&
            $address1->company == $address2->company &&
            $address1->address1 == $address2->address1 &&
            $address1->address2 == $address2->address2 &&
            $address1->city == $address2->city &&
            $address1->state == $address2->state &&
            $address1->zip == $address2->zip &&
            $address1->country == $address2->country &&
            $address1->phone == $address2->phone
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
	 * Updates an address with information from another address and saves it
     * @param Address $address1 Address to copy data to
     * @param Address $address2 Address to copy data from
     * @return
     */
    public static function updateAddressInformation (
        &$address1,
        &$address2
    ) {
        $address1->first_name = $address2->first_name;
        $address1->last_name = $address2->last_name;
        $address1->company = $address2->company;
        $address1->address1 = $address2->address1;
        $address1->address2 = $address2->address2;
        $address1->city = $address2->city;
        $address1->state = $address2->state;
        $address1->zip = $address2->zip;
        $address1->country = $address2->country;
        $address1->phone = $address2->phone;
        return;
    }
}
