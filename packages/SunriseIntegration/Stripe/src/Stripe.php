<?php

namespace SunriseIntegration\Stripe;

use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\CardException;

class Stripe
{
    protected static function getStripeClient()
    {
        return new StripeClient(config('stripe.api_secret'));
    }

    /**
     * @param string $email
     * @param string $description
     * @return \Stripe\Customer
     * @throws Exception
     */
    public static function createCustomer(string $email, string $description)
    {
        $stripe = self::getStripeClient();
        $stripePaymentMethod = config('app.env') !== 'local' ? 'card' : 'pm_card_visa';

        try {
            $customer = $stripe->customers->create([
                'email' => $email,
                'description' => $description
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $customer;
    }

    /**
     * @param string $id
     * @param array $data
     * @return \Stripe\Customer
     * @throws Exception
     */
    public static function updateCustomer(string $id, array $data)
    {
        $stripe = self::getStripeClient();

        try {
            $customer = $stripe->customers->update($id, $data);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $customer;
    }

    /**
     * @param int $limit
     * @return \Stripe\Collection
     * @throws Exception
     */
    public static function getCustomers(int $limit)
    {
        $stripe = self::getStripeClient();

        try {
            $customers = $stripe->customers->all(['limit' => $limit]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $customers;
    }

    /**
     * @param string $id
     * @return \Stripe\Customer
     * @throws Exception
     */
    public static function getCustomer(string $id)
    {
        $stripe = self::getStripeClient();

        try {
            $customer = $stripe->customers->retrieve($id, []);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $customer;
    }

    /**
     * @param string $customerId
     * @return \Stripe\SetupIntent
     * @throws Exception
     */
    public static function createIntent(string $customerId)
    {
        $stripe = self::getStripeClient();

        try {
            $intent = $stripe->setupIntents->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'payment_method_options' => [
                    'card' => [
                        'request_three_d_secure' => 'any'
                    ]
                ]
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $intent;
    }

    /**
     * @param string $customerId
     * @return \Stripe\Collection
     * @throws Exception
     */
    public static function getPaymentMethod(string $customerId)
    {
        $stripe = self::getStripeClient();

        try {
            $paymentMethod = $stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'card'
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        return $paymentMethod;
    }

    /**
     * @param string $paymentMethodId
     * @return \Stripe\PaymentMethod
     * @throws Exception
     */
    public static function detachPaymentMethod(string $paymentMethodId)
    {
        $paymentMethod = null;
        $stripe = self::getStripeClient();

        try {
            $paymentMethod = $stripe->paymentMethods->detach($paymentMethodId, []);
        } catch (Exception $e) {
            if (stripos($e->getMessage(), 'No such PaymentMethod') === false) {
                throw new Exception($e->getMessage());
            }
        }

        return $paymentMethod;
    }

    /**
     * @param string $customerId
     * @param string $paymentMethod
     * @param string $amount
     * @param string $currency
     * @return \Stripe\PaymentIntent
     * @throws \Stripe\Exception\ApiErrorException
     * @throws Exception
     */
    public static function createPaymentIntent(string $customerId, string $paymentMethod, string $amount, string $currency)
    {
        $stripe = self::getStripeClient();

        try {
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $customerId,
                'payment_method' => $paymentMethod,
                'off_session' => true,
                'confirm' => true
            ], [
                'idempotency_key' => self::v4()
            ]);
        } catch (CardException $e) {
            throw new Exception($e->getMessage());
        }

        return $paymentIntent;
    }


    /**
     * @param $email
     * @return \Stripe\Account
     * @throws \Stripe\Exception\ApiErrorException
     */
    public static function createAccountConnect($email)
    {
        return self::getStripeClient()->accounts->create([
            'country' => 'US',
            'type' => 'express',
            'email' => $email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);
    }

    /**
     * @param $id
     * @return \Stripe\AccountLink
     * @throws \Stripe\Exception\ApiErrorException
     */
    public static function createAccountLink($id, $platform_store_id)
    {

        //TODO replace the app url with the env variable
        $encrypt = base64_encode($platform_store_id);
        return self::getStripeClient()->accountLinks->create([
            'account' => $id,
            'refresh_url' => 'https://stage-app-2.teelaunch.com/integrations?r=' . $encrypt,
            'return_url' => 'https://stage-app-2.teelaunch.com/integrations?r=' . $encrypt,
            'type' => 'account_onboarding',
        ]);
    }

    public static function getConnectedAccount($id)
    {
        return self::getStripeClient()->accounts->retrieve($id);
    }

    /**
     * @param $id (account id)
     */
    public static function createDashboardLink($id)
    {
        return self::getStripeClient()->accounts->createLoginLink($id,[]);
    }

    /**
     * UUID V4 Key Generation
     * specified from https://www.php.net/manual/en/function.uniqid.php#94959
     *
     * @return string
     */
    protected static function v4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }


}
