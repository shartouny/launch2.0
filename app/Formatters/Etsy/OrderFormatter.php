<?php

namespace App\Formatters\Etsy;

use App\Formatters\IFormatter;
use App\Models\Orders\Address;
use App\Models\Orders\Order;
use App\Models\Orders\OrderStatus;
use App\Models\Platforms\PlatformStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use SunriseIntegration\Etsy\Helpers\EtsyHelper;
use SunriseIntegration\Etsy\Models\Receipt;
use SunriseIntegration\Etsy\Models\Transaction;

class OrderFormatter
{

    static function formatForPlatform($order, $platformStore)
    {

    }

    /**
     * @param \SunriseIntegration\Etsy\Models\Receipt $receipt
     * @param PlatformStore $platformStore
     * @return mixed
     */
    static function formatForDb($receipt, $platformStore)
    {

        $order = new Order();
        $order->account_id = $platformStore->account_id;
        $order->platform_store_id = $platformStore->id;
        $order->platform_order_id = $receipt->getReceiptId();
        $order->platform_order_number = $receipt->getReceiptId();
        $order->platform_created_at = Carbon::createFromTimestamp($receipt->getCreationTsz())->toDateTimeString();
        $order->platform_updated_at =  Carbon::createFromTimestamp($receipt->getCreationTsz())->toDateTimeString();
        $order->platform_data = $receipt->toJson();

        $fullName = $receipt->getName();
        $fullName = explode(' ', $fullName);

        $shippingAddress = new Address();
        $shippingAddress->first_name = self::sanitize(array_shift($fullName));
        $shippingAddress->last_name = self::sanitize(implode(' ',$fullName));
        $shippingAddress->address1 = self::sanitize($receipt->getFirstLine());
        $shippingAddress->address2 = self::sanitize($receipt->getSecondLine());
        $shippingAddress->city = $receipt->getCity();

        //$shippingAddress->company = null;

        $shippingAddress->country = EtsyHelper::convertCountryIdToISO($receipt->getCountryId());

        //$shippingAddress->phone = '';
        $shippingAddress->state = $receipt->getState();
        $shippingAddress->zip = $receipt->getZip();
        $shippingAddress->save();

        $order->shipping_address_id = $shippingAddress->id;
        $order->billing_address_id = $shippingAddress->id;

        $order->email = $receipt->getBuyerEmail();

        $order->total = $receipt->getGrandtotal();

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
