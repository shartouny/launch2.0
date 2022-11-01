<?php

namespace App\Actions\Billing\Payoneer;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RequestAuthTokenAction
{
  /**
   * @var int
   */
  protected $client_id;
  /**
   * @var string
   */
  protected $client_secret;
  /**
   * @var string
   */
  protected $request_token_url;
  /**
   * @var array
   */
  protected $auth_data;

  public function __construct(array $auth_data)
  {
    $this->client_id = config('payoneer.client_id');
    $this->client_secret = config('payoneer.client_secret');
    $this->request_token_url = $this->getRequestTokenUrl();
    $this->auth_data = $auth_data;
    $this->auth_data["grant_type"] = "authorization_code";
  }

  /**
   * @return array|bool
   */
  public function __invoke()
  {
    try {
      $response = $this->makeRequest();

      if (!$response->successful()) {
        throw new Exception("Request access token failed");
      }

      return $response->json();
    } catch (Exception $e) {
      Log::debug($e->getMessage());
      return false;
    }
  }

  public function getRequestTokenUrl(): string
  {
    return config('app.env') !== 'production'
      ? config('payoneer.dev.auth_base_url')
      : config('payoneer.prod.auth_base_url');
  }

  public function getAuthorizationHeader(): string
  {
    return "Basic " . base64_encode("{$this->client_id}:{$this->client_secret}");
  }

  public function makeRequest(): Response
  {
    return Http::withHeaders([ "Authorization" => $this->getAuthorizationHeader() ])
            ->post($this->request_token_url, $this->auth_data);
  }
}
