<?php

namespace SunriseIntegration\Shopify\Models;

use App\Core\Shopify\OrderHandler;
use App\Core\ShopifyFactory;
use App\Model\Configuration;
use App\Model\Message;
use App\Model\ShippingMethod;
use SunriseIntegration\Shopify\Models\Order\LineItem;
use SunriseIntegration\Shopify\Models\Order\OrderType;
use PerformanceTeam\Core\Model\Order\Type;
use SunriseIntegration\Shopify\Models\Order\ShippingLine;
use SunriseIntegration\Shopify\Models\Order\Transaction;
use SunriseIntegration\App\Account;
use SunriseIntegration\App\Configuration as AppConfiguration;

/**
 * Class Order
 *
 * @method \SunriseIntegration\Shopify\Models\Address getBillingAddress()
 * @method getBrowserIp()
 * @method getBuyerAcceptsMarketing()
 * @method getCancelReason()
 * @method getCancelledAt()
 * @method getCartToken()
 * @method getClientDetails()
 * @method getClosedAt()
 * @method getCreatedAt()
 * @method getCurrency()
 * @method \SunriseIntegration\Shopify\Models\Customer getCustomer()
 * @method getDiscountCodes()
 * @method getEmail()
 * @method getFinancialStatus()
 * @method getFulfillments()
 * @method getFulfillmentStatus()
 * @method getTags()
 * @method getGateway()
 * @method getId()
 * @method getLandingSite()
 * @method \SunriseIntegration\Shopify\Models\Order\LineItem[] getLineItems()
 * @method getLocationId()
 * @method getName()
 * @method getNote()
 * @method getNoteAttributes()
 * @method getNumber()
 * @method getOrderNumber()
 * @method \SunriseIntegration\Shopify\Models\Order\Transaction\Payment\Detail getPaymentDetails()
 * @method getPaymentGatewayNames()
 * @method getProcessedAt()
 * @method getProcessingMethod()
 * @method getReferringSite()
 * @method getRefunds()
 * @method \SunriseIntegration\Shopify\Models\Order\ShippingLine[] getShippingLines()
 * @method getSourceName()
 * @method getSubtotalPrice()
 * @method getSubtotalPriceSet()
 * @method getTaxLines()
 * @method getTaxesIncluded()
 * @method getToken()
 * @method getTotalDiscounts()
 * @method getTotalLineItemsPrice()
 * @method getTotalPrice()
 * @method getTotalPriceSet()
 * @method getTotalShippingPriceSet()
 * @method getTotalTax()
 * @method getTotalTaxSet()
 * @method getTotalWeight()
 * @method getUpdatedAt()
 * @method getUserId()
 * @method getOrderStatusUrl()
 * @method getSendReceipt()
 * @method getSendFulfillmentReceipt()
 * @method \SunriseIntegration\Shopify\Models\Order\Transaction[] getTransactions()
 * @method getOrderName()
 * @method getPhone()
 *
 * @method setBrowserIp()
 * @method setBuyerAcceptsMarketing()
 * @method setCancelReason()
 * @method setCancelledAt($date)
 * @method setCartToken($token)
 * @method setClientDetails($details)
 * @method setClosedAt($date)
 * @method setCreatedAt($date)
 * @method setCurrency($currency)
 * @method setDiscountCodes($discount)
 * @method setEmail($email)
 * @method setFinancialStatus($financialStatus)
 * @method setFulfillments($fulfillment)
 * @method setFulfillmentStatus($fulfillmentStatus)
 * @method setTags($tags)
 * @method setGateway($gateway)
 * @method setId($id)
 * @method setLandingSite($landingSite)
 * @method setLocationId($locationId)
 * @method setName($name)
 * @method setNote($notes)
 * @method setNoteAttributes($noteAttributes)
 * @method setNumber($number)
 * @method setOrderNumber($orderNumber)
 * @method setPaymentGatewayNames($paymentGatewayNames)
 * @method setProcessedAt($processedAt)
 * @method setProcessingMethod($processingMethod)
 * @method setReferringSite($referringSite)
 * @method setRefunds($refunds)
 * @method setSourceName($sourceName)
 * @method setSubtotalPrice($subtotalPrice)
 * @method setTaxLines($taxLines)
 * @method setTaxesIncluded($isTaxIncluded)
 * @method setToken($token)
 * @method setTotalDiscounts($discounts)
 * @method setTotalLineItemsPrice($totalLineItemPrice)
 * @method setTotalPrice($totalPrice)
 * @method setTotalTax($totalTax)
 * @method setTotalWeight($totalWeight)
 * @method setUpdatedAt($date)
 * @method setUserId($userId)
 * @method setOrderStatusUrl($statusUrl)
 * @method setSendReceipt($shouldSendReceipt)
 * @method setSendFulfillmentReceipt($shouldSendfulfillmentReceipt)
 * @method setOrderName($orderName)
 * @method setPhone($phone)
 *
 * @package SunriseIntegration\Shopify\Models
 */
