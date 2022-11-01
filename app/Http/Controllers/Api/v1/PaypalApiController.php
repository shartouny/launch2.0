<?php

namespace App\Http\Controllers\Api\v1;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use App\Http\Controllers\Controller;
use App\Models\Orders\PaymentMethod;

use Illuminate\Support\Facades\Auth;
use SunriseIntegration\Paypal\Paypal;
use App\Models\Accounts\AccountPaymentMethod;
use App\Models\Accounts\AccountPaymentMethodMetadata;

class PaypalApiController extends Controller
{
    private function saveMetadata(string $key, string $value): void
    {
        $paymentMethod = PaymentMethod::paypal();
        $accountPaymentMethod = AccountPaymentMethod::where([
            'account_id' => Auth::user()->account_id,
            'payment_method_id' => $paymentMethod->id,
            'is_active' => true
        ])->first() ?? new AccountPaymentMethod();
        $accountPaymentMethod->account_id = Auth::user()->account_id;
        $accountPaymentMethod->payment_method_id = $paymentMethod->id;
        $accountPaymentMethod->save();

        $accountPaymentMethod->metadataInsecure()->updateOrCreate([
            'account_payment_method_id' => $accountPaymentMethod->id,
            'key' => $key
        ], [
            'value' => $value
        ]);
    }

    public function authorizePaypal(): Response
    {
        try {
            $clientToken = Paypal::generateClientToken();
        } catch (Exception $e) {
            return $this->responseBadRequest($e->getMessage());
        }

        return $this->responseOk(['token' => $clientToken]);
    }

    public function savePaymentMethod(Request $request): Response
    {
        $nonce = $request->get('nonce');
        $payerId = $request->get('payerId');
        $firstName = $request->get('firstName');
        $lastName = $request->get('lastName');
        $email = $request->get('email');

        $activePaymentMethod = AccountPaymentMethod::activePaymentMethod();

        if (!$activePaymentMethod) {
            try {
                $response = Paypal::createCustomer($firstName, $lastName, $email);
            } catch(Exception $e) {
                return $this->responseServerError($e);
            }

            $customer = $response->customer;
            $customerId = $customer->id;
            $this->saveMetadata('customer_id', $customerId);
        } else {
            $customerId = AccountPaymentMethodMetadata::customerId($activePaymentMethod->id);
        }

        if (isset($payerId)) {
            $this->saveMetadata('payer_id', $payerId);
        }

        if (isset($firstName)) {
            $this->saveMetadata('first_name', $firstName);
        }

        if (isset($lastName)) {
            $this->saveMetadata('last_name', $lastName);
        }

        if (isset($email)) {
            $this->saveMetadata('email', $email);
        }

        try {
            $response = Paypal::createPaymentMethod($customerId, $nonce);
        } catch(Exception $e) {
            return $this->responseServerError($e);
        }

        $this->saveMetadata('payment_method_token', $response->paymentMethod->token);

        return $this->responseOk('Payment Method token stored');
    }
}
