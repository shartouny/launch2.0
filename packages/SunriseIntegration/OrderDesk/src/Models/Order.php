<?php

namespace SunriseIntegration\OrderDesk\Models;

/**
 * Class Order
 * @package SunriseIntegration\OrderDesk\Models
 *
 * @method getId
 * @method getSourceId
 * @method getSourceName
 * @method getEmail
 * @method getShippingMethod
 * @method getQuantityTotal
 * @method getWeightTotal
 * @method getProductTotal
 * @method getShippingTotal
 * @method getHandlingTotal
 * @method getTaxTotal
 * @method getDiscountTotal
 * @method getOrderTotal
 * @method getCcNumber
 * @method getCcExp
 * @method getProcessorResponse
 * @method getPaymentType
 * @method getPaymentStatus
 * @method getProcessorBalance
 * @method getRefundTotal
 * @method getCustomerId
 * @method getEmailCount
 * @method getIpAddress
 * @method getTagColor
 * @method getTagName
 * @method getFulfillmentName
 * @method getFulfillmentId
 * @method getFolderId
 * @method getDateAdded
 * @method getDateUpdated
 * @method getCheckoutData
 * @method getOrderMetadata
 * @method getShipping
 * @method getCustomer
 * @method getReturnAddress
 * @method getDiscountList
 * @method getOrderNotes
 * @method getOrderShipments
 * @method getOrderItems
 *
 * @method setId(int $value)
 * @method setSourceId($value)
 * @method setSourceName($value)
 * @method setEmail($value)
 * @method setShippingMethod($value)
 * @method setQuantityTotal($value)
 * @method setWeightTotal($value)
 * @method setProductTotal($value)
 * @method setShippingTotal($value)
 * @method setHandlingTotal($value)
 * @method setTaxTotal($value)
 * @method setDiscountTotal($value)
 * @method setOrderTotal($value)
 * @method setCcNumber($value)
 * @method setCcExp($value)
 * @method setProcessorResponse($value)
 * @method setPaymentType($value)
 * @method setPaymentStatus($value)
 * @method setProcessorBalance($value)
 * @method setRefundTotal($value)
 * @method setCustomerId($value)
 * @method setEmailCount($value)
 * @method setIpAddress($value)
 * @method setTagColor($value)
 * @method setTagName($value)
 * @method setFulfillmentName($value)
 * @method setFulfillmentId($value)
 * @method setFolderId($value)
 * @method setDateAdded($value)
 * @method setDateUpdated($value)
 * @method setCheckoutData($value)
 * @method setOrderMetadata($value)
 * @method setShipping($value)
 * @method setCustomer($value)
 * @method setReturnAddress($value)
 * @method setDiscountList($value)
 * @method setOrderNotes($value)
 * @method setOrderShipments($value)
 * @method setOrderItems($value)
 */
class Order extends AbstractEntity
{

    protected $id;
    protected $source_id;
    protected $source_name;
    protected $email;
    protected $shipping_method;
    protected $quantity_total;
    protected $weight_total;
    protected $product_total;
    protected $shipping_total;
    protected $handling_total;
    protected $tax_total;
    protected $discount_total;
    protected $order_total;
    protected $cc_number;
    protected $cc_exp;
    protected $processor_response;
    protected $payment_type;
    protected $payment_status;
    protected $processor_balance;
    protected $refund_total;
    protected $customer_id;
    protected $email_count;
    protected $ip_address;
    protected $tag_color;
    protected $tag_name;
    protected $fulfillment_name;
    protected $fulfillment_id;
    protected $folder_id;
    protected $date_added;
    protected $date_updated;
    protected $checkout_data;
    protected $order_metadata;
    protected $shipping;
    protected $customer;
    protected $return_address;
    protected $discount_list;
    protected $order_notes;
    protected $order_shipments;
    protected $order_items;
}
