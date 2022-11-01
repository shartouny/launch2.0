<?php

namespace SunriseIntegration\Shopify\Models\Order;

class OrderStatus
{
    const PENDING = 1;
    const PROCESSING = 2;
    const PRODUCTION = 3;
    const SHIPPED = 4;
    const FULFILLED = 5;
    const CANCELLED = 6;

    /**
     * @param $stateId
     * @return mixed|string
     */

    public static function get( $stateId ) {

        $states = [
            self::PENDING          => 'Pending',
            self::PROCESSING       => 'Processing',
            self::PRODUCTION       => 'Production',
	        self::FULFILLED        => 'Fulfilled',
	        self::SHIPPED          => 'Shipped',
	        self::CANCELLED        => 'Cancelled',
        ];

        return $states[ $stateId ] ?? 'Undefined';
    }
}
