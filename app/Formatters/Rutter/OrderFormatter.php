<?php

namespace App\Formatters\Rutter;

use App\Models\Orders\Address;
use App\Models\Orders\Order;
use App\Models\Platforms\PlatformStore;
use Carbon\Carbon;

class OrderFormatter
{

    static function formatForPlatform($order, $platformStore)
    {

    }

    /**
     * @param array $orderData
     * @param PlatformStore $platformStore
     * @return mixed
     */
    static function formatForDb($orderData, $platformStore)
    {

        $order = new Order();
        $order->account_id = $platformStore->account_id;
        $order->platform_store_id = $platformStore->id;
        $order->platform_order_id = $orderData->id;
        $order->platform_order_number = $orderData->order_number;
        $order->platform_created_at = Carbon::createFromTimestamp(strtotime($orderData->created_at))->toDateTimeString();
        $order->platform_updated_at =  Carbon::createFromTimestamp(strtotime($orderData->updated_at))->toDateTimeString();
        $order->platform_data = json_encode($orderData);

        $shippingAddress = new Address();
        if(!empty($orderData->shipping_address)){
            $shippingAddress->first_name = self::sanitize($orderData->shipping_address->first_name);
            $shippingAddress->last_name = self::sanitize($orderData->shipping_address->last_name);
            $shippingAddress->address1 = self::sanitize($orderData->shipping_address->address1);
            $shippingAddress->address2 = self::sanitize($orderData->shipping_address->address2);
            $shippingAddress->city = self::sanitize($orderData->shipping_address->city);
            $shippingAddress->country = $orderData->shipping_address->country_code;
            $shippingAddress->phone = $orderData->shipping_address->phone;
            $shippingAddress->state = $orderData->shipping_address->state ?? '';
            $shippingAddress->zip = $orderData->shipping_address->zip ?? '';
            $shippingAddress->save();
        }


        $order->shipping_address_id = $shippingAddress->id ?? null;
        $order->billing_address_id = $shippingAddress->id ?? null;
        $order->email = $orderData->customer->email ?? null;
        $order->total = $orderData->total_price;

        return $order;
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