class Order extends AbstractEntity
{

    #region Properties
    protected $billing_address;
    protected $browser_ip;
    protected $buyer_accepts_marketing;
    protected $cancel_reason;
    protected $cancelled_at;
    protected $cart_token;
    protected $client_details;
    protected $closed_at;
    protected $created_at;
    protected $currency;
    protected $customer;
    protected $discount_codes = [];
    protected $email;
    protected $financial_status;
    protected $fulfillments = [];
    protected $fulfillment_status;
    protected $tags;
    protected $gateway;
    protected $id;
    protected $landing_site;
    protected $line_items = [];
    protected $location_id;
    protected $name;
    protected $note;
    protected $note_attributes;
    protected $number;
    protected $order_number;
    protected $payment_details;
    protected $payment_gateway_names;
    protected $processed_at;
    protected $processing_method;
    protected $referring_site;
    protected $refunds;
    protected $shipping_address;
    protected $shipping_lines = [];
    protected $source_name;
    protected $subtotal_price;
    protected $subtotal_price_set;
    protected $tax_lines = [];
    protected $taxes_included;
    protected $token;
    protected $total_discounts;
    protected $total_line_items_price;
    protected $total_price;
    protected $total_shipping_price_set;
    protected $total_tax;
    protected $total_weight;
    protected $updated_at;
    protected $user_id;
    protected $order_status_url;
    protected $send_receipt;
    protected $send_fulfillment_receipt;
    protected $transactions = [];
    protected $order_name;
    protected $phone;

    #endregion

    public function count()
    {
        $result = $this->getRequestData('/admin/orders/count.json');

        if (!empty($result)) {
            $result = json_decode($result);

            if (!empty($result->count)) {
                return $result->count;
            }
        }

        return 0;

    }

    public function load($data)
    {
        $entity = [];
        if (\is_string($data)) {
            $entity = json_decode($data, true);
            $entity = !empty($entity['order']) ? $entity['order'] : $entity;
        } else {
            $entity = !empty($data->order) ? $data->order : $data;
        }

        parent::load($entity);
    }


    /**
     * @param mixed $shipping_address
     * @return Order
     */
    public function setShippingAddress($shipping_address)
    {

        if (\is_array($shipping_address) || \is_object($shipping_address)) {

            $shippingAddress = new Address($this->getApiAuthorization());

            $shippingAddress->load($shipping_address);
            $this->shipping_address = $shippingAddress;

            return $this;
        }

        return $this;
    }

    /**
     * @return \SunriseIntegration\Shopify\Models\Address
     */
    public function getShippingAddress()
    {
        return $this->shipping_address;
    }

    /**
     * @param Transaction[] $transactions
     *
     * @return Order
     */
    public function setTransactions($transactions)
    {
        foreach ($transactions as $t) {
            if (\is_array($t) || \is_object($t)) {
                $shopifyTransaction = new Transaction($this->getApiAuthorization());
                $transactions->load($t);
                $this->transactions[] = $shopifyTransaction;
            } else {
                $this->transactions[] = $t;
            }

        }

        return $this;
    }

    /**
     * @param mixed $billing_address
     * @return Order
     */
    public function setBillingAddress($billing_address)
    {
        if (\is_array($billing_address) || \is_object($billing_address)) {
            $billingAddress = new Address($this->getApiAuthorization());
            $billingAddress->load($billing_address);
            $this->billing_address = $billingAddress;
            return $this;
        }

        $this->billing_address = $billing_address;
        return $this;
    }

    /**
     * @param string | array $customer
     * @return Order
     */
    public function setCustomer($customer)
    {
        if (\is_array($customer) || \is_object($customer)) {
            $shopifyCustomer = new Customer($this->getApiAuthorization());
            $shopifyCustomer->load($customer);
            $this->customer = $shopifyCustomer;
            return $this;
        }

        $this->customer = $customer;
        return $this;
    }

    /**
     * @param  $line_items
     * @return Order
     */
    public function setLineItems($line_items)
    {
        foreach ($line_items as $item) {
            if (\is_array($item) || \is_object($item) || \is_object($line_items)) {
                $lineItem = new LineItem($this->getApiAuthorization());
                $lineItem->load($item);
                $this->line_items[] = $lineItem;
            } else {
                $this->line_items[] = $item;
            }
        }
        return $this;
    }

