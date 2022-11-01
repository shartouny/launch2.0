<?php

namespace App\Actions\Billing\Payoneer;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountPaymentMethod;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountPaymentMethodMetadata;

class StoreAccountPaymentMethodMetadataAction
{
  /**
   * @var array
   */
  protected $data;

  /**
   * @var string
   */
  protected $account_id;

  /**
   * @var string
   */
  protected $state;

  public function __construct(array $data, ?string $state)
  {
    $this->data = $data;
    $this->state = $state;
    $this->account_id = Redis::get("payoneer:$state");
  }

  public function __invoke(): bool
  {
    try {
      $this->createMetadataRows($this->data);

      $this->deleteAccountDataFromRedis();

      return true;
    } catch (Exception $e) {
      Log::debug($e->getMessage());
      return false;
    }
  }

  public function getAccountPaymentMethodId(): string
  {
    return AccountPaymentMethod::activePaymentMethod()
      ->where("account_id", $this->account_id)
      ->first()
      ->id;
  }

  public function filteredData($data): array
  {
    return [
      "access_token" => $data["access_token"],
      "expires_in" => Carbon::parse("+{$data["expires_in"]}sec")->format("Y-m-d H:i:s"),
      "refresh_token" => $data["refresh_token"],
      "id_token" => $data["id_token"]
    ];
  }

  public function createMetadataRows($data): void
  {
    foreach($this->filteredData($data) as $key => $val) {
      AccountPaymentMethodMetadata::create([
        "key" => $key,
        "value" => $val,
        "account_payment_method_id" => $this->getAccountPaymentMethodId()
      ]);
    }
  }

  public function deleteAccountDataFromRedis()
  {
    Redis::command("del", [
      "payoneer:$this->state"
    ]);
  }
}
