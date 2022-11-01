<?php

namespace App\Actions\Billing\Payoneer;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommitTransactionAction extends BaseAction
{
  /**
   * @return bool|object
   */
  public function __invoke(string $id)
  {
    try {
      $response = $this->commit($id);

      if (!$response->successful()) {
        throw new Exception('Could not commit payment');
      }

      return $this->formatResponse($response);

    } catch (Exception $e) {
      Log::error($e->getMessage());
      return false;
    }
  }

  public function commit(string $id): Response
  {
    return Http::withHeaders([
      'Authorization' => 'Bearer ' . $this->access_token
    ])->put(
      $this->getUrl($id)
    );
  }

  public function getUrl(string $id): string
  {
    $account_id = $this->extractAccountIdFromToken();

    return config('app.env') !== 'production' ?
      config('payoneer.dev.api_url') . "/accounts/$account_id/payments/$id" :
      config('payoneer.prod.api_url') . "/accounts/$account_id/payments/$id";
  }
}