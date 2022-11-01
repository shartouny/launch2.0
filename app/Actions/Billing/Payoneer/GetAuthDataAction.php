<?php

namespace App\Actions\Billing\Payoneer;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class GetAuthDataAction
{
  /**
   * @var string $state
   */
  protected $state;

  public function __construct(string $state)
  {
    $this->state = $state;
  }

  public function __invoke(array $data)
  {
    try {
      if (!$this->stateMatch($data))
      {
        throw new Exception("Could not get associated user with the provided state");
      }

     return $this->retrieveData($data);

    } catch (Exception $e) {
      Log::debug($e->getMessage());
      return false;
    }

  }

  /**
   * Check if it can find an accound id associated with the 'payoneer:{state}' key
   * @param array $data
   * @return null|string
   */
  public function stateMatch(array $data)
  {
    return Redis::get("payoneer:{$data['state']}");
  }

  public function retrieveData($data): array
  {
    return [
      "code" => $data["code"],
      "redirect_uri" => config('payoneer.redirect_uri'),
      "state" => $data["state"]
    ];
  }
}