    /**
     * @param mixed $payment_details
     * @return Order
     */
    public function setPaymentDetails($payment_details)
    {
        if (\is_array($payment_details) || \is_object($payment_details)) {
            $detail = new Transaction\Payment\Detail($this->getApiAuthorization());
            $detail->load($payment_details);
            $this->payment_details = $detail;
            return $this;
        }

        $this->payment_details = $payment_details;
        return $this;
    }


    /**
     * @param  ShippingLine[] | array $shipping_lines
     * @return Order
     */
    public function setShippingLines($shipping_lines)
    {
        foreach ($shipping_lines as $line) {
            if (\is_array($line) || \is_object($line) || \is_object($shipping_lines)) {
                $shippingLine = new ShippingLine($this->getApiAuthorization());
                $shippingLine->load($line);
                $this->shipping_lines[] = $shippingLine;
            } else {
                $this->shipping_lines[] = $line;
            }
        }

        return $this;
    }

    public static function save($shop, $order_id, $avsEnabled = false, $forceUpdate = false)
    {
        $member    = Account::where([['shop', $shop],['status','a']])->first();
        $shopifyAC = $member['shopify_ac'];

        if ($avsEnabled != null) {
            $checkAddress = $avsEnabled;
        } else {
            $avsCheckEnabled = Configuration::where([['shop', $shop], ['entity', 'avs_checked_enabled']])->first();
            $checkAddress    = $avsCheckEnabled->avs_check_enabled ?? false;
        }

        if ($forceUpdate) {
            $message = 'Force order update ' . ($checkAddress ? '(AVS enabled)' : '(AVS disabled)');
            try {
                $entityMessage = new Message();
                $entityMessage->setEntityId($order_id);
                $entityMessage->setEntityType(Message::ENTITY_TYPE_ORDER);
                $entityMessage->setMessageType(Message::MESSAGE_TYPE_INFO);
                $entityMessage->setDescription($message);
                $entityMessage->setSource($shop);
                $entityMessage->save();
            } catch (\Exception $exception) {

            }
        }

        $auth = new Auth();
        $auth->setApiKey(AppConfiguration::getApiKey());
        $auth->setPassword($shopifyAC);
        $auth->setSecret(AppConfiguration::getApiSecret());
        $auth->setStoreUrl($shop);
        ShopifyFactory::setAuthorization($auth);
        $order = ShopifyFactory::getOrderById($order_id);

        $shopifyOrder = new \SunriseIntegration\Shopify\Models\Order($auth);
        $shopifyOrder->load($order);
        $shopifyOrders[] = $shopifyOrder;

        $orderHandler = new OrderHandler($shop, $checkAddress, null, $forceUpdate);
        $orderHandler->process($shopifyOrders, 1);
    }

    public function mapShippingMethod($shop)
    {
        $shippingLines = $this->getShippingLines();
        if (empty($shippingLines)) {
            $message               = 'Order is missing shipping method.';
            return $this;
        }

        $title   = $shippingLines[0]->getTitle();
        $carrier = $shippingLines[0]->getSource();

        $shippingMethod = ShippingMethod::where([['shop', $shop], ['source_title', $title]])->first();

        if ($carrier == 'shopify') {
            $shippingLines[0]->setSource('custom');
        }

        if (!$shippingMethod) {
            $this->setShippingLines($shippingLines);
            return $this;
        }

        if ($shippingMethod->pt_title !== null) {
            $shippingLines[0]->setTitle($shippingMethod->pt_title);
            $shippingLines[0]->setCode($shippingMethod->pt_title);
        }
        if ($shippingMethod->pt_carrier !== null) {
            $shippingLines[0]->setSource($shippingMethod->pt_carrier);
        }

        $this->setShippingLines($shippingLines);

        return $this;
    }

    public function getType(){
        $orderType = OrderType::SALES_ORDER;
        if (!empty(self::getTags())) {
            $orderTags = explode(',', self::getTags());
            foreach ($orderTags as $orderTag) {
                if (trim(strtolower($orderTag)) === 'wholesale') {
                    $orderType = OrderType::WHOLESALE_ORDER;
                    break;
                }
            }
        }
        return $orderType;
    }

    public function getPerformanceTeamOrderComments(){
        $noteArray= [];
        $noteAttributes = $this->getNoteAttributes();
        if(count($noteAttributes) > 0) {
          foreach ($noteAttributes as $index => $noteAttribute) {
            $sanitizedName = json_decode(json_encode($noteAttribute['name']));
            $sanitizedValue = json_decode(json_encode($noteAttribute['value']));
            if (strlen($sanitizedValue) > 0) {
              $noteArray[$sanitizedName] = $sanitizedValue;
            }
          }
        }
        return $noteArray;
    }

}
