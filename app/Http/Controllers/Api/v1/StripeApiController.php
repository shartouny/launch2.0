<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Orders\Address;
use Exception;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

use App\Models\Orders\PaymentMethod;
use App\Models\Accounts\AccountPaymentMethod;
use App\Models\Accounts\AccountPaymentMethodMetadata;

use App\Http\Controllers\Controller;
use App\Http\Resources\Stripe\IntentResource;
use App\Http\Resources\Stripe\PaymentMethodResource;

use Illuminate\Support\Facades\Log;
use SunriseIntegration\Stripe\Stripe;

use Illuminate\Support\Facades\Crypt;

class StripeApiController extends Controller
{

    public function createInactivePaymentMethod()
    {
        $paymentMethod = PaymentMethod::stripe();
        $inactivePaymentMethod = new AccountPaymentMethod();
        $inactivePaymentMethod->account_id = Auth::user()->account_id;
        $inactivePaymentMethod->payment_method_id = $paymentMethod->id;
        $inactivePaymentMethod->is_active = false;
        $inactivePaymentMethod->save();

        return $inactivePaymentMethod;
    }

    /**
     * @param Request $request
     * @return Response|IntentResource
     */
    public function registerIntent(Request $request)
    {
        Log::debug("--------------- registerIntent ---------------");
        $user = Auth::user();
        $paymentMethod = PaymentMethod::stripe();

        $accountPaymentMethod = AccountPaymentMethod::where('payment_method_id', $paymentMethod->id)->first();
        Log::debug('$accountPaymentMethod: ' . json_encode($accountPaymentMethod, JSON_PRETTY_PRINT));


        if($accountPaymentMethod) {
            $customerId = AccountPaymentMethodMetadata::customerId($accountPaymentMethod->id);
            Log::debug('$customerId: ' . $customerId);
        }

        if ($accountPaymentMethod && $customerId) {
            try {
                $customer = Stripe::getCustomer($customerId);
            } catch (Exception $e) {
                return $this->responseNotFound($e->getMessage());
            }
        } else {
            if(!$accountPaymentMethod) {
                $accountPaymentMethod = $this->createInactivePaymentMethod();
            }
            try {
                $customer = Stripe::createCustomer($request->get('email'), "{$user->account->name} [{$user->account->id}]");
            } catch (Exception $e) {
                return $this->responseBadRequest($e->getMessage());
            }
        }

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => 'email'
        ], [
            'value' => $request->get('email')
        ]);

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => 'customer_id'
        ], [
            'value' => $customer->id
        ]);

        try {
            $intent = Stripe::createIntent($customer->id);
        } catch (Exception $e) {
            return $this->responseBadRequest($e->getMessage());
        }

        Log::debug('$intent: ' . json_encode($intent, JSON_PRETTY_PRINT));

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => 'setup_intent_id'
        ], [
            'value' => $intent->id
        ]);

        return new IntentResource($intent);
    }

    /**
     * @param Request $request
     * @return Response|PaymentMethodResource
     * @throws Exception
     */
    public function savePaymentMethod(Request $request)
    {
        Log::debug("--------------- registerIntent ---------------");
        Log::debug("Request: " . json_encode($request->all()));

        $request->validate([
            'id' => 'required|string',
            'payment_method' => 'required|string'
        ]);

        $paymentMethod = PaymentMethod::stripe();

        $setupIntentId = $request->get('id');

        $accountPaymentMethod = AccountPaymentMethod::where([
            'payment_method_id' => $paymentMethod->id,
        ])->with(['metadataInsecure' => function($q){
            $q->where('key','setup_intent_id');
        }])->orderBy('created_at','desc')->first();
        Log::debug('$accountPaymentMethod: ' . json_encode($accountPaymentMethod, JSON_PRETTY_PRINT));

        $metadataSetupIntentId = $accountPaymentMethod->metadataInsecure->pluck('value')->first();
        Log::debug('$metadataSetupIntentId: ' . $metadataSetupIntentId);

        if(!$accountPaymentMethod || $metadataSetupIntentId !== $setupIntentId){
            Log::warning('Setup intent ids do not match');
            throw new Exception('Failed to find account payment method');
        }

        $customerId = AccountPaymentMethodMetadata::customerId($accountPaymentMethod->id);

        $response = Stripe::getPaymentMethod($customerId);
        Log::debug('Stripe Response: ' . json_encode($response));
        $stripePaymentMethod = $response->data[0];

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => 'payment_method_token'
        ], [
            'value' => $request->get('payment_method')
        ]);

        $card = $stripePaymentMethod->card;

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => 'card'
        ], [
            'value' => $card->last4
        ]);

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => 'card_exp_month'
        ], [
            'value' => $card->exp_month
        ]);

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => 'card_exp_year'
        ], [
            'value' => $card->exp_year
        ]);

        $billingAddress = $stripePaymentMethod->billing_details;
        $name = explode(' ', $billingAddress->name);
        $lastName = array_pop($name);
        $firstName = implode(' ', $name);


        $billing = Address::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $billingAddress->phone,
            'address1' => $billingAddress->address->line1,
            'address2' => $billingAddress->address->line2,
            'city' => $billingAddress->address->city,
            'state' => $billingAddress->address->state,
            'zip' => $billingAddress->address->postal_code,
            'country' => $billingAddress->address->country
        ]);

        $accountPaymentMethod->billing_address_id = $billing->id;
        $accountPaymentMethod->is_active = true;
        $accountPaymentMethod->save();

        $accountPaymentMethod->metadataInsecure()->where('key', 'setup_intent_id')->delete();

        unset($accountPaymentMethod->metadataInsecure);

        return new PaymentMethodResource($accountPaymentMethod);
    }
}
