<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Accounts\AccountPaymentMethod;
use App\Models\Accounts\AccountPaymentMethodMetadata;
use App\Http\Resources\Accounts\AccountPaymentMethodResource;

class AccountPaymentMethodController extends Controller
{
    public function index()
    {
        $paymentMethod = AccountPaymentMethod::with('billingAddress')->orderBy('created_at', 'desc')->get();

        return new AccountPaymentMethodResource($paymentMethod);
    }

    public function show($id)
    {
        $paymentMethod = AccountPaymentMethod::with('billingAddress')->find($id);

        if (!$paymentMethod) {
            return $this->responseNotFound();
        }

        return new AccountPaymentMethodResource($paymentMethod);
    }

    public function active()
    {
        $activePaymentMethod = AccountPaymentMethod::with('billingAddress')->where('is_active', true)->first();

        if (isset($activePaymentMethod)) {
            $metadata = [];
            switch($activePaymentMethod->payment_method_id){
                case 1:
                    $metadata = AccountPaymentMethodMetadata::cardInfo($activePaymentMethod->id);
                    break;
                case 2:
                    $metadata =  AccountPaymentMethodMetadata::paypalInfo($activePaymentMethod->id);
                    break;
                case 3:
                    $metadata =  AccountPaymentMethodMetadata::payoneerInfo($activePaymentMethod->id);
                    break;
                default:
                    break;
            }
            $activePaymentMethod->metadata = $metadata;
        }

        return new AccountPaymentMethodResource($activePaymentMethod);
    }

    public function delete(String $id)
    {
        $paymentMethod = AccountPaymentMethod::where('id', $id)->first();
        $paymentMethod->remove();
        return $this->responseOk();
    }
}
