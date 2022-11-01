<?php

namespace SunriseIntegration\Shopify\Http\Controllers;

use App\Formatters\Shopify\OrderFormatter;
use App\Formatters\Shopify\OrderLineItemFormatter;
use App\Http\Controllers\Controller;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Blanks\BlankInfoChart;
use App\Models\Orders\OrderStatus;
use App\Models\Platforms\Platform;

use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreSettings;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Mockery\Exception;
use GuzzleHttp\Client;
use SunriseIntegration\Shopify\Helpers\ShopifyHelper;
use SunriseIntegration\Shopify\Helpers\ShopifyDeliveryProfileHelper;
use SunriseIntegration\Shopify\API;
use Javascript;
use App\Models\Orders\Order;
use SunriseIntegration\Shopify\Models\Order as ShopifyOrder;
// use SunriseIntegration\Shopify\Models\Order;
use SunriseIntegration\TeelaunchModels\Models\Orders\OrderLineItem;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProduct;
use SunriseIntegration\TeelaunchModels\Utils\Logger;
use App\Formatters\Shopify\AddressFormatter;
use App\Helpers\AddressHelper;
use SunriseIntegration\Shopify\Models\Shopify;
use SunriseIntegration\Shopify\ShopifyManager;
use Illuminate\Support\Str;
use App\Models\Orders\OrderLog;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountSettings;

class ShopifyController extends Controller
{

    public $platformName = 'Shopify';
    public $shopify;
    public $shop;

    private $scopes = [
//        'read_customers',
//        'write_customers',
        'read_orders',
        'write_orders',
        'read_fulfillments',
        'write_fulfillments',
        'read_products',
        'write_products',
        'read_script_tags',
        'write_script_tags',
        'read_inventory',
        'write_inventory',
        'read_locations',
        'read_shipping',
        'write_shipping'
    ];

    function __construct(Request $request)
    {
        $this->shop = session()->get('shop') ?? null;

        if ($this->shop) {
            $this->shopify = new API([
                'key' => config('shopify.api_key'),
                'secret' => config('shopify.api_secret'),
                'shop' => $this->shop->url
            ]);
            $this->shopify->setAccessToken($this->shop->api_token);
        }
    }

    public function requestInstall(Request $request)
    {
        $logger = new Logger('shopify');
        $app_url = config('app.url');

        $api_key = config('shopify.api_key');

        $scopes = implode(',', $this->scopes);
        $redirect_uri = "{$app_url}/shopify/app/install";
        $nonce = str_random(40);

        $platform = Platform::where('name', $this->platformName)->first();
        $shopUrl = $request->shop;

        if (!$platform || !$shopUrl) {
            $logger->error('Install Request failed, missing platform or shop url', ['request' => $request]);
            return redirect(URL::to('/'));
        }

        try {
            $shop = PlatformStore::getByPlatformUrl($platform->id, $shopUrl) ?? new PlatformStore();
            $shop->name = $shopUrl;
            $shop->platform_id = $platform->id;
            $shop->url = $shopUrl;
            $shop->save();

            $shop->settings()->updateOrCreate([
                'platform_store_id' => $shop->id,
                'key' => 'secret'
            ], [
                'value' => $nonce
            ]);

//            $shop->settings()->updateOrCreate([
//                'platform_store_id' => $shop->id,
//                'key' => 'url'
//            ], [
//                'value' => $shopUrl
//            ]);
            $logger->debug('Shop found');
            $redirect = "https://{$shopUrl}/admin/oauth/authorize?client_id={$api_key}&scope={$scopes}&redirect_uri={$redirect_uri}&state={$nonce}";
            //Log::info($redirect);

            // use frame buster as shopify is stupid and doesn't do it on their end
            return view('frame_buster', ['url' => $redirect]);
            // return redirect($redirect);

        } catch (Exception $e) {
            $logger->error('Install Request failed', ['request' => $request, $e]);
            return redirect(URL::to('/'));
        }
    }

    public function install(Request $request)
    {
        $logger = new Logger('shopify');
        $redirectOnFail = URL::to('/');
        $logger->debug('Install');

        if (!($request->has('shop') && $request->has('timestamp') && $request->has('code') && $request->has('state') && $request->has('hmac'))) {
            $logger->error('Install failed', ['request' => $request]);
            return redirect($redirectOnFail);
        }

        Log::info("Installing Shopify App for $request->shop");

        $code = $request->input('code');
        $nonce = $request->input('state');
        $shopUrl = $request->shop;

        $redirectOnFail = "https://{$shopUrl}/admin/apps";

        $platform = Platform::where('name', $this->platformName)->first();

        if (strpos($shopUrl, 'myshopify.com') == false) {
            $logger->error('Shop missing myshopify.com domain', ['shop' => $shopUrl]);
            $logger->error('Install failed', ['request' => $request]);
            return redirect($redirectOnFail);
        }

        if (!$platform) {
            $logger->error('Platform not available for installation', ['name' => $this->platformName]);
            $logger->error('Install failed', ['request' => $request]);
            return redirect($redirectOnFail);
        }

        $shop = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);
        if (!$shop) {
            $logger->error('Shop URL not found in PlatformStoreSettings', ['platform_store_id' => $platform->id, 'url' => $shopUrl]);
            $logger->error('Install failed', ['request' => $request]);
            return redirect($redirectOnFail);
        }

        try {
           // $secret = $shop->settings()->where('key', 'secret')->first();
            $secret = PlatformStoreSettings::where([['platform_store_id',$shop->id],['key', 'secret']])->first();
            $logger->debug('Secret: '.json_encode($secret));
            if ($secret->value !== $nonce) {
                $logger->error('Secret does not match state', ['name' => $this->platformName, 'nonce' => $nonce, 'secret' => $secret->value]);
                $logger->error('Install failed', ['request' => $request]);
                return redirect($redirectOnFail);
            }
            $secret->delete();
        } catch (\Exception $e) {
            $logger->error($e);
            $logger->error('Install failed', ['request' => $request]);
            return redirect($redirectOnFail);
        }

        $postURL = "https://{$shopUrl}/admin/oauth/access_token";

