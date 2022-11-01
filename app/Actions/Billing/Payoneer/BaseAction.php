<?php

namespace App\Actions\Billing\Payoneer;

use App\Models\Accounts\AccountPaymentMethod;
use Illuminate\Http\Client\Response;

class BaseAction
{
  /**
   * @var array
   */
  protected $metadata;
  /**
   * @var string
   */
  protected $id_token;
  /**
   * @var string
   */
  protected $access_token;

    /**
     * @var int
     */
  protected $accountId;

  public function __construct($accountId)
  {
    $this->accountId = $accountId;
    $this->metadata = $this->getAccountPaymentMetadata();
    $this->id_token = $this->getValueFromMetadata('id_token');
    $this->access_token = $this->getValueFromMetadata('access_token');
  }

  public function getAccountPaymentMetadata()
  {
      return AccountPaymentMethod::activePaymentMethod()
          ->where("account_id", $this->accountId)
          ->first()
          ->metadata
          ->toArray();
  }

  public function getValueFromMetadata(string $key): string
  {
    return array_flatten(
      array_filter(
        $this->metadata,
        function ($row) use ($key) {
            return $row['key'] === $key;
        }
      )
    )[1];
  }

  public function extractAccountIdFromToken(): string
  {
    return json_decode(
      base64_decode(
        str_replace('_', '/', str_replace('-','+',explode('.', $this->id_token)[1]))
      )
    )->account_id;
  }

  public function formatResponse(Response $response): object
  {
    return json_decode($response)->result;
  }
}
