<?php

namespace App\Actions\Billing\Payoneer;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DebitAccountBalanceAction extends BaseAction
{
  /**
   * @var int
   */
  protected $amount;
  /**
   * @var object
   */
  protected $balance;

  public function __construct(float $amount, object $balance, int $accountId)
  {
    parent::__construct($accountId);
    $this->amount = $amount;
    $this->balance = $balance;
  }

  /**
   * @return bool|object
   */
  public function __invoke()
  {
    try {
      $response = $this->debitAccount();

      if (!$response->successful()) {
        throw new Exception('Failed to debit account');
      }

      return $this->formatResponse($response);
    } catch (Exception $e) {
      Log::error($e->getMessage() . $e->getFile());
      return false;
    }
  }

  public function debitAccount(): Response
  {
    return Http::withHeaders([
      'Authorization' => 'Bearer ' . $this->access_token
    ])->post($this->getUrl(), $this->getDebitData());
  }

  public function getDebitData(): array
  {
    return [
      "client_reference_id" => Str::random(10),
      "amount" => $this->amount,
      "currency" => $this->balance->currency,
      "description" => "Charging a user",
      "to" => [
        "type" => "partner",
        "id" => config('payoneer.partner_id')
      ]
    ];
  }

  public function getUrl(): string
  {
    $account_id = $this->extractAccountIdFromToken();

    return config('app.env') !== 'production' ?
      config('payoneer.dev.api_url') . "accounts/$account_id/balances/{$this->balance->id}/payments/debit" :
      config('payoneer.prod.api_url') . "accounts/$account_id/balances/{$this->balance->id}/payments/debit";
  }
}
