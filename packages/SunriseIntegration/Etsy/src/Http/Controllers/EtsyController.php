<?php

namespace SunriseIntegration\Etsy\Http\Controllers;

use App\Models\Platforms\Platform;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use SunriseIntegration\Etsy\API as EtsyAPI;
use SunriseIntegration\Etsy\Etsy;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStore;
use SunriseIntegration\TeelaunchModels\Utils\Logger;
use function Sentry\captureException;


class EtsyController extends Controller
{

    public $platformName = 'Etsy';
    public $etsy;
    public $shop;
    public $logger;

    //TODO: Create a PlatformController and move response param init, getter/setters into it
    public $responseParams;

    public function __construct()
    {
        $this->etsy = new EtsyAPI(config('etsy.api_key'), config('etsy.api_secret'));
        $this->logger = new Logger('etsy');

        $this->responseParams = new \stdClass();
        $this->responseParams->platform = $this->platformName;
        $this->responseParams->store = null;
        $this->responseParams->status = 'failed';
    }

    function requestInstall(Request $request)
    {
        $this->logger->info("Etsy Request Install");

        $callbackUrl = config('app.url') . "/etsy/app/install";
        try {
            $response = $this->etsy->getRequestToken($callbackUrl);
        } catch (Exception $e) {
            $this->logger->error($e);
            return $this->responseServerError();
        }

        $loginUrl = $response['login_url'] ?? null;
        $oauthToken = $response['oauth_token'] ?? null;//Will be passed in callback as oauth_token
        $oauthTokenSecret = $response['oauth_token_secret'] ?? null;//Need to store in cookie or DB
        $oauthConsumerKey = $response['oauth_consumer_key'] ?? null;//App identifier
        if (!$loginUrl) {
            $this->logger->error("Missing login url in Etsy response", $response);
            return $this->responseServerError();
        }

        try {
            Cookie::queue('etsy_secret', $oauthTokenSecret, $minutes = 15, $path = '/etsy/app', config('session.domain'), config('session.secure'), $httpOnly = true, false, $sameSite = 'lax');
            Cookie::queue('etsy_account', Auth::user()->account_id, $minutes = 15, $path = '/etsy/app', config('session.domain'), config('session.secure'), $httpOnly = true, false, $sameSite = 'lax');
        } catch (Exception $e) {
            $this->logger->error($e);
            return $this->responseServerError();
        }

        return response(['loginUrl' => $loginUrl]);
    }

    function install(Request $request)
    {
        $this->logger->info("Etsy Install");

        $tokens = null;

        $accountId = Cookie::get('etsy_account');

        // get temporary credentials from the url
        $request_token = $_GET['oauth_token'];

        // get the temporary credentials secret - this assumes you set the request secret
        // in a cookie, but you may also set it in a database or elsewhere
        $request_token_secret = Cookie::get('etsy_secret');

        // get the verifier from the url
        $verifier = $_GET['oauth_verifier'];

        $this->logger->info("Account: $accountId | token exists: " . ($request_token ? 'true' : 'false') . "  | secret exists: " . ($request_token_secret ? 'true' : 'false') . " | verifier: $verifier");

        $this->etsy->setAccessTokens($request_token, $request_token_secret);

        try {
            // set the verifier and request Etsy's token credentials url
            $tokens = $this->etsy->getAccessToken($verifier);
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        if (!$tokens) {
            return $this->responseServerError();
        }

        $oauthToken = $tokens['oauth_token'];
        $oauthTokenSecret = $tokens['oauth_token_secret'];

        //TODO: Get Shop info and save to DB
        $this->etsy->setAccessTokens($oauthToken, $oauthTokenSecret);
        $shopInfo = $this->etsy->getShopInfo();
        $this->logger->info("Get Shop info Response: ".json_encode($shopInfo));
        if (!$shopInfo || !is_object($shopInfo) || !$shopInfo->results) {
            $this->responseParams->status = 'failed';
            $params = [];
            foreach ($this->responseParams as $key => $value){
                $params[] = "$key=$value";
            }
            $paramString = implode('&',$params);
            $this->logger->error("Etsy shop info request failed");
            Log::error("Etsy install failed | Shop Response: ".json_encode($shopInfo));
            captureException(new Exception("Etsy install failed | Shop Response: ".json_encode($shopInfo)));
            return redirect("integrations?{$paramString}&");
        }

        $shopId = $shopInfo->results[0]->shop_id;
        $userId = $shopInfo->results[0]->user_id;
        $shopName = $shopInfo->results[0]->shop_name;

        $explodedUrl = explode('?',$shopInfo->results[0]->url);

        $url = $explodedUrl[0]; //str_replace('?utm_source=teelaunch2stage&utm_medium=api&utm_campaign=api', '', $shopInfo->results[0]->url);

        $platform = Platform::where('name', $this->platformName)->first();
        $platformStore = PlatformStore::where([['account_id', $accountId], ['platform_id', $platform->id], ['url', $url]])->withTrashed()->first() ?? new PlatformStore(); //PlatformStore::getByPlatformUrl($platform->id, $url) ?? new PlatformStore();
        $platformStore->account_id = $accountId;
        $platformStore->name = $shopName;
        $platformStore->url = $url;
        $platformStore->platform_id = $platform->id;
        $platformStore->enabled = true;
        $platformStore->deleted_at = null;
        $platformStore->save();

        if (config('app.env') === 'local') {
            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'api_token_local'
            ], [
                'value' => $oauthToken
            ]);

            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'api_secret_local'
            ], [
                'value' => $oauthTokenSecret
            ]);
        }

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'api_token'
        ], [
            'value' => encrypt($oauthToken)
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'api_secret'
        ], [
            'value' => encrypt($oauthTokenSecret)
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'url'
        ], [
            'value' => $url
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'shop_id'
        ], [
            'value' => $shopId
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'user_id'
        ], [
            'value' => $userId
        ]);

        $platformStore->settings()->updateOrCreate([
            'key' => 'orders_min_last_modified'
        ], [
            'value' => '-1 day'
        ]);

        Artisan::call("etsy:import-products-account --account_id=$accountId --platform_store_id=$platformStore->id");

        Artisan::call("etsy:import-orders-account --account_id=$accountId --platform_store_id=$platformStore->id");

        $this->responseParams->status = 'success';
        $this->responseParams->store = $shopName;
        $params = [];
        foreach ($this->responseParams as $key => $value){
            $params[] = "$key=$value";
        }
        $paramString = implode('&',$params);
        return redirect("integrations?{$paramString}&");
    }
}