        try {
            $client = new Client();
            $response = $client->request('POST', $postURL, [
                'json' => [
                    'client_id' => config('shopify.api_key'),
                    'client_secret' => config('shopify.api_secret'),
                    'code' => $code
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode == 200) {
                $body = \GuzzleHttp\json_decode($body);
                $scopes = $body->scope;
                $scopes = explode(',', $scopes);
                foreach ($this->scopes as $requiredScopes) {
                    if (!in_array($requiredScopes, $scopes)) {
                        // Scopes have been altered by user
                        // NOTE: Doesn't work...Shopify strips the read permissions
                        // Log::error("$this->shopUrl - $requiredScopes is missing from returned Shopify scopes");
                    }
                }

                $shop->enabled = true;
                $shop->settings()->updateOrCreate([
                    'platform_store_id' => $shop->id,
                    'key' => 'secret'
                ], [
                    'value' => str_random(20)
                ]);
                $shop->settings()->updateOrCreate([
                    'platform_store_id' => $shop->id,
                    'key' => 'api_token'
                ], [
                    'value' => encrypt($body->access_token)
                ]);
                $shop->save();

                $this->installHooks($shop);
                ShopifyHelper::setup_sizing_charts_script($shop);
                ShopifyHelper::setup_fulfillment_service($shop);

                $appUrl = config('shopify.app_url');

                Log::info("Redirecting to https://{$shopUrl}/admin/apps/$appUrl");

                return redirect("https://{$shopUrl}/admin/apps/$appUrl");
            } else {
                Log::error("OAuth API token request failed", [
                    'shop' => $shopUrl,
                    'response' => $body,
                    'http' => $statusCode
                ]);
            }
        } catch (Exception $e) {
            Log::error($e);
        }

        Log::error('Install failed', ['request' => $request]);
        return redirect($redirectOnFail);
    }

    public function index(Request $request)
    {
        $shopUrl = $request->shop ?? session('shop')->url;
        $platform = Platform::where('name', $this->platformName)->first();
        $shop = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);

        $logger = new Logger('shopify');

        // Check for existing shop, if not then continue to install
        if (!isset($shop) || $shop->api_token == null) {
            $logger->debug('Requesting Install');
            return $this->requestInstall($request);
        }

        //Verify access scopes
        $shopify = new API([
            'key' => config('shopify.api_key'),
            'secret' => config('shopify.api_secret'),
            'shop' => $shopUrl
        ], $logger);
        $shopify->setAccessToken($shop->api_token);

        $accessScopes = $shopify->getAccessScopes();


        if ($shopify->lastHttpCode() !== 200) {
            //User must have uninstalled and go through OAuth again
            return $this->requestInstall($request);
        }

        // convert std object to array
        $accessScopesArr = json_decode(json_encode($accessScopes), true);

        // make a flat string array from objects
        $currentScopes = array_map(function($x) {
            return $x['handle'];
        }, $accessScopesArr);

        $newScopes = json_decode(json_encode($this->scopes), true);

        // remove items we have from array
        foreach($newScopes as $nsi => $nsv){
            foreach($currentScopes as $csi => $csv){
                if($nsv == $csv){
                    unset($newScopes[$csi]);
                    break;
                }
            }
        }

        // see if we need to add new scopes
        if(count($newScopes) > 0) {
            $logger->info("Requesting reinstall, new scopes needed" . json_encode($newScopes));
            // need to reinstall with new scopes
            return $this->requestInstall($request);
        }

        // redirect user to the shopify auth page which will do the linking and sign in user
        $queryString = base64_encode($request->getQueryString());
        $shopUrl = $request->input('shop');
        return redirect("/shopify/auth?queryString=$queryString&shop=$shopUrl");
    }

