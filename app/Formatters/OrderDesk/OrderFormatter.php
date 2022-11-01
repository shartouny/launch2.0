<?php

namespace App\Formatters\OrderDesk;

use App\Models\Orders\OrderLineItem;
use SunriseIntegration\Etsy\Models\Receipt;
use SunriseIntegration\OrderDesk\Models\Address;
use SunriseIntegration\OrderDesk\Models\Discount;
use SunriseIntegration\OrderDesk\Models\Order;
use SunriseIntegration\OrderDesk\Models\ReturnAddress;

class OrderFormatter
{

    /**
     * @param \App\Models\Orders\Order $order
     * @return Order
     */
    static function formatForPlatform($order)
    {
        $orderPayments = $order->payments;

        $orderDeskOrder = new Order();
        $orderDeskOrder->setSourceId("$order->platform_order_id-$order->platform_order_number"); //$order->platform_order_number
        $orderDeskOrder->setSourceName("teelaunch app 2");//TODO: Maybe use "teelaunch app 2" as source name //$order->platform_order_number
        $orderDeskOrder->setEmail($order->email);

        $discountTotal = 0.00;
        $shippingTotal = 0.00;
        $taxTotal = 0.00;
        foreach ($order->payments as $payment){
            if($payment->accountPayment->status === 1){
                $discountTotal += $payment->discount;
                $shippingTotal += $payment->shipping_subtotal;
                $taxTotal += $payment->tax;
            }
        }

        //Push discounts to order desk if exists
        if($discountTotal > 0){
            $orderDiscount = new Discount();
            $orderDiscount->setName('Discount');
            $orderDiscount->setCode('');
            $orderDiscount->setAmount($discountTotal);

            $discountList = [];
            $discountList[] = $orderDiscount;
            $orderDeskOrder->setDiscountList($discountList);
        }

        $orderDeskOrder->setShippingTotal($shippingTotal);
        $orderDeskOrder->setDiscountTotal($discountTotal);
        $orderDeskOrder->setTaxTotal($taxTotal);

        $billingAddress = $order->billingAddress ?? $order->shippingAddress;
        $customer = new Address();
        $customer->setFirstName($billingAddress->first_name);
        $customer->setLastName($billingAddress->last_name);
        $customer->setCompany($billingAddress->company);
        $customer->setAddress1($billingAddress->address1);
        $customer->setAddress2($billingAddress->address2);
        $customer->setCity($billingAddress->city);
        $customer->setState($billingAddress->state);
        $customer->setPostalCode($billingAddress->zip);
        $customer->setCountry($billingAddress->country);
        $customer->setPhone($billingAddress->phone);
        $orderDeskOrder->setCustomer($customer);

        $shippingAddress = $order->shippingAddress;
        $shipping = new Address();
        $shipping->setFirstName($shippingAddress->first_name);
        $shipping->setLastName($shippingAddress->last_name);
        $shipping->setCompany($shippingAddress->company);
        $shipping->setAddress1($shippingAddress->address1);
        $shipping->setAddress2($shippingAddress->address2);
        $shipping->setCity($shippingAddress->city);
        $shipping->setState($shippingAddress->state);
        $shipping->setPostalCode($shippingAddress->zip);
        $shipping->setCountry($shippingAddress->country);
        $shipping->setPhone($shippingAddress->phone);
        $orderDeskOrder->setShipping($shipping);

        $shippingLabel = $order->account->shippingLabel;
        $shippingAddressMetadata = [];
        if ($shippingLabel) {
            $shippingAddress = $shippingLabel->shippingAddress;
            if ($shippingAddress) {
                $returnAddress = new ReturnAddress();
                $returnAddress->setName($shippingAddress->first_name);
                $returnAddress->setCompany($shippingAddress->company);
                $returnAddress->setAddress1($shippingAddress->address1);
                $returnAddress->setAddress2($shippingAddress->address2);
                $returnAddress->setCity($shippingAddress->city);
                $returnAddress->setState($shippingAddress->state);
                $returnAddress->setPostalCode($shippingAddress->zip);
                $returnAddress->setCountry($shippingAddress->country);
                $returnAddress->setPhone($shippingAddress->phone);
                $orderDeskOrder->setReturnAddress($returnAddress);

                $shippingAddressMetadata = [
                    'shipping_label_address_1' => $shippingAddress->address1,
                    'shipping_label_address_2' => $shippingAddress->address2,
                    'shipping_label_city' => $shippingAddress->city,
                    'shipping_label_state' => $shippingAddress->state,
                    'shipping_label_zip' => $shippingAddress->zip,
                    'shipping_label_country' => $shippingAddress->country
                ];
            }
        }

        $orderDeskLineItems = [];
        foreach ($order->lineItems as $lineItem) {
            $lineItemCost = self::wasLineItemPaidFor($lineItem, $orderPayments);
            if ($lineItemCost) {
                $orderDeskLineItems[] = OrderItemFormatter::formatForPlatform($lineItem, $lineItemCost);
            }
        }
        $orderDeskOrder->setOrderItems($orderDeskLineItems);

        $brandingImageMetadata = [];
        $brandingImages = $order->account->brandingImages;
        foreach ($brandingImages as $brandingImage) {
            $brandingImageMetadata[$brandingImage->brandingImageType->code] = $brandingImage->file_url;
        }

        $metadata = [
            'source' => 'teelaunch-2',
            'order_id' => $order->id,
            'account_id' => $order->account->id,
            'account_name' => $order->account->name,
            'account_email' => $order->account->user->email,
            'platform_name' => $order->store->platform->name,
            'platform_store_name' => $order->store->name,
            'platform_order_id' => $order->platform_order_id,
            'platform_order_number' => $order->platform_order_number,
            'store_url' => $order->store->url,
            'customer_service_email' => $shippingLabel ? $shippingLabel->email : null,
            'customer_note' => self::getCustomerNote($order),
            'special_message' => $shippingLabel ? $shippingLabel->message : null,
            'order_split_total' => 1,
            'discount' => $discountTotal
        ];

        $metadata = array_merge($metadata, $shippingAddressMetadata);

        $orderDeskOrder->setOrderMetadata($metadata);
        $orderDeskOrder->setCheckoutData($brandingImageMetadata);

        return $orderDeskOrder;
    }

    /**
     * @param OrderLineItem $lineItem
     * @param array $orderPayments
     * @return bool
     */
    static function wasLineItemPaidFor($lineItem, $orderPayments)
    {
        foreach ($orderPayments as $orderPayment) {
            if($orderPayment->accountPayment->status === 1) {
                foreach ($orderPayment->lineItems as $orderPaymentLineItem) {
                    if ($orderPaymentLineItem->order_line_item_id == $lineItem->id) {
                        return $orderPaymentLineItem->unit_cost;
                    }
                }
            }
        }
        return null;
    }

    static function getCustomerNote($order)
    {
        $platformName = $order->store->platform->name;
        switch (strtolower($platformName)) {
            case 'etsy':
                $receipt = new Receipt($order->platform_data);
                $messageFromBuyer = $receipt->getMessageFromBuyer();
                $giftMessage = $receipt->getGiftMessage();
                $customerNotes = [];
                if ($messageFromBuyer) {
                    $customerNotes[] = $messageFromBuyer;
                }
                if ($giftMessage) {
                    $customerNotes[] = $giftMessage;
                }
                return implode(' | ', $customerNotes);
                break;
            default:
                return null;
                break;
        }
    }
}
