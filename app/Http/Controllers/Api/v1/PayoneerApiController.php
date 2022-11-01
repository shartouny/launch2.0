<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\Billing\Payoneer\GetAuthDataAction;
use App\Actions\Billing\Payoneer\RequestAuthTokenAction;
use App\Actions\Billing\Payoneer\StoreAccountPaymentMethodMetadataAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountPaymentMethod;
use SunriseIntegration\TeelaunchModels\Models\Orders\PaymentMethod;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PayoneerApiController extends Controller
{
  /**
   * @var string|null
   */
  protected $account_id;

  public function preserve()
  {
    $state = Str::random(30);
    $saved = Redis::set("payoneer:$state", Auth::user()->account_id);

    if (!$saved) {
      return response([], 500);
    }

    return response()->json([
      "state" => $state
    ], 201);
  }

  public function authorizePayoneer(Request $request)
  {

    $this->setAccountId($request->state);

    $this->attachPaymentMethodToAccount();

    try {
      $auth_data = (new GetAuthDataAction($request->state))(
        $request->only("code", "redirect_uri", "state")
      );
      if (!$auth_data) {
        throw new Exception("State does not match");
      }

      $access_token_data = (new RequestAuthTokenAction($auth_data))();
      if (!$access_token_data) {
        throw new Exception("Something went wrong with requesting an access token");
      }

      $metadata = (new StoreAccountPaymentMethodMetadataAction($access_token_data, $request->state))();
      if (!$metadata) {
        throw new Exception("Metadata rows not created");
      }

      return view("billing.payoneer.info", [
        "success" => true
      ]);

    } catch (Exception $e) {
      Log::debug($e->getMessage());
      return view("billing.payoneer.info", [
        "success" => false,
        "message" => $e->getMessage()
      ]);
    }

  }

  public function setAccountId($value)
  {
    $this->account_id = Redis::get("payoneer:$value");
  }

  public function attachPaymentMethodToAccount()
  {
      AccountPaymentMethod::create([
        "account_id" => $this->account_id,
        "payment_method_id" => PaymentMethod::payoneer()->id,
        "is_active" => true
      ]);
  }

}
