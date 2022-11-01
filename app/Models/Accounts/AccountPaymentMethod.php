<?php

namespace App\Models\Accounts;

use App\Events\AccountPaymentMethodUpsert;
use App\Actions\Billing\Payoneer\CommitTransactionAction;
use App\Actions\Billing\Payoneer\DebitAccountBalanceAction;
use App\Actions\Billing\Payoneer\GetAccountBalanceAction;
use App\Models\Accounts\AccountPaymentMethod\ChargeDetails;
use Exception;
use Illuminate\Support\Facades\Log;
use SunriseIntegration\Paypal\Paypal;
use SunriseIntegration\Stripe\Stripe;

class AccountPaymentMethod extends \SunriseIntegration\TeelaunchModels\Models\Accounts\AccountPaymentMethod
{
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    /**
     * @var ChargeDetails
     */
    protected $chargeDetails;

    protected $dispatchesEvents = [
      'saved' =>  AccountPaymentMethodUpsert::class,
    ];

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new \App\Scopes\Account());
    }


    // ----- Actions ----- //

    /**
     * @param AccountPayment $accountPayment
     * @param string $currency
     * @return bool
     * @throws Exception
     */
    public function charge(AccountPayment $accountPayment, string $currency = 'USD'): bool
    {
        if (!$this->is_active) {
            throw new Exception('This payment method is not active');
        }

        $customerId = AccountPaymentMethodMetadata::customerId($this->id);

        if (!isset($customerId) && strtolower($this->paymentMethod->name) !== 'payoneer') {
            throw new Exception('Customer ID not found');
        }

        $paymentMethodToken = AccountPaymentMethodMetadata::paymentMethodToken($this->id);

        if (!isset($paymentMethodToken) && strtolower($this->paymentMethod->name) !== 'payoneer') {
            throw new Exception('Payment method token not found');
        }

        $amount = $accountPayment->amount;

        $chargeDetails = new ChargeDetails();
        $chargeDetails->setPlatform($this->paymentMethod->name);

        if (strtolower($this->paymentMethod->name) === 'stripe') {
            if (gettype($amount) == 'double') {
                $amount = round($amount * 100);
            }

            $response = Stripe::createPaymentIntent($customerId, $paymentMethodToken, $amount, $currency);

            $status = $response->status === 'succeeded' ? ChargeDetails::STATUS_SUCCESS : ChargeDetails::STATUS_FAIL;
            $chargeDetails->setStatus($status);
            $chargeDetails->setPlatformStatus($response->status);

            if ($response->last_payment_error) {
                $chargeDetails->setPlatformErrors($response->last_payment_error);
            }

            $chargeDetails->setPlatformTransactionId($response->id);
            $chargeDetails->setPlatformData(json_encode($response));
        }

        if (strtolower($this->paymentMethod->name) === 'payoneer') {
            $balance = (new GetAccountBalanceAction($accountPayment->account_id))();

            $debit = (new DebitAccountBalanceAction($amount, $balance, $accountPayment->account_id))();

            $commit = (new CommitTransactionAction($accountPayment->account_id))($debit->commit_id);

            $status = $commit->status_description === 'completed' ? ChargeDetails::STATUS_SUCCESS : ChargeDetails::STATUS_FAIL;
            $chargeDetails->setStatus($status);
            $chargeDetails->setPlatformStatus($commit->status);
            $chargeDetails->setPlatformTransactionId($commit->payment_id);
            $chargeDetails->setPlatformData(json_encode($commit));
        }

        if (strtolower($this->paymentMethod->name) === 'paypal') {
            Log::debug("Charge Braintree | Amount: $amount | Currency: $currency");

            $response = Paypal::newTransaction($paymentMethodToken, $accountPayment);

            Log::debug('BrainTree Response: ' . json_encode($response, JSON_PRETTY_PRINT));

            $status = $response->success === true && ($response->transaction->status === 'settling' || $response->transaction->status === 'settled') ? ChargeDetails::STATUS_SUCCESS : ChargeDetails::STATUS_FAIL;
            $chargeDetails->setStatus($status);

            if (isset($response->message)) {
                $chargeDetails->setPlatformMessage($response->message);
            }

            if (isset($response->errors)) {
                $chargeDetails->setStatus(ChargeDetails::STATUS_FAIL);
                $chargeDetails->setPlatformErrors($response->errors);
            }

            if (isset($response->transaction)) {
                $chargeDetails->setPlatformStatus($response->transaction->status);
                $chargeDetails->setPlatformTransactionId($response->transaction->id);
            }

            $chargeDetails->setPlatformData($response);
        }

        $this->chargeDetails = $chargeDetails;

        if ($chargeDetails->getStatus() == ChargeDetails::STATUS_FAIL) {
            return false;
        }

        return true;
    }

    /**
     * @return ChargeDetails
     */
    public function getChargeDetails()
    {
        return $this->chargeDetails;
    }

    /**
     * @return AccountPaymentMethod
     * @throws Exception
     */
    public function remove()
    {
        if (!$this->is_active) {
            throw new Exception('This payment method is not active');
        }

        $paymentMethodToken = AccountPaymentMethodMetadata::paymentMethodToken($this->id);

        if (!isset($paymentMethodToken)) {
            $this->is_active = false;
            $this->save();
            return $this;
            //throw new Exception('Payment method token not found');
        }

        if (strtolower($this->paymentMethod->name) === 'stripe') {
            Stripe::detachPaymentMethod($paymentMethodToken);
        }

        if (strtolower($this->paymentMethod->name) === 'paypal') {
            Paypal::deletePaymentMethod($paymentMethodToken);
        }

        $this->metadataInsecure()->where('key','payment_method_token')->update(['value' => null]);
        $this->is_active = false;
        $this->save();

        return $this;
    }
}
