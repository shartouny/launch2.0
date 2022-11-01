<?php

namespace SunriseIntegration\Paypal;

use App\Models\Accounts\AccountPayment;
use Exception;
use Braintree\Gateway;

class Paypal
{
    protected static function getGateway()
    {
        return new Gateway([
            'environment' => config('braintree.env') ?? 'sandbox',
            'merchantId' => config('braintree.merchant_id'),
            'publicKey' => config('braintree.public_key'),
            'privateKey' => config('braintree.private_key')
        ]);
    }

    public static function generateClientToken()
    {
      $gateway = self::getGateway();
      try {
          return $gateway->clientToken()->generate();
      } catch (Exception $e) {
          throw $e;
      }
    }

    public static function createCustomer($firstName, $lastName, $email)
    {
      $gateway = self::getGateway();
      try {
          return $gateway->customer()->create([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email
        ]);
      } catch (Exception $e) {
          throw $e;
      }
    }

    public static function createPaymentMethod($customerId, $nonce)
    {
      $gateway = self::getGateway();
      try {
          return $gateway->paymentMethod()->create([
            'customerId' => $customerId,
            'paymentMethodNonce' => $nonce
        ]);
      } catch (Exception $e) {
          throw $e;
      }
    }

    public static function deletePaymentMethod($token)
    {
      $gateway = self::getGateway();
      try {
          return $gateway->paymentMethod()->delete($token);
      } catch (Exception $e) {
          throw $e;
      }
    }

    /**
     * @param string $paymentMethodToken
     * @param AccountPayment|null $accountPayment
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     * @throws Exception
     */
    public static function newTransaction($paymentMethodToken, $accountPayment)
    {
        $gateway = self::getGateway();

        try {
            $result = $gateway->transaction()->sale([
                'paymentMethodToken' => $paymentMethodToken,
                'amount' => $accountPayment->amount,
                'purchaseOrderNumber' => $accountPayment->id,
                'options' => [
                    'submitForSettlement' => true
                ],
                'taxAmount' => $accountPayment->tax_total,
                'shippingAmount' => $accountPayment->shipping_total,
                'discountAmount' => $accountPayment->discount_total,

// TODO: Implement these fields to achieve Level 3 Authorization and save on transaction fees
//                'shipsFromPostalCode' => '60654',
//                'shipping' => [
//                    'firstName' => 'Clinton',
//                    'lastName' => 'Ecker',
//                    'streetAddress' => '1234 Main Street',
//                    'extendedAddress' => 'Unit 222',
//                    'locality' => 'Chicago',
//                    'region' => 'IL',
//                    'postalCode' => '60654',
//                    'countryCodeAlpha3' => 'USA'
//                ],
//                'lineItems' => [
//                    [
//                        'name' => 'Product',
//                        'kind' => Braintree\TransactionLineItem::DEBIT,
//                        'quantity' => '10.0000',
//                        'unitAmount' => '9.5000',
//                        'unitOfMeasure' => 'unit',
//                        'totalAmount' => '95.00',
//                        'taxAmount' => '5.00',
//                        'discountAmount' => '0.00',
//                        'productCode' => '54321',
//                        'commodityCode' => '98765'
//                    ]
//                ]
            ]);
        } catch(Exception $e) {
            throw $e;
        }

        return $result;
    }
}
