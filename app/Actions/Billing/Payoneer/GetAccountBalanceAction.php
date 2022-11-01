<?php

namespace App\Actions\Billing\Payoneer;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetAccountBalanceAction extends BaseAction
{
  protected $currency = 'USD';

  public function __construct($accountId)
  {
      parent::__construct($accountId);

  }

    /**
   * @return bool|object
   */
  public function __invoke()
  {
    try {
      $response = $this->getBalances();

      if ($response->failed()) {
        throw new Exception('Failed to get account balances');
      }

      return $this->getRelatedCurrencyBalance($response);
    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  public function getBalances()
  {
    return Http::withHeaders([
      'Authorization' => 'Bearer ' . $this->access_token
    ])->get($this->getUrl());
  }

  public function getUrl(): string
  {
    $account_id = $this->extractAccountIdFromToken();

    return config('app.env') !== 'production' ?
      config('payoneer.dev.api_url') . "accounts/$account_id/balances" :
      config('payoneer.prod.api_url') . "accounts/$account_id/balances";
  }

  public function getRelatedCurrencyBalance($response)
  {
    $balances = json_decode($response->body())
              ->result
              ->balances
              ->items;

    $balance = array_filter(
      $balances, function ($balance) {
        return $balance->currency === $this->currency;
      }
    );

    return $balance[array_keys($balance)[0]] ?? [];
  }
}