    public function authenticate(Request $request)
    {
        $logger = new Logger('shopify');
        if(
            !isset($request->encoded_data)
        ) {
            // bad request
            return response()->json([
                "request" => $request->all(),
                "error" => "Missing parameters"
            ])->setStatusCode(400);
        }
        try {
            $data = base64_decode($request->encoded_data);
            // validate hmac from decoded original query string
            $dataParts = explode("&", urldecode($data));
            $hmac = explode("=", array_shift($dataParts))[1];
            $hmacVerifyString = implode("&", $dataParts);
            $logger->debug("hmac : $hmac , hmacVerifyString: $hmacVerifyString");
            if ($hmac !== hash_hmac('sha256', $hmacVerifyString, config('shopify.api_secret'))) {
                // hmac is valid
                $logger->debug("HMAC invalid - hmac : $hmac , hmacVerifyString: $hmacVerifyString");
                return response()->json([
                    "request" => $request->all(),
                    "error" => "Forbidden"
                ])->setStatusCode(403);
            }

            // get shop name from shopify hmac data
            parse_str($data, $shopifyData);
            $shopUrl = $shopifyData['shop'];

            // find the associated shop
            $platform = Platform::where('name', $this->platformName)->first();
            $shop = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);

            if ($shop && $shop->account_id) {
                // shop is associated, send login credentials
                $user = User::where('account_id', $shop->account_id)->first();
                if(!$user) {
                    return response()->json([
                        "request" => $request->all(),
                        "error" => "Unauthorized",
                    ])->setStatusCode(401);
                }
                $logger->debug('User Found, logging in');
                $token = Str::random(60);
                $user->api_token = $user->api_token ?  $user->api_token : $token;
                $user->save();

                // send the shop's users token and email verified to auto log them in
                return response()->json([
                    "request" => $request->all(),
                    "token" => $user->api_token,
                    "emailVerified" => $user->account->email_verified
                ])->setStatusCode(200);
            } else {
                // account not associated, respond ok without token
                return response()->json([
                    "request" => $request->all(),
                    "error" => "Account not associates",
                ])->setStatusCode(200);
            }

        } catch (\Exception $e) {
            $logger->error($e);
            return response()->json([
                "request" => $request->all(),
                "error" => "Something went wrong"
            ])->setStatusCode(400);
        }
    }

    public function associate(Request $request)
    {
        $logger = new Logger('shopify');
        if(
            !isset($request->encoded_data)
        ) {
            // bad request
            return response()->json([
                "request" => $request->all(),
                "error" => "Missing parameters"
            ])->setStatusCode(400);
        }

        if(!$request->bearerToken()){
            return response()->json([
                "request" => $request->all(),
                "error" => "Unauthorized, not logged in"
            ])->setStatusCode(401);
        }
        try {
            $data = base64_decode($request->encoded_data);
            // validate hmac from decoded original query string
            $dataParts = explode("&", urldecode($data));
            $hmac = explode("=", array_shift($dataParts))[1];
            $hmacVerifyString = implode("&", $dataParts);
            $logger->debug("hmac : $hmac , hmacVerifyString: $hmacVerifyString");
            if ($hmac !== hash_hmac('sha256', $hmacVerifyString, config('shopify.api_secret'))) {
                // hmac is valid
                $logger->debug("HMAC invalid - hmac : $hmac , hmacVerifyString: $hmacVerifyString");
                return response()->json([
                    "request" => $request->all(),
                    "error" => "Forbidden"
                ])->setStatusCode(403);
            }

            // get shop name from shopify hmac data
            parse_str($data, $shopifyData);
            $shopName = $shopifyData['shop'];

            // get user account id
            $user = User::where('api_token', $request->bearerToken())->first();
            $logger->debug("Bearer token: ". $request->bearerToken());
            if (!$user) {
                $logger->debug("Could not find user");
                return response()->json([
                    "request" => $request->all(),
                    "error" => "Unauthorized"
                ])->setStatusCode(401);
            }

            $platform = Platform::where('name', $this->platformName)->first();
            $shop = PlatformStore::getByPlatformUrl($platform->id, $shopName);

            // make association
            $shop->account_id = $user->account->id;
            $shop->save();
            $logger->debug("Associated " . $shopName ." with " . $user->account->id);

            // make sure the user is marked as email verified when associating with shopify. Can cause issues if not
            if(!$user->hasVerifiedEmail()){
                $user->markEmailAsVerified();
                $userAccount = $user->account;
                if($userAccount){
                    $userAccount->email_verified = true;
                    $userAccount->save();
                }
            }

            $logger->info("Initiating Product import");
            Artisan::call("shopify:import-products-account --account_id={$user->account->id} --platform_store_id=$shop->id");

            // add delivery profiles
            // TODO too slow. Need to move to a queue. Currently updated when sending products

            // $logger->info("Setting up delivery profiles");
            // $deliveryProfileHelper = new ShopifyDeliveryProfileHelper(
            //     $shop,
            //     $logger
            // );

            // $deliveryProfileHelper->updateDeliveryProfiles();


            return response()->json([
                "request" => $request->all(),
                "status" => "Ok"
            ])->setStatusCode(200);

        } catch (\Exception $e) {
            $logger->error($e);
            return response()->json([
                "request" => $request->all(),
                "error" => "Something went wrong"
            ])->setStatusCode(400);
        }
    }

    // unused
    public function register(Request $request)
    {
        $shop = session('shop');
        return response()->view('shopify::register', compact('shop'));
    }

    // unused
    public function createAccount(Request $request)
    {

        $errors = new MessageBag();

        $validatedData = $request->validate([
            'name' => 'bail|required|string',
            'email' => 'bail|required|email|max:255',
            'password' => 'bail|required|string'
        ]);

        if (!$validatedData) {
            if ($request->wantsJson()) {
                return response()->json($request->all(), 422);
            }
        }

        $platform = Platform::where('name', $this->platformName)->first();

        $shop = Shop::where([
            ['url', session('shop')->url],
            ['platform_id', $platform->id]
        ])->first();

        if ($shop->account_id !== null) {
            $errors->add('general', 'This shop has already been registered. Try <a href="' . route('shopify.login') . '">logging in.</a>');
            if ($request->wantsJson()) {
                $request['errors'] = $errors;
                return response()->json($request->all(), 422);
            }
            return response()->view('shopify::register', compact('shop', 'errors'));
        }

        $user = User::where('email', $request->email)->first();
        if ($user && config('app.env') !== 'local') {

            if ($user->password !== Hash::make($request->password)) {
                $errors->add('email', 'This email has already been taken.');
                if ($request->wantsJson()) {
                    $request['errors'] = $errors;
                    return response()->json($request->all(), 422);
                }
                return response()->view('shopify::register', compact('shop', 'errors'));
            }
        } else {
            if (!$user) {
                $create = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password)
                ];
                try {
                    $user = User::create($create);
                } catch (Exception $e) {
                    Log::error($e->getMessage(), ['shop' => $shop, 'create' => $create]);
                    $errors->add('general', 'Failed to create account. Try again.');
                    if ($request->wantsJson()) {
                        $request['errors'] = $errors;
                        return response()->json($request->all(), 422);
                    }
                    return response()->view('shopify::register', compact('shop', 'errors'));
                }
            }
        }

        $shop->account_id = $user->id;
        $shop->save();

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('shopify.setup-payments')]);
        }

        return response()->view('shopify::index', compact('shop'));
    }

    // unused
    public function login(Request $request)
    {
        $shop = session()->get('shop') ?? null;
        return view('shopify::login', compact('shop'));
    }

    // unused
    public function loginAccount(Request $request)
    {

        $validatedData = $request->validate([
            'email' => 'bail|required|email|max:255',
            'password' => 'bail|required|string'
        ]);

        if (!$validatedData) {
            return response()->json($request->all());
        }

        $user = User::where('email', $request->email)->first();

        if ($user && $user->password == Hash::make($request->password)) {
            if ($request->wantsJson()) {
                return response()->json(['redirect' => route('shopify.setup-payments')]);
            }
        }

        return response()->json(['errors' => ['general' => ['Wrong email and password combination']]], 422);
    }

    public function account()
    {
        $shop = session()->get('shop');
        $shopSettings = ShopSettings::where('shop_id', $shop->id)->first() ?? new ShopSettings();
        Javascript::put(['shop' => $shop, 'shop_settings' => $shopSettings]);
        return response()->view('shopify::account');
    }

    /**
     * @return array
     */
    public function getRequiredWebhooks()
    {
        $app_url = config('app.url');
        $hook_url = "{$app_url}/api/v1/shopify/hooks";

        return [
            [
                'format' => 'json',
                'topic' => 'app/uninstalled',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'orders/cancelled',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'orders/create',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'orders/fulfilled',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'orders/paid',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'orders/updated',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'orders/delete',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'products/create',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'products/update',
                'address' => $hook_url
            ],
            [
                'format' => 'json',
                'topic' => 'products/delete',
                'address' => $hook_url
            ]
        ];


    }

    /**
     * @param null $targetShop
     * @return API
     */
    public function getShopifyApi($targetShop = null)
    {
        $shop = $targetShop ?? session('shop');

        $shopify = new API([
            'key' => config('shopify.api_key'),
            'secret' => config('shopify.api_secret'),
            'shop' => $shop->url
        ]);

        $shopify->setAccessToken($shop->api_token);

        return $shopify;
    }

    public function installHooks($targetShop = null)
    {
        $shopify = $this->getShopifyApi($targetShop);

        try {
            $addWebhooks = $shopify->addWebhooks($this->getRequiredWebhooks());
        } catch (Exception $e) {
            Log::error('Failed to install webhooks', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * @param null $targetShop
     * @return object|null
     */
    public function getHooks($targetShop = null)
    {
        $shopify = $this->getShopifyApi($targetShop);

        try {
            return $shopify->getWebhooks()->webhooks;
        } catch (Exception $e) {
            Log::error('Failed to retrieve webhooks', ['exception' => $e->getMessage()]);
        }
        return null;
    }

    public function checkHooks($targetShop)
    {
        $shop = $targetShop ?? session('shop');

        $webhooks = $this->getHooks($shop);
        if (!$webhooks) {
            return null;
        }

        $shopify = $this->getShopifyApi($targetShop);

        $requiredWebhooks = $this->getRequiredWebhooks();
        //Update any existing webhooks
        foreach ($requiredWebhooks as $requiredWebhookIndex => $requiredWebhook) {
            foreach ($webhooks as $webhookIndex => $webhook) {
                if ($requiredWebhook['topic'] === $webhook->topic) {
                    if ($requiredWebhook['address'] !== $webhook->address) {
                        $webhook->address = $requiredWebhook['address'];
                        $shopify->updateWebhook($webhook);
                    }
                    unset($requiredWebhooks[$requiredWebhookIndex]);
                    unset($webhooks[$webhookIndex]);
                }
            }
        }

        //Webhooks left to install
        $shopify->addWebhooks($requiredWebhooks);

        //Delete remaining webhooks
        foreach ($webhooks as $webhook) {
            //$shopify->deleteWebhook($webhook->id);
        }
    }

    public function setupPayments()
    {
        $shop = session('shop');

        $user = User::where([['id', $shop->account_id]])->first();
        Javascript::put(['shop' => $shop, 'email' => $user->email, 'password' => $shop->secret]);
        return response()->view('shopify::setup-payments');
    }

    public function validationError()
    {
        return $this->responseBadRequest('Validation error');
        return response()->view('shopify::error');
    }

    function verifyWebhook($data, $hmac_header)
    {
        if (config('app.env') === 'local') {
            return true;
        }
        $calculated_hmac = base64_encode(hash_hmac('sha256', $data, config('shopify.api_secret'), true));
        return hash_equals($hmac_header, $calculated_hmac);
    }

    public function receiveHook(Request $request)
    {
        Log::debug('Webhook received', ['request' => $request]);

        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $apiVersion = $request->header('X-Shopify-Api-Version');
        $topic = $request->header('X-Shopify-Topic');
        $shopUrl = $request->header('X-Shopify-Shop-Domain');
        $body = $request->getContent();

        if (!$this->verifyWebhook($body, $hmac)) {
            Log::error('Webhook failed hmac');
            return response('Unauthorized', 401);
        }
        if (!$topic) {
            Log::error('Webhook failed, missing header X-Shopify-Topic');
            return response('Bad Request', 400);
        }
        if (!$shopUrl) {
            Log::error('Webhook failed, missing header X-Shopify-Shop-Domain');
            return response('Bad Request', 400);
        }

        $topicExploded = explode('/', $topic);
        $topicNamespace = $topicExploded[0];
        $topicAction = $topicExploded[1];
        switch ($topicNamespace) {
            case 'app':
                if ($topicAction === 'uninstalled') {
                    $this->uninstallApp($shopUrl);
                }
                break;
            case 'orders':
                $this->handleOrdersWebhook($topicAction, $shopUrl, $body);
                break;
            case 'shop':
                if ($topicAction === 'redact') {
                    $this->removeStoreData($shopUrl);
                }
                break;
            case 'customers':
                if ($topicAction === 'redact') {
                    $this->removeCustomerData($shopUrl, $body);
                }
                elseif ($topicAction === 'data_request') {
                    $response = $this->fetchCustomerData($shopUrl, $body);
                }
                break;
            default:
                break;
        }
        return response($response ?? 'Ok', 200);
    }

    public function removeStoreData($shopUrl)
    {
        $platform = Platform::where('name', $this->platformName)->first();
        $platformStore = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);

        $orders = Order::where('platform_store_id', $platformStore->id)->get();
        foreach ($orders as $order){
            $order->shippingAddress->delete();
            $order->billingAddress->delete();
        }

        Order::where('platform_store_id', $platformStore->id)->update([
            'email' => 'REDACTED',
            'platform_data' => [],
        ]);

        return response(null, 200);
    }

    public function removeCustomerData($shopUrl, $body){

        $data = json_decode($body);
        $customerEmail = $data['customer']['email'] ?? '';
        $ordersToRedact = $data['orders_to_redact'] ?? [];

        $platform = Platform::where('name', $this->platformName)->first();
        $platformStore = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);

        if($ordersToRedact){
            $orders = Order::where('platform_store_id', $platformStore->id)->whereIn('platform_order_number', $ordersToRedact)->get();
            foreach ($orders as $order){
                $order->shippingAddress->delete();
                $order->billingAddress->delete();
            }

            Order::where('email', $customerEmail)
                ->where('platform_store_id', $platformStore->id)
                ->whereIn('platform_order_number', $ordersToRedact)
                ->update([
                'email' => 'REDACTED',
                'platform_data' => [],
            ]);
        }
        else{
            $orders = Order::where('platform_store_id', $platformStore->id)->get();
            foreach ($orders as $order){
                $order->shippingAddress->delete();
                $order->billingAddress->delete();
            }

            Order::where('email', $customerEmail)->where('platform_store_id', $platformStore->id)->update([
                'email' => 'REDACTED',
                'platform_data' => [],
            ]);
        }

        return response(null, 200);
    }

    public function fetchCustomerData($shopUrl, $body){

        $data = json_decode($body);
        $customerEmail = $data['customer']['email'] ?? '';
        $ordersRequested = $data['orders_requested'] ?? [];

        $platform = Platform::where('name', $this->platformName)->first();
        $platformStore = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);

        if($ordersRequested){
            $orders = Order::where('email', $customerEmail)->where('platform_store_id', $platformStore->id)->whereIn('platform_order_number', $ordersRequested)->get()->toArray();
        }
        else{
            $orders = Order::where('email', $customerEmail)->where('platform_store_id', $platformStore->id)->get()->toArray();
        }

        return response(json_encode($orders), 200);
    }

    public function handleOrdersWebhook($topicAction, $shopUrl, $body)
    {
        $shopifyOrder = new ShopifyOrder(null, $body);
        $platform = Platform::where('name', $this->platformName)->first();
        $platformStore = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);
        $logger = new Logger('orders');
        switch ($topicAction) {
            case 'created':
                break;
            case 'updated':
                $logger->info("*** Update hook received ***");
                $order = Order::where([
                    ['platform_store_id', $platformStore->id],
                    ['platform_order_id', (string)$shopifyOrder->getId()]
                ])->first();

                if (!$order) {
                    $logger->info("Skip update, No Order found | Shopify Order: {$shopifyOrder->toJson()}");
                    return;
                }

                // If order is not less than pending, it has already been processed and updates are locked in our system
                if ($order->status > OrderStatus::PENDING) {
                    $logger->info("Skip update, Order status is past Pending | Order: {$order->id}");
                    return;
                }

                // set status to platform payment hold if shopify payment is pending
                if($shopifyOrder->getFinancialStatus() === 'pending'){
                    $order->status = OrderStatus::PLATFORM_PAYMENT_HOLD;
                } else {
                    // check to see if we move to hold order or set to pending
                    $accountSetting = AccountSettings::where([['account_id', $platformStore->account_id],['key','order_hold']])->first();
                    $holdOrders = $accountSetting ? boolval($accountSetting->value) : false;
                    $order->status = $holdOrders === true ? OrderStatus::HOLD : OrderStatus::PENDING;
                }

                $logger->info("Updating order: {$order->id}");

                // Update the addresses if we need to
                $formattedShippingAddress = AddressFormatter::formatForDb($shopifyOrder->getShippingAddress(), $platformStore);
                if(!AddressHelper::addressesMatch($order->shippingAddress, $formattedShippingAddress)){
                    AddressHelper::updateAddressInformation($order->shippingAddress, $formattedShippingAddress);
                    $order->shippingAddress->save();
                    $logger->info("Updated shipping address");
                }

                $formattedBillingAddress = AddressFormatter::formatForDb($shopifyOrder->getBillingAddress(), $platformStore);
                if(!AddressHelper::addressesMatch($order->billingAddress, $formattedBillingAddress)){
                    AddressHelper::updateAddressInformation($order->billingAddress, $formattedBillingAddress);
                    $order->shippingAddress->save();
                    $logger->info("Updated billing address");
                }

                $orderLineItems = $order->lineItems;
                $shopifyOrderLineItems = $shopifyOrder->getLineItems();

                //Find and compare Order and Shopify Line Items
                foreach ($orderLineItems as $orderLineItemIndex => $orderLineItem) {
                    // $logger->info("Check if Order Line Item needs updating | Order Line Item: {$orderLineItem}");
                    foreach ($shopifyOrderLineItems as $shopifyLineItemIndex => $shopifyLineItem) {
                        // shopify does not remove items, it just sets fulfillable quantity to 0
                        if($shopifyLineItem->getQuantity() > 0) {
                            $shopifyFormattedLineItem = OrderLineItemFormatter::formatForDb($shopifyLineItem, $platformStore, $logger);
                            $logger->debug("Order line item id: " . $orderLineItem-> platform_line_item_id . " - platform line item id: ". $shopifyFormattedLineItem->platform_line_item_id);
                            if ($orderLineItem->platform_line_item_id === $shopifyFormattedLineItem->platform_line_item_id) {
                                // check to see if anything changed
                                if (
                                    $orderLineItem->quantity !== $shopifyFormattedLineItem->quantity ||
                                    $orderLineItem->price !== $shopifyFormattedLineItem->price
                                ) {
                                    $orderLineItem->quantity = $shopifyFormattedLineItem->quantity;
                                    $orderLineItem->price = $shopifyFormattedLineItem->price;
                                    $orderLineItem->save();
                                    $logger->info("Order Line Item updated | Order Line Item: {$orderLineItem->id}");
                                } else {
                                    $logger->debug("Order Line Item doesn't need updating");
                                }
                                unset($orderLineItems[$orderLineItemIndex]);
                                unset($shopifyOrderLineItems[$shopifyLineItemIndex]);
                                break;
                            }
                        }
                    }
                }

                //Delete remaining Order Line Items
                foreach ($orderLineItems as $orderLineItemIndex => $orderLineItem) {
                    // this can fail if the order has been paid for, should not reach here if it has, but just in case
                    try {
                        $logger->info("Removing line item id: " . $orderLineItem->id);
                        $orderLineItem->delete();
                    } catch(Exception $e) {
                        $logger->error("Error removing line item id: " . $orderLineItem->id);
                    }
                }

                if(count($shopifyOrderLineItems) > 0){
                    // we need to add line items to order
                    $shopifyManager = new ShopifyManager('shopify', $order->account_id, $platformStore->id, $logger);

                    //Save remaining Shopify Line Items
                    foreach ($shopifyOrderLineItems as $shopifyLineItemIndex => $shopifyLineItem) {
                        // 0 quantities will still be here, but deleted, don't add them back
                        if($shopifyLineItem->getQuantity() > 0) {
                            $logger->info("Saving new shopify line item: " . $shopifyLineItem->getId());
                            $shopifyManager->ensureProductIsOnPlatform($shopifyLineItem);
                            $formattedLineItem = OrderLineItemFormatter::formatForDb($shopifyLineItem, $platformStore, $logger);
                            $formattedLineItem->order_id = $order->id;
                            // save the line item so we get associations before getting thumbnail
                            $formattedLineItem = $order->lineItems()->save($formattedLineItem);

                            $lineItemThumbnail = $shopifyManager->getLineItemThumbnail($shopifyLineItem);
                            if ($lineItemThumbnail) {
                                // determine extension
                                $lineItemThumbnailArr = explode('.', $lineItemThumbnail);
                                // Shopify urls have query strings sometimes, remove them
                                $ext = explode("?", $lineItemThumbnailArr[count($lineItemThumbnailArr) - 1])[0];
                                $logger->debug("Line item thumbnail: " . $lineItemThumbnail);
                                $formattedLineItem->saveFileFromUrl($lineItemThumbnail, Str::uuid() . '.' . $ext);
                            }
                        }
                    }
                }

                $order->logs()->create([
                    'message' => 'Order Updated',
                    'message_type' => OrderLog::MESSAGE_TYPE_INFO
                ]);

                // update order dates
                $formattedOrder = OrderFormatter::formatForDb($shopifyOrder, $platformStore);
                $order->platform_created_at = $formattedOrder->platform_created_at;
                $order->platform_updated_at = $formattedOrder->platform_updated_at;
                $order->save();

                break;
            case 'paid':
                // picked up by cron
                break;
            case 'cancelled':
                break;
            case 'delete':
                break;
            default:
                break;
        }
    }

    public function uninstallApp(string $shopUrl)
    {
        $platform = Platform::where('name', $this->platformName)->first();

        if (strpos($shopUrl, 'myshopify.com') == false) {
            Log::error('Shop missing myshopify.com domain', ['shop' => $shopUrl]);
            return response(null, 422);
        }

        $shop = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);
        if (!$shop) {
            Log::error('Shop URL not found in PlatformStoreSettings', ['platform_store_id' => $platform->id, 'url' => $shopUrl]);
            return response(null, 422);
        }

        try {
            $platformStoreSettingsId = $shop->settings->where('key', 'api_token')->pluck('id');
            PlatformStoreSettings::where('id', $platformStoreSettingsId)->delete();
        } catch (\Exception $e) {
            Log::error($e);
            return response(null, 422);
        }

        Log::info("Shop Uninstalled | $shopUrl");

        return response(null, 200);
    }

    public function saveAccount(Request $request)
    {
        //$shop = session()->get('shop');

        Validator::make($request->all(), [
            'shop_settings.id' => 'required|integer',
            'shop_settings.shop_id' => 'required|integer',
            'shop_settings.enabled' => 'boolean',
            'shop_settings.accrual_factor' => 'numeric',
            'shop_settings.cost_factor' => 'numeric',
            'shop_settings.new_customer_points' => 'numeric',
            'shop_settings.limits_maximum_accrual' => 'required|boolean',
            'shop_settings.maximum_accrual_limit' => 'nullable|integer|required_if:shop_settings.limits_maximum_accrual,1',
            'shop_settings.allows_discounted_purchases' => 'boolean',
            'shop_settings.timezone' => 'string',
        ], [
            'numeric' => 'Enter a value.',
            'shop_settings.maximum_accrual_limit.required_if' => 'Enter a maximum bankable points value.'
        ])->validate();

        if ($request->shop_settings['shop_id'] !== $this->shop->id) {
            $errors = new MessageBag();
            $errors->add('general', 'Validation failed');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        if ($request->shop_settings['limits_maximum_accrual'] == true && $request->shop_settings['new_customer_points'] > $request->shop_settings['maximum_accrual_limit']) {
            $errors = new MessageBag();
            $errors->add('shop_settings.accrual_factor', 'New customer points must be less than or equal to maximum bankable points');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        $shopSettings = ShopSettings::where('shop_id', $this->shop->id)->first() ?? new ShopSettings();
        try {
            $shopSettings->enabled = $request->shop_settings['enabled'];
            $shopSettings->accrual_factor = $request->shop_settings['accrual_factor'];
            $shopSettings->cost_factor = $request->shop_settings['cost_factor'];
            $shopSettings->new_customer_points = $request->shop_settings['new_customer_points'];
            $shopSettings->limits_maximum_accrual = $request->shop_settings['limits_maximum_accrual'];
            $shopSettings->maximum_accrual_limit = $request->shop_settings['maximum_accrual_limit'];
            $shopSettings->allows_discounted_purchases = $request->shop_settings['allows_discounted_purchases'];
            $shopSettings->timezone = $request->shop_settings['timezone'];
            $shopSettings->save();
        } catch (Exception $e) {
            Log::error('Failed to update shop settings', ['request' => $request->all()]);
            $errors = new MessageBag();
            $errors->add('general', 'Failed to save');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        return response()->json(['data' => $shopSettings], 200);
    }

    public function generatePassword(Request $request)
    {
        //$shop = session()->get('shop');

        $validatedData = $request->validate([
            'shop' => 'required'
        ]);

        if ($request->shop['id'] !== $this->shop->id) {
            $errors = new MessageBag();
            $errors->add('general', 'Validation failed');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        $shop = Shop::where('id', $this->shop->id)->first();
        if (!$shop) {
            Log::error('Failed to find shop to generate new password', ['request' => $request->all()]);
            $errors = new MessageBag();
            $errors->add('general', 'Failed to generate new password');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        try {
            $shop->secret = str_random(20);
            $shop->save();
        } catch (Exception $e) {
            Log::error('Failed to save new password', ['request' => $request->all()]);
            $errors = new MessageBag();
            $errors->add('general', 'Failed to generate new password');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        return response()->json(['password' => $shop->secret], 200);
    }

    public function customers()
    {
        //$shop = session()->get('shop');

        $customers = Customer::where('shop_id', $this->shop->id)->paginate();

        Javascript::put(['shop' => $this->shop, 'customers' => $customers]);
        return response()->view('shopify::customers');
    }

    //    public function customer(Request $request, $id)
    //    {
    //        //$shop = session()->get('shop');
    //
    //        $customer = Customer::where([['shop_id', $this->shop->id], ['id', $id]])->paginate();
    //        Javascript::put(['shop' => $this->shop, 'customer' => $customer]);
    //        return response()->view('shopify::customer');
    //    }

    public function fetchProductSizingChart($platformStoreProductId, Request $request)
    {
        header("Access-Control-Allow-Origin: *");

        $variantBlankIds = [];
        $variantsSizeCharts = [];
        $sizeCharts = [];

        $platform = Platform::where('name', $this->platformName)->first();
        $shopUrl = $request->storeUrl ?? '';

        $shopUrl = str_replace('https://', '', $shopUrl);
        $shopUrl = str_replace('http://', '', $shopUrl);

        $shop = PlatformStore::getByPlatformUrl($platform->id, $shopUrl);

        if($shop){
          $platformStoreProduct = PlatformStoreProduct::where('platform_product_id', (string)$platformStoreProductId)->where('platform_store_id', $shop->id)->first();
          if($platformStoreProduct){
            foreach ($platformStoreProduct->variants as $variant){
              $variantBlankIds[] = $variant->productVariant->blankVariant->blank_id;
            }

            $variantBlankIds = array_unique($variantBlankIds);

            foreach ($variantBlankIds as $variantBlankId) {
              $variantsSizeCharts[] = BlankInfoChart::getInfoChartDetails($variantBlankId);
            }

            if(!empty($variantsSizeCharts)){
              foreach ($variantsSizeCharts as $variantsSizeChart){
                if($variantsSizeChart){
                  $headers = [];
                  array_push($headers, $variantsSizeChart->column_1_header);
                  array_push($headers, $variantsSizeChart->column_2_header);
                  array_push($headers, $variantsSizeChart->column_3_header);

                  $sizeCharts[] = [
                    'name' => $variantsSizeChart->name,
                    'headers' => $headers,
                    'options' => $variantsSizeChart->blank_option_values,
                    'optionValues' => $variantsSizeChart->info_chart_units
                  ];
                }
              }
            }

          }

          return response($sizeCharts, 200);
        }

        Log::error('Shop URL not found in PlatformStoreSettings', ['platform_store_id' => $platform->id, 'url' => $shopUrl]);
        return response(['error' => 'Shop not found'], 422);
    }

    public function customer(Request $request)
    {
        //$shop = session()->get('shop');

        //Check for customer in DB
        if (isset($request->customer_id)) {
            $customer = Customer::where([['id', $request->customer_id], ['shop_id', $this->shop->id]])->first();
            Javascript::put(['shop' => $this->shop, 'customer' => $customer]);
            return response()->view('shopify::new-customer');
        }

        //When coming from Shopify->Customers->Actions page check if customer is in DB then redirect to proper URL using customer id
        if (isset($request->id)) {
            $customer = Customer::where([['platform_ref_num', $request->id], ['shop_id', $this->shop->id]])->first();
            if ($customer) {
                return redirect()->route('shopify.customer', ['customer_id' => $customer->id]);
            }
        }

        //If customer doesn't exist in DB
        $shopSettings = ShopSettings::where('shop_id', $this->shop->id)->first();

        $customer = new Customer();
        $customer->accrual_factor = $shopSettings->accrual_factor;
        $customer->points = $shopSettings->new_customer_points;

        if ($request->id) {
            $shopifyResponse = $this->shopify->getCustomer($request->id);

            if ($this->shopify->lastHttpCode() == 200) {
                $shopifyCustomer = $shopifyResponse->customer;
                $customer->name = $shopifyCustomer->first_name . " " . $shopifyCustomer->last_name;
                $customer->email = $shopifyCustomer->email;
                $customer->platform_ref_num = $shopifyCustomer->id;
            }
        }

        Javascript::put(['shop' => $this->shop, 'customer' => $customer]);

        return response()->view('shopify::new-customer');
    }

    public function createCustomer(Request $request)
    {
        Validator::make($request->all(), [
            'customer.name' => 'required|string',
            'customer.email' => 'required|email',
            'customer.accrual_factor' => 'required|numeric',
            'customer.points' => 'required|numeric',
        ])->validate();

        if ($request->shop['id'] !== $this->shop->id) {
            $errors = new MessageBag();
            $errors->add('general', 'Validation failed');
            $request->merge(['errors' => $errors]);
            return response()->json($request->errors, 422);
        }

        $customerExists = Customer::where([
            ['shop_id', $this->shop->id],
            ['email', $request->customer['email']]
        ])->first();

        if ($customerExists) {
            $errors = new MessageBag();
            $errors->add('general', 'Customer already invited');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        $platformRefNum = $request->customer['platform_ref_num'];
        //Check Shopify for customer reference number
        if (empty($platformRefNum)) {
            $searchBy['email'] = $request->customer['email'];
            $shopifyResponse = $this->shopify->searchCustomers($searchBy);
            if ($this->shopify->lastHttpCode() == 200) {
                $shopifyCustomers = $shopifyResponse->customers;
                if (!empty($shopifyCustomers[0])) {
                    $shopifyCustomer = $shopifyCustomers[0];
                    $platformRefNum = $shopifyCustomer->id;
                    $tagsArray = explode(', ', $shopifyCustomer->tags);
                    $tagsArray[] = config('shopify.tag');
                    $shopifyCustomer->tags = implode(', ', $tagsArray);
                    $shopifyResponse = $this->shopify->updateCustomer($platformRefNum, $shopifyCustomer);
                    if ($this->shopify->lastHttpCode() != 200) {
                        Log::error('Failed to update customer', [
                            'shop' => $this->shop,
                            'shopifyCustomer' => $shopifyCustomer,
                            'shopifyResponse' => $shopifyResponse
                        ]);
                    }
                }
            } else {
                Log::error('Failed to find customer in Shopify', [
                    'shop' => $this->shop,
                    'searchBy' => $searchBy,
                    'shopifyResponse' => $shopifyResponse
                ]);
            }
        }

        if (empty($platformRefNum)) {
            //Create in Shopify
            $fullNameArray = explode(' ', $request->customer['name']);
            $firstName = array_shift($fullNameArray);
            $lastName = implode(' ', $fullNameArray);
            $shopifyCustomer = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $request->customer['email'],
                'tags' => config('shopify.tag')
            ];
            $shopifyResponse = $this->shopify->createCustomer($shopifyCustomer);
            if ($this->shopify->lastHttpCode() == 201) {
                $platformRefNum = $shopifyResponse->customer->id;
            } else {
                Log::error('Failed to create customer in Shopify', [
                    'shop' => $this->shop,
                    'shopifyCustomer' => $shopifyCustomer,
                    'shopifyResponse' => $shopifyResponse
                ]);
            }
        }

        try {
            $customer = new Customer();
            $customer->shop_id = $this->shop->id;
            $customer->platform_id = $this->shop->platform_id;
            $customer->platform_ref_num = $platformRefNum;
            $customer->name = $request->customer['name'];
            $customer->email = $request->customer['email'];
            $customer->accrual_factor = $request->customer['accrual_factor'];
            $customer->points = $request->customer['points'];
            $customer->enabled = true;
            $customer->save();
        } catch (Exception $e) {
            Log::error('Failed to create customer', [
                'Exception' => $e->getMessage(),
                'shop' => $this->shop,
                'customer' => $customer
            ]);
        }

        try {
            $transaction = new Transaction();
            $transaction->shop_id = $this->shop->id;
            $transaction->customer_id = $customer->id;
            $transaction->platform_id = $this->shop->platform_id;
            $transaction->accrual_factor = $customer->accrual_factor;
            $transaction->points = $customer->points;
            $transaction->note = "Customer created";
        } catch (Exception $e) {
            Log::error('Failed to create customer', [
                'Exception' => $e->getMessage(),
                'shop' => $this->shop,
                'transaction' => $transaction
            ]);
        }

        try {
            Mail::to($customer)->send(new InviteCustomer($customer));
        } catch (Exception $e) {
            Log::error('Failed to email customer', [
                'Exception' => $e->getMessage(),
                'shop' => $this->shop,
                'customer' => $customer
            ]);
        }

        Javascript::put([
            'shop' => $this->shop,
            'customer' => $customer,
            'message' => "Invite sent to $customer->email"
        ]);

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('shopify.customer', ['id' => $customer->id])]);
        }

        return response()->redirectToRoute('shopify.customer', ['id' => $customer->id]);
    }

    public function updateCustomer(Request $request)
    {
        $customer_id = $request->customer_id;

        Validator::make($request->all(), [
            'customer.id' => 'required|numeric',
            'customer.name' => 'required|string',
            'customer.email' => 'required|email',
            'customer.accrual_factor' => 'required|numeric',
            'customer.points' => 'required|numeric',
        ])->validate();

        if ($request->shop['id'] != $this->shop->id || $customer_id != $request->customer['id']) {
            $errors = new MessageBag();
            $errors->add('general', 'Validation failed');
            $request->merge(['errors' => $errors]);
            return response()->json($request->errors, 422);
        }

        $customer = Customer::where([
            ['shop_id', $this->shop->id],
            ['id', $customer_id]
        ])->first();

        if (!$customer) {
            $errors = new MessageBag();
            $errors->add('general', 'Customer not found');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        try {
            DB::transaction(function () use ($request, $customer) {
                //Log the transaction if necessary
                $pointDiffference = $request->customer['points'] - $request->customer['original_points'];
                if ($pointDiffference !== 0 || $request->customer['accrual_factor'] !== $customer->accrual_factor) {

                    try {
                        $transaction = new Transaction();
                        $transaction->shop_id = $this->shop->id;
                        $transaction->customer_id = $customer->id;
                        $transaction->platform_id = $this->shop->platform_id;
                        $transaction->accrual_factor = $customer->accrual_factor;
                        $transaction->points = $pointDiffference;
                        $transaction->note = "Customer updated";
                        $transaction->save();
                    } catch (Exception $e) {
                        Log::error('Failed to create customer', [
                            'Exception' => $e->getMessage(),
                            'shop' => $this->shop,
                            'transaction' => $transaction
                        ]);
                    }
                }

                //Update customer
                try {
                    $customer->name = $request->customer['name'];
                    $customer->accrual_factor = $request->customer['accrual_factor'];
                    $customer->points = $request->customer['points'];
                    $customer->enabled = $request->customer['enabled'];;
                    $customer->save();
                } catch (Exception $e) {
                    Log::error('Failed to update customer', [
                        'Exception' => $e->getMessage(),
                        'shop' => $this->shop,
                        'customer' => $customer
                    ]);
                }
            }, 3);
        } catch (Exception $e) {
            $errors = new MessageBag();
            $errors->add('general', 'Update failed');
            $request->merge(['errors' => $errors]);
            return response()->json($request->all(), 422);
        }

        return response()->json(['customer' => $customer, 'message' => "Customer updated"]);
    }

    public function checkout(Request $request)
    {
        // Must receive a POST from Shopify with dara or don't process
        if (empty($request->x_signature)) {
            exit('No message');
        }

        $clientIP = ShopifyHelper::get_client_ip_env();

        // Get token from the store
        $shopName = $request->x_shop_name;
        $shopUrl = "$shopName.myshopify.com";

        $shop = Shop::where([['url', $shopUrl]])->first();

        if (!$shop) {
            exit('Sorry, your Points Account is not Configured');
        }

        $shopify = new API([
            'key' => config('shopify.api_key'),
            'secret' => config('shopify.api_secret'),
            'shop' => $shop->url
        ]);

        $shopify->setAccessToken($shop->api_token);

        echo '<pre>';
        print_r($_POST);
        echo '<hr>';

        //this is the password the user entered in the Payments section of the app
        ShopifyHelper::$storePassword = $shop->secret;

        // Send all POST parameters to generate the signature hash
        ShopifyHelper::generateSignature($_POST);

        if (ShopifyHelper::validateSignature($_POST['x_signature'])) {
            // only properly validated signature hashes should be trusted
            $validSignature = true;
            $checkoutToken = ShopifyHelper::getCheckoutToken($_POST);
            $checkoutData = $shopify->getCheckout($checkoutToken);
            //print_r($checkoutData); echo '<hr>';
        } else {
            exit('Validation Error');
        }


        if ($checkoutData->checkout->customer_id && $validSignature) {
            $customerData = $shopify->getCustomer($checkoutData->checkout->customer_id);
            $customerEmail = $customerData->customer->email;
            $hasAccount = $customerData->customer->state;

            $customer = Customer::where([['email', $customerEmail], ['shop_id', $shop->id]])->first();


            $transaction_id = rand(100000, 999999);

            $postBack = array(
                'x_account_id' => $_POST['x_account_id'],
                'x_amount' => $_POST['x_amount'],
                'x_currency' => $_POST['x_currency'],
                'x_gateway_reference' => $transaction_id,
                'x_reference' => $_POST['x_reference'],
                'x_result' => 'completed',
                'x_test' => $_POST['x_test'],
                'x_timestamp' => date('c'),
                'x_message' => '300 Points Used'
            );

            $completed_signature = ShopifyHelper::generateSignature($postBack);
        }
    }

    public function fetchTrackingNumbers(Request $request)
    {
        // endpoint is needed for fulfillment service, but not in use
        // tracking information sent to Shopify directly when we get it
        return response('Ok', 200);
    }

    public function fetchStock(Request $request)
    {
        // endpoint is needed for fulfillment service, but not in use
        // we are not tracking stock on fulfillment service, this should never be called
        return response('Ok', 200);
    }

    /**
     * A little end point GET reachable with /test for development
     */
    public function test(Request $request)
    {
        $logger = new Logger('shopify');
        $matches = [];
        $brokenVariantLinksAll = [];
        $page = 1;
        while(true){
            $brokenVariantLinksPaginator = ShopifyHelper::getBrokenVariantLinks($page);
            $brokenVariantLinks = $brokenVariantLinksPaginator->items();
            $brokenVariantLinksAll = array_merge($brokenVariantLinksAll, $brokenVariantLinks);
            if(count($brokenVariantLinks) == 0){
                break;
            }
            // $newMatches = ShopifyHelper::fixBrokenVariantLinks($brokenVariantLinks);
            // $matches = array_merge($matches, $newMatches);
            $page++;

        }

        return response()->json([
            "broken" => $brokenVariantLinksAll,
            "matches" => $matches
        ]);
    }
}
